<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { 
    MoreFilled, View, Edit, Delete, 
    OfficeBuilding, Warning, InfoFilled, Minus,
    Timer, Check, Location
} from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    tickets: Object, 
});

const emit = defineEmits(['page-change']);

const formatDate = (dateString) => {
    if (!dateString) return '--';
    return new Date(dateString).toLocaleDateString('es-MX', {
        day: '2-digit', month: 'short', year: '2-digit'
    });
};

const getStatusColor = (status) => {
    const map = {
        'Borrador': 'info',
        'Programado': 'info',
        'Levantamiento': 'warning',
        'Catálogo': 'primary',
        'Proceso de ejecución': 'warning',
        'Ejecutado': 'success',
        'Finalizado': 'success',
        'Facturado': 'primary',
        'Pagado': 'success',
    };
    return map[status] || 'info';
};

// Clases CSS personalizadas para hacer la prioridad más llamativa
const getPriorityClasses = (priority) => {
    const map = {
        'Baja': 'bg-gray-100 text-gray-500 border border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700',
        'Media': 'bg-orange-100 text-orange-600 border border-orange-200 dark:bg-orange-900/40 dark:text-orange-400 dark:border-orange-800',
        'Alta': 'bg-red-500 text-white font-bold shadow-sm',
        'Urgente': 'bg-red-600 text-white font-black shadow-md animate-pulse ring-2 ring-red-300 dark:ring-red-900'
    };
    return map[priority] || map['Media'];
};

const getHealthStatus = (ticket) => {
    // Finalized statuses: always show as Finalizado
    if (['Ejecutado', 'Finalizado', 'Facturado', 'Pagado', 'Cancelado'].includes(ticket.status)) {
        return { color: 'success', text: 'Finalizado', icon: Check };
    }

    if (!ticket.scheduled_start || !ticket.scheduled_end) {
        return { color: 'info', text: 'Sin fechas', icon: Minus };
    }

    const start = new Date(ticket.scheduled_start);
    const end = new Date(ticket.scheduled_end);
    // Add 1 day to the end date — the ticket is overdue the day AFTER the due date
    end.setDate(end.getDate() + 1);
    const now = new Date();

    // Past the end date
    if (now > end) {
        return { color: 'danger', text: 'Vencido', icon: Warning };
    }

    // Before the start date
    if (now < start) {
        return { color: 'info', text: 'Programado', icon: Timer };
    }

    // Within the date range
    return { color: 'success', text: 'A tiempo', icon: Check };
};

const getAssignedTechnicians = (ticket) => {
    const techs = new Map();
    if (ticket.tasks && ticket.tasks.length > 0) {
        ticket.tasks.forEach(task => {
            if (task.assignee) techs.set(task.assignee.id, task.assignee);
        });
    }
    return Array.from(techs.values());
};

// Devuelve "Sucursal - Unidad (Región, País)" a partir de la relación branch del ticket
const getBranchDetails = (ticket) => {
    const b = ticket.branch;
    if (!b || typeof b !== 'object') return 'Sucursal no especificada';

    const head = [b.branch_name, b.unit].filter(Boolean).join(' - ');
    const location = [b.city, b.region, b.country].filter(Boolean).join(', ');

    if (head && location) return `${head} (${location})`;
    return head || location || 'Sucursal no especificada';
};

const handleRowClick = (row) => {
    router.visit(route('tickets.show', row.id));
};

const deleteTicket = (ticket) => {
    ElMessageBox.confirm(
        `¿Estás seguro de eliminar el ticket #${ticket.id}? Se perderá el historial de tareas y evidencias.`,
        'Eliminar ticket',
        {
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            type: 'error',
        }
    )
    .then(() => {
        router.delete(route('tickets.destroy', ticket.id), {
             onSuccess: () => ElMessage.success('Ticket eliminado correctamente')
        });
    })
    .catch(() => {});
};
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
        
        <!-- VISTA ESCRITORIO -->
        <div class="hidden md:block">
            <el-table 
                :data="tickets.data" 
                style="width: 100%" 
                stripe
                @row-click="handleRowClick"
                row-class-name="cursor-pointer hover:bg-gray-50 dark:hover:bg-[#27272a] transition-colors"
            >
                <!-- Folio (Dinámico) / Prioridad Llamativa / Fecha de creación -->
                <el-table-column label="Folio / Prioridad" width="150">
                    <template #default="scope">
                        <div class="flex flex-col items-center gap-1">
                            <span class="font-mono text-gray-700 dark:text-gray-300 font-bold text-xs bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded w-full text-center">
                                {{ scope.row.folio }}
                            </span>
                            <span 
                                :class="['text-[10px] uppercase px-2 py-0.5 rounded-full text-center w-full', getPriorityClasses(scope.row.priority)]"
                            >
                                {{ scope.row.priority }}
                            </span>
                            <span class="text-[9px] text-gray-400 dark:text-gray-500 font-mono w-full text-center">
                                {{ formatDate(scope.row.created_at) }}
                            </span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Equipo (Tareas)" min-width="140">
                    <template #default="scope">
                        <div class="flex -space-x-2 overflow-hidden py-1">
                            <el-tooltip 
                                v-for="tech in getAssignedTechnicians(scope.row).slice(0, 4)" 
                                :key="tech.id"
                                :content="tech.name"
                                placement="top"
                            >
                                <el-avatar 
                                    :size="26" 
                                    :src="tech.profile_photo_url"
                                    class="inline-block border-2 border-white dark:border-[#1e1e20] bg-gray-100 text-gray-500 text-xs"
                                >
                                    {{ tech.name.charAt(0) }}
                                </el-avatar>
                            </el-tooltip>
                            <div v-if="getAssignedTechnicians(scope.row).length === 0" class="text-xs text-gray-400 italic">
                                Sin asignar
                            </div>
                            <div v-else-if="getAssignedTechnicians(scope.row).length > 4" class="flex items-center justify-center w-6 h-6 rounded-full border-2 border-white bg-gray-200 text-[9px] text-gray-600 font-bold z-10">
                                +{{ getAssignedTechnicians(scope.row).length - 4 }}
                            </div>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Proyecto y Sitio" min-width="260">
                    <template #default="scope">
                        <div>
                            <p class="font-bold text-gray-800 dark:text-gray-200 text-sm truncate" :title="scope.row.name || scope.row.service_type">
                                {{ scope.row.name || scope.row.service_type || 'Servicio general' }}
                            </p>
                            <div class="flex flex-col gap-0.5 mt-1">
                                <div class="flex items-center gap-1 text-xs text-gray-500">
                                    <el-icon><OfficeBuilding /></el-icon>
                                    <span class="truncate font-medium">{{ scope.row.customer?.name }}</span>
                                </div>
                                <div class="flex items-center gap-1 text-[11px] text-gray-500" v-if="scope.row.branch">
                                    <el-icon><Location /></el-icon>
                                    <span class="truncate" :title="getBranchDetails(scope.row)">
                                        {{ getBranchDetails(scope.row) }}
                                    </span>
                                </div>
                            </div>
                            <!-- Indicador de catálogo de costos -->
                            <div class="flex items-center gap-2 mt-1.5">
                                <el-tag v-if="scope.row.budget?.latest_catalog" size="small" type="success" effect="plain" class="!text-[10px] !h-5 !px-1.5">
                                    Catálogo v{{ scope.row.budget.latest_catalog.version }}
                                </el-tag>
                            </div>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Asesor" width="150">
                    <template #default="scope">
                        <div v-if="scope.row.seller" class="flex items-center gap-2">
                            <el-avatar :size="28" :src="scope.row.seller.profile_photo_url" class="bg-gray-100 text-gray-500 text-xs shrink-0">
                                {{ scope.row.seller.name.charAt(0) }}
                            </el-avatar>
                            <div class="flex flex-col leading-tight">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate max-w-[90px]">
                                    {{ scope.row.seller.name }}
                                </span>
                            </div>
                        </div>
                        <span v-else class="text-xs text-gray-400 italic">Sin asignar</span>
                    </template>
                </el-table-column>

                <el-table-column label="Fechas" width="140">
                    <template #default="scope">
                        <div class="text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Inicio:</span>
                                <span class="text-gray-700 dark:text-gray-300 font-mono">{{ formatDate(scope.row.scheduled_start) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Fin:</span>
                                <span 
                                    :class="[
                                        'font-mono',
                                        getHealthStatus(scope.row).text === 'Vencido' 
                                            ? 'text-red-500 font-bold' 
                                            : 'text-gray-700 dark:text-gray-300'
                                    ]"
                                >
                                    {{ formatDate(scope.row.scheduled_end) }}
                                </span>
                            </div>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Salud" width="120" align="center">
                    <template #default="scope">
                        <div class="flex flex-col items-center">
                            <el-tooltip
                                v-if="getHealthStatus(scope.row).text === 'Vencido'"
                                content="Vence un día después de la fecha de fin programada"
                                placement="top"
                            >
                                <el-tag 
                                    :type="getHealthStatus(scope.row).color" 
                                    effect="dark" 
                                    size="small" 
                                    class="w-full text-center border-none font-bold cursor-help"
                                >
                                    {{ getHealthStatus(scope.row).text }}
                                </el-tag>
                            </el-tooltip>
                            <el-tag 
                                v-else
                                :type="getHealthStatus(scope.row).color" 
                                effect="dark" 
                                size="small" 
                                class="w-full text-center border-none font-bold"
                            >
                                {{ getHealthStatus(scope.row).text }}
                            </el-tag>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Progreso" width="100">
                    <template #default="scope">
                        <div class="flex flex-col items-center">
                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ scope.row.progress || 0 }}%</span>
                            <el-progress 
                                :percentage="scope.row.progress || 0" 
                                :stroke-width="4" 
                                :show-text="false"
                                :color="scope.row.progress === 100 ? '#67c23a' : '#f26c17'"
                                class="w-full mt-1"
                            />
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Estado" width="110" align="center">
                    <template #default="scope">
                        <el-tag :type="getStatusColor(scope.row.status)" size="small" effect="light" class="w-full border-none">
                            {{ scope.row.status }}
                        </el-tag>
                    </template>
                </el-table-column>

                <!-- Acciones -->
                <el-table-column width="60" align="center" fixed="right">
                    <template #default="scope">
                        <div @click.stop>
                            <el-dropdown trigger="click">
                                <span class="el-dropdown-link cursor-pointer p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full transition">
                                    <el-icon><MoreFilled /></el-icon>
                                </span>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <Link :href="route('tickets.show', scope.row.id)">
                                            <el-dropdown-item icon="View">Ver detalles</el-dropdown-item>
                                        </Link>
                                        <Link v-if="can('tickets.edit')" :href="route('tickets.edit', scope.row.id)">
                                            <el-dropdown-item icon="Edit">Editar</el-dropdown-item>
                                        </Link>
                                        <el-dropdown-item v-if="can('tickets.delete')" divided icon="Delete" class="text-red-500" @click="deleteTicket(scope.row)">
                                            Eliminar
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                    </template>
                </el-table-column>
            </el-table>
        </div>

        <!-- VISTA MÓVIL (Tarjetas) -->
        <div class="md:hidden">
            <div v-for="ticket in tickets.data" :key="ticket.id" class="p-4 border-b border-gray-100 dark:border-[#2b2b2e] hover:bg-gray-50 dark:hover:bg-[#252529] cursor-pointer relative" @click="handleRowClick(ticket)">
                
                <div class="absolute left-0 top-0 bottom-0 w-1" :class="`bg-${getHealthStatus(ticket).color}`"></div>

                <div class="flex justify-between items-start mb-2 pl-3">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-gray-700 dark:text-gray-300 font-bold text-xs bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded">
                            {{ ticket.folio }}
                        </span>
                        <el-tooltip
                            v-if="getHealthStatus(ticket).text === 'Vencido'"
                            content="Vence un día después de la fecha de fin programada"
                            placement="top"
                        >
                            <el-tag :type="getHealthStatus(ticket).color" size="small" effect="dark" class="!border-none !h-5 !text-[10px] cursor-help">
                                {{ getHealthStatus(ticket).text }}
                            </el-tag>
                        </el-tooltip>
                        <el-tag v-else :type="getHealthStatus(ticket).color" size="small" effect="dark" class="!border-none !h-5 !text-[10px]">
                            {{ getHealthStatus(ticket).text }}
                        </el-tag>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <span 
                            :class="['text-[10px] uppercase px-2 py-0.5 rounded-full font-bold', getPriorityClasses(ticket.priority)]"
                        >
                            {{ ticket.priority }}
                        </span>
                        <span class="text-[9px] text-gray-400 dark:text-gray-500 font-mono">
                            {{ formatDate(ticket.created_at) }}
                        </span>
                    </div>
                </div>

                <div class="pl-3 mb-3">
                    <h3 class="font-bold text-gray-800 dark:text-gray-200 text-sm mb-1 leading-tight">
                        {{ ticket.name || ticket.service_type || 'Servicio general' }}
                    </h3>
                    <div class="flex flex-col gap-0.5">
                        <p class="text-xs text-gray-500 flex items-center gap-1 font-medium">
                            <el-icon><OfficeBuilding /></el-icon> 
                            {{ ticket.customer?.name }}
                        </p>
                        <p class="text-[11px] text-gray-400 flex items-center gap-1" v-if="ticket.branch">
                            <el-icon><Location /></el-icon> 
                            <span class="truncate">{{ getBranchDetails(ticket) }}</span>
                        </p>
                    </div>

                    <div v-if="ticket.seller" class="mt-2 flex items-center gap-1.5 pl-3">
                        <el-avatar :size="20" :src="ticket.seller.profile_photo_url" class="bg-gray-100 text-gray-500 text-[10px] shrink-0">
                            {{ ticket.seller.name.charAt(0) }}
                        </el-avatar>
                        <span class="text-xs text-gray-500">Asesor: <span class="font-medium">{{ ticket.seller.name }}</span></span>
                    </div>
                </div>

                <div class="flex justify-between items-center pl-3">
                    <div class="flex -space-x-1">
                        <el-avatar 
                            v-for="tech in getAssignedTechnicians(ticket).slice(0, 3)" 
                            :key="tech.id"
                            :size="24" 
                            :src="tech.profile_photo_url"
                            class="border border-white dark:border-[#1e1e20]"
                        >
                            {{ tech.name.charAt(0) }}
                        </el-avatar>
                        <span v-if="getAssignedTechnicians(ticket).length === 0" class="text-[10px] text-gray-400 italic bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                            Sin asignar
                        </span>
                    </div>
                    
                    <div class="text-right">
                        <p class="text-[10px] text-gray-400 uppercase">Vence</p>
                        <p 
                            :class="[
                                'text-xs font-bold',
                                getHealthStatus(ticket).text === 'Vencido' 
                                    ? 'text-red-500' 
                                    : 'text-gray-700 dark:text-gray-300'
                            ]"
                        >
                            {{ formatDate(ticket.scheduled_end) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row justify-between items-center p-4 bg-gray-50 dark:bg-[#252529] border-t border-gray-100 dark:border-[#2b2b2e]">
            <div class="text-xs text-gray-500 mb-3 sm:mb-0">
                Mostrando {{ tickets.from }} a {{ tickets.to }} de {{ tickets.total }} registros
            </div>
            <el-pagination
                :current-page="tickets.current_page"
                :page-size="tickets.per_page"
                :total="tickets.total"
                layout="prev, pager, next"
                background
                @current-change="$emit('page-change', $event)"
                class="!p-0"
            />
        </div>
    </div>
</template>

<style scoped>
:deep(.el-table .cursor-pointer) {
    cursor: pointer;
}
.el-dropdown-link:focus {
    outline: none;
}
</style>