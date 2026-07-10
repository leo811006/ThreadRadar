<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();

const email = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

async function submit() {
    error.value = '';
    loading.value = true;

    try {
        await auth.login(email.value, password.value);
        router.push({ name: 'dashboard' });
    } catch (e) {
        error.value = e.response?.data?.message ?? '登入失敗，請檢查帳號密碼。';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-950 px-4">
        <form
            class="w-full max-w-sm rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm p-8 space-y-5"
            @submit.prevent="submit"
        >
            <div class="flex flex-col items-center gap-3 text-center">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-600 text-white font-semibold">TR</span>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900 dark:text-white">ThreadRadar 登入</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Threads 關鍵字監測後台</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                <input
                    v-model="email"
                    type="email"
                    required
                    autocomplete="email"
                    class="mt-1.5 w-full rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">密碼</label>
                <input
                    v-model="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="mt-1.5 w-full rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <p v-if="error" class="rounded-lg bg-red-50 dark:bg-red-950 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ error }}</p>

            <button
                type="submit"
                :disabled="loading"
                class="w-full rounded-lg bg-indigo-600 text-white py-2 font-medium hover:bg-indigo-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
            >
                {{ loading ? '登入中...' : '登入' }}
            </button>
        </form>
    </div>
</template>
