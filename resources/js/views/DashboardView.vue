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
    <div v-if="loading" class="text-gray-500">載入中...</div>

    <div v-else class="space-y-8">
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="rounded-lg bg-white dark:bg-gray-800 shadow p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">今日搜尋次數</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ dashboard.today.search_count }}</p>
            </div>
            <div class="rounded-lg bg-white dark:bg-gray-800 shadow p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">今日新增文章</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ dashboard.today.new_posts_count }}</p>
            </div>
            <div class="rounded-lg bg-white dark:bg-gray-800 shadow p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">今日更新文章</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ dashboard.today.updated_posts_count }}</p>
            </div>
            <div class="rounded-lg bg-white dark:bg-gray-800 shadow p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">今日通知次數</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ dashboard.today.notification_count }}</p>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="rounded-lg bg-white dark:bg-gray-800 shadow p-4">
                <h2 class="font-semibold text-gray-900 dark:text-white mb-3">熱門文章 TOP20</h2>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li v-for="post in dashboard.top_posts" :key="post.id" class="py-2">
                        <a :href="post.threads_url" target="_blank" class="text-sm text-indigo-600 hover:underline line-clamp-1">
                            {{ post.content || post.threads_url }}
                        </a>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ post.author_name }} · Views {{ post.views_count }} · Likes {{ post.likes_count }}
                        </p>
                    </li>
                    <li v-if="!dashboard.top_posts.length" class="py-2 text-sm text-gray-400">尚無資料</li>
                </ul>
            </div>

            <div class="rounded-lg bg-white dark:bg-gray-800 shadow p-4">
                <h2 class="font-semibold text-gray-900 dark:text-white mb-3">熱門作者 TOP20</h2>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li v-for="author in dashboard.top_authors" :key="author.author_username" class="py-2">
                        <p class="text-sm text-gray-900 dark:text-white">{{ author.author_name }} <span class="text-gray-400">@{{ author.author_username }}</span></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ author.post_count }} 篇文章 · 熱門度 {{ author.total_hotness }}
                        </p>
                    </li>
                    <li v-if="!dashboard.top_authors.length" class="py-2 text-sm text-gray-400">尚無資料</li>
                </ul>
            </div>

            <div class="rounded-lg bg-white dark:bg-gray-800 shadow p-4">
                <h2 class="font-semibold text-gray-900 dark:text-white mb-3">熱門關鍵字 TOP20</h2>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li v-for="keyword in dashboard.top_keywords" :key="keyword.id" class="py-2 flex justify-between">
                        <span class="text-sm text-gray-900 dark:text-white">{{ keyword.name }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ keyword.post_count }} 篇</span>
                    </li>
                    <li v-if="!dashboard.top_keywords.length" class="py-2 text-sm text-gray-400">尚無資料</li>
                </ul>
            </div>
        </section>
    </div>
</template>
