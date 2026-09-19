<script setup>
import { ref, reactive } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PayrollProfileFields from '@/Components/Payroll/PayrollProfileFields.vue';
import PhotoUploader from '@/Components/Forms/PhotoUploader.vue';

const props = defineProps({
    roles: Array, // Recibimos la lista de roles
    faceRecognitionEnabled: Boolean,
});

const formRef = ref();
const photoPreview = ref(null);

const handlePhotoChange = (file) => {
    form.photo = file;
    photoPreview.value = URL.createObjectURL(file);
};

const form = useForm({
    name: '',
    email: '',
    password: '',
    roles: [], // Array para múltiples roles
    department: '',
    position: '',
    phone: '',
    photo: null,

    // Nómina y asistencia (opcional, según permisos)
    employee_number: '',
    hire_date: null,
    daily_salary: null,
    daily_hours: null,
    is_payroll_subject: false,
    is_attendance_subject: false,
    can_remote_attendance: false,
    kiosk_pin: '',
});

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
        { required: true, message: 'La contraseña es obligatoria', trigger: 'blur' },
        { min: 8, message: 'Mínimo 8 caracteres', trigger: 'blur' },
    ],
    roles: [
        { required: true, message: 'Debes asignar al menos un rol', trigger: 'change' },
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
            form.post(route('users.store'), {
                forceFormData: true,
                onFinish: () => form.reset('password'),
            });
        } else {
            return false;
        }
    });
};
</script>

<template>
    <AppLayout title="Crear nuevo usuario">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                    Crear nuevo usuario
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
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 border-b border-gray-100 dark:border-gray-700 pb-2 mb-3 flex items-center gap-2">
                                <el-icon class="text-primary"><User /></el-icon> Información de la cuenta
                            </h3>
                            
                            <div class="flex flex-col sm:flex-row gap-6">
                                <!-- Foto de perfil -->
                                <div class="mx-auto sm:mx-0 shrink-0">
                                    <PhotoUploader :preview="photoPreview" :max-size-mb="4" @change="handlePhotoChange" />
                                    <p v-if="form.errors.photo" class="text-center text-xs text-red-500 mt-1">{{ form.errors.photo }}</p>
                                </div>

                                <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Nombre -->
                                    <el-form-item label="Nombre completo" prop="name" :error="form.errors.name">
                                        <el-input v-model="form.name" placeholder="Ej. Juan Pérez" />
                                    </el-form-item>

                                    <!-- Email -->
                                    <el-form-item label="Correo electrónico" prop="email" :error="form.errors.email">
                                        <el-input v-model="form.email" placeholder="juan@empresa.com" />
                                    </el-form-item>

                                    <!-- Password -->
                                    <el-form-item label="Contraseña" prop="password" :error="form.errors.password">
                                        <el-input v-model="form.password" placeholder="Ingrese la contraseña inicial" />
                                    </el-form-item>

                                    <!-- Roles (Selector Múltiple) -->
                                    <el-form-item label="Rol de usuario" prop="roles" :error="form.errors.roles">
                                        <el-select 
                                            v-model="form.roles" 
                                            multiple 
                                            placeholder="Seleccionar roles" 
                                            class="w-full"
                                            collapse-tags
                                            collapse-tags-tooltip
                                        >
                                            <el-option
                                                v-for="role in roles"
                                                :key="role.id"
                                                :label="role.name"
                                                :value="role.name"
                                            />
                                        </el-select>
                                    </el-form-item>
                                </div>
                            </div>

                            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                                <template v-if="faceRecognitionEnabled">
                                    Se usa como referencia del reconocimiento facial del kiosco: foto de frente, con buena iluminación y sin lentes oscuros.
                                </template>
                                <template v-else>
                                    Se muestra como foto de perfil del colaborador en todo el sistema.
                                </template>
                            </p>
                        </div>

                        <!-- Sección: Datos de Empleado -->
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 border-b border-gray-100 dark:border-gray-700 pb-2 mb-3 flex items-center gap-2">
                                <el-icon class="text-primary"><Suitcase /></el-icon> Datos del empleado
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- Departamento -->
                                <el-form-item label="Departamento" prop="department" :error="form.errors.department" class="md:col-span-1">
                                    <el-select v-model="form.department" placeholder="Seleccionar" class="w-full">
                                        <el-option label="Administración y ventas" value="Administración y ventas" />
                                        <el-option label="Construcción" value="Construcción" />
                                        <el-option label="Costos y presupuestos" value="Costos y presupuestos" />
                                        <el-option label="Mantenimiento" value="Mantenimiento" />
                                        <el-option label="Obras" value="Obras" />
                                    </el-select>
                                </el-form-item>

                                <!-- Puesto -->
                                <el-form-item label="Puesto" prop="position" :error="form.errors.position" class="md:col-span-1">
                                    <el-input v-model="form.position" placeholder="Ej. Gerente de ventas" />
                                </el-form-item>

                                <!-- Teléfono -->
                                <el-form-item label="Teléfono" prop="phone" :error="form.errors.phone" class="md:col-span-1">
                                    <el-input v-model="form.phone" placeholder="Ej. 55 1234 5678" />
                                </el-form-item>
                            </div>
                        </div>

                        <!-- Sección: Nómina y asistencia -->
                        <PayrollProfileFields :form="form" />

                        <!-- Botones de Acción -->
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                            <Link :href="route('users.index')">
                                <el-button>Cancelar</el-button>
                            </Link>
                            <el-button type="primary" native-type="submit" :loading="form.processing" color="#f26c17">
                                Guardar usuario
                            </el-button>
                        </div>

                    </el-form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Formulario compacto: menos espacio entre campos */
:deep(.el-form-item) {
    margin-bottom: 12px;
}

:deep(.el-form-item__label) {
    padding-bottom: 4px;
}
</style>