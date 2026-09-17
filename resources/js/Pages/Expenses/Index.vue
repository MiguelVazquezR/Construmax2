<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Check,
    CreditCard,
    Delete,
    DocumentChecked,
    Edit,
    Money,
    MoreFilled,
    Promotion,
    View,
} from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';
import ExpenseSummaryCards from './Partials/ExpenseSummaryCards.vue';
import ExpenseFormDialog from './Partials/ExpenseFormDialog.vue';

const { can } = usePermissions();

const props = defineProps({
    expenses: Object,
    stats: Object,
    categories: Array,
    filters: Object,
});

const search = ref(props.filters.search || '');
const categoryFilter = ref(props.filters.category_id ? Number(props.filters.category_id) : null);
const statusFilter = ref(props.filters.status || '');
const dateRange = ref(
    props.filters.from && props.filters.to
        ? [props.filters.from, props.filters.to]
        : []
);

const statusOptions = [
    { value: 'pending', label: 'Pendiente de pago' },
    { value: 'paid', label: 'Pagado' },
    { value: 'cancelled', label: 'Cancelado' },
];

const showFormDialog = ref(false);
const editingExpense = ref(null);

const buildParams = () => ({
    search: search.value || undefined,
    category_id: categoryFilter.value || undefined,
    status: statusFilter.value || undefined,
    from: dateRange.value?.[0] || undefined,
    to: dateRange.value?.[1] || undefined,
});

const refreshData = () => {
    router.get(route('expenses.index'), buildParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const fetchData = debounce(refreshData, 300);

watch([search, categoryFilter, statusFilter, dateRange], fetchData);

const clearFilters = () => {
    search.value = '';
    categoryFilter.value = null;
    statusFilter.value = '';
    dateRange.value = [];
};

const openCreateDialog = () => {
    editingExpense.value = null;
    showFormDialog.value = true;
};

const openEditDialog = (expense) => {
    editingExpense.value = expense;
    showFormDialog.value = true;
};

const onFormSaved = () => {
    showFormDialog.value = false;
};

const onCategoriesChanged = () => {
    router.reload({
        only: ['categories', 'expenses', 'stats'],
        preserveScroll: true,
        preserveState: true,
    });
};

const markPaid = (expense) => {
    ElMessageBox.confirm(
        `¿Confirmas que el gasto "${expense.concept}" ya fue pagado?`,
        'Marcar como pagado',
        {
            confirmButtonText: 'Sí, marcar pagado',
            cancelButtonText: 'Cancelar',
            type: 'info',
        }
    ).then(() => {
        router.post(route('expenses.mark-paid', expense.id), {}, {
            preserveScroll: true,
            onSuccess: () => ElMessage.success('Gasto marcado como pagado.'),
        });
    }).catch(() => {});
};

const destroyExpense = (expense) => {
    ElMessageBox.confirm(
        `Se eliminará el gasto "${expense.concept}". Esta acción no se puede deshacer.`,
        'Eliminar gasto',
        {
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            type: 'warning',
        }
    ).then(() => {
        router.delete(route('expenses.destroy', expense.id), {
            preserveScroll: true,
            onSuccess: () => ElMessage.success('Gasto eliminado correctamente.'),
        });
    }).catch(() => {});
};

const handlePageChange = (page) => {
    router.get(route('expenses.index'), { ...buildParams(), page }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(value || 0);
};

const formatDate = (value) => {
    if (!value) return '—';

    const [year, month, day] = value.split('-');

    return new Date(year, month - 1, day).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const statusTagType = (status) => {
    const map = {
        pending: 'warning',
        paid: 'success',
        cancelled: 'danger',
    };

    return map[status] || 'info';
};

// --- Payment method icons (shown next to the amount) ---
const paymentMethodIcons = {
    cash: Money,
    transfer: Promotion,
    card: CreditCard,
    check: DocumentChecked,
    other: MoreFilled,
};

const paymentMethodColors = {
    cash: 'text-green-600',
    transfer: 'text-blue-600',
    card: 'text-violet-500',
    check: 'text-amber-500',
    other: 'text-gray-400',
};

const isCategoryTruncated = (name) => (name || '').length > 16;

// --- Row actions (dropdown) ---
const handleRowCommand = (command, expense) => {
    if (command === 'mark-paid') {
        markPaid(expense);
    } else if (command === 'edit') {
        openEditDialog(expense);
    } else if (command === 'delete') {
        destroyExpense(expense);
    }
};
</script>

<template>
    <AppLayout title="Control de gastos">
        <div class="space-y-4">
            <!-- Header -->
            <div
                class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] flex flex-col lg:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Control de gastos</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Registro y seguimiento de los gastos de la empresa</p>
                    </div>
                </div>

                <el-button v-if="can('expenses.create')" type="primary" @click="openCreateDialog">
                    Registrar gasto
                </el-button>
            </div>

            <!-- Summary -->
            <ExpenseSummaryCards :stats="stats" />

            <!-- Filters -->
            <div
                class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] flex flex-col lg:flex-row flex-wrap gap-3">
                <div class="w-full lg:w-72">
                    <el-input v-model="search" placeholder="Buscar folio, concepto o referencia..." clearable prefix-icon="Search" />
                </div>

                <el-select v-model="categoryFilter" placeholder="Categoría" clearable filterable class="w-full lg:!w-52">
                    <el-option
                        v-for="category in categories"
                        :key="category.id"
                        :label="category.name"
                        :value="category.id"
                    />
                </el-select>

                <el-select v-model="statusFilter" placeholder="Estatus" clearable class="w-full lg:!w-40">
                    <el-option
                        v-for="option in statusOptions"
                        :key="option.value"
                        :label="option.label"
                        :value="option.value"
                    />
                </el-select>

                <el-date-picker
                    v-model="dateRange"
                    type="daterange"
                    value-format="YYYY-MM-DD"
                    range-separator="a"
                    start-placeholder="Desde"
                    end-placeholder="Hasta"
                    class="w-full lg:!w-72"
                />

                <el-button text @click="clearFilters">Limpiar filtros</el-button>
            </div>

            <!-- Table -->
            <div
                class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-table :data="expenses.data" style="width: 100%" stripe>
                    <el-table-column label="Folio" width="80">
                        <template #default="scope">
                            <span class="font-mono text-xs text-gray-600 dark:text-gray-400">{{ scope.row.folio }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Fecha" width="110">
                        <template #default="scope">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ formatDate(scope.row.expense_date) }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Concepto" min-width="220" show-overflow-tooltip>
                        <template #default="scope">
                            <div class="flex flex-col">
                                <span class="font-medium text-gray-800 dark:text-gray-200 text-sm">{{ scope.row.concept }}</span>
                                <span v-if="scope.row.reference" class="text-xs text-gray-400">Ref: {{ scope.row.reference }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Categoría" width="150">
                        <template #default="scope">
                            <el-tooltip
                                v-if="scope.row.category_name"
                                :content="scope.row.category_name"
                                placement="top"
                                :disabled="!isCategoryTruncated(scope.row.category_name)"
                            >
                                <span class="block w-full truncate text-sm text-gray-600 dark:text-gray-400">
                                    {{ scope.row.category_name }}
                                </span>
                            </el-tooltip>
                            <span v-else class="text-xs text-gray-400">Sin categoría</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Ticket" width="140">
                        <template #default="scope">
                            <Link
                                v-if="scope.row.ticket_id && can('tickets.index')"
                                :href="route('tickets.show', scope.row.ticket_id)"
                                class="font-mono text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 hover:underline"
                            >
                                {{ scope.row.ticket_folio }}
                            </Link>
                            <span v-else-if="scope.row.ticket_id" class="font-mono text-xs text-gray-500">{{ scope.row.ticket_folio }}</span>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Monto" width="150" align="right">
                        <template #default="scope">
                            <div class="flex items-center justify-end gap-2">
                                <el-tooltip
                                    v-if="scope.row.payment_method"
                                    :content="scope.row.payment_method_label"
                                    placement="top"
                                >
                                    <el-icon :class="paymentMethodColors[scope.row.payment_method] || 'text-gray-400'">
                                        <component :is="paymentMethodIcons[scope.row.payment_method]" />
                                    </el-icon>
                                </el-tooltip>

                                <el-tooltip v-if="scope.row.receipt_url" content="Ver comprobante" placement="top">
                                    <a
                                        :href="scope.row.receipt_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-blue-500 hover:text-blue-700 dark:text-blue-400 shrink-0"
                                    >
                                        <el-icon><View /></el-icon>
                                    </a>
                                </el-tooltip>

                                <span class="font-mono text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ formatCurrency(scope.row.amount) }}
                                </span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Estatus" width="120" align="center">
                        <template #default="scope">
                            <el-tag :type="statusTagType(scope.row.status)" size="small" effect="light">
                                {{ scope.row.status_label }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Registró" min-width="140">
                        <template #default="scope">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ scope.row.created_by }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Acciones" width="90" align="center" fixed="right">
                        <template #default="scope">
                            <el-dropdown
                                v-if="can('expenses.edit') || can('expenses.delete')"
                                trigger="click"
                                @command="(command) => handleRowCommand(command, scope.row)"
                            >
                                <el-button link :icon="MoreFilled" />
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item
                                            v-if="scope.row.status === 'pending' && can('expenses.edit')"
                                            command="mark-paid"
                                            :icon="Check"
                                        >
                                            Marcar como pagado
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="can('expenses.edit')" command="edit" :icon="Edit">
                                            Editar
                                        </el-dropdown-item>
                                        <el-dropdown-item
                                            v-if="can('expenses.delete')"
                                            command="delete"
                                            :icon="Delete"
                                            divided
                                        >
                                            Eliminar
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </template>
                    </el-table-column>

                    <template #empty>
                        <el-empty :image-size="90" description="No hay gastos registrados con los filtros seleccionados." />
                    </template>
                </el-table>

                <div v-if="expenses.total > 0" class="flex justify-end p-4 border-t border-gray-100 dark:border-[#2b2b2e]">
                    <el-pagination
                        background
                        layout="total, prev, pager, next"
                        :total="expenses.total"
                        :page-size="expenses.per_page"
                        :current-page="expenses.current_page"
                        @current-change="handlePageChange"
                    />
                </div>
            </div>
        </div>

        <!-- Expense form dialog -->
        <ExpenseFormDialog
            v-if="showFormDialog"
            v-model="showFormDialog"
            :expense="editingExpense"
            :categories="categories"
            @saved="onFormSaved"
            @categories-changed="onCategoriesChanged"
        />
    </AppLayout>
</template>
