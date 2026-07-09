<?php

namespace App\Providers\AiAnalysisProviders;

use App\Contracts\AiAnalysisProviderInterface;
use App\Data\AiAnalysisResult;
use App\Exceptions\AiAnalysisPermanentFailureException;
use App\Models\Post;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

/**
 * Google Gemini API 整合（FR-8）。單次請求同時取得摘要、情緒、標籤，
 * 要求模型以 JSON 格式回覆以避免額外的文字解析。
 */
class GeminiAiAnalysisProvider implements AiAnalysisProviderInterface
{
    private const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    private const VALID_SENTIMENTS = ['positive', 'negative', 'neutral'];

    private const RESPONSE_SCHEMA = [
        'type' => 'OBJECT',
        'properties' => [
            'summary' => ['type' => 'STRING'],
            'sentiment' => ['type' => 'STRING', 'enum' => self::VALID_SENTIMENTS],
            'tags' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
        ],
        'required' => ['summary', 'sentiment', 'tags'],
    ];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    /**
     * @throws AiAnalysisPermanentFailureException 回應格式永久不符預期（重試也不會成功）
     * @throws Throwable 網路逾時、5xx、限流等暫時性錯誤，交由 AnalyzePostJob 依 tries 重試
     */
    public function analyze(Post $post): AiAnalysisResult
    {
        // API key 走 header 而非 URL query string：Gemini 官方也支援 x-goog-api-key，
        // header 比 query string 更不容易被 proxy/access log、APM 工具意外記錄下來，
        // 與本專案其他外部 API 整合（ThreadsApiSearchProvider 用 Authorization header）一致。
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-goog-api-key' => $this->apiKey,
        ])
            ->timeout(30)
            ->post(self::API_BASE_URL . "/{$this->model}:generateContent", [
                'contents' => [
                    ['parts' => [['text' => $this->buildPrompt($post)]]],
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

        if (
            ! is_array($parsed)
            || ! isset($parsed['summary'], $parsed['sentiment'], $parsed['tags'])
            || ! is_string($parsed['summary'])
            || ! is_array($parsed['tags'])
            || ! in_array($parsed['sentiment'], self::VALID_SENTIMENTS, true)
        ) {
            throw new AiAnalysisPermanentFailureException(
                'Gemini response JSON does not match the expected summary/sentiment/tags shape.'
            );
        }

        return new AiAnalysisResult(
            summary: $parsed['summary'],
            sentiment: $parsed['sentiment'],
            tags: $parsed['tags'],
        );
    }

    private function buildPrompt(Post $post): string
    {
        return <<<PROMPT
            請針對以下 Threads 文章內容，用繁體中文提供：
            1. summary：一句話摘要（50字以內）
            2. sentiment：情緒判斷，只能是 positive、negative、neutral 三者之一
            3. tags：3 到 5 個主題標籤（繁體中文，不含 # 符號）

            文章內容：
            {$post->content}
            PROMPT;
    }
}
