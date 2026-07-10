<?php

use App\Data\PostData;
use App\Models\Post;
use App\Services\PostUpsertService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

function makeUpsertPostData(array $overrides = []): PostData
{
    // array_key_exists（非 ??）才能讓呼叫端明確傳入 null 覆蓋預設值——
    // ?? 無法區分「沒傳這個 key」與「明確傳了 null」，會誤將 null 覆蓋為預設值。
    $value = fn (string $key, mixed $default) => array_key_exists($key, $overrides) ? $overrides[$key] : $default;

    return new PostData(
        threadsUrl: $value('threadsUrl', 'https://www.threads.net/@user/post/abc'),
        authorName: $value('authorName', 'Original Author'),
        authorUsername: $value('authorUsername', 'originalauthor'),
        postedAt: CarbonImmutable::parse('2026-01-01 00:00:00'),
        content: $value('content', 'Original content'),
        images: [],
        videos: [],
        viewsCount: $value('viewsCount', 100),
        likesCount: $value('likesCount', 10),
        repliesCount: $value('repliesCount', 5),
        repostsCount: $value('repostsCount', 2),
        quotesCount: $value('quotesCount', 1),
        isVerifiedAuthor: $value('isVerifiedAuthor', false),
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

it('accepts views/quotes null while likes/replies/reposts have values, as from the scraper source', function () {
    $data = makeUpsertPostData([
        'viewsCount' => null,
        'quotesCount' => null,
        'likesCount' => 894,
        'repliesCount' => 42,
        'repostsCount' => 2,
    ]);

    $post = $this->service->upsert($data);

    expect($post->views_count)->toBe(0)
        ->and($post->quotes_count)->toBe(0)
        ->and($post->likes_count)->toBe(894)
        ->and($post->replies_count)->toBe(42)
        ->and($post->reposts_count)->toBe(2);
});

it('accepts likes/replies/reposts null while views/quotes have values', function () {
    $data = makeUpsertPostData([
        'viewsCount' => 500,
        'quotesCount' => 3,
        'likesCount' => null,
        'repliesCount' => null,
        'repostsCount' => null,
    ]);

    $post = $this->service->upsert($data);

    expect($post->views_count)->toBe(500)
        ->and($post->quotes_count)->toBe(3)
        ->and($post->likes_count)->toBe(0)
        ->and($post->replies_count)->toBe(0)
        ->and($post->reposts_count)->toBe(0);
});

it('throws when only some of views/quotes are null', function () {
    $data = makeUpsertPostData(['viewsCount' => 100, 'quotesCount' => null]);

    $this->service->upsert($data);
})->throws(LogicException::class, 'views/quotes');

it('throws when only some of likes/replies/reposts are null', function () {
    $data = makeUpsertPostData(['likesCount' => 10, 'repliesCount' => null, 'repostsCount' => 2]);

    $this->service->upsert($data);
})->throws(LogicException::class, 'likes/replies/reposts');

it('does not zero-fill an existing post real views/quotes when a later scraper update has them null', function () {
    $url = 'https://www.threads.net/@user/post/no-zero-fill-test';

    $this->service->upsert(makeUpsertPostData([
        'threadsUrl' => $url,
        'viewsCount' => 50000,
        'quotesCount' => 8,
        'likesCount' => 100,
        'repliesCount' => 10,
        'repostsCount' => 5,
    ]));

    $updated = $this->service->upsert(makeUpsertPostData([
        'threadsUrl' => $url,
        'viewsCount' => null,
        'quotesCount' => null,
        'likesCount' => 894,
        'repliesCount' => 42,
        'repostsCount' => 2,
    ]));

    // posts 表：views/quotes 這次沒有新觀測，維持既有真實值不被覆蓋。
    expect($updated->views_count)->toBe(50000)
        ->and($updated->quotes_count)->toBe(8)
        ->and($updated->likes_count)->toBe(894);

    // post_metric_snapshots：缺值那組沿用最後已知真實值，不可寫成 0
    // （0 會與 posts 表的 50000 互相矛盾，偽造出一筆歸零的假快照）。
    $latestSnapshot = $updated->metricSnapshots()->latest('recorded_at')->first();
    expect($latestSnapshot->views_count)->toBe(50000)
        ->and($latestSnapshot->quotes_count)->toBe(8)
        ->and($latestSnapshot->likes_count)->toBe(894);
});

it('does not create a duplicate snapshot when metric values are unchanged from the last one', function () {
    $url = 'https://www.threads.net/@user/post/dedup-snapshot-test';

    $post = $this->service->upsert(makeUpsertPostData([
        'threadsUrl' => $url,
        'likesCount' => 100,
        'repliesCount' => 10,
        'repostsCount' => 5,
    ]));

    $this->service->upsert(makeUpsertPostData([
        'threadsUrl' => $url,
        'likesCount' => 100,
        'repliesCount' => 10,
        'repostsCount' => 5,
    ]));

    expect($post->metricSnapshots()->count())->toBe(1);
});

it('creates a new snapshot when metric values differ from the last one', function () {
    $url = 'https://www.threads.net/@user/post/change-snapshot-test';

    $post = $this->service->upsert(makeUpsertPostData([
        'threadsUrl' => $url,
        'likesCount' => 100,
        'repliesCount' => 10,
        'repostsCount' => 5,
    ]));

    $this->service->upsert(makeUpsertPostData([
        'threadsUrl' => $url,
        'likesCount' => 150,
        'repliesCount' => 10,
        'repostsCount' => 5,
    ]));

    expect($post->metricSnapshots()->count())->toBe(2);
});
