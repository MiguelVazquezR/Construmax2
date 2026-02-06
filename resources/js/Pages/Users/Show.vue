<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    user: Object,
});
</script>

<template>
    <AppLayout title="Detalles de usuario">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                    Detalles de usuario
                </h2>
                <Link :href="route('users.index')">
                    <el-button icon="Back" circle />
                </Link>
            </div>
        </template>

        <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg overflow-hidden border border-gray-100 dark:border-[#2b2b2e]">
                
                <!-- Cabecera de Perfil -->
                <div class="p-6 md:p-8 border-b border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row items-center sm:items-start gap-6">
                    <el-avatar :size="80" :src="user.profile_photo_url" class="border-4 border-white shadow-md" />
                    <div class="text-center sm:text-left flex-1">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ user.name }}</h3>
                        <p class="text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                        <div class="mt-3">
                            <el-tag :type="user.is_active ? 'success' : 'danger'" effect="dark">
                                {{ user.is_active ? 'Activo' : 'Inactivo / Baja' }}
                            </el-tag>
                        </div>
                    </div>
                    <div>
                        <Link :href="route('users.edit', user.id)">
                            <el-button type="primary" color="#f26c17" icon="Edit">
                                Editar
                            </el-button>
                        </Link>
                    </div>
                </div>

                <!-- Detalles -->
                <div class="p-6 md:p-8">
                    <el-descriptions title="Información del empleado" :column="2" border size="large">
                        <el-descriptions-item label="Departamento">
                            {{ user.employee?.department || 'No asignado' }}
                        </el-descriptions-item>
                        
                        <el-descriptions-item label="Puesto">
                            {{ user.employee?.position || 'No asignado' }}
                        </el-descriptions-item>
                        
                        <el-descriptions-item label="Teléfono">
                            {{ user.employee?.phone || 'No asignado' }}
                        </el-descriptions-item>
                        
                        <el-descriptions-item label="Fecha de registro">
                            {{ new Date(user.created_at).toLocaleDateString() }}
                        </el-descriptions-item>
                    </el-descriptions>

                    <div class="mt-8 bg-gray-50 dark:bg-[#252529] p-4 rounded-lg text-sm text-gray-600 dark:text-gray-400">
                        <h4 class="font-bold mb-2">Información técnica</h4>
                        <p>ID de usuario: <span class="font-mono">{{ user.id }}</span></p>
                        <p>Verificación de correo: {{ user.email_verified_at ? 'Verificado' : 'Pendiente' }}</p>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Ajustes para el modo oscuro en el-descriptions */
:global(.dark) :deep(.el-descriptions__body),
:global(.dark) :deep(.el-descriptions__label),
:global(.dark) :deep(.el-descriptions__content) {
    background-color: #1e1e20;
    color: #e5e7eb;
    border-color: #3f3f46;
}
</style>