<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    my_day: Object,
    kpis: Object,
});

const { can } = usePermissions();
const user = usePage().props.auth.user;

const currentDate = new Date().toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });

const formatTime = (dateStr) => {
    return new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(val || 0);
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

            <!-- 2. ACCESOS RÁPIDOS (Según Permisos) -->
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- 3. RESUMEN PERSONAL (Columna Izquierda 2/3) -->
                <div class="lg:col-span-2 space-y-8">
                    
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

                    <!-- Mis Tickets Pendientes -->
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
                                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ ticket.budget?.service_type || 'Servicio' }}</p>
                                            <p class="text-xs text-gray-500">{{ ticket.budget?.customer?.name }}</p>
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

                <!-- 4. RESUMEN GERENCIAL (Columna Derecha 1/3) -->
                <div class="lg:col-span-1 space-y-6">
                    
                    <!-- KPI VENTAS (Solo Permiso CRM) -->
                    <div v-if="can('crm.analytics')" class="bg-gradient-to-br from-white to-gray-50 dark:from-[#1e1e20] dark:to-[#252529] rounded-lg shadow-sm border border-gray-200 dark:border-[#2b2b2e] p-5">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="font-bold text-gray-600 dark:text-gray-300 text-sm uppercase">Ventas del mes</h4>
                            <el-icon class="text-green-500"><TrendCharts /></el-icon>
                        </div>
                        <p class="text-3xl font-bold text-gray-800 dark:text-white">{{ formatCurrency(kpis.crm?.sales_month) }}</p>
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

                    <!-- KPI OPERACIONES (Solo Permiso Tickets) -->
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

                </div>
            </div>

        </div>
    </AppLayout>
</template>