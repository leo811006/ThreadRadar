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
    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900">
        <form
            class="w-full max-w-sm rounded-lg bg-white dark:bg-gray-800 shadow p-8 space-y-4"
            @submit.prevent="submit"
        >
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">ThreadRadar 登入</h1>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                <input
                    v-model="email"
                    type="email"
                    required
                    class="mt-1 w-full rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">密碼</label>
                <input
                    v-model="password"
                    type="password"
                    required
                    class="mt-1 w-full rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
            </div>

            <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

            <button
                type="submit"
                :disabled="loading"
                class="w-full rounded bg-indigo-600 text-white py-2 font-medium hover:bg-indigo-700 disabled:opacity-50"
            >
                {{ loading ? '登入中...' : '登入' }}
            </button>
        </form>
    </div>
</template>
