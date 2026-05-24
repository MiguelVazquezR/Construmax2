<script setup>
import { WarningFilled, CircleCheckFilled } from '@element-plus/icons-vue';
import { Link } from '@inertiajs/vue3';
import KpiCard from '@/Components/Dashboard/KpiCard.vue';

defineProps({
    kpis: Object,
    tables: Object,
});
</script>

<template>
    <div class="space-y-6">
        <h3 class="text-lg font-bold text-gray-800 dark:text-white">Pagos a técnicos externos</h3>

        <!-- KPIs rápidos -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <KpiCard
                label="Completaron y falta pago"
                :value="kpis.techs_completed_unpaid"
                subtitle="Técnicos externos sin pago registrado"
                :icon="WarningFilled"
                icon-bg="bg-orange-50 dark:bg-orange-900/20"
                icon-color="text-orange-500"
                value-color="text-orange-500"
            />
            <KpiCard
                label="Con tareas pendientes"
                :value="kpis.techs_pending_tasks"
                subtitle="Técnicos externos que no han terminado"
                :icon="CircleCheckFilled"
                icon-bg="bg-blue-50 dark:bg-blue-900/20"
                icon-color="text-blue-500"
            />
        </div>

        <!-- Tablas lado a lado -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Técnicos que terminaron sin pago -->
            <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <h4 class="text-sm font-bold text-orange-600 dark:text-orange-400 mb-3">Falta registrar pago</h4>
                <div v-if="tables.external_techs_completed.length === 0" class="text-xs text-gray-400 text-center py-4">
                    Todos al corriente
                </div>
                <div v-else class="space-y-4">
                    <div
                        v-for="tech in tables.external_techs_completed"
                        :key="tech.id"
                        class="border border-gray-100 dark:border-[#2b2b2e] rounded-lg p-3"
                    >
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-semibold text-gray-800 dark:text-gray-200 text-sm">{{ tech.name }}</span>
                            <span class="text-xs text-gray-400">
                                {{ tech.completed_tickets }} tickets · {{ tech.completed_tasks }} tareas
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <Link
                                v-for="ticket in tech.tickets"
                                :key="ticket.id"
                                :href="route('tickets.edit', ticket.id)"
                                class="text-xs bg-gray-50 dark:bg-[#252529] hover:bg-orange-50 dark:hover:bg-orange-900/20 px-2 py-1 rounded border border-gray-200 dark:border-[#3f3f46] text-orange-600 dark:text-orange-400 hover:border-orange-300 transition-colors"
                            >
                                {{ ticket.folio }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Técnicos con tareas pendientes -->
            <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <h4 class="text-sm font-bold text-blue-600 dark:text-blue-400 mb-3">Tareas sin terminar</h4>
                <div v-if="tables.external_techs_pending.length === 0" class="text-xs text-gray-400 text-center py-4">
                    Todo al día
                </div>
                <div v-else class="space-y-4">
                    <div
                        v-for="tech in tables.external_techs_pending"
                        :key="tech.id"
                        class="border border-gray-100 dark:border-[#2b2b2e] rounded-lg p-3"
                    >
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-semibold text-gray-800 dark:text-gray-200 text-sm">{{ tech.name }}</span>
                            <span class="text-xs text-gray-400">
                                {{ tech.pending_tickets }} tickets · {{ tech.pending_tasks }} tareas
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <Link
                                v-for="ticket in tech.tickets"
                                :key="ticket.id"
                                :href="route('tickets.edit', ticket.id)"
                                class="text-xs bg-gray-50 dark:bg-[#252529] hover:bg-blue-50 dark:hover:bg-blue-900/20 px-2 py-1 rounded border border-gray-200 dark:border-[#3f3f46] text-blue-600 dark:text-blue-400 hover:border-blue-300 transition-colors"
                            >
                                {{ ticket.folio }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
