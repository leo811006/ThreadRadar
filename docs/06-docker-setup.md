# Docker 開發/部署環境使用說明

## 服務組成（對應 04-system-architecture.md §4）

| 服務 | 說明 |
|---|---|
| `app` | PHP-FPM，處理 HTTP 請求（REST API + Filament Admin），僅 dispatch 工作，不執行巡檢/通知 |
| `nginx` | Reverse proxy，對外開放 `localhost:8000` |
| `mysql` | MySQL 8，資料持久層 |
| `redis` | Cache + Queue driver |
| `queue-worker` | 執行 `CrawlKeywordJob`/`SendNotificationJob`，可水平擴展（`docker compose up --scale queue-worker=3`） |
| `scheduler` | 每分鐘觸發 `schedule:run`，僅負責判斷到期關鍵字並 dispatch，不做實際巡檢 |

## 兩份環境變數檔案

- **`.env`**：本機開發用（`php artisan serve` 直接跑），`DB_HOST`/`REDIS_HOST` 指向 `127.0.0.1`。
- **`.env.docker`**（需自行複製 `.env.docker.example` 建立，已加入 `.gitignore`）：Docker Compose 內各容器共用，`DB_HOST=mysql`、`REDIS_HOST=redis`，走容器網路互相呼叫，不能用 `127.0.0.1`。

**重要**：`docker-compose.yml` 中 `mysql` 服務的 `MYSQL_DATABASE`/`MYSQL_USER`/`MYSQL_PASSWORD` 為寫死值（`threadradar` / `threadradar` / `secret`），因為 docker-compose 的 `${VAR}` 插值語法讀取的是專案根目錄的 `.env`（docker-compose 自己的慣例），而非 `.env.docker`。**修改 `.env.docker` 內的資料庫密碼時，必須同步修改 `docker-compose.yml` 內 `mysql` 服務的 `environment` 區塊**，兩者目前要求手動保持一致。

## 啟動方式

```bash
cp .env.docker.example .env.docker
# 視需要調整 .env.docker 內容（例如正式環境的 THREADS_ACCESS_TOKEN、通知管道 webhook）

docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Admin 後台：`http://localhost:8000`（Filament Panel 路徑於階段十實作時確認）
REST API：`http://localhost:8000/api/...`

## 水平擴展 Queue Worker

```bash
docker compose up -d --scale queue-worker=3
```
