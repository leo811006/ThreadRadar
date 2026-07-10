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

const thresholdGroups = computed(() => {
    const groups = new Map();
    form.value.thresholds.forEach((threshold, index) => {
        const group = threshold.group ?? 0;
        if (!groups.has(group)) {
            groups.set(group, []);
        }
        groups.get(group).push(index);
    });
    return [...groups.entries()].sort((a, b) => a[0] - b[0]);
});

function nextGroupNumber() {
    return form.value.thresholds.reduce((max, t) => Math.max(max, t.group ?? 0), -1) + 1;
}

function addThresholdGroup() {
    form.value.thresholds.push({ metric: 'views', operator: '>=', value: 1000, group: nextGroupNumber() });
}

function addThresholdToGroup(group) {
    form.value.thresholds.push({ metric: 'views', operator: '>=', value: 1000, group });
}

function removeThreshold(index) {
    form.value.thresholds.splice(index, 1);
}

function addChannel() {
    form.value.notification_channels.push({ id: null, channel_type: 'discord', config: {}, is_active: true, configText: '' });
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
        thresholds: keyword.thresholds.map((t) => ({ metric: t.metric, operator: t.operator, value: t.value, group: t.group ?? 0 })),
        // config 在 API 回應中已遮蔽為 '******'，configText 留空表示「不修改」。
        // id 一併保留，後端會依 id 比對既有管道，未提供新設定值時保留資料庫現有 config。
        notification_channels: keyword.notification_channels.map((c) => ({
            id: c.id,
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

    const invalidChannelIndexes = [];

    const notificationChannels = form.value.notification_channels.map((c, index) => {
        let config = {};

        if (c.configText) {
            try {
                config = JSON.parse(c.configText);
            } catch {
                invalidChannelIndexes.push(index);
            }
        }

        return { id: c.id ?? undefined, channel_type: c.channel_type, is_active: c.is_active, config };
    });

    if (invalidChannelIndexes.length) {
        errors.value = {
            notification_channels: [
                `第 ${invalidChannelIndexes.map((i) => i + 1).join('、')} 個通知管道的設定值不是合法的 JSON 格式，請修正後再儲存。`,
            ],
        };
        saving.value = false;
        return;
    }

    const payload = { ...form.value, notification_channels: notificationChannels };

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
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">關鍵字</label>
                    <input v-model="form.name" required class="mt-1.5 w-full rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <p v-if="errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.name[0] }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <input id="is_active" v-model="form.is_active" type="checkbox" class="rounded border border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                    <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">啟用</label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">巡檢頻率</label>
                    <select v-model.number="form.crawl_interval_min" class="mt-1.5 w-full rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option :value="1">每 1 分鐘</option>
                        <option :value="5">每 5 分鐘</option>
                        <option :value="10">每 10 分鐘</option>
                        <option :value="30">每 30 分鐘</option>
                        <option :value="60">每 1 小時</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">搜尋時間範圍</label>
                    <select v-model="form.time_range_type" class="mt-1.5 w-full rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                        <input v-model="form.time_range_custom_from" type="datetime-local" class="mt-1.5 w-full rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">結束時間</label>
                        <input v-model="form.time_range_custom_to" type="datetime-local" class="mt-1.5 w-full rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">熱門度門檻</h2>
                        <p class="mt-0.5 text-xs text-gray-400">同一組內條件須同時符合（AND），任一組全數符合即算命中（組間 OR）</p>
                    </div>
                    <button type="button" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer" @click="addThresholdGroup">
                        + 新增條件組
                    </button>
                </div>

                <div
                    v-for="([group, indexes], groupIndex) in thresholdGroups"
                    :key="group"
                    class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 space-y-2"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                            第 {{ groupIndex + 1 }} 組{{ groupIndex > 0 ? '（或）' : '' }}
                        </span>
                        <button type="button" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer" @click="addThresholdToGroup(group)">
                            + 加入此組條件
                        </button>
                    </div>

                    <div v-for="index in indexes" :key="index" class="flex items-center gap-2">
                        <select v-model="form.thresholds[index].metric" class="rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option v-for="option in metricOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <select v-model="form.thresholds[index].operator" class="rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option v-for="op in operatorOptions" :key="op" :value="op">{{ op }}</option>
                        </select>
                        <input v-model.number="form.thresholds[index].value" type="number" min="0" class="w-32 rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <button type="button" class="text-sm text-red-600 dark:text-red-400 hover:underline cursor-pointer" @click="removeThreshold(index)">移除</button>
                    </div>
                </div>

                <p v-if="!form.thresholds.length" class="text-sm text-gray-400">未設定門檻條件時，所有符合關鍵字的文章都會通過篩選</p>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">通知管道</h2>
                    <button type="button" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer" @click="addChannel">
                        + 新增管道
                    </button>
                </div>

                <div v-for="(channel, index) in form.notification_channels" :key="index" class="space-y-2 border-b border-gray-100 dark:border-gray-800 pb-3 last:border-0">
                    <div class="flex items-center gap-2">
                        <select v-model="channel.channel_type" class="rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option v-for="option in channelTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <label class="flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="channel.is_active" type="checkbox" class="rounded border border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" /> 啟用
                        </label>
                        <button type="button" class="text-sm text-red-600 dark:text-red-400 hover:underline ml-auto cursor-pointer" @click="removeChannel(index)">移除</button>
                    </div>
                    <textarea
                        v-model="channel.configText"
                        rows="2"
                        placeholder='設定值（JSON 格式），例如 {"webhook_url": "https://..."}'
                        class="w-full text-sm rounded-lg border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white font-mono focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="isEdit && channel.id" class="text-xs text-gray-400">留空表示保留原設定值不變更</p>
                </div>

                <p v-if="!form.notification_channels.length" class="text-sm text-gray-400">尚未設定通知管道</p>
                <p v-if="errors.notification_channels" class="text-sm text-red-600 dark:text-red-400">{{ errors.notification_channels[0] }}</p>
            </div>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    :disabled="saving"
                    class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-medium hover:bg-indigo-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
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
