<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    kpis: Object,
    charts: Object,
    general: Object,
    filters: Object,
});

// --- FILTROS DE FECHA ---
const dateRange = ref([props.filters.start_date, props.filters.end_date]);

const shortcuts = [
    { text: 'Hoy', value: () => [new Date(), new Date()] },
    { text: 'Esta semana', value: () => { const end = new Date(); const start = new Date(); start.setTime(start.getTime() - 3600 * 1000 * 24 * 7); return [start, end]; } },
    { text: 'Este mes', value: () => { const end = new Date(); const start = new Date(); start.setDate(1); return [start, end]; } },
    { text: 'Próximo mes', value: () => { const start = new Date(); start.setDate(1); start.setMonth(start.getMonth() + 1); const end = new Date(start); end.setMonth(end.getMonth() + 1); end.setDate(0); return [start, end]; } },
];

const handleDateChange = (val) => {
    if (val) {
        router.get(route('tickets.dashboard'), {
            start_date: val[0],
            end_date: val[1]
        }, { preserveState: true, preserveScroll: true, replace: true });
    }
};

// --- GRÁFICAS REACTIVAS ---

// 1. Línea de Tiempo (Area Chart)
const timelineSeries = computed(() => props.charts.timeline.series);
const timelineOptions = computed(() => ({
    chart: { type: 'area', toolbar: { show: false }, fontFamily: 'inherit' },
    colors: ['#3b82f6'],
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    xaxis: { 
        categories: props.charts.timeline.labels,
        axisBorder: { show: false },
        axisTicks: { show: false }
    },
    grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] } }
}));

// 2. Carga por Técnico (Bar Chart)
const workloadSeries = computed(() => [{
    name: 'Tickets Asignados',
    data: props.charts.workload.map(w => w.total)
}]);
const workloadOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
    colors: ['#8b5cf6'],
    plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '60%' } },
    xaxis: { categories: props.charts.workload.map(w => w.name) },
    grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
    dataLabels: { enabled: true, textAnchor: 'start', style: { colors: ['#fff'] }, offsetX: 0 }
}));

// 3. Prioridad (Donut)
const prioritySeries = computed(() => Object.values(props.charts.priority));
const priorityOptions = computed(() => ({
    chart: { type: 'donut', fontFamily: 'inherit' },
    labels: Object.keys(props.charts.priority),
    // Colores semánticos: Baja(Gris), Media(Naranja), Alta(Rojo), Urgente(Rojo Oscuro)
    colors: ['#9ca3af', '#fb923c', '#ef4444', '#b91c1c'], 
    legend: { position: 'bottom' },
    plotOptions: { pie: { donut: { size: '65%' } } },
    dataLabels: { enabled: false },
    noData: { text: 'Sin datos' }
}));

</script>

<template>
    <AppLayout title="Tablero Operativo">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                    Tablero de operaciones
                </h2>
                
                <div class="w-full md:w-auto flex items-center gap-2">
                    <span class="text-sm text-gray-500 hidden md:inline">Programación:</span>
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
            
            <!-- SECCIÓN 1: RENDIMIENTO OPERATIVO (FILTRADO) -->
            <section>
                <div class="flex items-center gap-2 mb-4">
                    <el-icon class="text-primary"><Stopwatch /></el-icon>
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">Desempeño en el periodo</h3>
                </div>

                <!-- KPIS -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Total Programados -->
                    <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Tickets Programados</p>
                                <h3 class="text-3xl font-bold text-gray-800 dark:text-white mt-1">{{ kpis.total_tickets }}</h3>
                            </div>
                            <div class="p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-blue-500">
                                <el-icon :size="24"><Calendar /></el-icon>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">Para iniciar en estas fechas</p>
                    </div>

                    <!-- Completados -->
                    <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Completados</p>
                                <h3 class="text-3xl font-bold text-gray-800 dark:text-white mt-1">{{ kpis.completed_tickets }}</h3>
                            </div>
                            <div class="p-2 bg-green-50 dark:bg-green-900/20 rounded-lg text-green-600">
                                <el-icon :size="24"><CircleCheck /></el-icon>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">Del total programado</p>
                    </div>

                    <!-- Eficiencia -->
                    <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Tasa de Cumplimiento</p>
                                <h3 class="text-3xl font-bold text-gray-800 dark:text-white mt-1">{{ kpis.completion_rate }}%</h3>
                            </div>
                            <div class="p-2 bg-purple-50 dark:bg-purple-900/20 rounded-lg text-purple-500">
                                <el-icon :size="24"><DataLine /></el-icon>
                            </div>
                        </div>
                        <el-progress :percentage="kpis.completion_rate" :show-text="false" :stroke-width="4" class="mt-3" status="success" />
                    </div>

                    <!-- Vencidos -->
                    <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Vencidos / Retrasados</p>
                                <h3 class="text-3xl font-bold text-red-500 mt-1">{{ kpis.overdue_tickets }}</h3>
                            </div>
                            <div class="p-2 bg-red-50 dark:bg-red-900/20 rounded-lg text-red-500">
                                <el-icon :size="24"><Warning /></el-icon>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">Debieron terminar en el periodo</p>
                    </div>
                </div>

                <!-- GRÁFICAS -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Cronología -->
                    <div class="lg:col-span-2 bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Volumen de tickets programados</h3>
                        <div class="h-72">
                            <VueApexCharts type="area" height="100%" :options="timelineOptions" :series="timelineSeries" />
                        </div>
                    </div>

                    <!-- Prioridades -->
                    <div class="lg:col-span-1 bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Distribución por prioridad</h3>
                        <div class="h-72 flex items-center justify-center">
                            <VueApexCharts type="donut" width="100%" :options="priorityOptions" :series="prioritySeries" />
                        </div>
                    </div>
                </div>

                <!-- CARGA DE TRABAJO -->
                <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Carga de trabajo por técnico (Tickets Asignados)</h3>
                    <div class="h-64">
                        <VueApexCharts type="bar" height="100%" :options="workloadOptions" :series="workloadSeries" />
                    </div>
                </div>
            </section>

            <el-divider border-style="dashed" />

            <!-- SECCIÓN 2: TENDENCIAS GENERALES (HISTÓRICO) -->
            <section class="opacity-90">
                <div class="flex items-center gap-2 mb-4">
                    <el-icon class="text-gray-500"><Histogram /></el-icon>
                    <h3 class="text-lg font-bold text-gray-600 dark:text-gray-300">Estado global actual (Tiempo real)</h3>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Backlog General -->
                    <div class="bg-gray-50 dark:bg-[#18181b] p-6 rounded-xl border border-gray-200 dark:border-[#2b2b2e] flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Backlog Total</p>
                            <h3 class="text-3xl font-bold text-gray-700 dark:text-gray-200 mt-1">{{ general.backlog }}</h3>
                            <p class="text-xs text-gray-400 mt-1">Tickets activos (No completados)</p>
                        </div>
                        <div class="bg-white dark:bg-[#27272a] p-3 rounded-full shadow-sm text-orange-400">
                            <el-icon :size="24"><Box /></el-icon>
                        </div>
                    </div>

                    <!-- Tareas Pendientes -->
                    <div class="bg-gray-50 dark:bg-[#18181b] p-6 rounded-xl border border-gray-200 dark:border-[#2b2b2e] flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Actividades Pendientes</p>
                            <h3 class="text-3xl font-bold text-gray-700 dark:text-gray-200 mt-1">{{ general.pending_tasks }}</h3>
                            <p class="text-xs text-gray-400 mt-1">Tareas individuales sin terminar</p>
                        </div>
                        <div class="bg-white dark:bg-[#27272a] p-3 rounded-full shadow-sm text-blue-400">
                            <el-icon :size="24"><List /></el-icon>
                        </div>
                    </div>

                    <!-- Top Técnicos Ocupados -->
                    <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl border border-gray-200 dark:border-[#2b2b2e] shadow-sm">
                        <h4 class="text-sm font-bold text-gray-600 dark:text-gray-300 mb-3">Técnicos con más pendientes</h4>
                        <ul class="space-y-3">
                            <li v-for="(tech, index) in general.busy_techs" :key="index" class="flex justify-between items-center text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-400 font-mono text-xs">{{ index + 1 }}.</span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ tech.name }}</span>
                                </div>
                                <span class="bg-red-50 text-red-600 px-2 py-0.5 rounded text-xs font-bold">{{ tech.pending_count }} tareas</span>
                            </li>
                            <li v-if="general.busy_techs.length === 0" class="text-xs text-gray-400 text-center">Todo al día</li>
                        </ul>
                    </div>

                </div>
            </section>

        </div>
    </AppLayout>
</template>