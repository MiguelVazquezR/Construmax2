<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    invoices: Object,
});

const invoiceSeries = computed(() => Object.values(props.invoices));
const invoiceOptions = computed(() => ({
    chart: { type: 'donut', fontFamily: 'inherit' },
    labels: Object.keys(props.invoices),
    colors: ['#4ade80', '#facc15', '#9ca3af'],
    legend: { position: 'bottom' },
    plotOptions: { pie: { donut: { size: '65%' } } },
    dataLabels: { enabled: false },
    noData: { text: 'Sin datos en este periodo' },
}));
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Facturas emitidas vs pagadas</h3>
        <div class="h-72 flex items-center justify-center">
            <VueApexCharts type="donut" width="100%" :options="invoiceOptions" :series="invoiceSeries" />
        </div>
    </div>
</template>
