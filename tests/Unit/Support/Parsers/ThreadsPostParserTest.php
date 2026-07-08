<?php

use App\Support\Parsers\ThreadsPostParser;

beforeEach(function () {
    $this->parser = new ThreadsPostParser();
});

it('parses a full raw API response into PostData', function () {
    $raw = [
        'permalink' => 'https://www.threads.net/@johndoe/post/abc123',
        'username' => 'johndoe',
        'timestamp' => '2026-01-15T10:30:00+0000',
        'text' => 'Hello world from Threads',
        'views' => 12345,
        'likes' => 678,
        'replies' => 90,
        'reposts' => 12,
        'quotes' => 3,
        'is_verified' => true,
        'media_attachments' => [
            ['type' => 'IMAGE', 'url' => 'https://example.com/image1.jpg'],
            ['type' => 'VIDEO', 'url' => 'https://example.com/video1.mp4'],
            ['type' => 'IMAGE', 'url' => 'https://example.com/image2.jpg'],
        ],
    ];

    $post = $this->parser->parse($raw);

    expect($post->threadsUrl)->toBe('https://www.threads.net/@johndoe/post/abc123')
        ->and($post->authorName)->toBe('johndoe')
        ->and($post->authorUsername)->toBe('johndoe')
        ->and($post->content)->toBe('Hello world from Threads')
        ->and($post->viewsCount)->toBe(12345)
        ->and($post->likesCount)->toBe(678)
        ->and($post->repliesCount)->toBe(90)
        ->and($post->repostsCount)->toBe(12)
        ->and($post->quotesCount)->toBe(3)
        ->and($post->isVerifiedAuthor)->toBeTrue()
        ->and($post->images)->toBe(['https://example.com/image1.jpg', 'https://example.com/image2.jpg'])
        ->and($post->videos)->toBe(['https://example.com/video1.mp4']);
});

it('defaults missing optional fields to safe values', function () {
    $raw = [
        'permalink' => 'https://www.threads.net/@johndoe/post/minimal',
        'username' => 'johndoe',
        'timestamp' => '2026-01-15T10:30:00+0000',
    ];

    $post = $this->parser->parse($raw);

    expect($post->content)->toBe('')
        ->and($post->viewsCount)->toBe(0)
        ->and($post->likesCount)->toBe(0)
        ->and($post->repliesCount)->toBe(0)
        ->and($post->repostsCount)->toBe(0)
        ->and($post->quotesCount)->toBe(0)
        ->and($post->isVerifiedAuthor)->toBeFalse()
        ->and($post->images)->toBe([])
        ->and($post->videos)->toBe([]);
});

it('returns empty arrays when there are no media attachments', function () {
    $raw = [
        'permalink' => 'https://www.threads.net/@johndoe/post/no-media',
        'username' => 'johndoe',
        'timestamp' => '2026-01-15T10:30:00+0000',
        'media_attachments' => [],
    ];

    $post = $this->parser->parse($raw);

    expect($post->images)->toBe([])
        ->and($post->videos)->toBe([]);
});
