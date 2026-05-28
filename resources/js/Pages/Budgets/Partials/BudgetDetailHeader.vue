<script setup>
import { Calendar, User } from '@element-plus/icons-vue';

const props = defineProps({
    budget: Object,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: props.budget.currency || 'MXN',
    }).format(value || 0);
};

const formatMXN = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('es-MX', {
        year: 'numeric', month: 'long', day: 'numeric',
    });
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
        'Perdido': 'danger',
    };
    return map[status] || 'info';
};
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border-l-4 border-primary p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">{{ budget.ticket?.name }}</h1>
            <p class="text-sm text-gray-500 flex items-center gap-2">
                <el-icon><Calendar /></el-icon> Registro: {{ formatDate(budget.created_at) }}
                <span class="mx-1">•</span>
                <el-icon><User /></el-icon> Responsable: {{ budget.responsible?.name }}
            </p>
        </div>
        <div class="text-right w-full md:w-auto">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Monto total</p>
            <p class="text-3xl font-bold text-gray-800 dark:text-gray-100">
                {{ formatCurrency(budget.total_cost) }}
                <span class="text-lg text-gray-400 font-normal">{{ budget.currency }}</span>
            </p>
            <p v-if="budget.currency === 'USD'" class="text-xs text-gray-500 mt-1">
                ≈ {{ formatMXN(budget.total_cost * budget.exchange_rate) }}
                <span class="opacity-70">(TC: {{ budget.exchange_rate }})</span>
            </p>
        </div>
    </div>
</template>
