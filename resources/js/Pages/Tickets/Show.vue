<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TicketTasks from '@/Pages/Tickets/Partials/TicketTasks.vue';
import TicketInfo from '@/Pages/Tickets/Partials/TicketInfo.vue';
import TicketTimeline from '@/Pages/Tickets/Partials/TicketTimeline.vue';

const props = defineProps({
    ticket: Object,
    users: Array, // Para asignar tareas en el componente de tareas
});

const activeTab = ref('tasks');

const getStatusColor = (status) => {
    const map = {
        'Programado': 'info',
        'En proceso': 'primary',
        'En espera': 'warning',
        'Revisión': 'warning',
        'Completado': 'success',
        'Cancelado': 'danger'
    };
    return map[status] || 'info';
};
</script>

<template>
    <AppLayout :title="`Ticket #${ticket.id}`">
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-3">
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                        Ticket de servicio #{{ ticket.id }}
                    </h2>
                    <el-tag :type="getStatusColor(ticket.status)" effect="dark" size="large">
                        {{ ticket.status }}
                    </el-tag>
                </div>
                
                <div class="flex gap-2">
                    <Link :href="route('tickets.index')">
                        <el-button icon="Back">Volver</el-button>
                    </Link>
                    <Link :href="route('tickets.edit', ticket.id)">
                        <el-button type="primary" color="#f26c17" icon="Edit">
                            Editar
                        </el-button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">
            
            <!-- TARJETA RESUMEN SUPERIOR -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                <div class="flex flex-col md:flex-row gap-6 justify-between items-center">
                    
                    <!-- Info Básica -->
                    <div class="flex items-center gap-4 w-full md:w-auto">
                        <div class="size-14 flex items-center justify-center bg-orange-50 dark:bg-orange-900/20 rounded-full text-primary">
                            <el-icon :size="32"><Tools /></el-icon>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white leading-tight">
                                {{ ticket.budget?.service_type }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Cliente: {{ ticket.budget?.customer?.name }}
                            </p>
                        </div>
                    </div>

                    <!-- Responsable -->
                    <div class="flex items-center gap-3 w-full md:w-auto bg-gray-50 dark:bg-[#252529] px-4 py-2 rounded-lg border border-gray-100 dark:border-[#3f3f46]">
                        <span class="text-xs text-gray-400 uppercase font-bold">Técnico Responsable</span>
                        <div class="flex items-center gap-2">
                            <el-avatar :size="28" class="!text-xs bg-white text-gray-600 border">
                                {{ ticket.responsible?.name?.charAt(0) }}
                            </el-avatar>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ ticket.responsible?.name }}
                            </span>
                        </div>
                    </div>

                    <!-- Progreso General -->
                    <div class="w-full md:w-1/3">
                        <div class="flex justify-between mb-1">
                            <span class="text-xs text-gray-500">Progreso de tareas</span>
                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ ticket.progress }}%</span>
                        </div>
                        <el-progress 
                            :percentage="ticket.progress" 
                            :show-text="false" 
                            :stroke-width="10" 
                            :color="ticket.progress === 100 ? '#67c23a' : '#f26c17'"
                        />
                    </div>
                </div>
            </div>

            <!-- CONTENIDO EN PESTAÑAS -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] min-h-[500px]">
                <el-tabs v-model="activeTab" class="px-6 pt-2">
                    
                    <!-- TAB 1: GESTIÓN DE TAREAS -->
                    <el-tab-pane label="Tareas y seguimiento" name="tasks">
                        <TicketTasks :ticket="ticket" :users="users" />
                    </el-tab-pane>

                    <!-- TAB 2: INFORMACIÓN DETALLADA -->
                    <el-tab-pane label="Información general" name="info">
                        <TicketInfo :ticket="ticket" />
                    </el-tab-pane>

                    <!-- TAB 3: CRONOGRAMA -->
                    <el-tab-pane label="Cronograma" name="timeline">
                        <TicketTimeline :ticket="ticket" />
                    </el-tab-pane>

                </el-tabs>
            </div>

        </div>
    </AppLayout>
</template>