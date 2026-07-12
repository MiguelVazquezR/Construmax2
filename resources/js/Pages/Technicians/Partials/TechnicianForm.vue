<script setup>
import { 
    User, 
    MapLocation, 
    Document, 
    UploadFilled,
    Tools,
    Plus,
    Camera
} from '@element-plus/icons-vue';
import BankAccountsTab from './BankAccountsTab.vue';

defineProps({
    form: {
        type: Object,
        required: true
    },
    photoPreview: {
        type: String,
        default: null
    },
    isEdit: {
        type: Boolean,
        default: false
    },
    technician: {
        type: Object,
        default: null
    }
});

defineEmits(['photo-change', 'tax-file-change', 'tax-file-remove']);

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
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- COLUMNA IZQUIERDA: Perfil y Operación -->
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
                            :on-change="(file) => $emit('photo-change', file)"
                            accept="image/*"
                        >
                            <div v-if="photoPreview" class="relative">
                                <el-avatar :size="100" :src="photoPreview" class="border-2 border-gray-200" />
                                <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-40 rounded-full opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                                    <el-icon class="text-white text-xl"><Camera /></el-icon>
                                </div>
                            </div>
                            <div v-else class="w-[100px] h-[100px] rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center border-2 border-dashed border-gray-300 dark:border-gray-600 cursor-pointer hover:border-primary transition-colors">
                                <div class="text-center text-gray-400">
                                    <el-icon class="text-xl mb-1"><Plus /></el-icon>
                                    <div class="text-[10px]">Foto</div>
                                </div>
                            </div>
                        </el-upload>
                        <p class="text-center text-xs text-gray-400 mt-2">Opcional</p>
                    </div>

                    <div class="flex-1 w-full grid grid-cols-1 md:grid-cols-2 gap-4">
                        <el-form-item label="Nombre completo" prop="name" class="md:col-span-2">
                            <el-input v-model="form.name" placeholder="Ej. Juan Pérez López" />
                        </el-form-item>

                        <el-form-item label="Correo electrónico" prop="email">
                            <el-input v-model="form.email" placeholder="contacto@tecnico.com" />
                        </el-form-item>

                        <el-form-item label="Teléfono principal (móvil)" prop="phone">
                            <el-input v-model="form.phone" placeholder="10 dígitos" />
                        </el-form-item>

                        <el-form-item label="Tipo de colaborador" class="md:col-span-2">
                            <div class="flex items-center h-10">
                                <el-switch
                                    v-model="form.is_internal"
                                    active-text="Empleado interno"
                                    inactive-text="Proveedor externo"
                                    style="--el-switch-on-color: #f26c17;"
                                />
                            </div>
                            <div class="mt-2 text-xs text-gray-500 bg-gray-50 dark:bg-[#252529] p-3 rounded border border-gray-100 dark:border-[#3f3f46]">
                                <p><span class="font-bold text-gray-700 dark:text-gray-300">Empleado interno:</span> Personal que se encuentra directamente en la nómina de la empresa.</p>
                                <p class="mt-1"><span class="font-bold text-gray-700 dark:text-gray-300">Proveedor externo:</span> Contratista independiente o freelance (pago por destajo/proyecto).</p>
                            </div>
                        </el-form-item>

                        <el-form-item label="Teléfono secundario (emergencias)" prop="secondary_phone">
                            <el-input v-model="form.secondary_phone" placeholder="Opcional" />
                        </el-form-item>

                        <el-form-item label="Nivel / categoría" prop="level">
                            <el-select v-model="form.level" class="w-full">
                                <el-option label="Encargado" value="Encargado" />
                                <el-option label="Auxiliar / Ayudante" value="Auxiliar/Ayudante" />
                            </el-select>
                        </el-form-item>

                        <!-- En creación, el rating va aquí. En edición va en el panel de estatus -->
                        <el-form-item v-if="!isEdit" label="Calificación inicial" prop="rating_avg">
                            <el-rate v-model="form.rating_avg" allow-half />
                            <p class="text-xs text-gray-400 mt-1">Sugerido dejar en 0 para ingresos recientes.</p>
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
                        <el-select v-model="form.state" placeholder="Opcional" class="w-full" filterable clearable>
                            <el-option v-for="state in mexicoStates" :key="state" :label="state" :value="state" />
                        </el-select>
                    </el-form-item>

                    <el-form-item label="Ciudad / municipio" prop="city">
                        <el-input v-model="form.city" placeholder="Ej. Guadalajara (Opcional)" />
                    </el-form-item>

                    <el-form-item label="Colonia" prop="colony">
                        <el-input v-model="form.colony" placeholder="Ej. Centro (Opcional)" />
                    </el-form-item>

                    <el-form-item label="Código postal" prop="zip_code">
                        <el-input v-model="form.zip_code" placeholder="Ej. 44100" maxlength="5" />
                    </el-form-item>

                    <el-form-item label="Radio de cobertura (Km)" prop="coverage_radius_km" class="md:col-span-2">
                        <div class="flex items-center gap-4 w-full">
                            <el-slider v-model="form.coverage_radius_km" :min="1" :max="100" class="flex-1 coverage-slider" />
                            <span class="font-bold text-gray-600 w-16 text-right">{{ form.coverage_radius_km }} Km</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Distancia máxima que el técnico está dispuesto a desplazarse desde su base.</p>
                    </el-form-item>

                    <el-divider class="md:col-span-2" />

                    <el-form-item label="Especialidades y habilidades" prop="specialties" class="md:col-span-2">
                        <el-select
                            v-model="form.specialties"
                            multiple
                            filterable
                            allow-create
                            default-first-option
                            placeholder="Selecciona o escribe nuevas etiquetas (Opcional)"
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
                        <p class="text-xs text-gray-400 mt-1">Puedes escribir una nueva especialidad y presionar Enter para agregarla.</p>
                    </el-form-item>
                </div>
            </div>

            <!-- 3. Cuentas bancarias (solo edición) -->
            <div v-if="isEdit && technician" class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                <BankAccountsTab :technician="technician" />
            </div>
        </div>

        <!-- COLUMNA DERECHA: Fiscal y Extras -->
        <div class="lg:col-span-1 space-y-6">
            
            <!-- 4. Gestión de Estatus (SOLO EN EDICIÓN) -->
            <div v-if="isEdit" class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
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
                    <p class="text-xs text-gray-400 mt-1">Refleja el desempeño general del técnico.</p>
                </el-form-item>
            </div>

            <!-- 5. Datos Fiscales -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2 border-b pb-2 border-gray-100 dark:border-gray-700">
                    <el-icon class="text-primary"><Document /></el-icon> Datos fiscales
                </h3>

                <el-form-item label="Razón social" prop="legal_name">
                    <el-input v-model="form.legal_name" placeholder="Opcional" />
                </el-form-item>

                <el-form-item label="RFC" prop="rfc">
                    <el-input v-model="form.rfc" placeholder="ABCD123456XYZ" class="uppercase" />
                </el-form-item>

                <el-form-item :label="isEdit ? 'Actualizar constancia fiscal' : 'Constancia de situación fiscal'">
                    <el-upload
                        class="upload-demo w-full"
                        drag
                        action="#"
                        :auto-upload="false"
                        :on-change="(file) => $emit('tax-file-change', file)"
                        :on-remove="() => $emit('tax-file-remove')"
                        :limit="1"
                        accept=".pdf,.jpg,.png,.jpeg"
                    >
                        <el-icon class="el-icon--upload"><upload-filled /></el-icon>
                        <div class="el-upload__text text-xs">
                            Arrastra archivo o <em>haz clic</em>
                        </div>
                    </el-upload>
                    <p class="text-xs text-gray-400 mt-1" v-if="form.tax_file">
                        Archivo seleccionado: {{ form.tax_file.name }}
                    </p>
                </el-form-item>
            </div>

            <!-- 5. Notas Internas y Submit -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                <el-form-item label="Notas internas (bitácora)">
                    <el-input 
                        v-model="form.internal_notes" 
                        type="textarea" 
                        :rows="3" 
                        placeholder="Comentarios opcionales sobre la contratación, referencias, etc." 
                    />
                </el-form-item>

                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <!-- Se inyectarán los botones de guardar y cancelar desde el componente padre -->
                    <slot name="actions"></slot>
                </div>
            </div>

        </div>
    </div>
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

/* ESTILOS AVATAR UPLOAD */
.avatar-uploader :deep(.el-upload) {
    border-radius: 50%;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}
</style>