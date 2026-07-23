<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Money, ZoomIn, Delete, Coin, Timer } from '@element-plus/icons-vue';
import RequestDepositModal from '@/Components/Deposits/RequestDepositModal.vue';
import axios from 'axios';

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

const formatDateTime = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('es-ES', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: true,
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

// --- DEPOSITOS SOLICITADOS (solo no completados) ---
const ticketDeposits = computed(() => {
    return (props.ticket.deposits || []).filter(d => d.status !== 'completed');
});

// --- DEPOSIT REQUEST MODAL ---
const showDepositModal = ref(false);
const selectedTechForDeposit = ref(null);
const submittingDeposit = ref(false);

const depositTicketInfo = computed(() => ({
    id: props.ticket.id,
    folio: props.ticket.folio ?? `#${props.ticket.id}`,
    name: props.ticket.name,
    customer_name: props.ticket.customer?.name ?? '',
}));

const openDepositModal = (tech) => {
    selectedTechForDeposit.value = {
        id: tech.id,
        name: tech.name,
        technician: {
            id: tech.technician_id,
            is_internal: tech.is_internal,
            state: tech.state,
        },
    };
    showDepositModal.value = true;
};

// Same handler used by RequestDepositModal @saved
const handleDepositSaved = async (deposit) => {
    if (deposit) {
        ElMessage.success('Depósito programado correctamente.');
    }
    showDepositModal.value = false;
    router.reload({ only: ['ticket'], preserveScroll: true, preserveState: true });
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

// --- DEPOSIT DELETE (usando axios para no redirigir) ---
const deleteDeposit = async (depositId) => {
    try {
        await ElMessageBox.confirm('¿Eliminar este depósito solicitado?', 'Confirmar', {
            type: 'warning',
        });

        const { data } = await axios.delete(route('deposits.destroy', depositId), {
            headers: { 'Accept': 'application/json' },
        });

        ElMessage.success(data.message || 'Depósito eliminado');

        // Remover localmente sin recargar la página
        const idx = props.ticket.deposits.findIndex(d => d.id === depositId);
        if (idx !== -1) {
            props.ticket.deposits.splice(idx, 1);
        }
    } catch (err) {
        if (err !== 'cancel') {
            ElMessage.error(err.response?.data?.message || 'Error al eliminar el depósito.');
        }
    }
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

const showDepositVoucher = (deposit) => {
    // Deposit model has a single-file 'voucher' media collection
    if (deposit.media && deposit.media.length > 0) {
        proofPreviewUrl.value = deposit.media[0].original_url;
        proofPreviewVisible.value = true;
    } else {
        ElMessage.info('Este depósito no tiene comprobante.');
    }
};

// Helper to check if a deposit has voucher media
const depositHasVoucher = (deposit) => {
    return deposit.media && deposit.media.length > 0;
};

const getStatusType = (status) => {
    const map = {
        pending: 'warning',
        approved: 'success',
        completed: 'success',
        cancelled: 'danger',
    };
    return map[status] || 'info';
};

const getStatusLabel = (status) => {
    const map = {
        pending: 'Pendiente',
        approved: 'Aprobado',
        completed: 'Completado',
        cancelled: 'Cancelado',
    };
    return map[status] || status;
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
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ tech.name }}
                        <template v-if="tech.state"> — {{ tech.state }}</template>
                    </span>
                </div>
                <el-button type="warning" size="small" plain :icon="Coin" @click="openDepositModal(tech)">
                    Solicitar depósito
                </el-button>
            </div>

            <!-- Depósitos solicitados -->
            <div v-if="ticketDeposits.length > 0" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                <p class="text-[11px] font-bold text-gray-400 uppercase mb-2 flex items-center gap-1">
                    <el-icon><Timer /></el-icon> Depósitos solicitados
                </p>
                <div v-for="dep in ticketDeposits" :key="dep.id" class="flex items-center justify-between py-1.5 text-sm border-b border-gray-100 dark:border-gray-800 last:border-0">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-orange-600">{{ formatCurrency(dep.amount) }}</span>
                            <el-tag :type="getStatusType(dep.status)" size="small" effect="dark">
                                {{ getStatusLabel(dep.status) }}
                            </el-tag>
                            <span class="text-xs text-gray-400">{{ formatDateTime(dep.created_at) }}</span>
                        </div>
                        <div v-if="dep.technician?.user" class="text-xs text-gray-500 mt-0.5">
                            {{ dep.technician.user.name }}
                            <template v-if="dep.deposit_type"> — {{ dep.deposit_type.name }}</template>
                        </div>
                        <p v-if="dep.notes" class="text-xs text-gray-500 mt-0.5 truncate" :title="dep.notes">{{ dep.notes }}</p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <el-tooltip v-if="depositHasVoucher(dep)" content="Ver comprobante">
                            <el-button circle size="small" type="info" plain icon="ZoomIn" @click="showDepositVoucher(dep)" />
                        </el-tooltip>
                        <el-tooltip v-if="dep.status === 'pending'" content="Eliminar depósito">
                            <el-button circle size="small" type="danger" plain icon="Delete" @click="deleteDeposit(dep.id)" />
                        </el-tooltip>
                    </div>
                </div>
            </div>

            <!-- Historial de pagos registrados -->
            <div v-if="ticket.budget?.technician_payments?.length" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                <p class="text-[11px] font-bold text-gray-400 uppercase mb-2">Historial de pagos</p>
                <div v-for="pay in ticket.budget.technician_payments" :key="pay.id" class="flex items-center justify-between py-1.5 text-sm border-b border-gray-100 dark:border-gray-800 last:border-0">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-green-600">{{ formatCurrency(pay.amount) }}</span>
                            <span class="text-xs text-gray-400">{{ formatDateTime(pay.created_at) }}</span>
                            <el-tag v-if="pay.reference" size="small" type="info" class="scale-75">{{ pay.reference }}</el-tag>
                        </div>
                        <p v-if="pay.notes" class="text-xs text-gray-500 mt-0.5 truncate" :title="pay.notes">{{ pay.notes }}</p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <!-- Comprobante del pago (TechnicianPayment media) -->
                        <el-tooltip v-if="pay.media?.length" content="Ver comprobante">
                            <el-button circle size="small" type="info" plain icon="ZoomIn" @click="showPaymentProof(pay)" />
                        </el-tooltip>
                        <!-- Comprobante del depósito asociado (Deposit voucher) -->
                        <el-tooltip v-if="pay.deposit?.media?.length" content="Ver comprobante de depósito">
                            <el-button circle size="small" type="warning" plain icon="Coin" @click="showDepositVoucher(pay.deposit)" />
                        </el-tooltip>
                        <el-button circle size="small" type="danger" plain icon="Delete" @click="deleteTechPayment(pay.id)" />
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL SOLICITAR DEPÓSITO -->
        <RequestDepositModal
            v-if="selectedTechForDeposit"
            v-model="showDepositModal"
            :technician="selectedTechForDeposit"
            :ticket="depositTicketInfo"
            @saved="handleDepositSaved"
        />

        <!-- VISOR DE COMPROBANTE -->
        <el-image-viewer v-if="proofPreviewVisible" :url-list="[proofPreviewUrl]" @close="proofPreviewVisible = false" />
    </div>
</template>
