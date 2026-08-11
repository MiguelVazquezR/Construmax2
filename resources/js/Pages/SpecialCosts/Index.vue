<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import { usePermissions } from '@/Composables/usePermissions';
import { ElMessage, ElMessageBox } from 'element-plus';

const { can } = usePermissions();

const props = defineProps({
    catalogs: Object,
    filters: Object,
    canTransfer: Boolean,
    canApprove: Boolean,
    canCreateVersion: Boolean,
});

const search = ref(props.filters.search || '');
const branchFilter = ref(props.filters.branch || '');

const fetchData = debounce(() => {
    router.get(route('special-costs.index'), {
        search: search.value,
        branch: branchFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 300);

watch([search, branchFilter], fetchData);

const formatCurrency = (value, currency = 'MXN') => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: currency,
    }).format(value || 0);
};

const getCatalogStatusColor = (status) => {
    const map = {
        'pending_approval': 'warning',
        'approved': 'success',
    };
    return map[status] || 'info';
};

const handlePageChange = (val) => {
    router.visit(route('special-costs.index', { ...route().params, page: val }), {
        preserveState: true,
        preserveScroll: true,
    });
};

const handleRowClick = (row) => {
    router.visit(route('special-costs.show', row.id));
};

const approveFromList = (row, event) => {
    event.stopPropagation();

    ElMessageBox.confirm(
        'Estas seguro de aprobar este catalogo de costos especiales? Una vez aprobado, el asesor recibira una notificacion y el catalogo desaparecera de esta lista.',
        'Aprobar catalogo especial',
        {
            confirmButtonText: 'Si, aprobar',
            cancelButtonText: 'Cancelar',
            type: 'info',
        }
    ).then(() => {
        router.post(route('special-costs.approve-catalog', row.id), {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                ElMessage.success('Catalogo aprobado correctamente.');
            },
            onError: () => {
                ElMessage.error('Error al aprobar el catalogo.');
            },
        });
    }).catch(() => {});
};
</script>

<template>
    <AppLayout title="Costos especiales">
        <div class="space-y-4">
            <!-- Header -->
            <div
                class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] flex flex-col lg:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Costos especiales</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Catálogos transferidos que requieren autorización de Dirección</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div
                class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] flex flex-col sm:flex-row gap-3">
                <div class="w-full sm:w-80">
                    <el-input v-model="search" placeholder="Buscar proyecto, cliente, folio..." clearable prefix-icon="Search" />
                </div>
                <el-input v-model="branchFilter" placeholder="Filtrar sucursal, unidad, país..." clearable prefix-icon="Search" class="w-full sm:w-64" />
            </div>

            <!-- Info -->
            <el-alert type="warning" :closable="false" show-icon>
                <template #title>
                    <span class="text-sm">Estos catálogos requieren autorización especial de Dirección. Solo se muestran los pendientes de aprobación. Una vez aprobados, desaparecerán de esta lista.</span>
                </template>
            </el-alert>

            <!-- Table -->
            <div
                class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-table :data="catalogs.data" style="width: 100%" stripe @row-click="handleRowClick"
                    row-class-name="cursor-pointer hover:bg-gray-50 dark:hover:bg-[#27272a] transition-colors">

                    <el-table-column label="Folio" width="140">
                        <template #default="scope">
                            <div class="flex flex-col">
                                <Link
                                    v-if="can('tickets.index') && scope.row.ticket_id"
                                    :href="route('tickets.show', scope.row.ticket_id)"
                                    class="font-mono text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 hover:underline"
                                    @click.stop
                                >
                                    {{ scope.row.ticket_folio }}
                                </Link>
                                <span v-else class="font-mono text-xs text-gray-500">{{ scope.row.ticket_folio }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Proyecto" min-width="200">
                        <template #default="scope">
                            <span class="font-medium text-gray-800 dark:text-gray-200 text-sm">{{ scope.row.ticket_name }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Cliente" min-width="160">
                        <template #default="scope">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ scope.row.customer_name }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Sucursal" min-width="140">
                        <template #default="scope">
                            <div class="flex flex-col">
                                <span class="text-sm text-gray-600 dark:text-gray-400">{{ scope.row.branch_name }}</span>
                                <span class="text-xs text-gray-400">{{ scope.row.branch_unit }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Versión" width="80" align="center">
                        <template #default="scope">
                            <el-tag size="small" type="info" effect="plain">v{{ scope.row.version }}</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Estado" width="160" align="center">
                        <template #default="scope">
                            <el-tag :type="getCatalogStatusColor(scope.row.status)" size="small" effect="light" class="w-full text-center">
                                {{ scope.row.status_label }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Total" width="150" align="right">
                        <template #default="scope">
                            <span class="font-mono text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ formatCurrency(scope.row.total) }}
                            </span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Transferido" width="130" align="center">
                        <template #default="scope">
                            <span class="text-xs text-gray-500">
                                {{ new Date(scope.row.created_at).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' }) }}
                            </span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Acciones" width="120" align="center" fixed="right">
                        <template #default="scope">
                            <div class="flex items-center justify-center gap-2">
                                <el-button
                                    v-if="canApprove"
                                    type="success" size="small" plain
                                    @click="approveFromList(scope.row, $event)">
                                    Aprobar
                                </el-button>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Pagination -->
                <div
                    class="flex flex-col sm:flex-row justify-between items-center p-4 bg-gray-50 dark:bg-[#252529] border-t border-gray-100 dark:border-[#2b2b2e]">
                    <div class="text-xs text-gray-500 mb-3 sm:mb-0">
                        Mostrando {{ catalogs.from }} a {{ catalogs.to }} de {{ catalogs.total }} registros
                    </div>
                    <el-pagination v-model:current-page="catalogs.current_page" :page-size="catalogs.per_page"
                        :total="catalogs.total" layout="prev, pager, next" background @current-change="handlePageChange"
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