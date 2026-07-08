<?php

use App\Contracts\NotificationChannelInterface;
use App\Data\NotificationPayload;
use App\Models\Keyword;
use App\Models\NotificationLog;
use App\Models\Post;
use App\Services\NotificationService;

class SpyChannel implements NotificationChannelInterface
{
    public int $callCount = 0;

    public function send(NotificationPayload $payload, array $config): void
    {
        $this->callCount++;
    }
}

class FailingChannel implements NotificationChannelInterface
{
    public int $callCount = 0;

    public function send(NotificationPayload $payload, array $config): void
    {
        $this->callCount++;

        throw new RuntimeException('simulated delivery failure');
    }
}

it('sends notification and marks notified_at when first matched', function () {
    $spy = new SpyChannel();
    $service = new NotificationService(['discord' => $spy]);

    $keyword = Keyword::factory()->create();
    $keyword->notificationChannels()->create([
        'channel_type' => 'discord',
        'config' => ['webhook_url' => 'https://discord.example/webhook'],
        'is_active' => true,
    ]);

    $post = Post::factory()->create();
    $match = $post->keywordMatches()->create([
        'keyword_id' => $keyword->id,
        'matched_at' => now(),
    ]);

    $result = $service->notifyIfNotAlreadyNotified($post, $match);

    expect($result)->toBeTrue()
        ->and($spy->callCount)->toBe(1)
        ->and($match->fresh()->notified_at)->not->toBeNull();
});

it('does not send notification again if already notified', function () {
    $spy = new SpyChannel();
    $service = new NotificationService(['discord' => $spy]);

    $keyword = Keyword::factory()->create();
    $keyword->notificationChannels()->create([
        'channel_type' => 'discord',
        'config' => ['webhook_url' => 'https://discord.example/webhook'],
        'is_active' => true,
    ]);

    $post = Post::factory()->create();
    $match = $post->keywordMatches()->create([
        'keyword_id' => $keyword->id,
        'matched_at' => now(),
        'notified_at' => now(),
    ]);

    $result = $service->notifyIfNotAlreadyNotified($post, $match);

    expect($result)->toBeFalse()
        ->and($spy->callCount)->toBe(0);
});

it('skips inactive channels', function () {
    $spy = new SpyChannel();
    $service = new NotificationService(['discord' => $spy]);

    $keyword = Keyword::factory()->create();
    $keyword->notificationChannels()->create([
        'channel_type' => 'discord',
        'config' => ['webhook_url' => 'https://discord.example/webhook'],
        'is_active' => false,
    ]);

    $post = Post::factory()->create();
    $match = $post->keywordMatches()->create([
        'keyword_id' => $keyword->id,
        'matched_at' => now(),
    ]);

    $service->notifyIfNotAlreadyNotified($post, $match);

    expect($spy->callCount)->toBe(0);
});

it('logs a notification_logs entry with sent status on success', function () {
    $spy = new SpyChannel();
    $service = new NotificationService(['discord' => $spy]);

    $keyword = Keyword::factory()->create();
    $keyword->notificationChannels()->create([
        'channel_type' => 'discord',
        'config' => ['webhook_url' => 'https://discord.example/webhook'],
        'is_active' => true,
    ]);

    $post = Post::factory()->create();
    $match = $post->keywordMatches()->create([
        'keyword_id' => $keyword->id,
        'matched_at' => now(),
    ]);

    $service->notifyIfNotAlreadyNotified($post, $match);

    expect(NotificationLog::where('status', 'sent')->count())->toBe(1);
});

it('dispatches to multiple active channels independently', function () {
    $discordSpy = new SpyChannel();
    $slackSpy = new SpyChannel();
    $service = new NotificationService(['discord' => $discordSpy, 'slack' => $slackSpy]);

    $keyword = Keyword::factory()->create();
    $keyword->notificationChannels()->create([
        'channel_type' => 'discord',
        'config' => ['webhook_url' => 'https://discord.example/webhook'],
        'is_active' => true,
    ]);
    $keyword->notificationChannels()->create([
        'channel_type' => 'slack',
        'config' => ['webhook_url' => 'https://slack.example/webhook'],
        'is_active' => true,
    ]);

    $post = Post::factory()->create();
    $match = $post->keywordMatches()->create([
        'keyword_id' => $keyword->id,
        'matched_at' => now(),
    ]);

    $service->notifyIfNotAlreadyNotified($post, $match);

    expect($discordSpy->callCount)->toBe(1)
        ->and($slackSpy->callCount)->toBe(1);
});

it('continues to other channels and still marks notified when one channel fails', function () {
    $failingSpy = new FailingChannel();
    $workingSpy = new SpyChannel();
    $service = new NotificationService(['discord' => $failingSpy, 'slack' => $workingSpy]);

    $keyword = Keyword::factory()->create();
    $keyword->notificationChannels()->create([
        'channel_type' => 'discord',
        'config' => ['webhook_url' => 'https://discord.example/webhook'],
        'is_active' => true,
    ]);
    $keyword->notificationChannels()->create([
        'channel_type' => 'slack',
        'config' => ['webhook_url' => 'https://slack.example/webhook'],
        'is_active' => true,
    ]);

    $post = Post::factory()->create();
    $match = $post->keywordMatches()->create([
        'keyword_id' => $keyword->id,
        'matched_at' => now(),
    ]);

    $result = $service->notifyIfNotAlreadyNotified($post, $match);

    expect($result)->toBeTrue()
        ->and($failingSpy->callCount)->toBe(1)
        ->and($workingSpy->callCount)->toBe(1)
        ->and($match->fresh()->notified_at)->not->toBeNull()
        ->and(NotificationLog::where('status', 'failed')->where('channel_type', 'discord')->exists())->toBeTrue()
        ->and(NotificationLog::where('status', 'sent')->where('channel_type', 'slack')->exists())->toBeTrue();
});

it('does not create a sent log for the failing channel and records its error message', function () {
    $failingSpy = new FailingChannel();
    $service = new NotificationService(['discord' => $failingSpy]);

    $keyword = Keyword::factory()->create();
    $keyword->notificationChannels()->create([
        'channel_type' => 'discord',
        'config' => ['webhook_url' => 'https://discord.example/webhook'],
        'is_active' => true,
    ]);

    $post = Post::factory()->create();
    $match = $post->keywordMatches()->create([
        'keyword_id' => $keyword->id,
        'matched_at' => now(),
    ]);

    $service->notifyIfNotAlreadyNotified($post, $match);

    $log = NotificationLog::where('channel_type', 'discord')->first();

    expect($log->status)->toBe('failed')
        ->and($log->error_message)->toBe('simulated delivery failure')
        ->and(NotificationLog::where('status', 'sent')->count())->toBe(0);
});
