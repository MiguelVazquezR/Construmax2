<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Money, ZoomIn, Delete } from '@element-plus/icons-vue';

const props = defineProps({
    ticket: { type: Object, required: true },
    assignedTechnicians: { type: Array, default: () => [] },
});

// --- HELPERS ---
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: props.ticket.budget?.currency || 'MXN',
    }).format(value || 0);
};

const formatDateLong = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('es-ES', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: true
    });
};

// --- COMPUTED ---
const totalTechnicianAmount = computed(() => {
    if (!props.ticket.budget?.concepts) return 0;
    return props.ticket.budget.concepts
        .filter(c => c.paid_to_technician)
        .reduce((sum, c) => sum + parseFloat(c.amount), 0);
});

const totalTechnicianPaid = computed(() => {
    if (!props.ticket.budget?.technician_payments) return 0;
    return props.ticket.budget.technician_payments.reduce((sum, p) => sum + parseFloat(p.amount), 0);
});

const technicianPaymentProgress = computed(() => {
    if (totalTechnicianAmount.value <= 0) return 100;
    return Math.min(Math.round((totalTechnicianPaid.value / totalTechnicianAmount.value) * 100), 100);
});

// --- PAYMENT MODAL ---
const showPaymentModal = ref(false);
const paymentUploadRef = ref(null);
const selectedTechForPayment = ref(null);

const paymentForm = useForm({
    user_id: null,
    amount: 0,
    payment_date: new Date().toISOString().split('T')[0],
    payment_method: 'Transferencia',
    reference: '',
    notes: '',
    proof: null,
});

const openPaymentModal = (tech) => {
    selectedTechForPayment.value = tech;
    paymentForm.reset();
    paymentForm.user_id = tech.id;
    if (paymentUploadRef.value) paymentUploadRef.value.clearFiles();
    showPaymentModal.value = true;
};

const handlePaymentProofChange = (file) => {
    paymentForm.proof = file.raw;
};

const submitPayment = () => {
    paymentForm.post(route('budgets.technician-payments.store', props.ticket.budget?.id), {
        onSuccess: () => {
            showPaymentModal.value = false;
            paymentForm.reset();
            ElMessage.success('Pago a técnico registrado');
        },
        onError: () => ElMessage.error('Error al registrar pago'),
        forceFormData: true,
    });
};

const deleteTechPayment = (paymentId) => {
    ElMessageBox.confirm('¿Eliminar este registro de pago?', 'Confirmar', {
        type: 'warning',
    }).then(() => {
        router.delete(route('budgets.technician-payments.destroy', paymentId), {
            onSuccess: () => ElMessage.success('Pago eliminado'),
        });
    }).catch(() => {});
};

// --- PROOF VIEWER ---
const proofPreviewVisible = ref(false);
const proofPreviewUrl = ref('');
const showPaymentProof = (pay) => {
    if (pay.media && pay.media.length > 0) {
        proofPreviewUrl.value = pay.media[0].original_url;
        proofPreviewVisible.value = true;
    }
};
</script>

<template>
    <div v-if="totalTechnicianAmount > 0" class="mb-8">
        <div class="bg-gray-50 dark:bg-[#252529] rounded-lg p-4 border border-gray-200 dark:border-[#3f3f46]">
            <div class="flex justify-between items-center mb-3">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                    <el-icon><Money /></el-icon> Pago a técnico
                </h4>
                <span class="text-xs font-bold">{{ formatCurrency(totalTechnicianPaid) }} / {{ formatCurrency(totalTechnicianAmount) }}</span>
            </div>
            <el-progress
                :percentage="technicianPaymentProgress"
                :status="technicianPaymentProgress >= 100 ? 'success' : ''"
                :stroke-width="14"
                class="mb-4"
            >
                <span class="text-xs font-bold">{{ technicianPaymentProgress }}%</span>
            </el-progress>

            <!-- Técnicos con pagos -->
            <div v-for="tech in assignedTechnicians" :key="tech.id" class="flex items-center justify-between p-3 bg-white dark:bg-[#1e1e20] rounded-lg border border-gray-200 dark:border-gray-700 mb-2 last:mb-0">
                <div class="flex items-center gap-2">
                    <el-avatar :size="28" :src="tech.profile_photo_url">{{ tech.name.charAt(0) }}</el-avatar>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ tech.name }}</span>
                </div>
                <el-button type="success" size="small" plain @click="openPaymentModal(tech)">
                    Pagar
                </el-button>
            </div>

            <!-- Historial de pagos -->
            <div v-if="ticket.budget?.technician_payments?.length" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                <p class="text-[11px] font-bold text-gray-400 uppercase mb-2">Historial de pagos</p>
                <div v-for="pay in ticket.budget.technician_payments" :key="pay.id" class="flex items-center justify-between py-1.5 text-sm border-b border-gray-100 dark:border-gray-800 last:border-0">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-green-600">{{ formatCurrency(pay.amount) }}</span>
                            <span class="text-xs text-gray-400">{{ formatDateLong(pay.payment_date) }}</span>
                            <el-tag v-if="pay.reference" size="small" type="info" class="scale-75">{{ pay.reference }}</el-tag>
                        </div>
                        <p v-if="pay.notes" class="text-xs text-gray-500 mt-0.5 truncate" :title="pay.notes">{{ pay.notes }}</p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <el-tooltip v-if="pay.media?.length" content="Ver comprobante">
                            <el-button circle size="small" type="info" plain icon="ZoomIn" @click="showPaymentProof(pay)" />
                        </el-tooltip>
                        <el-button circle size="small" type="danger" plain icon="Delete" @click="deleteTechPayment(pay.id)" />
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL PAGO A TÉCNICO -->
        <el-dialog
            v-model="showPaymentModal"
            :title="`Pago a técnico: ${selectedTechForPayment?.name || ''}`"
            width="450px"
            append-to-body
        >
            <el-form :model="paymentForm" label-position="top">
                <el-alert
                    title="Importante"
                    type="info"
                    description="El comprobante es obligatorio para los pagos a personal técnico."
                    show-icon
                    :closable="false"
                    class="mb-4"
                />

                <el-form-item label="Monto a pagar">
                    <el-input-number v-model="paymentForm.amount" :min="0.01" :precision="2" class="!w-full">
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
                            <el-option label="Nómina" value="Nómina" />
                        </el-select>
                    </el-form-item>
                </div>

                <el-form-item label="Referencia bancaria">
                    <el-input v-model="paymentForm.reference" placeholder="Ej. SPEI-123456" />
                </el-form-item>

                <el-form-item label="Comprobante de pago (Obligatorio)" :error="paymentForm.errors.proof">
                    <el-upload
                        ref="paymentUploadRef"
                        class="w-full"
                        :auto-upload="false"
                        :limit="1"
                        :on-change="handlePaymentProofChange"
                        accept="image/*,.pdf"
                    >
                        <template #trigger>
                            <el-button type="primary" plain icon="Upload">Adjuntar comprobante</el-button>
                        </template>
                        <template #tip>
                            <div class="el-upload__tip">Archivos PDF o Imagen (Máx. 5MB)</div>
                        </template>
                    </el-upload>
                </el-form-item>
            </el-form>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="showPaymentModal = false">Cancelar</el-button>
                    <el-button type="success" @click="submitPayment" :loading="paymentForm.processing">
                        Registrar pago
                    </el-button>
                </span>
            </template>
        </el-dialog>

        <!-- VISOR DE COMPROBANTE DE PAGO -->
        <el-image-viewer v-if="proofPreviewVisible" :url-list="[proofPreviewUrl]" @close="proofPreviewVisible = false" />
    </div>
</template>
