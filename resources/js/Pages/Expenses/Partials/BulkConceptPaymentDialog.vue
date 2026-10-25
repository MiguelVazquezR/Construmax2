<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

const props = defineProps({
    modelValue: Boolean,
    /** Pending concepts selected in the picker, with their budget data. */
    concepts: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const dialogVisible = ref(props.modelValue);
watch(() => props.modelValue, (value) => { dialogVisible.value = value; });
watch(dialogVisible, (value) => emit('update:modelValue', value));

const formRef = ref(null);

const paymentMethodOptions = [
    { value: 'cash', label: 'Efectivo' },
    { value: 'transfer', label: 'Transferencia' },
    { value: 'card', label: 'Tarjeta' },
    { value: 'check', label: 'Cheque' },
    { value: 'other', label: 'Otro' },
];

const commissionModeOptions = [
    { value: 'none', label: 'Sin comisión' },
    { value: 'fixed', label: 'Monto total a repartir' },
];

const todayIsoDate = () => {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
};

const form = useForm({
    concepts: [],
    payment_date: todayIsoDate(),
    payment_method: '',
    commission_mode: 'none',
    commission_value: null,
    reference: '',
    notes: '',
});

watch(() => props.modelValue, (open) => {
    if (!open) return;

    form.concepts = props.concepts.map((concept) => concept.id);
    form.payment_date = todayIsoDate();
    form.payment_method = '';
    form.commission_mode = 'none';
    form.commission_value = null;
    form.reference = '';
    form.notes = '';
    form.clearErrors();
});

const count = computed(() => props.concepts.length);
const baseTotal = computed(() => props.concepts.reduce((sum, concept) => sum + Number(concept.amount || 0), 0));

const commissionTotal = computed(() => {
    const value = Number(form.commission_value || 0);

    if (form.commission_mode === 'none' || value <= 0) return 0;
    if (form.commission_mode === 'percent') return baseTotal.value * value / 100;

    return value;
});

const totalToPay = computed(() => baseTotal.value + commissionTotal.value);

const commissionPerExpense = computed(() => {
    if (form.commission_mode !== 'fixed' || !count.value) return null;

    return commissionTotal.value / count.value;
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(value || 0);
};

const rules = {
    payment_date: [{ required: true, message: 'Selecciona la fecha de pago.', trigger: 'change' }],
};

function submit() {
    formRef.value?.validate((valid) => {
        if (!valid) return;

        if (!count.value) {
            ElMessage.warning('Selecciona al menos un gasto por pagar.');

            return;
        }

        form.concepts = props.concepts.map((concept) => concept.id);

        form.post(route('expenses.budgets.concepts.bulk-payment'), {
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success('Pagos registrados correctamente.');
                emit('update:modelValue', false);
                emit('saved');
            },
        });
    });
}
</script>

<template>
    <el-dialog v-model="dialogVisible" title="Registrar pago de gastos" width="620px" top="8vh" destroy-on-close>
        <el-alert type="info" :closable="false" show-icon class="mb-4"
            :title="`${count} gastos por pagar · ${formatCurrency(baseTotal)}`">
            <template #default>
                Los gastos ya pagados no se incluyen en este movimiento.
            </template>
        </el-alert>

        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
            <div class="grid grid-cols-1 sm:grid-cols-2 sm:gap-x-4">
                <el-form-item label="Fecha de pago" prop="payment_date" :error="form.errors.payment_date">
                    <el-date-picker
                        v-model="form.payment_date"
                        type="date"
                        value-format="YYYY-MM-DD"
                        format="DD/MM/YYYY"
                        placeholder="Selecciona la fecha"
                        class="w-full"
                    />
                </el-form-item>

                <el-form-item label="Método de pago" prop="payment_method" :error="form.errors.payment_method">
                    <el-select v-model="form.payment_method" placeholder="Selecciona el método" clearable class="w-full">
                        <el-option
                            v-for="option in paymentMethodOptions"
                            :key="option.value"
                            :label="option.label"
                            :value="option.value"
                        />
                    </el-select>
                </el-form-item>

                <el-form-item label="Referencia" prop="reference" :error="form.errors.reference">
                    <el-input v-model="form.reference" placeholder="Folio o referencia del pago" />
                </el-form-item>

                <el-form-item label="Comisión" prop="commission_mode" :error="form.errors.commission_mode">
                    <el-select v-model="form.commission_mode" class="w-full">
                        <el-option
                            v-for="option in commissionModeOptions"
                            :key="option.value"
                            :label="option.label"
                            :value="option.value"
                        />
                    </el-select>
                </el-form-item>

                <el-form-item
                    v-if="form.commission_mode !== 'none'"
                    :label="form.commission_mode === 'percent' ? 'Porcentaje de comisión' : 'Monto total de comisión'"
                    prop="commission_value"
                    :error="form.errors.commission_value"
                >
                    <el-input-number
                        v-model="form.commission_value"
                        :min="0"
                        :precision="2"
                        :step="form.commission_mode === 'percent' ? 1 : 10"
                        controls-position="right"
                        class="w-full"
                    />
                </el-form-item>

                <el-form-item label="Notas" prop="notes" :error="form.errors.notes" class="sm:col-span-2">
                    <el-input v-model="form.notes" type="textarea" :rows="2" placeholder="Notas del pago (opcional)" />
                </el-form-item>
            </div>

            <el-alert v-if="form.commission_mode !== 'none' && commissionTotal > 0" type="warning" :closable="false" show-icon>
                <template #title>
                    <span v-if="commissionPerExpense !== null">
                        Comisión total {{ formatCurrency(commissionTotal) }} · {{ formatCurrency(commissionPerExpense) }} por cada uno de los {{ count }} gastos
                    </span>
                    <span v-else>
                        Comisión del {{ form.commission_value }}% sobre el monto de cada gasto · Total estimado {{ formatCurrency(commissionTotal) }}
                    </span>
                </template>
            </el-alert>
        </el-form>

        <div class="flex items-center justify-between mt-4 p-3 rounded-lg bg-gray-50 dark:bg-[#252529]">
            <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">Total a registrar</span>
            <span class="text-lg font-bold text-primary">{{ formatCurrency(totalToPay) }}</span>
        </div>

        <template #footer>
            <el-button @click="dialogVisible = false">Cancelar</el-button>
            <el-button type="primary" :loading="form.processing" @click="submit">
                Registrar pagos
            </el-button>
        </template>
    </el-dialog>
</template>
