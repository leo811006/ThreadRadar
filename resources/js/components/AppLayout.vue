<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();

async function handleLogout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-950">
        <nav class="sticky top-0 z-40 bg-white/90 dark:bg-gray-900/90 backdrop-blur border-b border-gray-200 dark:border-gray-800">
            <div class="max-w-6xl mx-auto px-4 flex items-center justify-between h-14">
                <div class="flex items-center gap-6">
                    <span class="flex items-center gap-2 font-semibold text-gray-900 dark:text-white">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-indigo-600 text-white text-xs">TR</span>
                        ThreadRadar
                    </span>
                    <RouterLink
                        :to="{ name: 'dashboard' }"
                        class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"
                        active-class="!text-indigo-600 dark:!text-indigo-400"
                    >
                        Dashboard
                    </RouterLink>
                    <RouterLink
                        :to="{ name: 'keywords' }"
                        class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"
                        active-class="!text-indigo-600 dark:!text-indigo-400"
                    >
                        關鍵字管理
                    </RouterLink>
                    <RouterLink
                        :to="{ name: 'posts' }"
                        class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"
                        active-class="!text-indigo-600 dark:!text-indigo-400"
                    >
                        文章列表
                    </RouterLink>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ auth.user?.email }}</span>
                    <button
                        type="button"
                        class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors cursor-pointer"
                        @click="handleLogout"
                    >
                        登出
                    </button>
                </div>
            </div>
        </nav>

        <main class="max-w-6xl mx-auto px-4 py-6">
            <RouterView />
        </main>
    </div>
</template>
