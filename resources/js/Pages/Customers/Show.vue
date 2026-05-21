<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { 
    OfficeBuilding, User, Message, Phone, Location, 
    ArrowDown, ChatDotRound, Edit, Back, List, Timer,
    Ticket
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
    const month = date.toLocaleDateString('es-ES', { month: 'long' });
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
                            {{ customer.name }}
                        </h2>
                        <p class="text-xs text-gray-500">{{ customer.business_name }}</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link v-if="can('customers.edit')" :href="route('customers.edit', customer.id)">
                        <el-button type="primary" color="#f26c17" :icon="Edit">
                            Editar
                        </el-button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-8 sm:px-6 lg:px-8 space-y-6">
            
            <!-- Tarjeta de Datos Generales -->
            <div class="bg-white dark:bg-[#1e1e20] rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-[#2b2b2e] bg-gray-50/50 dark:bg-[#252529]">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <el-icon class="text-primary"><OfficeBuilding /></el-icon> 
                        Información General
                    </h3>
                </div>
                <div class="p-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Nombre Comercial</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ customer.name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Razón Social</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ customer.business_name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">RFC</p>
                        <p class="font-mono font-semibold text-gray-900 dark:text-gray-100">{{ customer.rfc }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Condiciones de pago</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ customer.payment_condition }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Días de crédito</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ customer.payment_days || 0 }} días</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Uso de CFDI</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ customer.invoice_usage }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Tarjeta de Sucursales Globales -->
                <div class="bg-white dark:bg-[#1e1e20] rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-[#2b2b2e] bg-gray-50/50 dark:bg-[#252529]">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <el-icon class="text-primary"><Location /></el-icon> 
                            Sucursales Registradas ({{ customer.branches?.length || 0 }})
                        </h3>
                    </div>
                    <div class="p-6">
                        <div v-if="customer.branches && customer.branches.length > 0" class="space-y-4">
                            <div v-for="branch in customer.branches" :key="branch.id" class="p-4 bg-gray-50 dark:bg-[#252529]/50 border border-gray-200 dark:border-gray-700 rounded-lg flex flex-col sm:flex-row justify-between gap-4 transition-colors hover:bg-gray-100 dark:hover:bg-[#252529]">
                                <div>
                                    <p class="font-bold text-gray-900 dark:text-gray-100">{{ branch.branch_name || 'Sin nombre definido' }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Unidad: <span class="font-medium text-gray-700 dark:text-gray-300">{{ branch.unit }}</span></p>
                                </div>
                                <div class="text-left sm:text-right text-sm text-gray-500 dark:text-gray-400">
                                    <p class="font-medium">{{ branch.region }}</p>
                                    <p>{{ branch.country }}</p>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-gray-500 dark:text-gray-400 italic text-sm">No hay sucursales registradas para este cliente.</p>
                    </div>
                </div>

                <!-- Tarjeta de Contactos y sus Asignaciones -->
                <div class="bg-white dark:bg-[#1e1e20] rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-[#2b2b2e] bg-gray-50/50 dark:bg-[#252529]">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <el-icon class="text-primary"><User /></el-icon> 
                            Contactos Asignados ({{ customer.contacts?.length || 0 }})
                        </h3>
                    </div>
                    <div class="p-6">
                        <div v-if="customer.contacts && customer.contacts.length > 0" class="space-y-6">
                            <div v-for="contact in customer.contacts" :key="contact.id" class="p-5 border-2 border-gray-100 dark:border-gray-800 rounded-xl relative">
                                <div class="flex flex-col sm:flex-row justify-between mb-4">
                                    <div>
                                        <p class="font-bold text-gray-900 dark:text-gray-100">{{ contact.name }}</p>
                                        <p class="text-sm text-primary font-medium">{{ contact.position }}</p>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400 text-left sm:text-right mt-2 sm:mt-0 space-y-0.5">
                                        <p>{{ contact.phone }}</p>
                                        <p>{{ contact.email }}</p>
                                    </div>
                                </div>
                                
                                <div class="pt-3 border-t border-gray-100 dark:border-gray-800">
                                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Sucursales a cargo:</p>
                                    <div v-if="contact.branches && contact.branches.length > 0" class="flex flex-wrap gap-2">
                                        <el-tag v-for="cb in contact.branches" :key="cb.id" type="info" effect="light" size="small" class="border-gray-200 dark:border-gray-700">
                                            {{ cb.branch_name ? cb.branch_name : cb.unit }} - {{ cb.region }}
                                        </el-tag>
                                    </div>
                                    <p v-else class="text-xs text-gray-400 italic">Ninguna sucursal asignada.</p>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-gray-500 dark:text-gray-400 italic text-sm">No hay contactos registrados.</p>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO CON PESTAÑAS -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-tabs v-model="activeTab" class="px-4 pt-2">
                    
                    <!-- PESTAÑA 1: INFORMACIÓN GENERAL -->
                    <el-tab-pane label="Información General" name="general">
                        <div class="p-4 space-y-6">
                            
                            <!-- Datos Fiscales -->
                            <div>
                                <h4 class="font-bold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2 border-b pb-2 dark:border-gray-700">
                                    <el-icon><List /></el-icon> Datos Comerciales
                                </h4>
                                <el-descriptions border :column="2" size="large" class="custom-descriptions">
                                    <el-descriptions-item label="Razón Social">
                                        {{ customer.business_name }}
                                    </el-descriptions-item>
                                    <el-descriptions-item label="RFC">
                                        {{ customer.rfc }}
                                    </el-descriptions-item>
                                    <el-descriptions-item label="Condiciones de pago">
                                        {{ customer.payment_condition }}
                                    </el-descriptions-item>
                                    <el-descriptions-item label="Días de crédito">
                                        <div class="flex items-center gap-2">
                                            <el-icon><Timer /></el-icon>
                                            {{ customer.payment_days > 0 ? `${customer.payment_days} días naturales` : 'Pago inmediato / Contado' }}
                                        </div>
                                    </el-descriptions-item>
                                    <el-descriptions-item label="Método de pago">
                                        {{ customer.payment_method }}
                                    </el-descriptions-item>
                                    <el-descriptions-item label="Uso de factura">
                                        {{ customer.invoice_usage }}
                                    </el-descriptions-item>
                                    <el-descriptions-item label="Fecha de registro">
                                        {{ formatDate(customer.created_at) }}
                                    </el-descriptions-item>
                                </el-descriptions>
                            </div>

                            <!-- Contactos -->
                            <div>
                                <h4 class="font-bold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2 border-b pb-2 dark:border-gray-700">
                                    <el-icon><User /></el-icon> Contactos Asociados
                                </h4>
                                <div v-if="customer.contacts && customer.contacts.length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <div 
                                        v-for="contact in customer.contacts" 
                                        :key="contact.id"
                                        class="border border-gray-200 dark:border-[#3f3f46] rounded-lg p-5 hover:shadow-md transition-shadow bg-gray-50/50 dark:bg-[#252529] relative group flex flex-col h-full"
                                    >
                                        <div class="flex items-start justify-between mb-4">
                                            <div class="flex items-center gap-3">
                                                <el-avatar :size="40" class="bg-primary text-white font-bold">{{ contact.name.charAt(0) }}</el-avatar>
                                                <div>
                                                    <p class="font-bold text-gray-800 dark:text-gray-200 text-base leading-tight">{{ contact.name }}</p>
                                                    <p class="text-sm text-primary font-medium">{{ contact.position }}</p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="space-y-3 mb-4 text-sm flex-grow">
                                            <p class="flex items-center text-gray-600 dark:text-gray-400 gap-2 truncate">
                                                <el-icon><Message /></el-icon>
                                                <a :href="`mailto:${contact.email}`" class="hover:text-primary transition-colors underline decoration-dotted">
                                                    {{ contact.email }}
                                                </a>
                                            </p>
                                            
                                            <div class="flex items-center text-gray-600 dark:text-gray-400 gap-2">
                                                <el-icon><Phone /></el-icon>
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

                                        <div class="mt-auto pt-3 border-t border-gray-200 dark:border-gray-700">
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1">
                                                <el-icon><Location /></el-icon> Sucursales a cargo
                                            </p>
                                            <div class="space-y-2 max-h-32 overflow-y-auto pr-2 custom-scrollbar">
                                                <template v-if="Array.isArray(contact.branches)">
                                                    <div v-for="(branch, idx) in contact.branches" :key="idx" class="bg-white dark:bg-[#1e1e20] p-2 rounded border border-gray-100 dark:border-gray-800 text-xs">
                                                        <span class="font-bold text-gray-700 dark:text-gray-300">{{ branch.unit }}</span>
                                                        <span class="text-gray-500 ml-1">({{ branch.region }}, {{ branch.country }})</span>
                                                    </div>
                                                </template>
                                                <template v-else>
                                                    <div class="bg-white dark:bg-[#1e1e20] p-2 rounded border border-gray-100 dark:border-gray-800 text-xs text-gray-600">
                                                        {{ contact.branches }}
                                                    </div>
                                                </template>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                                <el-empty v-else description="Sin contactos registrados" :image-size="80" />
                            </div>
                        </div>
                    </el-tab-pane>

                    <!-- PESTAÑA 2: HISTORIAL DE TICKETS -->
                    <el-tab-pane label="Historial de Tickets" name="tickets">
                        <div class="p-4">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                    <el-icon><Ticket /></el-icon> Tickets Registrados
                                </h4>
                                <Link :href="route('tickets.create', { customer_id: customer.id })">
                                    <el-button size="small" type="primary" plain icon="Plus">Nuevo ticket</el-button>
                                </Link>
                            </div>

                            <div v-if="customer.tickets && customer.tickets.length > 0">
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
                                            <div class="font-bold text-gray-800 dark:text-gray-200">{{ scope.row.name }}</div>
                                            <div class="text-xs text-gray-500">{{ scope.row.service_type }}</div>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Fechas" width="160">
                                        <template #default="scope">
                                            <div class="text-xs space-y-1">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-400">Inicio:</span>
                                                    <span class="text-gray-700 dark:text-gray-300 font-mono">{{ formatDate(scope.row.scheduled_start) }}</span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-400">Fin:</span>
                                                    <span class="text-gray-700 dark:text-gray-300 font-mono">{{ formatDate(scope.row.scheduled_end) }}</span>
                                                </div>
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

                                    <el-table-column label="Estatus" width="160" align="center">
                                        <template #default="scope">
                                            <el-tag :type="getStatusColor(scope.row.status)" size="small" effect="dark" class="w-full border-none">
                                                {{ scope.row.status }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </div>
                            <el-empty v-else description="No hay tickets registrados para este cliente aún." :image-size="100">
                                <Link :href="route('tickets.create', { customer_id: customer.id })">
                                    <el-button type="primary">Crear el primero</el-button>
                                </Link>
                            </el-empty>
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

/* Custom Scrollbar for branches */
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #d1d5db;
    border-radius: 10px;
}
:global(.dark) .custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #4b5563;
}
</style>