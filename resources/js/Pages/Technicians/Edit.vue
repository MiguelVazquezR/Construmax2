<script setup>
import { ref, reactive } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';
import { 
    User, 
    MapLocation, 
    Document, 
    Money, 
    UploadFilled,
    Back,
    Tools,
    Plus,
    Camera
} from '@element-plus/icons-vue';

const props = defineProps({
    technician: Object,
});

const formRef = ref();

// Lógica de Previsualización de Foto:
// Solo mostramos la foto si existe un 'path' real en la BD.
// Ignoramos la 'profile_photo_url' por defecto de Jetstream (iniciales) si no hay foto subida.
const photoPreview = ref(
    props.technician.user.profile_photo_path 
        ? props.technician.user.profile_photo_url 
        : null
);

const commonSpecialties = [
    'Electricidad baja tensión',
    'Electricidad alta tensión',
    'Plomería / Fontanería',
    'Aire acondicionado (HVAC)',
    'Tablaroca y acabados',
    'Pintura general',
    'Impermeabilización',
    'Albañilería',
    'Herrería y soldadura',
    'Vidrio y aluminio',
    'Redes y voz/datos',
    'Cerrajería',
    'Limpieza industrial',
    'Carpintería',
    'Pisos y azulejos',
    'Jardinería',
    'Fumigación y plagas',
    'Instalación de cámaras (CCTV)',
    'Domótica',
    'Mantenimiento de elevadores'
];

const mexicoStates = [
    'Aguascalientes', 'Baja California', 'Baja California Sur', 'Campeche', 'Chiapas', 'Chihuahua',
    'Ciudad de México', 'Coahuila', 'Colima', 'Durango', 'Estado de México', 'Guanajuato',
    'Guerrero', 'Hidalgo', 'Jalisco', 'Michoacán', 'Morelos', 'Nayarit', 'Nuevo León',
    'Oaxaca', 'Puebla', 'Querétaro', 'Quintana Roo', 'San Luis Potosí', 'Sinaloa', 'Sonora',
    'Tabasco', 'Tamaulipas', 'Tlaxcala', 'Veracruz', 'Yucatán', 'Zacatecas'
];

// Inicializar form con datos existentes
const form = useForm({
    _method: 'PUT', // Truco para enviar archivos en update
    // Datos User
    name: props.technician.user.name,
    email: props.technician.user.email,
    photo: null, // Solo si se cambia
    
    // Datos Técnico
    phone: props.technician.phone || '', // Asegurar string
    secondary_phone: props.technician.secondary_phone || '',
    is_internal: Boolean(props.technician.is_internal),
    status: props.technician.status, // Campo extra en Edit
    
    // Geolocalización
    state: props.technician.state,
    city: props.technician.city,
    colony: props.technician.colony || '',
    zip_code: props.technician.zip_code || '',
    coverage_radius_km: props.technician.coverage_radius_km || 10,
    
    // Especialidades
    specialties: props.technician.specialties || [],

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
    
    // Archivos (No precargamos el archivo, solo si se sube uno nuevo)
    tax_file: null,
});

const rules = reactive({
    name: [{ required: true, message: 'El nombre es obligatorio', trigger: 'blur' }],
    email: [
        { required: true, message: 'El correo es obligatorio', trigger: 'blur' },
        { type: 'email', message: 'Formato de correo inválido', trigger: 'blur' }
    ],
    phone: [{ required: true, message: 'Teléfono principal requerido', trigger: 'blur' }],
    state: [{ required: true, message: 'Selecciona un estado', trigger: 'change' }],
    city: [{ required: true, message: 'La ciudad es obligatoria', trigger: 'blur' }],
    coverage_radius_km: [{ required: true, message: 'Define un radio de cobertura', trigger: 'change' }],
    specialties: [{ type: 'array', required: true, message: 'Selecciona al menos una especialidad', trigger: 'change' }],
    status: [{ required: true, message: 'El estatus es requerido', trigger: 'change' }],
    rating_avg: [{ required: true, message: 'La calificación es obligatoria', trigger: 'change' }],
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
            // Usamos post con _method: PUT para soportar FormData (archivos)
            form.post(route('technicians.update', props.technician.id), {
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
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- COLUMNA IZQUIERDA -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- 1. Datos Generales -->
                        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6 flex items-center gap-2 border-b pb-2 border-gray-100 dark:border-gray-700">
                                <el-icon class="text-primary"><User /></el-icon> Perfil del técnico
                            </h3>
                            
                            <div class="flex flex-col sm:flex-row gap-6 mb-6 items-center sm:items-start">
                                <!-- Foto de Perfil -->
                                <div class="relative group">
                                    <el-upload
                                        class="avatar-uploader"
                                        action="#"
                                        :auto-upload="false"
                                        :show-file-list="false"
                                        :on-change="handlePhotoChange"
                                        accept="image/*"
                                    >
                                        <div v-if="photoPreview" class="relative">
                                            <el-avatar :size="100" :src="photoPreview" class="border-2 border-gray-200" />
                                            <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-40 rounded-full opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                                                <el-icon class="text-white text-xl"><Camera /></el-icon>
                                            </div>
                                        </div>
                                        <!-- Placeholder vacío si no hay foto real -->
                                        <div v-else class="w-[100px] h-[100px] rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center border-2 border-dashed border-gray-300 dark:border-gray-600 cursor-pointer hover:border-primary transition-colors">
                                            <div class="text-center text-gray-400">
                                                <el-icon class="text-xl mb-1"><Plus /></el-icon>
                                                <div class="text-[10px]">Foto</div>
                                            </div>
                                        </div>
                                    </el-upload>
                                </div>

                                <div class="flex-1 w-full grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <el-form-item label="Nombre completo" prop="name" class="md:col-span-2">
                                        <el-input v-model="form.name" />
                                    </el-form-item>

                                    <el-form-item label="Correo electrónico" prop="email">
                                        <el-input v-model="form.email" />
                                    </el-form-item>

                                    <el-form-item label="Tipo de colaborador">
                                        <div class="flex items-center h-10">
                                            <el-switch
                                                v-model="form.is_internal"
                                                active-text="Empleado interno"
                                                inactive-text="Proveedor externo"
                                                style="--el-switch-on-color: #f26c17;"
                                            />
                                        </div>
                                    </el-form-item>

                                    <el-form-item label="Teléfono principal" prop="phone">
                                        <el-input v-model="form.phone" />
                                    </el-form-item>

                                    <el-form-item label="Teléfono secundario" prop="secondary_phone">
                                        <el-input v-model="form.secondary_phone" placeholder="Opcional" />
                                    </el-form-item>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Geolocalización y Habilidades -->
                        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2 border-b pb-2 border-gray-100 dark:border-gray-700">
                                <el-icon class="text-primary"><MapLocation /></el-icon> Operación y cobertura
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <el-form-item label="Estado" prop="state">
                                    <el-select v-model="form.state" placeholder="Seleccionar" class="w-full" filterable>
                                        <el-option v-for="state in mexicoStates" :key="state" :label="state" :value="state" />
                                    </el-select>
                                </el-form-item>

                                <el-form-item label="Ciudad / municipio" prop="city">
                                    <el-input v-model="form.city" />
                                </el-form-item>

                                <el-form-item label="Colonia" prop="colony">
                                    <el-input v-model="form.colony" />
                                </el-form-item>

                                <el-form-item label="Código postal" prop="zip_code">
                                    <el-input v-model="form.zip_code" maxlength="5" />
                                </el-form-item>

                                <el-form-item label="Radio de cobertura (Km)" prop="coverage_radius_km" class="md:col-span-2">
                                    <div class="flex items-center gap-4 w-full">
                                        <el-slider v-model="form.coverage_radius_km" :min="1" :max="100" class="flex-1 coverage-slider" />
                                        <span class="font-bold text-gray-600 w-16 text-right">{{ form.coverage_radius_km }} Km</span>
                                    </div>
                                </el-form-item>

                                <el-divider class="md:col-span-2" />

                                <el-form-item label="Especialidades y habilidades" prop="specialties" class="md:col-span-2">
                                    <el-select
                                        v-model="form.specialties"
                                        multiple
                                        filterable
                                        allow-create
                                        default-first-option
                                        placeholder="Selecciona o escribe nuevas etiquetas"
                                        class="w-full"
                                    >
                                        <template #prefix><el-icon><Tools /></el-icon></template>
                                        <el-option
                                            v-for="item in commonSpecialties"
                                            :key="item"
                                            :label="item"
                                            :value="item"
                                        />
                                    </el-select>
                                </el-form-item>
                            </div>
                        </div>
                    </div>

                    <!-- COLUMNA DERECHA -->
                    <div class="lg:col-span-1 space-y-6">
                        
                        <!-- 3. Gestión de Estatus -->
                        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2 border-b pb-2 border-gray-100 dark:border-gray-700">
                                <el-icon class="text-primary"><Tools /></el-icon> Estatus operativo
                            </h3>
                            
                            <el-form-item label="Estatus actual" prop="status">
                            <el-select v-model="form.status" class="w-full">
                                <el-option label="En revisión" value="En revisión" />
                                <el-option label="Activo" value="Activo" />
                                <el-option label="Inactivo" value="Inactivo" />
                                <el-option label="Vetado" value="Vetado" />
                            </el-select>
                        </el-form-item>

                        <el-form-item label="Calificación general" prop="rating_avg" class="mt-4">
                            <el-rate v-model="form.rating_avg" allow-half />
                            <p class="text-xs text-gray-400 mt-1">En caso de ser técnico nuevo sin historial, se recomienda dejar o mantener en 0 estrellas.</p>
                        </el-form-item>
                    </div>

                    <!-- 4. Datos Fiscales -->
                        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2 border-b pb-2 border-gray-100 dark:border-gray-700">
                                <el-icon class="text-primary"><Document /></el-icon> Datos fiscales
                            </h3>

                            <el-form-item label="Razón social" prop="legal_name">
                                <el-input v-model="form.legal_name" />
                            </el-form-item>

                            <el-form-item label="RFC" prop="rfc">
                                <el-input v-model="form.rfc" class="uppercase" />
                            </el-form-item>

                            <el-form-item label="Actualizar constancia fiscal">
                                <el-upload
                                    class="upload-demo w-full"
                                    drag
                                    action="#"
                                    :auto-upload="false"
                                    :on-change="handleTaxFileChange"
                                    :on-remove="handleTaxFileRemove"
                                    :limit="1"
                                    accept=".pdf,.jpg,.png,.jpeg"
                                >
                                    <el-icon class="el-icon--upload"><upload-filled /></el-icon>
                                    <div class="el-upload__text text-xs">
                                        Arrastra archivo o <em>haz clic</em>
                                    </div>
                                </el-upload>
                                <p class="text-xs text-gray-400 mt-1" v-if="form.tax_file">Nuevo archivo: {{ form.tax_file.name }}</p>
                            </el-form-item>
                        </div>

                        <!-- 5. Datos Bancarios -->
                        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2 border-b pb-2 border-gray-100 dark:border-gray-700">
                                <el-icon class="text-primary"><Money /></el-icon> Pagos
                            </h3>

                            <el-form-item label="Banco" prop="bank_name">
                                <el-input v-model="form.bank_name" />
                            </el-form-item>

                            <el-form-item label="Cuenta / tarjeta" prop="bank_account">
                                <el-input v-model="form.bank_account" />
                            </el-form-item>

                            <el-form-item label="CLABE interbancaria" prop="clabe">
                                <el-input v-model="form.clabe" maxlength="18" show-word-limit />
                            </el-form-item>
                        </div>

                        <!-- 6. Notas Internas -->
                        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                            <el-form-item label="Notas internas (bitácora)">
                                <el-input 
                                    v-model="form.internal_notes" 
                                    type="textarea" 
                                    :rows="3" 
                                    placeholder="Bitácora de comportamiento, incidentes, etc." 
                                />
                            </el-form-item>

                            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
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
                            </div>
                        </div>

                    </div>
                </div>
            </el-form>
        </div>
    </AppLayout>
</template>

<style scoped>
.uppercase :deep(input) {
    text-transform: uppercase;
}

/* FIX PARA EL SLIDER */
:deep(.el-slider__runway) {
    background-color: #e4e7ed !important;
}
.dark :deep(.el-slider__runway) {
    background-color: #4c4d4f !important;
}

:deep(.el-slider__bar) {
    background-color: #f26c17;
}

:deep(.el-slider__button) {
    border-color: #f26c17;
}

.avatar-uploader :deep(.el-upload) {
    border-radius: 50%;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}
</style>