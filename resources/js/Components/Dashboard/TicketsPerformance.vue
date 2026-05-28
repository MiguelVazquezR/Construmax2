<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import KpiCard from '@/Components/Dashboard/KpiCard.vue';
import { Calendar, CircleCheck, DataLine, Warning, Stopwatch } from '@element-plus/icons-vue';

const props = defineProps({
    kpis: Object,
    charts: Object,
});

// --- Gráfica: Cronología (Area Chart) ---
const timelineSeries = computed(() => props.charts.timeline.series);
const timelineOptions = computed(() => ({
    chart: { type: 'area', toolbar: { show: false }, fontFamily: 'inherit' },
    colors: ['#3b82f6'],
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    xaxis: {
        categories: props.charts.timeline.labels,
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] } },
}));

// --- Gráfica: Carga por Técnico (Horizontal Bar) ---
const workloadSeries = computed(() => [{
    name: 'Tickets asignados',
    data: props.charts.workload.map(w => w.total),
}]);
const workloadOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
    colors: ['#8b5cf6'],
    plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '60%' } },
    xaxis: { categories: props.charts.workload.map(w => w.name) },
    grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
    dataLabels: { enabled: true, textAnchor: 'start', style: { colors: ['#fff'] }, offsetX: 0 },
    noData: { text: 'Sin datos' },
}));

// --- Gráfica: Prioridades (Donut) ---
const prioritySeries = computed(() => Object.values(props.charts.priority));
const priorityOptions = computed(() => ({
    chart: { type: 'donut', fontFamily: 'inherit' },
    labels: Object.keys(props.charts.priority),
    colors: ['#9ca3af', '#fb923c', '#ef4444', '#b91c1c'],
    legend: { position: 'bottom' },
    plotOptions: { pie: { donut: { size: '65%' } } },
    dataLabels: { enabled: false },
    noData: { text: 'Sin datos' },
}));
</script>

<template>
    <section>
        <div class="flex items-center gap-2 mb-4">
            <el-icon class="text-primary"><Stopwatch /></el-icon>
            <h3 class="text-lg font-bold text-gray-800 dark:text-white">Desempeño en el periodo</h3>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <KpiCard
                label="Tickets programados"
                :value="kpis.total_tickets"
                subtitle="Para iniciar en estas fechas"
                :icon="Calendar"
                icon-bg="bg-blue-50 dark:bg-blue-900/20"
                icon-color="text-blue-500"
            />
            <KpiCard
                label="Completados"
                :value="kpis.completed_tickets"
                subtitle="Del total programado"
                :icon="CircleCheck"
                icon-bg="bg-green-50 dark:bg-green-900/20"
                icon-color="text-green-600"
            />
            <KpiCard
                label="Tasa de cumplimiento"
                :value="`${kpis.completion_rate}%`"
                :icon="DataLine"
                icon-bg="bg-purple-50 dark:bg-purple-900/20"
                icon-color="text-purple-500"
            >
                <el-progress
                    :percentage="kpis.completion_rate"
                    :show-text="false"
                    :stroke-width="4"
                    class="mt-3"
                    status="success"
                />
            </KpiCard>
            <KpiCard
                label="Vencidos / retrasados"
                :value="kpis.overdue_tickets"
                subtitle="Debieron terminar en el periodo"
                :icon="Warning"
                icon-bg="bg-red-50 dark:bg-red-900/20"
                icon-color="text-red-500"
                value-color="text-red-500"
            />
        </div>

        <!-- Gráficas: Cronología + Prioridades -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2 bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Volumen de tickets programados</h3>
                <div class="h-72">
                    <VueApexCharts type="area" height="100%" :options="timelineOptions" :series="timelineSeries" />
                </div>
            </div>

            <div class="lg:col-span-1 bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Distribución por prioridad</h3>
                <div class="h-72 flex items-center justify-center">
                    <VueApexCharts type="donut" width="100%" :options="priorityOptions" :series="prioritySeries" />
                </div>
            </div>
        </div>

        <!-- Carga de trabajo -->
        <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Carga de trabajo por técnico (Tickets asignados)</h3>
            <div class="h-64">
                <VueApexCharts type="bar" height="100%" :options="workloadOptions" :series="workloadSeries" />
            </div>
        </div>
    </section>
</template>
