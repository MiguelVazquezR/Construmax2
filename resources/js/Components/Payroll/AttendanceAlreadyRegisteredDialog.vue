<script setup>
import { computed } from 'vue';
import { Close, WarningFilled } from '@element-plus/icons-vue';

const props = defineProps({
    modelValue: Boolean,
    title: {
        type: String,
        default: 'Ese registro no se pudo guardar',
    },
    message: {
        type: String,
        default: '',
    },
    typeLabel: {
        type: String,
        default: '',
    },
    registeredAt: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue', 'closed']);

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const showSelectionHint = computed(
    () => !String(props.message || '').toLowerCase().includes('selecciona otro')
);
</script>

<template>
    <el-dialog
        v-model="visible"
        width="min(92vw, 520px)"
        align-center
        append-to-body
        :show-close="false"
        class="attendance-already-registered-dialog"
        @closed="emit('closed')"
    >
        <div class="relative overflow-hidden bg-gradient-to-b from-red-50 via-red-50/40 to-white px-6 pb-6 pt-8 text-center dark:from-red-500/15 dark:via-[#1e1e20] dark:to-[#1e1e20] sm:px-8">
            <div class="pointer-events-none absolute -right-12 -top-12 h-40 w-40 rounded-full bg-red-200/50 blur-3xl dark:bg-red-500/20"></div>
            <div class="pointer-events-none absolute -bottom-16 -left-12 h-40 w-40 rounded-full bg-rose-200/40 blur-3xl dark:bg-rose-500/10"></div>

            <button
                type="button"
                class="absolute right-4 top-4 z-10 flex h-9 w-9 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10 dark:hover:text-gray-200"
                aria-label="Cerrar"
                @click="visible = false"
            >
                <el-icon :size="18"><Close /></el-icon>
            </button>

            <div class="relative mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-white shadow-xl shadow-red-200/60 ring-1 ring-red-100 dark:bg-[#252529] dark:shadow-black/40 dark:ring-red-400/20">
                <el-icon :size="40" class="text-red-500"><WarningFilled /></el-icon>
            </div>

            <h2 class="relative mt-5 text-2xl font-bold tracking-tight text-gray-800 dark:text-white">
                {{ title }}
            </h2>

            <p class="relative mx-auto mt-2 max-w-sm text-sm leading-relaxed text-gray-500 dark:text-gray-300">
                {{ message }}
            </p>

            <div
                v-if="typeLabel || registeredAt"
                class="relative mt-4 inline-flex flex-wrap items-center justify-center gap-2 rounded-full border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700 shadow-sm dark:border-red-400/30 dark:bg-[#252529] dark:text-red-200"
            >
                <span v-if="typeLabel">{{ typeLabel }}</span>
                <span v-if="typeLabel && registeredAt" class="text-red-300">·</span>
                <span v-if="registeredAt">{{ registeredAt }}</span>
            </div>

            <p v-if="showSelectionHint" class="relative mt-5 text-sm font-medium text-gray-600 dark:text-gray-300">
                Selecciona otro tipo de registro para continuar.
            </p>

            <el-button
                type="danger"
                size="large"
                class="relative !mt-6 !h-12 w-full !rounded-xl !text-base !font-semibold"
                @click="visible = false"
            >
                Elegir otro registro
            </el-button>
        </div>
    </el-dialog>
</template>

<style scoped>
:global(.attendance-already-registered-dialog) {
    border-radius: 24px !important;
    overflow: hidden;
    padding: 0 !important;
}

:global(.attendance-already-registered-dialog .el-dialog__header) {
    display: none !important;
}

:global(.attendance-already-registered-dialog .el-dialog__body) {
    padding: 0 !important;
}
</style>
