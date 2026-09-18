<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import ExpenseReceiptsField from './ExpenseReceiptsField.vue';

const props = defineProps({
    modelValue: Boolean,
    /** Budget panel object: { id, folio, name, ... } */
    budget: Object,
    /** Concept row from the budget breakdown with an optional linked expense. */
    concept: Object,
});

const emit = defineEmits(['update:modelValue', 'saved']);

const dialogVisible = ref(props.modelValue);
watch(() => props.modelValue, (value) => { dialogVisible.value = value; });
watch(dialogVisible, (value) => emit('update:modelValue', value));

const formRef = ref(null);

const statusOptions = [
    { value: 'pending', label: 'Pendiente de pago' },
    { value: 'paid', label: 'Pagado' },
    { value: 'cancelled', label: 'Cancelado' },
];

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
    status: props.concept?.expense?.status ?? 'paid',
    payment_date: props.concept?.expense?.expense_date ?? props.concept?.payment_date ?? todayIsoDate(),
    payment_method: props.concept?.expense?.payment_method ?? '',
    commission_amount: props.concept?.expense?.commission_amount || null,
    reference: props.concept?.expense?.reference ?? '',
    notes: props.concept?.expense?.notes ?? '',
    receipts: [],
    remove_receipt_ids: [],
});

const onReceiptsChange = ({ files, remove_ids }) => {
    form.receipts = files;
    form.remove_receipt_ids = remove_ids;
};

const rules = {
    status: [{ required: true, message: 'Selecciona el estatus del gasto.', trigger: 'change' }],
    payment_date: [{ required: true, message: 'Selecciona la fecha de pago.', trigger: 'change' }],
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: props.budget?.currency || 'MXN',
    }).format(value || 0);
};

function submit() {
    formRef.value?.validate((valid) => {
        if (!valid) return;

        form.post(route('expenses.budgets.concepts.payment', [props.budget.id, props.concept.id]), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success('Pago del concepto registrado correctamente.');
                emit('update:modelValue', false);
                emit('saved');
            },
        });
    });
}
</script>

<template>
    <el-dialog
        v-model="dialogVisible"
        :title="concept?.expense ? 'Editar pago del concepto' : 'Registrar pago del concepto'"
        width="620px"
        top="8vh"
        destroy-on-close
    >
        <el-alert
            type="info"
            :closable="false"
            show-icon
            class="mb-4"
            :title="`${concept?.concept} — ${formatCurrency(concept?.amount)}`"
        >
            <template #default>
                El concepto y el monto se administran desde el desglose del presupuesto.
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

                <el-form-item label="Estatus" prop="status" :error="form.errors.status">
                    <el-select v-model="form.status" class="w-full">
                        <el-option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :label="option.label"
                            :value="option.value"
                        />
                    </el-select>
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

                <el-form-item
                    label="Comisión (opcional)"
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
                    <p class="text-xs text-gray-400 mt-1">Comisión del canal de pago (p. ej. cobro en OXXO).</p>
                </el-form-item>

                <el-form-item label="Referencia" prop="reference" :error="form.errors.reference">
                    <el-input v-model="form.reference" placeholder="Folio de factura o recibo (opcional)" maxlength="255" />
                </el-form-item>
            </div>

            <el-form-item label="Notas" prop="notes" :error="form.errors.notes">
                <el-input
                    v-model="form.notes"
                    type="textarea"
                    :rows="3"
                    maxlength="2000"
                    show-word-limit
                    placeholder="Información adicional del pago (opcional)"
                />
            </el-form-item>

            <el-form-item label="Comprobantes (opcional)" :error="form.errors.receipts">
                <ExpenseReceiptsField
                    :existing="concept?.expense?.receipts ?? []"
                    @change="onReceiptsChange"
                />
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="dialogVisible = false">Cancelar</el-button>
            <el-button type="primary" :loading="form.processing" @click="submit">
                {{ concept?.expense ? 'Guardar cambios' : 'Registrar pago' }}
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
