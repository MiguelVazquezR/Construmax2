<script setup>
import { ref, watch, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import { ElMessage, ElMessageBox } from 'element-plus';
import { 
    Search, Plus, MoreFilled, View, Edit, Delete, 
    OfficeBuilding, More, UserFilled, Warning, InfoFilled, Minus,
    Sort, Timer, Check
} from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    tickets: Object, // Paginado
    filters: Object, // { search, status, perPage, sort }
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const sortFilter = ref('delay'); // 'delay' o 'start_date'
const perPage = ref(parseInt(props.filters?.perPage) || 10);

const statuses = [
    'Programado', 
    'En proceso', 
    'Completado', 
];

// --- HELPERS DE FECHA Y ESTADO ---

const formatDate = (dateString) => {
    if (!dateString) return '--';
    return new Date(dateString).toLocaleDateString('es-MX', {
        day: '2-digit', month: 'short', year: '2-digit'
    });
};

const getStatusColor = (status) => {
    const map = {
        'Programado': 'info',
        'En proceso': 'primary',
        'Completado': 'success',
    };
    return map[status] || 'info';
};

const getPriorityColor = (priority) => {
    const map = {
        'Baja': 'text-gray-400',
        'Media': 'text-orange-500 font-bold',
        'Alta': 'text-red-500 font-bold',
        'Urgente': 'text-purple-600 font-black' // Color más agresivo para urgente
    };
    return map[priority] || 'text-gray-400';
};

const getPriorityBadge = (priority) => {
    const map = {
        'Baja': 'info',
        'Media': 'warning',
        'Alta': 'danger',
        'Urgente': 'danger'
    };
    return map[priority] || 'info';
};

const getPriorityIcon = (priority) => {
    if (priority === 'Alta' || priority === 'Urgente') return Warning;
    if (priority === 'Media') return InfoFilled;
    return Minus;
};

// --- CÁLCULO DE SALUD (SEMÁFORO) ---
const getHealthStatus = (ticket) => {
    if (ticket.status === 'Completado' || ticket.status === 'Cancelado') {
        return { color: 'success', text: 'Finalizado', icon: Check };
    }

    if (!ticket.scheduled_start || !ticket.scheduled_end) {
        return { color: 'info', text: 'Sin fechas', icon: Minus };
    }

    const start = new Date(ticket.scheduled_start);
    const end = new Date(ticket.scheduled_end);
    const now = new Date();
    const progress = ticket.progress || 0;

    // 1. YA VENCIÓ
    if (now > end && progress < 100) {
        return { color: 'danger', text: 'Vencido', icon: Warning };
    }

    // 2. AÚN NO EMPIEZA
    if (now < start) {
        return { color: 'info', text: 'Programado', icon: Timer };
    }

    // 3. EN TIEMPO (Cálculo de ritmo)
    const totalDuration = end - start;
    const elapsed = now - start;
    
    // Porcentaje de tiempo transcurrido (0 a 1)
    let timePercentage = elapsed / totalDuration;
    timePercentage = Math.min(Math.max(timePercentage, 0), 1); // Clamp

    // Porcentaje de avance real (0 a 1)
    const progressPercentage = progress / 100;

    // Umbral de tolerancia (ej: 15% de holgura)
    if (progressPercentage < (timePercentage - 0.15)) {
        return { color: 'danger', text: 'Atrasado', icon: Warning }; // Rojo
    } else if (progressPercentage < (timePercentage - 0.05)) {
        return { color: 'warning', text: 'En riesgo', icon: Warning }; // Amarillo
    } else {
        return { color: 'success', text: 'A tiempo', icon: Check }; // Verde
    }
};

// --- HELPER PARA TÉCNICOS ---
const getAssignedTechnicians = (ticket) => {
    const techs = new Map();
    
    // Agregar responsable principal
    if (ticket.responsible) {
        techs.set(ticket.responsible.id, ticket.responsible);
    }

    // Agregar técnicos de las tareas
    if (ticket.tasks && ticket.tasks.length > 0) {
        ticket.tasks.forEach(task => {
            if (task.assignee) {
                techs.set(task.assignee.id, task.assignee);
            }
        });
    }

    return Array.from(techs.values());
};

// --- LOGICA DE FILTROS ---

const fetchData = debounce(() => {
    router.get(route('tickets.index'), { 
        search: search.value, 
        status: statusFilter.value,
        sort: sortFilter.value,
        perPage: perPage.value 
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 300);

const handleSizeChange = (val) => {
    perPage.value = val;
    fetchData();
};

const handlePageChange = (val) => {
    router.get(route('tickets.index'), { 
        search: search.value, 
        status: statusFilter.value,
        sort: sortFilter.value,
        perPage: perPage.value, 
        page: val 
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

watch([search, statusFilter, sortFilter], fetchData);

// --- ACCIONES ---

const handleRowClick = (row) => {
    router.visit(route('tickets.show', row.id));
};

const deleteTicket = (ticket) => {
    ElMessageBox.confirm(
        `¿Estás seguro de eliminar el ticket #${ticket.id}? Se perderá el historial de tareas y evidencias.`,
        'Eliminar Ticket',
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
    <AppLayout title="Tickets de servicio">
        <div class="space-y-4">
            <!-- Barra de Herramientas -->
            <div class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] flex flex-col lg:flex-row justify-between items-center gap-4">
                
                <!-- Filtros -->
                <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto flex-1">
                    <div class="w-full sm:w-64">
                        <el-input
                            v-model="search"
                            placeholder="Buscar por folio, cliente, servicio..."
                            clearable
                            prefix-icon="Search"
                        />
                    </div>
                    
                    <div class="w-full sm:w-40">
                        <el-select v-model="statusFilter" placeholder="Estado" clearable>
                            <el-option label="Todos" value="all" />
                            <el-option v-for="st in statuses" :key="st" :label="st" :value="st" />
                        </el-select>
                    </div>

                    <div class="w-full sm:w-48 md:w-56">
                        <el-select v-model="sortFilter" placeholder="Orden">
                            <template #prefix><el-icon><Sort /></el-icon></template>
                            <el-option label="Por Atraso / Urgencia" value="delay">
                                <span class="flex items-center gap-2">
                                    <el-icon class="!text-red-500"><Warning /></el-icon> Por atraso
                                </span>
                            </el-option>
                            <el-option label="Por Fecha Inicio" value="start_date">
                                <span class="flex items-center gap-2">
                                    <el-icon class="!text-blue-500"><Timer /></el-icon> Por inicio
                                </span>
                            </el-option>
                        </el-select>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex items-center gap-3 w-full lg:w-auto justify-end">
                    <Link v-if="can('tickets.create')" :href="route('tickets.create')">
                        <el-button type="primary" color="#f26c17" icon="Plus" class="!font-bold">
                            Nuevo ticket
                        </el-button>
                    </Link>
                </div>
            </div>

            <!-- CONTENEDOR TABLA -->
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
                        <!-- Folio / Prioridad -->
                        <el-table-column label="Folio" width="100">
                            <template #default="scope">
                                <div class="flex flex-col items-center">
                                    <span class="font-mono text-gray-500 font-bold text-xs">#{{ scope.row.id }}</span>
                                    
                                    <el-tag 
                                        :type="getPriorityBadge(scope.row.priority)" 
                                        size="small" 
                                        effect="plain"
                                        class="mt-1 !text-[10px] !h-5 !px-1 w-full text-center"
                                    >
                                        {{ scope.row.priority }}
                                    </el-tag>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Encargado (Antes Técnico) -->
                        <el-table-column label="Encargado" width="160">
                            <template #default="scope">
                                <div class="flex items-center gap-2">
                                    <el-avatar :size="28" :src="scope.row.responsible?.profile_photo_url" class="bg-gray-200 border-2 border-white shadow-sm">
                                        {{ scope.row.responsible?.name?.charAt(0) }}
                                    </el-avatar>
                                    <div class="flex flex-col min-w-0">
                                        <span class="text-xs font-bold text-gray-800 dark:text-gray-200 truncate">
                                            {{ scope.row.responsible?.name }}
                                        </span>
                                        <span class="text-[10px] text-gray-400">Responsable</span>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Técnicos Asignados (Equipo) -->
                        <el-table-column label="Equipo" min-width="120">
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
                                    <div v-if="getAssignedTechnicians(scope.row).length > 4" class="flex items-center justify-center w-6 h-6 rounded-full border-2 border-white bg-gray-200 text-[9px] text-gray-600 font-bold z-10">
                                        +{{ getAssignedTechnicians(scope.row).length - 4 }}
                                    </div>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Servicio / Cliente -->
                        <el-table-column label="Proyecto" min-width="220">
                            <template #default="scope">
                                <div>
                                    <p class="font-bold text-gray-800 dark:text-gray-200 text-sm truncate" :title="scope.row.budget?.service_type">
                                        {{ scope.row.budget?.service_type || 'Servicio General' }}
                                    </p>
                                    <div class="flex items-center gap-1 text-xs text-gray-500">
                                        <el-icon><OfficeBuilding /></el-icon>
                                        <span class="truncate">{{ scope.row.budget?.customer?.name }}</span>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Fechas -->
                        <el-table-column label="Fechas" width="140">
                            <template #default="scope">
                                <div class="text-xs">
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Inicio:</span>
                                        <span class="text-gray-700 dark:text-gray-300 font-mono">{{ formatDate(scope.row.scheduled_start) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Fin:</span>
                                        <span class="text-gray-700 dark:text-gray-300 font-mono">{{ formatDate(scope.row.scheduled_end) }}</span>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Semáforo / Salud -->
                        <el-table-column label="Salud" width="120" align="center">
                            <template #default="scope">
                                <div class="flex flex-col items-center">
                                    <el-tag 
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

                        <!-- Progreso -->
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

                        <!-- Estatus -->
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
                        
                        <!-- Barra de estado lateral (Semáforo) -->
                        <div class="absolute left-0 top-0 bottom-0 w-1" :class="`bg-${getHealthStatus(ticket).color}`"></div>

                        <!-- Header Tarjeta -->
                        <div class="flex justify-between items-start mb-2 pl-3">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-mono font-bold text-gray-500">#{{ ticket.id }}</span>
                                <el-tag :type="getHealthStatus(ticket).color" size="small" effect="dark" class="!border-none !h-5 !text-[10px]">
                                    {{ getHealthStatus(ticket).text }}
                                </el-tag>
                            </div>
                            <el-icon :class="getPriorityColor(ticket.priority)" :size="16">
                                <component :is="getPriorityIcon(ticket.priority)" />
                            </el-icon>
                        </div>

                        <!-- Info Principal -->
                        <div class="pl-3 mb-3">
                            <h3 class="font-bold text-gray-800 dark:text-gray-200 text-sm mb-1 leading-tight">
                                {{ ticket.budget?.service_type || 'Servicio' }}
                            </h3>
                            <p class="text-xs text-gray-500 flex items-center gap-1">
                                <el-icon><OfficeBuilding /></el-icon> 
                                {{ ticket.budget?.customer?.name }}
                            </p>
                        </div>

                        <!-- Footer -->
                        <div class="flex justify-between items-center pl-3">
                            <!-- Equipo -->
                            <div class="flex -space-x-1">
                                <el-avatar 
                                    v-for="tech in getAssignedTechnicians(ticket).slice(0, 3)" 
                                    :key="tech.id"
                                    :size="24" 
                                    :src="tech.profile_photo_url"
                                    class="border border-white"
                                >
                                    {{ tech.name.charAt(0) }}
                                </el-avatar>
                            </div>
                            
                            <!-- Fecha Fin -->
                            <div class="text-right">
                                <p class="text-[10px] text-gray-400 uppercase">Vence</p>
                                <p class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ formatDate(ticket.scheduled_end) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paginación -->
                <div class="flex flex-col sm:flex-row justify-between items-center p-4 bg-gray-50 dark:bg-[#252529] border-t border-gray-100 dark:border-[#2b2b2e]">
                    <div class="text-xs text-gray-500 mb-3 sm:mb-0">
                        Mostrando {{ tickets.from }} a {{ tickets.to }} de {{ tickets.total }} registros
                    </div>
                    <el-pagination
                        v-model:current-page="tickets.current_page"
                        :page-size="tickets.per_page"
                        :total="tickets.total"
                        layout="prev, pager, next"
                        background
                        @current-change="handlePageChange"
                        class="!p-0"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.el-table .cursor-pointer) {
    cursor: pointer;
}
.el-dropdown-link:focus {
    outline: none;
}
</style>