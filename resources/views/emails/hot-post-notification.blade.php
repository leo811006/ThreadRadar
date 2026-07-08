<x-mail::message>
# 🔥 關鍵字「{{ $payload->keywordName }}」發現熱門文章

**作者**: {{ $payload->authorName }} (@{{ $payload->authorUsername }})

**內容摘要**: {{ $payload->contentSummary }}

| 指標 | 數值 |
|---|---|
| Views | {{ $payload->viewsCount }} |
| Likes | {{ $payload->likesCount }} |
| Replies | {{ $payload->repliesCount }} |
| Reposts | {{ $payload->repostsCount }} |
| Quotes | {{ $payload->quotesCount }} |

<x-mail::button :url="$payload->threadsUrl">
查看原文
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
