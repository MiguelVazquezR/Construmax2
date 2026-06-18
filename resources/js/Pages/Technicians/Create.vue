<script setup>
import { ref, reactive } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';
import { Back } from '@element-plus/icons-vue';
import TechnicianForm from './Partials/TechnicianForm.vue'; // Nuevo componente

const formRef = ref();
const photoPreview = ref(null);

const form = useForm({
    // Datos User
    name: '',
    email: '',
    phone: '',
    photo: null, 
    
    // Datos Técnico
    secondary_phone: '',
    is_internal: false,
    
    // Geolocalización
    state: '',
    city: '',
    colony: '',
    zip_code: '',
    coverage_radius_km: 10,
    
    // Especialidades
    specialties: [],
    level: 'Encargado',

    // Fiscal
    legal_name: '',
    rfc: '',
    
    // Bancario
    bank_name: '',
    bank_account: '',
    clabe: '',
    
    // Interno
    internal_notes: '',
    rating_avg: 0,
    
    // Archivos
    tax_file: null,
});

// Dejamos requeridos SOLAMENTE Nombre y Teléfono para permitir creación rápida
const rules = reactive({
    name: [{ required: true, message: 'El nombre es obligatorio', trigger: 'blur' }],
    email: [
        { type: 'email', message: 'Formato de correo inválido', trigger: 'blur' }
    ],
    phone: [{ required: true, message: 'Teléfono principal requerido', trigger: 'blur' }],
});

const handlePhotoChange = (file) => {
    form.photo = file.raw;
    photoPreview.value = URL.createObjectURL(file.raw);
};

const handleTaxFileChange = (file) => {
    const isLt5M = file.size / 1024 / 1024 < 5;
    if (!isLt5M) {
        ElMessage.error('El archivo no debe exceder los 5MB');
        return false;
    }
    form.tax_file = file.raw;
};

const handleTaxFileRemove = () => {
    form.tax_file = null;
};

const submit = () => {
    if (!formRef.value) return;
    
    formRef.value.validate((valid) => {
        if (valid) {
            form.post(route('technicians.store'), {
                forceFormData: true, // <-- CORRECCIÓN: Obliga a Inertia a enviar los archivos
                onSuccess: () => {
                    ElMessage.success('Técnico registrado exitosamente');
                },
                onError: (errors) => {
                    ElMessage.error('Error al registrar. Revisa los campos marcados.');
                    console.error(errors);
                }
            });
        } else {
            ElMessage.warning('Completa los campos obligatorios');
            return false;
        }
    });
};
</script>

<template>
    <AppLayout title="Nuevo técnico">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">
                        Alta de técnico
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Registra un nuevo proveedor de servicios operativos.</p>
                </div>
                <Link :href="route('technicians.index')">
                    <el-button :icon="Back" circle plain />
                </Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <el-form 
                ref="formRef"
                :model="form" 
                :rules="rules" 
                label-position="top"
                require-asterisk-position="right"
                size="large"
                @submit.prevent="submit"
            >
                <TechnicianForm 
                    :form="form"
                    :photo-preview="photoPreview"
                    :is-edit="false"
                    @photo-change="handlePhotoChange"
                    @tax-file-change="handleTaxFileChange"
                    @tax-file-remove="handleTaxFileRemove"
                >
                    <template #actions>
                        <el-button 
                            type="primary" 
                            native-type="submit" 
                            class="w-full !font-bold !h-12 !text-lg" 
                            color="#f26c17"
                            :loading="form.processing"
                        >
                            Registrar técnico
                        </el-button>
                        <div class="text-center mt-3">
                            <Link :href="route('technicians.index')" class="text-sm text-gray-500 hover:text-primary">
                                Cancelar
                            </Link>
                        </div>
                    </template>
                </TechnicianForm>
            </el-form>
        </div>
    </AppLayout>
</template>