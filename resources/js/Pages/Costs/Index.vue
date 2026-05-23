<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    budgets: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');

const statuses = [
    'Borrador',
    'Cotización',
    'Presupuesto enviado',
    'Facturado',
    'Facturación',
    'Trabajo en proceso',
    'Trabajo terminado',
    'Pagado',
];

const fetchData = debounce(() => {
    router.get(route('costs.index'), {
        search: search.value,
        status: statusFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 300);

watch([search, statusFilter], fetchData);

const formatCurrency = (value, currency = 'MXN') => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: currency,
    }).format(value || 0);
};

const getStatusColor = (status) => {
    const map = {
        'Borrador': 'info',
        'Cotización': 'warning',
        'Presupuesto enviado': 'primary',
        'Facturado': 'warning',
        'Facturación': 'danger',
        'Trabajo en proceso': 'primary',
        'Trabajo terminado': 'success',
        'Pagado': 'success',
    };
    return map[status] || 'info';
};

const handlePageChange = (val) => {
    router.visit(route('costs.index', { ...route().params, page: val }), {
        preserveState: true,
        preserveScroll: true,
    });
};

const handleRowClick = (row) => {
    router.visit(route('costs.show', row.id));
};
</script>

<template>
    <AppLayout title="Gestión de costos">
        <div class="space-y-4">
            <!-- Barra de Herramientas -->
            <div class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] flex flex-col lg:flex-row justify-between items-center gap-4">
                
                <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto flex-1">
                    <div class="w-full sm:w-80">
                        <el-input
                            v-model="search"
                            placeholder="Buscar por proyecto, cliente..."
                            clearable
                            prefix-icon="Search"
                        />
                    </div>

                    <div class="w-full sm:w-48">
                        <el-select v-model="statusFilter" placeholder="Filtrar estado" clearable class="w-full">
                            <el-option label="Todos (menos Perdido)" value="all" />
                            <el-option v-for="st in statuses" :key="st" :label="st" :value="st" />
                        </el-select>
                    </div>
                </div>
            </div>

            <!-- Tabla de presupuestos para catálogos -->
            <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-alert
                    type="info"
                    :closable="false"
                    show-icon
                    class="m-4"
                >
                    <template #title>
                        Selecciona un presupuesto para generar o editar su catálogo de conceptos y desglosar sus costos unitarios.
                    </template>
                </el-alert>

                <el-table 
                    :data="budgets.data" 
                    style="width: 100%" 
                    stripe
                    @row-click="handleRowClick"
                    row-class-name="cursor-pointer hover:bg-gray-50 dark:hover:bg-[#27272a] transition-colors"
                >
                    <el-table-column prop="id" label="Folio" width="80" align="center">
                        <template #default="scope">
                            <span class="font-mono text-xs text-gray-500">#{{ scope.row.id }}</span>
                        </template>
                    </el-table-column>
                    
                    <el-table-column label="Proyecto" min-width="200">
                        <template #default="scope">
                            <div class="flex flex-col">
                                <span class="font-bold text-gray-800 dark:text-gray-200 text-sm">{{ scope.row.ticket_name }}</span>
                                <span class="font-mono text-xs text-gray-500">Folio Ticket: {{ scope.row.ticket_folio }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Cliente" min-width="180">
                        <template #default="scope">
                            <div class="flex items-center gap-2">
                                <el-icon class="text-gray-400"><OfficeBuilding /></el-icon>
                                <span class="text-sm text-gray-600 dark:text-gray-400">{{ scope.row.customer_name }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Estado" width="140" align="center">
                        <template #default="scope">
                            <el-tag :type="getStatusColor(scope.row.status)" size="small" effect="light" class="w-full text-center">
                                {{ scope.row.status }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Conceptos" width="100" align="center">
                        <template #default="scope">
                            <el-badge :value="scope.row.concept_count" type="primary" class="mt-1">
                                <el-button size="small" circle icon="List" />
                            </el-badge>
                        </template>
                    </el-table-column>

                    <el-table-column label="Total" width="140" align="right">
                        <template #default="scope">
                             <span class="font-mono text-sm font-semibold text-gray-700 dark:text-gray-300">
                                 {{ formatCurrency(scope.row.total_cost, scope.row.currency) }}
                             </span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Acciones" width="100" align="center" fixed="right">
                        <template #default="scope">
                            <Link :href="route('costs.show', scope.row.id)" @click.stop>
                                <el-button type="primary" plain size="small" icon="Edit">Catálogo</el-button>
                            </Link>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Paginación -->
                <div class="flex flex-col sm:flex-row justify-between items-center p-4 bg-gray-50 dark:bg-[#252529] border-t border-gray-100 dark:border-[#2b2b2e]">
                    <div class="text-xs text-gray-500 mb-3 sm:mb-0">
                        Mostrando {{ budgets.from }} a {{ budgets.to }} de {{ budgets.total }} registros
                    </div>
                    <el-pagination
                        v-model:current-page="budgets.current_page"
                        :page-size="budgets.per_page"
                        :total="budgets.total"
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
</style>