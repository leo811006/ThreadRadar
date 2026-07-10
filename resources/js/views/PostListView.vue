<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { listPosts } from '../api/posts';
import { listKeywords } from '../api/keywords';

const loading = ref(true);
const posts = ref([]);
const meta = ref(null);
const keywordOptions = ref([]);

const selectedPost = ref(null);

const filters = ref({
    keyword: '',
    author: '',
    date_from: '',
    date_to: '',
    is_verified_author: '',
    is_matched: '',
    ai_sentiment: '',
    sort: 'latest',
});

const page = ref(1);

function sentimentLabel(sentiment) {
    return { positive: '正面', negative: '負面', neutral: '中立' }[sentiment] ?? '';
}

function openDetail(post) {
    selectedPost.value = post;
}

function closeDetail() {
    selectedPost.value = null;
}

function handleKeydown(event) {
    if (event.key === 'Escape' && selectedPost.value) {
        closeDetail();
    }
}

async function load() {
    loading.value = true;

    const params = Object.fromEntries(
        Object.entries({ ...filters.value, page: page.value }).filter(([, value]) => value !== '')
    );

    const response = await listPosts(params);
    posts.value = response.data;
    meta.value = response.meta;
    loading.value = false;
}

function goToPage(target) {
    if (loading.value || !meta.value || target < 1 || target > meta.value.last_page || target === page.value) {
        return;
    }

    page.value = target;
    load();
}

let debounceTimer = null;
watch(filters, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        page.value = 1;
        load();
    }, 300);
}, { deep: true });

async function loadKeywordOptions() {
    try {
        const response = await listKeywords(1, { per_page: 100 });
        keywordOptions.value = response.data;
    } catch {
        keywordOptions.value = [];
    }
}

onMounted(() => {
    load();
    loadKeywordOptions();
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div class="space-y-4">
        <h1 class="text-lg font-semibold text-gray-900 dark:text-white">文章列表</h1>

        <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 grid grid-cols-2 sm:grid-cols-8 gap-3">
            <select v-model="filters.keyword" class="rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">全部關鍵字</option>
                <option v-for="kw in keywordOptions" :key="kw.id" :value="kw.name">{{ kw.name }}</option>
            </select>
            <input v-model="filters.author" placeholder="作者" class="rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500" />
            <input v-model="filters.date_from" type="date" class="rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500" />
            <input v-model="filters.date_to" type="date" class="rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500" />
            <select v-model="filters.is_verified_author" class="rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">全部帳號</option>
                <option value="1">已驗證</option>
                <option value="0">未驗證</option>
            </select>
            <select v-model="filters.is_matched" class="rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">全部狀態</option>
                <option value="1">已達標</option>
                <option value="0">未達標</option>
            </select>
            <select v-model="filters.ai_sentiment" class="rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">全部情緒</option>
                <option value="positive">正面</option>
                <option value="negative">負面</option>
                <option value="neutral">中立</option>
            </select>
            <select v-model="filters.sort" class="rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="latest">最新</option>
                <option value="hottest">最熱門</option>
                <option value="views">Views 最多</option>
                <option value="likes">Likes 最多</option>
                <option value="replies">Replies 最多</option>
                <option value="reposts">Reposts 最多</option>
            </select>
        </div>

        <div v-if="loading" class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 space-y-3">
            <div v-for="i in 8" :key="i" class="h-10 rounded bg-gray-100 dark:bg-gray-800/60 animate-pulse" />
        </div>

        <div v-else class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/80 dark:bg-gray-800/50 text-left text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">作者</th>
                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">關鍵字</th>
                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">內容</th>
                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">AI 摘要</th>
                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">AI 情緒</th>
                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">Views</th>
                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">Likes</th>
                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">Replies</th>
                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">發文時間</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr
                        v-for="post in posts"
                        :key="post.id"
                        tabindex="0"
                        role="button"
                        class="cursor-pointer hover:bg-indigo-50/60 dark:hover:bg-indigo-500/10 transition-colors focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
                        @click="openDetail(post)"
                        @keydown.enter="openDetail(post)"
                    >
                        <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-white whitespace-nowrap">
                            {{ post.author_name }}
                            <span v-if="post.is_verified_author" class="text-indigo-500" title="已驗證">✓</span>
                        </td>
                        <td class="px-4 py-2.5 max-w-[10rem]" @click.stop>
                            <div v-if="post.keywords?.length" class="flex flex-wrap gap-1">
                                <span
                                    v-for="kw in post.keywords"
                                    :key="kw"
                                    class="inline-flex rounded-md px-1.5 py-0.5 text-xs font-medium bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20"
                                >{{ kw }}</span>
                            </div>
                            <div v-else-if="post.crawled_keywords?.length" class="flex flex-wrap gap-1">
                                <span
                                    v-for="kw in post.crawled_keywords"
                                    :key="kw"
                                    class="inline-flex rounded-md px-1.5 py-0.5 text-xs font-medium bg-gray-100 text-gray-500 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-600/30"
                                    title="未達標"
                                >{{ kw }}</span>
                            </div>
                            <span v-else class="text-gray-400 text-xs">—</span>
                        </td>
                        <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300 max-w-sm">
                            <span class="line-clamp-2 break-words">{{ post.content }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300 max-w-sm">
                            <span class="line-clamp-2 break-words">{{ post.ai_summary ?? '尚未分析' }}</span>
                        </td>
                        <td class="px-4 py-2.5">
                            <span
                                v-if="post.ai_sentiment"
                                class="inline-flex rounded-md px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                                :class="{
                                    'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/20': post.ai_sentiment === 'positive',
                                    'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20': post.ai_sentiment === 'negative',
                                    'bg-gray-100 text-gray-600 ring-gray-500/10 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600/30': post.ai_sentiment === 'neutral',
                                }"
                            >{{ sentimentLabel(post.ai_sentiment) }}</span>
                            <span v-else class="text-gray-400 text-xs">尚未分析</span>
                        </td>
                        <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300 tabular-nums">{{ post.views_count }}</td>
                        <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300 tabular-nums">{{ post.likes_count }}</td>
                        <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300 tabular-nums">{{ post.replies_count }}</td>
                        <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ new Date(post.posted_at).toLocaleString('zh-TW') }}</td>
                        <td class="px-4 py-2.5">
                            <a :href="post.threads_url" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline" @click.stop>查看</a>
                        </td>
                    </tr>
                    <tr v-if="!posts.length">
                        <td colspan="10" class="px-4 py-12 text-center text-gray-400">尚無符合條件的文章</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="meta" class="px-4 py-2.5 flex items-center justify-between border-t border-gray-100 dark:border-gray-800">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    共 {{ meta.total }} 筆，第 {{ meta.current_page }} / {{ meta.last_page }} 頁
                </span>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="px-2.5 py-1 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent cursor-pointer"
                        :disabled="loading || page <= 1"
                        @click="goToPage(page - 1)"
                    >上一頁</button>
                    <button
                        type="button"
                        class="px-2.5 py-1 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent cursor-pointer"
                        :disabled="loading || page >= meta.last_page"
                        @click="goToPage(page + 1)"
                    >下一頁</button>
                </div>
            </div>
        </div>

        <div
            v-if="selectedPost"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4"
            @click.self="closeDetail"
        >
            <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xl p-6 space-y-5">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ selectedPost.author_name }}
                            <span v-if="selectedPost.is_verified_author" class="text-indigo-500" title="已驗證">✓</span>
                        </h2>
                        <p v-if="selectedPost.author_username" class="text-xs text-gray-500 dark:text-gray-400">@{{ selectedPost.author_username }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer"
                        aria-label="關閉"
                        @click="closeDetail"
                    >✕</button>
                </div>

                <p class="text-sm text-gray-800 dark:text-gray-100 whitespace-pre-wrap break-words leading-relaxed">{{ selectedPost.content }}</p>

                <div v-if="selectedPost.images?.length" class="grid grid-cols-3 gap-2">
                    <img
                        v-for="(src, i) in selectedPost.images"
                        :key="i"
                        :src="src"
                        :alt="`${selectedPost.author_name} 的貼文圖片 ${i + 1}`"
                        class="rounded-lg object-cover w-full h-24 bg-gray-100 dark:bg-gray-800"
                        loading="lazy"
                        @error="(e) => e.target.style.visibility = 'hidden'"
                    />
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 p-3">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Views</p>
                        <p class="text-sm font-semibold tabular-nums text-gray-900 dark:text-white">{{ selectedPost.views_count }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Likes</p>
                        <p class="text-sm font-semibold tabular-nums text-gray-900 dark:text-white">{{ selectedPost.likes_count }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Replies</p>
                        <p class="text-sm font-semibold tabular-nums text-gray-900 dark:text-white">{{ selectedPost.replies_count }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Reposts</p>
                        <p class="text-sm font-semibold tabular-nums text-gray-900 dark:text-white">{{ selectedPost.reposts_count }}</p>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">AI 摘要</p>
                    <p class="text-sm text-gray-800 dark:text-gray-100">{{ selectedPost.ai_summary ?? '尚未分析' }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">AI 情緒</span>
                    <span
                        v-if="selectedPost.ai_sentiment"
                        class="inline-flex rounded-md px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                        :class="{
                            'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/20': selectedPost.ai_sentiment === 'positive',
                            'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20': selectedPost.ai_sentiment === 'negative',
                            'bg-gray-100 text-gray-600 ring-gray-500/10 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600/30': selectedPost.ai_sentiment === 'neutral',
                        }"
                    >{{ sentimentLabel(selectedPost.ai_sentiment) }}</span>
                    <span v-else class="text-gray-400 text-xs">尚未分析</span>
                </div>

                <div v-if="selectedPost.ai_tags?.length">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">AI 標籤</p>
                    <div class="flex flex-wrap gap-1">
                        <span
                            v-for="tag in selectedPost.ai_tags"
                            :key="tag"
                            class="inline-flex rounded-md px-1.5 py-0.5 text-xs font-medium bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-400 dark:ring-indigo-500/20"
                        >{{ tag }}</span>
                    </div>
                </div>

                <div v-if="selectedPost.keywords?.length">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">收錄關鍵字（已達標）</p>
                    <div class="flex flex-wrap gap-1">
                        <span
                            v-for="kw in selectedPost.keywords"
                            :key="kw"
                            class="inline-flex rounded-md px-1.5 py-0.5 text-xs font-medium bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20"
                        >{{ kw }}</span>
                    </div>
                </div>
                <div v-else-if="selectedPost.crawled_keywords?.length">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">收錄關鍵字（未達標）</p>
                    <div class="flex flex-wrap gap-1">
                        <span
                            v-for="kw in selectedPost.crawled_keywords"
                            :key="kw"
                            class="inline-flex rounded-md px-1.5 py-0.5 text-xs font-medium bg-gray-100 text-gray-500 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-600/30"
                        >{{ kw }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 pt-3 border-t border-gray-100 dark:border-gray-800">
                    <span>發文時間：{{ new Date(selectedPost.posted_at).toLocaleString('zh-TW') }}</span>
                    <a :href="selectedPost.threads_url" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">前往 Threads 查看</a>
                </div>
            </div>
        </div>
    </div>
</template>
