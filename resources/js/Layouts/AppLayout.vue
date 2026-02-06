<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Banner from '@/Components/Banner.vue';
import AppSidebar from './AppSidebar.vue';
import AppTopbar from './AppTopbar.vue';

defineProps({
    title: String,
});

// Estado para el sidebar en Desktop (colapsado o extendido)
const isSidebarCollapse = ref(false);

// Estado para el drawer en Mobile (abierto o cerrado)
const isDrawerOpen = ref(false);

const toggleSidebar = () => {
    // Si es móvil, abrimos/cerramos el drawer
    if (window.innerWidth < 1024) {
        isDrawerOpen.value = !isDrawerOpen.value;
    } else {
        // Si es desktop, colapsamos/expandimos el sidebar
        isSidebarCollapse.value = !isSidebarCollapse.value;
    }
};
</script>

<template>
    <div>
        <Head :title="title" />
        <Banner />

        <div class="min-h-screen bg-[#f4f6f8] dark:bg-[#121212] flex overflow-hidden">
            
            <!-- Sidebar para Desktop (oculto en móvil) -->
            <aside 
                class="hidden lg:block bg-white shadow-sm dark:bg-[#1e1e20] transition-all duration-300 ease-in-out z-20"
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

            <!-- Contenido Principal -->
            <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
                
                <!-- Topbar -->
                <AppTopbar 
                    :title="title" 
                    @toggle-sidebar="toggleSidebar"
                />

                <!-- Main Content Scrollable Area -->
                <main class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8">
                    <!-- Slot del contenido de la página -->
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Eliminar padding por defecto del body del drawer de Element Plus para que el sidebar llene todo */
:deep(.el-drawer__body) {
    padding: 0;
    background-color: transparent;
}
</style>