<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import Kanban from '@/Pages/Budgets/Partials/Kanban.vue';
import TableList from '@/Pages/Budgets/Partials/TableList.vue';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    budgets: Object,
    filters: Object,
});

// Estado de la vista: 'list' | 'kanban'
// Recuperamos del localStorage para recordar la preferencia del usuario
const viewMode = ref(localStorage.getItem('budget_view_mode') || 'list');

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');
const perPage = ref(parseInt(props.filters.perPage) || 10);

const statuses = [
    'Borrador', 
    'Presupuesto enviado', 
    'Facturado', 
    'Trabajo en proceso', 
    'Trabajo terminado', 
    'Pagado', 
    'Perdido'
];

// Cambiar modo de vista
const toggleView = (mode) => {
    viewMode.value = mode;
    localStorage.setItem('budget_view_mode', mode);
};

// Sincronización con servidor
const fetchData = debounce(() => {
    router.get(route('budgets.index'), { 
        search: search.value, 
        status: statusFilter.value,
        perPage: perPage.value 
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 300);

watch([search, statusFilter, perPage], fetchData);
</script>

<template>
    <AppLayout title="Gestión de presupuestos">
        <div class="space-y-4">
            <!-- Barra de Herramientas -->
            <div class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] flex flex-col lg:flex-row justify-between items-center gap-4">
                
                <!-- Filtros Izquierda -->
                <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto flex-1">
                    <div class="w-full sm:w-64">
                        <el-input
                            v-model="search"
                            placeholder="Buscar por nombre, cliente..."
                            clearable
                            prefix-icon="Search"
                        />
                    </div>
                    
                    <div class="w-full sm:w-48">
                        <el-select v-model="statusFilter" placeholder="Filtrar estado" clearable>
                            <el-option label="Todos los estados" value="all" />
                            <el-option v-for="st in statuses" :key="st" :label="st" :value="st" />
                        </el-select>
                    </div>
                </div>

                <!-- Acciones Derecha -->
                <div class="flex items-center gap-3 w-full lg:w-auto justify-end">
                    
                    <!-- Switcher de Vista -->
                    <div class="bg-gray-100 dark:bg-[#252529] p-1 rounded-lg flex items-center">
                        <button 
                            @click="toggleView('list')"
                            class="p-2 rounded-md transition-all duration-200"
                            :class="viewMode === 'list' ? 'bg-white dark:bg-[#1e1e20] shadow-sm text-primary' : 'text-gray-400 hover:text-gray-600'"
                            title="Vista de lista"
                        >
                            <el-icon :size="18"><notebook /></el-icon>
                        </button>
                        <button 
                            @click="toggleView('kanban')"
                            class="p-2 rounded-md transition-all duration-200"
                            :class="viewMode === 'kanban' ? 'bg-white dark:bg-[#1e1e20] shadow-sm text-primary' : 'text-gray-400 hover:text-gray-600'"
                            title="Vista Kanban"
                        >
                            <el-icon :size="18"><files /></el-icon>
                        </button>
                    </div>

                    <div class="h-6 w-px bg-gray-200 dark:bg-gray-700 mx-1"></div>

                    <el-select v-model="perPage" placeholder="Ver" style="width: 100px" class="hidden sm:block">
                        <el-option label="10 / pág" :value="10" />
                        <el-option label="20 / pág" :value="20" />
                        <el-option label="50 / pág" :value="50" />
                    </el-select>

                    <Link v-if="can('budgets.create')" :href="route('budgets.create')">
                        <el-button type="primary" color="#f26c17" icon="Plus">
                            Nuevo
                        </el-button>
                    </Link>
                </div>
            </div>

            <!-- Contenido Dinámico -->
            <transition name="el-fade-in-linear" mode="out-in">
                <div :key="viewMode">
                    <TableList 
                        v-if="viewMode === 'list'" 
                        :budgets="budgets" 
                    />
                    <Kanban 
                        v-else 
                        :budgets="budgets" 
                    />
                </div>
            </transition>

        </div>
    </AppLayout>
</template>