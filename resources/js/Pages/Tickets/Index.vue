<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import { ElMessage, ElMessageBox } from 'element-plus';

const props = defineProps({
    tickets: Object, // Paginado
    filters: Object, // { search, status, perPage }
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const perPage = ref(parseInt(props.filters?.perPage) || 10);

const statuses = [
    'Programado', 
    'En proceso', 
    'En espera', 
    'Revisión', 
    'Completado', 
    'Cancelado'
];

// --- HELPERS ---

const formatDate = (dateString) => {
    if (!dateString) return '--';
    return new Date(dateString).toLocaleDateString('es-MX', {
        day: '2-digit', month: 'short'
    });
};

const getStatusColor = (status) => {
    const map = {
        'Programado': 'info',
        'En proceso': 'primary',
        'En espera': 'warning',
        'Revisión': 'warning',
        'Completado': 'success',
        'Cancelado': 'danger'
    };
    return map[status] || 'info';
};

const getPriorityColor = (priority) => {
    const map = {
        'Baja': 'text-gray-400',
        'Media': 'text-orange-400',
        'Alta': 'text-red-500',
        'Urgente': 'text-red-600 font-bold'
    };
    return map[priority] || 'text-gray-400';
};

const getPriorityIcon = (priority) => {
    if (priority === 'Alta' || priority === 'Urgente') return 'Warning';
    if (priority === 'Media') return 'InfoFilled';
    return 'Minus'; // Baja
};

// --- LOGICA DE FILTROS ---

const fetchData = debounce(() => {
    router.get(route('tickets.index'), { 
        search: search.value, 
        status: statusFilter.value, 
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
        perPage: perPage.value, 
        page: val 
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

watch([search, statusFilter], fetchData);

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
                    <div class="w-full sm:w-72">
                        <el-input
                            v-model="search"
                            placeholder="Buscar por folio, cliente, servicio..."
                            clearable
                            prefix-icon="Search"
                        />
                    </div>
                    
                    <div class="w-full sm:w-48">
                        <el-select v-model="statusFilter" placeholder="Estado" clearable>
                            <el-option label="Todos" value="all" />
                            <el-option v-for="st in statuses" :key="st" :label="st" :value="st" />
                        </el-select>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex items-center gap-3 w-full lg:w-auto justify-end">
                    <el-select v-model="perPage" placeholder="Ver" style="width: 100px" class="hidden sm:block">
                        <el-option label="10 / pág" :value="10" />
                        <el-option label="20 / pág" :value="20" />
                        <el-option label="50 / pág" :value="50" />
                    </el-select>

                    <Link :href="route('tickets.create')">
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
                                <div class="flex items-center gap-2">
                                    <el-tooltip :content="`Prioridad: ${scope.row.priority}`" placement="top">
                                        <el-icon :class="getPriorityColor(scope.row.priority)" :size="16">
                                            <component :is="getPriorityIcon(scope.row.priority)" />
                                        </el-icon>
                                    </el-tooltip>
                                    <span class="font-mono text-gray-500 font-bold">#{{ scope.row.id }}</span>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Servicio / Cliente -->
                        <el-table-column label="Servicio / Cliente" min-width="250">
                            <template #default="scope">
                                <div>
                                    <p class="font-bold text-gray-800 dark:text-gray-200 text-sm truncate">
                                        {{ scope.row.budget?.service_type || 'Servicio General' }}
                                    </p>
                                    <div class="flex items-center gap-1 text-xs text-gray-500">
                                        <el-icon><OfficeBuilding /></el-icon>
                                        <span class="truncate">{{ scope.row.budget?.customer?.name }}</span>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Responsable -->
                        <el-table-column label="Técnico" min-width="150">
                            <template #default="scope">
                                <div class="flex items-center gap-2">
                                    <el-avatar :size="24" class="!text-[10px] bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        {{ scope.row.responsible?.name?.charAt(0) }}
                                    </el-avatar>
                                    <span class="text-sm text-gray-700 dark:text-gray-300 truncate">
                                        {{ scope.row.responsible?.name }}
                                    </span>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Fechas -->
                        <el-table-column label="Programación" width="180">
                            <template #default="scope">
                                <div class="text-xs">
                                    <p class="text-gray-500">Del: <span class="text-gray-700 dark:text-gray-300 font-medium">{{ formatDate(scope.row.scheduled_start) }}</span></p>
                                    <p class="text-gray-500">Al: <span class="text-gray-700 dark:text-gray-300 font-medium">{{ formatDate(scope.row.scheduled_end) }}</span></p>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Progreso -->
                        <el-table-column label="Progreso" width="120">
                            <template #default="scope">
                                <!-- El atributo 'progress' debe venir del backend (append) o calcularse aquí si vienen las tareas -->
                                <div class="flex items-center gap-2">
                                    <el-progress 
                                        :percentage="scope.row.progress || 0" 
                                        :stroke-width="6" 
                                        :show-text="false"
                                        :color="scope.row.progress === 100 ? '#67c23a' : '#f26c17'"
                                        class="w-full"
                                    />
                                    <span class="text-xs text-gray-500">{{ scope.row.progress || 0 }}%</span>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Estatus -->
                        <el-table-column label="Estado" width="140" align="center">
                            <template #default="scope">
                                <el-tag :type="getStatusColor(scope.row.status)" size="small" effect="light" class="w-full border-none">
                                    {{ scope.row.status }}
                                </el-tag>
                            </template>
                        </el-table-column>

                        <!-- Acciones -->
                        <el-table-column width="80" align="center" fixed="right">
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
                                                <Link :href="route('tickets.edit', scope.row.id)">
                                                    <el-dropdown-item icon="Edit">Editar</el-dropdown-item>
                                                </Link>
                                                <el-dropdown-item divided icon="Delete" class="text-red-500" @click="deleteTicket(scope.row)">
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
                    <div v-for="ticket in tickets.data" :key="ticket.id" class="p-4 border-b border-gray-100 dark:border-[#2b2b2e] hover:bg-gray-50 dark:hover:bg-[#252529] cursor-pointer" @click="handleRowClick(ticket)">
                        
                        <!-- Header Tarjeta -->
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-mono font-bold text-gray-500">#{{ ticket.id }}</span>
                                <el-tag :type="getStatusColor(ticket.status)" size="small" effect="plain" class="!border-none">
                                    {{ ticket.status }}
                                </el-tag>
                            </div>
                            <el-icon :class="getPriorityColor(ticket.priority)" :size="16">
                                <component :is="getPriorityIcon(ticket.priority)" />
                            </el-icon>
                        </div>

                        <!-- Info Principal -->
                        <h3 class="font-bold text-gray-800 dark:text-gray-200 text-sm mb-1">
                            {{ ticket.budget?.service_type || 'Servicio' }}
                        </h3>
                        <p class="text-xs text-gray-500 flex items-center gap-1 mb-3">
                            <el-icon><OfficeBuilding /></el-icon> 
                            {{ ticket.budget?.customer?.name }}
                        </p>

                        <!-- Fechas y Responsable -->
                        <div class="flex justify-between items-end border-t border-gray-50 dark:border-gray-800 pt-3">
                            <div class="flex flex-col gap-1">
                                <span class="text-[10px] text-gray-400 uppercase">Programado</span>
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">
                                    {{ formatDate(ticket.scheduled_start) }} - {{ formatDate(ticket.scheduled_end) }}
                                </span>
                            </div>
                            
                            <div class="flex items-center gap-2" @click.stop>
                                <el-avatar :size="24" class="!text-[10px] bg-gray-100 text-gray-600">
                                    {{ ticket.responsible?.name?.charAt(0) }}
                                </el-avatar>
                                
                                <el-dropdown trigger="click">
                                    <div class="p-1 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700">
                                        <el-icon class="text-gray-400"><More /></el-icon>
                                    </div>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <Link :href="route('tickets.edit', ticket.id)">
                                                <el-dropdown-item icon="Edit">Editar</el-dropdown-item>
                                            </Link>
                                            <el-dropdown-item icon="Delete" class="text-red-500" @click="deleteTicket(ticket)">Eliminar</el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
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