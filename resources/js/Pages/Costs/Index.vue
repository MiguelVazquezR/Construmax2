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
const catalogFilter = ref(props.filters.catalog || 'all');
const branchFilter = ref(props.filters.branch || '');

const fetchData = debounce(() => {
    router.get(route('costs.index'), {
        search: search.value,
        catalog: catalogFilter.value,
        branch: branchFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 300);

watch([search, catalogFilter, branchFilter], fetchData);

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

const getCatalogTotalLabel = (row) => {
    if (row.has_catalog && row.latest_version) {
        return `v${row.latest_version}`;
    }
    return 'Presupuesto';
};
</script>

<template>
    <AppLayout title="Gestión de costos">
        <div class="space-y-4">
            <!-- Barra de Herramientas -->
            <div
                class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] flex flex-col lg:flex-row justify-between items-center gap-4">

                <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto flex-1">
                    <div class="w-full sm:w-80">
                        <el-input v-model="search" placeholder="Buscar proyecto, cliente, folio ticket..." clearable
                            prefix-icon="Search" />
                    </div>

                    <el-input v-model="branchFilter" placeholder="Filtrar sucursal, unidad, país, región..." clearable
                        prefix-icon="Search" class="w-full sm:w-64" />

                    <el-select v-model="catalogFilter" placeholder="Filtro de catálogo" clearable class="lg:!w-1/4">
                        <el-option label="Todos los presupuestos" value="all" />
                        <el-option label="Con catálogo" value="with" />
                        <el-option label="Sin catálogo" value="without" />
                    </el-select>
                </div>
            </div>

            <!-- Tabla de presupuestos para catálogos -->
            <div
                class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-alert type="info" :closable="false" show-icon class="m-4">
                    <template #title>
                        Selecciona un presupuesto para generar o editar su catálogo de conceptos y desglosar sus costos
                        unitarios.
                    </template>
                </el-alert>

                <el-table :data="budgets.data" style="width: 100%" stripe @row-click="handleRowClick"
                    row-class-name="cursor-pointer hover:bg-gray-50 dark:hover:bg-[#27272a] transition-colors">

                    <el-table-column label="Proyecto" min-width="200">
                        <template #default="scope">
                            <div class="flex flex-col">
                                <span class="font-bold text-gray-800 dark:text-gray-200 text-sm">{{
                                    scope.row.ticket_name }}</span>
                                <span class="font-mono text-xs text-gray-500">Folio Ticket: {{ scope.row.ticket_folio
                                    }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Cliente" min-width="180">
                        <template #default="scope">
                            <div class="flex items-center gap-2">
                                <el-icon class="text-gray-400">
                                    <OfficeBuilding />
                                </el-icon>
                                <span class="text-sm text-gray-600 dark:text-gray-400">{{ scope.row.customer_name
                                    }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Unidad" width="120">
                        <template #default="scope">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ scope.row.branch_unit }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="País" width="100">
                        <template #default="scope">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ scope.row.branch_country }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Región" width="100">
                        <template #default="scope">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ scope.row.branch_region }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Contacto" width="140">
                        <template #default="scope">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ scope.row.contact_name }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Estado" width="140" align="center">
                        <template #default="scope">
                            <el-tag :type="getStatusColor(scope.row.status)" size="small" effect="light"
                                class="w-full text-center">
                                {{ scope.row.status }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Catálogo" width="120" align="center">
                        <template #default="scope">
                            <el-tag v-if="scope.row.has_catalog" type="success" size="small" effect="light">
                                Versión {{ scope.row.latest_version }}
                            </el-tag>
                            <el-tag v-else type="info" size="small" effect="light" class="text-gray-500">
                                Sin registro
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Total" width="150" align="right">
                        <template #default="scope">
                            <div class="flex flex-col items-end">
                                <span class="font-mono text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ formatCurrency(scope.row.total_cost, scope.row.currency) }}
                                </span>
                                <span class="text-xs text-gray-400">{{ getCatalogTotalLabel(scope.row) }}</span>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Paginación -->
                <div
                    class="flex flex-col sm:flex-row justify-between items-center p-4 bg-gray-50 dark:bg-[#252529] border-t border-gray-100 dark:border-[#2b2b2e]">
                    <div class="text-xs text-gray-500 mb-3 sm:mb-0">
                        Mostrando {{ budgets.from }} a {{ budgets.to }} de {{ budgets.total }} registros
                    </div>
                    <el-pagination v-model:current-page="budgets.current_page" :page-size="budgets.per_page"
                        :total="budgets.total" layout="prev, pager, next" background @current-change="handlePageChange"
                        class="!p-0" />
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