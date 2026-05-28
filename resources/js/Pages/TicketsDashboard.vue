<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TicketsPerformance from '@/Components/Dashboard/TicketsPerformance.vue';
import CRMRevenue from '@/Components/Dashboard/CRMRevenue.vue';
import GlobalState from '@/Components/Dashboard/GlobalState.vue';
import RegionDistribution from '@/Components/Dashboard/RegionDistribution.vue';
import InvoiceStatus from '@/Components/Dashboard/InvoiceStatus.vue';
import TechnicianPayments from '@/Components/Dashboard/TechnicianPayments.vue';

const props = defineProps({
    customers: Array,
    sellers: Array,
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

// --- Filtro de cliente ---
const selectedCustomer = ref(props.filters.customer_id || '');

// --- Filtro de vendedor ---
const selectedSeller = ref(props.filters.seller_id || '');

const shortcuts = [
    { text: 'Hoy', value: () => [new Date(), new Date()] },
    { text: 'Últimos 7 días', value: () => { const end = new Date(); const start = new Date(); start.setTime(start.getTime() - 3600 * 1000 * 24 * 7); return [start, end]; } },
    { text: 'Este mes', value: () => { const end = new Date(); const start = new Date(); start.setDate(1); return [start, end]; } },
    { text: 'Mes pasado', value: () => { const end = new Date(); const start = new Date(); start.setMonth(start.getMonth() - 1); start.setDate(1); end.setDate(0); return [start, end]; } },
    { text: 'Próximo mes', value: () => { const start = new Date(); start.setDate(1); start.setMonth(start.getMonth() + 1); const end = new Date(start); end.setMonth(end.getMonth() + 1); end.setDate(0); return [start, end]; } },
];

const applyFilters = () => {
    router.get(route('tickets.dashboard'), {
        start_date: dateRange.value[0],
        end_date: dateRange.value[1],
        customer_id: selectedCustomer.value || null,
        seller_id: selectedSeller.value || null,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const handleDateChange = (val) => {
    if (val) applyFilters();
};

const handleCustomerChange = () => {
    applyFilters();
};

const handleSellerChange = () => {
    applyFilters();
};
</script>

<template>
    <AppLayout title="Tablero de analíticas">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <h2 class="font-semibold text-base text-gray-800 dark:text-white leading-tight">
                    Tablero de analíticas
                </h2>

                <div class="w-full md:w-auto flex items-center gap-3">
                    <!-- Filtro de cliente -->
                    <el-select
                        v-model="selectedCustomer"
                        placeholder="Todos los clientes"
                        clearable
                        size="large"
                        class="!w-[220px]"
                        @change="handleCustomerChange"
                    >
                        <el-option
                            v-for="c in customers"
                            :key="c.id"
                            :label="c.name"
                            :value="c.id"
                        />
                    </el-select>

                    <!-- Filtro de vendedor -->
                    <el-select
                        v-model="selectedSeller"
                        placeholder="Todos los vendedores"
                        clearable
                        size="large"
                        class="!w-[220px]"
                        @change="handleSellerChange"
                    >
                        <el-option
                            v-for="s in sellers"
                            :key="s.id"
                            :label="s.name"
                            :value="s.id"
                        />
                    </el-select>

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

            <!-- SECCIÓN 3: Distribución geográfica + Facturas -->
            <RegionDistribution :regions="charts.regions" />

            <el-divider border-style="dashed" />

            <!-- SECCIÓN 4: Facturas emitidas vs pagadas -->
            <InvoiceStatus :invoices="charts.invoices" />

            <el-divider border-style="dashed" />

            <!-- SECCIÓN 5: Pagos a técnicos externos -->
            <TechnicianPayments :kpis="kpis" :tables="tables" />

            <el-divider border-style="dashed" />

            <!-- SECCIÓN 6: Estado global (sin filtro) -->
            <GlobalState :general="general" :kpis="kpis" />
        </div>
    </AppLayout>
</template>