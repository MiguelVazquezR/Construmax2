<script setup>
import { computed, ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { OfficeBuilding, Document, UserFilled, Setting } from '@element-plus/icons-vue';
import TaskTemplateModal from './TaskTemplateModal.vue';
import QuickBranchModal from './QuickBranchModal.vue';
import axios from 'axios';

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
    },
    serviceTypes: {
        type: Array,
        default: () => []
    }
});

defineEmits(['open-quick-tech']);

const showTaskTemplateModal = ref(false);
const showQuickBranchModal = ref(false);

// --- SERVICE TYPE MANAGEMENT ---
const showServiceTypeModal = ref(false);
const editingServiceType = ref(null);
const savingServiceType = ref(false);
const serviceTypeForm = reactive({
    name: '',
});
const serviceTypeFormRef = ref(null);

const serviceTypeRules = reactive({
    name: [{ required: true, message: 'El nombre es requerido', trigger: 'blur' }],
});

const localServiceTypes = ref([...props.serviceTypes]);

const activeServiceTypes = computed(() => {
    return [...localServiceTypes.value].sort((a, b) => a.name.localeCompare(b.name));
});

const openServiceTypeModal = (st = null) => {
    editingServiceType.value = st;
    serviceTypeForm.name = st ? st.name : '';
    if (serviceTypeFormRef.value) serviceTypeFormRef.value.clearValidate();
    showServiceTypeModal.value = true;
};

const saveServiceType = async () => {
    if (!serviceTypeFormRef.value) return;
    try {
        await serviceTypeFormRef.value.validate();
    } catch {
        return;
    }

    savingServiceType.value = true;

    try {
        if (editingServiceType.value) {
            const { data } = await axios.put(route('service-types.update', editingServiceType.value.id), { name: serviceTypeForm.name });
            const updated = data.serviceType;
            const idx = localServiceTypes.value.findIndex(st => st.id === updated.id);
            if (idx !== -1) localServiceTypes.value[idx] = updated;
            ElMessage.success('Tipo de servicio actualizado.');
        } else {
            const { data } = await axios.post(route('service-types.store'), { name: serviceTypeForm.name });
            localServiceTypes.value = [...localServiceTypes.value, { ...data.serviceType, is_active: true }];
            ElMessage.success('Tipo de servicio creado.');
        }
        showServiceTypeModal.value = false;
    } catch (err) {
        ElMessage.error(err.response?.data?.message || 'Error al guardar el tipo de servicio.');
    } finally {
        savingServiceType.value = false;
    }
};

const deleteServiceType = (st) => {
    ElMessageBox.confirm(
        `¿Estás seguro de eliminar "${st.name}"?`,
        'Eliminar tipo de servicio',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(async () => {
        try {
            await axios.delete(route('service-types.destroy', st.id));
            localServiceTypes.value = localServiceTypes.value.filter(item => item.id !== st.id);
            ElMessage.success('Tipo de servicio eliminado.');
        } catch (err) {
            ElMessage.error(err.response?.data?.message || 'Error al eliminar el tipo de servicio.');
        }
    }).catch(() => {});
};

const handleTemplateSaved = () => {
    router.reload({ only: ['templates'] });
};

const statuses = [
    'Borrador',
    'Programado',
    'Levantamiento', 
    'Catálogo', 
    'Proceso de ejecución', 
    'Ejecutado',
    'Finalizado',
    'Facturado', 
    'Pagado'
];

const filteredContacts = computed(() => {
    if (!props.form.customer_id) return [];
    const customer = props.customers.find(c => c.id === props.form.customer_id);
    return customer ? customer.contacts : [];
});

// Region/state filter for branches
const branchRegionFilter = ref('');

// Unique regions available for the selected customer
const availableRegions = computed(() => {
    if (!props.form.customer_id) return [];
    const customer = props.customers.find(c => c.id === props.form.customer_id);
    if (!customer || !customer.branches) return [];
    const regions = [...new Set(customer.branches.map(b => b.region).filter(Boolean))];
    return regions.sort();
});

// Ahora leemos las sucursales directamente del cliente, con filtro por región
const customerBranches = computed(() => {
    if (!props.form.customer_id) return [];
    const customer = props.customers.find(c => c.id === props.form.customer_id);
    
    if (!customer || !customer.branches) return [];
    
    let branches = customer.branches;
    
    // Apply region filter
    if (branchRegionFilter.value) {
        branches = branches.filter(b => b.region === branchRegionFilter.value);
    }
    
    return branches.map(b => ({
        label: b.branch_name ? `${b.branch_name} (${b.unit}) - ${b.region}` : `${b.unit} - ${b.region}`,
        value: b.id
    }));
});

const handleCustomerChange = () => {
    props.form.customer_contact_id = '';
    props.form.customer_branch_id = '';
    branchRegionFilter.value = '';
    
    // Auto-seleccionar sucursal si el cliente solo tiene una
    if (customerBranches.value.length === 1) {
        props.form.customer_branch_id = customerBranches.value[0].value;
    }
};

const handleBranchCreated = (branch) => {
    // Reload customers to get updated branches list
    router.reload({ only: ['customers'] });
    // Select the newly created branch
    props.form.customer_branch_id = branch.id;
};

const handleContactChange = () => {
    // Ya no blanqueamos la sucursal obligatoriamente al cambiar de contacto,
    // porque las sucursales son del cliente.
};

const technicianUsers = computed(() => {
    // Only users with technician profile AND level 'Encargado'
    const techUsers = props.users.filter(u => u.technician && u.technician.level === 'Encargado');
    // Also include any user referenced in form.technicians that might not have a profile
    const referencedIds = new Set((props.form.technicians || []).map(Number));
    techUsers.forEach(u => referencedIds.delete(Number(u.id)));
    if (referencedIds.size > 0) {
        const missing = props.users.filter(u => referencedIds.has(Number(u.id)));
        techUsers.push(...missing);
    }
    return techUsers;
});

const assistantTechnicians = computed(() => {
    // Only users with technician profile AND level 'Auxiliar/Ayudante'
    const assistants = props.users.filter(u => u.technician && u.technician.level === 'Auxiliar/Ayudante');
    const referencedIds = new Set((props.form.assistant_technicians || []).map(Number));
    assistants.forEach(u => referencedIds.delete(Number(u.id)));
    if (referencedIds.size > 0) {
        const missing = props.users.filter(u => referencedIds.has(Number(u.id)));
        assistants.push(...missing);
    }
    return assistants;
});

const sellerUsers = computed(() => {
    return props.users.filter(u => u.employee);
});

const getTechLabel = (user) => {
    let label = user.name;
    if (user.technician) {
        label += user.technician.is_internal ? ' (Interno)' : ' (Externo)';
        if (user.technician.state) {
            label += ` — ${user.technician.state}`;
        }
    }
    return label;
};
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
                    <div class="flex items-start gap-2 w-full">
                        <div class="flex-1 space-y-2">
                            <el-select
                                v-model="branchRegionFilter"
                                placeholder="Filtrar por estado..."
                                class="w-full"
                                clearable
                                :disabled="!form.customer_id"
                                @change="form.customer_branch_id = ''"
                            >
                                <el-option
                                    v-for="region in availableRegions"
                                    :key="region"
                                    :label="region"
                                    :value="region"
                                />
                            </el-select>
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
                        </div>
                        <el-button
                            v-if="form.customer_id"
                            size="default"
                            type="primary"
                            plain
                            @click.stop="showQuickBranchModal = true"
                            :title="'Nueva sucursal'"
                        >
                            + Nueva
                        </el-button>
                    </div>
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
                    <div class="flex items-start gap-2 w-full">
                        <el-select v-model="form.service_type" placeholder="Seleccionar" class="flex-1" filterable>
                            <el-option v-for="st in activeServiceTypes" :key="st.id" :label="`${st.id} - ${st.name}`" :value="st.name">
                                <span style="float: left">{{ st.id }}</span>
                                <span style="float: right; color: var(--el-text-color-secondary); font-size: 13px">{{ st.name }}</span>
                            </el-option>
                        </el-select>
                        <el-button
                            type="primary"
                            plain
                            :icon="Setting"
                            @click.stop="openServiceTypeModal()"
                            title="Gestionar tipos de servicio"
                        />
                    </div>
                </el-form-item>

                <el-form-item label="Duración estimada" prop="duration" :error="form.errors.duration">
                    <el-input v-model="form.duration" placeholder="Ej. 2 semanas" />
                </el-form-item>

                <el-form-item label="No. reporte / ticket" prop="report_number" :error="form.errors.report_number">
                    <el-input v-model="form.report_number" placeholder="Número de reporte del cliente" />
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

                <!-- Múltiples Técnicos Encargados (solo nivel Encargado) -->
                <el-form-item prop="technicians" :error="form.errors.technicians">
                    <template #label>
                        Técnicos asignados (encargados)
                    </template>
                    <el-select 
                        v-model="form.technicians" 
                        multiple 
                        placeholder="Seleccionar técnicos encargados..." 
                        class="w-full" 
                        filterable
                        collapse-tags
                        collapse-tags-tooltip
                    >
                        <el-option v-for="tech in technicianUsers" :key="tech.id" :label="getTechLabel(tech)" :value="tech.id" />
                    </el-select>
                </el-form-item>

                <!-- Técnicos Auxiliares / Ayudantes -->
                <el-form-item prop="assistant_technicians" :error="form.errors.assistant_technicians">
                    <template #label>
                        Técnicos auxiliares / ayudantes
                    </template>
                    <el-select 
                        v-model="form.assistant_technicians" 
                        multiple 
                        placeholder="Seleccionar técnicos auxiliares..." 
                        class="w-full" 
                        filterable
                        collapse-tags
                        collapse-tags-tooltip
                    >
                        <el-option v-for="tech in assistantTechnicians" :key="tech.id" :label="getTechLabel(tech)" :value="tech.id" />
                    </el-select>
                </el-form-item>

                <!-- Plantilla de Tareas -->
                <el-form-item prop="task_template_id" :error="form.errors.task_template_id">
                    <template #label>
                        <div class="flex justify-between items-center w-full">
                            <span>Plantilla de tareas (opcional)</span>
                            <el-button type="primary" link size="small" @click.stop="showTaskTemplateModal = true">
                                + Gestionar plantillas
                            </el-button>
                        </div>
                    </template>
                    <el-select v-model="form.task_template_id" clearable placeholder="Seleccionar plantilla..." class="w-full" filterable>
                        <el-option v-for="tpl in templates" :key="tpl.id" :label="tpl.name" :value="tpl.id" />
                    </el-select>
                </el-form-item>
            </div>

            <!-- Alerta Informativa (Se muestra si se selecciona una plantilla) -->
            <el-alert 
                v-if="form.task_template_id" 
                title="Generación automática de tareas activada" 
                type="success" 
                :description="isEdit ? 'Si el ticket no tiene tareas aún, se generarán automáticamente para todos los técnicos asignados al guardar.' : 'Las tareas definidas en esta plantilla se registrarán en automático para todos los técnicos asignados una vez que guardes el ticket.'"
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

        <!-- Componente Modal de Registro Rápido de Sucursal -->
        <QuickBranchModal
            v-model="showQuickBranchModal"
            :customers="customers"
            :selected-customer-id="form.customer_id"
            @created="handleBranchCreated"
        />

        <!-- Modal: Gestión de tipos de servicio -->
        <el-dialog
            v-model="showServiceTypeModal"
            :title="editingServiceType ? 'Editar tipo de servicio' : 'Nuevo tipo de servicio'"
            width="500px"
            destroy-on-close
        >
            <el-form
                ref="serviceTypeFormRef"
                :model="serviceTypeForm"
                :rules="serviceTypeRules"
                label-position="top"
                @submit.prevent="saveServiceType"
            >
                <el-form-item label="Nombre" prop="name">
                    <el-input v-model="serviceTypeForm.name" placeholder="Ej. Plomería" />
                </el-form-item>
            </el-form>

            <!-- Existing service types list -->
            <div class="mt-6 border-t border-gray-100 dark:border-gray-700 pt-4">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Tipos de servicio existentes</h4>
                <div class="space-y-2 max-h-60 overflow-y-auto">
                    <div
                        v-for="st in localServiceTypes"
                        :key="st.id"
                        class="flex items-center justify-between p-2 rounded-lg border border-gray-100 dark:border-gray-700"
                    >
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ st.name }}</span>
                        <div class="flex items-center gap-1">
                            <el-button
                                size="small"
                                type="primary"
                                plain
                                @click="openServiceTypeModal(st)"
                            >
                                Editar
                            </el-button>
                            <el-button
                                size="small"
                                type="danger"
                                plain
                                @click="deleteServiceType(st)"
                            >
                                Eliminar
                            </el-button>
                        </div>
                    </div>
                    <el-empty v-if="localServiceTypes.length === 0" description="Sin tipos de servicio aún" :image-size="40" />
                </div>
            </div>

            <template #footer>
                <el-button @click="showServiceTypeModal = false">Cancelar</el-button>
                <el-button type="primary" color="#f26c17" @click="saveServiceType" :loading="savingServiceType">
                    {{ editingServiceType ? 'Actualizar' : 'Crear' }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>