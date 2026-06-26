<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { OfficeBuilding, Timer, Location, UserFilled } from '@element-plus/icons-vue';

const props = defineProps({
    tickets: Object,
});

const emit = defineEmits(['page-change']);

const localTickets = ref([]);

watch(() => props.tickets.data, (newVal) => {
    localTickets.value = JSON.parse(JSON.stringify(newVal));
}, { immediate: true });

const columns = [
    { id: 'Borrador', label: 'Borrador', color: '#9ca3af' },
    { id: 'Programado', label: 'Programado', color: '#6366f1' },
    { id: 'Levantamiento', label: 'Levantamiento', color: '#0d9488' },
    { id: 'Catálogo', label: 'Cotización (Catálogo)', color: '#3b82f6' },
    { id: 'Proceso de ejecución', label: 'En ejecución', color: '#f59e0b' },
    { id: 'Ejecutado', label: 'Ejecutado', color: '#10b981' },
    { id: 'Finalizado', label: 'Finalizado', color: '#059669' },
    { id: 'Facturado', label: 'Facturado', color: '#eab308' },
    { id: 'Pagado', label: 'Pagado', color: '#34d399' },
    { id: 'Cancelado', label: 'Cancelado', color: '#ef4444' },
];

const groupedTickets = computed(() => {
    const groups = {};
    columns.forEach(col => groups[col.id] = []);
    
    localTickets.value.forEach(ticket => {
        if (groups[ticket.status]) {
            groups[ticket.status].push(ticket);
        } else {
            if (!groups['Borrador']) groups['Borrador'] = [];
            groups['Borrador'].push(ticket);
        }
    });
    return groups;
});

const draggedItem = ref(null);

const onDragStart = (e, ticket) => {
    draggedItem.value = ticket;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', ticket.id);
};

const onDragOver = (e) => {
    e.preventDefault(); 
    e.dataTransfer.dropEffect = 'move';
};

const onDrop = async (e, targetStatus) => {
    e.preventDefault();
    const ticket = draggedItem.value;
    if (!ticket || ticket.status === targetStatus) {
        draggedItem.value = null;
        return;
    }

    // When moving to Catálogo, a budget is required
    if (targetStatus === 'Catálogo' && !ticket.budget) {
        try {
            await ElMessageBox.confirm(
                'A partir del proceso de creación de catálogo, se requiere un presupuesto relacionado al ticket. ¿Deseas crear el presupuesto ahora?',
                'Presupuesto requerido',
                {
                    confirmButtonText: 'Crear presupuesto',
                    cancelButtonText: 'Más tarde',
                    type: 'warning',
                }
            );
            // Update status first, then redirect to budget creation
            router.put(route('tickets.update-status', ticket.id), {
                status: targetStatus
            }, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    router.visit(route('budgets.create', { ticket_id: ticket.id }));
                },
                onError: () => {
                    ElMessage.error('Error al actualizar el estatus.');
                }
            });
        } catch {
            // User chose "Más tarde" — do not change status
        }
        draggedItem.value = null;
        return;
    }

    updateStatus(ticket.id, targetStatus);
    draggedItem.value = null;
};

const updateStatus = (ticketId, newStatus) => {
    const index = localTickets.value.findIndex(t => t.id === ticketId);
    if (index !== -1) {
        localTickets.value[index].status = newStatus;
    }

    router.put(route('tickets.update-status', ticketId), {
        status: newStatus
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            router.reload({ only: ['tickets'] });
            ElMessage.error('Error al actualizar el estado del ticket.');
        }
    });
};

const handleCardClick = (id) => {
    router.visit(route('tickets.show', id));
};

const formatDate = (dateString) => {
    if (!dateString) return '--';
    return new Date(dateString).toLocaleDateString('es-MX', {
        day: '2-digit', month: 'short'
    });
};

const getAssignedTechnicians = (ticket) => {
    const techs = new Map();
    if (ticket.responsible) techs.set(ticket.responsible.id, ticket.responsible);
    if (ticket.tasks && ticket.tasks.length > 0) {
        ticket.tasks.forEach(task => {
            if (task.assignee) techs.set(task.assignee.id, task.assignee);
        });
    }
    return Array.from(techs.values());
};
</script>

<template>
    <div class="h-full flex flex-col bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
        <div class="flex-1 overflow-x-auto pb-4 custom-scrollbar-x">
            <div class="flex gap-4 min-w-[1600px] px-1">
                
                <div 
                    v-for="col in columns" 
                    :key="col.id" 
                    class="flex-1 min-w-[260px] bg-gray-50/50 dark:bg-[#18181b] rounded-xl p-3 flex flex-col h-[calc(100vh-280px)] border border-transparent transition-colors"
                    @dragover="onDragOver"
                    @drop="onDrop($event, col.id)"
                >
                    <!-- Cabecera -->
                    <div class="flex justify-between items-center mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full border-2" :style="{ backgroundColor: col.color, borderColor: col.color }"></span>
                            <h3 class="font-bold text-gray-700 dark:text-gray-200 text-sm">{{ col.label }}</h3>
                        </div>
                        <span class="bg-white dark:bg-[#27272a] text-xs font-bold px-2 py-0.5 rounded-full text-gray-500 shadow-sm border border-gray-100 dark:border-gray-700">
                            {{ groupedTickets[col.id]?.length || 0 }}
                        </span>
                    </div>

                    <!-- Lista de Tarjetas -->
                    <div class="flex-1 overflow-y-auto space-y-3 custom-scrollbar-y pr-1">
                        <div 
                            v-for="ticket in groupedTickets[col.id]" 
                            :key="ticket.id"
                            draggable="true"
                            @dragstart="onDragStart($event, ticket)"
                            @click="handleCardClick(ticket.id)"
                            class="bg-white dark:bg-[#252529] p-3 rounded-lg shadow-sm border-l-4 hover:shadow-md hover:-translate-y-0.5 transition-all cursor-move group relative"
                            :style="{ borderLeftColor: col.color }"
                        >

                            <div class="flex justify-between items-start mb-2 pr-2">
                                <span class="text-xs font-mono font-bold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">
                                    {{ ticket.folio }}
                                </span>
                                <span 
                                    class="text-[10px] px-1.5 py-0.5 rounded font-medium"
                                    :class="ticket.priority === 'Urgente' || ticket.priority === 'Alta' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600 dark:bg-[#3f3f46] dark:text-gray-300'"
                                >
                                    {{ ticket.priority }}
                                </span>
                            </div>
                            
                            <h4 class="font-bold text-gray-800 dark:text-gray-200 text-sm leading-tight mb-1 group-hover:text-primary transition-colors pr-2">
                                {{ ticket.name || ticket.service_type || 'Servicio' }}
                            </h4>
                            
                            <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1 pr-2">
                                <el-icon :size="12"><OfficeBuilding /></el-icon>
                                <span class="truncate">{{ ticket.customer?.name }}</span>
                            </div>

                            <div v-if="ticket.seller" class="text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1 pr-2 mt-0.5">
                                <el-icon :size="12"><UserFilled /></el-icon>
                                <span class="truncate">{{ ticket.seller.name }}</span>
                            </div>

                            <div v-if="ticket.branch" class="text-xs text-gray-400 dark:text-gray-500 mb-3 flex items-center gap-1 pr-2 mt-0.5">
                                <el-icon :size="12"><Location /></el-icon>
                                <span class="truncate">{{ [ticket.branch.branch_name, ticket.branch.unit].filter(Boolean).join(' · ') }}</span>
                            </div>
                            <div v-else class="mb-3"></div>

                            <div class="flex justify-between items-end border-t border-gray-100 dark:border-[#3f3f46] pt-2 mt-2 pr-2">
                                <div class="flex -space-x-1">
                                    <el-avatar 
                                        v-for="tech in getAssignedTechnicians(ticket).slice(0, 3)" 
                                        :key="tech.id"
                                        :size="22" 
                                        :src="tech.profile_photo_url"
                                        class="border border-white dark:border-[#252529]"
                                    >
                                        {{ tech.name.charAt(0) }}
                                    </el-avatar>
                                    <div v-if="getAssignedTechnicians(ticket).length > 3" class="flex items-center justify-center w-[22px] h-[22px] rounded-full border border-white bg-gray-100 text-[9px] text-gray-600 font-bold z-10">
                                        +{{ getAssignedTechnicians(ticket).length - 3 }}
                                    </div>
                                </div>
                                
                                <div class="text-right flex flex-col items-end">
                                    <span class="text-[10px] text-gray-400 flex items-center gap-1">
                                        <el-icon><Timer /></el-icon> {{ formatDate(ticket.scheduled_end) }}
                                    </span>
                                    <span class="text-[10px] font-bold" :class="ticket.progress === 100 ? 'text-green-500' : 'text-primary'">
                                        {{ ticket.progress || 0 }}%
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-if="!groupedTickets[col.id]?.length" class="h-full flex flex-col items-center justify-center opacity-40 min-h-[100px] border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-lg">
                            <p class="text-xs text-gray-400">Arrastra aquí</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="flex justify-center mt-2 pt-3 border-t border-gray-100 dark:border-[#2b2b2e]">
             <el-pagination
                :current-page="tickets.current_page"
                :page-size="tickets.per_page"
                :total="tickets.total"
                layout="prev, pager, next"
                background
                small
                @current-change="$emit('page-change', $event)"
            />
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar-x::-webkit-scrollbar { height: 8px; }
.custom-scrollbar-x::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar-x::-webkit-scrollbar-thumb { background-color: #d1d5db; border-radius: 4px; }
.custom-scrollbar-x::-webkit-scrollbar-thumb:hover { background-color: #9ca3af; }

.custom-scrollbar-y::-webkit-scrollbar { width: 4px; }
.custom-scrollbar-y::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar-y::-webkit-scrollbar-thumb { background-color: #e5e7eb; border-radius: 4px; }
:global(.dark) .custom-scrollbar-y::-webkit-scrollbar-thumb { background-color: #3f3f46; }
:global(.dark) .custom-scrollbar-x::-webkit-scrollbar-thumb { background-color: #3f3f46; }
</style>