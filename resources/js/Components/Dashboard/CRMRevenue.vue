<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import KpiCard from '@/Components/Dashboard/KpiCard.vue';
import { Money, User, Wallet, TrendCharts, DataAnalysis } from '@element-plus/icons-vue';

const props = defineProps({
    kpis: Object,
    charts: Object,
    tables: Object,
    currencyMode: { type: String, default: 'MXN' },
});

const emit = defineEmits(['update:currencyMode']);

// --- Formateo de moneda ---
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: props.currencyMode,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value || 0);
};

// --- Helper para elegir valor según moneda ---
const getValue = (obj) => {
    if (!obj) return 0;
    if (typeof obj === 'object' && ('mxn' in obj || 'usd' in obj)) {
        return props.currencyMode === 'MXN' ? obj.mxn : obj.usd;
    }
    return obj;
};

// --- Gráfica: Ingresos (Bar) ---
const incomeSeries = computed(() => [{
    name: `Ingresos (${props.currencyMode})`,
    data: props.currencyMode === 'MXN' ? props.charts.income.data_mxn : props.charts.income.data_usd,
}]);
const incomeOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
    colors: ['#f26c17'],
    plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
    dataLabels: { enabled: false },
    xaxis: {
        categories: props.charts.income.labels,
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: {
            formatter: (val) => {
                if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
                if (val >= 1000) return (val / 1000).toFixed(0) + 'k';
                return val;
            },
        },
    },
    grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
    tooltip: {
        y: {
            formatter: (val) => new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: props.currencyMode,
            }).format(val),
        },
    },
}));

// --- Gráfica: Estado de presupuestos (Donut) ---
const statusSeries = computed(() => Object.values(props.charts.status));
const statusOptions = computed(() => ({
    chart: { type: 'donut', fontFamily: 'inherit' },
    labels: Object.keys(props.charts.status),
    colors: ['#9ca3af', '#60a5fa', '#facc15', '#fb923c', '#4ade80', '#34d399', '#f87171'],
    legend: { position: 'bottom' },
    plotOptions: { pie: { donut: { size: '65%' } } },
    dataLabels: { enabled: false },
    noData: { text: 'Sin datos en este periodo' },
}));

// --- Gráfica: Servicios populares (Horizontal Bar) ---
const serviceSeries = computed(() => [{
    name: 'Solicitudes',
    data: props.charts.services.map(s => s.total),
}]);
const serviceOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '50%' } },
    colors: ['#3b82f6'],
    dataLabels: { enabled: true, textAnchor: 'start', style: { colors: ['#fff'] }, offsetX: 0 },
    xaxis: { categories: props.charts.services.map(s => s.service_type) },
    grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
    noData: { text: 'Sin datos' },
}));
</script>

<template>
    <section>
        <div class="flex items-center gap-2 mb-4">
            <el-icon class="text-primary"><DataAnalysis /></el-icon>
            <h3 class="text-lg font-bold text-gray-800 dark:text-white">Rendimiento comercial ({{ currencyMode }})</h3>
        </div>

        <!-- Currency Switch -->
        <div class="flex justify-end mb-4">
            <div class="bg-white dark:bg-[#1e1e20] p-1 rounded-lg border border-gray-200 dark:border-[#2b2b2e] flex text-sm font-bold shadow-sm">
                <button
                    @click="emit('update:currencyMode', 'MXN')"
                    class="px-3 py-1.5 rounded transition-all"
                    :class="currencyMode === 'MXN' ? 'bg-green-50 text-green-600 shadow-sm' : 'text-gray-400 hover:text-gray-600'"
                >MXN</button>
                <button
                    @click="emit('update:currencyMode', 'USD')"
                    class="px-3 py-1.5 rounded transition-all"
                    :class="currencyMode === 'USD' ? 'bg-green-50 text-green-600 shadow-sm' : 'text-gray-400 hover:text-gray-600'"
                >USD</button>
            </div>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <KpiCard
                label="Ingresos"
                :value="formatCurrency(getValue(kpis.total_revenue))"
                subtitle="Pagos cobrados en fechas seleccionadas"
                :icon="Money"
                icon-bg="bg-green-50 dark:bg-green-900/20"
                icon-color="text-green-600"
                value-color="text-green-600 dark:text-green-400"
            />
            <KpiCard
                label="Nuevos clientes"
                :value="kpis.new_customers"
                subtitle="Registrados en el periodo"
                :icon="User"
                icon-bg="bg-purple-50 dark:bg-purple-900/20"
                icon-color="text-purple-500"
            />
            <KpiCard
                label="Por cobrar"
                :value="formatCurrency(getValue(kpis.pending_balance))"
                subtitle="De presupuestos creados en fechas"
                :icon="Wallet"
                icon-bg="bg-orange-50 dark:bg-orange-900/20"
                icon-color="text-orange-500"
            />
            <KpiCard
                label="Conversión"
                :value="`${kpis.conversion_rate}%`"
                subtitle="Presupuestos ganados vs totales"
                :icon="TrendCharts"
                icon-bg="bg-blue-50 dark:bg-blue-900/20"
                icon-color="text-blue-500"
            />
        </div>

        <!-- Gráficas principales -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Tendencia de ingresos -->
            <div class="lg:col-span-2 bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">Tendencia de ingresos</h3>
                    <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-[#2b2b2e] text-gray-500 font-bold">
                        {{ currencyMode }}
                    </span>
                </div>
                <div class="h-72">
                    <VueApexCharts type="bar" height="100%" :options="incomeOptions" :series="incomeSeries" />
                </div>
            </div>

            <!-- Estado de presupuestos -->
            <div class="lg:col-span-1 bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Estado de presupuestos</h3>
                <div class="h-72 flex items-center justify-center">
                    <VueApexCharts type="donut" width="100%" :options="statusOptions" :series="statusSeries" />
                </div>
            </div>
        </div>

        <!-- Tablas y gráficas secundarias -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Top clientes -->
            <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Mejores clientes (En el periodo)</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-[#252529]">
                            <tr>
                                <th class="px-4 py-3 rounded-l-lg">Cliente</th>
                                <th class="px-4 py-3 text-right rounded-r-lg">Total ({{ currencyMode }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(customer, index) in tables.top_customers"
                                :key="index"
                                class="border-b border-gray-100 dark:border-[#2b2b2e] last:border-0"
                            >
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">
                                    {{ index + 1 }}. {{ customer.name }}
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-green-600 dark:text-green-400">
                                    {{ formatCurrency(currencyMode === 'MXN' ? customer.total_mxn : customer.total_usd) }}
                                </td>
                            </tr>
                            <tr v-if="tables.top_customers.length === 0">
                                <td colspan="2" class="text-center py-4 text-gray-400">Sin movimientos registrados</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Servicios populares -->
            <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Servicios solicitados por categoría</h3>
                <div class="h-64">
                    <VueApexCharts type="bar" height="100%" :options="serviceOptions" :series="serviceSeries" />
                </div>
            </div>
        </div>
    </section>
</template>
