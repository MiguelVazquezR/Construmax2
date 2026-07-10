<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import { Plus, Sort, DataBoard, List as ListIcon, Location, Search } from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';
import TicketList from './Partials/TicketList.vue';
import TicketKanban from './Partials/TicketKanban.vue';

const { can } = usePermissions();

const props = defineProps({
    tickets: Object, 
    customers: { type: Array, default: () => [] },
    technicians: { type: Array, default: () => [] },
    sellers: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) }, 
    canViewAll: { type: Boolean, default: false },
});

const currentUserName = ref(''); // Will be set from page props if available

// Helper para extraer los filtros de forma segura y evitar conflictos nativos de JS
const getFilter = (key, defaultVal) => {
    if (props.filters && !Array.isArray(props.filters) && props.filters[key] !== undefined && props.filters[key] !== null) {
        return props.filters[key];
    }
    return defaultVal;
};

const folioFilter = ref(getFilter('folio', ''));
const customerFilter = ref(getFilter('customer', '') ? Number(getFilter('customer', '')) : '');
const regionFilter = ref(getFilter('region', ''));
const priorityFilter = ref(getFilter('priority', ''));
const technicianFilter = ref(getFilter('technician', '') ? Number(getFilter('technician', '')) : '');
const sellerFilter = ref(getFilter('seller', '') ? Number(getFilter('seller', '')) : '');
const catalogFilter = ref(getFilter('has_catalog', ''));

// Validación extra explícita para asegurar que siempre sea un string correcto
let initialSort = getFilter('sort', 'created_at');
if (typeof initialSort !== 'string') initialSort = 'created_at';
const sortFilter = ref(initialSort); 

const perPage = ref(parseInt(getFilter('perPage', 20))); 

const viewMode = ref(localStorage.getItem('ticketViewMode') || 'list');

const toggleViewMode = (mode) => {
    viewMode.value = mode;
    localStorage.setItem('ticketViewMode', mode);
};

const statuses = [
    'Borrador',
    'Programado',
    'Levantamiento', 
    'Catálogo', 
    'Proceso de ejecución', 
    'Ejecutado',
    'Finalizado',
    'Facturado', 
    'Pagado',
    'Cancelado',
];

const defaultStatuses = ['Borrador', 'Programado', 'Levantamiento', 'Catálogo', 'Proceso de ejecución', 'Ejecutado'];

// Status filter uses an array for multi-select support
const rawStatus = getFilter('status', defaultStatuses);
const statusFilter = ref(Array.isArray(rawStatus) ? [...rawStatus] : defaultStatuses);

// Handle "all" toggle logic in the multi-select
const handleStatusChange = (value) => {
    if (!value || value.length === 0) {
        statusFilter.value = [...defaultStatuses];
        return;
    }
    if (value.includes('all')) {
        if (value.length === 1) {
            // Only "all" — keep it
        } else if (value[value.length - 1] === 'all') {
            // User just picked "all" → keep only "all"
            statusFilter.value = ['all'];
        } else {
            // "all" was already selected, user added others → remove "all"
            statusFilter.value = value.filter(s => s !== 'all');
        }
    }
};

const fetchData = debounce(() => {
    router.get(route('tickets.index'), { 
        folio: folioFilter.value,
        customer: customerFilter.value,
        region: regionFilter.value,
        priority: priorityFilter.value,
        technician: technicianFilter.value,
        seller: sellerFilter.value,
        status: statusFilter.value,
        has_catalog: catalogFilter.value,
        sort: sortFilter.value,
        perPage: perPage.value 
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 300);

const handlePageChange = (val) => {
    router.get(route('tickets.index'), { 
        folio: folioFilter.value,
        customer: customerFilter.value,
        region: regionFilter.value,
        priority: priorityFilter.value,
        technician: technicianFilter.value,
        seller: sellerFilter.value,
        status: statusFilter.value,
        has_catalog: catalogFilter.value,
        sort: sortFilter.value,
        perPage: perPage.value, 
        page: val 
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

watch([folioFilter, customerFilter, regionFilter, priorityFilter, technicianFilter, sellerFilter, statusFilter, catalogFilter, sortFilter], fetchData);
</script>

<template>
    <AppLayout title="Tickets de servicio">
        <div class="space-y-4">

            <!-- Indicador de alcance de vista -->
            <el-alert
                v-if="canViewAll"
                title="Mostrando tickets de todos los asesores"
                type="info"
                show-icon
                :closable="false"
                class="!bg-blue-50 dark:!bg-blue-900/20 !text-blue-700 dark:!text-blue-300 border border-blue-100 dark:border-blue-800"
            />
            <el-alert
                v-else
                title="Mostrando solo tus tickets como asesor"
                type="info"
                show-icon
                :closable="false"
                class="!bg-blue-50 dark:!bg-blue-900/20 !text-blue-700 dark:!text-blue-300 border border-blue-100 dark:border-blue-800"
            >
                <template #default>
                    <span class="text-xs">Los tickets donde no eres el asesor asignado no se muestran.</span>
                </template>
            </el-alert>

            <!-- PANEL DE FILTROS Y CONTROLES -->
            <div class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] space-y-4">
                
                <!-- Fila Superior: Filtros específicos -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-8 gap-3 w-full">
                    <el-input v-model="folioFilter" placeholder="Folio (Ej. #34)" clearable :prefix-icon="Search" class="w-full" />
                    
                    <el-select v-model="customerFilter" placeholder="Filtrar por cliente" clearable filterable class="w-full">
                        <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>

                    <el-input v-model="regionFilter" placeholder="Sucursal, unidad, región o país..." clearable :prefix-icon="Location" class="w-full" />

                    <el-select v-model="priorityFilter" placeholder="Prioridad" clearable class="w-full">
                        <el-option label="Baja" value="Baja" />
                        <el-option label="Media" value="Media" />
                        <el-option label="Alta" value="Alta" />
                        <el-option label="Urgente" value="Urgente" />
                    </el-select>

                    <el-select v-model="technicianFilter" placeholder="Técnico asignado" clearable filterable class="w-full">
                        <el-option v-for="t in technicians" :key="t.id" :label="t.name" :value="t.id" />
                    </el-select>

                    <el-select v-model="sellerFilter" placeholder="Vendedor / Asesor" clearable filterable class="w-full">
                        <el-option v-for="s in sellers" :key="s.id" :label="s.name" :value="s.id" />
                    </el-select>

                    <el-select v-model="catalogFilter" placeholder="Catálogo" clearable class="w-full">
                        <el-option label="Todos" value="" />
                        <el-option label="Con catálogo" value="yes" />
                        <el-option label="Sin catálogo" value="no" />
                    </el-select>

                    <el-select v-model="statusFilter" placeholder="Estado" multiple collapse-tags collapse-tags-tooltip class="w-full" @change="handleStatusChange">
                        <el-option label="Todos los estados" value="all" />
                        <el-option v-for="st in statuses" :key="st" :label="st" :value="st" />
                    </el-select>
                </div>

                <!-- Fila Inferior: Ordenamiento y Acciones -->
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 border-t border-gray-100 dark:border-gray-800 pt-4">
                    
                    <div class="w-full sm:w-64">
                        <el-select v-model="sortFilter" placeholder="Ordenar por" class="w-full">
                            <template #prefix><el-icon><Sort /></el-icon></template>
                            <el-option label="Por fecha de creación" value="created_at" />
                            <el-option label="Por atraso / urgencia" value="delay" />
                            <el-option label="Por fecha de inicio" value="start_date" />
                        </el-select>
                    </div>

                    <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end">
                        
                        <el-radio-group v-model="viewMode" size="default" @change="toggleViewMode">
                            <el-radio-button label="list" title="Vista de lista">
                                <el-icon><ListIcon /></el-icon>
                            </el-radio-button>
                            <el-radio-button label="kanban" title="Vista Kanban">
                                <el-icon><DataBoard /></el-icon>
                            </el-radio-button>
                        </el-radio-group>

                        <Link v-if="can('tickets.create')" :href="route('tickets.create')">
                            <el-button type="primary" color="#f26c17" icon="Plus" class="!font-bold">
                                Nuevo ticket
                            </el-button>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- CONTENEDOR DE VISTAS (Lista o Kanban) -->
            <transition name="el-fade-in-linear" mode="out-in">
                <div :key="viewMode">
                    <TicketList 
                        v-if="viewMode === 'list'" 
                        :tickets="tickets" 
                        @page-change="handlePageChange"
                    />
                    
                    <TicketKanban 
                        v-else-if="viewMode === 'kanban'" 
                        :tickets="tickets" 
                        @page-change="handlePageChange"
                    />
                </div>
            </transition>

        </div>
    </AppLayout>
</template>