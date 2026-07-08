# 階段一附件：Threads 資料來源可行性分析

本文件回答需求文件要求的六個問題：官方 API 現況、合法公開資料取得方式、瀏覽器自動化必要性、登入需求、封鎖風險、維護成本與穩定性提升手段。

---

## 1. 官方 API 現況

Meta 提供正式 Threads API（Graph API 家族）。文件明示主要用途為「代表使用者發布內容並僅向該使用者本人顯示」，即**帳號自管**。

但存在獨立的 **Keyword Search 端點**：申請 `threads_keyword_search` 權限並通過 App Review 後，**可搜尋公開貼文**（非僅自己帳號的內容）。未獲批准前，搜尋僅回傳已認證使用者自己的貼文。系統性/機敏字詞會被平台過濾、回傳空陣列。

- 官方文件：https://developers.facebook.com/docs/threads/overview
- Keyword Search：https://developers.facebook.com/docs/threads/keyword-search/

**這是本專案最重要的發現**：官方合法路徑存在，且明確支援本專案的核心用例（關鍵字搜尋公開貼文），並非只能靠爬蟲。

---

## 2. Rate Limit / 認證 / 費用

| 項目 | 內容 |
|---|---|
| 認證方式 | OAuth 2.0，短期 token 換 60 天長期 token |
| 申請門檻 | 需通過 Meta App Review（錄影 demo、隱私權政策、服務條款 URL；多數商業情境需商業驗證） |
| Keyword Search 配額 | **每使用者 / 每 24 小時最多 2,200 次查詢**（跨 App 累加；查無結果不計入配額） |
| 一般發文配額 | 250 則貼文/日、1,000 則回覆/日（本專案不需要，僅讀取） |
| 費用 | 官方文件未提定價，目前免費但配額受限，無長期保證 |

參考：https://developers.facebook.com/docs/graph-api/overview/rate-limiting/ 、 https://www.blotato.com/blog/threads-api-pricing

**架構意涵**：2,200 次/日的配額，若以「N 組關鍵字 × 每小時查詢一次」估算，最多可支撐約 91 組關鍵字/日（2200÷24）；若需要 1 分鐘頻率的高頻監控，單一關鍵字就會用掉 1,440 次配額，僅能支撐 1-2 組關鍵字。**高頻 + 多關鍵字會撞到配額上限**，需要在排程層設計配額感知的降級或排隊機制（見 03 系統設計文件）。

---

## 3. robots.txt 與服務條款（爬蟲路徑的合規邊界）

- `threads.com/robots.txt`（`threads.net` 301 導向至此）對 ClaudeBot、GPTBot、PerplexityBot、Google-Extended、Amazonbot 等十餘個 AI/爬蟲 User-Agent **完全封鎖**；未列名代理商一律 `Disallow: /`。
- Meta「Automated Data Collection Terms」明文禁止**未經書面事先許可的自動化資料蒐集**，禁止轉售/授權蒐集所得資料、禁止商用無限查詢、禁止規避 robots.txt。

參考：https://www.threads.com/robots.txt 、 https://www.facebook.com/legal/automated_data_collection_terms

**結論**：若走爬蟲路徑，形式上違反平台 ToS 是明確事實，不是灰色地帶的模糊風險。

---

## 4. 第三方付費 API（Scraper-as-API）

存在多家非官方服務，**繞過官方 API、直接爬公開頁面轉售**：

| 服務 | 定價模式 |
|---|---|
| EnsembleData | 免費 50 次/日；付費階梯至約 $1,400/月（Platinum） |
| Data365 | Basic €300/月起、Standard €850/月起 |
| RapidAPI 各家 Threads Scraper | 常見 $0/100 次起跳，量大可到 $200/百萬次 |

風險：無 SLA 保證、依賴逆向工程的非公開端點（平台隨時可改版打掉）、明確違反 Meta ToS 中的自動蒐集條款；帳號/IP 被封鎖是常態風險，非例外情況。

參考：https://www.socialcrawl.dev/blog/best-social-media-scraping-apis-2026

---

## 5. Headless Browser 直接抓取的可行性

- Threads **搜尋功能需登入**（一般地區）。唯一例外：**歐盟/EEA/瑞士因 DMA 法規開放未登入的唯讀瀏覽與搜尋**。其他地區匿名訪客只能看單一公開貼文/個人檔案頁，看不到搜尋結果頁。
- 反爬層：Cloudflare（Turnstile 挑戰）+ 瀏覽器指紋辨識（`navigator.webdriver`、Headless UA 特徵等）。
- 業界共識：2026 年原生 Playwright/Puppeteer 在資料中心 IP 上**幾乎必被攔截**，需搭配 stealth 外掛、住宅代理、CAPTCHA 破解服務才有機會，成功率不穩定，成本隨規模上升非線性增加。

參考：https://www.zenrows.com/blog/playwright-cloudflare-bypass

**結論**：爬蟲路徑不只是法律風險，技術上的長期維護成本也顯著高於官方 API（需要持續應對平台反制手段的軍備競賽）。

---

## 6. 法律先例

### Meta v. Bright Data（2024，加州聯邦法院，Judge Chen）
Meta 敗訴。理由：ToS 僅約束「已登入使用中的帳號持有人」，**登出後爬公開資料不受 ToS 合約條款約束**。Meta 隨後撤告並放棄上訴權。這是目前對「爬公開資料」最有利的判例。

**但**：本案未涉及非公開資料，且不代表爬蟲完全零風險——只是降低了「單純登出爬公開頁」的**合約違約**風險，不影響平台仍可用技術手段（IP 封鎖、帳號封鎖）自行防禦。

參考：https://www.fbm.com/publications/major-decision-affects-law-of-scraping-and-online-data-collection-meta-platforms-v-bright-data/

### hiQ Labs v. LinkedIn（2022 和解）
九巡上訴法院確認爬公開網站**不構成 CFAA「未經授權存取」**（刑事/準刑事層面無罪），但案件最終在地方法院被判 **hiQ 違反 LinkedIn 使用者協議**（合約層面違約），雙方 2022 年和解，hiQ 須付 **50 萬美元賠償**並永久停止爬取、刪除所有蒐集資料與程式碼。

參考：https://en.wikipedia.org/wiki/HiQ_Labs_v._LinkedIn

**這說明「不違反 CFAA」不等於「不違反 ToS 合約責任」**——違約求償依然可能重創一個商業化爬蟲產品，即使爬的是公開資料。

---

## 7. 綜合結論與建議方案

| 方案 | 合法性 | 資料完整度 | 穩定性 | 成本 | 是否支援關鍵字搜尋 |
|---|---|---|---|---|---|
| **官方 Keyword Search API** | 完全合法 | 中（僅公開貼文，受配額限制範圍） | 高（官方維護） | 目前免費，配額受限 | 是，原生支援 |
| 第三方 Scraper API | 違反 Meta ToS，轉嫁風險給第三方 | 高 | 中（依賴逆向工程端點，可能隨時失效） | $300-1400+/月 | 是 |
| 自建 Headless Browser 爬蟲 | 違反 Meta ToS（形式上），合約求償風險真實存在 | 高 | 低（需持續對抗反爬機制，多數地區需登入） | 開發+維護成本最高（stealth、代理、CAPTCHA） | 需自行實作 |

### 建議：**官方 API 優先，架構預留爬蟲/第三方 API 作為 fallback 或補充**

理由：
1. 官方 API 是唯一無法律/合約風險的路徑，且原生支援本專案核心用例。
2. 2,200 次/日配額雖限制了「多關鍵字 + 高頻」的極端情境，但足以支撐 MVP 與大多數中等規模監控需求（如 10-20 組關鍵字、10-30 分鐘頻率）。
3. 架構上必須做 **Provider 介面隔離**（`SearchProviderInterface`），讓官方 API 是預設實作，未來若業務需求超出配額，可在不改動核心業務邏輯的前提下，疊加第三方 API 或爬蟲作為配額用盡後的 fallback，並讓使用者/系統管理者明確知情這類 fallback 的合規風險（不應該是靜默切換）。
4. 這個決策直接影響技術選型：不再需要以「哪個語言的 headless browser 生態最成熟」作為選型的主要權重，而是回到「哪個語言的 HTTP client / OAuth / Queue / Web 框架生態最適合構建一個標準的 API 整合型 Web 應用」。技術選型評估將在此基礎上進行（見 03-tech-stack-evaluation.md）。
