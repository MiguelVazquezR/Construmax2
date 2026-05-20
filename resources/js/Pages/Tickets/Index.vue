<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import { Search, Plus, Sort, Warning, Timer, DataBoard, List as ListIcon, Location } from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';
import TicketList from './Partials/TicketList.vue';
import TicketKanban from './Partials/TicketKanban.vue';

const { can } = usePermissions();

const props = defineProps({
    tickets: Object, // Paginado
    filters: Object, // { search, status, perPage, sort, location }
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const sortFilter = ref(props.filters?.sort || 'delay'); 
const locationFilter = ref(props.filters?.location || 'all'); 
const perPage = ref(parseInt(props.filters?.perPage) || 20); // Aumenté el default a 20 para que el Kanban luzca mejor

// Preferencia de vista (Lista o Kanban), guardada en localStorage para mejorar la UX
const viewMode = ref(localStorage.getItem('ticketViewMode') || 'list');

const toggleViewMode = (mode) => {
    viewMode.value = mode;
    localStorage.setItem('ticketViewMode', mode);
};

// Nuevas columnas / estados operativos
const statuses = [
    'Borrador', 
    'Levantamiento', 
    'Catálogo', 
    'Proceso de ejecución', 
    'Ejecutado', 
    'Facturado', 
    'Pagado'
];

// --- LOGICA DE FILTROS ---
const fetchData = debounce(() => {
    router.get(route('tickets.index'), { 
        search: search.value, 
        status: statusFilter.value,
        location: locationFilter.value,
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
        search: search.value, 
        status: statusFilter.value,
        location: locationFilter.value,
        sort: sortFilter.value,
        perPage: perPage.value, 
        page: val 
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

watch([search, statusFilter, locationFilter, sortFilter], fetchData);
</script>

<template>
    <AppLayout title="Tickets de servicio">
        <div class="space-y-4">
            
            <!-- Barra de Herramientas Principal -->
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
                        <el-select v-model="locationFilter" placeholder="Ubicación" clearable class="w-full">
                            <template #prefix><el-icon><Location /></el-icon></template>
                            <el-option label="Todas las ubic." value="all" />
                            <el-option label="Local" value="Local" />
                            <el-option label="Foráneo" value="Foráneo" />
                        </el-select>
                    </div>
                    
                    <div class="w-full sm:w-48">
                        <el-select v-model="statusFilter" placeholder="Estado" clearable class="w-full">
                            <el-option label="Todos los estados" value="all" />
                            <el-option v-for="st in statuses" :key="st" :label="st" :value="st" />
                        </el-select>
                    </div>

                    <div class="w-full sm:w-48 md:w-56">
                        <el-select v-model="sortFilter" placeholder="Orden" class="w-full">
                            <template #prefix><el-icon><Sort /></el-icon></template>
                            <el-option label="Por atraso / urgencia" value="delay">
                                <span class="flex items-center gap-2">
                                    <el-icon class="!text-red-500"><Warning /></el-icon> Por atraso
                                </span>
                            </el-option>
                            <el-option label="Por fecha de inicio" value="start_date">
                                <span class="flex items-center gap-2">
                                    <el-icon class="!text-blue-500"><Timer /></el-icon> Por inicio
                                </span>
                            </el-option>
                        </el-select>
                    </div>
                </div>

                <!-- Botones de Acción y Toggle de Vista -->
                <div class="flex items-center gap-3 w-full lg:w-auto justify-between lg:justify-end">
                    
                    <!-- Toggle Switch -->
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

            <!-- CONTENEDOR DINÁMICO (LISTA O KANBAN) -->
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