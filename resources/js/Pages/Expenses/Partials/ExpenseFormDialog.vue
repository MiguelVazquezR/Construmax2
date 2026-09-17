<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { Setting, UploadFilled } from '@element-plus/icons-vue';
import { debounce } from 'lodash';
import axios from 'axios';
import { usePermissions } from '@/Composables/usePermissions';
import ExpenseCategoriesManager from './ExpenseCategoriesManager.vue';

const props = defineProps({
    modelValue: Boolean,
    expense: { type: Object, default: null },
    categories: Array,
});

const emit = defineEmits(['update:modelValue', 'saved', 'categories-changed']);

const { can } = usePermissions();

const isEditing = computed(() => !!props.expense);

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
    ticket_id: props.expense?.ticket_id ?? null,
    concept: props.expense?.concept ?? '',
    reference: props.expense?.reference ?? '',
    notes: props.expense?.notes ?? '',
    amount: props.expense?.amount ?? null,
    expense_date: props.expense?.expense_date ?? todayIsoDate(),
    payment_method: props.expense?.payment_method ?? '',
    status: props.expense?.status ?? 'pending',
});

// --- Categories manager ---
const showCategoriesManager = ref(false);

const openCategoriesManager = () => {
    showCategoriesManager.value = true;
};

const onCategoriesChanged = () => {
    emit('categories-changed');
};

// --- Receipt ---
const receiptFile = ref(null);
const removeReceipt = ref(false);
const hasExistingReceipt = computed(() => !!props.expense?.receipt_url);

const onReceiptChange = (file) => {
    if (file.raw && file.raw.size > 10 * 1024 * 1024) {
        ElMessage.warning('El comprobante no debe exceder 10 MB.');
        return;
    }

    receiptFile.value = file.raw;
};

const clearReceiptSelection = () => {
    receiptFile.value = null;
};

const rules = {
    expense_category_id: [{ required: true, message: 'Selecciona una categoría.', trigger: 'change' }],
    concept: [{ required: true, message: 'Escribe el concepto del gasto.', trigger: 'blur' }],
    amount: [{ required: true, message: 'Captura el monto del gasto.', trigger: 'blur' }],
    expense_date: [{ required: true, message: 'Selecciona la fecha del gasto.', trigger: 'change' }],
    status: [{ required: true, message: 'Selecciona el estatus del gasto.', trigger: 'change' }],
};

// --- Optional ticket link (remote search) ---
const searchResults = ref([]);
const ticketsLoading = ref(false);
const selectedTicket = ref(
    props.expense?.ticket_id
        ? {
            id: props.expense.ticket_id,
            folio: props.expense.ticket_folio,
            name: props.expense.ticket_name,
            customer_name: null,
        }
        : null
);

const ticketOptions = computed(() => {
    const options = [...searchResults.value];

    if (
        form.ticket_id
        && selectedTicket.value?.id === form.ticket_id
        && !options.some((option) => option.id === form.ticket_id)
    ) {
        options.unshift(selectedTicket.value);
    }

    return options;
});

watch(() => form.ticket_id, (value) => {
    if (!value) {
        selectedTicket.value = null;
        return;
    }

    const match = searchResults.value.find((option) => option.id === value);

    if (match) {
        selectedTicket.value = match;
    }
});

const searchTickets = debounce(async (query) => {
    ticketsLoading.value = true;

    try {
        const { data } = await axios.get(route('expenses.tickets.search'), {
            params: { q: query },
        });

        searchResults.value = data;
    } catch {
        searchResults.value = [];
    } finally {
        ticketsLoading.value = false;
    }
}, 300);

onMounted(() => {
    searchTickets('');
});

const ticketLabel = (option) => {
    const label = [option.folio, option.name].filter(Boolean).join(' — ');

    return option.customer_name ? `${label} (${option.customer_name})` : label;
};

// --- Submission ---
function submit() {
    formRef.value?.validate((valid) => {
        if (!valid) return;

        const withReceipt = (data) => ({
            ...data,
            ...(receiptFile.value ? { receipt: receiptFile.value } : {}),
            ...(removeReceipt.value ? { remove_receipt: true } : {}),
        });

        if (isEditing.value) {
            // PHP does not parse multipart bodies on real PUT requests, so the update is sent
            // as POST with method spoofing (_method=PUT). This keeps file uploads working.
            form.transform((data) => ({ ...withReceipt(data), _method: 'PUT' }))
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

        form.transform(withReceipt).post(route('expenses.store'), {
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
        :title="isEditing ? 'Editar gasto' : 'Registrar gasto'"
        width="680px"
        destroy-on-close
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
            <el-form-item label="Concepto" prop="concept" :error="form.errors.concept">
                <el-input
                    v-model="form.concept"
                    placeholder="Ej. Renta de oficina de septiembre"
                    maxlength="255"
                />
            </el-form-item>

            <div class="grid grid-cols-1 sm:grid-cols-2 sm:gap-x-4">
                <el-form-item
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
                        placeholder="Selecciona una categoría"
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
                    >
                        <template #prefix>
                            <span class="text-gray-500">$</span>
                        </template>
                    </el-input-number>
                </el-form-item>

                <el-form-item label="Fecha del gasto" prop="expense_date" :error="form.errors.expense_date">
                    <el-date-picker
                        v-model="form.expense_date"
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

                <el-form-item label="Referencia" prop="reference" :error="form.errors.reference">
                    <el-input v-model="form.reference" placeholder="Folio de factura o recibo (opcional)" maxlength="255" />
                </el-form-item>
            </div>

            <el-form-item label="Ticket (opcional)" prop="ticket_id" :error="form.errors.ticket_id">
                <el-select
                    v-model="form.ticket_id"
                    placeholder="Busca por folio, proyecto o cliente"
                    remote
                    reserve-keyword
                    filterable
                    clearable
                    :remote-method="searchTickets"
                    :loading="ticketsLoading"
                    class="w-full"
                >
                    <el-option
                        v-for="ticket in ticketOptions"
                        :key="ticket.id"
                        :label="ticketLabel(ticket)"
                        :value="ticket.id"
                    />
                </el-select>
                <p class="text-xs text-gray-400 mt-1">
                    Úsalo para gastos ligados a un proyecto. Déjalo vacío para gastos generales.
                </p>
            </el-form-item>

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

            <el-form-item label="Comprobante del gasto (opcional)" :error="form.errors.receipt">
                <div class="w-full">
                    <div v-if="hasExistingReceipt && !removeReceipt" class="flex items-center gap-2 text-sm">
                        <a
                            :href="props.expense.receipt_url"
                            target="_blank"
                            rel="noopener"
                            class="text-blue-600 hover:underline dark:text-blue-400"
                        >
                            {{ props.expense.receipt_name || 'Ver comprobante actual' }}
                        </a>
                        <el-button link type="danger" size="small" @click="removeReceipt = true">
                            Quitar
                        </el-button>
                    </div>

                    <div v-else-if="removeReceipt" class="text-sm text-amber-600">
                        El comprobante actual se eliminará al guardar.
                        <el-button link size="small" @click="removeReceipt = false">Deshacer</el-button>
                    </div>

                    <el-upload
                        :auto-upload="false"
                        :limit="1"
                        accept=".jpg,.jpeg,.png,.webp,.pdf"
                        :on-change="onReceiptChange"
                        :on-remove="clearReceiptSelection"
                        :on-exceed="() => ElMessage.warning('Solo se puede adjuntar un comprobante.')"
                    >
                        <el-button size="small" :icon="UploadFilled">Adjuntar archivo</el-button>
                        <template #tip>
                            <div class="el-upload__tip">
                                JPG, PNG, WEBP o PDF. Máx. 10 MB.
                                <span v-if="hasExistingReceipt"> Al subir un archivo nuevo se reemplazará el actual.</span>
                            </div>
                        </template>
                    </el-upload>
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

/* Amount input: same width as the rest of the inputs */
:deep(.expense-amount-item .el-input-number) {
    width: 100%;
}
</style>
