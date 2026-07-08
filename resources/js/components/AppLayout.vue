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
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <nav class="bg-white dark:bg-gray-800 shadow-sm">
            <div class="max-w-6xl mx-auto px-4 flex items-center justify-between h-14">
                <div class="flex items-center gap-6">
                    <span class="font-semibold text-gray-900 dark:text-white">ThreadRadar</span>
                    <RouterLink
                        :to="{ name: 'dashboard' }"
                        class="text-sm text-gray-600 dark:text-gray-300 hover:text-indigo-600"
                        active-class="text-indigo-600 font-medium"
                    >
                        Dashboard
                    </RouterLink>
                    <RouterLink
                        :to="{ name: 'keywords' }"
                        class="text-sm text-gray-600 dark:text-gray-300 hover:text-indigo-600"
                        active-class="text-indigo-600 font-medium"
                    >
                        關鍵字管理
                    </RouterLink>
                    <RouterLink
                        :to="{ name: 'posts' }"
                        class="text-sm text-gray-600 dark:text-gray-300 hover:text-indigo-600"
                        active-class="text-indigo-600 font-medium"
                    >
                        文章列表
                    </RouterLink>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ auth.user?.email }}</span>
                    <button
                        class="text-sm text-gray-600 dark:text-gray-300 hover:text-red-600"
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
