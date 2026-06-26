<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import AppLayout from '@/Layouts/AppLayout.vue';
import TicketTasks from '@/Pages/Tickets/Partials/TicketTasks.vue';
import TicketInfo from '@/Pages/Tickets/Partials/TicketInfo.vue';
import TicketTimeline from '@/Pages/Tickets/Partials/TicketTimeline.vue';
import TicketBudgetCard from '@/Pages/Tickets/Partials/TicketBudgetCard.vue';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    ticket: Object,
    users: Array, // Para asignar tareas en el componente de tareas
});

const activeTab = ref('tasks');

const statusOptions = [
    { label: 'Borrador', value: 'Borrador', color: '#9ca3af' },
    { label: 'Programado', value: 'Programado', color: '#6366f1' },
    { label: 'Levantamiento', value: 'Levantamiento', color: '#0d9488' },
    { label: 'Cotización (Catálogo)', value: 'Catálogo', color: '#3b82f6' },
    { label: 'En ejecución', value: 'Proceso de ejecución', color: '#f59e0b' },
    { label: 'Ejecutado', value: 'Ejecutado', color: '#10b981' },
    { label: 'Finalizado', value: 'Finalizado', color: '#059669' },
    { label: 'Facturado', value: 'Facturado', color: '#eab308' },
    { label: 'Pagado', value: 'Pagado', color: '#34d399' },
    { label: 'Cancelado', value: 'Cancelado', color: '#ef4444' },
];

const currentStatus = ref(props.ticket.status);

const hasBudget = computed(() => !!props.ticket.budget);

const getStatusColor = (status) => {
    const map = {
        'Borrador': 'secondary',
        'Programado': 'info',
        'Levantamiento': 'info',
        'Catálogo': 'primary',
        'Proceso de ejecución': 'warning',
        'Ejecutado': 'warning',
        'Finalizado': 'success',
        'Facturado': 'success',
        'Pagado': 'success',
        'Cancelado': 'danger'
    };
    return map[status] || 'info';
};

async function handleStatusChange(newStatus) {
    // v-model already updated currentStatus, so compare against the original prop value
    if (props.ticket.status === newStatus) return;

    // If changing to Catálogo and no budget exists, require confirmation
    if (newStatus === 'Catálogo' && !hasBudget.value) {
        try {
            await ElMessageBox.confirm(
                'Para mover este ticket a Cotización (Catálogo) es necesario tener un presupuesto registrado. ¿Deseas crear uno ahora?',
                'Presupuesto requerido',
                {
                    confirmButtonText: 'Crear presupuesto',
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                }
            );
            // Change status first, then redirect to budget creation
            router.put(route('tickets.update-status', props.ticket.id), {
                status: newStatus
            }, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    router.visit(route('budgets.create', { ticket_id: props.ticket.id }));
                },
                onError: () => {
                    currentStatus.value = props.ticket.status;
                    ElMessage.error('Error al actualizar el estatus.');
                }
            });
            return;
        } catch {
            // User cancelled — revert to previous status
            currentStatus.value = props.ticket.status;
            return;
        }
    }

    router.put(route('tickets.update-status', props.ticket.id), {
        status: newStatus
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            ElMessage.success('Estatus actualizado correctamente.');
        },
        onError: () => {
            currentStatus.value = props.ticket.status;
            ElMessage.error('Error al actualizar el estatus.');
        }
    });
}
</script>

<template>
    <AppLayout :title="`Ticket ${ticket.folio}`">
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-3">
                    <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">
                        Ticket {{ ticket.folio }}
                    </h2>
                    <el-select
                        v-model="currentStatus"
                        value-key="value"
                        class="!w-56"
                        @change="handleStatusChange"
                    >
                        <el-option
                            v-for="opt in statusOptions"
                            :key="opt.value"
                            :label="opt.label"
                            :value="opt.value"
                        >
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full inline-block" :style="{ backgroundColor: opt.color }"></span>
                                <span>{{ opt.label }}</span>
                            </div>
                        </el-option>
                    </el-select>
                </div>
                
                <div class="flex gap-2">
                    <Link :href="route('tickets.index')">
                        <el-button icon="Back">Volver</el-button>
                    </Link>
                    <Link v-if="can('tickets.edit')" :href="route('tickets.edit', ticket.id)">
                        <el-button type="primary" color="#f26c17" icon="Edit">
                            Editar
                        </el-button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">
            
            <!-- TARJETA RESUMEN SUPERIOR -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                <div class="flex flex-col md:flex-row gap-6 justify-between items-center">
                    
                    <!-- Info Básica -->
                    <div class="flex items-center gap-4 w-full md:w-auto">
                        <div class="size-14 flex items-center justify-center bg-orange-50 dark:bg-orange-900/20 rounded-full text-primary">
                            <el-icon :size="32"><Tools /></el-icon>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white leading-tight">
                                {{ ticket.name }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ ticket.customer?.name }}
                            </p>
                            <p v-if="ticket.seller" class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                Asesor: {{ ticket.seller.name }}
                            </p>
                        </div>
                    </div>

                    <!-- Progreso General -->
                    <div class="w-full md:w-1/3">
                        <div class="flex justify-between mb-1">
                            <span class="text-xs text-gray-500">Progreso de tareas</span>
                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ ticket.progress }}%</span>
                        </div>
                        <el-progress 
                            :percentage="ticket.progress" 
                            :show-text="false" 
                            :stroke-width="10" 
                            :color="ticket.progress === 100 ? '#67c23a' : '#f26c17'"
                        />
                    </div>
                </div>
            </div>

            <!-- CONTENIDO EN PESTAÑAS -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] min-h-[500px]">
                <el-tabs v-model="activeTab" class="px-6 pt-2">
                    
                    <!-- TAB 1: GESTIÓN DE TAREAS -->
                    <el-tab-pane label="Tareas y seguimiento" name="tasks">
                        <TicketTasks :ticket="ticket" :users="users" />
                    </el-tab-pane>

                    <!-- TAB 2: INFORMACIÓN DETALLADA -->
                    <el-tab-pane label="Información general" name="info">
                        <TicketInfo :ticket="ticket" />
                    </el-tab-pane>

                    <!-- TAB 3: CRONOGRAMA -->
                    <el-tab-pane label="Cronograma" name="timeline">
                        <TicketTimeline :ticket="ticket" />
                    </el-tab-pane>

                    <!-- TAB 4: PRESUPUESTO -->
                    <el-tab-pane label="Presupuesto" name="budget">
                        <div class="pb-4">
                            <TicketBudgetCard :budget="ticket.budget" :ticket-id="ticket.id" :ticket="ticket" />
                        </div>
                    </el-tab-pane>

                </el-tabs>
            </div>

        </div>
    </AppLayout>
</template>