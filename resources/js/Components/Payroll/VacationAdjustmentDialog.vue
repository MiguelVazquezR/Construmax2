<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    userId: { type: Number, default: null },
    userName: { type: String, default: '' },
    availableDays: { type: Number, default: 0 },
});

const emit = defineEmits(['saved']);

const visible = ref(false);
const processing = ref(false);
const errors = ref({});
const form = ref({ type: 'grant', days: 1, reason: '' });

const typeOptions = [
    { value: 'initial', label: 'Saldo inicial' },
    { value: 'grant', label: 'Agregar días' },
    { value: 'adjustment', label: 'Ajustar días' },
];

const typeHints = {
    initial: 'Días que se abonan una sola vez, por ejemplo el saldo que el colaborador ya tenía antes de usar el sistema.',
    grant: 'Días adicionales que la empresa otorga y se suman al saldo disponible.',
    adjustment: 'Corrección del saldo: usa un número negativo para descontar días.',
};

const currentHint = computed(() => typeHints[form.value.type] ?? '');
const isAdjustment = computed(() => form.value.type === 'adjustment');

const roundDays = (value) => Math.round(value * 100) / 100;

const daysError = computed(() => {
    const days = Number(form.value.days);

    if (!days) return 'Captura una cantidad de días distinta de cero.';
    if (days < 0 && !isAdjustment.value) return 'Solo el ajuste manual permite descontar días.';
    if (days > 365 || days < -365) return 'No se pueden capturar más de 365 días en un movimiento.';
    return null;
});

const previewBalance = computed(() =>
    roundDays(Number(props.availableDays || 0) + (Number(form.value.days) || 0))
);

const open = (type = 'grant') => {
    errors.value = {};
    form.value = {
        type,
        days: type === 'adjustment' ? -1 : 1,
        reason: '',
    };
    visible.value = true;
};

watch(() => form.value.type, (type) => {
    if (type !== 'adjustment' && Number(form.value.days) < 0) {
        form.value.days = 1;
    }
});

const submit = () => {
    if (daysError.value) return;

    processing.value = true;

    router.post(route('payroll.vacations.adjustments.store', { user: props.userId }), form.value, {
        preserveScroll: true,
        onSuccess: () => {
            visible.value = false;
            emit('saved');
        },
        onError: (validationErrors) => {
            errors.value = validationErrors;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

defineExpose({ open });
</script>

<template>
    <el-dialog
        v-model="visible"
        title="Movimiento de saldo de vacaciones"
        width="520px"
        top="12vh"
        :close-on-click-modal="false"
    >
        <div class="space-y-4">
            <div class="rounded-lg bg-gray-50 dark:bg-[#252529]/60 border border-gray-200 dark:border-gray-800 px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                <span class="font-semibold text-gray-800 dark:text-gray-100">{{ userName }}</span>
                · Días disponibles hoy: <span class="font-semibold text-emerald-600">{{ availableDays }}</span>
            </div>

            <el-form label-position="top" size="default">
                <el-form-item label="Tipo de movimiento" required :error="errors.type">
                    <el-radio-group v-model="form.type">
                        <el-radio-button v-for="option in typeOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </el-radio-button>
                    </el-radio-group>
                </el-form-item>

                <p class="text-xs text-gray-500 dark:text-gray-400 -mt-3 mb-1">{{ currentHint }}</p>

                <el-form-item label="Días" required :error="errors.days || daysError" class="vacation-days-item">
                    <el-input-number
                        v-model="form.days"
                        :min="isAdjustment ? -365 : 0.5"
                        :max="365"
                        :step="0.5"
                        :precision="2"
                        controls-position="right"
                    />
                </el-form-item>

                <el-form-item label="Motivo" :error="errors.reason">
                    <el-input
                        v-model="form.reason"
                        type="textarea"
                        :rows="2"
                        maxlength="255"
                        placeholder="Referencia u observaciones (opcional)"
                    />
                </el-form-item>
            </el-form>

            <div class="rounded-lg border border-gray-200 dark:border-gray-800 px-4 py-3 text-sm">
                <span class="text-gray-500 dark:text-gray-400">Nuevo saldo disponible:</span>
                <span
                    class="font-bold ml-1"
                    :class="previewBalance < 0 ? 'text-red-500' : 'text-emerald-600'"
                >
                    {{ previewBalance }} día(s)
                </span>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <el-button @click="visible = false">Cancelar</el-button>
                <el-button
                    type="primary"
                    color="#f26c17"
                    :loading="processing"
                    :disabled="!!daysError"
                    @click="submit"
                >
                    Registrar movimiento
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<style scoped>
:deep(.vacation-days-item .el-input-number) {
    width: 100%;
}
</style>
