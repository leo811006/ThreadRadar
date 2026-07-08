<?php

use App\Models\CrawlLog;
use App\Models\Keyword;
use App\Models\NotificationLog;
use App\Models\Post;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/dashboard')->assertUnauthorized();
});

it('returns today statistics', function () {
    $keyword = Keyword::factory()->create();

    CrawlLog::create([
        'keyword_id' => $keyword->id,
        'status' => 'success',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $post = Post::factory()->create(['first_seen_at' => now(), 'last_seen_at' => now()]);
    $match = $post->keywordMatches()->create(['keyword_id' => $keyword->id, 'matched_at' => now(), 'notified_at' => now()]);

    NotificationLog::create([
        'post_keyword_match_id' => $match->id,
        'channel_type' => 'discord',
        'status' => 'sent',
        'payload' => [],
        'sent_at' => now(),
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/dashboard')
        ->assertSuccessful();

    expect($response->json('data.today.search_count'))->toBe(1)
        ->and($response->json('data.today.new_posts_count'))->toBe(1)
        ->and($response->json('data.today.notification_count'))->toBe(1);
});

it('returns top posts ordered by hotness', function () {
    $hot = Post::factory()->create(['likes_count' => 10000]);
    Post::factory()->create(['likes_count' => 1]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/dashboard')
        ->assertSuccessful();

    expect($response->json('data.top_posts.0.id'))->toBe($hot->id);
});

it('returns top authors aggregated by hotness', function () {
    Post::factory()->create(['author_username' => 'popular', 'author_name' => 'Popular User', 'likes_count' => 5000]);
    Post::factory()->create(['author_username' => 'popular', 'author_name' => 'Popular User', 'likes_count' => 5000]);
    Post::factory()->create(['author_username' => 'quiet', 'likes_count' => 1]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/dashboard')
        ->assertSuccessful();

    $topAuthor = collect($response->json('data.top_authors'))->firstWhere('author_username', 'popular');

    expect($topAuthor)->not->toBeNull()
        ->and($topAuthor['post_count'])->toBe(2);
});

it('returns top keywords ordered by matched post count', function () {
    $popular = Keyword::factory()->create(['name' => 'Popular']);
    $quiet = Keyword::factory()->create(['name' => 'Quiet']);

    $post1 = Post::factory()->create();
    $post2 = Post::factory()->create();
    $post1->keywords()->attach($popular->id, ['matched_at' => now()]);
    $post2->keywords()->attach($popular->id, ['matched_at' => now()]);

    $post3 = Post::factory()->create();
    $post3->keywords()->attach($quiet->id, ['matched_at' => now()]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/dashboard')
        ->assertSuccessful();

    expect($response->json('data.top_keywords.0.id'))->toBe($popular->id)
        ->and($response->json('data.top_keywords.0.post_count'))->toBe(2);
});
