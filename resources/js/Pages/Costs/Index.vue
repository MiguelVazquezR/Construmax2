<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import { usePermissions } from '@/Composables/usePermissions';
import { ElMessage, ElMessageBox } from 'element-plus';
import { ChatDotSquare } from '@element-plus/icons-vue';

const { can } = usePermissions();

const props = defineProps({
    budgets: Object,
    filters: Object,
});

const normalizeCatalogFilter = (value) => {
    if (!value) return ['pending'];
    if (Array.isArray(value)) return value;
    if (value === 'all') return ['without', 'pending', 'approved'];
    return [value];
};

const search = ref(props.filters.search || '');
const catalogFilter = ref(normalizeCatalogFilter(props.filters.catalog));
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
        'Pendiente de aprobación': 'warning',
    };
    return map[status] || 'info';
};

const getCatalogStatusColor = (status) => {
    const map = {
        'pending_approval': 'warning',
        'approved': 'success',
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

const approveCatalog = (row, event) => {
    event.stopPropagation();

    ElMessageBox.confirm(
        '¿Estás seguro de aprobar este catálogo de costos? Una vez aprobado, el asesor recibirá una notificación.',
        'Aprobar catálogo',
        {
            confirmButtonText: 'Sí, aprobar',
            cancelButtonText: 'Cancelar',
            type: 'info',
        }
    ).then(() => {
        router.post(route('costs.approve-catalog', { budget: row.id, catalog: row.catalog_id }), {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                ElMessage.success('Catálogo de costos aprobado correctamente.');
            },
            onError: () => {
                ElMessage.error('Error al aprobar el catálogo.');
            },
        });
    }).catch(() => {});
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

                    <el-select v-model="catalogFilter" placeholder="Filtro de catálogo" multiple collapse-tags class="lg:!w-1/3">
                        <el-option label="Sin catálogo" value="without" />
                        <el-option label="Pendientes de aprobación" value="pending" />
                        <el-option label="Aprobados" value="approved" />
                    </el-select>
                </div>
            </div>

            <!-- Info: no rejection flow -->
            <el-alert type="info" :closable="false" show-icon class="mx-0">
                <template #title>
                    <span class="text-sm">Si un catálogo requiere ajustes, simplemente crea una nueva versión desde la vista de detalle. El catálogo anterior permanecerá en el historial.</span>
                </template>
            </el-alert>

            <!-- Tabla de presupuestos para catálogos -->
            <div
                class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-alert type="info" :closable="false" show-icon class="m-4" v-if="catalogFilter.length === 0 || catalogFilter.includes('all')">
                    <template #title>
                        Mostrando todos los presupuestos. Usa los filtros "Sin catálogo" o "Pendientes de aprobación" para ver solo los que requieren atención.
                    </template>
                </el-alert>
                <el-alert type="info" :closable="false" show-icon class="m-4" v-else>
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
                                <div class="flex items-center gap-1.5">
                                    <Link
                                        v-if="can('tickets.index') && scope.row.ticket_id"
                                        :href="route('tickets.show', scope.row.ticket_id)"
                                        class="font-mono text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 hover:underline"
                                        @click.stop
                                    >
                                        Folio Ticket: {{ scope.row.ticket_folio }}
                                    </Link>
                                    <span v-else class="font-mono text-xs text-gray-500">Folio Ticket: {{ scope.row.ticket_folio }}</span>
                                    <el-tooltip
                                        v-if="scope.row.ticket_important_note"
                                        :content="scope.row.ticket_important_note"
                                        placement="top"
                                        :show-after="300"
                                    >
                                        <el-icon :size="14" color="#e6a23c" class="shrink-0">
                                            <ChatDotSquare />
                                        </el-icon>
                                    </el-tooltip>
                                </div>
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

                    <el-table-column label="Estado ticket" width="160" align="center">
                        <template #default="scope">
                            <el-tag :type="getStatusColor(scope.row.status)" size="small" effect="light"
                                class="w-full text-center">
                                {{ scope.row.status }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Catálogo" width="200" align="center">
                        <template #default="scope">
                            <div v-if="scope.row.has_catalog" class="flex flex-col items-center gap-1">
                                <el-tag :type="getCatalogStatusColor(scope.row.catalog_status)" size="small" effect="light">
                                    {{ scope.row.catalog_status_label }}
                                </el-tag>
                                <span class="text-[10px] text-gray-400">v{{ scope.row.latest_version }}</span>
                                <span v-if="scope.row.catalog_approved_by" class="text-[10px] text-gray-400">
                                    por {{ scope.row.catalog_approved_by }}
                                </span>
                            </div>
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

                    <el-table-column label="Acciones" width="120" align="center" fixed="right">
                        <template #default="scope">
                            <div class="flex items-center justify-center gap-2">
                                <el-button
                                    v-if="scope.row.has_catalog && scope.row.catalog_status === 'pending_approval' && can('costs.approve')"
                                    type="success" size="small" plain
                                    @click="approveCatalog(scope.row, $event)">
                                    Aprobar
                                </el-button>
                                <span v-else-if="scope.row.has_catalog && scope.row.catalog_status === 'approved'"
                                    class="text-xs text-green-600 font-medium">
                                    ✓ Aprobado
                                </span>
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