<script setup>
import { onMounted, ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { getKeyword, createKeyword, updateKeyword } from '../api/keywords';

const props = defineProps({
    id: { type: String, default: null },
});

const router = useRouter();
const isEdit = computed(() => props.id !== null && props.id !== undefined);

const form = ref({
    name: '',
    is_active: true,
    crawl_interval_min: 10,
    time_range_type: '24h',
    time_range_custom_from: '',
    time_range_custom_to: '',
    thresholds: [],
    notification_channels: [],
});

const errors = ref({});
const saving = ref(false);

const metricOptions = [
    { value: 'views', label: 'Views' },
    { value: 'likes', label: 'Likes' },
    { value: 'replies', label: 'Replies' },
    { value: 'reposts', label: 'Reposts' },
    { value: 'quotes', label: 'Quotes' },
];

const operatorOptions = ['>', '>=', '=', '<', '<='];

const channelTypeOptions = [
    { value: 'email', label: 'Email' },
    { value: 'discord', label: 'Discord' },
    { value: 'slack', label: 'Slack' },
    { value: 'line', label: 'LINE' },
    { value: 'telegram', label: 'Telegram' },
    { value: 'webhook', label: 'Webhook' },
];

function addThreshold() {
    form.value.thresholds.push({ metric: 'views', operator: '>=', value: 1000 });
}

function removeThreshold(index) {
    form.value.thresholds.splice(index, 1);
}

function addChannel() {
    form.value.notification_channels.push({ channel_type: 'discord', config: {}, is_active: true, configText: '' });
}

function removeChannel(index) {
    form.value.notification_channels.splice(index, 1);
}

async function load() {
    if (!isEdit.value) {
        return;
    }

    const keyword = await getKeyword(props.id);
    form.value = {
        name: keyword.name,
        is_active: keyword.is_active,
        crawl_interval_min: keyword.crawl_interval_min,
        time_range_type: keyword.time_range_type,
        time_range_custom_from: keyword.time_range_custom_from ?? '',
        time_range_custom_to: keyword.time_range_custom_to ?? '',
        thresholds: keyword.thresholds.map((t) => ({ metric: t.metric, operator: t.operator, value: t.value })),
        // config 在 API 回應中已遮蔽，編輯既有管道時需重新輸入設定值才會更新
        notification_channels: keyword.notification_channels.map((c) => ({
            channel_type: c.channel_type,
            is_active: c.is_active,
            config: {},
            configText: '',
        })),
    };
}

async function submit() {
    errors.value = {};
    saving.value = true;

    const payload = {
        ...form.value,
        notification_channels: form.value.notification_channels.map((c) => {
            let config = {};
            try {
                config = c.configText ? JSON.parse(c.configText) : {};
            } catch {
                config = {};
            }
            return { channel_type: c.channel_type, is_active: c.is_active, config };
        }),
    };

    try {
        if (isEdit.value) {
            await updateKeyword(props.id, payload);
        } else {
            await createKeyword(payload);
        }
        router.push({ name: 'keywords' });
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="max-w-2xl space-y-6">
        <h1 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ isEdit ? '編輯關鍵字' : '新增關鍵字' }}
        </h1>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="rounded-lg bg-white dark:bg-gray-800 shadow p-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">關鍵字</label>
                    <input v-model="form.name" required class="mt-1 w-full rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                    <p v-if="errors.name" class="text-sm text-red-600">{{ errors.name[0] }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <input id="is_active" v-model="form.is_active" type="checkbox" />
                    <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">啟用</label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">巡檢頻率</label>
                    <select v-model.number="form.crawl_interval_min" class="mt-1 w-full rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option :value="1">每 1 分鐘</option>
                        <option :value="5">每 5 分鐘</option>
                        <option :value="10">每 10 分鐘</option>
                        <option :value="30">每 30 分鐘</option>
                        <option :value="60">每 1 小時</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">搜尋時間範圍</label>
                    <select v-model="form.time_range_type" class="mt-1 w-full rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="30min">最近 30 分鐘</option>
                        <option value="1h">最近 1 小時</option>
                        <option value="6h">最近 6 小時</option>
                        <option value="24h">最近 24 小時</option>
                        <option value="7d">最近 7 天</option>
                        <option value="custom">自訂日期</option>
                    </select>
                </div>

                <div v-if="form.time_range_type === 'custom'" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">起始時間</label>
                        <input v-model="form.time_range_custom_from" type="datetime-local" class="mt-1 w-full rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">結束時間</label>
                        <input v-model="form.time_range_custom_to" type="datetime-local" class="mt-1 w-full rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                    </div>
                </div>
            </div>

            <div class="rounded-lg bg-white dark:bg-gray-800 shadow p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900 dark:text-white">熱門度門檻（全部須同時符合）</h2>
                    <button type="button" class="text-sm text-indigo-600 hover:underline" @click="addThreshold">
                        + 新增條件
                    </button>
                </div>

                <div v-for="(threshold, index) in form.thresholds" :key="index" class="flex items-center gap-2">
                    <select v-model="threshold.metric" class="rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option v-for="option in metricOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <select v-model="threshold.operator" class="rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option v-for="op in operatorOptions" :key="op" :value="op">{{ op }}</option>
                    </select>
                    <input v-model.number="threshold.value" type="number" min="0" class="w-32 rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                    <button type="button" class="text-sm text-red-600 hover:underline" @click="removeThreshold(index)">移除</button>
                </div>

                <p v-if="!form.thresholds.length" class="text-sm text-gray-400">未設定門檻條件時，所有符合關鍵字的文章都會通過篩選</p>
            </div>

            <div class="rounded-lg bg-white dark:bg-gray-800 shadow p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900 dark:text-white">通知管道</h2>
                    <button type="button" class="text-sm text-indigo-600 hover:underline" @click="addChannel">
                        + 新增管道
                    </button>
                </div>

                <div v-for="(channel, index) in form.notification_channels" :key="index" class="space-y-2 border-b border-gray-100 dark:border-gray-700 pb-3 last:border-0">
                    <div class="flex items-center gap-2">
                        <select v-model="channel.channel_type" class="rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option v-for="option in channelTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <label class="flex items-center gap-1 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="channel.is_active" type="checkbox" /> 啟用
                        </label>
                        <button type="button" class="text-sm text-red-600 hover:underline ml-auto" @click="removeChannel(index)">移除</button>
                    </div>
                    <textarea
                        v-model="channel.configText"
                        rows="2"
                        placeholder='設定值（JSON 格式），例如 {"webhook_url": "https://..."}'
                        class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white font-mono"
                    />
                </div>

                <p v-if="!form.notification_channels.length" class="text-sm text-gray-400">尚未設定通知管道</p>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="saving"
                    class="rounded bg-indigo-600 text-white px-4 py-2 text-sm hover:bg-indigo-700 disabled:opacity-50"
                >
                    {{ saving ? '儲存中...' : '儲存' }}
                </button>
                <RouterLink :to="{ name: 'keywords' }" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">
                    取消
                </RouterLink>
            </div>
        </form>
    </div>
</template>
