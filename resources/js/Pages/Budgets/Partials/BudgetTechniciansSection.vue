<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Avatar, Coin } from '@element-plus/icons-vue';
import RequestDepositModal from '@/Components/Deposits/RequestDepositModal.vue';

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

    return Object.values(techs).map(tech => ({
        ...tech,
        amount_to_pay: totalTechnicianAmount.value,
        payment_progress: totalTechnicianAmount.value > 0
            ? Math.min(Math.round((tech.total_paid / totalTechnicianAmount.value) * 100), 100)
            : 0,
    }));
});

// Monto total de conceptos marcados como pago a técnico
const totalTechnicianAmount = computed(() => {
    if (!props.budget.concepts) return 0;
    return props.budget.concepts
        .filter(c => c.paid_to_technician)
        .reduce((sum, c) => sum + parseFloat(c.amount), 0);
});

const deleteTechPayment = (paymentId) => {
    ElMessageBox.confirm('¿Eliminar este registro de pago a técnico?', 'Confirmar', {
        type: 'warning',
    }).then(() => {
        router.delete(route('budgets.technician-payments.destroy', paymentId), {
            onSuccess: () => ElMessage.success('Pago eliminado'),
        });
    }).catch(() => {});
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
                            <p class="font-bold text-gray-800 dark:text-white">{{ tech.user.name }}</p>
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

                <div v-if="tech.payments.length > 0" class="mt-3 bg-gray-50 dark:bg-[#252529] rounded p-3 text-sm">
                    <p class="text-xs font-bold text-gray-500 mb-2 uppercase">Historial de pagos</p>
                    <ul class="space-y-2">
                        <li v-for="pay in tech.payments" :key="pay.id" class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 last:border-0 pb-1 last:pb-0">
                            <div class="flex gap-2 items-center">
                                <span class="font-mono font-bold">{{ formatCurrency(pay.amount) }}</span>
                                <span class="text-xs text-gray-400">({{ formatDate(pay.payment_date) }})</span>
                                <el-tag v-if="pay.reference" size="small" type="info" class="scale-90">{{ pay.reference }}</el-tag>
                            </div>
                            <div class="flex gap-1">
                                <el-tooltip content="Ver comprobante" placement="top" v-if="pay.media && pay.media.length > 0">
                                    <el-button circle size="small" icon="Document" @click="openPreview(pay.media[0])" />
                                </el-tooltip>
                                <el-button circle size="small" type="danger" plain icon="Delete" @click="deleteTechPayment(pay.id)" />
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div v-else class="p-8 text-center text-gray-500">
            <el-icon :size="40" class="mb-2 opacity-50"><User /></el-icon>
            <p>Esta sección es para gestión de pagos de técnicos externos.</p>
            <p class="text-xs mt-1">Si no hay técnicos externos asignados, esta sección estará vacía.</p>
        </div>
    </div>

    <!-- Modal solicitar depósito -->
    <RequestDepositModal
        v-if="selectedTechForDeposit"
        v-model="showDepositModal"
        :technician="selectedTechForDeposit"
        :ticket="depositTicketInfo"
        @saved="showDepositModal = false"
    />
</template>
