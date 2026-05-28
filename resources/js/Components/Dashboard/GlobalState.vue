<script setup>
import { Histogram, Box, List, UserFilled } from '@element-plus/icons-vue';

defineProps({
    general: Object,
    kpis: Object,
});
</script>

<template>
    <section class="opacity-90">
        <div class="flex items-center gap-2 mb-4">
            <el-icon class="text-gray-500"><Histogram /></el-icon>
            <h3 class="text-lg font-bold text-gray-600 dark:text-gray-300">Estado global actual (Tiempo real)</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Backlog total -->
            <div class="bg-gray-50 dark:bg-[#18181b] p-6 rounded-xl border border-gray-200 dark:border-[#2b2b2e] flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Backlog total</p>
                    <h3 class="text-3xl font-bold text-gray-700 dark:text-gray-200 mt-1">{{ general.backlog }}</h3>
                    <p class="text-xs text-gray-400 mt-1">Tickets activos (No completados)</p>
                </div>
                <div class="bg-white dark:bg-[#27272a] p-3 rounded-full shadow-sm text-orange-400">
                    <el-icon :size="24"><Box /></el-icon>
                </div>
            </div>

            <!-- Tareas pendientes -->
            <div class="bg-gray-50 dark:bg-[#18181b] p-6 rounded-xl border border-gray-200 dark:border-[#2b2b2e] flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Actividades pendientes</p>
                    <h3 class="text-3xl font-bold text-gray-700 dark:text-gray-200 mt-1">{{ general.pending_tasks }}</h3>
                    <p class="text-xs text-gray-400 mt-1">Tareas individuales sin terminar</p>
                </div>
                <div class="bg-white dark:bg-[#27272a] p-3 rounded-full shadow-sm text-blue-400">
                    <el-icon :size="24"><List /></el-icon>
                </div>
            </div>

            <!-- Clientes totales -->
            <div class="bg-gray-50 dark:bg-[#18181b] p-6 rounded-xl border border-gray-200 dark:border-[#2b2b2e] flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Base de clientes total</p>
                    <h3 class="text-3xl font-bold text-gray-700 dark:text-gray-200 mt-1">{{ kpis.total_customers }}</h3>
                    <p class="text-xs text-gray-400 mt-1">Clientes registrados históricamente</p>
                </div>
                <div class="bg-white dark:bg-[#27272a] p-3 rounded-full shadow-sm">
                    <el-icon :size="24" class="text-gray-400"><UserFilled /></el-icon>
                </div>
            </div>

            <!-- Proyectos activos -->
            <div class="bg-gray-50 dark:bg-[#18181b] p-6 rounded-xl border border-gray-200 dark:border-[#2b2b2e] flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Carga de trabajo actual</p>
                    <h3 class="text-3xl font-bold text-gray-700 dark:text-gray-200 mt-1">{{ kpis.active_projects }}</h3>
                    <p class="text-xs text-gray-400 mt-1">Proyectos "En proceso" actualmente</p>
                </div>
                <div class="bg-white dark:bg-[#27272a] p-3 rounded-full shadow-sm">
                    <el-icon :size="24" class="text-gray-400"><List /></el-icon>
                </div>
            </div>
        </div>

        <!-- Técnicos con más pendientes -->
        <div class="mt-6 bg-white dark:bg-[#1e1e20] p-6 rounded-xl border border-gray-200 dark:border-[#2b2b2e] shadow-sm">
            <h4 class="text-sm font-bold text-gray-600 dark:text-gray-300 mb-3">Técnicos con más pendientes</h4>
            <ul class="space-y-3">
                <li
                    v-for="(tech, index) in general.busy_techs"
                    :key="index"
                    class="flex justify-between items-center text-sm"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-gray-400 font-mono text-xs">{{ index + 1 }}.</span>
                        <span class="text-gray-700 dark:text-gray-300">{{ tech.name }}</span>
                    </div>
                    <span class="bg-red-50 text-red-600 px-2 py-0.5 rounded text-xs font-bold">{{ tech.pending_count }} tareas</span>
                </li>
                <li v-if="general.busy_techs.length === 0" class="text-xs text-gray-400 text-center">Todo al día</li>
            </ul>
        </div>
    </section>
</template>
