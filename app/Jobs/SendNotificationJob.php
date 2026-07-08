<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\PostKeywordMatch;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $postId,
        public readonly int $postKeywordMatchId,
    ) {
        $this->onQueue('notify');
    }

    public function handle(NotificationService $notificationService): void
    {
        $post = Post::findOrFail($this->postId);
        $match = PostKeywordMatch::findOrFail($this->postKeywordMatchId);

        $notificationService->notifyIfNotAlreadyNotified($post, $match);
    }
}
