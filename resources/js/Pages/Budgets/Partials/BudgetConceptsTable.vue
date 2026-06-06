<script setup>
import { computed } from 'vue';
import { Money } from '@element-plus/icons-vue';

const props = defineProps({
    budget: Object,
});

const conceptsTotal = computed(() => {
    if (!props.budget.concepts || !props.budget.concepts.length) return 0;
    return props.budget.concepts.reduce((sum, c) => sum + Number(c.amount || 0), 0);
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
            <el-table-column label="Pago a técnico" align="center" width="130">
                <template #default="scope">
                    <el-tag v-if="scope.row.paid_to_technician" type="success" size="small" effect="light">Sí</el-tag>
                    <el-tag v-else type="info" size="small" effect="plain">No</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="payment_date" label="Fecha pago técnico" align="center" width="150">
                <template #default="scope">
                    <span v-if="scope.row.payment_date" class="text-sm text-gray-600 dark:text-gray-400">
                        {{ new Date(scope.row.payment_date).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) }}
                    </span>
                    <span v-else class="text-xs text-gray-400 italic">—</span>
                </template>
            </el-table-column>
        </el-table>
        <div class="p-4 text-right bg-gray-50 dark:bg-[#252529] border-t border-gray-100 dark:border-[#2b2b2e]">
            <span class="text-sm font-bold text-gray-600 mr-4">TOTAL:</span>
            <span class="text-xl font-bold text-primary">{{ formatCurrency(conceptsTotal) }}</span>
        </div>
    </div>
</template>
