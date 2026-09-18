<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

const props = defineProps({
    modelValue: Boolean,
    /** Budget panel object: { id, folio, name, ... } */
    budget: Object,
    /** Concepts selected in the breakdown table. */
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

const todayIsoDate = () => {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
};

const form = useForm({
    concepts: props.concepts.map((concept) => concept.id),
    payment_date: todayIsoDate(),
    payment_method: '',
    reference: '',
    notes: '',
    commission_amount: null,
});

const rules = {
    payment_date: [{ required: true, message: 'Selecciona la fecha de pago.', trigger: 'change' }],
};

const total = () => props.concepts.reduce((sum, concept) => sum + Number(concept.amount || 0), 0);

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: props.budget?.currency || 'MXN',
    }).format(value || 0);
};

function submit() {
    formRef.value?.validate((valid) => {
        if (!valid) return;

        form.post(route('expenses.budgets.concepts.mark-paid', props.budget.id), {
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success('Se registraron los pagos seleccionados.');
                emit('update:modelValue', false);
                emit('saved');
            },
        });
    });
}
</script>

<template>
    <el-dialog v-model="dialogVisible" title="Marcar conceptos como pagados" width="560px" top="8vh" destroy-on-close>
        <el-alert
            type="info"
            :closable="false"
            show-icon
            class="mb-4"
            :title="`${concepts.length} conceptos — ${formatCurrency(total())}`"
        >
            <template #default>
                Se registrará un gasto pagado por cada concepto seleccionado. Después podrás agregar comprobantes por separado.
            </template>
        </el-alert>

        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
            <div class="grid grid-cols-1 sm:grid-cols-2 sm:gap-x-4">
                <el-form-item
                    label="Fecha de pago"
                    prop="payment_date"
                    :error="form.errors.payment_date"
                    class="expense-date-item"
                >
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
                    <el-select v-model="form.payment_method" placeholder="Opcional" clearable class="w-full">
                        <el-option
                            v-for="option in paymentMethodOptions"
                            :key="option.value"
                            :label="option.label"
                            :value="option.value"
                        />
                    </el-select>
                </el-form-item>
            </div>

            <el-form-item label="Referencia" prop="reference" :error="form.errors.reference">
                <el-input v-model="form.reference" placeholder="Folio de factura o recibo (opcional)" maxlength="255" />
            </el-form-item>

            <el-form-item
                label="Comisión de la transacción (opcional)"
                prop="commission_amount"
                :error="form.errors.commission_amount"
                class="expense-commission-item"
            >
                <el-input-number
                    v-model="form.commission_amount"
                    :min="0"
                    :precision="2"
                    :controls="false"
                    placeholder="0.00"
                    class="w-full"
                >
                    <template #prefix>
                        <span class="text-gray-500">$</span>
                    </template>
                </el-input-number>
                <p class="text-xs text-gray-400 mt-1">
                    Si todo el pago se hizo en un solo movimiento con comisión (p. ej. OXXO), captúrala aquí: se registra una sola vez como comisión general del presupuesto.
                </p>
            </el-form-item>

            <el-form-item label="Notas" prop="notes" :error="form.errors.notes">
                <el-input
                    v-model="form.notes"
                    type="textarea"
                    :rows="2"
                    maxlength="2000"
                    show-word-limit
                    placeholder="Información adicional del pago (opcional)"
                />
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="dialogVisible = false">Cancelar</el-button>
            <el-button type="primary" :loading="form.processing" @click="submit">
                Marcar como pagados
            </el-button>
        </template>
    </el-dialog>
</template>

<style scoped>
/* Commission and date inputs: same width as the rest of the inputs */
:deep(.expense-commission-item .el-input-number),
:deep(.expense-date-item .el-date-editor) {
    width: 100%;
}
</style>
