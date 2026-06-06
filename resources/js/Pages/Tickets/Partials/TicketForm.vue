<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { OfficeBuilding, Document, UserFilled } from '@element-plus/icons-vue';
import TaskTemplateModal from './TaskTemplateModal.vue';

const props = defineProps({
    form: {
        type: Object,
        required: true
    },
    users: {
        type: Array,
        required: true
    },
    customers: {
        type: Array,
        default: () => []
    },
    templates: {
        type: Array,
        default: () => []
    },
    isEdit: {
        type: Boolean,
        default: false
    }
});

defineEmits(['open-quick-tech']);

const showTaskTemplateModal = ref(false);

const handleTemplateSaved = () => {
    router.reload({ only: ['templates'] });
};

const serviceTypes = [
    { id: 1, name: 'Iluminación' },
    { id: 2, name: 'Herrería' },
    { id: 3, name: 'Acabados' },
    { id: 4, name: 'Eléctrico' },
    { id: 5, name: 'Aire acondicionado' },
    { id: 6, name: 'Sanitario' },
    { id: 7, name: 'Anuncios' },
    { id: 8, name: 'Pintura' },
    { id: 9, name: 'Carpintería' },
    { id: 10, name: 'Vidrio' },
    { id: 11, name: 'Aluminio' },
    { id: 12, name: 'Protección civil STPS' },
    { id: 13, name: 'Monta cargas' },
    { id: 14, name: 'Control de plagas' },
    { id: 15, name: 'Impermeabilización' },
    { id: 16, name: 'Servicios varios' }
];

const statuses = [
    'Borrador', 
    'Levantamiento', 
    'Catálogo', 
    'Proceso de ejecución', 
    'Ejecutado', 
    'Facturado', 
    'Pagado'
];

const filteredContacts = computed(() => {
    if (!props.form.customer_id) return [];
    const customer = props.customers.find(c => c.id === props.form.customer_id);
    return customer ? customer.contacts : [];
});

// Ahora leemos las sucursales directamente del cliente
const customerBranches = computed(() => {
    if (!props.form.customer_id) return [];
    const customer = props.customers.find(c => c.id === props.form.customer_id);
    
    if (!customer || !customer.branches) return [];
    
    return customer.branches.map(b => ({
        label: b.branch_name ? `${b.branch_name} (${b.unit}) - ${b.region}` : `${b.unit} - ${b.region}`,
        value: b.id
    }));
});

const handleCustomerChange = () => {
    props.form.customer_contact_id = '';
    props.form.customer_branch_id = '';
    
    // Auto-seleccionar sucursal si el cliente solo tiene una
    if (customerBranches.value.length === 1) {
        props.form.customer_branch_id = customerBranches.value[0].value;
    }
};

const handleContactChange = () => {
    // Ya no blanqueamos la sucursal obligatoriamente al cambiar de contacto,
    // porque las sucursales son del cliente.
};

const technicianUsers = computed(() => {
    return props.users.filter(u => u.technician);
});

const sellerUsers = computed(() => {
    return props.users.filter(u => u.employee);
});
</script>

<template>
    <div class="space-y-6">
        
        <!-- SECCIÓN 1: CLIENTE Y SITIO -->
        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2 border-b pb-3 dark:border-gray-700">
                <el-icon class="text-primary"><OfficeBuilding /></el-icon> Información del cliente y sitio
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <el-form-item label="Cliente" prop="customer_id" :error="form.errors.customer_id">
                    <el-select 
                        v-model="form.customer_id" 
                        placeholder="Seleccionar cliente" 
                        class="w-full" 
                        filterable
                        @change="handleCustomerChange"
                    >
                        <el-option v-for="customer in customers" :key="customer.id" :label="customer.name" :value="customer.id" />
                    </el-select>
                </el-form-item>

                <el-form-item label="Contacto principal" prop="customer_contact_id" :error="form.errors.customer_contact_id">
                    <el-select 
                        v-model="form.customer_contact_id" 
                        placeholder="Seleccionar contacto" 
                        class="w-full"
                        :disabled="!form.customer_id"
                        @change="handleContactChange"
                    >
                        <el-option v-for="contact in filteredContacts" :key="contact.id" :label="contact.name" :value="contact.id" />
                    </el-select>
                </el-form-item>

                <el-form-item label="Sucursal / sitio" prop="customer_branch_id" :error="form.errors.customer_branch_id">
                    <el-select 
                        v-model="form.customer_branch_id" 
                        placeholder="Seleccionar sucursal" 
                        class="w-full"
                        :disabled="!form.customer_id"
                        filterable
                        clearable
                    >
                        <el-option v-for="branch in customerBranches" :key="branch.value" :label="branch.label" :value="branch.value" />
                    </el-select>
                </el-form-item>
            </div>
        </div>

        <!-- SECCIÓN 2: DATOS DEL PROYECTO -->
        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2 border-b pb-3 dark:border-gray-700">
                <el-icon class="text-primary"><Document /></el-icon> Datos del proyecto / servicio
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <el-form-item label="Nombre del proyecto" prop="name" :error="form.errors.name" class="md:col-span-2">
                    <el-input v-model="form.name" placeholder="Ej. Mantenimiento general de luminarias" />
                </el-form-item>

                <el-form-item label="Tipo de servicio" prop="service_type" :error="form.errors.service_type">
                    <el-select v-model="form.service_type" placeholder="Seleccionar" class="w-full" filterable>
                        <el-option v-for="item in serviceTypes" :key="item.id" :label="`${item.id} - ${item.name}`" :value="item.name">
                            <span style="float: left">{{ item.id }}</span>
                            <span style="float: right; color: var(--el-text-color-secondary); font-size: 13px">{{ item.name }}</span>
                        </el-option>
                    </el-select>
                </el-form-item>

                <el-form-item label="Duración estimada" prop="duration" :error="form.errors.duration">
                    <el-input v-model="form.duration" placeholder="Ej. 2 semanas" />
                </el-form-item>
                
                <el-form-item label="Prioridad" prop="priority" :error="form.errors.priority">
                    <el-select v-model="form.priority" class="w-full">
                        <el-option label="Baja" value="Baja" />
                        <el-option label="Media" value="Media" />
                        <el-option label="Alta" value="Alta" />
                        <el-option label="Urgente" value="Urgente" />
                    </el-select>
                </el-form-item>

                <el-form-item v-if="isEdit" label="Estado actual" prop="status" :error="form.errors.status">
                    <el-select v-model="form.status" class="w-full">
                        <el-option v-for="item in statuses" :key="item" :label="item" :value="item" />
                    </el-select>
                </el-form-item>
            </div>
        </div>

        <!-- SECCIÓN 3: ASIGNACIONES Y FECHAS -->
        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
            <div class="flex justify-between items-center mb-4 border-b pb-3 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                    <el-icon class="text-primary"><UserFilled /></el-icon> Responsables y programación
                </h3>
                <el-button type="primary" link size="small" @click="$emit('open-quick-tech')">
                    + Nuevo técnico rápido
                </el-button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Asesor / Vendedor -->
                <el-form-item prop="seller_id" :error="form.errors.seller_id">
                    <template #label>
                        Asesor / Vendedor
                    </template>
                    <el-select 
                        v-model="form.seller_id" 
                        placeholder="Seleccionar asesor..." 
                        class="w-full" 
                        filterable
                        clearable
                    >
                        <el-option v-for="seller in sellerUsers" :key="seller.id" :label="seller.name" :value="seller.id" />
                    </el-select>
                </el-form-item>

                <!-- Múltiples Técnicos Ejecutores -->
                <el-form-item prop="technicians" :error="form.errors.technicians" :class="isEdit ? 'md:col-span-2' : ''">
                    <template #label>
                        Técnicos asignados (ejecutores)
                    </template>
                    <el-select 
                        v-model="form.technicians" 
                        multiple 
                        placeholder="Seleccionar técnicos que ejecutarán el trabajo..." 
                        class="w-full" 
                        filterable
                        collapse-tags
                        collapse-tags-tooltip
                    >
                        <el-option v-for="tech in technicianUsers" :key="tech.id" :label="tech.name" :value="tech.id" />
                    </el-select>
                </el-form-item>

                <!-- Plantilla de Tareas (Solo Creación) -->
                <el-form-item v-if="!isEdit" prop="task_template_id" :error="form.errors.task_template_id">
                    <template #label>
                        <div class="flex justify-between items-center w-full">
                            <span>Plantilla de tareas iniciales (Opcional)</span>
                            <el-button type="primary" link size="small" @click.stop="showTaskTemplateModal = true">
                                + Nueva plantilla
                            </el-button>
                        </div>
                    </template>
                    <el-select v-model="form.task_template_id" clearable placeholder="Seleccionar plantilla..." class="w-full">
                        <el-option v-for="tpl in templates" :key="tpl.id" :label="tpl.name" :value="tpl.id" />
                    </el-select>
                </el-form-item>
            </div>

            <!-- Alerta Informativa (Se muestra si se selecciona una plantilla en Creación) -->
            <el-alert 
                v-if="!isEdit && form.task_template_id" 
                title="Generación automática de tareas activada" 
                type="success" 
                description="Las tareas definidas en esta plantilla se registrarán en automático para todos los técnicos asignados una vez que guardes el ticket."
                show-icon 
                :closable="false"
                class="mb-6 mt-2 !bg-green-50 dark:!bg-green-900/20 !text-green-700 dark:!text-green-400 border border-green-100 dark:border-green-800"
            />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                <!-- Fechas -->
                <el-form-item label="Inicio programado" prop="scheduled_start" :error="form.errors.scheduled_start">
                    <el-date-picker 
                        v-model="form.scheduled_start" 
                        type="date" 
                        class="!w-full" 
                        placeholder="Seleccionar fecha"
                        format="DD/MM/YYYY"
                        value-format="YYYY-MM-DD"
                    />
                </el-form-item>

                <el-form-item label="Fin estimado" prop="scheduled_end" :error="form.errors.scheduled_end">
                    <el-date-picker 
                        v-model="form.scheduled_end" 
                        type="date" 
                        class="!w-full" 
                        placeholder="Seleccionar fecha"
                        format="DD/MM/YYYY"
                        value-format="YYYY-MM-DD"
                    />
                </el-form-item>
            </div>

            <el-form-item label="Instrucciones operativas específicas" prop="instructions" :error="form.errors.instructions" class="mt-4">
                <el-input 
                    v-model="form.instructions" 
                    type="textarea" 
                    :rows="4" 
                    placeholder="Detalles operativos, restricciones de acceso al sitio, herramientas necesarias..." 
                />
            </el-form-item>
        </div>

        <!-- SECCIÓN 4: ARCHIVOS ADJUNTOS (Recursos para el técnico) -->
        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
            <div class="flex justify-between items-center mb-4 border-b pb-3 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                    <el-icon class="text-primary"><FolderOpened /></el-icon> Archivos de apoyo (Recursos para técnico)
                </h3>
                <span class="text-xs text-gray-400">Opcional</span>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Sube planos, manuales, diagramas o cualquier documento que el técnico necesite para ejecutar el trabajo.
                Estos archivos serán visibles en la orden de trabajo pública del técnico.
            </p>
            <el-upload
                ref="supportUploadRef"
                :auto-upload="false"
                :show-file-list="false"
                :on-change="(file) => { form.uploaded_files.push(file.raw); }"
                :on-remove="(file) => { form.uploaded_files = form.uploaded_files.filter(f => f.name !== file.name || f.size !== file.size); }"
                multiple
                class="w-full"
            >
                <el-button type="primary" plain icon="Upload">Seleccionar archivos</el-button>
                <template #tip>
                    <div class="el-upload__tip">Archivos PDF, imágenes, documentos (Máx. 10MB c/u)</div>
                </template>
            </el-upload>
            <div v-if="form.uploaded_files.length > 0" class="mt-3 flex flex-wrap gap-2">
                <el-tag
                    v-for="(f, i) in form.uploaded_files"
                    :key="i"
                    closable
                    type="info"
                    effect="plain"
                    @close="form.uploaded_files.splice(i, 1)"
                >
                    {{ f.name }}
                </el-tag>
            </div>
        </div>

        <!-- Componente Modal de Plantillas de Tareas -->
        <TaskTemplateModal 
            v-model="showTaskTemplateModal"
            @saved="handleTemplateSaved"
        />
    </div>
</template>