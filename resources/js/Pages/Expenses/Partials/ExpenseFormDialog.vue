<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { Setting } from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';
import ExpenseCategoriesManager from './ExpenseCategoriesManager.vue';
import ExpenseReceiptsField from './ExpenseReceiptsField.vue';

const props = defineProps({
    modelValue: Boolean,
    expense: { type: Object, default: null },
    categories: Array,
    /** When set, the expense is registered against this budget. */
    budget: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'saved', 'categories-changed']);

const { can } = usePermissions();

const isEditing = computed(() => !!props.expense);

// Budget expenses keep a budget link; concept payments also lock concept and amount.
const isBudgetExpense = computed(() => !!props.budget || !!props.expense?.budget_id);
const isConceptPayment = computed(() => !!props.expense?.budget_concept_id);

// Deposit expenses mirror the deposits module: amount, date, status and
// receipts are managed there (completed with the "Marcar realizado" action).
const isDepositExpense = computed(() => !!props.expense?.deposit_id);

const dialogTitle = computed(() => {
    if (isEditing.value) return 'Editar gasto';
    if (props.budget) return 'Registrar gasto del presupuesto';

    return 'Registrar gasto';
});

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
    expense_category_id: props.expense?.category_id ?? null,
    budget_id: props.budget?.id ?? props.expense?.budget_id ?? null,
    is_commission: !!props.expense?.is_commission,
    concept: props.expense?.concept ?? '',
    reference: props.expense?.reference ?? '',
    notes: props.expense?.notes ?? '',
    amount: props.expense?.amount ?? null,
    commission_amount: props.expense?.commission_amount || null,
    expense_date: props.expense?.expense_date ?? todayIsoDate(),
    payment_method: props.expense?.payment_method ?? '',
    status: props.expense?.status ?? 'pending',
    receipts: [],
    remove_receipt_ids: [],
});

// --- Categories manager ---
const showCategoriesManager = ref(false);

const openCategoriesManager = () => {
    showCategoriesManager.value = true;
};

const onCategoriesChanged = () => {
    emit('categories-changed');
};

// --- Receipts (several files per expense) ---
const onReceiptsChange = ({ files, remove_ids }) => {
    form.receipts = files;
    form.remove_receipt_ids = remove_ids;
};

const rules = computed(() => ({
    // Budget and deposit expenses do not require a category.
    expense_category_id: isBudgetExpense.value || isDepositExpense.value
        ? []
        : [{ required: true, message: 'Selecciona una categoría.', trigger: 'change' }],
    concept: [{ required: true, message: 'Escribe el concepto del gasto.', trigger: 'blur' }],
    amount: [{ required: true, message: 'Captura el monto del gasto.', trigger: 'blur' }],
    expense_date: [{ required: true, message: 'Selecciona la fecha del gasto.', trigger: 'change' }],
    status: [{ required: true, message: 'Selecciona el estatus del gasto.', trigger: 'change' }],
}));

// --- Submission ---
function submit() {
    formRef.value?.validate((valid) => {
        if (!valid) return;

        if (isEditing.value) {
            // PHP does not parse multipart bodies on real PUT requests, so the update is sent
            // as POST with method spoofing (_method=PUT). This keeps file uploads working.
            form.transform((data) => ({ ...data, _method: 'PUT' }))
                .post(route('expenses.update', props.expense.id), {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        ElMessage.success('Gasto actualizado correctamente.');
                        emit('update:modelValue', false);
                        emit('saved');
                    },
                });

            return;
        }

        form.post(route('expenses.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success('Gasto registrado correctamente.');
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
        :title="dialogTitle"
        width="680px"
        top="8vh"
        destroy-on-close
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
            <el-alert
                v-if="props.budget"
                type="info"
                :closable="false"
                show-icon
                class="mb-4"
                :title="`Presupuesto ${props.budget.folio} — ${props.budget.name}`"
            />

            <el-alert
                v-if="isDepositExpense"
                type="info"
                :closable="false"
                show-icon
                class="mb-4"
                title="Este gasto proviene de un depósito"
                description="El monto, la fecha, el estatus y el comprobante se administran desde depósitos. Para subir el comprobante y la comisión usa la acción Marcar realizado en la lista de gastos."
            />

            <el-form-item label="Concepto" prop="concept" :error="form.errors.concept">
                <el-input
                    v-model="form.concept"
                    placeholder="Ej. Renta de oficina de septiembre"
                    maxlength="255"
                    :disabled="isConceptPayment || isDepositExpense"
                />
            </el-form-item>
            <p v-if="isConceptPayment" class="-mt-2 mb-4 text-xs text-gray-500 dark:text-gray-400">
                Este gasto corresponde a un concepto del desglose del presupuesto; el concepto y el monto se administran desde el presupuesto.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 sm:gap-x-4">
                <el-form-item
                    v-if="!isConceptPayment"
                    label="Categoría"
                    prop="expense_category_id"
                    :error="form.errors.expense_category_id"
                    class="expense-category-item"
                >
                    <template #label>
                        <div class="flex items-center justify-between flex-1">
                            <span>Categoría</span>
                            <el-button
                                v-if="can('expenses.categories.manage')"
                                link
                                size="small"
                                :icon="Setting"
                                title="Gestionar categorías"
                                @click="openCategoriesManager"
                            />
                        </div>
                    </template>
                    <el-select
                        v-model="form.expense_category_id"
                        :placeholder="isBudgetExpense ? 'Opcional' : 'Selecciona una categoría'"
                        filterable
                        class="w-full"
                    >
                        <el-option
                            v-for="category in categories"
                            :key="category.id"
                            :label="category.name"
                            :value="category.id"
                        />
                    </el-select>
                </el-form-item>

                <el-form-item label="Monto" prop="amount" :error="form.errors.amount" class="expense-amount-item">
                    <el-input-number
                        v-model="form.amount"
                        :min="0.01"
                        :precision="2"
                        :controls="false"
                        placeholder="0.00"
                        class="w-full"
                        :disabled="isConceptPayment || isDepositExpense"
                    >
                        <template #prefix>
                            <span class="text-gray-500">$</span>
                        </template>
                    </el-input-number>
                </el-form-item>

                <el-form-item
                    label="Fecha del gasto"
                    prop="expense_date"
                    :error="form.errors.expense_date"
                    class="expense-date-item"
                >
                    <el-date-picker
                        v-model="form.expense_date"
                        type="date"
                        value-format="YYYY-MM-DD"
                        format="DD/MM/YYYY"
                        placeholder="Selecciona la fecha"
                        class="w-full"
                        :disabled="isDepositExpense"
                    />
                </el-form-item>

                <el-form-item label="Estatus" prop="status" :error="form.errors.status">
                    <el-select v-model="form.status" class="w-full" :disabled="isDepositExpense">
                        <el-option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :label="option.label"
                            :value="option.value"
                        />
                    </el-select>
                </el-form-item>

                <el-form-item label="Método de pago" prop="payment_method" :error="form.errors.payment_method">
                    <el-select v-model="form.payment_method" placeholder="Opcional" clearable class="w-full" :disabled="isDepositExpense">
                        <el-option
                            v-for="option in paymentMethodOptions"
                            :key="option.value"
                            :label="option.label"
                            :value="option.value"
                        />
                    </el-select>
                </el-form-item>

                <el-form-item
                    v-if="!form.is_commission"
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
                        :disabled="isDepositExpense"
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
                    placeholder="Información adicional del gasto (opcional)"
                />
            </el-form-item>

            <el-form-item v-if="!isDepositExpense" label="Comprobantes (opcional)" :error="form.errors.receipts">
                <ExpenseReceiptsField
                    :existing="props.expense?.receipts ?? []"
                    @change="onReceiptsChange"
                />
            </el-form-item>

            <el-form-item v-else label="Comprobante del depósito">
                <div class="w-full text-sm">
                    <div v-if="props.expense?.receipts?.length" class="space-y-1">
                        <a
                            v-for="receipt in props.expense.receipts"
                            :key="receipt.id"
                            :href="receipt.url"
                            target="_blank"
                            rel="noopener"
                            class="block text-blue-600 hover:underline dark:text-blue-400"
                        >
                            {{ receipt.name }}
                        </a>
                    </div>
                    <p v-else class="text-xs text-gray-400">
                        El comprobante aparecerá aquí cuando marques el depósito como realizado.
                    </p>
                </div>
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="dialogVisible = false">Cancelar</el-button>
            <el-button type="primary" :loading="form.processing" @click="submit">
                {{ isEditing ? 'Guardar cambios' : 'Registrar gasto' }}
            </el-button>
        </template>
    </el-dialog>

    <!-- Categories manager -->
    <ExpenseCategoriesManager
        v-if="showCategoriesManager"
        v-model="showCategoriesManager"
        @changed="onCategoriesChanged"
    />
</template>

<style scoped>
/* Keep the required asterisk, the label text and the settings button on the same line */
:deep(.expense-category-item > .el-form-item__label) {
    width: 100%;
    display: flex;
    align-items: center;
}

/* Amount, commission and date inputs: same width as the rest of the inputs */
:deep(.expense-amount-item .el-input-number),
:deep(.expense-commission-item .el-input-number),
:deep(.expense-date-item .el-date-editor) {
    width: 100%;
}
</style>
