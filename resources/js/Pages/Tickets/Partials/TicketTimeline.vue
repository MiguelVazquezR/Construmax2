<script setup>
import { ref, computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    ticket: Object,
});

const viewMode = ref('vertical');

// --- 1. LÓGICA VERTICAL ---
const timelineActivities = computed(() => {
    const activities = [];

    activities.push({
        content: 'Ticket Creado',
        timestamp: props.ticket.created_at,
        type: 'primary',
        icon: 'Plus',
        color: '#909399',
        detail: 'Registro inicial'
    });

    if (props.ticket.scheduled_start) {
        activities.push({
            content: 'Inicio Programado',
            timestamp: props.ticket.scheduled_start,
            type: 'warning',
            icon: 'Clock',
            color: '#e6a23c',
            detail: 'Fecha agendada'
        });
    }

    if (props.ticket.tasks) {
        props.ticket.tasks.forEach(task => {
            // Hito: Inicio de tarea (si está definido)
            if (task.start_date) {
                activities.push({
                    content: `Inicio Tarea: ${task.name}`,
                    timestamp: task.start_date,
                    type: 'primary', // Azul suave
                    icon: 'VideoPlay',
                    color: '#a0cfff',
                    detail: 'Inicio planificado'
                });
            }

            if (task.status === 'Completada' && task.completed_at) {
                activities.push({
                    content: `Completada: ${task.name}`,
                    timestamp: task.completed_at,
                    type: 'success',
                    icon: 'Check',
                    color: '#67c23a',
                    detail: `Realizada por: ${task.assignee?.name || 'Técnico'}`
                });
            } else if (task.due_date) {
                activities.push({
                    content: `Vencimiento: ${task.name}`,
                    timestamp: task.due_date,
                    type: 'info',
                    icon: 'Bell',
                    color: '#409eff',
                    detail: `Fecha límite`
                });
            }
        });
    }

    if (props.ticket.scheduled_end) {
        activities.push({
            content: 'Fin Programado',
            timestamp: props.ticket.scheduled_end,
            type: 'danger',
            icon: 'Flag',
            color: '#f56c6c',
            detail: 'Fecha estimada término'
        });
    }

    return activities.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
});

// --- 2. LÓGICA HORIZONTAL (Gantt Chart Preciso) ---
const ganttSeries = computed(() => {
    const data = [];

    // Barra Principal: Ticket
    if (props.ticket.scheduled_start && props.ticket.scheduled_end) {
        data.push({
            x: 'Servicio General',
            y: [
                new Date(props.ticket.scheduled_start).getTime(),
                new Date(props.ticket.scheduled_end).getTime()
            ],
            fillColor: '#f26c17'
        });
    }

    // Barras de Tareas
    if (props.ticket.tasks) {
        props.ticket.tasks.forEach(task => {
            // INICIO: Prioridad a start_date, fallback a creación de ticket
            const startDate = task.start_date 
                ? new Date(task.start_date) 
                : new Date(props.ticket.created_at);
            
            // FIN: Prioridad a completed_at, luego due_date, luego fallback (+1 día)
            let endDate = null;
            if (task.completed_at) {
                endDate = new Date(task.completed_at);
            } else if (task.due_date) {
                endDate = new Date(task.due_date);
            } else {
                endDate = new Date(startDate.getTime() + (24 * 60 * 60 * 1000));
            }

            // Validar coherencia temporal para ApexCharts
            if (endDate.getTime() <= startDate.getTime()) {
                endDate = new Date(startDate.getTime() + (3600 * 1000));
            }

            // Color
            let color = '#9ca3af'; // Pendiente
            if (task.status === 'Completada') color = '#67c23a';
            else if (new Date() > endDate) color = '#f56c6c'; // Vencida
            else color = '#409eff'; // En tiempo

            data.push({
                x: task.name,
                y: [startDate.getTime(), endDate.getTime()],
                fillColor: color
            });
        });
    }

    return [{ data: data }];
});

const ganttOptions = computed(() => ({
    chart: {
        type: 'rangeBar',
        height: 350 + (props.ticket.tasks?.length * 30),
        toolbar: { show: false },
        fontFamily: 'inherit'
    },
    plotOptions: {
        bar: {
            horizontal: true,
            barHeight: '50%',
            borderRadius: 4
        }
    },
    xaxis: {
        type: 'datetime',
        labels: { datetimeFormatter: { year: 'yyyy', month: 'MMM \'yy', day: 'dd MMM' } }
    },
    grid: {
        borderColor: '#f3f4f6',
        strokeDashArray: 4,
        xaxis: { lines: { show: true } }
    },
    tooltip: {
        x: { format: 'dd MMM HH:mm' } // Formato más preciso
    }
}));

const formatDate = (date) => {
    return new Date(date).toLocaleString('es-MX', { 
        day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' 
    });
};
</script>

<template>
    <div class="py-6 px-4">
        
        <div class="flex flex-col sm:flex-row justify-between items-center mb-8 gap-4">
            <div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">Cronograma de actividades</h3>
                <p class="text-sm text-gray-500">Visualiza la línea de tiempo del servicio.</p>
            </div>
            
            <div class="bg-gray-100 dark:bg-[#252529] p-1 rounded-lg flex items-center">
                <button 
                    @click="viewMode = 'vertical'"
                    class="px-3 py-1.5 rounded-md text-sm font-medium transition-all duration-200 flex items-center gap-2"
                    :class="viewMode === 'vertical' ? 'bg-white dark:bg-[#1e1e20] shadow-sm text-primary' : 'text-gray-500 hover:text-gray-700'"
                >
                    <el-icon><More /></el-icon> Línea de Tiempo
                </button>
                <button 
                    @click="viewMode = 'gantt'"
                    class="px-3 py-1.5 rounded-md text-sm font-medium transition-all duration-200 flex items-center gap-2"
                    :class="viewMode === 'gantt' ? 'bg-white dark:bg-[#1e1e20] shadow-sm text-primary' : 'text-gray-500 hover:text-gray-700'"
                >
                    <el-icon><Operation /></el-icon> Diagrama Gantt
                </button>
            </div>
        </div>
        
        <!-- VISTA 1: VERTICAL -->
        <div v-if="viewMode === 'vertical'" class="max-w-3xl mx-auto">
            <el-timeline>
                <el-timeline-item
                    v-for="(activity, index) in timelineActivities"
                    :key="index"
                    :icon="activity.icon"
                    :type="activity.type"
                    :color="activity.color"
                    :timestamp="formatDate(activity.timestamp)"
                    placement="top"
                    size="large"
                >
                    <div class="bg-gray-50 dark:bg-[#252529] p-4 rounded-lg border border-gray-100 dark:border-[#3f3f46] shadow-sm transition hover:shadow-md">
                        <span class="font-bold text-gray-800 dark:text-gray-200 block mb-1">{{ activity.content }}</span>
                        <span class="text-xs text-gray-500">{{ activity.detail }}</span>
                    </div>
                </el-timeline-item>
            </el-timeline>
            
            <p v-if="timelineActivities.length === 0" class="text-center text-gray-400 py-8">
                No hay actividad registrada aún.
            </p>
        </div>

        <!-- VISTA 2: GANTT -->
        <div v-else class="bg-white dark:bg-[#1e1e20] p-2 rounded-lg">
            <div v-if="ganttSeries[0].data.length > 0">
                <VueApexCharts 
                    type="rangeBar" 
                    width="100%" 
                    :height="ganttOptions.chart.height" 
                    :options="ganttOptions" 
                    :series="ganttSeries" 
                />
            </div>
            <div v-else class="text-center text-gray-400 py-12 border-2 border-dashed border-gray-200 dark:border-[#3f3f46] rounded-xl">
                <el-icon :size="48" class="mb-2"><Calendar /></el-icon>
                <p>No hay suficientes datos de fechas para generar el diagrama.</p>
            </div>
        </div>

    </div>
</template>