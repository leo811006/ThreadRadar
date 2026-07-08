<?php

use App\Data\PostData;
use App\Models\Keyword;
use App\Services\FilterService;
use Carbon\CarbonImmutable;

function makePostData(array $overrides = []): PostData
{
    return new PostData(
        threadsUrl: $overrides['threadsUrl'] ?? 'https://www.threads.net/@user/post/abc',
        authorName: $overrides['authorName'] ?? 'Test Author',
        authorUsername: $overrides['authorUsername'] ?? 'testauthor',
        postedAt: CarbonImmutable::now(),
        content: $overrides['content'] ?? 'Test content',
        images: [],
        videos: [],
        viewsCount: $overrides['viewsCount'] ?? 0,
        likesCount: $overrides['likesCount'] ?? 0,
        repliesCount: $overrides['repliesCount'] ?? 0,
        repostsCount: $overrides['repostsCount'] ?? 0,
        quotesCount: $overrides['quotesCount'] ?? 0,
        isVerifiedAuthor: $overrides['isVerifiedAuthor'] ?? false,
    );
}

beforeEach(function () {
    $this->filterService = new FilterService();
});

it('returns true when keyword has no thresholds', function () {
    $keyword = Keyword::factory()->create();
    $post = makePostData();

    expect($this->filterService->matchesThreshold($post, $keyword))->toBeTrue();
});

it('matches a single greater-than threshold correctly', function () {
    $keyword = Keyword::factory()->create();
    $keyword->thresholds()->create(['metric' => 'views', 'operator' => '>', 'value' => 10000]);

    expect($this->filterService->matchesThreshold(makePostData(['viewsCount' => 10001]), $keyword))->toBeTrue();
    expect($this->filterService->matchesThreshold(makePostData(['viewsCount' => 10000]), $keyword))->toBeFalse();
    expect($this->filterService->matchesThreshold(makePostData(['viewsCount' => 9999]), $keyword))->toBeFalse();
});

it('matches a single greater-than-or-equal threshold correctly', function () {
    $keyword = Keyword::factory()->create();
    $keyword->thresholds()->create(['metric' => 'likes', 'operator' => '>=', 'value' => 500]);

    expect($this->filterService->matchesThreshold(makePostData(['likesCount' => 500]), $keyword))->toBeTrue();
    expect($this->filterService->matchesThreshold(makePostData(['likesCount' => 499]), $keyword))->toBeFalse();
});

it('matches a single equal-to threshold correctly', function () {
    $keyword = Keyword::factory()->create();
    $keyword->thresholds()->create(['metric' => 'replies', 'operator' => '=', 'value' => 30]);

    expect($this->filterService->matchesThreshold(makePostData(['repliesCount' => 30]), $keyword))->toBeTrue();
    expect($this->filterService->matchesThreshold(makePostData(['repliesCount' => 31]), $keyword))->toBeFalse();
});

it('matches a single less-than threshold correctly', function () {
    $keyword = Keyword::factory()->create();
    $keyword->thresholds()->create(['metric' => 'reposts', 'operator' => '<', 'value' => 50]);

    expect($this->filterService->matchesThreshold(makePostData(['repostsCount' => 49]), $keyword))->toBeTrue();
    expect($this->filterService->matchesThreshold(makePostData(['repostsCount' => 50]), $keyword))->toBeFalse();
});

it('matches a single less-than-or-equal threshold correctly', function () {
    $keyword = Keyword::factory()->create();
    $keyword->thresholds()->create(['metric' => 'quotes', 'operator' => '<=', 'value' => 20]);

    expect($this->filterService->matchesThreshold(makePostData(['quotesCount' => 20]), $keyword))->toBeTrue();
    expect($this->filterService->matchesThreshold(makePostData(['quotesCount' => 21]), $keyword))->toBeFalse();
});

it('requires all thresholds to match (AND logic)', function () {
    $keyword = Keyword::factory()->create();
    $keyword->thresholds()->create(['metric' => 'views', 'operator' => '>=', 'value' => 10000]);
    $keyword->thresholds()->create(['metric' => 'likes', 'operator' => '>=', 'value' => 500]);

    // Both satisfied
    expect($this->filterService->matchesThreshold(
        makePostData(['viewsCount' => 10000, 'likesCount' => 500]),
        $keyword
    ))->toBeTrue();

    // Only one satisfied
    expect($this->filterService->matchesThreshold(
        makePostData(['viewsCount' => 10000, 'likesCount' => 499]),
        $keyword
    ))->toBeFalse();

    // Neither satisfied
    expect($this->filterService->matchesThreshold(
        makePostData(['viewsCount' => 1, 'likesCount' => 1]),
        $keyword
    ))->toBeFalse();
});
