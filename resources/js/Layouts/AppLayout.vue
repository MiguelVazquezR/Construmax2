<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Banner from '@/Components/Banner.vue';
import AppSidebar from './AppSidebar.vue';
import AppTopbar from './AppTopbar.vue';

defineProps({
    title: String,
});

// Inicializamos el estado leyendo del localStorage (si existe), si no, por defecto es false (expandido)
const isSidebarCollapse = ref(localStorage.getItem('sidebar_state') === 'true');

// Estado para el drawer en Mobile
const isDrawerOpen = ref(false);

const toggleSidebar = () => {
    if (window.innerWidth < 1024) {
        isDrawerOpen.value = !isDrawerOpen.value;
    } else {
        // Alternamos y guardamos la preferencia
        isSidebarCollapse.value = !isSidebarCollapse.value;
        localStorage.setItem('sidebar_state', isSidebarCollapse.value);
    }
};
</script>

<template>
    <!-- Contenedor Raíz: Fijo a la altura de la pantalla (h-screen) y sin scroll global -->
    <div class="h-screen bg-[#f4f6f8] dark:bg-[#121212] flex flex-col overflow-hidden">
        <Head :title="title" />
        
        <!-- Contenedor Flexible: Ocupa el espacio restante -->
        <div class="flex-1 flex overflow-hidden">
            
            <!-- Sidebar para Desktop (Fijo a la izquierda) -->
            <aside 
                class="hidden lg:block bg-white shadow-sm dark:bg-[#1e1e20] transition-all duration-300 ease-in-out z-20 flex-shrink-0"
                :class="isSidebarCollapse ? 'w-16' : 'w-64'"
            >
                <AppSidebar :is-collapse="isSidebarCollapse" />
            </aside>

            <!-- Drawer para Mobile -->
            <el-drawer
                v-model="isDrawerOpen"
                direction="ltr"
                :with-header="false"
                size="70%"
                class="lg:hidden !p-0"
            >
                <AppSidebar :is-collapse="false" />
            </el-drawer>

            <!-- Área de Contenido (Lado derecho) -->
            <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
                
                <!-- Topbar (Fijo arriba) -->
                <AppTopbar 
                    :title="title" 
                    @toggle-sidebar="toggleSidebar"
                />

                <!-- Page Header (Fijo debajo del topbar si existe) -->
                <header v-if="$slots.header" class="bg-white dark:bg-[#1e1e20] shadow-sm border-b border-gray-100 dark:border-[#2b2b2e] z-10 flex-none">
                    <div class="mx-auto py-1 px-4 sm:px-6 lg:px-8">
                        <slot name="header" />
                    </div>
                </header>

                <!-- Main Content (ÚNICA ÁREA CON SCROLL) -->
                <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 scroll-smooth">
                    <!-- Slot del contenido de la página -->
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Eliminar padding por defecto del body del drawer de Element Plus */
:deep(.el-drawer__body) {
    padding: 0;
    background-color: transparent;
}
</style>