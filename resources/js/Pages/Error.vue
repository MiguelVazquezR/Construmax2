<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

// Recibimos el código de estado desde el backend
const props = defineProps({
    status: {
        type: Number,
        required: true,
    },
});

// Configuración de textos e iconos según el error
const errorDetails = computed(() => {
    const map = {
        404: {
            title: '404',
            subTitle: 'Página no encontrada',
            description: 'Lo sentimos, la página que estás buscando no existe o ha sido movida.',
            icon: '404', // Icono nativo de Element Plus
        },
        403: {
            title: '403',
            subTitle: 'Acceso Denegado',
            description: 'No tienes permisos suficientes para acceder a esta sección del ERP.',
            icon: '403', // Icono nativo de Element Plus
        },
        419: {
            title: '419',
            subTitle: 'Sesión Expirada',
            description: 'Tu sesión ha caducado por inactividad. Por favor, recarga o vuelve al inicio.',
            icon: 'warning',
        },
        500: {
            title: '500',
            subTitle: 'Error del Servidor',
            description: 'Ocurrió un problema interno en nuestros servidores. Inténtalo más tarde.',
            icon: '500', // Icono nativo de Element Plus
        },
        503: {
            title: '503',
            subTitle: 'Servicio No Disponible',
            description: 'Estamos realizando tareas de mantenimiento. Volveremos en breve.',
            icon: 'info',
        },
    };

    return map[props.status] || {
        title: 'Error',
        subTitle: 'Algo salió mal',
        description: 'Ocurrió un error inesperado.',
        icon: 'error',
    };
});
</script>

<template>
    <Head :title="`${props.status} - ${errorDetails.subTitle}`" />

    <div class="flex h-screen w-full items-center justify-center bg-gray-50 text-gray-800 dark:bg-gray-900 dark:text-gray-100">
        <div class="w-full max-w-lg rounded-xl bg-white p-8 shadow-lg dark:bg-gray-800">
            <!-- Componente Result de Element Plus -->
            <el-result
                :icon="errorDetails.icon"
                :title="errorDetails.title"
                :sub-title="errorDetails.subTitle"
            >
                <template #extra>
                    <div class="flex flex-col items-center space-y-4">
                        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ errorDetails.description }}
                        </p>

                        <!-- Botón de redirección -->
                        <Link href="/dashboard" class="inline-block">
                            <el-button type="primary" size="large" round>
                                Volver al Dashboard
                            </el-button>
                        </Link>
                    </div>
                </template>
            </el-result>
        </div>
    </div>
</template>

<style scoped>
/* Ajustes finos para modo oscuro si Element Plus no lo detecta automáticamente */
:deep(.el-result__title) {
    @apply dark:text-white;
}
:deep(.el-result__subtitle) {
    @apply dark:text-gray-400;
}
</style>