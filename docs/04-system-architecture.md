# 階段三：系統架構設計

## 1. 架構總覽

```
                              ┌─────────────────────────────────────────────┐
                              │              使用者 / 管理者                   │
                              └───────────────┬─────────────┬───────────────┘
                                               │             │
                                     Vue3 SPA  │             │  Filament Admin
                                   (Dashboard) │             │  (關鍵字/文章管理)
                                               ▼             ▼
                              ┌─────────────────────────────────────────────┐
                              │           Laravel App (HTTP Layer)          │
                              │   REST API Controllers  /  Filament Panel   │
                              └───────────────┬─────────────────────────────┘
                                               │ 呼叫 Service Layer
                                               ▼
        ┌──────────────────────────────────────────────────────────────────────┐
        │                          Service Layer                                │
        │  KeywordService / SearchService / FilterService / NotificationService │
        │  DashboardService                                                     │
        └───────┬───────────────────┬───────────────────┬──────────────────────┘
                │                   │                   │
                ▼                   ▼                   ▼
      ┌──────────────────┐ ┌──────────────────┐ ┌──────────────────────┐
      │ Repository Layer  │ │  Crawler Layer    │ │  Notification Layer   │
      │ (Eloquent 封裝)    │ │ SearchProvider    │ │ Channel Adapters      │
      │ KeywordRepository  │ │ interface         │ │ Email/Discord/Slack/  │
      │ PostRepository     │ │ → ThreadsApi      │ │ LINE/Telegram/Webhook │
      │ NotificationRepo   │ │   Provider (v1)    │ │                       │
      └─────────┬─────────┘ └─────────┬─────────┘ └───────────┬────────────┘
                │                     │                        │
                ▼                     ▼                        ▼
      ┌───────────────────────────────────────────────────────────────────┐
      │                         MySQL 8 (資料持久層)                        │
      └───────────────────────────────────────────────────────────────────┘

      ┌───────────────────────────────────────────────────────────────────┐
      │                    非同步處理層（Redis 驅動）                        │
      │                                                                     │
      │  Laravel Scheduler (cron tick, 每分鐘)                              │
      │        │                                                           │
      │        ▼ 判斷各關鍵字是否到期 → dispatch                             │
      │  Queue: crawl-queue          Queue: notify-queue                   │
      │        │                            │                              │
      │        ▼                            ▼                              │
      │  CrawlKeywordJob              SendNotificationJob                  │
      │  (呼叫 SearchService)          (呼叫 NotificationService)           │
      │        │                                                           │
      │        └──── 新/更新文章達標 → dispatch SendNotificationJob ────────┘
      └───────────────────────────────────────────────────────────────────┘
```

---

## 2. 分層架構說明（Layered Architecture + Repository Pattern）

### 2.1 為什麼採用 Service + Repository 分層

需求文件明確要求「Service Layer / Repository Layer」，理由：

- **Repository Layer**：封裝 Eloquent 查詢細節，Controller/Service 不直接操作 Model 的複雜查詢邏輯，未來若需要切換 ORM 或加入讀寫分離、快取層，只需改 Repository 實作。
- **Service Layer**：封裝業務邏輯（去重判斷、門檻比對、通知去重），使其可被 Controller、Filament Resource、Queue Job 共用，且可獨立單元測試（不需要啟動 HTTP/Queue）。

### 2.2 各模組職責

| 模組 | 職責 | 對應需求 |
|---|---|---|
| `KeywordService` | 關鍵字 CRUD、啟用狀態、巡檢頻率設定的業務規則驗證 | FR-1, FR-2 |
| `SearchService` | 呼叫 `SearchProviderInterface` 取得原始搜尋結果，交給 Parser 正規化 | FR-3 |
| `Parser`（`ThreadsPostParser`） | 將 Provider 回傳的原始資料轉換為統一的 `PostData` DTO | FR-3 |
| `FilterService` | 依關鍵字設定的門檻條件（運算子 + 數值）判斷文章是否達標 | FR-2 |
| `PostUpsertService` | 依 Threads URL 去重，新建或更新文章記錄，維護 `first_seen_at`/`last_seen_at` | FR-3, FR-6（去重） |
| `NotificationService` | 依達標結果與「首次達標only通知一次」規則，決定是否觸發通知；委派給 Channel Adapters | FR-5 |
| `DashboardService` | 聚合今日統計、排行榜、趨勢數據（含快取） | FR-7 |

### 2.3 Crawler 層：Provider 介面隔離（本專案最重要的架構決策）

```php
interface SearchProviderInterface
{
    /**
     * @return PostData[]
     */
    public function search(SearchQuery $query): array;

    public function remainingQuota(): ?int; // 供排程層做配額感知降級
}
```

- **v1 實作**：`ThreadsApiSearchProvider`（官方 Keyword Search API，OAuth 2.0）
- 之所以仍定義介面（即便 MVP 只有一個實作）：
  1. 測試時可注入 `FakeSearchProvider` 回傳固定資料，Service/Job 邏輯可在不呼叫真實 API 的情況下完整測試（對應需求「高測試覆蓋率」）。
  2. 若未來配額不足需要 fallback（第三方 API 等），可新增實作並在設定檔切換，不需改動 `SearchService` 以上的任何程式碼。
  3. 明確的介面邊界讓「資料來源合規風險」被侷限在單一模組，未來稽核/替換時範圍清楚。

### 2.4 配額感知的排程降級機制

Threads Keyword Search API 配額為 2,200 次/日。設計：

- `ThreadsApiSearchProvider` 每次呼叫後記錄本日已用次數（Redis counter，每日 UTC 午夜重置對應 Meta 配額重置時間）。
- `CrawlKeywordJob` 執行前檢查 `remainingQuota()`：
  - 配額充足 → 正常執行
  - 配額低於安全水位（如剩餘 <5%）→ 僅執行「高優先權」關鍵字（可在關鍵字設定加 `priority` 欄位，Phase 2 可選），其餘延後
  - 配額用盡 → 該次排程 tick 跳過，記錄 log 並可選擇性通知系統管理者（非文章通知，是系統健康通知）
- 這一機制確保多組關鍵字競爭配額時系統不會直接對 API 報錯，而是優雅降級。

---

## 3. 任務流程圖：巡檢主流程

```
Scheduler tick (每分鐘)
  │
  ▼
查詢 keywords 表中 is_active=true 且 (now - last_crawled_at) >= crawl_interval 的關鍵字
  │
  ▼
逐一 dispatch CrawlKeywordJob（進 crawl-queue，非同步）
  │
  ▼ [Job 內執行]
檢查配額 remainingQuota()
  │
  ├─ 配額不足 → log + skip，結束
  │
  ▼ 配額充足
SearchService->search(keyword 設定的時間範圍)
  │
  ▼
ThreadsApiSearchProvider 呼叫官方 API
  │
  ▼
ThreadsPostParser 正規化為 PostData[]
  │
  ▼
逐篇文章 → PostUpsertService->upsert(postData, keywordId)
  │
  ├─ URL 已存在 → 更新 views/likes/replies/reposts/quotes + last_seen_at
  │              → 記錄互動數變化（供趨勢圖使用）
  │
  └─ URL 不存在 → 建立新記錄，first_seen_at = now
  │
  ▼
FilterService->matchesThreshold(postData, keyword的門檻條件)
  │
  ├─ 不符合 → 結束（僅更新資料，不通知）
  │
  ▼ 符合
檢查該文章對該關鍵字是否已「首次達標通知」過
  │
  ├─ 已通知過 → 結束（去重，不重複通知）
  │
  ▼ 首次達標
dispatch SendNotificationJob（進 notify-queue）
  │
  ▼ [Job 內執行]
NotificationService 依關鍵字設定的通知管道，逐一呼叫 Channel Adapter
  │
  ▼
標記 post_keyword_matches.notified_at = now（防止重複通知的持久化標記）
  │
  ▼
更新 keyword.last_crawled_at = now
```

---

## 4. 部署架構

```
┌─────────────────────────────────────────────────────────────┐
│                        Docker Compose                        │
│                                                                │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌───────────────┐ │
│  │   app    │  │  nginx   │  │  mysql   │  │     redis      │ │
│  │ (PHP-FPM)│  │ (reverse │  │    8     │  │ (cache+queue)  │ │
│  │          │  │  proxy)  │  │          │  │                │ │
│  └──────────┘  └──────────┘  └──────────┘  └───────────────┘ │
│                                                                │
│  ┌──────────────────┐  ┌──────────────────────┐              │
│  │  queue-worker     │  │  scheduler            │              │
│  │  (horizon or      │  │  (cron: schedule:run  │              │
│  │   queue:work)     │  │   每分鐘)              │              │
│  └──────────────────┘  └──────────────────────┘              │
└─────────────────────────────────────────────────────────────┘
```

- `app`：處理 HTTP 請求（REST API + Filament Admin），不承擔巡檢/通知的實際執行（僅 dispatch）。
- `queue-worker`：獨立容器執行 `CrawlKeywordJob`/`SendNotificationJob`，可依負載水平擴展（多個 worker 容器）。
- `scheduler`：僅負責每分鐘觸發 tick 判斷哪些關鍵字到期，實際工作交給 Queue，避免排程程序本身被 API 呼叫延遲卡住。
- 不綁定雲廠商：`docker-compose.yml` 可直接部署於任何支援 Docker 的 VPS；雲端部署時 mysql/redis 可替換為對應的 managed service（RDS/ElastiCache 等），應用層設定不需更動（透過 `.env` 抽換連線資訊）。

---

## 5. 目錄結構（Laravel 標準 + 自訂分層）

```
ThreadRadar/
├── app/
│   ├── Console/
│   │   └── Commands/
│   ├── Contracts/                  # 介面定義
│   │   ├── SearchProviderInterface.php
│   │   └── NotificationChannelInterface.php
│   ├── Data/                       # DTO（PostData, SearchQuery 等）
│   │   ├── PostData.php
│   │   └── SearchQuery.php
│   ├── Filament/                   # Filament Admin Resources
│   │   └── Resources/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   ├── Requests/
│   │   └── Resources/              # API Resource（JSON 轉換）
│   ├── Jobs/
│   │   ├── CrawlKeywordJob.php
│   │   └── SendNotificationJob.php
│   ├── Models/
│   ├── Notifications/              # Laravel Notification classes
│   │   └── Channels/               # 自訂 Discord/LINE/Telegram channel
│   ├── Providers/
│   │   └── SearchProviders/
│   │       └── ThreadsApiSearchProvider.php
│   ├── Repositories/
│   ├── Services/
│   │   ├── KeywordService.php
│   │   ├── SearchService.php
│   │   ├── FilterService.php
│   │   ├── PostUpsertService.php
│   │   ├── NotificationService.php
│   │   └── DashboardService.php
│   └── Support/
│       └── Parsers/
│           └── ThreadsPostParser.php
├── database/
│   ├── migrations/
│   └── factories/
├── docs/                           # 本系列設計文件
├── routes/
│   └── api.php
├── tests/
│   ├── Unit/
│   └── Feature/
├── docker/
│   ├── php/Dockerfile
│   └── nginx/default.conf
├── docker-compose.yml
└── .github/workflows/ci.yml
```

---

## 6. 替代方案與取捨紀錄

| 決策點 | 選擇 | 替代方案 | 為何不選替代方案 |
|---|---|---|---|
| 佇列系統 | Redis Queue | RabbitMQ / Kafka | 本專案吞吐量遠低於需要專用 MQ 的規模，多一個系統元件增加維運成本但無對應效益 |
| 排程觸發粒度 | 每分鐘 tick + DB 判斷到期 | 為每組關鍵字各自建立 cron entry | 動態新增關鍵字時不需要重新產生 crontab，設定完全由資料庫驅動，更符合「易維護」需求 |
| 通知去重 | 持久化欄位標記 `notified_at`（非 Redis 暫存） | Redis Set 記錄已通知文章 | 通知去重需要長期保存（文章可能好幾天後才符合門檻），Redis 若設定 TTL 或被清空會導致重複通知；DB 欄位可靠且可稽核 |
| Admin 後台 | Filament v4 | 自建 Vue Admin | Filament 可將關鍵字/文章管理的 CRUD 表單與列表開發量降低 80% 以上，且與 Laravel 生態原生整合 |
