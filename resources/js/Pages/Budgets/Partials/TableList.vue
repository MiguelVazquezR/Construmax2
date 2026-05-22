<script setup>
import { router, Link } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    budgets: Object,
});

// Helpers para formato
const formatCurrency = (value, currency = 'MXN') => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: currency,
    }).format(value || 0);
};

const getStatusColor = (status) => {
    const map = {
        'Borrador': 'info',
        'Presupuesto enviado': 'primary',
        'Facturado': 'warning',
        'Trabajo en proceso': 'primary',
        'Trabajo terminado': 'success',
        'Pagado': 'success',
        'Perdido': 'danger'
    };
    return map[status] || 'info';
};

const handlePageChange = (val) => {
    router.visit(route('budgets.index', { ...route().params, page: val }), {
        preserveState: true,
        preserveScroll: true,
    });
};

const handleRowClick = (row) => {
    router.visit(route('budgets.show', row.id));
};

const deleteBudget = (budget) => {
    ElMessageBox.confirm(
        `¿Eliminar el presupuesto del proyecto "${budget.ticket?.name}"?`,
        'Confirmar eliminación',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'error' }
    ).then(() => {
        router.delete(route('budgets.destroy', budget.id), {
            onSuccess: () => ElMessage.success('Presupuesto eliminado')
        });
    }).catch(() => {});
};
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
        
        <!-- Tabla -->
        <div class="hidden md:block">
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
                
                <el-table-column label="Proyecto / Servicio" min-width="200">
                    <template #default="scope">
                        <div>
                            <p class="font-bold text-gray-800 dark:text-gray-200 text-sm">{{ scope.row.ticket?.name }}</p>
                            <p class="text-xs text-gray-500">{{ scope.row.ticket?.service_type }}</p>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Cliente" min-width="180">
                    <template #default="scope">
                        <div class="flex items-center gap-2">
                            <el-icon class="text-gray-400"><OfficeBuilding /></el-icon>
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ scope.row.ticket?.customer?.name }}</span>
                        </div>
                    </template>
                </el-table-column>

                <!-- NUEVA COLUMNA: RESPONSABLE -->
                <el-table-column label="Responsable" width="160">
                    <template #default="scope">
                        <div class="flex items-center gap-2">
                            <el-avatar 
                                :size="24" 
                                :src="scope.row.responsible?.profile_photo_url"
                                class="bg-gray-200 text-gray-600 text-xs"
                            >
                                {{ scope.row.responsible?.name?.charAt(0) }}
                            </el-avatar>
                            <span class="text-xs text-gray-600 dark:text-gray-400 truncate">
                                {{ scope.row.responsible?.name }}
                            </span>
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

                <el-table-column label="Total costo" width="160" align="right">
                    <template #default="scope">
                         <div class="flex flex-col items-end leading-tight">
                            <span class="font-mono text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ formatCurrency(scope.row.concepts_sum_amount || 0, scope.row.currency) }}
                            </span>
                            <!-- Conversión a MXN si es USD -->
                            <span v-if="scope.row.currency === 'USD'" class="text-[10px] text-gray-400">
                                ≈ {{ formatCurrency((scope.row.concepts_sum_amount || 0) * scope.row.exchange_rate, 'MXN') }}
                            </span>
                         </div>
                    </template>
                </el-table-column>

                <el-table-column label="Acciones" width="90" align="center" fixed="right">
                    <template #default="scope">
                        <div @click.stop>
                            <el-dropdown trigger="click">
                                <span class="el-dropdown-link cursor-pointer p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full transition display-inline-block">
                                    <el-icon><MoreFilled /></el-icon>
                                </span>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <Link :href="route('budgets.show', scope.row.id)">
                                            <el-dropdown-item icon="View">Ver detalles</el-dropdown-item>
                                        </Link>
                                        <Link v-if="can('budgets.edit')" :href="route('budgets.edit', scope.row.id)">
                                            <el-dropdown-item icon="Edit">Editar</el-dropdown-item>
                                        </Link>
                                        <el-dropdown-item v-if="can('budgets.delete')" divided icon="Delete" class="text-red-500" @click="deleteBudget(scope.row)">
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

        <!-- Vista Móvil -->
        <div class="md:hidden">
            <div v-for="item in budgets.data" :key="item.id" class="p-4 border-b border-gray-100 dark:border-[#2b2b2e] last:border-0 hover:bg-gray-50 dark:hover:bg-[#252529] cursor-pointer" @click="handleRowClick(item)">
                <div class="flex justify-between items-start mb-2">
                    <span class="font-mono text-xs text-gray-400">#{{ item.id }}</span>
                    <el-tag :type="getStatusColor(item.status)" size="small">{{ item.status }}</el-tag>
                </div>
                <h3 class="font-bold text-gray-800 dark:text-gray-200 text-sm mb-1">{{ item.ticket?.name }}</h3>
                
                <div class="flex flex-col gap-1 mb-3">
                    <p class="text-xs text-gray-500 flex items-center gap-1">
                        <el-icon><OfficeBuilding /></el-icon> {{ item.ticket?.customer?.name }}
                    </p>
                    <p class="text-xs text-gray-500 flex items-center gap-1">
                        <el-icon><User /></el-icon> {{ item.responsible?.name }}
                    </p>
                </div>
                
                <div class="flex justify-between items-center mb-2 bg-gray-50 dark:bg-[#27272a] p-2 rounded">
                    <span class="text-xs text-gray-400">Costo total:</span>
                    <div class="text-right">
                        <div class="font-mono text-sm font-semibold text-gray-700 dark:text-gray-300">
                            {{ formatCurrency(item.concepts_sum_amount || 0, item.currency) }}
                        </div>
                        <div v-if="item.currency === 'USD'" class="text-[10px] text-gray-400">
                            ≈ {{ formatCurrency((item.concepts_sum_amount || 0) * item.exchange_rate, 'MXN') }}
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-2 border-t border-gray-50 dark:border-gray-800">
                    <span class="text-xs text-gray-400">{{ item.ticket?.service_type }}</span>
                    <div class="flex gap-2" @click.stop>
                         <Link v-if="can('budgets.edit')" :href="route('budgets.edit', item.id)">
                            <el-button size="small" icon="Edit" circle />
                        </Link>
                        <el-button v-if="can('budgets.delete')" size="small" type="danger" icon="Delete" circle @click="deleteBudget(item)" />
                    </div>
                </div>
            </div>
        </div>

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
</template>

<style scoped>
:deep(.el-table .cursor-pointer) {
    cursor: pointer;
}
</style>