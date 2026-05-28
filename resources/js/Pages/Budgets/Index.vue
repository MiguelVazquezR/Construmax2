<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import TableList from '@/Pages/Budgets/Partials/TableList.vue';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    budgets: Object,
    filters: Object,
    users: Array, // Nueva prop recibida del controlador
});

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');
const perPage = ref(parseInt(props.filters.perPage) || 10);
const branchFilter = ref(props.filters.branch || '');

// Lógica de inicialización del filtro de usuarios
// Si viene 'all' o vacío, el array es vacío (se ven todos).
// Si viene un valor/array, lo convertimos a array de números para el el-select.
const transformUserId = (val) => {
    if (!val || val === 'all') return [];
    if (Array.isArray(val)) return val.map(Number);
    return [Number(val)];
};
const userFilter = ref(transformUserId(props.filters.user_id));

const ticketStatuses = [
    'Borrador',
    'Cotización',
    'Proceso de ejecución',
    'Ejecutado',
    'Facturación',
    'Facturado',
    'Pagado',
    'Completado',
    'Cancelado',
];

// Sincronización con servidor
const fetchData = debounce(() => {
    router.get(route('budgets.index'), { 
        search: search.value, 
        status: statusFilter.value,
        perPage: perPage.value,
        branch: branchFilter.value,
        user_id: userFilter.value.length > 0 ? userFilter.value : 'all',
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 300);

watch([search, statusFilter, perPage, userFilter, branchFilter], fetchData);
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
                    
                    <!-- NUEVO: Filtro de asesor/vendedor -->
                    <div class="w-full sm:w-60">
                         <el-select 
                            v-model="userFilter" 
                            placeholder="Asesor/vendedor" 
                            multiple 
                            collapse-tags 
                            collapse-tags-tooltip
                            clearable
                            class="w-full"
                        >
                            <el-option 
                                v-for="user in users" 
                                :key="user.id" 
                                :label="user.name" 
                                :value="user.id" 
                            />
                        </el-select>
                    </div>

                    <div class="w-full sm:w-48">
                        <el-select v-model="statusFilter" placeholder="Filtrar estado" clearable class="w-full">
                            <el-option label="Todos los estados" value="all" />
                            <el-option v-for="st in ticketStatuses" :key="st" :label="st" :value="st" />
                        </el-select>
                    </div>

                    <div class="w-full sm:w-48">
                        <el-input
                            v-model="branchFilter"
                            placeholder="Sucursal, región, unidad..."
                            clearable
                            prefix-icon="Search"
                        />
                    </div>
                </div>

                <!-- Acciones Derecha -->
                <div class="flex items-center gap-3 w-full lg:w-auto justify-end">
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

            <!-- Contenido -->
            <el-alert
                type="info"
                :closable="false"
                show-icon
                class="mb-4"
            >
                <template #title>
                    Los presupuestos permiten el control administrativo de un ticket: registro de costos, moneda, pagos del cliente y pagos a técnicos.
                </template>
            </el-alert>
            <TableList :budgets="budgets" />

        </div>
    </AppLayout>
</template>