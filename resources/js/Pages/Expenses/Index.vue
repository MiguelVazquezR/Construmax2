<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Check,
    CircleCheck,
    CreditCard,
    Delete,
    Document,
    DocumentChecked,
    Download,
    Edit,
    Money,
    MoreFilled,
    Promotion,
} from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useCostType } from '@/Composables/useCostType';
import ExpenseSummaryCards from './Partials/ExpenseSummaryCards.vue';
import ExpenseFormDialog from './Partials/ExpenseFormDialog.vue';
import ExpenseTypeDialog from './Partials/ExpenseTypeDialog.vue';
import BudgetPickerDialog from './Partials/BudgetPickerDialog.vue';
import CompleteDepositDialog from './Partials/CompleteDepositDialog.vue';

const { can } = usePermissions();
const { costTypeLabel, costTypeTagType } = useCostType();

const props = defineProps({
    expenses: Object,
    stats: Object,
    categories: Array,
    budgets: Array,
    filters: Object,
});

const search = ref(props.filters.search || '');
const folioFilter = ref(props.filters.folio || '');
const categoryFilter = ref(props.filters.category_id ? Number(props.filters.category_id) : null);
const statusFilter = ref(props.filters.status || '');
const paymentMethodFilter = ref(props.filters.payment_method || '');
const typeFilter = ref(props.filters.type || '');
const budgetFilter = ref(props.filters.budget_id ? Number(props.filters.budget_id) : null);
const sortBy = ref(props.filters.sort_by || '');
const sortDir = ref(props.filters.sort_dir || '');
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

const typeOptions = [
    { value: 'general', label: 'Gasto general' },
    { value: 'budget', label: 'Gasto de presupuesto' },
    { value: 'commission', label: 'Comisiones' },
    { value: 'deposit', label: 'Depósitos' },
];

const paymentMethodOptions = [
    { value: 'cash', label: 'Efectivo' },
    { value: 'transfer', label: 'Transferencia' },
    { value: 'card', label: 'Tarjeta' },
    { value: 'check', label: 'Cheque' },
    { value: 'other', label: 'Otro' },
];

const showTypeDialog = ref(false);
const showBudgetPicker = ref(false);
const showFormDialog = ref(false);
const editingExpense = ref(null);
const formBudget = ref(null);

const buildParams = () => ({
    search: search.value || undefined,
    folio: folioFilter.value || undefined,
    category_id: categoryFilter.value || undefined,
    status: statusFilter.value || undefined,
    payment_method: paymentMethodFilter.value || undefined,
    type: typeFilter.value || undefined,
    budget_id: budgetFilter.value || undefined,
    from: dateRange.value?.[0] || undefined,
    to: dateRange.value?.[1] || undefined,
    sort_by: sortBy.value || undefined,
    sort_dir: sortDir.value || undefined,
});

const refreshData = () => {
    router.get(route('expenses.index'), buildParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const fetchData = debounce(refreshData, 300);

watch([search, folioFilter, categoryFilter, statusFilter, paymentMethodFilter, typeFilter, budgetFilter, dateRange], fetchData);

const clearFilters = () => {
    search.value = '';
    folioFilter.value = '';
    categoryFilter.value = null;
    statusFilter.value = '';
    paymentMethodFilter.value = '';
    typeFilter.value = '';
    budgetFilter.value = null;
    dateRange.value = [];
};

const openCreateDialog = () => {
    showTypeDialog.value = true;
};

const onTypeSelected = (type) => {
    if (type === 'budget') {
        showBudgetPicker.value = true;

        return;
    }

    editingExpense.value = null;
    formBudget.value = null;
    showFormDialog.value = true;
};

const openEditDialog = (expense) => {
    editingExpense.value = expense;
    formBudget.value = null;
    showFormDialog.value = true;
};

// --- Complete deposit (mirror expenses) ---
const showCompleteDepositDialog = ref(false);
const completingExpense = ref(null);

const openCompleteDepositDialog = (expense) => {
    completingExpense.value = expense;
    showCompleteDepositDialog.value = true;
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

const downloadReport = () => {
    const params = Object.fromEntries(
        Object.entries(buildParams()).filter(([, value]) => value !== undefined && value !== '')
    );

    window.location.href = route('expenses.export', params);
};

const handleSortChange = ({ prop, order }) => {
    if (!order) {
        sortBy.value = '';
        sortDir.value = '';
    } else {
        sortBy.value = prop;
        sortDir.value = order === 'ascending' ? 'asc' : 'desc';
    }

    refreshData();
};

const budgetOptionLabel = (budget) => {
    const label = [budget.folio, budget.name].filter(Boolean).join(' — ');

    return budget.customer_name ? `${label} (${budget.customer_name})` : label;
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

const isTextTruncated = (value) => (value || '').length > 16;

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

                <div class="flex items-center gap-2">
                    <el-button @click="downloadReport">
                        <el-icon class="mr-1"><Download /></el-icon>
                        Descargar reporte
                    </el-button>

                    <el-button v-if="can('expenses.create')" type="primary" @click="openCreateDialog">
                        Registrar gasto
                    </el-button>
                </div>
            </div>

            <!-- Summary -->
            <ExpenseSummaryCards :stats="stats" />

            <!-- Filters -->
            <div
                class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] flex flex-col lg:flex-row flex-wrap gap-3">
                <el-input v-model="search" placeholder="Buscar concepto o referencia..." clearable prefix-icon="Search" class="w-full lg:!w-64" />

                <el-input v-model="folioFilter" placeholder="Folio (GAS-0001)" clearable prefix-icon="Search" class="w-full lg:!w-44" />

                <el-select v-model="categoryFilter" placeholder="Categoría" clearable filterable class="w-full lg:!w-48">
                    <el-option
                        v-for="category in categories"
                        :key="category.id"
                        :label="category.name"
                        :value="category.id"
                    />
                </el-select>

                <el-select v-model="statusFilter" placeholder="Estatus" clearable class="w-full lg:!w-44">
                    <el-option
                        v-for="option in statusOptions"
                        :key="option.value"
                        :label="option.label"
                        :value="option.value"
                    />
                </el-select>

                <el-select v-model="paymentMethodFilter" placeholder="Método de pago" clearable class="w-full lg:!w-44">
                    <el-option
                        v-for="option in paymentMethodOptions"
                        :key="option.value"
                        :label="option.label"
                        :value="option.value"
                    />
                </el-select>

                <el-select v-model="typeFilter" placeholder="Tipo de gasto" clearable class="w-full lg:!w-44">
                    <el-option
                        v-for="option in typeOptions"
                        :key="option.value"
                        :label="option.label"
                        :value="option.value"
                    />
                </el-select>

                <el-select v-model="budgetFilter" placeholder="Presupuesto" clearable filterable class="w-full lg:!w-64">
                    <el-option
                        v-for="budget in budgets"
                        :key="budget.id"
                        :label="budgetOptionLabel(budget)"
                        :value="budget.id"
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
                <el-table :data="expenses.data" style="width: 100%" stripe @sort-change="handleSortChange">
                    <el-table-column label="Folio" prop="folio" width="80" sortable="custom">
                        <template #default="scope">
                            <span class="font-mono text-xs text-gray-600 dark:text-gray-400">{{ scope.row.folio }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Fecha" prop="expense_date" width="110" sortable="custom">
                        <template #default="scope">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ formatDate(scope.row.expense_date) }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Concepto" min-width="220" show-overflow-tooltip>
                        <template #default="scope">
                            <div class="flex flex-col">
                                <div class="flex items-center gap-1">
                                    <span class="font-medium text-gray-800 dark:text-gray-200 text-sm">{{ scope.row.concept }}</span>
                                    <el-tag v-if="scope.row.is_commission" type="warning" size="small" effect="plain">Comisión</el-tag>
                                    <el-tag v-if="scope.row.deposit_id" type="info" size="small" effect="plain">Depósito</el-tag>
                                </div>
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
                                :disabled="!isTextTruncated(scope.row.category_name)"
                            >
                                <span class="block w-full truncate text-sm text-gray-600 dark:text-gray-400">
                                    {{ scope.row.category_name }}
                                </span>
                            </el-tooltip>
                            <el-tooltip
                                v-else-if="costTypeLabel(scope.row.budget_concept_type)"
                                content="Tipo de costo del concepto del presupuesto"
                                placement="top"
                            >
                                <el-tag :type="costTypeTagType(scope.row.budget_concept_type)" size="small" effect="plain">
                                    {{ costTypeLabel(scope.row.budget_concept_type) }}
                                </el-tag>
                            </el-tooltip>
                            <span v-else class="text-xs text-gray-400">Sin categoría</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Presupuesto" prop="budget_id" width="110" sortable="custom">
                        <template #default="scope">
                            <Link
                                v-if="scope.row.budget_id"
                                :href="route('expenses.budgets.show', scope.row.budget_id)"
                                class="font-mono text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 hover:underline"
                            >
                                {{ scope.row.budget_folio }}
                            </Link>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Monto" width="150" align="right">
                        <template #default="scope">
                            <div class="flex flex-col items-end">
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

                                    <span class="font-mono text-sm font-semibold text-gray-700 dark:text-gray-300">
                                        {{ formatCurrency(scope.row.amount) }}
                                    </span>
                                </div>
                                <span v-if="scope.row.commission_amount > 0" class="text-xs text-amber-600">
                                    + {{ formatCurrency(scope.row.commission_amount) }} comisión
                                </span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Estatus" prop="status" width="150" align="center" sortable="custom">
                        <template #default="scope">
                            <el-tag :type="statusTagType(scope.row.status)" size="small" effect="light">
                                {{ scope.row.status_label }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Registró" width="140">
                        <template #default="scope">
                            <el-tooltip
                                v-if="scope.row.created_by"
                                :content="scope.row.created_by"
                                placement="top"
                                :disabled="!isTextTruncated(scope.row.created_by)"
                            >
                                <span class="block w-full truncate text-sm text-gray-600 dark:text-gray-400">
                                    {{ scope.row.created_by }}
                                </span>
                            </el-tooltip>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Acciones" width="170" align="center" fixed="right">
                        <template #default="scope">
                            <div class="flex items-center justify-center gap-2">
                                <el-button
                                    v-if="scope.row.deposit_id && scope.row.status === 'pending' && can('expenses.edit')"
                                    size="small"
                                    type="primary"
                                    :icon="CircleCheck"
                                    @click="openCompleteDepositDialog(scope.row)"
                                >
                                    Realizado
                                </el-button>

                                <span class="inline-flex w-4 justify-center">
                                    <el-tooltip
                                        v-if="scope.row.receipt_url"
                                        :content="(scope.row.receipts?.length || 1) > 1 ? `${scope.row.receipts.length} comprobantes` : 'Ver comprobante'"
                                        placement="top"
                                    >
                                        <a
                                            :href="scope.row.receipt_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-blue-500 hover:text-blue-700 dark:text-blue-400"
                                        >
                                            <el-icon><Document /></el-icon>
                                        </a>
                                    </el-tooltip>
                                </span>

                                <el-dropdown
                                    v-if="can('expenses.edit') || (can('expenses.delete') && !scope.row.deposit_id)"
                                    trigger="click"
                                    @command="(command) => handleRowCommand(command, scope.row)"
                                >
                                    <el-button link :icon="MoreFilled" />
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item
                                                v-if="scope.row.status === 'pending' && !scope.row.deposit_id && can('expenses.edit')"
                                                command="mark-paid"
                                                :icon="Check"
                                            >
                                                Marcar como pagado
                                            </el-dropdown-item>
                                            <el-dropdown-item v-if="can('expenses.edit')" command="edit" :icon="Edit">
                                                Editar
                                            </el-dropdown-item>
                                            <el-dropdown-item
                                                v-if="can('expenses.delete') && !scope.row.deposit_id"
                                                command="delete"
                                                :icon="Delete"
                                                divided
                                            >
                                                Eliminar
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                            </div>
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

        <!-- Expense type picker -->
        <ExpenseTypeDialog
            v-if="showTypeDialog"
            v-model="showTypeDialog"
            @select="onTypeSelected"
        />

        <!-- Budget picker -->
        <BudgetPickerDialog v-if="showBudgetPicker" v-model="showBudgetPicker" />

        <!-- Complete deposit dialog -->
        <CompleteDepositDialog
            v-if="showCompleteDepositDialog && completingExpense"
            v-model="showCompleteDepositDialog"
            :expense="completingExpense"
        />

        <!-- Expense form dialog -->
        <ExpenseFormDialog
            v-if="showFormDialog"
            v-model="showFormDialog"
            :expense="editingExpense"
            :categories="categories"
            :budget="formBudget"
            @saved="onFormSaved"
            @categories-changed="onCategoriesChanged"
        />
    </AppLayout>
</template>
