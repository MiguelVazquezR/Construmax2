<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    subtitle: { type: String, default: '' },
    icon: { type: Object, default: null },
    iconBg: { type: String, default: 'bg-blue-50 dark:bg-blue-900/20' },
    iconColor: { type: String, default: 'text-blue-500' },
    valueColor: { type: String, default: 'text-gray-800 dark:text-white' },
});
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ label }}</p>
                <h3 :class="['text-3xl font-bold mt-1', valueColor]">{{ value }}</h3>
            </div>
            <div v-if="icon" :class="['p-2 rounded-lg', iconBg, iconColor]">
                <el-icon :size="24"><component :is="icon" /></el-icon>
            </div>
        </div>
        <p v-if="subtitle" class="text-xs text-gray-400 mt-2">{{ subtitle }}</p>
        <slot />
    </div>
</template>
