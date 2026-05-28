<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { Back, Edit, User, Ticket } from '@element-plus/icons-vue';

const { can } = usePermissions();

defineProps({
    user: Object,
});

const activeTab = ref('general');

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const day = date.toLocaleDateString('es-ES', { day: '2-digit' });
    const month = date.toLocaleDateString('es-ES', { month: 'short' });
    const year = date.getFullYear();
    return `${day} ${month}, ${year}`;
};

const getStatusColor = (status) => {
    const map = {
        'Borrador': 'info',
        'Levantamiento': 'warning',
        'Catálogo': 'primary',
        'Proceso de ejecución': 'warning',
        'Ejecutado': 'success',
        'Facturado': 'primary',
        'Pagado': 'success',
        'Cancelado': 'danger'
    };
    return map[status] || 'info';
};

const getPriorityColor = (priority) => {
    const map = {
        'Baja': 'info',
        'Media': 'warning',
        'Alta': 'danger',
        'Urgente': 'danger'
    };
    return map[priority] || 'info';
};

const navigateToTicket = (row) => {
    router.visit(route('tickets.show', row.id));
};
</script>

<template>
    <AppLayout :title="`Usuario: ${user.name}`">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('users.index')">
                        <el-button :icon="Back" circle plain />
                    </Link>
                    <div>
                        <h2 class="font-semibold text-base text-gray-800 dark:text-white leading-tight">
                            Perfil del usuario
                        </h2>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link v-if="can('users.edit')" :href="route('users.edit', user.id)">
                        <el-button type="primary" color="#f26c17" :icon="Edit">
                            Editar usuario
                        </el-button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">

            <!-- HEADER RESUMEN (HERO CARD) -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="flex items-center gap-5">
                    <el-avatar :size="56" :src="user.profile_photo_url" class="border-2 border-gray-200 dark:border-gray-700 shrink-0" />
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ user.name }}</h3>
                            <el-tag :type="user.is_active ? 'success' : 'danger'" size="small" effect="dark" class="rounded-full">
                                {{ user.is_active ? 'Activo' : 'Baja' }}
                            </el-tag>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm">
                            {{ user.email }}
                        </p>
                    </div>
                </div>

                <div class="flex gap-6 md:gap-10 text-left md:text-right border-t md:border-t-0 border-gray-100 dark:border-gray-800 pt-4 md:pt-0 w-full md:w-auto">
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-1">Departamento</p>
                        <p class="text-lg font-bold text-gray-800 dark:text-white">{{ user.employee?.department || 'No asignado' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-1">Puesto</p>
                        <p class="text-lg font-bold text-blue-600 dark:text-blue-400">
                            {{ user.employee?.position || 'No asignado' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO ORGANIZADO EN PESTAÑAS -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-tabs v-model="activeTab" class="px-2 pt-2 custom-tabs">

                    <!-- PESTAÑA 1: INFORMACIÓN GENERAL -->
                    <el-tab-pane name="general">
                        <template #label>
                            <span class="flex items-center gap-2 px-2">
                                <el-icon><User /></el-icon> Información general
                            </span>
                        </template>
                        <div class="p-6">
                            <el-descriptions border :column="2" size="large" class="custom-descriptions">
                                <el-descriptions-item label="Nombre">
                                    <span class="font-medium">{{ user.name }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item label="Email">
                                    <span class="font-mono">{{ user.email }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item label="Departamento">
                                    {{ user.employee?.department || 'No asignado' }}
                                </el-descriptions-item>
                                <el-descriptions-item label="Puesto">
                                    {{ user.employee?.position || 'No asignado' }}
                                </el-descriptions-item>
                                <el-descriptions-item label="Teléfono">
                                    {{ user.employee?.phone || 'No asignado' }}
                                </el-descriptions-item>
                                <el-descriptions-item label="Rol asignado">
                                    <div class="flex flex-wrap gap-1">
                                        <el-tag
                                            v-for="role in user.roles"
                                            :key="role.id"
                                            size="small"
                                            type="info"
                                            effect="plain"
                                        >
                                            {{ role.name }}
                                        </el-tag>
                                        <span v-if="!user.roles || user.roles.length === 0" class="text-xs text-gray-400 italic">Sin rol</span>
                                    </div>
                                </el-descriptions-item>
                                <el-descriptions-item label="Fecha de registro">
                                    {{ formatDate(user.created_at) }}
                                </el-descriptions-item>
                                <el-descriptions-item label="ID de usuario">
                                    <span class="font-mono">{{ user.id }}</span>
                                </el-descriptions-item>
                            </el-descriptions>
                        </div>
                    </el-tab-pane>

                    <!-- PESTAÑA 2: TICKETS A CARGO -->
                    <el-tab-pane name="tickets">
                        <template #label>
                            <span class="flex items-center gap-2 px-2">
                                <el-icon><Ticket /></el-icon> Tickets a cargo
                                <el-tag v-if="user.tickets_as_seller?.length" size="small" type="primary" class="ml-1 rounded-full">{{ user.tickets_as_seller.length }}</el-tag>
                            </span>
                        </template>
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-6">
                                <div>
                                    <h4 class="font-bold text-gray-800 dark:text-gray-200 text-lg">Tickets como vendedor</h4>
                                    <p class="text-sm text-gray-500">Tickets en los que este usuario figura como vendedor responsable.</p>
                                </div>
                            </div>

                            <div v-if="user.tickets_as_seller && user.tickets_as_seller.length > 0" class="border border-gray-200 dark:border-gray-800 rounded-lg overflow-hidden">
                                <el-table
                                    :data="user.tickets_as_seller"
                                    style="width: 100%"
                                    stripe
                                    @row-click="navigateToTicket"
                                    row-class-name="cursor-pointer hover:bg-gray-50 dark:hover:bg-[#27272a] transition-colors"
                                >
                                    <el-table-column label="Folio" width="100">
                                        <template #default="scope">
                                            <span class="font-mono font-bold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                                                {{ scope.row.folio || `#${scope.row.id}` }}
                                            </span>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Proyecto / Servicio" min-width="220">
                                        <template #default="scope">
                                            <div class="font-bold text-gray-800 dark:text-gray-200 truncate">{{ scope.row.name }}</div>
                                            <div class="text-xs text-gray-500 truncate">{{ scope.row.service_type }}</div>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Sucursal" min-width="180">
                                        <template #default="scope">
                                            <div v-if="scope.row.branch" class="text-sm text-gray-700 dark:text-gray-300">
                                                <div class="font-semibold truncate">{{ scope.row.branch.branch_name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                    {{ scope.row.branch.region }}, {{ scope.row.branch.country }}
                                                </div>
                                            </div>
                                            <span v-else class="text-sm text-gray-400 italic">General</span>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Fecha inicio" width="120">
                                        <template #default="scope">
                                            <div class="text-sm font-mono text-gray-600 dark:text-gray-400">
                                                {{ formatDate(scope.row.scheduled_start) }}
                                            </div>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Prioridad" width="100" align="center">
                                        <template #default="scope">
                                            <el-tag :type="getPriorityColor(scope.row.priority)" size="small" effect="plain" class="w-full text-center">
                                                {{ scope.row.priority }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Estatus" width="140" align="center">
                                        <template #default="scope">
                                            <el-tag :type="getStatusColor(scope.row.status)" size="small" effect="dark" class="w-full border-none">
                                                {{ scope.row.status }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </div>

                            <div v-else class="text-center py-12 bg-gray-50 dark:bg-[#252529]/50 rounded-lg border border-dashed border-gray-300 dark:border-gray-700">
                                <el-empty description="No hay tickets asignados" :image-size="100">
                                    <template #default>
                                        <p class="text-sm text-gray-500">Este usuario aún no tiene tickets como vendedor.</p>
                                    </template>
                                </el-empty>
                            </div>
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </div>

        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.cursor-pointer) {
    cursor: pointer;
}

:deep(.custom-tabs .el-tabs__nav-wrap::after) {
    background-color: var(--el-border-color-light);
}
:global(.dark) :deep(.custom-tabs .el-tabs__nav-wrap::after) {
    background-color: #3f3f46;
}

:global(.dark) :deep(.el-descriptions__body),
:global(.dark) :deep(.el-descriptions__label),
:global(.dark) :deep(.el-descriptions__content) {
    background-color: #1e1e20;
    color: #e5e7eb;
    border-color: #3f3f46;
}

:global(.dark) :deep(.el-descriptions__label) {
    background-color: #27272a;
    color: #9ca3af;
}
</style>