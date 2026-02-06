<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Iniciar Sesión" />

    <!-- Contenedor: min-h-screen asegura que esté centrado verticalmente -->
    <div class="min-h-screen flex flex-col justify-center items-center bg-[#f4f6f8] dark:bg-[#121212] transition-colors duration-300 py-4">
        
        <!-- Tarjeta: Más ancha (480px) y menos padding vertical (p-6) para ahorrar altura -->
        <div class="w-full max-w-[480px] bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border-t-4 border-primary p-6 sm:px-8 sm:py-7">
            
            <!-- Encabezado: Márgenes reducidos -->
            <div class="text-center mb-6">
                <div class="flex justify-center">
                    <!-- Logo un poco más pequeño (h-10) para ahorrar espacio -->
                    <ApplicationLogo class="!h-32 w-auto object-contain" />
                </div>
                <h2 class="text-xl font-bold text-gray-800 dark:text-white tracking-tight">
                    Acceso Corporativo
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Ingresa tus credenciales para continuar
                </p>
            </div>

            <!-- Mensaje de Estado -->
            <div v-if="status" class="mb-4 bg-green-50 text-green-700 p-2.5 rounded text-sm text-center border border-green-200">
                {{ status }}
            </div>

            <!-- Formulario: Espaciado vertical más ajustado (space-y-4) -->
            <form @submit.prevent="submit" class="space-y-5">
                
                <!-- Email -->
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400 pl-1">
                        Correo Electrónico
                    </label>
                    <el-input 
                        v-model="form.email" 
                        placeholder="nombre@empresa.com" 
                        size="large"
                        class="w-full minimal-input"
                    >
                        <template #prefix>
                            <span class="text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                            </span>
                        </template>
                    </el-input>
                    <span v-if="form.errors.email" class="text-red-500 text-xs pl-1">
                        {{ form.errors.email }}
                    </span>
                </div>

                <!-- Password -->
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400 pl-1">
                        Contraseña
                    </label>
                    <el-input 
                        v-model="form.password" 
                        type="password" 
                        placeholder="••••••••" 
                        show-password 
                        size="large"
                        class="w-full minimal-input"
                    >
                        <template #prefix>
                            <span class="text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                            </span>
                        </template>
                    </el-input>
                    <span v-if="form.errors.password" class="text-red-500 text-xs pl-1">
                        {{ form.errors.password }}
                    </span>
                </div>

                <!-- Opciones -->
                <div class="flex items-center justify-between pt-1">
                    <el-checkbox v-model="form.remember" label="Recordarme" size="default" class="minimal-checkbox" />
                    
                    <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm text-gray-500 hover:text-primary transition-colors duration-200">
                        ¿Olvidaste tu contraseña?
                    </Link>
                </div>

                <!-- Botón de Acción -->
                <el-button 
                    :loading="form.processing"
                    native-type="submit"
                    class="w-full !h-10 !text-base !font-semibold !rounded shadow-sm hover:shadow-md transition-all duration-200"
                    color="#f26c17" 
                    type="primary"
                >
                    Ingresar
                </el-button>
            </form>
        </div>

        <!-- Footer pegado a la tarjeta -->
        <div class="mt-5 text-center">
            <p class="text-xs text-gray-400 dark:text-gray-600">
                &copy; {{ new Date().getFullYear() }} Sistema ERP.
            </p>
        </div>
    </div>
</template>

<style scoped>
/* Ajustes finos para Element Plus Minimalista */

:deep(.el-input__wrapper) {
    background-color: #f9fafb;
    box-shadow: none !important;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 8px 11px;
    transition: all 0.2s ease;
}

:deep(.el-input__wrapper:hover) {
    background-color: #fff;
    border-color: #d1d5db;
}

:deep(.el-input__wrapper.is-focus) {
    background-color: #fff;
    border-color: #f26c17 !important;
    box-shadow: 0 0 0 1px #f26c17 !important;
}

/* Modo Oscuro para Inputs */
:global(.dark) :deep(.el-input__wrapper) {
    background-color: #27272a;
    border-color: #3f3f46;
}

:global(.dark) :deep(.el-input__wrapper:hover),
:global(.dark) :deep(.el-input__wrapper.is-focus) {
    border-color: #f26c17 !important;
}

:global(.dark) :deep(.el-input__inner) {
    color: white;
}

/* Checkbox Personalizado */
:deep(.minimal-checkbox .el-checkbox__input.is-checked .el-checkbox__inner) {
    background-color: #f26c17;
    border-color: #f26c17;
}

:deep(.minimal-checkbox .el-checkbox__label) {
    color: #6b7280;
    font-weight: 400;
}
</style>