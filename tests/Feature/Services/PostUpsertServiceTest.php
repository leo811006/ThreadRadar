<?php

use App\Data\PostData;
use App\Models\Post;
use App\Services\PostUpsertService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

function makeUpsertPostData(array $overrides = []): PostData
{
    return new PostData(
        threadsUrl: $overrides['threadsUrl'] ?? 'https://www.threads.net/@user/post/abc',
        authorName: $overrides['authorName'] ?? 'Original Author',
        authorUsername: $overrides['authorUsername'] ?? 'originalauthor',
        postedAt: CarbonImmutable::parse('2026-01-01 00:00:00'),
        content: $overrides['content'] ?? 'Original content',
        images: [],
        videos: [],
        viewsCount: $overrides['viewsCount'] ?? 100,
        likesCount: $overrides['likesCount'] ?? 10,
        repliesCount: $overrides['repliesCount'] ?? 5,
        repostsCount: $overrides['repostsCount'] ?? 2,
        quotesCount: $overrides['quotesCount'] ?? 1,
        isVerifiedAuthor: $overrides['isVerifiedAuthor'] ?? false,
    );
}

beforeEach(function () {
    $this->service = new PostUpsertService();
});

it('creates a new post when threads_url does not exist', function () {
    $data = makeUpsertPostData();

    $post = $this->service->upsert($data);

    expect(Post::count())->toBe(1)
        ->and($post->threads_url)->toBe($data->threadsUrl)
        ->and($post->views_count)->toBe(100)
        ->and($post->first_seen_at)->not->toBeNull()
        ->and($post->last_seen_at)->not->toBeNull();
});

it('does not create a duplicate post for the same threads_url', function () {
    $url = 'https://www.threads.net/@user/post/duplicate-test';

    $this->service->upsert(makeUpsertPostData(['threadsUrl' => $url, 'viewsCount' => 100]));
    $this->service->upsert(makeUpsertPostData(['threadsUrl' => $url, 'viewsCount' => 200]));

    expect(Post::count())->toBe(1);
});

it('updates interaction counts on existing post without changing first_seen_at', function () {
    $url = 'https://www.threads.net/@user/post/update-test';

    Date::setTestNow(Date::parse('2026-01-01 00:00:00'));
    $first = $this->service->upsert(makeUpsertPostData(['threadsUrl' => $url, 'viewsCount' => 100]));
    $firstSeenAt = $first->first_seen_at;

    Date::setTestNow(Date::parse('2026-01-01 01:00:00'));
    $second = $this->service->upsert(makeUpsertPostData(['threadsUrl' => $url, 'viewsCount' => 500, 'likesCount' => 50]));

    Date::setTestNow();

    expect($second->id)->toBe($first->id)
        ->and($second->views_count)->toBe(500)
        ->and($second->likes_count)->toBe(50)
        ->and($second->first_seen_at->equalTo($firstSeenAt))->toBeTrue()
        ->and($second->last_seen_at->greaterThan($firstSeenAt))->toBeTrue();
});

it('preserves original content fields on update, not overwriting with new values', function () {
    $url = 'https://www.threads.net/@user/post/content-preserve-test';

    $this->service->upsert(makeUpsertPostData([
        'threadsUrl' => $url,
        'authorName' => 'Original Author',
        'content' => 'Original content',
    ]));

    $updated = $this->service->upsert(makeUpsertPostData([
        'threadsUrl' => $url,
        'authorName' => 'Changed Author',
        'content' => 'Changed content',
    ]));

    expect($updated->author_name)->toBe('Original Author')
        ->and($updated->content)->toBe('Original content');
});

it('records a metric snapshot on every upsert', function () {
    $url = 'https://www.threads.net/@user/post/snapshot-test';

    $post = $this->service->upsert(makeUpsertPostData(['threadsUrl' => $url, 'viewsCount' => 100]));
    $this->service->upsert(makeUpsertPostData(['threadsUrl' => $url, 'viewsCount' => 200]));

    expect($post->metricSnapshots()->count())->toBe(2);
});
