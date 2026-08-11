<script setup>
import { ElButton, ElIcon } from 'element-plus';
import { User, Setting } from '@element-plus/icons-vue';

defineProps({
    /** Current calendar mode: 'personal' | 'field-work' */
    modelValue: { type: String, required: true },

    /** Current view: 'day' | 'week' | 'month' */
    viewMode: { type: String, required: true },

    /** Whether the user can view the field work calendar */
    canViewFieldWork: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'update:viewMode']);

const viewOptions = [
    { value: 'day', label: 'Día' },
    { value: 'week', label: 'Semana' },
    { value: 'month', label: 'Mes' },
];

function setMode(mode) {
    emit('update:modelValue', mode);
}

function setView(view) {
    emit('update:viewMode', view);
}
</script>

<template>
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-4">

        <!-- Calendar mode toggle -->
        <div class="flex items-center rounded-lg border border-zinc-200/60 dark:border-zinc-700 overflow-hidden">
            <button
                class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium transition-colors"
                :class="modelValue === 'personal'
                    ? 'bg-primary text-white'
                    : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700'"
                @click="setMode('personal')"
            >
                <el-icon size="14"><User /></el-icon>
                Calendario personal
            </button>
            <button
                v-if="canViewFieldWork"
                class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium transition-colors"
                :class="modelValue === 'field-work'
                    ? 'bg-primary text-white'
                    : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700'"
                @click="setMode('field-work')"
            >
                <el-icon size="14"><Setting /></el-icon>
                Trabajo en campo
            </button>
        </div>

        <!-- View toggle: Día / Semana / Mes -->
        <div class="flex items-center rounded-lg border border-zinc-200/60 dark:border-zinc-700 overflow-hidden">
            <button
                v-for="opt in viewOptions"
                :key="opt.value"
                class="px-4 py-2 text-sm font-medium transition-colors"
                :class="viewMode === opt.value
                    ? 'bg-zinc-100 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-100'
                    : 'bg-white dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-700'"
                @click="setView(opt.value)"
            >
                {{ opt.label }}
            </button>
        </div>

    </div>
</template>
