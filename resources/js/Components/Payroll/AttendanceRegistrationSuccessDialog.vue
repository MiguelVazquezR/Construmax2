<script setup>
import { computed } from 'vue';
import { CircleCheck, Close } from '@element-plus/icons-vue';

const props = defineProps({
    modelValue: Boolean,
    userName: {
        type: String,
        default: '',
    },
    typeLabel: {
        type: String,
        default: '',
    },
    punchedAt: {
        type: String,
        default: '',
    },
    similarity: {
        type: [Number, String],
        default: null,
    },
    suggestedNextLabel: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});
</script>

<template>
    <el-dialog
        v-model="visible"
        width="min(92vw, 520px)"
        align-center
        append-to-body
        :show-close="false"
        class="attendance-registration-success-dialog"
    >
        <div class="relative overflow-hidden bg-gradient-to-b from-emerald-50 via-emerald-50/40 to-white px-6 pb-6 pt-8 text-center dark:from-emerald-500/15 dark:via-[#1e1e20] dark:to-[#1e1e20] sm:px-8">
            <div class="pointer-events-none absolute -right-12 -top-12 h-40 w-40 rounded-full bg-emerald-200/50 blur-3xl dark:bg-emerald-500/20"></div>
            <div class="pointer-events-none absolute -bottom-16 -left-12 h-40 w-40 rounded-full bg-teal-200/40 blur-3xl dark:bg-teal-500/10"></div>

            <button
                type="button"
                class="absolute right-4 top-4 z-10 flex h-9 w-9 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10 dark:hover:text-gray-200"
                aria-label="Cerrar"
                @click="visible = false"
            >
                <el-icon :size="18"><Close /></el-icon>
            </button>

            <div class="relative mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-white shadow-xl shadow-emerald-200/60 ring-1 ring-emerald-100 dark:bg-[#252529] dark:shadow-black/40 dark:ring-emerald-400/20">
                <el-icon :size="44" class="text-emerald-500"><CircleCheck /></el-icon>
            </div>

            <h2 class="relative mt-5 text-2xl font-bold tracking-tight text-gray-800 dark:text-white">
                ¡Registro exitoso!
            </h2>

            <p v-if="userName" class="relative mx-auto mt-2 max-w-sm text-sm font-semibold text-gray-600 dark:text-gray-300">
                {{ userName }}
            </p>

            <div class="relative mt-4 inline-flex flex-wrap items-center justify-center gap-2 rounded-full border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm dark:border-emerald-400/30 dark:bg-[#252529] dark:text-emerald-200">
                <span>{{ typeLabel }}</span>
                <span class="text-emerald-300">·</span>
                <span>{{ punchedAt }}</span>
            </div>

            <p v-if="similarity" class="relative mt-3 text-xs font-medium text-emerald-600 dark:text-emerald-300">
                Identificado por rostro · {{ similarity }}% de coincidencia
            </p>

            <p v-if="suggestedNextLabel" class="relative mt-4 text-sm text-gray-500 dark:text-gray-300">
                Siguiente sugerido:
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ suggestedNextLabel }}</span>
            </p>

            <el-button
                type="success"
                size="large"
                class="relative !mt-6 !h-12 w-full !rounded-xl !text-base !font-semibold"
                @click="visible = false"
            >
                Cerrar
            </el-button>

            <p class="relative mt-3 text-[11px] text-gray-400 dark:text-gray-500">
                Esta ventana se cierra automáticamente en unos segundos.
            </p>
        </div>
    </el-dialog>
</template>

<style scoped>
:global(.attendance-registration-success-dialog) {
    border-radius: 24px !important;
    overflow: hidden;
    padding: 0 !important;
}

:global(.attendance-registration-success-dialog .el-dialog__header) {
    display: none !important;
}

:global(.attendance-registration-success-dialog .el-dialog__body) {
    padding: 0 !important;
}
</style>
