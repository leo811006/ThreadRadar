# 階段四：Database Schema 與 ER Diagram

## 1. ER Diagram

```
┌────────────────────┐       ┌──────────────────────────┐
│      keywords       │       │   keyword_thresholds      │
│──────────────────────│       │────────────────────────────│
│ id            PK     │◄──┐  │ id                  PK     │
│ name                 │   └──│ keyword_id          FK     │
│ is_active             │      │ metric  (enum)             │  views/likes/replies/
│ crawl_interval_min   │      │ operator (enum)             │  reposts/quotes
│ time_range_type       │      │ value   (bigint)            │  >, >=, =, <, <=
│ time_range_custom_from│      └──────────────────────────┘
│ time_range_custom_to  │
│ last_crawled_at       │       ┌──────────────────────────┐
│ created_at            │       │  keyword_notification_    │
│ updated_at            │       │  channels                  │
└──────────┬───────────┘       │────────────────────────────│
           │                    │ id              PK         │
           │ 1                  │ keyword_id      FK         │──► keywords.id
           │                    │ channel_type    (enum)     │  email/discord/slack/
           │ N                  │ config          (json)     │  line/telegram/webhook
           ▼                    │ is_active                  │
┌────────────────────────┐     └──────────────────────────┘
│  post_keyword_matches   │
│──────────────────────────│
│ id                PK     │      ┌────────────────────────────┐
│ post_id           FK     │─────►│           posts              │
│ keyword_id        FK     │      │────────────────────────────│
│ matched_at                │      │ id                   PK     │
│ notified_at   (nullable) │      │ threads_url    UNIQUE       │
│ created_at                │      │ author_name                 │
└──────────────────────────┘      │ author_username              │
                                    │ posted_at                    │
                                    │ content        (text)        │
                                    │ images         (json)        │
                                    │ videos         (json)        │
                                    │ views_count                  │
                                    │ likes_count                  │
                                    │ replies_count                │
                                    │ reposts_count                │
                                    │ quotes_count                 │
                                    │ is_verified_author            │
                                    │ ai_summary      (nullable)    │  Phase 2 預留
                                    │ ai_tags         (json,nullable)│ Phase 2 預留
                                    │ ai_sentiment    (nullable)    │  Phase 2 預留
                                    │ first_seen_at                 │
                                    │ last_seen_at                  │
                                    │ created_at                    │
                                    │ updated_at                    │
                                    └───────────┬──────────────────┘
                                                │ 1
                                                │ N
                                                ▼
                                    ┌────────────────────────────┐
                                    │   post_metric_snapshots      │
                                    │────────────────────────────│
                                    │ id              PK          │
                                    │ post_id         FK          │
                                    │ views_count                  │
                                    │ likes_count                  │
                                    │ replies_count                 │
                                    │ reposts_count                 │
                                    │ quotes_count                  │
                                    │ recorded_at                   │
                                    └────────────────────────────┘

┌────────────────────────┐        ┌────────────────────────────┐
│   notification_logs     │        │      crawl_logs              │
│──────────────────────────│        │────────────────────────────│
│ id                PK     │        │ id                PK        │
│ post_keyword_match_id FK │        │ keyword_id        FK        │
│ channel_type              │        │ status (enum)                │  success/failed/
│ status  (enum)            │        │ posts_found                  │  quota_exceeded
│ payload          (json)   │        │ posts_created                │
│ error_message  (nullable) │        │ posts_updated                │
│ sent_at                    │        │ api_calls_used                │
│ created_at                  │        │ error_message  (nullable)     │
└──────────────────────────┘        │ started_at                     │
                                       │ finished_at                    │
                                       └────────────────────────────┘

┌────────────────────────┐
│  daily_statistics        │   （Dashboard 用聚合表，由排程每日/即時更新，避免即時聚合查詢拖慢 Dashboard）
│──────────────────────────│
│ id                PK     │
│ date              UNIQUE │
│ search_count               │
│ new_posts_count             │
│ updated_posts_count         │
│ notification_count           │
│ created_at                    │
│ updated_at                    │
└──────────────────────────┘
```

---

## 2. 資料表詳細定義

### 2.1 `keywords`

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint unsigned PK | |
| name | varchar(255) | 關鍵字名稱，如「iPhone」 |
| is_active | boolean, default true | 啟用/停用開關 |
| crawl_interval_min | tinyint unsigned | 巡檢頻率（分鐘）：1/5/10/30/60 |
| time_range_type | enum | `30min`,`1h`,`6h`,`24h`,`7d`,`custom` |
| time_range_custom_from | datetime nullable | 僅 `time_range_type=custom` 時使用 |
| time_range_custom_to | datetime nullable | 同上 |
| last_crawled_at | datetime nullable | 供 Scheduler 判斷是否到期 |
| created_at / updated_at | timestamp | |

索引：`(is_active, last_crawled_at)` 供 Scheduler 查詢到期關鍵字。

### 2.2 `keyword_thresholds`

一組關鍵字可有多筆門檻條件（AND 邏輯）。

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint unsigned PK | |
| keyword_id | bigint unsigned FK → keywords.id, cascade delete | |
| metric | enum | `views`,`likes`,`replies`,`reposts`,`quotes` |
| operator | enum | `>`,`>=`,`=`,`<`,`<=` |
| value | bigint unsigned | 門檻數值 |

索引：`keyword_id`

### 2.3 `keyword_notification_channels`

一組關鍵字可掛多個通知管道。

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint unsigned PK | |
| keyword_id | bigint unsigned FK → keywords.id, cascade delete | |
| channel_type | enum | `email`,`discord`,`slack`,`line`,`telegram`,`webhook` |
| config | json | 管道專屬設定（webhook URL、email 收件人、bot token 等，敏感值加密儲存） |
| is_active | boolean, default true | |

### 2.4 `posts`

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint unsigned PK | |
| threads_url | varchar(512) UNIQUE | **去重鍵** |
| author_name | varchar(255) | |
| author_username | varchar(255) | 索引，供依作者查詢 |
| posted_at | datetime | Threads 上的原始發文時間 |
| content | text | |
| images | json nullable | URL 陣列 |
| videos | json nullable | URL 陣列 |
| views_count / likes_count / replies_count / reposts_count / quotes_count | bigint unsigned default 0 | |
| is_verified_author | boolean default false | |
| ai_summary | text nullable | Phase 2 預留 |
| ai_tags | json nullable | Phase 2 預留 |
| ai_sentiment | varchar(50) nullable | Phase 2 預留 |
| first_seen_at | datetime | 系統首次發現時間 |
| last_seen_at | datetime | 系統最後一次確認資料的時間 |
| created_at / updated_at | timestamp | |

索引：`threads_url`（unique）、`author_username`、`posted_at`、`(views_count, likes_count)` 供熱門排序、FULLTEXT `content` 供內容搜尋。

### 2.5 `post_keyword_matches`（多對多關聯 + 通知去重狀態）

一篇文章可能命中多個關鍵字，每個「文章-關鍵字」組合獨立追蹤是否已通知。

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint unsigned PK | |
| post_id | bigint unsigned FK → posts.id, cascade delete | |
| keyword_id | bigint unsigned FK → keywords.id, cascade delete | |
| matched_at | datetime | 該文章對該關鍵字首次符合門檻的時間 |
| notified_at | datetime nullable | **非 null 表示已通知過，是通知去重的權威標記** |
| created_at | timestamp | |

索引：`UNIQUE(post_id, keyword_id)`、`(keyword_id, notified_at)`

### 2.6 `post_metric_snapshots`（互動數歷史快照，供趨勢圖）

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint unsigned PK | |
| post_id | bigint unsigned FK → posts.id, cascade delete | |
| views_count / likes_count / replies_count / reposts_count / quotes_count | bigint unsigned | 該次巡檢時的快照值 |
| recorded_at | datetime | |

用途：Dashboard「每日熱門文章成長」趨勢圖需要歷史數據點，而非只有 `posts` 表的最新值。每次 upsert 更新 `posts` 主表的同時，額外插入一筆快照。

### 2.7 `notification_logs`

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint unsigned PK | |
| post_keyword_match_id | bigint unsigned FK → post_keyword_matches.id | |
| channel_type | enum | 同 keyword_notification_channels |
| status | enum | `sent`,`failed` |
| payload | json | 實際發送的通知內容（供除錯與稽核） |
| error_message | text nullable | |
| sent_at | datetime nullable | |
| created_at | timestamp | |

用途：通知稽核與除錯；也可作為未來「通知次數」統計的原始資料來源。

### 2.8 `crawl_logs`

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint unsigned PK | |
| keyword_id | bigint unsigned FK → keywords.id | |
| status | enum | `success`,`failed`,`quota_exceeded` |
| posts_found / posts_created / posts_updated | int unsigned | |
| api_calls_used | int unsigned | 供配額監控與除錯 |
| error_message | text nullable | |
| started_at / finished_at | datetime | |

用途：巡檢歷史稽核、配額使用追蹤、除錯依據。也是 Dashboard「今日搜尋次數」的資料來源（`COUNT(*) WHERE DATE(started_at) = today`）。

### 2.9 `daily_statistics`（Dashboard 聚合表）

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint unsigned PK | |
| date | date UNIQUE | |
| search_count | int unsigned | |
| new_posts_count | int unsigned | |
| updated_posts_count | int unsigned | |
| notification_count | int unsigned | |

用途：避免 Dashboard 每次請求都對 `crawl_logs`/`posts`/`notification_logs` 做即時聚合查詢（隨資料量增長會變慢）。由 `SendNotificationJob`/`CrawlKeywordJob` 執行時遞增計數，或由每日排程批次計算前一日數據。**設計取捨**：即時遞增（Redis incr + 每小時同步進 DB）比純批次計算更符合「今日即時統計」的需求，實作時採用「Job 內即時 upsert increment」而非等到隔天才計算。

---

## 3. 去重與更新邏輯總結（對應 FR-3, FR-6）

```sql
-- PostUpsertService 的核心邏輯（概念 SQL，實際以 Eloquent updateOrCreate 實作）
INSERT INTO posts (threads_url, ..., first_seen_at, last_seen_at)
VALUES (?, ..., NOW(), NOW())
ON DUPLICATE KEY UPDATE
  views_count = VALUES(views_count),
  likes_count = VALUES(likes_count),
  replies_count = VALUES(replies_count),
  reposts_count = VALUES(reposts_count),
  quotes_count = VALUES(quotes_count),
  last_seen_at = NOW();
  -- 注意：author_name/content 等內容欄位是否更新，取決於業務判斷（Threads 貼文內容通常不會變動，
  -- 但作者可能改名——實作時內容類欄位傾向保留首次值，互動數類欄位永遠覆蓋為最新值）
```

通知去重權威來源是 `post_keyword_matches.notified_at`，而非依賴 Queue 層面的去重（Queue 訊息可能重複投遞，不可靠），確保「首次達標只通知一次」在資料庫層面有唯一且持久的保證。

---

## 4. 索引設計重點（效能考量）

| 查詢情境 | 索引 |
|---|---|
| Scheduler 找到期關鍵字 | `keywords(is_active, last_crawled_at)` |
| 去重 upsert | `posts(threads_url)` unique |
| 依熱門度排序列表 | `posts(views_count)`, `posts(likes_count)` 等各自索引，或視查詢模式建複合索引 |
| 依作者查詢 | `posts(author_username)` |
| 依關鍵字查詢文章 | `post_keyword_matches(keyword_id)` |
| 通知去重檢查 | `post_keyword_matches(post_id, keyword_id)` unique |
| Dashboard 今日統計 | `daily_statistics(date)` unique，O(1) 查詢單日數據 |
| 內容全文搜尋 | `posts` FULLTEXT INDEX on `content` |
