<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';

// Iconos
import { 
    Calendar, Plus, DocumentAdd, User, Setting, 
    Clock, Tools, TrendCharts, List, Warning, 
    Money, Document 
} from '@element-plus/icons-vue';

const props = defineProps({
    my_day: Object,
    kpis: Object,
});

const { can } = usePermissions();
const user = usePage().props.auth.user;

// Control del filtro de moneda
const currencyMode = ref('MXN');

const currentDate = new Date().toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });

const formatTime = (dateStr) => {
    return new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

// Formateador
const formatCurrency = (val, currency) => {
    return new Intl.NumberFormat('es-MX', { 
        style: 'currency', 
        currency: currency, 
        maximumFractionDigits: 2 
    }).format(val || 0);
};

// Computed para mostrar el valor correcto según el filtro seleccionado
const currentSalesDisplay = computed(() => {
    if (!props.kpis.crm) return 0;
    
    // Aquí mostramos DIRECTAMENTE la bolsa correspondiente
    return currencyMode.value === 'MXN' 
        ? props.kpis.crm.sales_month_mxn 
        : props.kpis.crm.sales_month_usd;
});

// Helper para obtener el nombre del cliente desde un budget
const getBudgetCustomerName = (budget) => {
    return budget.ticket?.customer?.name || 'Sin cliente';
};

const getBudgetServiceType = (budget) => {
    return budget.ticket?.name || 'Sin nombre';
};
</script>

<template>
    <AppLayout title="Panel Principal">
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-8">
            
            <!-- 1. BIENVENIDA -->
            <div class="bg-gradient-to-r from-orange-500 to-orange-400 rounded-xl p-6 text-white shadow-md flex flex-col md:flex-row justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">¡Hola, {{ user.name }}!</h1>
                    <p class="opacity-90 mt-1 capitalize">{{ currentDate }}</p>
                </div>
                <div class="mt-4 md:mt-0 flex gap-3">
                    <Link v-if="can('calendar.create')" :href="route('calendar.index')">
                        <button class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg text-sm font-medium transition backdrop-blur-sm flex items-center gap-2">
                            <el-icon><Calendar /></el-icon> Ver Agenda
                        </button>
                    </Link>
                </div>
            </div>

            <!-- 2. ACCESOS RÁPIDOS -->
            <div>
                <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-3">Accesos Rápidos</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <Link v-if="can('tickets.create')" :href="route('tickets.create')" class="group">
                        <div class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] hover:shadow-md transition text-center">
                            <div class="w-10 h-10 mx-auto bg-blue-50 text-blue-500 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition">
                                <el-icon :size="20"><Plus /></el-icon>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Nuevo ticket</span>
                        </div>
                    </Link>
                    <Link v-if="can('budgets.create')" :href="route('budgets.create')" class="group">
                        <div class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] hover:shadow-md transition text-center">
                            <div class="w-10 h-10 mx-auto bg-green-50 text-green-500 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition">
                                <el-icon :size="20"><DocumentAdd /></el-icon>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Presupuesto</span>
                        </div>
                    </Link>
                    <Link v-if="can('customers.create')" :href="route('customers.create')" class="group">
                        <div class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] hover:shadow-md transition text-center">
                            <div class="w-10 h-10 mx-auto bg-purple-50 text-purple-500 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition">
                                <el-icon :size="20"><User /></el-icon>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Nuevo cliente</span>
                        </div>
                    </Link>
                    <Link v-if="can('users.create')" :href="route('users.create')" class="group">
                        <div class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] hover:shadow-md transition text-center">
                            <div class="w-10 h-10 mx-auto bg-gray-50 text-gray-500 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition">
                                <el-icon :size="20"><Setting /></el-icon>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Alta usuario</span>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- 3. RESUMEN PERSONAL -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Mi Agenda -->
                <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                    <div class="p-4 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#2b2b2e] flex justify-between items-center">
                        <h3 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                            <el-icon><Clock /></el-icon> Mi agenda de hoy
                        </h3>
                        <Link :href="route('calendar.index')" class="text-xs text-primary hover:underline">Ver calendario completo</Link>
                    </div>
                    <div class="p-4">
                        <ul v-if="my_day.events.length > 0" class="space-y-3">
                            <li v-for="event in my_day.events" :key="event.id" class="flex items-start gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-[#252529] transition">
                                <div class="w-12 text-center">
                                    <span class="block text-xs font-bold text-gray-500">{{ formatTime(event.start_time) }}</span>
                                </div>
                                <div class="w-1 h-10 rounded-full" :class="event.type === 'Reunión' ? 'bg-blue-400' : 'bg-orange-400'"></div>
                                <div>
                                    <p class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ event.title }}</p>
                                    <p class="text-xs text-gray-500">{{ event.type }}</p>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-gray-400 text-center py-4">No tienes eventos programados para hoy.</p>
                    </div>
                </div>

                <!-- Mis Tickets -->
                <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                    <div class="p-4 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#2b2b2e] flex justify-between items-center">
                        <h3 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                            <el-icon><Tools /></el-icon> Mis tickets asignados
                        </h3>
                        <Link :href="route('tickets.index')" class="text-xs text-primary hover:underline">Ver todos</Link>
                    </div>
                    <div class="p-4">
                        <div v-if="my_day.tickets.length > 0" class="space-y-3">
                            <div v-for="ticket in my_day.tickets" :key="ticket.id" class="flex items-center justify-between p-3 border border-gray-100 dark:border-[#3f3f46] rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded bg-gray-100 dark:bg-[#3f3f46]">
                                        <span class="text-xs font-mono font-bold">#{{ ticket.id }}</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ ticket.service_type || ticket.name || 'Servicio' }}</p>
                                        <p class="text-xs text-gray-500">{{ ticket.customer?.name }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <el-tag size="small" :type="ticket.priority === 'Urgente' ? 'danger' : 'warning'">{{ ticket.priority }}</el-tag>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-400 text-center py-4">¡Estás al día! No tienes tickets pendientes.</p>
                    </div>
                </div>
            </div>

            <!-- 4. KPIs GERENCIALES -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <!-- KPI VENTAS -->
                <div v-if="can('crm.analytics')" class="bg-gradient-to-br from-white to-gray-50 dark:from-[#1e1e20] dark:to-[#252529] rounded-lg shadow-sm border border-gray-200 dark:border-[#2b2b2e] p-5">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h4 class="font-bold text-gray-600 dark:text-gray-300 text-sm uppercase">Ventas del mes</h4>
                            <p class="text-xs text-gray-400">Total cobrado en {{ currencyMode }}</p>
                        </div>
                        <!-- SWITCH DE FILTRO (MXN / USD) -->
                        <div class="bg-gray-100 dark:bg-[#3f3f46] p-1 rounded-lg flex text-xs font-bold">
                            <button 
                                @click="currencyMode = 'MXN'"
                                class="px-2 py-1 rounded transition-colors"
                                :class="currencyMode === 'MXN' ? 'bg-white dark:bg-[#1e1e20] shadow text-green-600' : 'text-gray-400 hover:text-gray-600'"
                            >MXN</button>
                            <button 
                                @click="currencyMode = 'USD'"
                                class="px-2 py-1 rounded transition-colors"
                                :class="currencyMode === 'USD' ? 'bg-white dark:bg-[#1e1e20] shadow text-green-600' : 'text-gray-400 hover:text-gray-600'"
                            >USD</button>
                        </div>
                    </div>

                    <!-- Monto Dinámico (Solo suma la moneda seleccionada) -->
                    <p class="text-3xl font-bold text-gray-800 dark:text-white transition-all duration-300">
                        {{ formatCurrency(currentSalesDisplay, currencyMode) }}
                    </p>
                    
                    <div class="mt-4 flex gap-4 text-xs">
                        <div>
                            <span class="block font-bold text-gray-700 dark:text-gray-300">{{ kpis.crm?.customers_month }}</span>
                            <span class="text-gray-500">Nuevos clientes</span>
                        </div>
                        <div>
                            <span class="block font-bold text-gray-700 dark:text-gray-300">{{ kpis.crm?.budgets_pending }}</span>
                            <span class="text-gray-500">Presup. pendientes</span>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-[#3f3f46] text-center">
                        <Link :href="route('crm.dashboard')" class="text-sm text-primary font-medium hover:underline">Ver analíticas completas</Link>
                    </div>
                </div>

                <!-- KPI OPERACIONES -->
                <div v-if="can('tickets.analytics')" class="bg-gradient-to-br from-white to-gray-50 dark:from-[#1e1e20] dark:to-[#252529] rounded-lg shadow-sm border border-gray-200 dark:border-[#2b2b2e] p-5">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="font-bold text-gray-600 dark:text-gray-300 text-sm uppercase">Operaciones activas</h4>
                        <el-icon class="text-blue-500"><List /></el-icon>
                    </div>
                    <p class="text-3xl font-bold text-gray-800 dark:text-white">{{ kpis.ops?.active_tickets }}</p>
                    <p class="text-xs text-gray-500">Tickets en proceso actualmente</p>
                    
                    <div v-if="kpis.ops?.overdue_tickets > 0" class="mt-4 bg-red-50 dark:bg-red-900/20 p-3 rounded text-red-600 text-xs font-bold flex items-center gap-2">
                        <el-icon><Warning /></el-icon>
                        {{ kpis.ops?.overdue_tickets }} Tickets vencidos
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-[#3f3f46] text-center">
                        <Link :href="route('tickets.dashboard')" class="text-sm text-primary font-medium hover:underline">Ver tablero operativo</Link>
                    </div>
                </div>

                <!-- KPI COSTOS (Cotización) -->
                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-[#1e1e20] dark:to-[#252529] rounded-lg shadow-sm border border-gray-200 dark:border-[#2b2b2e] p-5">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="font-bold text-gray-600 dark:text-gray-300 text-sm uppercase">Costos</h4>
                        <el-icon class="text-purple-500"><Money /></el-icon>
                    </div>
                    <p class="text-3xl font-bold text-gray-800 dark:text-white">{{ kpis.costs?.total || 0 }}</p>
                    <p class="text-xs text-gray-500">Presupuestos en cotización</p>

                    <div v-if="kpis.costs?.budgets?.length > 0" class="mt-4 space-y-2">
                        <div v-for="budget in kpis.costs.budgets" :key="budget.id" class="flex items-center justify-between p-2 bg-purple-50 dark:bg-purple-900/10 rounded text-xs">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-700 dark:text-gray-300 truncate">{{ getBudgetServiceType(budget) }}</p>
                                <p class="text-gray-500 truncate">{{ getBudgetCustomerName(budget) }}</p>
                            </div>
                            <Link :href="route('budgets.show', budget.id)" class="text-primary hover:underline ml-2 shrink-0">Ver</Link>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-[#3f3f46] text-center">
                        <Link :href="route('budgets.index', { status: 'Cotización' })" class="text-sm text-primary font-medium hover:underline">Ver todos en cotización</Link>
                    </div>
                </div>

                <!-- KPI FACTURACIÓN -->
                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-[#1e1e20] dark:to-[#252529] rounded-lg shadow-sm border border-gray-200 dark:border-[#2b2b2e] p-5">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="font-bold text-gray-600 dark:text-gray-300 text-sm uppercase">Facturación</h4>
                        <el-icon class="text-orange-500"><Document /></el-icon>
                    </div>
                    <p class="text-3xl font-bold text-gray-800 dark:text-white">{{ kpis.invoicing?.total || 0 }}</p>
                    <p class="text-xs text-gray-500">Presupuestos pendientes de facturar</p>

                    <div v-if="kpis.invoicing?.budgets?.length > 0" class="mt-4 space-y-2">
                        <div v-for="budget in kpis.invoicing.budgets" :key="budget.id" class="flex items-center justify-between p-2 bg-orange-50 dark:bg-orange-900/10 rounded text-xs">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-700 dark:text-gray-300 truncate">{{ getBudgetServiceType(budget) }}</p>
                                <p class="text-gray-500 truncate">{{ getBudgetCustomerName(budget) }}</p>
                            </div>
                            <Link :href="route('budgets.show', budget.id)" class="text-primary hover:underline ml-2 shrink-0">Ver</Link>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-[#3f3f46] text-center">
                        <Link :href="route('budgets.index', { status: 'Facturación' })" class="text-sm text-primary font-medium hover:underline">Ver todos en facturación</Link>
                    </div>
                </div>

            </div>

        </div>
    </AppLayout>
</template>