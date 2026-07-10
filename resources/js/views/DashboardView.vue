<script setup>
import { onMounted, ref } from 'vue';
import { getDashboard } from '../api/dashboard';

const loading = ref(true);
const dashboard = ref(null);

onMounted(async () => {
    dashboard.value = await getDashboard();
    loading.value = false;
});
</script>

<template>
    <div v-if="loading" class="space-y-8">
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div v-for="i in 4" :key="i" class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                <div class="h-3 w-20 rounded bg-gray-200 dark:bg-gray-800 animate-pulse" />
                <div class="mt-3 h-7 w-14 rounded bg-gray-200 dark:bg-gray-800 animate-pulse" />
            </div>
        </section>
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div v-for="i in 3" :key="i" class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 space-y-3">
                <div class="h-4 w-32 rounded bg-gray-200 dark:bg-gray-800 animate-pulse" />
                <div v-for="j in 4" :key="j" class="h-10 rounded bg-gray-100 dark:bg-gray-800/60 animate-pulse" />
            </div>
        </section>
    </div>

    <div v-else class="space-y-8">
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">今日搜尋次數</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ dashboard.today.search_count }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">今日新增文章</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ dashboard.today.new_posts_count }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">今日更新文章</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ dashboard.today.updated_posts_count }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">今日通知次數</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ dashboard.today.notification_count }}</p>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">熱門文章 TOP20</h2>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    <li v-for="post in dashboard.top_posts" :key="post.id" class="py-2.5">
                        <a :href="post.threads_url" target="_blank" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline line-clamp-1">
                            {{ post.content || post.threads_url }}
                        </a>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ post.author_name }} · Views {{ post.views_count }} · Likes {{ post.likes_count }}
                        </p>
                    </li>
                    <li v-if="!dashboard.top_posts.length" class="py-6 text-center text-sm text-gray-400">尚無資料</li>
                </ul>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">熱門作者 TOP20</h2>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    <li v-for="author in dashboard.top_authors" :key="author.author_username" class="py-2.5">
                        <p class="text-sm text-gray-900 dark:text-white">{{ author.author_name }} <span class="text-gray-400">@{{ author.author_username }}</span></p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ author.post_count }} 篇文章 · 熱門度 {{ author.total_hotness }}
                        </p>
                    </li>
                    <li v-if="!dashboard.top_authors.length" class="py-6 text-center text-sm text-gray-400">尚無資料</li>
                </ul>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">熱門關鍵字 TOP20</h2>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    <li v-for="keyword in dashboard.top_keywords" :key="keyword.id" class="py-2.5 flex justify-between items-center">
                        <span class="text-sm text-gray-900 dark:text-white">{{ keyword.name }}</span>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 tabular-nums">{{ keyword.post_count }} 篇</span>
                    </li>
                    <li v-if="!dashboard.top_keywords.length" class="py-6 text-center text-sm text-gray-400">尚無資料</li>
                </ul>
            </div>
        </section>
    </div>
</template>
