<script setup>
import { Money } from '@element-plus/icons-vue';

const props = defineProps({
    budget: Object,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: props.budget.currency || 'MXN',
    }).format(value || 0);
};
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
        <div class="p-4 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#2b2b2e]">
            <h3 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                <el-icon><Money /></el-icon> Conceptos del presupuesto
            </h3>
        </div>
        <el-table :data="budget.concepts" stripe style="width: 100%">
            <el-table-column prop="concept" label="Concepto" />
            <el-table-column prop="amount" label="Monto" align="right" width="150">
                <template #default="scope">
                    {{ formatCurrency(scope.row.amount) }}
                </template>
            </el-table-column>
        </el-table>
        <div class="p-4 text-right bg-gray-50 dark:bg-[#252529] border-t border-gray-100 dark:border-[#2b2b2e]">
            <span class="text-sm font-bold text-gray-600 mr-4">TOTAL:</span>
            <span class="text-xl font-bold text-primary">{{ formatCurrency(budget.total_cost) }}</span>
        </div>
    </div>
</template>
