<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKeywordRequest;
use App\Http\Requests\UpdateKeywordRequest;
use App\Http\Resources\KeywordResource;
use App\Models\Keyword;
use App\Services\KeywordService;
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
}
