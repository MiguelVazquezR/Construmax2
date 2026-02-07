<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('user-password.update'), {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            ElMessage.success('Contraseña actualizada correctamente.');
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }

            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
        
        <div class="p-6 border-b border-gray-100 dark:border-[#2b2b2e] bg-gray-50/50 dark:bg-[#252529]">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white flex items-center gap-2">
                <el-icon class="text-primary"><Lock /></el-icon> Seguridad y Contraseña
            </h3>
            <p class="text-sm text-gray-500 mt-1">Asegúrate de usar una contraseña larga y aleatoria para mantener tu cuenta segura.</p>
        </div>

        <div class="p-6">
            <form @submit.prevent="updatePassword">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contraseña Actual</label>
                        <el-input 
                            ref="currentPasswordInput"
                            v-model="form.current_password" 
                            type="password" 
                            show-password
                            size="large" 
                            placeholder="••••••••"
                        />
                        <p v-if="form.errors.current_password" class="text-xs text-red-500 mt-1">{{ form.errors.current_password }}</p>
                    </div>

                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nueva Contraseña</label>
                        <el-input 
                            ref="passwordInput"
                            v-model="form.password" 
                            type="password" 
                            show-password
                            size="large" 
                            placeholder="Nueva contraseña"
                        />
                        <p v-if="form.errors.password" class="text-xs text-red-500 mt-1">{{ form.errors.password }}</p>
                    </div>

                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirmar Contraseña</label>
                        <el-input 
                            v-model="form.password_confirmation" 
                            type="password" 
                            show-password
                            size="large" 
                            placeholder="Repite la contraseña"
                        />
                        <p v-if="form.errors.password_confirmation" class="text-xs text-red-500 mt-1">{{ form.errors.password_confirmation }}</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <el-button 
                        type="primary" 
                        native-type="submit" 
                        color="#f26c17" 
                        :loading="form.processing"
                        class="!font-bold"
                    >
                        Actualizar Contraseña
                    </el-button>
                </div>
            </form>
        </div>
    </div>
</template>