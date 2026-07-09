<?php

use App\Jobs\CrawlKeywordJob;
use App\Models\Keyword;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/keywords')->assertUnauthorized();
});

it('lists keywords with thresholds and notification channels', function () {
    $keyword = Keyword::factory()->create();
    $keyword->thresholds()->create(['metric' => 'views', 'operator' => '>=', 'value' => 1000]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/keywords')
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $keyword->id)
        ->assertJsonPath('data.0.thresholds.0.metric', 'views');
});

it('creates a keyword with thresholds and notification channels', function () {
    $payload = [
        'name' => 'iPhone',
        'is_active' => true,
        'crawl_interval_min' => 10,
        'time_range_type' => '24h',
        'thresholds' => [
            ['metric' => 'views', 'operator' => '>=', 'value' => 10000],
        ],
        'notification_channels' => [
            ['channel_type' => 'discord', 'config' => ['webhook_url' => 'https://discord.example/x'], 'is_active' => true],
        ],
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/keywords', $payload)
        ->assertCreated();

    expect(Keyword::where('name', 'iPhone')->exists())->toBeTrue();

    $keyword = Keyword::where('name', 'iPhone')->first();
    expect($keyword->thresholds)->toHaveCount(1)
        ->and($keyword->notificationChannels)->toHaveCount(1);

    // config must never be exposed in API responses.
    $response->assertJsonPath('data.notification_channels.0.config', '******');
});

it('rejects keyword creation with missing required fields', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/keywords', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'crawl_interval_min', 'time_range_type']);
});

it('requires custom time range dates when time_range_type is custom', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/keywords', [
            'name' => 'Test',
            'crawl_interval_min' => 10,
            'time_range_type' => 'custom',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['time_range_custom_from']);
});

it('shows a single keyword', function () {
    $keyword = Keyword::factory()->create();

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/keywords/{$keyword->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $keyword->id);
});

it('updates a keyword', function () {
    $keyword = Keyword::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/keywords/{$keyword->id}", ['name' => 'New Name'])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'New Name');

    expect($keyword->fresh()->name)->toBe('New Name');
});

it('replaces thresholds when updating with a new thresholds array', function () {
    $keyword = Keyword::factory()->create();
    $keyword->thresholds()->create(['metric' => 'views', 'operator' => '>=', 'value' => 1000]);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/keywords/{$keyword->id}", [
            'thresholds' => [
                ['metric' => 'likes', 'operator' => '>', 'value' => 500],
            ],
        ])
        ->assertSuccessful();

    $keyword->refresh();
    expect($keyword->thresholds)->toHaveCount(1)
        ->and($keyword->thresholds->first()->metric)->toBe('likes');
});

it('deletes a keyword', function () {
    $keyword = Keyword::factory()->create();

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/keywords/{$keyword->id}")
        ->assertNoContent();

    expect(Keyword::find($keyword->id))->toBeNull();
});

it('preserves existing notification channel config when updating without providing a new config', function () {
    $keyword = Keyword::factory()->create();
    $channel = $keyword->notificationChannels()->create([
        'channel_type' => 'discord',
        'config' => ['webhook_url' => 'https://discord.example/secret-webhook'],
        'is_active' => true,
    ]);

    // Simulates the SPA edit flow: config is masked to '******' in API responses,
    // so the frontend cannot round-trip the real value and must send an empty
    // config for channels the user didn't touch. Matching by id must preserve
    // the original secret rather than wiping it to an empty object.
    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/keywords/{$keyword->id}", [
            'notification_channels' => [
                ['id' => $channel->id, 'channel_type' => 'discord', 'config' => [], 'is_active' => true],
            ],
        ])
        ->assertSuccessful();

    $preserved = $keyword->notificationChannels()->first();
    expect($preserved->config)->toBe(['webhook_url' => 'https://discord.example/secret-webhook']);
});

it('overwrites notification channel config when a new non-empty config is provided', function () {
    $keyword = Keyword::factory()->create();
    $channel = $keyword->notificationChannels()->create([
        'channel_type' => 'discord',
        'config' => ['webhook_url' => 'https://discord.example/old-webhook'],
        'is_active' => true,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/keywords/{$keyword->id}", [
            'notification_channels' => [
                ['id' => $channel->id, 'channel_type' => 'discord', 'config' => ['webhook_url' => 'https://discord.example/new-webhook'], 'is_active' => true],
            ],
        ])
        ->assertSuccessful();

    $updated = $keyword->notificationChannels()->first();
    expect($updated->config)->toBe(['webhook_url' => 'https://discord.example/new-webhook']);
});

it('dispatches a crawl job immediately when crawl-now is called, ignoring the schedule', function () {
    Queue::fake([CrawlKeywordJob::class]);

    // 最近才巡檢過、遠未到下次間隔，一般排程不會 dispatch，但手動觸發應忽略排程限制。
    $keyword = Keyword::factory()->create([
        'crawl_interval_min' => 60,
        'last_crawled_at' => now()->subMinutes(1),
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/keywords/{$keyword->id}/crawl-now")
        ->assertStatus(202)
        ->assertJsonPath('message', '已加入巡檢佇列，請稍後重新整理查看結果。');

    Queue::assertPushed(CrawlKeywordJob::class, fn ($job) => $job->keywordId === $keyword->id);
});

it('rejects unauthenticated crawl-now requests', function () {
    $keyword = Keyword::factory()->create();

    $this->postJson("/api/keywords/{$keyword->id}/crawl-now")->assertUnauthorized();
});
