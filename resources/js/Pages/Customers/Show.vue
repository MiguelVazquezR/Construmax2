<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { 
    OfficeBuilding, User, Message, Phone, Location, 
    ArrowDown, ChatDotRound, Edit, Back, List, Timer,
    Ticket, MapLocation, Suitcase
} from '@element-plus/icons-vue';

const { can } = usePermissions();

const props = defineProps({
    customer: Object,
});

const activeTab = ref('general');

// --- HELPERS ---
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const day = date.toLocaleDateString('es-ES', { day: '2-digit' });
    const month = date.toLocaleDateString('es-ES', { month: 'short' });
    const year = date.getFullYear();
    return `${day} ${month}, ${year}`;
};

const getWhatsappUrl = (phone) => {
    if (!phone) return '#';
    const number = phone.replace(/\D/g, '');
    return `https://wa.me/${number}`;
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
    <AppLayout :title="`Cliente: ${customer.name}`">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('customers.index')">
                        <el-button :icon="Back" circle plain />
                    </Link>
                    <div>
                        <h2 class="font-semibold text-base text-gray-800 dark:text-white leading-tight">
                            Perfil del Cliente
                        </h2>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link v-if="can('customers.edit')" :href="route('customers.edit', customer.id)">
                        <el-button type="primary" color="#f26c17" :icon="Edit">
                            Editar cliente
                        </el-button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">
            
            <!-- HEADER RESUMEN (HERO CARD) -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="flex items-center gap-5">
                    <div class="bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 p-4 rounded-2xl shrink-0">
                        <el-icon :size="36"><OfficeBuilding /></el-icon>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ customer.name }}</h3>
                            <el-tag :type="customer.is_active ? 'success' : 'danger'" size="small" effect="dark" class="rounded-full">
                                {{ customer.is_active ? 'Activo' : 'Inactivo' }}
                            </el-tag>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm flex items-center gap-2">
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ customer.business_name }}</span>
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                            <span class="font-mono bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded text-xs">{{ customer.rfc }}</span>
                        </p>
                    </div>
                </div>
                
                <div class="flex gap-6 md:gap-10 text-left md:text-right border-t md:border-t-0 border-gray-100 dark:border-gray-800 pt-4 md:pt-0 w-full md:w-auto">
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-1">Moneda</p>
                        <p class="text-lg font-bold text-gray-800 dark:text-white">{{ customer.currency }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-1">Plazo de Pago</p>
                        <p class="text-lg font-bold text-blue-600 dark:text-blue-400 flex items-center gap-1 md:justify-end">
                            <el-icon><Timer /></el-icon>
                            {{ customer.payment_days > 0 ? `${customer.payment_days} días` : 'Contado' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO ORGANIZADO EN PESTAÑAS -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-tabs v-model="activeTab" class="px-2 pt-2 custom-tabs">
                    
                    <!-- PESTAÑA 1: DATOS COMERCIALES -->
                    <el-tab-pane name="general">
                        <template #label>
                            <span class="flex items-center gap-2 px-2"><el-icon><Suitcase /></el-icon> Datos Comerciales</span>
                        </template>
                        <div class="p-6">
                            <el-descriptions border :column="2" size="large" class="custom-descriptions">
                                <el-descriptions-item label="Razón Social">
                                    <span class="font-medium">{{ customer.business_name }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item label="RFC">
                                    <span class="font-mono font-medium">{{ customer.rfc }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item label="Condiciones de pago">
                                    {{ customer.payment_condition }}
                                </el-descriptions-item>
                                <el-descriptions-item label="Días de crédito">
                                    {{ customer.payment_days > 0 ? `${customer.payment_days} días naturales` : 'Pago inmediato / Contado' }}
                                </el-descriptions-item>
                                <el-descriptions-item label="Método de pago">
                                    {{ customer.payment_method }}
                                </el-descriptions-item>
                                <el-descriptions-item label="Uso de factura CFDI">
                                    {{ customer.invoice_usage }}
                                </el-descriptions-item>
                                <el-descriptions-item label="Fecha de alta">
                                    {{ formatDate(customer.created_at) }}
                                </el-descriptions-item>
                            </el-descriptions>
                        </div>
                    </el-tab-pane>

                    <!-- PESTAÑA 2: SUCURSALES -->
                    <el-tab-pane name="branches">
                        <template #label>
                            <span class="flex items-center gap-2 px-2">
                                <el-icon><MapLocation /></el-icon> Sucursales 
                                <el-tag size="small" type="info" class="ml-1 rounded-full">{{ customer.branches?.length || 0 }}</el-tag>
                            </span>
                        </template>
                        <div class="p-6">
                            <div v-if="customer.branches && customer.branches.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                                <div 
                                    v-for="branch in customer.branches" 
                                    :key="branch.id" 
                                    class="p-5 bg-gray-50 dark:bg-[#252529]/50 border border-gray-200 dark:border-gray-800 rounded-xl hover:shadow-md transition-shadow group relative overflow-hidden"
                                >
                                    <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
                                        <el-icon :size="60"><OfficeBuilding /></el-icon>
                                    </div>
                                    <h4 class="font-bold text-lg text-gray-900 dark:text-gray-100 mb-1 pr-10">
                                        {{ branch.branch_name || branch.unit }}
                                    </h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                        <span class="font-semibold text-gray-700 dark:text-gray-300">Unidad:</span> {{ branch.unit }}
                                    </p>
                                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700/50 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                        <el-icon class="text-primary"><Location /></el-icon>
                                        {{ branch.region }}, {{ branch.country }}
                                    </div>
                                </div>
                            </div>
                            <el-empty v-else description="Este cliente aún no tiene sucursales registradas." :image-size="80" />
                        </div>
                    </el-tab-pane>

                    <!-- PESTAÑA 3: CONTACTOS -->
                    <el-tab-pane name="contacts">
                        <template #label>
                            <span class="flex items-center gap-2 px-2">
                                <el-icon><User /></el-icon> Contactos 
                                <el-tag size="small" type="info" class="ml-1 rounded-full">{{ customer.contacts?.length || 0 }}</el-tag>
                            </span>
                        </template>
                        <div class="p-6">
                            <div v-if="customer.contacts && customer.contacts.length > 0" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-5">
                                <div 
                                    v-for="contact in customer.contacts" 
                                    :key="contact.id"
                                    class="border border-gray-200 dark:border-gray-800 rounded-xl p-5 hover:shadow-md transition-shadow bg-white dark:bg-[#1e1e20] flex flex-col h-full"
                                >
                                    <!-- Header de Contacto -->
                                    <div class="flex items-start gap-4 mb-4">
                                        <el-avatar :size="48" class="bg-primary text-white font-bold shrink-0">{{ contact.name.charAt(0) }}</el-avatar>
                                        <div class="min-w-0">
                                            <p class="font-bold text-gray-900 dark:text-gray-100 text-base truncate" :title="contact.name">{{ contact.name }}</p>
                                            <p class="text-sm text-primary font-medium truncate">{{ contact.position }}</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Info de Contacto -->
                                    <div class="space-y-3 mb-5 text-sm flex-grow">
                                        <p class="flex items-center text-gray-600 dark:text-gray-400 gap-2 truncate">
                                            <el-icon class="shrink-0"><Message /></el-icon>
                                            <a :href="`mailto:${contact.email}`" class="hover:text-primary transition-colors truncate" :title="contact.email">
                                                {{ contact.email }}
                                            </a>
                                        </p>
                                        
                                        <div class="flex items-center text-gray-600 dark:text-gray-400 gap-2">
                                            <el-icon class="shrink-0"><Phone /></el-icon>
                                            <el-dropdown trigger="click">
                                                <span class="el-dropdown-link cursor-pointer hover:text-primary transition-colors font-medium flex items-center gap-1">
                                                    {{ contact.phone }} <el-icon class="text-xs"><ArrowDown /></el-icon>
                                                </span>
                                                <template #dropdown>
                                                    <el-dropdown-menu>
                                                        <a :href="`tel:${contact.phone}`" class="no-underline text-inherit block w-full">
                                                            <el-dropdown-item :icon="Phone">Llamar</el-dropdown-item>
                                                        </a>
                                                        <a :href="getWhatsappUrl(contact.phone)" target="_blank" class="no-underline text-inherit block w-full">
                                                            <el-dropdown-item :icon="ChatDotRound">WhatsApp</el-dropdown-item>
                                                        </a>
                                                    </el-dropdown-menu>
                                                </template>
                                            </el-dropdown>
                                        </div>
                                    </div>

                                    <!-- Sucursales Asignadas (Tags minimalistas) -->
                                    <div class="mt-auto pt-4 border-t border-gray-100 dark:border-gray-800">
                                        <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">
                                            Sucursales Asignadas:
                                        </p>
                                        <div v-if="contact.branches && contact.branches.length > 0" class="flex flex-wrap gap-1.5">
                                            <el-tag 
                                                v-for="cb in contact.branches" 
                                                :key="cb.id" 
                                                type="info" 
                                                effect="plain" 
                                                size="small" 
                                                class="!border-gray-300 dark:!border-gray-600 !text-gray-600 dark:!text-gray-300 bg-gray-50 dark:bg-transparent"
                                            >
                                                {{ cb.unit }}
                                            </el-tag>
                                        </div>
                                        <p v-else class="text-xs text-gray-400 italic">Sin asignaciones</p>
                                    </div>
                                </div>
                            </div>
                            <el-empty v-else description="Sin contactos registrados" :image-size="80" />
                        </div>
                    </el-tab-pane>

                    <!-- PESTAÑA 4: HISTORIAL DE TICKETS -->
                    <el-tab-pane name="tickets">
                        <template #label>
                            <span class="flex items-center gap-2 px-2">
                                <el-icon><Ticket /></el-icon> Historial de Tickets
                                <el-tag v-if="customer.tickets?.length" size="small" type="primary" class="ml-1 rounded-full">{{ customer.tickets.length }}</el-tag>
                            </span>
                        </template>
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-6">
                                <div>
                                    <h4 class="font-bold text-gray-800 dark:text-gray-200 text-lg">Tickets Operativos</h4>
                                    <p class="text-sm text-gray-500">Historial de todos los servicios solicitados por este cliente.</p>
                                </div>
                                <Link :href="route('tickets.create', { customer_id: customer.id })">
                                    <el-button type="primary" color="#f26c17" icon="Plus" class="!font-bold">Nuevo ticket</el-button>
                                </Link>
                            </div>

                            <div v-if="customer.tickets && customer.tickets.length > 0" class="border border-gray-200 dark:border-gray-800 rounded-lg overflow-hidden">
                                <el-table 
                                    :data="customer.tickets" 
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

                                    <el-table-column label="Fecha Inicio" width="120">
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
                                <el-empty description="No hay tickets registrados" :image-size="100">
                                    <template #default>
                                        <p class="text-sm text-gray-500 mb-4">Crea el primer ticket de servicio para empezar el historial.</p>
                                        <Link :href="route('tickets.create', { customer_id: customer.id })">
                                            <el-button type="primary" plain>Comenzar ahora</el-button>
                                        </Link>
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

/* Tab Styling Overrides */
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

/* Fix para enlaces dentro del dropdown */
:deep(.el-dropdown-menu__item a) {
    display: flex;
    align-items: center;
    width: 100%;
    color: inherit;
    text-decoration: none;
}
</style>