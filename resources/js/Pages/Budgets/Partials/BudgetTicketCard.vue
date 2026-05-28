<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { usePermissions } from '@/Composables/usePermissions';
import { Tools, Lock } from '@element-plus/icons-vue';

const { can } = usePermissions();

const props = defineProps({
    budget: Object,
});

const generatingTicket = ref(false);

const getTicketStatusType = (status) => {
    const map = {
        'Programado': 'info',
        'En proceso': 'warning',
        'En espera': 'warning',
        'Revisión': 'primary',
        'Completado': 'success',
        'Cancelado': 'danger',
    };
    return map[status] || 'info';
};

const generateTicket = () => {
    ElMessageBox.confirm(
        'Se generará un Ticket de servicio automáticamente copiando la información del presupuesto. La fecha de inicio será hoy y el término estimado en 2 semanas.',
        '¿Generar ticket automático?',
        {
            confirmButtonText: 'Sí, generar ticket',
            cancelButtonText: 'Cancelar',
            type: 'info',
        }
    ).then(() => {
        generatingTicket.value = true;
        router.post(route('tickets.store-from-budget', props.budget.id), {}, {
            onSuccess: () => {
                ElMessage.success('Ticket generado correctamente');
                generatingTicket.value = false;
            },
            onError: () => {
                ElMessage.error('No se pudo generar el ticket');
                generatingTicket.value = false;
            },
        });
    }).catch(() => {});
};
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6 relative overflow-hidden">
        <div class="absolute top-0 right-0 p-4 opacity-5">
            <el-icon :size="100"><Tools /></el-icon>
        </div>
        <h3 class="font-bold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2 relative z-10">
            <el-icon><Tools /></el-icon> Ticket de servicio
        </h3>

        <div v-if="budget.ticket" class="relative z-10">
            <div class="flex justify-between items-start mb-3">
                <span class="text-xs text-gray-500">Folio ticket</span>
                <span class="font-mono text-sm font-bold text-gray-800 dark:text-gray-200">{{ budget.ticket.folio }}</span>
            </div>
            <div class="flex justify-between items-center mb-4">
                <span class="text-xs text-gray-500">Estatus operativo</span>
                <el-tag size="small" :type="getTicketStatusType(budget.ticket.status)">
                    {{ budget.ticket.status }}
                </el-tag>
            </div>
            <div class="mb-5">
                <div class="flex justify-between text-xs mb-1">
                    <span class="text-gray-500">Progreso tareas</span>
                    <span class="font-bold text-gray-700 dark:text-gray-300">{{ budget.ticket.progress || 0 }}%</span>
                </div>
                <el-progress
                    :percentage="budget.ticket.progress || 0"
                    :stroke-width="8"
                    :show-text="false"
                    :status="budget.ticket.status === 'Completado' ? 'success' : ''"
                />
            </div>
            <div v-if="can('tickets.index')" class="mt-2">
                <Link :href="route('tickets.show', budget.ticket.id)">
                    <el-button type="primary" plain class="w-full" icon="Right">
                        Ver seguimiento
                    </el-button>
                </Link>
            </div>
            <div v-else class="mt-2 p-2 bg-gray-50 dark:bg-[#252529] rounded text-xs text-center text-gray-500 border border-gray-100 dark:border-[#3f3f46]">
                <el-icon class="mr-1"><Lock /></el-icon> Detalle restringido
            </div>
        </div>

        <div v-else class="relative z-10">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Este proyecto no tiene una orden de servicio (Ticket) activa. Se creará automáticamente con la información actual.
            </p>
            <el-button
                type="primary"
                color="#f26c17"
                class="w-full"
                icon="Plus"
                @click="generateTicket"
                :loading="generatingTicket"
            >
                Generar ticket automático
            </el-button>
        </div>
    </div>
</template>
