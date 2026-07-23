<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import { usePermissions } from '@/Composables/usePermissions';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import SupportModal from '@/Components/SupportModal.vue';
// Iconos
import {  
    Money, Document 
} from '@element-plus/icons-vue';

defineProps({
    isCollapse: Boolean,
});

const { can } = usePermissions();
const showSupportModal = ref(false);
const route = window.route; // Acceso directo al helper route de Ziggy

// Lógica para determinar qué menú está activo (incluyendo sub-rutas)
const activeMenu = computed(() => {
    // Analíticas unificadas
    if (route().current('tickets.dashboard')) return 'tickets.dashboard';
    if (route().current('crm.dashboard')) return 'tickets.dashboard';

    // Clientes
    if (route().current('customers.*')) return 'customers.index';

    // Presupuestos
    if (route().current('budgets.*')) return 'budgets.index';

    // Deposits
    if (route().current('deposits.*')) return 'deposits.index';

    // Invoices
    if (route().current('invoices.*')) return 'invoices.index';

        // Tutoriales
        if (route().current('tutorials.*')) return 'tutorials.index';

    // Costos especiales
    if (route().current('special-costs.*')) return 'special-costs.index';

    if (route().current('costs.*')) return 'costs.index';

    // Tickets
    if (route().current('tickets.*') && !route().current('tickets.dashboard')) return 'tickets.index';

    // Configuración
    if (route().current('users.*')) return 'users.index';
    if (route().current('technicians.*')) return 'technicians.index';
    if (route().current('config.roles-permissions.*')) return 'config.roles-permissions.index';

    // Default
    return route().current();
});
</script>

<template>
    <div class="h-full flex flex-col bg-dark dark:bg-[#1e1e20] border-r border-gray-100 dark:border-[#2b2b2e]">

        <!-- Logo Area -->
        <div class="h-16 flex items-center justify-center border-b border-gray-100 dark:border-[#2b2b2e]">
            <Link :href="route('dashboard')" class="flex items-center gap-2">
                <ApplicationMark v-if="isCollapse" class="block !h-10 w-auto" />
                <span v-else
                    class="font-bold text-lg tracking-tight text-gray-800 dark:text-white transition-opacity duration-300">
                    <ApplicationLogo class="block !h-28 w-auto object-contain" />
                </span>
            </Link>
        </div>

        <!-- Menu Area -->
        <el-scrollbar class="flex-1">
            <el-menu :default-active="activeMenu" class="border-none !bg-transparent" :collapse="isCollapse"
                :unique-opened="true" text-color="#cccccc" active-text-color="#f26c17" :collapse-transition="false">
                <!-- Dashboard -->
                <Link :href="route('dashboard')">
                    <el-menu-item class="!bg-dark" index="dashboard">
                        <el-icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                            </svg></el-icon>
                        <template #title><span>Inicio</span></template>
                    </el-menu-item>
                </Link>

                <!-- Analíticas (unificadas) -->
                <Link v-if="can('tickets.analytics') || can('crm.analytics')" :href="route('tickets.dashboard')">
                    <el-menu-item class="!bg-dark" index="tickets.dashboard">
                        <el-icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                            </svg></el-icon>
                        <template #title><span>Analíticas</span></template>
                    </el-menu-item>
                </Link>

                <!-- Clientes -->
                <Link v-if="can('customers.index')" :href="route('customers.index')">
                    <el-menu-item class="!bg-dark" index="customers.index">
                        <el-icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg></el-icon>
                        <template #title><span>Clientes</span></template>
                    </el-menu-item>
                </Link>

                <!-- Gestión de tickets -->
                <Link v-if="can('tickets.index')" :href="route('tickets.index')">
                    <el-menu-item class="!bg-dark" index="tickets.index">
                        <el-icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" />
                            </svg></el-icon>
                        <template #title><span>Tickets</span></template>
                    </el-menu-item>
                </Link>

                <!-- Presupuestos -->
                <Link v-if="can('budgets.index')" :href="route('budgets.index')">
                    <el-menu-item class="!bg-dark" index="budgets.index">
                        <el-icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg></el-icon>
                        <template #title><span>Presupuestos</span></template>
                    </el-menu-item>
                </Link>

                   <!-- Costos especiales -->
               <Link v-if="can('special-costs.index')" :href="route('special-costs.index')">
                   <el-menu-item class="!bg-dark" index="special-costs.index">
                       <el-icon><ZoomIn/></el-icon>
                       <template #title><span>Costos especiales</span></template>
                   </el-menu-item>
               </Link>

                   <!-- Costos -->
               <Link v-if="can('costs.index')" :href="route('costs.index')">
                   <el-menu-item class="!bg-dark" index="costs.index">
                       <el-icon><Money/></el-icon>
                       <template #title><span>Costos</span></template>
                   </el-menu-item>
               </Link>

                <Link v-if="can('invoices.index')" :href="route('invoices.index')">
                    <el-menu-item class="!bg-dark" index="invoices.index">
                        <el-icon><Document/></el-icon>
                        <template #title><span>Facturación</span></template>
                    </el-menu-item>
                </Link>

                <!-- Depósitos -->
                <Link v-if="can('deposits.index')" :href="route('deposits.index')">
                    <el-menu-item class="!bg-dark" index="deposits.index">
                       <el-icon><Coin /></el-icon>
                        <template #title><span>Depósitos</span></template>
                    </el-menu-item>
                </Link>

                <!-- Tutoriales -->
                <Link :href="route('tutorials.index')">
                    <el-menu-item class="!bg-dark" index="tutorials.index">
                        <el-icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15.91 11.672a.375.375 0 010 .656l-5.603 3.113a.375.375 0 01-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112z" />
                            </svg></el-icon>
                        <template #title><span>Tutoriales</span></template>
                    </el-menu-item>
                </Link>

                <!-- Módulo Configuración -->
                <el-sub-menu index="settings" v-if="can('users.index') || can('roles.index')">
                    <template #title>
                        <el-icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg></el-icon>
                        <span>Configuración</span>
                    </template>

                    <Link v-if="can('users.index')" :href="route('users.index')">
                        <el-menu-item class="!bg-dark" index="users.index">Usuarios</el-menu-item>
                    </Link>

                    <Link v-if="can('technicians.index')" :href="route('technicians.index')">
                        <el-menu-item class="!bg-dark" index="technicians.index">Técnicos</el-menu-item>
                    </Link>

                    <Link v-if="can('roles.index')" :href="route('config.roles-permissions.index')">
                        <el-menu-item class="!bg-dark" index="config.roles-permissions.index">Roles y
                            Permisos</el-menu-item>
                    </Link>

                    <Link v-if="can('config.notifications')" :href="route('config.notifications.index')">
                        <el-menu-item class="!bg-dark" index="config.notifications.index">Notificaciones</el-menu-item>
                    </Link>
                </el-sub-menu>

            </el-menu>
        </el-scrollbar>

        <!-- Footer del Sidebar (colapsado) -->
        <div v-if="isCollapse"
            class="p-3 border-t border-gray-100 dark:border-[#2b2b2e] flex justify-center">
            <el-button
                text
                size="small"
                class="!text-gray-400 hover:!text-[#f26c17]"
                @click="showSupportModal = true"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                </svg>
            </el-button>
        </div>

        <!-- Footer del Sidebar (desplegado) -->
        <div v-else
            class="p-4 border-t border-gray-100 dark:border-[#2b2b2e] flex flex-col items-center gap-2">
            <span class="text-xs text-gray-400">v1.3.0</span>
            <el-button
                text
                size="small"
                class="!text-gray-400 hover:!text-[#f26c17]"
                @click="showSupportModal = true"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                </svg>
                Soporte técnico
            </el-button>
        </div>

        <!-- Support Modal -->
        <SupportModal v-model="showSupportModal" />
    </div>
</template>

<style scoped>
/* Personalización del menú */
:deep(.el-menu-item:hover),
:deep(.el-sub-menu__title:hover) {
    background-color: #fef0e7 !important;
    color: #f26c17 !important;
}

:deep(.el-menu-item.is-active) {
    background-color: #fff7f2 !important;
    border-right: 3px solid #f26c17;
    font-weight: 600;
}

:global(.dark) :deep(.el-menu-item:hover),
:global(.dark) :deep(.el-sub-menu__title:hover) {
    background-color: #27272a !important;
    color: #f26c17 !important;
}

:global(.dark) :deep(.el-menu-item.is-active) {
    background-color: #27272a !important;
}

:deep(.el-menu) {
    border-right: none;
}

/* Fix para que el Link de Inertia no rompa el diseño del menú (display block por defecto) */
:deep(a) {
    text-decoration: none;
    display: block;
}
</style>