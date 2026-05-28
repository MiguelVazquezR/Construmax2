<script setup>
import { router, Link } from '@inertiajs/vue3';
import { View, Calendar, TrendCharts, Management } from '@element-plus/icons-vue';

const props = defineProps({
    tickets: Array,
    technicianId: Number // ID del usuario técnico actual
});

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

const getTicketStatusType = (status) => {
    const map = {
        'Borrador': 'info',
        'Levantamiento': 'warning',
        'Catálogo': 'primary',
        'Proceso de ejecución': 'warning',
        'Ejecutado': 'success',
        'Facturado': 'primary',
        'Pagado': 'success',
        'Cancelado': 'danger'
    };
    return map[status] || 'info';
};

const getPriorityColor = (priority) => {
    const map = {
        'Baja': 'info',
        'Media': 'primary',
        'Alta': 'warning',
        'Urgente': 'danger'
    };
    return map[priority] || 'info';
};

const navigateToTicket = (ticketId) => {
    router.visit(route('tickets.show', ticketId));
};

// Helper para calcular tareas completadas desde el frontend si vienen cargadas
const getTaskStats = (tasks) => {
    if (!tasks || tasks.length === 0) return { completed: 0, total: 0 };
    const completed = tasks.filter(t => t.status === 'Completada').length;
    return { completed, total: tasks.length };
};
</script>

<template>
    <div class="py-4">
        <div v-if="tickets.length > 0">
            <el-table 
                :data="tickets" 
                style="width: 100%" 
                stripe 
                @row-click="(row) => navigateToTicket(row.id)" 
                row-class-name="cursor-pointer group"
            >
                <el-table-column prop="id" label="Folio" width="80">
                    <template #default="scope">
                        <span class="font-mono font-bold text-gray-500">#{{ scope.row.id }}</span>
                    </template>
                </el-table-column>

                <el-table-column label="Proyecto / Presupuesto" min-width="240">
                    <template #default="scope">
                        <div class="flex flex-col">
                            <!-- Nombre del presupuesto con enlace -->
                            <Link 
                                v-if="scope.row.budget"
                                :href="route('budgets.show', scope.row.budget.id)" 
                                class="font-bold text-blue-600 dark:text-blue-400 hover:underline truncate"
                                @click.stop
                            >
                                {{ scope.row.budget.name }}
                            </Link>
                            <span v-else class="text-gray-400 italic">Sin presupuesto</span>
                            
                            <!-- Cliente -->
                            <div class="text-xs text-gray-500 flex items-center gap-1 mt-0.5">
                                <el-icon><Management /></el-icon>
                                {{ scope.row.budget?.customer?.name || 'Cliente General' }}
                            </div>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Progreso / Tareas" min-width="180">
                    <template #default="scope">
                        <div class="pr-4">
                            <div class="flex justify-between items-center mb-1 text-xs">
                                <span class="text-gray-500">
                                    {{ getTaskStats(scope.row.tasks).completed }} / {{ getTaskStats(scope.row.tasks).total }} tareas
                                </span>
                                <span class="font-bold text-gray-700 dark:text-gray-300">{{ scope.row.progress }}%</span>
                            </div>
                            <el-progress 
                                :percentage="scope.row.progress" 
                                :show-text="false" 
                                :status="scope.row.progress === 100 ? 'success' : ''"
                                :stroke-width="6"
                            />
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Inicio" width="130">
                    <template #default="scope">
                        <div class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400">
                            <el-icon><Calendar /></el-icon>
                            {{ formatDate(scope.row.scheduled_start) }}
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Prioridad" width="100" align="center">
                    <template #default="scope">
                        <el-tag :type="getPriorityColor(scope.row.priority)" size="small" effect="plain" class="w-full text-center">
                            {{ scope.row.priority }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="Estatus" width="120" align="center">
                    <template #default="scope">
                        <el-tag :type="getTicketStatusType(scope.row.status)" size="small" effect="dark" class="w-full font-bold">
                            {{ scope.row.status }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column width="50" align="center">
                    <template #default>
                        <el-icon class="text-gray-300 group-hover:text-primary transition-colors"><View /></el-icon>
                    </template>
                </el-table-column>
            </el-table>
        </div>
        
        <el-empty v-else description="No se ha encontrado historial operativo para este técnico." :image-size="100">
            <template #description>
                <p class="text-gray-500">Asigna tickets a este técnico para ver su actividad aquí.</p>
            </template>
        </el-empty>
    </div>
</template>

<style scoped>
:deep(.cursor-pointer) {
    cursor: pointer;
}
/* Estilo sutil para las filas */
:deep(.el-table__row) {
    transition: background-color 0.2s ease;
}
</style>