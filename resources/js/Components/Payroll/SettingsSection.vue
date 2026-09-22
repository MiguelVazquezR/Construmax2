<script setup>
defineProps({
    title: String,
    description: String,
});
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-6">
        <div class="flex items-start gap-3 mb-5">
            <div class="w-9 h-9 rounded-full bg-[#fdf0e7] dark:bg-[#3a2a1d] text-[#f26c17] flex items-center justify-center shrink-0">
                <slot name="icon" />
            </div>
            <div>
                <h3 class="font-bold text-gray-800 dark:text-gray-100">{{ title }}</h3>
                <p v-if="description" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ description }}</p>
            </div>
        </div>

        <slot />
    </div>
</template>
