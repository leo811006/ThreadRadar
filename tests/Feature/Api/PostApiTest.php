<?php

use App\Models\Keyword;
use App\Models\Post;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/posts')->assertUnauthorized();
});

it('lists posts sorted by latest by default', function () {
    $older = Post::factory()->create(['posted_at' => now()->subDays(2)]);
    $newer = Post::factory()->create(['posted_at' => now()->subHour()]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts')
        ->assertSuccessful();

    expect($response->json('data.0.id'))->toBe($newer->id)
        ->and($response->json('data.1.id'))->toBe($older->id);
});

it('sorts posts by hottest', function () {
    $lowEngagement = Post::factory()->create(['views_count' => 10, 'likes_count' => 0, 'replies_count' => 0, 'reposts_count' => 0, 'quotes_count' => 0]);
    $highEngagement = Post::factory()->create(['views_count' => 10, 'likes_count' => 1000, 'replies_count' => 0, 'reposts_count' => 0, 'quotes_count' => 0]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts?sort=hottest')
        ->assertSuccessful();

    expect($response->json('data.0.id'))->toBe($highEngagement->id);
});

it('sorts posts by views descending', function () {
    $low = Post::factory()->create(['views_count' => 100]);
    $high = Post::factory()->create(['views_count' => 99999]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts?sort=views')
        ->assertSuccessful();

    expect($response->json('data.0.id'))->toBe($high->id);
});

it('filters posts by keyword name', function () {
    $keyword = Keyword::factory()->create(['name' => 'iPhone']);
    $matching = Post::factory()->create();
    $matching->keywords()->attach($keyword->id, ['matched_at' => now()]);

    $unrelated = Post::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts?keyword=iPhone')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($matching->id)
        ->and($ids)->not->toContain($unrelated->id);
});

it('filters posts by author', function () {
    $target = Post::factory()->create(['author_username' => 'targetuser']);
    $other = Post::factory()->create(['author_username' => 'otheruser']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts?author=targetuser')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($target->id)
        ->and($ids)->not->toContain($other->id);
});

it('filters posts by verified author', function () {
    $verified = Post::factory()->create(['is_verified_author' => true]);
    $unverified = Post::factory()->create(['is_verified_author' => false]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts?is_verified_author=1')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($verified->id)
        ->and($ids)->not->toContain($unverified->id);
});

it('filters posts by is_matched', function () {
    $keyword = Keyword::factory()->create();
    $matched = Post::factory()->create();
    $matched->keywordMatches()->create(['keyword_id' => $keyword->id, 'matched_at' => now()]);

    $unmatched = Post::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts?is_matched=1')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($matched->id)
        ->and($ids)->not->toContain($unmatched->id);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts?is_matched=0')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($unmatched->id)
        ->and($ids)->not->toContain($matched->id);
});

it('filters posts by date range', function () {
    $inRange = Post::factory()->create(['posted_at' => '2026-06-15 00:00:00']);
    $outOfRange = Post::factory()->create(['posted_at' => '2026-01-01 00:00:00']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts?date_from=2026-06-01&date_to=2026-06-30')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($inRange->id)
        ->and($ids)->not->toContain($outOfRange->id);
});

it('filters posts by ai_sentiment', function () {
    $positive = Post::factory()->create(['ai_summary' => 's', 'ai_sentiment' => 'positive']);
    $negative = Post::factory()->create(['ai_summary' => 's', 'ai_sentiment' => 'negative']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts?ai_sentiment=positive')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($positive->id)
        ->and($ids)->not->toContain($negative->id);
});

it('rejects an invalid ai_sentiment filter value', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts?ai_sentiment=invalid')
        ->assertStatus(422);
});

it('shows a single post', function () {
    $post = Post::factory()->create();

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/posts/{$post->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $post->id)
        ->assertJsonPath('data.threads_url', $post->threads_url);
});

it('returns 404 for a nonexistent post', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts/999999')
        ->assertNotFound();
});
