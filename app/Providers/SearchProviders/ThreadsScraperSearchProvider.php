<?php

namespace App\Providers\SearchProviders;

use App\Contracts\SearchProviderInterface;
use App\Data\PostData;
use App\Data\SearchQuery;
use App\Exceptions\ScraperBlockedException;
use App\Support\Parsers\ThreadsScraperPostParser;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * 非官方資料來源：以 headless browser（scripts/threads-scraper.mjs，Playwright）
 * 直接解析 https://www.threads.com/search 公開頁面 DOM，取代受限於 App Review 的
 * 官方 threads_keyword_search 權限（見 ThreadsApiSearchProvider）。
 *
 * 未經 Meta App Review、不呼叫 graph.threads.net，形式上違反 Meta Automated Data
 * Collection Terms，且無官方 SLA／隨時可能因頁面改版或反爬機制而失效。
 * 依使用者裁決僅用於個人非商業用途，不適用於商業 SaaS 場景。
 */
class ThreadsScraperSearchProvider implements SearchProviderInterface
{
    private const LOCK_KEY = 'threads_scraper:global_lock';

    public function __construct(
        private readonly ThreadsScraperPostParser $parser,
        private readonly string $nodeBinary,
        private readonly string $scriptPath,
        private readonly int $timeoutSeconds,
        private readonly int $lockTtlSeconds,
    ) {
        // 鎖 TTL 只需覆蓋「拿到鎖之後、scrape() 實際執行」的時間，不含等鎖排隊的時間
        // （block() 等待期間尚未持有鎖，不消耗 TTL）。若 TTL 未明顯大於 process
        // timeout，緩衝會被 Node 啟動開銷、GC 停頓等疊加延遲吃光，導致鎖在 scrape()
        // 仍執行中就過期、被第二個等待者取得而讓兩個 Chromium 行程同時啟動——
        // 正是這把鎖要防的情況，故在建構時就擋下明顯不合理的設定值。
        if ($this->lockTtlSeconds <= $this->timeoutSeconds) {
            throw new RuntimeException(
                'lockTtlSeconds 必須大於 timeoutSeconds，否則併發鎖可能在爬蟲仍執行中就過期。'
            );
        }
    }

    public function search(SearchQuery $query): array
    {
        // 全域併發鎖：headless browser 沒有官方配額機制可依循，多個 queue worker
        // 或「立即巡檢全部」批次觸發時若同時各自啟動 Chromium 打 threads.com，
        // 會大幅提高被偵測封鎖的風險。等鎖逾時（超過 timeoutSeconds）直接失敗，
        // 讓 job 走正常的失敗/重試流程，而非無限排隊卡住整個 queue。
        $lock = Cache::lock(self::LOCK_KEY, $this->lockTtlSeconds);

        try {
            return $lock->block($this->timeoutSeconds, fn () => $this->scrape($query));
        } catch (LockTimeoutException $e) {
            throw new RuntimeException(
                "ThreadsScraperSearchProvider: 等待併發鎖逾時（關鍵字：{$query->keyword}），可能有其他巡檢正在執行。",
                previous: $e
            );
        }
    }

    /**
     * @return PostData[]
     */
    private function scrape(SearchQuery $query): array
    {
        Log::warning('ThreadsScraperSearchProvider: 使用非官方 headless browser 爬蟲取得資料', [
            'keyword' => $query->keyword,
        ]);

        $process = new Process([$this->nodeBinary, $this->scriptPath, $query->keyword]);
        $process->setTimeout($this->timeoutSeconds);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = json_decode($process->getOutput(), true);

        if (! is_array($output) || isset($output['error'])) {
            throw new RuntimeException(
                'threads-scraper.mjs 回傳格式錯誤：' . (is_array($output) ? ($output['error'] ?? '') : $process->getOutput())
            );
        }

        if ($output['blocked'] ?? false) {
            throw new ScraperBlockedException(
                "疑似被 Threads 封鎖或選擇器已失效（關鍵字：{$query->keyword}）。"
            );
        }

        $posts = [];

        foreach ($output['data'] ?? [] as $raw) {
            try {
                $posts[] = $this->parser->parse($raw);
            } catch (Throwable $e) {
                // 單筆貼文格式異常（如缺少 permalink/timestamp）不應中斷整批解析，
                // 記錄後略過即可——見 ThreadsScraperPostParser 的防禦性檢查說明。
                Log::warning('ThreadsScraperSearchProvider: 略過一筆無法解析的貼文', [
                    'keyword' => $query->keyword,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $posts;
    }

    /**
     * 非官方頁面爬蟲沒有官方配額限制，回傳 null 表示配額狀態不適用。
     */
    public function remainingQuota(): ?int
    {
        return null;
    }
}
