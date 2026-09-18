<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Check,
    CircleCheck,
    Clock,
    Delete,
    Document,
    Edit,
    MoreFilled,
    Money,
    Tickets,
    Wallet,
} from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useCostType } from '@/Composables/useCostType';
import ExpenseFormDialog from './Partials/ExpenseFormDialog.vue';
import BudgetConceptPaymentDialog from './Partials/BudgetConceptPaymentDialog.vue';
import CompleteDepositDialog from './Partials/CompleteDepositDialog.vue';

const props = defineProps({
    budget: Object,
    categories: Array,
});

const { can } = usePermissions();
const { costTypeLabel, costTypeTagType } = useCostType();

const isCancelled = computed(() => props.budget.status === 'Cancelado');

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: props.budget.currency || 'MXN',
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
        'Borrador': 'info',
        'Por programar': 'info',
        'Programado': 'warning',
        'Levantamiento': 'warning',
        'Catálogo': 'primary',
        'Pendiente de aprobación': 'warning',
        'Proceso de ejecución': 'primary',
        'Ejecutado': 'success',
        'Finalizado': 'success',
        'Facturado': 'success',
        'Pagado': 'success',
        'Cancelado': 'danger',
    };

    return map[status] || 'info';
};

// --- Breakdown concepts ---
const paymentConcept = ref(null);
const showPaymentDialog = ref(false);

const openPaymentDialog = (concept) => {
    paymentConcept.value = concept;
    showPaymentDialog.value = true;
};

const conceptState = (concept) => {
    if (!concept.expense) {
        return { label: 'Pendiente', type: 'info' };
    }

    if (concept.expense.status === 'paid') {
        return { label: 'Pagado', type: 'success' };
    }

    if (concept.expense.status === 'cancelled') {
        return { label: 'Cancelado', type: 'danger' };
    }

    return { label: 'Pendiente de pago', type: 'warning' };
};

const conceptPaymentDate = (concept) => concept.expense?.expense_date || concept.payment_date;

// --- Extra expenses and commissions ---
const showExtraDialog = ref(false);
const editingExtra = ref(null);

const showCompleteDepositDialog = ref(false);
const completingDepositExpense = ref(null);

const openCompleteDepositDialog = (expense) => {
    completingDepositExpense.value = expense;
    showCompleteDepositDialog.value = true;
};

const openExtraDialog = () => {
    editingExtra.value = null;
    showExtraDialog.value = true;
};

const openEditExtra = (extra) => {
    editingExtra.value = extra;
    showExtraDialog.value = true;
};

const expenseStatusTagType = (status) => {
    const map = {
        pending: 'warning',
        paid: 'success',
        cancelled: 'danger',
    };

    return map[status] || 'info';
};

const markExtraPaid = (extra) => {
    ElMessageBox.confirm(
        `¿Confirmas que el gasto "${extra.concept}" ya fue pagado?`,
        'Marcar como pagado',
        {
            confirmButtonText: 'Sí, marcar pagado',
            cancelButtonText: 'Cancelar',
            type: 'info',
        }
    ).then(() => {
        router.post(route('expenses.mark-paid', extra.id), {}, {
            preserveScroll: true,
            onSuccess: () => ElMessage.success('Gasto marcado como pagado.'),
        });
    }).catch(() => {});
};

const destroyExtra = (extra) => {
    ElMessageBox.confirm(
        `Se eliminará el gasto "${extra.concept}". Esta acción no se puede deshacer.`,
        'Eliminar gasto',
        {
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            type: 'warning',
        }
    ).then(() => {
        router.delete(route('expenses.destroy', extra.id), {
            preserveScroll: true,
            onSuccess: () => ElMessage.success('Gasto eliminado correctamente.'),
        });
    }).catch(() => {});
};

const handleExtraCommand = (command, extra) => {
    if (command === 'mark-paid') {
        markExtraPaid(extra);
    } else if (command === 'complete-deposit') {
        openCompleteDepositDialog(extra);
    } else if (command === 'edit') {
        openEditExtra(extra);
    } else if (command === 'delete') {
        destroyExtra(extra);
    }
};
</script>

<template>
    <AppLayout title="Gastos del presupuesto">
        <div class="space-y-4">
            <!-- Header -->
            <div
                class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] flex flex-col lg:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-3">
                    <Link :href="route('expenses.index')">
                        <el-button circle icon="Back" title="Volver a gastos" aria-label="Volver a gastos" />
                    </Link>
                    <div>
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Gastos del presupuesto</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ budget.folio }} — {{ budget.name }}
                            <span v-if="budget.customer_name">· {{ budget.customer_name }}</span>
                        </p>
                    </div>
                </div>

                <el-tag :type="statusTagType(budget.status)" effect="plain" size="default">
                    {{ budget.status }}
                </el-tag>
            </div>

            <el-alert
                v-if="isCancelled"
                type="warning"
                :closable="false"
                show-icon
                title="Este presupuesto está cancelado"
                description="Solo puedes consultar los gastos registrados. Para registrar pagos, reactiva el presupuesto desde el ticket."
            />

            <!-- Summary -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-[#1e1e20] dark:to-[#252529] rounded-lg shadow-sm border border-gray-200 dark:border-[#2b2b2e] p-5">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="font-bold text-gray-600 dark:text-gray-300 text-sm uppercase">Desglose</h4>
                        <el-icon class="text-primary"><Money /></el-icon>
                    </div>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ formatCurrency(budget.totals.concepts) }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ budget.concepts.length }} conceptos en el presupuesto</p>
                </div>

                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-[#1e1e20] dark:to-[#252529] rounded-lg shadow-sm border border-gray-200 dark:border-[#2b2b2e] p-5">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="font-bold text-gray-600 dark:text-gray-300 text-sm uppercase">Adicionales</h4>
                        <el-icon class="text-blue-500"><Wallet /></el-icon>
                    </div>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ formatCurrency(budget.totals.extras) }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ budget.extras.length }} gastos adicionales y comisiones</p>
                </div>

                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-[#1e1e20] dark:to-[#252529] rounded-lg shadow-sm border border-gray-200 dark:border-[#2b2b2e] p-5">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="font-bold text-gray-600 dark:text-gray-300 text-sm uppercase">Pagado</h4>
                        <el-icon class="text-green-500"><CircleCheck /></el-icon>
                    </div>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ formatCurrency(budget.totals.paid) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Pagos registrados en el módulo de gastos</p>
                </div>

                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-[#1e1e20] dark:to-[#252529] rounded-lg shadow-sm border border-gray-200 dark:border-[#2b2b2e] p-5">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="font-bold text-gray-600 dark:text-gray-300 text-sm uppercase">Por pagar</h4>
                        <el-icon class="text-amber-500"><Clock /></el-icon>
                    </div>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ formatCurrency(budget.totals.pending) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Conceptos sin pago y adicionales pendientes</p>
                </div>
            </div>

            <!-- Breakdown -->
            <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <div class="p-4 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#2b2b2e]">
                    <h3 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                        <el-icon><Money /></el-icon> Desglose del presupuesto
                    </h3>
                </div>

                <el-table :data="budget.concepts" stripe style="width: 100%">
                    <el-table-column label="Concepto" min-width="200" show-overflow-tooltip>
                        <template #default="scope">
                            <div class="flex flex-col">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-800 dark:text-gray-200 text-sm">{{ scope.row.concept }}</span>
                                    <el-tag
                                        v-if="costTypeLabel(scope.row.type)"
                                        :type="costTypeTagType(scope.row.type)"
                                        size="small"
                                        effect="plain"
                                        class="shrink-0"
                                    >
                                        {{ costTypeLabel(scope.row.type) }}
                                    </el-tag>
                                </div>
                                <span v-if="scope.row.expense?.folio" class="text-xs text-gray-400">Gasto {{ scope.row.expense.folio }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Monto" width="150" align="right">
                        <template #default="scope">
                            <div class="flex flex-col items-end">
                                <span class="font-mono text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ formatCurrency(scope.row.amount) }}
                                </span>
                                <span v-if="scope.row.expense?.commission_amount > 0" class="text-xs text-amber-600">
                                    + {{ formatCurrency(scope.row.expense.commission_amount) }} comisión
                                </span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Pago a técnico" width="120" align="center">
                        <template #default="scope">
                            <el-tag v-if="scope.row.paid_to_technician" type="success" size="small" effect="light">Sí</el-tag>
                            <el-tag v-else type="info" size="small" effect="plain">No</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Estado" width="150" align="center">
                        <template #default="scope">
                            <el-tag :type="conceptState(scope.row).type" size="small" effect="light">
                                {{ conceptState(scope.row).label }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Fecha de pago" width="130" align="center">
                        <template #default="scope">
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                {{ formatDate(conceptPaymentDate(scope.row)) }}
                            </span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Referencia" width="150">
                        <template #default="scope">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ scope.row.expense?.reference || '—' }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Comprobantes" width="130" align="center">
                        <template #default="scope">
                            <div v-if="scope.row.expense?.receipts?.length" class="flex items-center justify-center gap-1">
                                <a
                                    :href="scope.row.expense.receipts[0].url"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-blue-500 hover:text-blue-700 dark:text-blue-400"
                                >
                                    <el-icon><Document /></el-icon>
                                </a>
                                <span v-if="scope.row.expense.receipts.length > 1" class="text-xs text-gray-500">
                                    +{{ scope.row.expense.receipts.length - 1 }}
                                </span>
                            </div>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Acciones" width="160" align="center" fixed="right">
                        <template #default="scope">
                            <el-button
                                v-if="(can('expenses.create') || can('expenses.edit')) && !isCancelled"
                                size="small"
                                :type="scope.row.expense ? 'default' : 'primary'"
                                plain
                                @click="openPaymentDialog(scope.row)"
                            >
                                {{ scope.row.expense ? 'Editar pago' : 'Registrar pago' }}
                            </el-button>
                        </template>
                    </el-table-column>

                    <template #empty>
                        <el-empty :image-size="90" description="Este presupuesto no tiene conceptos en el desglose." />
                    </template>
                </el-table>

                <div class="p-4 text-right bg-gray-50 dark:bg-[#252529] border-t border-gray-100 dark:border-[#2b2b2e]">
                    <span class="text-sm font-bold text-gray-600 dark:text-gray-300 mr-4">TOTAL DEL DESGLOSE:</span>
                    <span class="text-xl font-bold text-primary">{{ formatCurrency(budget.totals.concepts) }}</span>
                </div>
            </div>

            <!-- Extra expenses and commissions -->
            <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <div class="p-4 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#2b2b2e] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <h3 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                        <el-icon><Tickets /></el-icon> Gastos adicionales y comisiones
                    </h3>

                    <div v-if="can('expenses.create') && !isCancelled" class="flex items-center gap-2">
                        <el-button type="primary" size="default" @click="openExtraDialog">Agregar gasto</el-button>
                    </div>
                </div>

                <el-table :data="budget.extras" stripe style="width: 100%">
                    <el-table-column label="Concepto" min-width="200" show-overflow-tooltip>
                        <template #default="scope">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-800 dark:text-gray-200 text-sm">{{ scope.row.concept }}</span>
                                <el-tag v-if="scope.row.is_commission" type="warning" size="small" effect="plain">Comisión</el-tag>
                                <el-tag v-if="scope.row.deposit_id" type="info" size="small" effect="plain">Depósito</el-tag>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Categoría" width="150">
                        <template #default="scope">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ scope.row.category_name || 'Sin categoría' }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Monto" width="150" align="right">
                        <template #default="scope">
                            <div class="flex flex-col items-end">
                                <span class="font-mono text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ formatCurrency(scope.row.amount) }}
                                </span>
                                <span v-if="scope.row.commission_amount > 0" class="text-xs text-amber-600">
                                    + {{ formatCurrency(scope.row.commission_amount) }} comisión
                                </span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Estatus" width="150" align="center">
                        <template #default="scope">
                            <el-tag :type="expenseStatusTagType(scope.row.status)" size="small" effect="light">
                                {{ scope.row.status_label }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Fecha" width="120" align="center">
                        <template #default="scope">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ formatDate(scope.row.expense_date) }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Referencia" width="150">
                        <template #default="scope">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ scope.row.reference || '—' }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Comprobantes" width="130" align="center">
                        <template #default="scope">
                            <div v-if="scope.row.receipts?.length" class="flex items-center justify-center gap-1">
                                <a
                                    :href="scope.row.receipts[0].url"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-blue-500 hover:text-blue-700 dark:text-blue-400"
                                >
                                    <el-icon><Document /></el-icon>
                                </a>
                                <span v-if="scope.row.receipts.length > 1" class="text-xs text-gray-500">
                                    +{{ scope.row.receipts.length - 1 }}
                                </span>
                            </div>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Acciones" width="100" align="center" fixed="right">
                        <template #default="scope">
                            <el-dropdown
                                v-if="can('expenses.edit') || (can('expenses.delete') && !scope.row.deposit_id)"
                                trigger="click"
                                @command="(command) => handleExtraCommand(command, scope.row)"
                            >
                                <el-button link :icon="MoreFilled" />
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item
                                            v-if="scope.row.deposit_id && scope.row.status === 'pending' && can('expenses.edit')"
                                            command="complete-deposit"
                                            :icon="Check"
                                        >
                                            Marcar realizado
                                        </el-dropdown-item>
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
                        </template>
                    </el-table-column>

                    <template #empty>
                        <el-empty :image-size="90" description="No hay gastos adicionales ni comisiones registradas." />
                    </template>
                </el-table>

                <div class="p-4 text-right bg-gray-50 dark:bg-[#252529] border-t border-gray-100 dark:border-[#2b2b2e]">
                    <span class="text-sm font-bold text-gray-600 dark:text-gray-300 mr-4">TOTAL ADICIONALES:</span>
                    <span class="text-xl font-bold text-primary">{{ formatCurrency(budget.totals.extras) }}</span>
                </div>
            </div>
        </div>

        <!-- Concept payment dialog -->
        <BudgetConceptPaymentDialog
            v-if="showPaymentDialog && paymentConcept"
            v-model="showPaymentDialog"
            :budget="budget"
            :concept="paymentConcept"
            :categories="categories"
        />

        <!-- Extra expense dialog -->
        <ExpenseFormDialog
            v-if="showExtraDialog"
            v-model="showExtraDialog"
            :expense="editingExtra"
            :categories="categories"
            :budget="budget"
        />

        <!-- Complete deposit dialog -->
        <CompleteDepositDialog
            v-if="showCompleteDepositDialog && completingDepositExpense"
            v-model="showCompleteDepositDialog"
            :expense="completingDepositExpense"
        />
    </AppLayout>
</template>
