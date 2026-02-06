<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    customer: Object,
});

// Función para formatear fecha: '06 febrero, 2026'
const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    
    // Obtenemos partes
    const day = date.toLocaleDateString('es-ES', { day: '2-digit' });
    const month = date.toLocaleDateString('es-ES', { month: 'long' });
    const year = date.getFullYear();

    return `${day} ${month}, ${year}`;
};

// Limpiar teléfono para link de Whatsapp
const getWhatsappUrl = (phone) => {
    if (!phone) return '#';
    // Eliminar todo lo que no sea número
    const number = phone.replace(/\D/g, '');
    return `https://wa.me/${number}`;
};
</script>

<template>
    <AppLayout :title="`Cliente: ${customer.name}`">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                    Detalles del cliente
                </h2>
                <div class="flex gap-2">
                    <Link :href="route('customers.edit', customer.id)">
                        <el-button type="primary" color="#f26c17" icon="Edit">
                            Editar
                        </el-button>
                    </Link>
                    <Link :href="route('customers.index')">
                        <el-button icon="Back" circle />
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-5xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">
            
            <!-- TARJETA 1: INFORMACIÓN PRINCIPAL -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-[#2b2b2e] flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div class="flex items-center gap-4">
                        <div class="bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 p-3 rounded-lg">
                            <el-icon :size="32"><OfficeBuilding /></el-icon>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ customer.name }}</h3>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">{{ customer.business_name }}</p>
                        </div>
                    </div>
                    <el-tag :type="customer.is_active ? 'success' : 'danger'" size="large" effect="dark" class="mt-2 sm:mt-0">
                        {{ customer.is_active ? 'Cliente activo' : 'Cliente inactivo' }}
                    </el-tag>
                </div>

                <div class="p-6">
                    <el-descriptions border :column="3" size="large" class="custom-descriptions">
                        <el-descriptions-item label="RFC" label-class-name="font-bold">
                            <span class="font-mono bg-gray-50 dark:bg-gray-800 px-2 py-0.5 rounded text-sm border border-gray-200 dark:border-gray-700">
                                {{ customer.rfc }}
                            </span>
                        </el-descriptions-item>
                        
                        <el-descriptions-item label="Moneda">
                            {{ customer.currency }}
                        </el-descriptions-item>

                        <el-descriptions-item label="Registro">
                            <!-- Fecha formateada y capitalizada -->
                            <span class="capitalize">{{ formatDate(customer.created_at) }}</span>
                        </el-descriptions-item>

                        <el-descriptions-item label="Condiciones de pago">
                            {{ customer.payment_condition }}
                        </el-descriptions-item>

                        <el-descriptions-item label="Método de pago">
                            {{ customer.payment_method }}
                        </el-descriptions-item>

                        <el-descriptions-item label="Uso de factura">
                            {{ customer.invoice_usage }}
                        </el-descriptions-item>
                    </el-descriptions>
                </div>
            </div>

            <!-- TARJETA 2: CONTACTOS ASOCIADOS -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <div class="p-4 border-b border-gray-100 dark:border-[#2b2b2e] bg-gray-50/50 dark:bg-[#252529]">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <el-icon class="text-primary"><User /></el-icon> 
                        Contactos registrados ({{ customer.contacts.length }})
                    </h3>
                </div>

                <div class="p-6">
                    <div v-if="customer.contacts.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div 
                            v-for="contact in customer.contacts" 
                            :key="contact.id"
                            class="border border-gray-200 dark:border-[#3f3f46] rounded-lg p-4 hover:shadow-md transition-shadow bg-white dark:bg-[#1e1e20] relative group"
                        >
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <el-avatar :size="32" class="bg-primary text-white">{{ contact.name.charAt(0) }}</el-avatar>
                                    <div>
                                        <p class="font-bold text-gray-800 dark:text-gray-200 text-sm leading-tight">{{ contact.name }}</p>
                                        <p class="text-xs text-primary font-medium">{{ contact.position }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-2 mt-3 text-sm">
                                <p class="flex items-center text-gray-600 dark:text-gray-400 gap-2">
                                    <el-icon><Message /></el-icon>
                                    <a :href="`mailto:${contact.email}`" class="hover:text-primary transition-colors underline decoration-dotted">
                                        {{ contact.email }}
                                    </a>
                                </p>
                                
                                <!-- Menú desplegable para Teléfono -->
                                <div class="flex items-center text-gray-600 dark:text-gray-400 gap-2">
                                    <el-icon><Phone /></el-icon>
                                    
                                    <el-dropdown trigger="click">
                                        <span class="el-dropdown-link cursor-pointer hover:text-primary transition-colors font-medium">
                                            {{ contact.phone }}
                                            <el-icon class="el-icon--right"><ArrowDown /></el-icon>
                                        </span>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <!-- Opción Llamar -->
                                                <a :href="`tel:${contact.phone}`" class="block w-full text-inherit no-underline">
                                                    <el-dropdown-item icon="Phone">Llamar</el-dropdown-item>
                                                </a>
                                                <!-- Opción Whatsapp -->
                                                <a :href="getWhatsappUrl(contact.phone)" target="_blank" class="block w-full text-inherit no-underline">
                                                    <el-dropdown-item>
                                                        <el-icon><ChatDotRound /></el-icon> Whatsapp
                                                    </el-dropdown-item>
                                                </a>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </div>

                                <p class="flex items-start text-gray-600 dark:text-gray-400 gap-2">
                                    <el-icon class="mt-0.5"><Location /></el-icon>
                                    <span>{{ contact.branches }}</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <el-empty v-else description="No hay contactos registrados para este cliente" :image-size="100" />
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<style scoped>
/* Ajustes para modo oscuro en las descripciones */
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

:deep(.el-dropdown-menu__item) {
    display: flex;
    align-items: center;
    gap: 5px;
}

.el-dropdown-link:focus {
    outline: none;
}
</style>