<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { usePermissions } from '@/Composables/usePermissions';
import { Money, Wallet, User, Avatar } from '@element-plus/icons-vue';

const { can } = usePermissions();

const props = defineProps({
    budget: Object,
    ticketId: Number,
    ticket: Object,
});

const formatCurrency = (value, currency = 'MXN') => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: currency,
    }).format(value || 0);
};

const formatMXN = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(value || 0);
};

const getTicketStatusColor = (status) => {
    const map = {
        'Borrador': 'info',
        'Cotización': 'warning',
        'Proceso de ejecución': 'primary',
        'Ejecutado': 'success',
        'Facturación': 'danger',
        'Facturado': 'warning',
        'Pagado': 'success',
        'Completado': 'success',
        'Cancelado': 'danger',
    };
    return map[status] || 'info';
};

// Técnicos externos desde las tareas del ticket (pueden no tener pagos aún)
const externalTechniciansFromTasks = computed(() => {
    if (!props.ticket?.tasks?.length) return [];

    const seen = new Set();
    const techs = [];

    props.ticket.tasks.forEach(task => {
        if (!task.assignee) return;
        // Solo externos (no internos)
        if (task.assignee.technician?.is_internal) return;
        if (seen.has(task.user_id)) return;
        seen.add(task.user_id);
        techs.push(task.assignee);
    });

    return techs;
});

// Agrupa pagos a técnicos externos (solo los que ya tienen pagos)
const externalTechnicianPayments = computed(() => {
    if (!props.budget?.technician_payments?.length) return [];

    const grouped = {};

    props.budget.technician_payments.forEach(payment => {
        // Saltar técnicos internos
        if (payment.technician?.technician?.is_internal) return;

        const userId = payment.user_id;
        if (!grouped[userId]) {
            grouped[userId] = {
                user: payment.technician,
                total_paid: 0,
                payments: [],
            };
        }
        grouped[userId].total_paid += parseFloat(payment.amount);
        grouped[userId].payments.push(payment);
    });

    return Object.values(grouped);
});

// Técnicos externos de tareas que AÚN NO tienen pagos
const externalTechniciansWithoutPayments = computed(() => {
    const paidUserIds = new Set(externalTechnicianPayments.value.map(t => t.user.id));
    return externalTechniciansFromTasks.value.filter(u => !paidUserIds.has(u.id));
});

// ¿Hay al menos un técnico externo (con o sin pagos)?
const hasExternalTechnicians = computed(() => {
    return externalTechnicianPayments.value.length > 0 || externalTechniciansWithoutPayments.value.length > 0;
});
</script>

<template>
    <div class="space-y-4">

        <!-- Sin presupuesto -->
        <div v-if="!budget" class="text-center py-12">
            <el-icon :size="48" class="mb-4 opacity-30"><Money /></el-icon>
            <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">
                Sin presupuesto registrado
            </h3>
            <p class="text-sm text-gray-500 mb-6 max-w-md mx-auto">
                Este ticket aún no tiene un presupuesto asociado. Crea uno para llevar el control de costos, pagos del cliente y pagos a técnicos.
            </p>
            <Link v-if="can('budgets.create')" :href="route('budgets.create', { ticket_id: ticketId })">
                <el-button type="primary" color="#f26c17" icon="Plus">
                    Crear presupuesto
                </el-button>
            </Link>
            <p v-else class="text-xs text-gray-400">No tienes permisos para crear presupuestos.</p>
        </div>

        <!-- Con presupuesto -->
        <template v-else>
            <!-- Alerta informativa -->
            <el-alert
                type="info"
                :closable="false"
                show-icon
            >
                <template #title>
                    Para registrar un pago del cliente o un pago a técnico, dirígete a los detalles completos del presupuesto.
                </template>
            </el-alert>

            <!-- Resumen -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Estado y responsable -->
                <div class="bg-gray-50 dark:bg-[#252529] rounded-lg p-4 border border-gray-100 dark:border-[#3f3f46]">
                    <div class="flex items-center gap-2 mb-3">
                        <el-icon class="text-gray-400"><User /></el-icon>
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Estado y responsable</span>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <el-tag :type="getTicketStatusColor(budget.ticket?.status)" size="small">{{ budget.ticket?.status || 'N/A' }}</el-tag>
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        {{ budget.responsible?.name || '—' }}
                    </p>
                </div>

                <!-- Costos -->
                <div class="bg-gray-50 dark:bg-[#252529] rounded-lg p-4 border border-gray-100 dark:border-[#3f3f46]">
                    <div class="flex items-center gap-2 mb-3">
                        <el-icon class="text-gray-400"><Money /></el-icon>
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Costos</span>
                    </div>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white">
                        {{ formatCurrency(budget.total_cost, budget.currency) }}
                    </p>
                    <p v-if="budget.currency === 'USD'" class="text-xs text-gray-400">
                        ≈ {{ formatMXN(budget.total_cost * budget.exchange_rate) }} MXN
                    </p>
                    <p class="text-xs text-gray-400 mt-1">
                        {{ budget.concepts?.length || 0 }} conceptos
                    </p>
                </div>

                <!-- Pagos -->
                <div class="bg-gray-50 dark:bg-[#252529] rounded-lg p-4 border border-gray-100 dark:border-[#3f3f46]">
                    <div class="flex items-center gap-2 mb-3">
                        <el-icon class="text-gray-400"><Wallet /></el-icon>
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Pagos</span>
                    </div>
                    <div class="space-y-1">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Pagado</span>
                            <span class="font-medium text-green-600">{{ formatCurrency(budget.total_paid, budget.currency) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Pendiente</span>
                            <span class="font-medium" :class="budget.balance_due > 0 ? 'text-red-500' : 'text-green-500'">
                                {{ formatCurrency(budget.balance_due, budget.currency) }}
                            </span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5 dark:bg-gray-700 mt-2">
                        <div
                            class="bg-green-500 h-1.5 rounded-full transition-all"
                            :style="{ width: Math.min((budget.total_paid / budget.total_cost) * 100, 100) + '%' }"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- Pagos a técnicos externos -->
            <div class="bg-white dark:bg-[#1e1e20] rounded-lg border border-gray-100 dark:border-[#3f3f46] overflow-hidden">
                <div class="p-3 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#3f3f46]">
                    <h4 class="font-medium text-gray-800 dark:text-gray-200 text-sm flex items-center gap-2">
                        <el-icon><Avatar /></el-icon> Pagos a técnicos externos
                    </h4>
                </div>

                <!-- Hay técnicos externos (con o sin pagos) -->
                <template v-if="hasExternalTechnicians">
                    <div class="p-3 space-y-2">
                        <!-- Técnicos CON pagos -->
                        <div v-for="tech in externalTechnicianPayments" :key="tech.user.id" class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2">
                                <el-avatar :size="24" :src="tech.user.profile_photo_url">
                                    {{ tech.user.name?.charAt(0) }}
                                </el-avatar>
                                <span class="text-gray-700 dark:text-gray-300">{{ tech.user.name }}</span>
                            </div>
                            <span class="font-mono font-medium text-green-600">
                                {{ formatCurrency(tech.total_paid, budget.currency) }}
                            </span>
                        </div>

                        <!-- Técnicos SIN pagos -->
                        <div v-for="user in externalTechniciansWithoutPayments" :key="user.id" class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2">
                                <el-avatar :size="24" :src="user.profile_photo_url">
                                    {{ user.name?.charAt(0) }}
                                </el-avatar>
                                <span class="text-gray-700 dark:text-gray-300">{{ user.name }}</span>
                            </div>
                            <span class="text-xs text-gray-400 italic">Sin pagos</span>
                        </div>

                        <el-divider v-if="externalTechnicianPayments.length > 0" class="!my-2" />
                        <div v-if="externalTechnicianPayments.length > 0" class="flex justify-between text-sm font-semibold">
                            <span class="text-gray-500">Total pagado a externos</span>
                            <span class="text-gray-800 dark:text-white">
                                {{ formatCurrency(externalTechnicianPayments.reduce((s, t) => s + t.total_paid, 0), budget.currency) }}
                            </span>
                        </div>
                    </div>
                </template>

                <!-- Sin técnicos externos en absoluto -->
                <div v-else class="p-6 text-center text-sm text-gray-400">
                    <el-icon :size="24" class="mb-1 opacity-40"><Avatar /></el-icon>
                    <p>Esta sección es solo para técnicos externos.</p>
                    <p v-if="budget.technician_payments?.length" class="text-xs mt-1">
                        Los pagos registrados pertenecen a técnicos internos.
                    </p>
                </div>
            </div>

            <!-- Acceso directo -->
            <div class="flex justify-end">
                <Link :href="route('budgets.show', budget.id)">
                    <el-button type="primary" icon="Right">
                        Ver detalles completos del presupuesto
                    </el-button>
                </Link>
            </div>
        </template>
    </div>
</template>
