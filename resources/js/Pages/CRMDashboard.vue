<script setup>
import { ref, watch, reactive, computed } from 'vue'; // Agregamos 'computed'
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    kpis: Object,
    charts: Object,
    tables: Object,
    filters: Object,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 0 }).format(value);
};

// --- FILTRO DE FECHAS ---
const dateRange = ref([props.filters.start_date, props.filters.end_date]);

const shortcuts = [
    { text: 'Hoy', value: () => [new Date(), new Date()] },
    { text: 'Últimos 7 días', value: () => { const end = new Date(); const start = new Date(); start.setTime(start.getTime() - 3600 * 1000 * 24 * 7); return [start, end]; } },
    { text: 'Este mes', value: () => { const end = new Date(); const start = new Date(); start.setDate(1); return [start, end]; } },
    { text: 'Mes pasado', value: () => { const end = new Date(); const start = new Date(); start.setMonth(start.getMonth() - 1); start.setDate(1); end.setDate(0); return [start, end]; } },
    { text: 'Este año', value: () => { const end = new Date(); const start = new Date(new Date().getFullYear(), 0, 1); return [start, end]; } },
];

const handleDateChange = (val) => {
    if (val) {
        router.get(route('crm.dashboard'), {
            start_date: val[0],
            end_date: val[1]
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true
        });
    }
};

// --- CONFIGURACIÓN DE GRÁFICAS (REACTIVAS CON COMPUTED) ---

// 1. Ingresos (Bar Chart)
const incomeSeries = computed(() => [{
    name: 'Ingresos',
    data: props.charts.income.data
}]);

const incomeOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
    colors: ['#f26c17'],
    plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
    dataLabels: { enabled: false },
    xaxis: { 
        categories: props.charts.income.labels,
        axisBorder: { show: false },
        axisTicks: { show: false }
    },
    yaxis: {
        labels: { formatter: (val) => `$${val >= 1000 ? val/1000 + 'k' : val}` }
    },
    grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
    tooltip: { y: { formatter: (val) => formatCurrency(val) } }
}));

// 2. Estado de Presupuestos (Donut Chart)
const statusSeries = computed(() => Object.values(props.charts.status));

const statusOptions = computed(() => ({
    chart: { type: 'donut', fontFamily: 'inherit' },
    labels: Object.keys(props.charts.status),
    colors: ['#9ca3af', '#60a5fa', '#facc15', '#fb923c', '#4ade80', '#34d399', '#f87171'],
    legend: { position: 'bottom' },
    plotOptions: { pie: { donut: { size: '65%' } } },
    dataLabels: { enabled: false },
    noData: { text: 'Sin datos en este periodo' }
}));

// 3. Servicios Populares (Horizontal Bar)
const serviceSeries = computed(() => [{
    name: 'Solicitudes',
    data: props.charts.services.map(s => s.total)
}]);

const serviceOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '50%' } },
    colors: ['#3b82f6'],
    dataLabels: { enabled: true, textAnchor: 'start', style: { colors: ['#fff'] }, offsetX: 0 },
    xaxis: { categories: props.charts.services.map(s => s.service_type) },
    grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
    noData: { text: 'Sin datos' }
}));

</script>

<template>
    <AppLayout title="Analíticas CRM">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <h2 class="font-semibold text-base text-gray-800 dark:text-white leading-tight">
                    Tablero de control CRM
                </h2>
                
                <!-- Selector de Fechas -->
                <div class="w-full md:w-auto flex items-center gap-2">
                    <span class="text-sm text-gray-500 hidden md:inline">Periodo:</span>
                    <el-date-picker
                        v-model="dateRange"
                        type="daterange"
                        unlink-panels
                        range-separator="a"
                        start-placeholder="Inicio"
                        end-placeholder="Fin"
                        :shortcuts="shortcuts"
                        size="large"
                        format="DD/MM/YYYY"
                        value-format="YYYY-MM-DD"
                        @change="handleDateChange"
                        class="!w-full md:!w-[350px]"
                    />
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-8">
            
            <!-- SECCIÓN 1: RENDIMIENTO EN EL PERIODO (FILTRADO) -->
            <section>
                <div class="flex items-center gap-2 mb-4">
                    <el-icon class="text-primary"><DataAnalysis /></el-icon>
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">Rendimiento en el periodo seleccionado</h3>
                </div>

                <!-- 1. KPIS CARDS (FILTRADOS) -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Ingresos -->
                    <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Ingresos (Periodo)</p>
                                <h3 class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ formatCurrency(kpis.total_revenue) }}</h3>
                            </div>
                            <div class="p-2 bg-green-50 dark:bg-green-900/20 rounded-lg text-green-600">
                                <el-icon :size="24"><Money /></el-icon>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">Pagos cobrados en fechas seleccionadas</p>
                    </div>

                    <!-- Nuevos Clientes -->
                    <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Nuevos Clientes</p>
                                <h3 class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ kpis.new_customers }}</h3>
                            </div>
                            <div class="p-2 bg-purple-50 dark:bg-purple-900/20 rounded-lg text-purple-500">
                                <el-icon :size="24"><User /></el-icon>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">Registrados en el periodo</p>
                    </div>

                    <!-- Saldo Pendiente (De lo vendido en el periodo) -->
                    <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Por Cobrar (Generado)</p>
                                <h3 class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ formatCurrency(kpis.pending_balance) }}</h3>
                            </div>
                            <div class="p-2 bg-orange-50 dark:bg-orange-900/20 rounded-lg text-orange-500">
                                <el-icon :size="24"><Wallet /></el-icon>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">De presupuestos creados en fechas</p>
                    </div>

                    <!-- Tasa de Conversión -->
                    <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Conversión</p>
                                <h3 class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ kpis.conversion_rate }}%</h3>
                            </div>
                            <div class="p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-blue-500">
                                <el-icon :size="24"><TrendCharts /></el-icon>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">Presupuestos ganados vs totales</p>
                    </div>
                </div>

                <!-- 2. GRÁFICAS PRINCIPALES (FILTRADAS) -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    
                    <!-- Ingresos Mensuales/Diarios -->
                    <div class="lg:col-span-2 bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Tendencia de Ingresos</h3>
                        <div class="h-72">
                            <VueApexCharts type="bar" height="100%" :options="incomeOptions" :series="incomeSeries" />
                        </div>
                    </div>

                    <!-- Distribución de Estatus -->
                    <div class="lg:col-span-1 bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Estado de presupuestos</h3>
                        <div class="h-72 flex items-center justify-center">
                            <VueApexCharts type="donut" width="100%" :options="statusOptions" :series="statusSeries" />
                        </div>
                    </div>
                </div>

                <!-- 3. TABLAS Y GRÁFICAS SECUNDARIAS (FILTRADAS) -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <!-- Top Clientes -->
                    <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Mejores clientes (En el periodo)</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-[#252529]">
                                    <tr>
                                        <th class="px-4 py-3 rounded-l-lg">Cliente</th>
                                        <th class="px-4 py-3 text-right rounded-r-lg">Total Pagado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(customer, index) in tables.top_customers" :key="index" class="border-b border-gray-100 dark:border-[#2b2b2e] last:border-0">
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">
                                            {{ index + 1 }}. {{ customer.name }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold text-green-600 dark:text-green-400">
                                            {{ formatCurrency(customer.total_paid) }}
                                        </td>
                                    </tr>
                                    <tr v-if="tables.top_customers.length === 0">
                                        <td colspan="2" class="text-center py-4 text-gray-400">Sin movimientos registrados</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Servicios Populares -->
                    <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Servicios solicitados</h3>
                        <div class="h-64">
                            <VueApexCharts type="bar" height="100%" :options="serviceOptions" :series="serviceSeries" />
                        </div>
                    </div>
                </div>
            </section>

            <el-divider border-style="dashed" />

            <!-- SECCIÓN 2: TENDENCIAS GENERALES (HISTÓRICO) -->
            <section class="opacity-90">
                <div class="flex items-center gap-2 mb-4">
                    <el-icon class="text-gray-500"><Histogram /></el-icon>
                    <h3 class="text-lg font-bold text-gray-600 dark:text-gray-300">Tendencias Generales (Histórico)</h3>
                </div>
                <p class="text-sm text-gray-500 mb-6 -mt-2">Estos indicadores muestran el estado global del sistema, independientemente del rango de fechas seleccionado.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Total Clientes Históricos -->
                    <div class="bg-gray-50 dark:bg-[#18181b] p-6 rounded-xl border border-gray-200 dark:border-[#2b2b2e] flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Base de Clientes Total</p>
                            <h3 class="text-3xl font-bold text-gray-700 dark:text-gray-200 mt-1">{{ kpis.total_customers }}</h3>
                            <p class="text-xs text-gray-400 mt-1">Clientes registrados históricamente</p>
                        </div>
                        <div class="bg-white dark:bg-[#27272a] p-3 rounded-full shadow-sm">
                            <el-icon :size="24" class="text-gray-400"><UserFilled /></el-icon>
                        </div>
                    </div>

                    <!-- Proyectos Activos (En Proceso) -->
                    <div class="bg-gray-50 dark:bg-[#18181b] p-6 rounded-xl border border-gray-200 dark:border-[#2b2b2e] flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Carga de Trabajo Actual</p>
                            <h3 class="text-3xl font-bold text-gray-700 dark:text-gray-200 mt-1">{{ kpis.active_projects }}</h3>
                            <p class="text-xs text-gray-400 mt-1">Proyectos "En Proceso" actualmente</p>
                        </div>
                        <div class="bg-white dark:bg-[#27272a] p-3 rounded-full shadow-sm">
                            <el-icon :size="24" class="text-gray-400"><List /></el-icon>
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </AppLayout>
</template>