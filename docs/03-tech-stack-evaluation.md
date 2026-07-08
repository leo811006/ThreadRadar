# 階段二：技術選型評估

本評估基於階段一結論：**資料來源為官方 Threads Keyword Search API（OAuth 2.0 + REST，2,200 次/日配額）**，不涉及 headless browser。因此選型權重是「標準 Web 服務 + OAuth 整合 + Queue/Scheduler + 關聯式資料庫」的綜合能力，而非爬蟲生態。

評估標準：開發速度、維護成本、效能、擴充性、社群成熟度、是否適合本專案。

---

## 1. 後端語言 / 框架比較

| 語言 | 代表框架 | 開發速度 | 維護成本 | 效能 | 擴充性 | 社群成熟度 | 評語 |
|---|---|---|---|---|---|---|---|
| **PHP 8.2** | Laravel 12 | 極快（內建 Scheduler/Queue/ORM/Notification/Auth 全部原生） | 低（單一框架涵蓋九成需求，慣例優先） | 中（同步阻塞，但 Queue worker 可水平擴展彌補） | 高（Horizon 可視化佇列擴展、Octane 可選高效能模式） | 極高 | **本專案是「CRUD + 排程 + 佇列 + 通知 + 後台」的教科書型應用，Laravel 生態幾乎逐項原生對應每個需求模組** |
| Python 3.12 | Django + Celery | 快 | 中（Django 全家桶 + Celery 需額外整合排程） | 中 | 高 | 極高 | Django admin 可省後台開發，但 Notification/Webhook 整合需自建；Celery 設定複雜度高於 Laravel Queue |
| Python 3.12 | FastAPI + Celery | 中（需自行拼裝 ORM/Admin/Auth/Queue） | 中高（元件各自獨立，需自行整合維護） | 高（async） | 高 | 高 | 效能最佳但等於自己組一套框架，對「快速交付商業系統」不是最佳權衡 |
| Node.js 22 | NestJS + BullMQ | 快 | 中（TypeScript 型別安全佳，但 Admin/Notification 需自建或找第三方套件） | 高（事件驅動，I/O bound 場景表現佳） | 高 | 高 | 適合但沒有 Laravel/Django 級別的「開箱即用後台系統」，Filament 等價物較少 |
| Go 1.23 | Gin/Echo + asynq | 中（型別安全、部署為單一 binary） | 中高（生態偏底層，Admin/ORM 需自行拼裝或用較不成熟套件） | 極高 | 極高 | 中（Web 生態不如上述） | 效能與併發最強，但本專案瓶頸在「配額 2,200 次/日」的 I/O 呼叫，不是 CPU/併發瓶頸，Go 的效能優勢在此用不上 |
| Java 21 | Spring Boot | 中（企業級穩健，但樣板程式碼多） | 中高（啟動慢、資源消耗大，對中小型專案是過度工程） | 高 | 極高 | 極高 | 適合大型企業系統，但對這個規模的專案是殺雞用牛刀，團隊速度會被拖慢 |
| Rust | Axum + tokio | 慢 | 高（生態尚在成熟，Admin/ORM 選擇少） | 極高 | 極高 | 中低 | 效能與安全性最強，但本專案不是效能瓶頸驅動的系統，開發速度損失不划算 |

### 關鍵判斷依據

本專案的瓶頸分析：
1. **I/O bound，非 CPU bound**：核心工作是「定時打 API → 解析 JSON → 寫資料庫 → 觸發 Webhook」，不是高併發運算。Go/Rust 的效能優勢無法轉化為本專案的實際收益。
2. **配額是硬限制（2,200 次/日）**：意味著呼叫量本身有上限，語言效能不是系統吞吐量的瓶頸，**排程/去重/通知邏輯的正確性遠比語言執行速度重要**。
3. **需要的模組清單**（關鍵字 CRUD、Scheduler、Queue、五種通知管道、Admin 後台、REST API、Dashboard 統計）幾乎與 Laravel 官方生態一一對應：`Schedule` facade、`Queue`/`Horizon`、`Notification` channels（內建 Mail/Slack/自訂 channel）、Filament（Admin）、`Route::apiResource`。
4. 開發速度與維護成本是本專案最重要的非功能需求（商業化系統需要快速迭代、團隊可持續維護），這正是 Laravel 的核心優勢。

**結論：PHP 8.2 + Laravel 12。**

---

## 2. 前端框架比較

| 方案 | 適合度 | 理由 |
|---|---|---|
| **Vue 3 + Vite + TailwindCSS** | 高 | 與 Laravel 生態整合度最高（Inertia.js 可選、Laravel 官方文件範例多為 Vue）；Dashboard/表格/篩選這類「資料展示為主」的介面用 Vue 3 Composition API 開發效率高 |
| React + Next.js | 中 | 生態更龐大，但與 Laravel 整合需額外處理（純 API 前後分離），對本專案這種「後台管理為主」的系統是額外複雜度 |
| Svelte/SvelteKit | 中 | 效能佳、學習曲線平緩，但社群成熟度與可用元件庫少於 Vue，對 Dashboard 圖表/表格類元件選擇較少 |

**結論：Vue 3 + Vite + TailwindCSS**，搭配 **Filament v4**（Laravel 官方生態的 Admin Panel builder）處理後台管理介面，大幅減少關鍵字管理、文章列表、通知設定等 CRUD 介面的開發量。前台 Dashboard 若需要獨立於 Admin 之外的呈現，再以 Vue 3 SPA/Inertia 方式串接 REST API。

---

## 3. 資料庫

| 方案 | 適合度 | 理由 |
|---|---|---|
| **MySQL 8** | 高 | 需求指定；關聯式資料模型（關鍵字-文章-通知記錄多對多關係）適合 RDBMS；Laravel migration/Eloquent 生態成熟 |
| PostgreSQL | 高（備選） | 若未來需要更強的 JSON 查詢或全文搜尋，PostgreSQL 是合理替代，但需求已指定 MySQL，且 MySQL 8 的 JSON 欄位與全文索引已足夠本專案使用 |

**結論：MySQL 8**（依需求指定），文章內容全文搜尋可用 MySQL FULLTEXT INDEX，暫不需要引入 Elasticsearch。

---

## 4. 快取 / 佇列

**結論：Redis**，一套元件同時扮演三個角色：
- Laravel Cache driver（Dashboard 統計快取、API 限流）
- Laravel Queue driver（巡檢 Job、通知 Job 非同步處理）
- Laravel Horizon 的底層（Queue 監控可視化）

不需要額外引入 RabbitMQ/Kafka——本專案的佇列吞吐量遠低於需要專用訊息佇列系統的規模，Redis Queue 已足夠且降低維運複雜度（少一個系統元件）。

---

## 5. Scheduler

**結論：Laravel Scheduler（`schedule:run` cron entry）+ Queue Job**。每組關鍵字的巡檢頻率設定，轉換為 Scheduler 內動態註冊的排程任務（或用單一 minutely tick + 資料庫判斷各關鍵字是否到期），觸發後 dispatch 到 Queue 非同步執行，避免排程本身被 API 呼叫延遲卡住。

---

## 6. 部署 / DevOps

| 項目 | 選擇 | 理由 |
|---|---|---|
| 容器化 | Docker + docker-compose | 需求指定；app / mysql / redis / queue-worker / scheduler 分容器 |
| CI/CD | GitHub Actions | 免費額度足夠中小型專案、與 Laravel 生態（PestPHP/PHPUnit）整合成熟 |
| 部署目標 | 不綁定雲廠商；優先支援可自架的 VPS/Docker host，兼容主流雲（AWS/GCP/Azure）與 PaaS（Laravel Forge/Vapor 可選但非必須） | 符合需求「可部署至雲端或本地」 |

---

## 7. 最終技術棧總結

| 層級 | 選擇 |
|---|---|
| 後端語言/框架 | PHP 8.2 + Laravel 12 |
| 前端 | Vue 3 + Vite + TailwindCSS |
| Admin 後台 | Filament v4 |
| 資料庫 | MySQL 8 |
| 快取/佇列 | Redis |
| 排程 | Laravel Scheduler + Queue Job |
| 資料來源 | Threads 官方 Keyword Search API（OAuth 2.0） |
| 容器化 | Docker + docker-compose |
| CI/CD | GitHub Actions |
| 測試 | PestPHP（Laravel 生態慣用，語法簡潔，底層仍是 PHPUnit） |

### 與最初「不預設語言框架」要求的一致性說明

本評估在完全開放比較六種語言/框架後，基於**本專案的實際瓶頸是 I/O 與配額限制、而非運算效能**，以及**所需模組與 Laravel 官方生態高度重疊**兩個客觀理由，獨立推導出 Laravel 12 為最佳解——這與 workspace 現有專案的技術慣性無關（評估過程未將其列入評分項），純粹是需求與技術特性匹配的結果。若後續開發中發現效能瓶頸（例如巡檢量遠超預期規模），Laravel Octane 或將高頻 Job 抽出為獨立 Go 微服務是可行的漸進式優化路徑，不需要一開始就為此付出開發速度代價。

### 已知版本風險（待用戶最終確認）

Filament v4 目前為 beta/RC 階段，Laravel 12 + PHP 8.2 也是新版本組合，套件相容性可能不如成熟版本穩定。此風險將於系統架構階段前再次與使用者確認因應策略（按需求文件使用最新版、或 Filament 降級至穩定版 v3）。
