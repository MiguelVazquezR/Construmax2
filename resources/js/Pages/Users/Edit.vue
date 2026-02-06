<script setup>
import { ref, reactive } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    user: Object,
});

const formRef = ref();

// Inicializamos el formulario con los datos existentes
const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '', // Se deja vacío intencionalmente
    department: props.user.employee?.department || '',
    position: props.user.employee?.position || '',
    phone: props.user.employee?.phone || '',
});

// Reglas de validación (Password es opcional aquí)
const rules = reactive({
    name: [
        { required: true, message: 'El nombre es obligatorio', trigger: 'blur' },
        { min: 3, message: 'Debe tener al menos 3 caracteres', trigger: 'blur' },
    ],
    email: [
        { required: true, message: 'El correo es obligatorio', trigger: 'blur' },
        { type: 'email', message: 'Ingresa un correo válido', trigger: 'blur' },
    ],
    password: [
        // No es requerido, solo validamos longitud si se escribe algo
        { min: 8, message: 'Mínimo 8 caracteres', trigger: 'blur' },
    ],
    department: [
        { required: true, message: 'El departamento es obligatorio', trigger: 'change' },
    ],
    position: [
        { required: true, message: 'El puesto es obligatorio', trigger: 'blur' },
    ],
    phone: [
        { required: true, message: 'El teléfono es obligatorio', trigger: 'blur' },
    ],
});

const submit = () => {
    if (!formRef.value) return;
    
    formRef.value.validate((valid) => {
        if (valid) {
            form.put(route('users.update', props.user.id), {
                onFinish: () => form.reset('password'),
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Editar usuario">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                    Editar usuario: {{ user.name }}
                </h2>
                <Link :href="route('users.index')">
                    <el-button icon="Back" circle />
                </Link>
            </div>
        </template>

        <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-[#1e1e20] overflow-hidden shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e]">
                <div class="p-6 md:p-8">
                    
                    <el-form 
                        ref="formRef"
                        :model="form" 
                        :rules="rules" 
                        label-position="top"
                        require-asterisk-position="right"
                        size="large"
                        @submit.prevent="submit"
                    >
                        <!-- Sección: Datos de Cuenta -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 border-b border-gray-100 dark:border-gray-700 pb-2 mb-4 flex items-center gap-2">
                                <el-icon class="text-primary"><User /></el-icon> Información de la cuenta
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <el-form-item label="Nombre completo" prop="name" :error="form.errors.name">
                                    <el-input v-model="form.name" />
                                </el-form-item>

                                <el-form-item label="Correo electrónico" prop="email" :error="form.errors.email">
                                    <el-input v-model="form.email" />
                                </el-form-item>

                                <el-form-item label="Nueva contraseña (opcional)" prop="password" :error="form.errors.password">
                                    <el-input 
                                        v-model="form.password" 
                                        placeholder="Dejar vacío para mantener la actual" 
                                    />
                                </el-form-item>
                            </div>
                        </div>

                        <!-- Sección: Datos de Empleado -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 border-b border-gray-100 dark:border-gray-700 pb-2 mb-4 flex items-center gap-2">
                                <el-icon class="text-primary"><Suitcase /></el-icon> Datos del empleado
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <el-form-item label="Departamento" prop="department" :error="form.errors.department" class="md:col-span-1">
                                    <el-select v-model="form.department" placeholder="Seleccionar" class="w-full">
                                        <el-option label="Administración y ventas" value="Administración y ventas" />
                                        <el-option label="Construcción" value="Construcción" />
                                        <el-option label="Costos y presupuestos" value="Costos y presupuestos" />
                                        <el-option label="Mantenimiento" value="Mantenimiento" />
                                        <el-option label="Obras" value="Obras" />
                                    </el-select>
                                </el-form-item>

                                <el-form-item label="Puesto" prop="position" :error="form.errors.position" class="md:col-span-1">
                                    <el-input v-model="form.position" />
                                </el-form-item>

                                <el-form-item label="Teléfono" prop="phone" :error="form.errors.phone" class="md:col-span-1">
                                    <el-input v-model="form.phone" />
                                </el-form-item>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                            <Link :href="route('users.index')">
                                <el-button>Cancelar</el-button>
                            </Link>
                            <el-button type="primary" native-type="submit" :loading="form.processing" color="#f26c17">
                                Actualizar usuario
                            </el-button>
                        </div>

                    </el-form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>