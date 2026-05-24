<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TicketsPerformance from '@/Components/Dashboard/TicketsPerformance.vue';
import CRMRevenue from '@/Components/Dashboard/CRMRevenue.vue';
import GlobalState from '@/Components/Dashboard/GlobalState.vue';

const props = defineProps({
    kpis: Object,
    charts: Object,
    tables: Object,
    general: Object,
    filters: Object,
});

// --- Estado de moneda (compartido) ---
const currencyMode = ref('MXN');

// --- Filtros de fecha ---
const dateRange = ref([props.filters.start_date, props.filters.end_date]);

const shortcuts = [
    { text: 'Hoy', value: () => [new Date(), new Date()] },
    { text: 'Últimos 7 días', value: () => { const end = new Date(); const start = new Date(); start.setTime(start.getTime() - 3600 * 1000 * 24 * 7); return [start, end]; } },
    { text: 'Este mes', value: () => { const end = new Date(); const start = new Date(); start.setDate(1); return [start, end]; } },
    { text: 'Mes pasado', value: () => { const end = new Date(); const start = new Date(); start.setMonth(start.getMonth() - 1); start.setDate(1); end.setDate(0); return [start, end]; } },
    { text: 'Próximo mes', value: () => { const start = new Date(); start.setDate(1); start.setMonth(start.getMonth() + 1); const end = new Date(start); end.setMonth(end.getMonth() + 1); end.setDate(0); return [start, end]; } },
];

const handleDateChange = (val) => {
    if (val) {
        router.get(route('tickets.dashboard'), {
            start_date: val[0],
            end_date: val[1],
        }, { preserveState: true, preserveScroll: true, replace: true });
    }
};
</script>

<template>
    <AppLayout title="Tablero de analíticas">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <h2 class="font-semibold text-base text-gray-800 dark:text-white leading-tight">
                    Tablero de analíticas
                </h2>

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
            <!-- SECCIÓN 1: Desempeño operativo (tickets) -->
            <TicketsPerformance :kpis="kpis" :charts="charts" />

            <el-divider border-style="dashed" />

            <!-- SECCIÓN 2: Rendimiento comercial (CRM) -->
            <CRMRevenue
                :kpis="kpis"
                :charts="charts"
                :tables="tables"
                :currency-mode="currencyMode"
                @update:currency-mode="currencyMode = $event"
            />

            <el-divider border-style="dashed" />

            <!-- SECCIÓN 3: Estado global (sin filtro) -->
            <GlobalState :general="general" :kpis="kpis" />
        </div>
    </AppLayout>
</template>