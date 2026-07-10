// 非官方資料來源：headless browser 直接解析 https://www.threads.com/search 的公開頁面 DOM。
// 未經 Meta App Review、不呼叫 graph.threads.net，形式上違反 Meta Automated Data Collection Terms。
// 僅供個人非商業使用，参考 https://github.com/Chuanyin1202/threads-toolkit 的做法。
// DOM 結構隨時可能被 Threads 改版打掉，選擇器需視情況更新。
import { chromium } from 'playwright';

const keyword = process.argv[2];
if (!keyword) {
    console.error(JSON.stringify({ error: 'missing keyword argument' }));
    process.exit(1);
}

const searchUrl = `https://www.threads.com/search?q=${encodeURIComponent(keyword)}&serp_type=default`;

const browser = await chromium.launch({
    headless: true,
    args: ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox'],
});

try {
    const context = await browser.newContext({
        userAgent:
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        viewport: { width: 1280, height: 900 },
    });
    const page = await context.newPage();

    await page.goto(searchUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });

    // waitForSelector 逾時通常代表頁面被導向驗證碼/登入牆，或選擇器已被 Threads 改版打掉——
    // 兩者都不等於「這個關鍵字沒有結果」，須明確標記為 blocked，讓 PHP 端與零結果區分開來，
    // 否則巡檢會一直記錄「成功、零筆」而讓封鎖狀態長期不被察覺（見 ThreadsScraperSearchProvider）。
    const foundResults = await page
        .waitForSelector('a[href*="/post/"]', { timeout: 15000 })
        .then(() => true)
        .catch(() => false);

    if (!foundResults) {
        process.stdout.write(JSON.stringify({ blocked: true, data: [] }));
        await browser.close();
        process.exit(0);
    }

    // 頁面用無限捲動載入，捲幾次以取得更多結果。最多 12 次，但連續兩次捲動後
    // 貼文連結數量都沒有增加（該關鍵字結果已經捲完/沒有更多結果可載入）就提早
    // 結束，避免結果少的關鍵字仍固定付出 12 次的等待成本。
    let previousAnchorCount = 0;
    let stagnantRounds = 0;

    for (let i = 0; i < 12 && stagnantRounds < 2; i++) {
        await page.mouse.wheel(0, 2000);
        await page.waitForTimeout(800);

        const anchorCount = await page.locator('a[href*="/post/"]').count();
        stagnantRounds = anchorCount > previousAnchorCount ? 0 : stagnantRounds + 1;
        previousAnchorCount = anchorCount;
    }

    const posts = await page.evaluate(() => {
        // 讚/回覆/轉發三個互動按鈕的 svg 都帶有語意化 aria-label（"Like"/"Comment"/"Repost"），
        // 按鈕本身（div[role="button"]）沒有 aria-label，故以 svg 反查最近的按鈕祖先。
        // Share 按鈕（svg[aria-label="Share"]）不是統計數字，不擷取。
        // 分享數字節點為 0 時整個不渲染、其餘三者為 0 時渲染成空字串——皆一律視為 0，
        // 與「找不到對應按鈕」（代表頁面結構已變、無法信任）的 null 明確區分。
        //
        // 2026-07-10：回覆按鈕的 aria-label 已由 Threads 從 "Reply" 改為 "Comment"
        // （實測 DOM 印出的 svg aria-label 列表為 More/Like/Comment/Repost/Share），
        // 導致 replies 固定抓不到而回傳 null，觸發 PostUpsertService 的互動數
        // 「全 null 或全有值」不變條件例外。若未來又改版，同樣會在此處斷線。
        const METRIC_LABELS = { Like: 'likes', Comment: 'replies', Repost: 'reposts' };

        // isInNestedContainer：與 username/text/time 擷取邏輯共用同一份排除判斷，
        // 避免轉發/引用貼文內嵌套的原貼文卡片也有自己的 Like/Comment/Repost 動作列，
        // 讓 querySelectorAll 依文件順序找到的第一顆 svg 可能屬於嵌套的原貼文，
        // 而非外層轉發貼文本身，導致互動數被誤植到轉發者身上。
        function extractInteractionCounts(container, isInNestedContainer) {
            const counts = { likes: null, replies: null, reposts: null };

            for (const svg of container.querySelectorAll('svg[aria-label]')) {
                if (isInNestedContainer(svg)) continue;

                const key = METRIC_LABELS[svg.getAttribute('aria-label')];
                if (!key || counts[key] !== null) continue;

                const button = svg.closest('div[role="button"]');
                if (!button) continue;

                // wrapper/numberNode 是固定索引路徑，假設按鈕內部結構固定為
                // children[0].children[0].children[1] 才是數字節點。若 Threads
                // 改版導致這個路徑走不到（wrapper 或 numberNode 為 undefined），
                // 代表結構本身已不可信，須回傳 null（而非 '0'）交由 parseCount
                // 判定為無法解析——不可與「找到數字節點、但渲染成的文字恰好是
                // 空字串」（該按鈕確實是 0，見上方註解）混為一談，否則結構性
                // 失效會被無聲偽裝成一筆真實的 0，讓資料品質問題完全無跡可循。
                const wrapper = button.children?.[0]?.children?.[0];
                const numberNode = wrapper?.children?.[1];
                counts[key] = numberNode !== undefined ? (numberNode.textContent ?? '').trim() : null;
            }

            return counts;
        }

        function parseCount(text) {
            if (text === null) return null;
            if (text === '') return 0;

            // 數字很大時 Threads 會加上「+」尾碼表示「至少該數字」（如 "999+"、"1萬+"），
            // 而非精確值——原正則不接受尾碼「+」，導致這類熱門貼文的該指標被誤判為
            // 無法解析而回傳 null，同貼文其餘指標卻是正常數字，觸發 PostUpsertService
            // 的「全 null 或全有值」不變條件例外。以「+」表示的下限數字仍是有意義的值，
            // 故接受並照樣解析為該數字（略去 "+"），而非放棄整筆判定為 null。
            const match = text.match(/^(\d+(?:[.,]\d+)?)\s*([KMk萬億]?)\+?$/);
            if (!match) return null;

            const value = parseFloat(match[1].replace(',', ''));
            const multiplier = { K: 1_000, k: 1_000, M: 1_000_000, 萬: 10_000, 億: 100_000_000 }[match[2]] ?? 1;

            return Math.round(value * multiplier);
        }

        const anchors = Array.from(document.querySelectorAll('a[href*="/post/"]'));
        const seen = new Set();
        const results = [];

        for (const anchor of anchors) {
            const href = anchor.getAttribute('href');
            // /post/{id}/media 是同一篇貼文的媒體變體連結，會與正文連結重複計入，需排除。
            if (!href || seen.has(href) || href.includes('/media')) continue;
            seen.add(href);

            const container = anchor.closest('div[data-pressable-container="true"]');
            if (!container) continue;

            // 轉發/引用貼文會在 container 內嵌套第二層 data-pressable-container（原貼文卡片），
            // 其中包含原作者的使用者連結與原文文字。若不排除，會把轉發者誤判成原作者、
            // 或把兩篇貼文的文字合併成一段——故任何嵌套容器內的節點都要從擷取範圍中剔除。
            const nestedContainers = Array.from(container.querySelectorAll('div[data-pressable-container="true"]'));
            const isInNestedContainer = (el) => nestedContainers.some((nested) => nested.contains(el));

            const usernameLink = Array.from(container.querySelectorAll('a[href^="/@"]')).find(
                (a) => !isInNestedContainer(a)
            );
            const username = usernameLink ? (usernameLink.getAttribute('href') ?? '').replace('/@', '') : '';

            // 正文會被拆成多個 span[dir="auto"]（換行處各自成一個 span），且使用者名稱、
            // 互動數字（讚/回覆/轉發）也會落在同一個 selector，需排除後合併剩餘文字。
            // 互動數字可能以 K/M 或中文「萬/億」縮寫呈現（如「1.2萬」），也一併排除。
            // 此排除正則需與 parseCount 的解析正則同步涵蓋「+」尾碼（如「999+」），
            // 否則帶「+」的互動數字不會被排除、混入 text 欄位污染貼文內容。
            const textParts = Array.from(container.querySelectorAll('span[dir="auto"]'))
                .filter((s) => !s.closest('a[href^="/@"]') && !isInNestedContainer(s))
                .map((s) => (s.textContent ?? '').trim())
                .filter((t) => t && !/^\d+([.,]\d+)?[KMk萬億]?\+?$/.test(t));

            const timeEl = Array.from(container.querySelectorAll('time')).find((t) => !isInNestedContainer(t));

            const rawCounts = extractInteractionCounts(container, isInNestedContainer);

            results.push({
                permalink: href.startsWith('http') ? href : `https://www.threads.com${href}`,
                username,
                text: textParts.join(' '),
                timestamp: timeEl ? timeEl.getAttribute('datetime') ?? '' : '',
                likes: parseCount(rawCounts.likes),
                replies: parseCount(rawCounts.replies),
                reposts: parseCount(rawCounts.reposts),
            });
        }

        return results;
    });

    process.stdout.write(JSON.stringify({ data: posts }));
} catch (error) {
    console.error(JSON.stringify({ error: error.message }));
    process.exitCode = 1;
} finally {
    await browser.close();
}
