<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Wallet } from '@element-plus/icons-vue';

const props = defineProps({
    budget: Object,
});

const emit = defineEmits(['preview']);

const showPaymentModal = ref(false);
const paymentUploadRef = ref(null);

const paymentForm = useForm({
    amount: 0,
    payment_date: new Date().toISOString().split('T')[0],
    payment_method: 'Transferencia',
    reference: '',
    proof: null,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: props.budget.currency || 'MXN',
    }).format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('es-MX', {
        year: 'numeric', month: 'long', day: 'numeric',
    });
};

const openPaymentModal = () => {
    paymentForm.reset();
    paymentForm.amount = props.budget.balance_due > 0 ? props.budget.balance_due : 0;
    if (paymentUploadRef.value) paymentUploadRef.value.clearFiles();
    showPaymentModal.value = true;
};

const handlePaymentProofChange = (file) => {
    paymentForm.proof = file.raw;
};

const submitPayment = () => {
    paymentForm.post(route('budgets.payments.store', props.budget.id), {
        onSuccess: () => {
            showPaymentModal.value = false;
            paymentForm.reset();
            ElMessage.success('Pago registrado correctamente');
        },
        onError: () => ElMessage.error('Error al registrar el pago'),
        forceFormData: true,
    });
};

const deletePayment = (paymentId) => {
    ElMessageBox.confirm('¿Eliminar este pago? Se recalculará el saldo.', 'Confirmar', {
        type: 'warning',
    }).then(() => {
        router.delete(route('budgets.payments.destroy', paymentId), {
            onSuccess: () => ElMessage.success('Pago eliminado'),
        });
    }).catch(() => {});
};

const openPreview = (file) => {
    emit('preview', file);
};
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
        <h3 class="font-bold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2">
            <el-icon><Wallet /></el-icon> Estado de cuenta
        </h3>

        <div class="space-y-4 mb-6">
            <div class="flex justify-between items-center pb-2 border-b border-gray-50 dark:border-gray-800">
                <span class="text-xs text-gray-500">Moneda</span>
                <div class="text-right">
                    <span class="font-bold text-gray-800 dark:text-white text-sm block">{{ budget.currency }}</span>
                    <span v-if="budget.currency === 'USD'" class="text-[10px] text-gray-400 block">
                        TC: ${{ budget.exchange_rate }}
                    </span>
                </div>
            </div>

            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-500">Total proyecto</span>
                <span class="font-bold text-gray-800 dark:text-white">{{ formatCurrency(budget.total_cost) }}</span>
            </div>
            <div class="flex justify-between items-center text-green-600">
                <span class="text-sm">Pagado</span>
                <span class="font-bold">- {{ formatCurrency(budget.total_paid) }}</span>
            </div>
            <div class="border-t border-gray-200 dark:border-gray-700 pt-2 flex justify-between items-center">
                <span class="text-sm font-bold text-gray-800 dark:text-white">Saldo pendiente</span>
                <span class="font-bold text-lg" :class="budget.balance_due > 0 ? 'text-red-500' : 'text-green-500'">
                    {{ formatCurrency(budget.balance_due) }}
                </span>
            </div>

            <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700 mt-2">
                <div
                    class="bg-green-500 h-2.5 rounded-full transition-all duration-500"
                    :style="{ width: Math.min((budget.total_paid / budget.total_cost) * 100, 100) + '%' }"
                ></div>
            </div>
        </div>

        <el-divider content-position="center">Pagos</el-divider>

        <el-button type="success" class="w-full mb-4" @click="openPaymentModal" :disabled="budget.balance_due <= 0">
            Registrar pago
        </el-button>

        <div class="space-y-3 max-h-80 overflow-y-auto">
            <div v-for="payment in budget.payments" :key="payment.id" class="p-3 bg-gray-50 dark:bg-[#252529] rounded border border-gray-100 dark:border-[#3f3f46] text-sm group">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="font-bold text-gray-800 dark:text-gray-200">{{ formatCurrency(payment.amount) }}</p>
                        <p class="text-xs text-gray-400">{{ formatDate(payment.payment_date) }} • {{ payment.payment_method }}</p>
                    </div>
                    <div class="flex gap-1">
                        <el-tooltip v-if="payment.media && payment.media.length > 0" content="Ver comprobante" placement="top">
                            <el-button circle size="small" type="primary" plain icon="Picture" @click="openPreview(payment.media[0])" />
                        </el-tooltip>
                        <el-button
                            type="danger" icon="Delete" circle size="small" plain
                            class="opacity-0 group-hover:opacity-100 transition-opacity"
                            @click="deletePayment(payment.id)"
                        />
                    </div>
                </div>
                <p v-if="payment.reference" class="text-xs text-gray-500 mt-1 italic">Ref: {{ payment.reference }}</p>
            </div>
            <p v-if="budget.payments.length === 0" class="text-center text-xs text-gray-400">No hay pagos registrados.</p>
        </div>
    </div>

    <!-- Modal pago -->
    <el-dialog v-model="showPaymentModal" title="Registrar nuevo pago" width="450px">
        <el-form :model="paymentForm" label-position="top">
            <el-form-item label="Monto">
                <el-input-number v-model="paymentForm.amount" :min="0.00" :max="budget.balance_due" :precision="2" class="!w-full">
                    <template #prefix>$</template>
                </el-input-number>
            </el-form-item>
            <div class="grid grid-cols-2 gap-4">
                <el-form-item label="Fecha">
                    <el-date-picker v-model="paymentForm.payment_date" type="date" class="!w-full" format="DD/MM/YYYY" value-format="YYYY-MM-DD" />
                </el-form-item>
                <el-form-item label="Método">
                    <el-select v-model="paymentForm.payment_method" class="w-full">
                        <el-option label="Transferencia" value="Transferencia" />
                        <el-option label="Efectivo" value="Efectivo" />
                        <el-option label="Cheque" value="Cheque" />
                        <el-option label="Tarjeta" value="Tarjeta" />
                    </el-select>
                </el-form-item>
            </div>
            <el-form-item label="Referencia / Nota">
                <el-input v-model="paymentForm.reference" placeholder="Ej. Folio bancario" />
            </el-form-item>
            <el-form-item label="Comprobante (opcional)">
                <el-upload
                    ref="paymentUploadRef"
                    class="w-full"
                    :auto-upload="false"
                    :limit="1"
                    :on-change="handlePaymentProofChange"
                    accept="image/*,.pdf"
                >
                    <template #trigger>
                        <el-button icon="Upload">Adjuntar archivo</el-button>
                    </template>
                </el-upload>
            </el-form-item>
        </el-form>
        <template #footer>
            <span class="dialog-footer">
                <el-button @click="showPaymentModal = false">Cancelar</el-button>
                <el-button type="primary" color="#f26c17" @click="submitPayment" :loading="paymentForm.processing">
                    Guardar pago
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>
