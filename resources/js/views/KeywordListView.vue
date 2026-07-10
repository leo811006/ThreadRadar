<script setup>
import { onMounted, ref } from 'vue';
import { listKeywords, deleteKeyword, crawlKeywordNow } from '../api/keywords';

const loading = ref(true);
const keywords = ref([]);
const crawlingIds = ref(new Set());
const crawlMessage = ref('');

async function load() {
    loading.value = true;
    const response = await listKeywords();
    keywords.value = response.data;
    loading.value = false;
}

async function handleDelete(id) {
    if (!confirm('確定要刪除這組關鍵字嗎？')) {
        return;
    }

    await deleteKeyword(id);
    await load();
}

async function handleCrawlNow(id) {
    crawlingIds.value.add(id);
    crawlMessage.value = '';

    try {
        const result = await crawlKeywordNow(id);
        crawlMessage.value = result.message;
    } finally {
        crawlingIds.value.delete(id);
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold text-gray-900 dark:text-white">關鍵字管理</h1>
            <RouterLink
                :to="{ name: 'keywords.create' }"
                class="rounded-lg bg-indigo-600 text-white text-sm font-medium px-4 py-2 hover:bg-indigo-500 transition-colors"
            >
                新增關鍵字
            </RouterLink>
        </div>

        <div
            v-if="crawlMessage"
            class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-200 text-sm px-4 py-2.5"
        >
            {{ crawlMessage }}
        </div>

        <div v-if="loading" class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 space-y-3">
            <div v-for="i in 5" :key="i" class="h-10 rounded bg-gray-100 dark:bg-gray-800/60 animate-pulse" />
        </div>

        <div v-else class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/80 dark:bg-gray-800/50 text-left text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">關鍵字</th>
                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">啟用</th>
                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">巡檢頻率</th>
                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">時間範圍</th>
                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">門檻數</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="keyword in keywords" :key="keyword.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                        <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-white">{{ keyword.name }}</td>
                        <td class="px-4 py-2.5">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-md px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                                :class="keyword.is_active
                                    ? 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/20'
                                    : 'bg-gray-50 text-gray-500 ring-gray-500/20 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-600/30'"
                            >
                                <span class="inline-block w-1.5 h-1.5 rounded-full" :class="keyword.is_active ? 'bg-green-500' : 'bg-gray-400'" />
                                {{ keyword.is_active ? '啟用中' : '已停用' }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300">每 {{ keyword.crawl_interval_min }} 分鐘</td>
                        <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300">{{ keyword.time_range_type }}</td>
                        <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300 tabular-nums">{{ keyword.thresholds.length }}</td>
                        <td class="px-4 py-2.5 text-right space-x-3">
                            <button
                                type="button"
                                class="text-indigo-600 dark:text-indigo-400 hover:underline disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                                :disabled="crawlingIds.has(keyword.id)"
                                title="不受巡檢頻率限制，立即加入巡檢佇列（需 queue worker 執行中才會實際跑）"
                                @click="handleCrawlNow(keyword.id)"
                            >
                                {{ crawlingIds.has(keyword.id) ? '處理中...' : '立即巡檢' }}
                            </button>
                            <RouterLink
                                :to="{ name: 'keywords.edit', params: { id: keyword.id } }"
                                class="text-indigo-600 dark:text-indigo-400 hover:underline"
                            >
                                編輯
                            </RouterLink>
                            <button type="button" class="text-red-600 dark:text-red-400 hover:underline cursor-pointer" @click="handleDelete(keyword.id)">
                                刪除
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!keywords.length">
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400">尚無關鍵字，請先新增</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
