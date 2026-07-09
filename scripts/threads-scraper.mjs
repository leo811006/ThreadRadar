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

    // 頁面用無限捲動載入，捲幾次以取得更多結果。
    for (let i = 0; i < 5; i++) {
        await page.mouse.wheel(0, 2000);
        await page.waitForTimeout(800);
    }

    const posts = await page.evaluate(() => {
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
            // 互動數字（讚/回覆/轉發/引用）也會落在同一個 selector，需排除後合併剩餘文字。
            // 互動數字可能以中文「萬/億」縮寫呈現（如「1.2萬」），也一併排除。
            const textParts = Array.from(container.querySelectorAll('span[dir="auto"]'))
                .filter((s) => !s.closest('a[href^="/@"]') && !isInNestedContainer(s))
                .map((s) => (s.textContent ?? '').trim())
                .filter((t) => t && !/^\d+([.,]\d+)?[KMk萬億]?$/.test(t));

            const timeEl = Array.from(container.querySelectorAll('time')).find((t) => !isInNestedContainer(t));

            results.push({
                permalink: href.startsWith('http') ? href : `https://www.threads.com${href}`,
                username,
                text: textParts.join(' '),
                timestamp: timeEl ? timeEl.getAttribute('datetime') ?? '' : '',
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
