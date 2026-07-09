<script setup>
import { onMounted, ref, watch } from 'vue';
import { listPosts } from '../api/posts';

const loading = ref(true);
const posts = ref([]);
const meta = ref(null);

const filters = ref({
    keyword: '',
    author: '',
    date_from: '',
    date_to: '',
    is_verified_author: '',
    ai_sentiment: '',
    sort: 'latest',
});

function sentimentLabel(sentiment) {
    return { positive: '正面', negative: '負面', neutral: '中立' }[sentiment] ?? '';
}

async function load() {
    loading.value = true;

    const params = Object.fromEntries(
        Object.entries(filters.value).filter(([, value]) => value !== '')
    );

    const response = await listPosts(params);
    posts.value = response.data;
    meta.value = response.meta;
    loading.value = false;
}

let debounceTimer = null;
watch(filters, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(load, 300);
}, { deep: true });

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <h1 class="text-lg font-semibold text-gray-900 dark:text-white">文章列表</h1>

        <div class="rounded-lg bg-white dark:bg-gray-800 shadow p-4 grid grid-cols-2 sm:grid-cols-7 gap-3">
            <input v-model="filters.keyword" placeholder="關鍵字" class="rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm" />
            <input v-model="filters.author" placeholder="作者" class="rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm" />
            <input v-model="filters.date_from" type="date" class="rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm" />
            <input v-model="filters.date_to" type="date" class="rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm" />
            <select v-model="filters.is_verified_author" class="rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                <option value="">全部帳號</option>
                <option value="1">已驗證</option>
                <option value="0">未驗證</option>
            </select>
            <select v-model="filters.ai_sentiment" class="rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                <option value="">全部情緒</option>
                <option value="positive">正面</option>
                <option value="negative">負面</option>
                <option value="neutral">中立</option>
            </select>
            <select v-model="filters.sort" class="rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                <option value="latest">最新</option>
                <option value="hottest">最熱門</option>
                <option value="views">Views 最多</option>
                <option value="likes">Likes 最多</option>
                <option value="replies">Replies 最多</option>
                <option value="reposts">Reposts 最多</option>
            </select>
        </div>

        <div v-if="loading" class="text-gray-500">載入中...</div>

        <div v-else class="rounded-lg bg-white dark:bg-gray-800 shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700 text-left text-gray-500 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-2">作者</th>
                        <th class="px-4 py-2">內容</th>
                        <th class="px-4 py-2">AI 摘要</th>
                        <th class="px-4 py-2">AI 情緒</th>
                        <th class="px-4 py-2">Views</th>
                        <th class="px-4 py-2">Likes</th>
                        <th class="px-4 py-2">Replies</th>
                        <th class="px-4 py-2">發文時間</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <tr v-for="post in posts" :key="post.id">
                        <td class="px-4 py-2 text-gray-900 dark:text-white">
                            {{ post.author_name }}
                            <span v-if="post.is_verified_author" class="text-indigo-500" title="已驗證">✓</span>
                        </td>
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-300 max-w-xs truncate">{{ post.content }}</td>
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-300 max-w-xs truncate">{{ post.ai_summary ?? '尚未分析' }}</td>
                        <td class="px-4 py-2">
                            <span
                                v-if="post.ai_sentiment"
                                class="inline-block rounded px-2 py-0.5 text-xs"
                                :class="{
                                    'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200': post.ai_sentiment === 'positive',
                                    'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-200': post.ai_sentiment === 'negative',
                                    'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200': post.ai_sentiment === 'neutral',
                                }"
                            >{{ sentimentLabel(post.ai_sentiment) }}</span>
                            <span v-else class="text-gray-400">尚未分析</span>
                        </td>
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ post.views_count }}</td>
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ post.likes_count }}</td>
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ post.replies_count }}</td>
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ new Date(post.posted_at).toLocaleString('zh-TW') }}</td>
                        <td class="px-4 py-2">
                            <a :href="post.threads_url" target="_blank" class="text-indigo-600 hover:underline">查看</a>
                        </td>
                    </tr>
                    <tr v-if="!posts.length">
                        <td colspan="9" class="px-4 py-6 text-center text-gray-400">尚無符合條件的文章</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="meta" class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400 border-t border-gray-100 dark:border-gray-700">
                共 {{ meta.total }} 筆，第 {{ meta.current_page }} / {{ meta.last_page }} 頁
            </div>
        </div>
    </div>
</template>
