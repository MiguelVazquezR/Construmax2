<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Avatar, Coin, Timer, ZoomIn, Delete, Money } from '@element-plus/icons-vue';
import RequestDepositModal from '@/Components/Deposits/RequestDepositModal.vue';
import axios from 'axios';

const props = defineProps({
    budget: Object,
});

const emit = defineEmits(['preview']);

// --- DEPOSIT REQUEST MODAL ---
const showDepositModal = ref(false);
const selectedTechForDeposit = ref(null);

const depositTicketInfo = computed(() => {
    const ticket = props.budget?.ticket;
    return {
        id: ticket?.id,
        folio: ticket?.folio ?? `#${ticket?.id}`,
        name: ticket?.name,
        customer_name: ticket?.customer?.name ?? '',
    };
});

const openDepositModal = (tech) => {
    selectedTechForDeposit.value = {
        id: tech.user.id,
        name: tech.user.name,
        technician: {
            id: tech.user.technician?.id,
            is_internal: tech.user.technician?.is_internal,
            state: tech.user.technician?.state,
        },
    };
    showDepositModal.value = true;
};

const handleDepositSaved = async () => {
    ElMessage.success('Depósito programado correctamente.');
    showDepositModal.value = false;
    router.reload({ only: ['budget'], preserveScroll: true, preserveState: true });
};

// --- HELPERS ---
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

const formatDateTime = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('es-ES', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: true,
    });
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

const depositHasVoucher = (deposit) => {
    return deposit.media && deposit.media.length > 0;
};

// --- MONTOS ---
const totalTechnicianAmount = computed(() => {
    if (!props.budget.concepts) return 0;
    return props.budget.concepts
        .filter(c => c.paid_to_technician)
        .reduce((sum, c) => sum + parseFloat(c.amount), 0);
});

// --- TÉCNICOS DATA ---
const techniciansData = computed(() => {
    const techs = {};

    // 1. Procesar tareas del ticket (solo técnicos externos)
    if (props.budget.ticket?.tasks) {
        props.budget.ticket.tasks.forEach(task => {
            if (task.assignee) {
                // Saltar técnicos internos
                if (task.assignee.technician?.is_internal) return;

                const userId = task.user_id;
                if (!techs[userId]) {
                    techs[userId] = {
                        user: task.assignee,
                        total_tasks: 0,
                        completed_tasks: 0,
                        payments: [],
                        total_paid: 0,
                    };
                }
                techs[userId].total_tasks++;
                if (task.status === 'Completada') {
                    techs[userId].completed_tasks++;
                }
            }
        });
    }

    // 2. Procesar pagos a técnicos (solo externos)
    if (props.budget.technician_payments) {
        props.budget.technician_payments.forEach(payment => {
            const userId = payment.user_id;

            // Saltar técnicos internos (payment.technician es User, User.technician es Technician)
            if (payment.technician?.technician?.is_internal) return;

            if (!techs[userId]) {
                techs[userId] = {
                    user: payment.technician,
                    total_tasks: 0,
                    completed_tasks: 0,
                    payments: [],
                    total_paid: 0,
                };
            }
            techs[userId].payments.push(payment);
            techs[userId].total_paid += parseFloat(payment.amount);
        });
    }

    // 3. Asignar depósitos (no completados) a cada técnico
    const allDeposits = (props.budget.ticket?.deposits || []).filter(d => d.status !== 'completed');

    return Object.values(techs).map(tech => {
        const technicianId = tech.user.technician?.id;

        const techDeposits = technicianId
            ? allDeposits.filter(d => d.technician_id === technicianId)
            : [];

        return {
            ...tech,
            deposits: techDeposits,
            amount_to_pay: totalTechnicianAmount.value,
            payment_progress: totalTechnicianAmount.value > 0
                ? Math.min(Math.round((tech.total_paid / totalTechnicianAmount.value) * 100), 100)
                : 0,
        };
    });
});

// --- ACTIONS ---
const deleteTechPayment = (paymentId) => {
    ElMessageBox.confirm('¿Eliminar este registro de pago a técnico?', 'Confirmar', {
        type: 'warning',
    }).then(() => {
        router.delete(route('budgets.technician-payments.destroy', paymentId), {
            onSuccess: () => ElMessage.success('Pago eliminado'),
        });
    }).catch(() => {});
};

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
        const deposits = props.budget.ticket?.deposits;
        if (deposits) {
            const idx = deposits.findIndex(d => d.id === depositId);
            if (idx !== -1) {
                deposits.splice(idx, 1);
            }
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
    if (deposit.media && deposit.media.length > 0) {
        proofPreviewUrl.value = deposit.media[0].original_url;
        proofPreviewVisible.value = true;
    } else {
        ElMessage.info('Este depósito no tiene comprobante.');
    }
};

const openPreview = (file) => {
    emit('preview', file);
};
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
        <div class="p-4 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#2b2b2e] flex flex-col sm:flex-row justify-between items-center gap-2">
            <h3 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                <el-icon><Avatar /></el-icon> Gestión de técnicos y pagos
            </h3>
            <span class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded">Basado en tareas del Ticket {{ budget.ticket?.folio || 'N/A' }}</span>
        </div>

        <div v-if="techniciansData.length > 0" class="p-4 space-y-6">
            <div v-for="tech in techniciansData" :key="tech.user.id" class="border border-gray-100 dark:border-[#3f3f46] rounded-lg p-4 hover:shadow-sm transition-shadow">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-3">
                    <div class="flex items-center gap-3">
                        <el-avatar :src="tech.user.profile_photo_url" :size="40">{{ tech.user.name.charAt(0) }}</el-avatar>
                        <div>
                            <p class="font-bold text-gray-800 dark:text-white">
                                {{ tech.user.name }}
                                <el-tag
                                    v-if="tech.user.technician?.is_internal !== undefined"
                                    :type="tech.user.technician?.is_internal ? 'success' : 'warning'"
                                    size="small"
                                    effect="plain"
                                    class="ml-1"
                                >
                                    {{ tech.user.technician?.is_internal ? 'Interno' : 'Externo' }}
                                </el-tag>
                                <span v-if="tech.user.technician?.state" class="text-gray-400 text-xs ml-1">
                                    — {{ tech.user.technician.state }}
                                </span>
                            </p>
                            <p class="text-xs text-gray-500">{{ tech.completed_tasks }} / {{ tech.total_tasks }} tareas completadas</p>
                        </div>
                    </div>

                    <div class="w-full md:w-1/3 px-2">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-gray-400">Pago</span>
                            <span class="font-bold text-gray-700 dark:text-gray-300">{{ formatCurrency(tech.total_paid) }} / {{ formatCurrency(tech.amount_to_pay) }}</span>
                        </div>
                        <el-progress :percentage="tech.payment_progress" :show-text="false" :status="tech.payment_progress >= 100 ? 'success' : ''" />
                    </div>

                    <div class="flex items-center gap-3 w-full md:w-auto justify-between md:justify-end">
                        <div class="text-right">
                            <p class="text-xs text-gray-400">Total pagado</p>
                            <p class="font-bold text-green-600">{{ formatCurrency(tech.total_paid) }}</p>
                        </div>
                        <el-button type="warning" size="small" plain :icon="Coin" @click="openDepositModal(tech)">
                            Solicitar depósito
                        </el-button>
                    </div>
                </div>

                <!-- Depósitos solicitados -->
                <div v-if="tech.deposits.length > 0" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-[11px] font-bold text-gray-400 uppercase mb-2 flex items-center gap-1">
                        <el-icon><Timer /></el-icon> Depósitos solicitados
                    </p>
                    <div v-for="dep in tech.deposits" :key="dep.id" class="flex items-center justify-between py-1.5 text-sm border-b border-gray-100 dark:border-gray-800 last:border-0">
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
                                <el-button circle size="small" type="info" plain :icon="ZoomIn" @click="showDepositVoucher(dep)" />
                            </el-tooltip>
                            <el-tooltip v-if="dep.status === 'pending'" content="Eliminar depósito">
                                <el-button circle size="small" type="danger" plain :icon="Delete" @click="deleteDeposit(dep.id)" />
                            </el-tooltip>
                        </div>
                    </div>
                </div>

                <!-- Historial de pagos -->
                <div v-if="tech.payments.length > 0" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-[11px] font-bold text-gray-400 uppercase mb-2">Historial de pagos</p>
                    <div v-for="pay in tech.payments" :key="pay.id" class="flex items-center justify-between py-1.5 text-sm border-b border-gray-100 dark:border-gray-800 last:border-0">
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
                                <el-button circle size="small" type="info" plain :icon="ZoomIn" @click="showPaymentProof(pay)" />
                            </el-tooltip>
                            <!-- Comprobante del depósito asociado (Deposit voucher) -->
                            <el-tooltip v-if="pay.deposit?.media?.length" content="Ver comprobante de depósito">
                                <el-button circle size="small" type="warning" plain :icon="Coin" @click="showDepositVoucher(pay.deposit)" />
                            </el-tooltip>
                            <el-button circle size="small" type="danger" plain :icon="Delete" @click="deleteTechPayment(pay.id)" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="p-8 text-center text-gray-500">
            <el-icon :size="40" class="mb-2 opacity-50"><Money /></el-icon>
            <p>Esta sección es para gestión de pagos de técnicos externos.</p>
            <p class="text-xs mt-1">Si no hay técnicos externos asignados, esta sección estará vacía.</p>
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
</template>