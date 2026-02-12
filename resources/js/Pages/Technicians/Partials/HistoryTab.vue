<script setup>
import { router } from '@inertiajs/vue3';
import { View, UserFilled, Avatar } from '@element-plus/icons-vue';

const props = defineProps({
    tickets: Array,
    technicianId: Number // ID del usuario técnico actual
});

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

const getTicketStatusType = (status) => {
    const map = {
        'Programado': 'info',
        'En proceso': 'primary',
        'Completado': 'success',
        'Cancelado': 'danger'
    };
    return map[status] || 'warning';
};

const navigateToTicket = (ticketId) => {
    router.visit(route('tickets.show', ticketId));
};
</script>

<template>
    <div class="py-4">
        <div v-if="tickets.length > 0">
            <el-table 
                :data="tickets" 
                style="width: 100%" 
                stripe 
                @row-click="(row) => navigateToTicket(row.id)" 
                row-class-name="cursor-pointer"
            >
                <el-table-column prop="id" label="ID" width="80">
                    <template #default="scope">
                        <span class="font-bold text-gray-500">#{{ scope.row.id }}</span>
                    </template>
                </el-table-column>
                
                <el-table-column label="Rol" width="130">
                    <template #default="scope">
                        <el-tag 
                            v-if="scope.row.user_id === technicianId" 
                            type="warning" 
                            effect="plain" 
                            size="small"
                        >
                            <el-icon><Avatar /></el-icon> Responsable
                        </el-tag>
                        <el-tag 
                            v-else 
                            type="info" 
                            effect="plain" 
                            size="small"
                        >
                            <el-icon><UserFilled /></el-icon> Apoyo
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="Cliente / Proyecto" min-width="250">
                    <template #default="scope">
                        <div>
                            <div class="font-bold text-gray-800 dark:text-gray-200">
                                {{ scope.row.budget?.customer?.name || 'Cliente General' }}
                            </div>
                            <div class="text-xs text-gray-500 truncate">{{ scope.row.instructions || 'Sin descripción' }}</div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="Fecha" width="150">
                    <template #default="scope">
                        <div class="text-sm">
                            {{ formatDate(scope.row.scheduled_start) }}
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="Estatus" width="120" align="center">
                    <template #default="scope">
                        <el-tag :type="getTicketStatusType(scope.row.status)" size="small" effect="plain">
                            {{ scope.row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column width="60" align="center">
                    <template #default>
                        <el-icon class="text-gray-400"><View /></el-icon>
                    </template>
                </el-table-column>
            </el-table>
        </div>
        <el-empty v-else description="No hay tickets asignados a este técnico aún." />
    </div>
</template>

<style scoped>
:deep(.cursor-pointer) {
    cursor: pointer;
}
</style>