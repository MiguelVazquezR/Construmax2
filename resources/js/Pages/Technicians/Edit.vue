<script setup>
import { ref, reactive } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';
import { Back } from '@element-plus/icons-vue';
import TechnicianForm from './Partials/TechnicianForm.vue'; // Nuevo componente

const props = defineProps({
    technician: Object,
});

const formRef = ref();

// Lógica de Previsualización de Foto:
const photoPreview = ref(
    props.technician.user.profile_photo_path 
        ? props.technician.user.profile_photo_url 
        : null
);

// Inicializar form con datos existentes
const form = useForm({
    _method: 'PUT', // Truco para enviar archivos en update
    // Datos User
    name: props.technician.user.name,
    email: props.technician.user.email || '',
    photo: null, 
    
    // Datos Técnico
    phone: props.technician.phone || '', 
    secondary_phone: props.technician.secondary_phone || '',
    is_internal: Boolean(props.technician.is_internal),
    status: props.technician.status, 
    
    // Geolocalización
    state: props.technician.state || '',
    city: props.technician.city || '',
    colony: props.technician.colony || '',
    zip_code: props.technician.zip_code || '',
    coverage_radius_km: props.technician.coverage_radius_km || 10,
    
    // Especialidades
    specialties: props.technician.specialties || [],
    level: props.technician.level || 'Encargado',

    // Fiscal
    legal_name: props.technician.legal_name || '',
    rfc: props.technician.rfc || '',
    
    // Bancario
    bank_name: props.technician.bank_name || '',
    bank_account: props.technician.bank_account || '',
    clabe: props.technician.clabe || '',
    
    // Interno
    internal_notes: props.technician.internal_notes || '',
    rating_avg: Number(props.technician.rating_avg) || 0,
    
    // Archivos 
    tax_file: null,
});

// Igualmente relajamos las reglas, dejando obligatorios solo Nombre, Teléfono y el Estatus.
const rules = reactive({
    name: [{ required: true, message: 'El nombre es obligatorio', trigger: 'blur' }],
    email: [
        { type: 'email', message: 'Formato de correo inválido', trigger: 'blur' }
    ],
    phone: [{ required: true, message: 'Teléfono principal requerido', trigger: 'blur' }],
    status: [{ required: true, message: 'El estatus es requerido', trigger: 'change' }],
});

const handlePhotoChange = (file) => {
    const isImage = file.raw.type.startsWith('image/');
    const isLt2M = file.size / 1024 / 1024 < 2;

    if (!isImage) {
        ElMessage.error('El archivo debe ser una imagen');
        return false;
    }
    if (!isLt2M) {
        ElMessage.error('La imagen no debe exceder 2MB');
        return false;
    }

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
            form.post(route('technicians.update', props.technician.id), {
                forceFormData: true, 
                onSuccess: () => {
                    ElMessage.success('Perfil actualizado exitosamente');
                },
                onError: (errors) => {
                    ElMessage.error('Error al actualizar. Revisa los campos.');
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
    <AppLayout title="Editar técnico">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">
                        Editar técnico
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Actualiza la información operativa y administrativa.</p>
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
                    :is-edit="true"
                    :technician="technician"
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
                            Actualizar perfil
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