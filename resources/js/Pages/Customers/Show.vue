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

const formatCurrency = (amount, currency = 'MXN') => {
    return new Intl.NumberFormat('es-MX', { 
        style: 'currency', 
        currency: currency 
    }).format(amount || 0);
};

const getWhatsappUrl = (phone) => {
    if (!phone) return '#';
    const number = phone.replace(/\D/g, '');
    return `https://wa.me/${number}`;
};

const getStatusColor = (status) => {
    const map = {
        'Borrador': 'info',
        'Ticket enviado': 'primary', // Actualizado a Ticket
        'Facturado': 'warning',
        'Trabajo en proceso': 'primary',
        'Trabajo terminado': 'success',
        'Pagado': 'success',
        'Perdido': 'danger'
    };
    return map[status] || 'info';
};

// Actualizado a navigateToTicket
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

        <div class="max-w-6xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">
            
            <!-- HEADER RESUMEN -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-4">
                    <div class="bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 p-3 rounded-full">
                        <el-icon :size="32"><OfficeBuilding /></el-icon>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ customer.name }}</h3>
                            <el-tag :type="customer.is_active ? 'success' : 'danger'" size="small" effect="dark" class="rounded-full">
                                {{ customer.is_active ? 'Activo' : 'Inactivo' }}
                            </el-tag>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm flex items-center gap-1 mt-1">
                            <span class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">{{ customer.rfc }}</span>
                        </p>
                    </div>
                </div>
                
                <div class="flex gap-8 text-center sm:text-right">
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-bold">Moneda Base</p>
                        <p class="text-lg font-bold text-gray-800 dark:text-white">{{ customer.currency }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-bold">Plazo Crédito</p>
                        <p class="text-lg font-bold text-blue-600 dark:text-blue-400">
                            {{ customer.payment_days > 0 ? `${customer.payment_days} días` : 'Contado' }}
                        </p>
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
                                <div v-if="customer.contacts.length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
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

                                        <!-- SECCIÓN DE SUCURSALES (FORMATO JSON) -->
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
                    <el-tab-pane label="Historial de Tickets" name="tickets"> <!-- Actualizado el nombre de la pestaña -->
                        <div class="p-4">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                    <el-icon><Ticket /></el-icon> Tickets Recientes <!-- Icono actualizado a Ticket -->
                                </h4>
                                <!-- Actualizadas las rutas a tickets -->
                                <Link :href="route('tickets.create')">
                                    <el-button size="small" type="primary" plain icon="Plus">Nuevo ticket</el-button>
                                </Link>
                            </div>

                            <!-- Asegúrate de que la relación en tu backend también se llame tickets si cambiaste el modelo, o mantenla como budgets si solo es un cambio visual -->
                            <!-- Aquí asumo que la variable desde el controlador sigue siendo 'budgets' por ahora (como indicaste en el modelo inicialmente), pero la visualización dice 'Tickets' -->
                            <div v-if="customer.budgets && customer.budgets.length > 0">
                                <el-table 
                                    :data="customer.budgets" 
                                    style="width: 100%" 
                                    stripe 
                                    @row-click="navigateToTicket"
                                    row-class-name="cursor-pointer"
                                >
                                    <el-table-column prop="id" label="Folio" width="80">
                                        <template #default="scope">
                                            <span class="font-mono font-bold text-gray-500">#{{ scope.row.id }}</span>
                                        </template>
                                    </el-table-column>
                                    
                                    <el-table-column label="Proyecto / Servicio" min-width="200">
                                        <template #default="scope">
                                            <div class="font-bold text-gray-800 dark:text-gray-200">{{ scope.row.name }}</div>
                                            <div class="text-xs text-gray-500">{{ scope.row.service_type }}</div>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Monto" width="150" align="right">
                                        <template #default="scope">
                                            <span class="font-bold text-gray-700 dark:text-gray-300">
                                                {{ formatCurrency(scope.row.concepts_sum_amount, scope.row.currency) }}
                                            </span>
                                            <span class="text-xs text-gray-400 block">{{ scope.row.currency }}</span>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Fecha" width="140">
                                        <template #default="scope">
                                            <div class="text-sm">{{ formatDate(scope.row.created_at) }}</div>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Responsable" width="180">
                                        <template #default="scope">
                                            <div class="flex items-center gap-2">
                                                <el-avatar :size="24" :src="scope.row.responsible?.profile_photo_url">
                                                    {{ scope.row.responsible?.name?.charAt(0) }}
                                                </el-avatar>
                                                <span class="text-sm truncate">{{ scope.row.responsible?.name }}</span>
                                            </div>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Estatus" width="140" align="center">
                                        <template #default="scope">
                                            <el-tag :type="getStatusColor(scope.row.status)" size="small" effect="dark" class="w-full">
                                                {{ scope.row.status }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </div>
                            <el-empty v-else description="No hay tickets registrados para este cliente aún." :image-size="100">
                                <Link :href="route('tickets.create')">
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