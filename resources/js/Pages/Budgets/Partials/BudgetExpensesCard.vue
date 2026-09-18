<script setup>
import { computed } from 'vue';
import { Money } from '@element-plus/icons-vue';

const props = defineProps({
    budget: Object,
});

const expenses = computed(() => props.budget.expenses || []);

const totals = computed(() => {
    const paid = expenses.value
        .filter((expense) => expense.status === 'paid')
        .reduce((sum, expense) => sum + Number(expense.amount || 0) + Number(expense.commission_amount || 0), 0);

    const pending = expenses.value
        .filter((expense) => expense.status === 'pending')
        .reduce((sum, expense) => sum + Number(expense.amount || 0) + Number(expense.commission_amount || 0), 0);

    return { paid, pending };
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: props.budget.currency || 'MXN',
    }).format(value || 0);
};

const formatDate = (value) => {
    if (!value) return '—';

    // Dates arrive as ISO strings (e.g. 2026-09-18T00:00:00.000000Z).
    const [year, month, day] = String(value).split('T')[0].split('-');

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
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
        <div class="p-4 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#2b2b2e]">
            <h3 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                <el-icon><Money /></el-icon> Gastos del presupuesto
            </h3>
        </div>

        <el-table :data="expenses" stripe style="width: 100%">
            <el-table-column label="Concepto" min-width="220" show-overflow-tooltip>
                <template #default="scope">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-gray-800 dark:text-gray-200 text-sm">{{ scope.row.concept }}</span>
                        <el-tag v-if="scope.row.is_commission" type="warning" size="small" effect="plain">Comisión</el-tag>
                    </div>
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
                    <el-tag :type="statusTagType(scope.row.status)" size="small" effect="light">
                        {{ scope.row.status_label }}
                    </el-tag>
                </template>
            </el-table-column>

            <el-table-column label="Fecha" width="130" align="center">
                <template #default="scope">
                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ formatDate(scope.row.expense_date) }}</span>
                </template>
            </el-table-column>

            <template #empty>
                <el-empty :image-size="90" description="Aún no hay gastos registrados para este presupuesto." />
            </template>
        </el-table>

        <div class="p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 bg-gray-50 dark:bg-[#252529] border-t border-gray-100 dark:border-[#2b2b2e]">
            <span class="text-xs text-gray-500 dark:text-gray-400">
                Los pagos se registran y gestionan desde el control de gastos.
            </span>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-600 dark:text-gray-300">
                    Pagado: <strong class="text-green-600">{{ formatCurrency(totals.paid) }}</strong>
                </span>
                <span class="text-sm text-gray-600 dark:text-gray-300">
                    Por pagar: <strong class="text-amber-600">{{ formatCurrency(totals.pending) }}</strong>
                </span>
            </div>
        </div>
    </div>
</template>
