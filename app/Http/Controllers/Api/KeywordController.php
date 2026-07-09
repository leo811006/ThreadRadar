<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKeywordRequest;
use App\Http\Requests\UpdateKeywordRequest;
use App\Http\Resources\KeywordResource;
use App\Jobs\CrawlKeywordJob;
use App\Models\Keyword;
use App\Services\KeywordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class KeywordController extends Controller
{
    public function __construct(
        private readonly KeywordService $keywordService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $keywords = Keyword::with(['thresholds', 'notificationChannels'])
            ->latest()
            ->paginate();

        return KeywordResource::collection($keywords);
    }

    public function store(StoreKeywordRequest $request): KeywordResource
    {
        $keyword = $this->keywordService->create($request->validated());

        return new KeywordResource($keyword);
    }

    public function show(Keyword $keyword): KeywordResource
    {
        return new KeywordResource($keyword->load(['thresholds', 'notificationChannels']));
    }

    public function update(UpdateKeywordRequest $request, Keyword $keyword): KeywordResource
    {
        $keyword = $this->keywordService->update($keyword, $request->validated());

        return new KeywordResource($keyword);
    }

    public function destroy(Keyword $keyword): Response
    {
        $keyword->delete();

        return response()->noContent();
    }

    /**
     * 手動立即巡檢（不受 crawl_interval_min 排程限制）。僅將 CrawlKeywordJob
     * 投入佇列後立即回應，實際執行仍需 queue worker 在背景處理，不在此
     * request 內同步等待 Threads/Gemini API 回應（避免網頁請求逾時）。
     */
    public function crawlNow(Keyword $keyword): JsonResponse
    {
        CrawlKeywordJob::dispatch($keyword->id)->onQueue('crawl');

        return response()->json([
            'message' => '已加入巡檢佇列，請稍後重新整理查看結果。',
        ], Response::HTTP_ACCEPTED);
    }
}
