<?php

namespace App\Providers\AiAnalysisProviders;

use App\Contracts\AiAnalysisProviderInterface;
use App\Data\AiAnalysisResult;
use App\Exceptions\AiAnalysisPermanentFailureException;
use App\Models\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

/**
 * Google Gemini API 整合（FR-8）。一次請求打包整批文章、同時取得每篇的摘要、
 * 情緒、標籤，藉此降低 API 請求次數以遵守免費層級的 RPM/RPD 額度限制。
 * 呼叫端（AnalyzePostJob）負責控制批次大小以避免單次請求超過 TPM 額度。
 */
class GeminiAiAnalysisProvider implements AiAnalysisProviderInterface
{
    private const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    private const VALID_SENTIMENTS = ['positive', 'negative', 'neutral'];

    private const RESPONSE_SCHEMA = [
        'type' => 'ARRAY',
        'items' => [
            'type' => 'OBJECT',
            'properties' => [
                'post_id' => ['type' => 'INTEGER'],
                'summary' => ['type' => 'STRING'],
                'sentiment' => ['type' => 'STRING', 'enum' => self::VALID_SENTIMENTS],
                'tags' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
            ],
            'required' => ['post_id', 'summary', 'sentiment', 'tags'],
        ],
    ];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    /**
     * @param  Collection<int, Post>  $posts
     * @return array<int, AiAnalysisResult>
     *
     * @throws AiAnalysisPermanentFailureException 回應格式永久不符預期（重試也不會成功）
     * @throws Throwable 網路逾時、5xx、限流等暫時性錯誤，交由 AnalyzePostJob 依 tries 重試
     */
    public function analyzeBatch(Collection $posts): array
    {
        // API key 走 header 而非 URL query string：Gemini 官方也支援 x-goog-api-key，
        // header 比 query string 更不容易被 proxy/access log、APM 工具意外記錄下來，
        // 與本專案其他外部 API 整合（ThreadsApiSearchProvider 用 Authorization header）一致。
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-goog-api-key' => $this->apiKey,
        ])
            ->timeout(60)
            ->post(self::API_BASE_URL . "/{$this->model}:generateContent", [
                'contents' => [
                    ['parts' => [['text' => $this->buildPrompt($posts)]]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => self::RESPONSE_SCHEMA,
                ],
            ])
            ->throw();

        $text = $response->json('candidates.0.content.parts.0.text');

        if ($text === null) {
            // 沒有候選文字通常代表安全過濾器封鎖（finishReason=SAFETY/RECITATION）或
            // 提示詞本身被擋下，這類情況重試不會有不同結果。
            throw new AiAnalysisPermanentFailureException(
                'Gemini response missing candidate text (likely blocked by safety filters).'
            );
        }

        try {
            $parsed = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // MAX_TOKENS 截斷等原因導致輸出不是合法 JSON，屬於永久性錯誤：
            // 同樣的文章內容重試仍會得到同樣被截斷的結果。
            throw new AiAnalysisPermanentFailureException(
                "Gemini response is not valid JSON: {$e->getMessage()}", previous: $e
            );
        }

        if (! is_array($parsed)) {
            throw new AiAnalysisPermanentFailureException(
                'Gemini response JSON is not an array of per-post results.'
            );
        }

        $validPostIds = $posts->pluck('id')->all();
        $results = [];

        foreach ($parsed as $item) {
            if (
                ! is_array($item)
                || ! isset($item['post_id'], $item['summary'], $item['sentiment'], $item['tags'])
                || ! is_int($item['post_id'])
                || ! is_string($item['summary'])
                || ! is_array($item['tags'])
                || ! in_array($item['sentiment'], self::VALID_SENTIMENTS, true)
                || ! in_array($item['post_id'], $validPostIds, true)
            ) {
                // 單一項目格式錯誤或 post_id 對不上這批文章時只跳過該筆，不影響
                // 同批次中其他格式正確的結果——批次的其中一兩篇異常不該讓整批作廢。
                continue;
            }

            if (isset($results[$item['post_id']])) {
                // Gemini 的 responseSchema 沒有 uniqueItems 限制，理論上可能對同一個
                // post_id 回傳兩筆結果；記錄下來以便觀察是否為 batch size 過大或
                // 提示詞不夠清楚導致，避免這種資料品質問題完全無跡可循。
                Log::warning("GeminiAiAnalysisProvider: duplicate post_id {$item['post_id']} in batch response, keeping the last occurrence.");
            }

            $results[$item['post_id']] = new AiAnalysisResult(
                summary: $item['summary'],
                sentiment: $item['sentiment'],
                tags: $item['tags'],
            );
        }

        if (empty($results)) {
            throw new AiAnalysisPermanentFailureException(
                'Gemini response contained no valid per-post results matching this batch.'
            );
        }

        return $results;
    }

    /**
     * @param  Collection<int, Post>  $posts
     */
    private function buildPrompt(Collection $posts): string
    {
        $articles = $posts->map(fn (Post $post) => <<<ARTICLE
            post_id: {$post->id}
            內容：{$post->content}
            ARTICLE)->implode("\n\n---\n\n");

        return <<<PROMPT
            以下是多篇 Threads 文章，請針對「每一篇」個別提供分析結果，並以 JSON 陣列回傳，
            陣列中每個元素對應一篇文章，須包含：
            1. post_id：對應下方文章的 post_id，必須原樣照抄，不可更改或省略
            2. summary：該篇文章的一句話摘要（50字以內，繁體中文）
            3. sentiment：情緒判斷，只能是 positive、negative、neutral 三者之一
            4. tags：3 到 5 個主題標籤（繁體中文，不含 # 符號）

            陣列元素數量必須等於文章數量，每篇文章都要有對應的分析結果。

            文章列表：
            {$articles}
            PROMPT;
    }
}
