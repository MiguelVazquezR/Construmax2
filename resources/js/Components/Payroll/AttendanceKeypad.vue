<script setup>
import { computed, ref } from 'vue';
import { Back } from '@element-plus/icons-vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    maxLength: {
        type: Number,
        default: 50,
    },
    loading: Boolean,
    submitLabel: {
        type: String,
        default: 'Registrar',
    },
    compactSubmitLabel: {
        type: String,
        default: 'Registrar',
    },
});

const emit = defineEmits(['update:modelValue', 'submit']);

// Employee numbers are usually numeric, but they may contain letters and
// hyphens (for example "EMP-0001"), so a second layout is always available.
const layout = ref('digits');

const digitKeys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'clear', '0', 'backspace'];
const letterKeys = [...'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '-', 'backspace'];

const keys = computed(() => (layout.value === 'digits' ? digitKeys : letterKeys));

const update = (value) => emit('update:modelValue', value.slice(0, props.maxLength));

const press = (key) => {
    if (props.loading) return;

    if (key === 'backspace') {
        update(props.modelValue.slice(0, -1));
        return;
    }

    if (key === 'clear') {
        update('');
        return;
    }

    if (props.modelValue.length >= props.maxLength) return;

    update(props.modelValue + key);
};

const toggleLayout = () => {
    layout.value = layout.value === 'digits' ? 'letters' : 'digits';
};
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col rounded-2xl border border-gray-100 dark:border-[#2b2b2e] bg-white dark:bg-[#1e1e20] p-3 lg:p-5 shadow-sm">
        <div class="flex items-center justify-between gap-2 mb-2">
            <p class="text-[11px] uppercase tracking-widest text-gray-400">Número de empleado</p>
            <el-button text size="small" class="!px-2 !text-xs !font-semibold" @click="toggleLayout">
                {{ layout === 'digits' ? 'A-Z' : '0-9' }}
            </el-button>
        </div>

        <el-input
            :model-value="modelValue"
            class="employee-number-display"
            size="large"
            maxlength="50"
            inputmode="none"
            placeholder="Número de empleado"
            @update:model-value="update"
            @keyup.enter="emit('submit')"
        />

        <div
            class="mt-3 grid min-h-0 flex-1 grid-rows-4 gap-2"
            :class="layout === 'digits' ? 'grid-cols-3' : 'grid-cols-7'"
        >
            <el-button
                v-for="key in keys"
                :key="key"
                class="!m-0 !h-full !min-h-10 !w-full !px-0 !text-base !font-semibold"
                :class="layout === 'letters' ? '!text-sm' : ''"
                :disabled="loading"
                @click="press(key)"
            >
                <el-icon v-if="key === 'backspace'" :size="20"><Back /></el-icon>
                <span v-else-if="key === 'clear'">Limpiar</span>
                <span v-else>{{ key }}</span>
            </el-button>
        </div>

        <el-button
            type="primary"
            color="#f26c17"
            size="large"
            class="!mt-3 !h-14 !text-base w-full"
            :loading="loading"
            @click="emit('submit')"
        >
            <span class="lg:hidden">{{ compactSubmitLabel }}</span>
            <span class="hidden lg:inline">{{ submitLabel }}</span>
        </el-button>
    </div>
</template>

<style scoped>
.employee-number-display :deep(.el-input__inner) {
    text-align: center;
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: 0.12em;
}
</style>
