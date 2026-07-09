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
                class="rounded bg-indigo-600 text-white text-sm px-4 py-2 hover:bg-indigo-700"
            >
                新增關鍵字
            </RouterLink>
        </div>

        <div v-if="crawlMessage" class="rounded bg-indigo-50 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-200 text-sm px-4 py-2">
            {{ crawlMessage }}
        </div>

        <div v-if="loading" class="text-gray-500">載入中...</div>

        <div v-else class="rounded-lg bg-white dark:bg-gray-800 shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700 text-left text-gray-500 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-2">關鍵字</th>
                        <th class="px-4 py-2">啟用</th>
                        <th class="px-4 py-2">巡檢頻率</th>
                        <th class="px-4 py-2">時間範圍</th>
                        <th class="px-4 py-2">門檻數</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <tr v-for="keyword in keywords" :key="keyword.id">
                        <td class="px-4 py-2 text-gray-900 dark:text-white">{{ keyword.name }}</td>
                        <td class="px-4 py-2">
                            <span
                                class="inline-block w-2 h-2 rounded-full"
                                :class="keyword.is_active ? 'bg-green-500' : 'bg-gray-300'"
                            />
                        </td>
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-300">每 {{ keyword.crawl_interval_min }} 分鐘</td>
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ keyword.time_range_type }}</td>
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ keyword.thresholds.length }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            <button
                                class="text-indigo-600 hover:underline disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="crawlingIds.has(keyword.id)"
                                title="不受巡檢頻率限制，立即加入巡檢佇列（需 queue worker 執行中才會實際跑）"
                                @click="handleCrawlNow(keyword.id)"
                            >
                                {{ crawlingIds.has(keyword.id) ? '處理中...' : '立即巡檢' }}
                            </button>
                            <RouterLink
                                :to="{ name: 'keywords.edit', params: { id: keyword.id } }"
                                class="text-indigo-600 hover:underline"
                            >
                                編輯
                            </RouterLink>
                            <button class="text-red-600 hover:underline" @click="handleDelete(keyword.id)">
                                刪除
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!keywords.length">
                        <td colspan="6" class="px-4 py-6 text-center text-gray-400">尚無關鍵字，請先新增</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
