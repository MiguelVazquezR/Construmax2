<script setup>
import { computed } from 'vue';

const props = defineProps({
    stats: Object,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(value || 0);
};

const projectShare = computed(() => {
    if (!props.stats?.total_amount) return 0;

    return Math.round((props.stats.project_amount / props.stats.total_amount) * 100);
});

const projectCaption = computed(() => {
    const count = props.stats.project_count || 0;

    if (count === 0) {
        return 'Sin gastos ligados a tickets';
    }

    return `${count} gastos ligados a tickets (${projectShare.value}% del total)`;
});
</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div
            class="bg-gradient-to-br from-white to-gray-50 dark:from-[#1e1e20] dark:to-[#252529] rounded-lg shadow-sm border border-gray-200 dark:border-[#2b2b2e] p-5">
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-bold text-gray-600 dark:text-gray-300 text-sm uppercase">Total registrado</h4>
                <el-icon class="text-primary"><Wallet /></el-icon>
            </div>
            <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ formatCurrency(stats.total_amount) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ stats.total_count }} gastos en el periodo</p>
        </div>

        <div
            class="bg-gradient-to-br from-white to-gray-50 dark:from-[#1e1e20] dark:to-[#252529] rounded-lg shadow-sm border border-gray-200 dark:border-[#2b2b2e] p-5">
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-bold text-gray-600 dark:text-gray-300 text-sm uppercase">Pendientes de pago</h4>
                <el-icon class="text-amber-500"><Clock /></el-icon>
            </div>
            <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ formatCurrency(stats.pending_amount) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ stats.pending_count }} gastos por pagar</p>
        </div>

        <div
            class="bg-gradient-to-br from-white to-gray-50 dark:from-[#1e1e20] dark:to-[#252529] rounded-lg shadow-sm border border-gray-200 dark:border-[#2b2b2e] p-5">
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-bold text-gray-600 dark:text-gray-300 text-sm uppercase">En proyectos</h4>
                <el-icon class="text-blue-500"><Tickets /></el-icon>
            </div>
            <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ formatCurrency(stats.project_amount) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ projectCaption }}</p>
        </div>

        <div
            class="bg-gradient-to-br from-white to-gray-50 dark:from-[#1e1e20] dark:to-[#252529] rounded-lg shadow-sm border border-gray-200 dark:border-[#2b2b2e] p-5">
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-bold text-gray-600 dark:text-gray-300 text-sm uppercase">Pagados</h4>
                <el-icon class="text-green-500"><CircleCheck /></el-icon>
            </div>
            <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ formatCurrency(stats.paid_amount) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ stats.paid_count }} gastos pagados</p>
        </div>
    </div>
</template>
