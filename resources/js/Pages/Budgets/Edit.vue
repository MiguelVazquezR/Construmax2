<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';

const props = defineProps({
    budget: Object,   // El presupuesto a editar (con conceptos cargados)
    customers: Array, // Lista de clientes con contactos
    users: Array,     // Usuarios activos
});

const formRef = ref();

// Listas estáticas
const serviceTypes = [
    'Iluminación', 'Herrería', 'Acabados', 'Eléctrico', 'Aire acondicionado', 
    'Sanitario', 'Anuncios', 'Pintura', 'Carpintería', 'Vidrio', 
    'Aluminio', 'Protección civil STPS', 'Monta cargas', 'Control de plagas', 
    'Impermeabilización', 'Servicios varios'
];

const statuses = [
    'Borrador',
    'Presupuesto enviado', 'Facturado', 'Trabajo en proceso', 
    'Trabajo terminado', 'Pagado', 'Perdido'
];

const priorities = ['Baja', 'Media', 'Alta', 'Urgente'];

// Inicializar formulario con datos existentes
const form = useForm({
    name: props.budget.name,
    service_type: props.budget.service_type,
    status: props.budget.status,
    priority: props.budget.priority,
    duration: props.budget.duration,
    description: props.budget.description,
    
    // Relaciones
    user_id: props.budget.user_id,
    customer_id: props.budget.customer_id,
    customer_contact_id: props.budget.customer_contact_id,
    branch: props.budget.branch,

    // Costos (Mapeamos para asegurar que sean editables)
    concepts: props.budget.concepts.length > 0 
        ? props.budget.concepts.map(c => ({ concept: c.concept, amount: parseFloat(c.amount) }))
        : [{ concept: '', amount: 0 }]
});

// --- LÓGICA DINÁMICA (CASCADA) ---

// 1. Filtrar Contactos según Cliente seleccionado
const filteredContacts = computed(() => {
    if (!form.customer_id) return [];
    const customer = props.customers.find(c => c.id === form.customer_id);
    return customer ? customer.contacts : [];
});

// 2. Obtener Sucursales según Contacto seleccionado
const contactBranches = computed(() => {
    if (!form.customer_contact_id) return [];
    
    // Buscamos el contacto en la lista filtrada
    const contact = filteredContacts.value.find(c => c.id === form.customer_contact_id);
    
    // Si estamos editando y el contacto actual ya no existe en la lista (caso raro), no mostramos nada
    // Pero si existe, mostramos sus sucursales
    if (!contact || !contact.branches) return [];

    // Separamos por comas y limpiamos
    return contact.branches.split(',').map(b => b.trim()).filter(b => b !== '');
});

// Manejadores de cambio (Solo resetean si el usuario cambia el valor manualmente)
const handleCustomerChange = () => {
    form.customer_contact_id = '';
    form.branch = '';
};

const handleContactChange = () => {
    form.branch = '';
    if (contactBranches.value.length === 1) {
        form.branch = contactBranches.value[0];
    }
};

// --- LÓGICA DE COSTOS ---

const totalCost = computed(() => {
    return form.concepts.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);
};

const addConcept = () => {
    form.concepts.push({ concept: '', amount: 0 });
};

const removeConcept = (index) => {
    if (form.concepts.length > 1) {
        form.concepts.splice(index, 1);
    } else {
        ElMessage.warning('Debe haber al menos un concepto de costo.');
    }
};

// --- VALIDACIÓN Y ENVÍO ---

const rules = reactive({
    name: [{ required: true, message: 'Requerido', trigger: 'blur' }],
    service_type: [{ required: true, message: 'Requerido', trigger: 'change' }],
    status: [{ required: true, message: 'Requerido', trigger: 'change' }],
    user_id: [{ required: true, message: 'Requerido', trigger: 'change' }],
    customer_id: [{ required: true, message: 'Requerido', trigger: 'change' }],
    customer_contact_id: [{ required: true, message: 'Requerido', trigger: 'change' }],
    branch: [{ required: true, message: 'Requerido', trigger: 'change' }],
    priority: [{ required: true, message: 'Requerido', trigger: 'change' }],
});

const submit = () => {
    if (!formRef.value) return;
    
    formRef.value.validate((valid) => {
        if (valid) {
            // Validar montos
            const invalidCost = form.concepts.some(c => c.concept.trim() === '' || c.amount < 0);
            if(invalidCost) {
                ElMessage.error('Revisa los conceptos de costos. Todos deben tener nombre y monto válido.');
                return;
            }

            form.put(route('budgets.update', props.budget.id), {
                onSuccess: () => ElMessage.success('Presupuesto actualizado correctamente')
            });
        } else {
            ElMessage.error('Completa los campos obligatorios.');
        }
    });
};
</script>

<template>
    <AppLayout :title="`Editar presupuesto: ${budget.name}`">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                    Editar presupuesto: {{ budget.name }}
                </h2>
                <Link :href="route('budgets.index')">
                    <el-button icon="Back" circle />
                </Link>
            </div>
        </template>

        <div class="max-w-6xl mx-auto py-6 sm:px-6 lg:px-8">
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
                    
                    <!-- COLUMNA IZQUIERDA: DATOS GENERALES (2/3 ancho) -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- Tarjeta 1: Información del Proyecto -->
                        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                                <el-icon class="text-primary"><Document /></el-icon> Datos del proyecto
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <el-form-item label="Nombre del proyecto" prop="name" class="md:col-span-2">
                                    <el-input v-model="form.name" placeholder="Ej. Mantenimiento de luminarias NAVE B" />
                                </el-form-item>

                                <el-form-item label="Tipo de servicio" prop="service_type">
                                    <el-select v-model="form.service_type" placeholder="Seleccionar" class="w-full" filterable>
                                        <el-option v-for="item in serviceTypes" :key="item" :label="item" :value="item" />
                                    </el-select>
                                </el-form-item>

                                <el-form-item label="Duración estimada" prop="duration">
                                    <el-input v-model="form.duration" placeholder="Ej. 2 semanas" />
                                </el-form-item>

                                <el-form-item label="Prioridad" prop="priority">
                                    <el-select v-model="form.priority" class="w-full">
                                        <el-option v-for="item in priorities" :key="item" :label="item" :value="item" />
                                    </el-select>
                                </el-form-item>

                                <el-form-item label="Estado actual" prop="status">
                                    <el-select v-model="form.status" class="w-full">
                                        <el-option v-for="item in statuses" :key="item" :label="item" :value="item" />
                                    </el-select>
                                </el-form-item>

                                <el-form-item label="Descripción detallada" class="md:col-span-2">
                                    <el-input 
                                        v-model="form.description" 
                                        type="textarea" 
                                        :rows="3" 
                                        placeholder="Detalles adicionales..." 
                                    />
                                </el-form-item>
                            </div>
                        </div>

                        <!-- Tarjeta 2: Costos y Presupuesto -->
                        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                    <el-icon class="text-primary"><Money /></el-icon> Desglose de costos
                                </h3>
                                <el-button size="small" type="primary" plain icon="Plus" @click="addConcept">
                                    Agregar concepto
                                </el-button>
                            </div>

                            <div class="bg-gray-50 dark:bg-[#252529] rounded-lg p-4 border border-gray-200 dark:border-[#3f3f46]">
                                <div v-for="(item, index) in form.concepts" :key="index" class="flex flex-col sm:flex-row gap-3 mb-3 last:mb-0 items-start sm:items-center">
                                    <div class="flex-1 w-full">
                                        <el-input v-model="item.concept" placeholder="Concepto" />
                                    </div>
                                    <div class="w-full sm:w-40">
                                        <el-input-number 
                                            v-model="item.amount" 
                                            :min="0" 
                                            :precision="2" 
                                            :step="0.1"
                                            class="!w-full" 
                                            placeholder="Monto"
                                            controls-position="right"
                                        />
                                    </div>
                                    <el-button 
                                        type="danger" 
                                        icon="Delete" 
                                        circle 
                                        plain 
                                        @click="removeConcept(index)"
                                        :disabled="form.concepts.length === 1"
                                    />
                                </div>
                            </div>

                            <div class="flex justify-end mt-4 items-center gap-4">
                                <span class="text-gray-500 text-sm font-medium uppercase">Total Presupuesto:</span>
                                <span class="text-2xl font-bold text-gray-800 dark:text-white">
                                    {{ formatCurrency(totalCost) }}
                                </span>
                            </div>
                        </div>

                    </div>

                    <!-- COLUMNA DERECHA: RELACIONES (1/3 ancho) -->
                    <div class="lg:col-span-1 space-y-6">
                        
                        <!-- Tarjeta 3: Cliente y Ubicación -->
                        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6 sticky top-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                                <el-icon class="text-primary"><OfficeBuilding /></el-icon> Cliente y sitio
                            </h3>

                            <div class="space-y-4">
                                <el-form-item label="Cliente" prop="customer_id">
                                    <el-select 
                                        v-model="form.customer_id" 
                                        placeholder="Seleccionar cliente" 
                                        class="w-full" 
                                        filterable
                                        @change="handleCustomerChange"
                                    >
                                        <el-option 
                                            v-for="customer in customers" 
                                            :key="customer.id" 
                                            :label="customer.name" 
                                            :value="customer.id" 
                                        />
                                    </el-select>
                                </el-form-item>

                                <el-form-item label="Contacto" prop="customer_contact_id">
                                    <el-select 
                                        v-model="form.customer_contact_id" 
                                        placeholder="Seleccionar contacto" 
                                        class="w-full"
                                        :disabled="!form.customer_id"
                                        @change="handleContactChange"
                                    >
                                        <el-option 
                                            v-for="contact in filteredContacts" 
                                            :key="contact.id" 
                                            :label="contact.name" 
                                            :value="contact.id" 
                                        />
                                    </el-select>
                                </el-form-item>

                                <el-form-item label="Sucursal / Sitio" prop="branch">
                                    <el-select 
                                        v-model="form.branch" 
                                        placeholder="Seleccionar sucursal" 
                                        class="w-full"
                                        :disabled="!form.customer_contact_id"
                                        filterable
                                        allow-create
                                        default-first-option
                                    >
                                        <el-option 
                                            v-for="branch in contactBranches" 
                                            :key="branch" 
                                            :label="branch" 
                                            :value="branch" 
                                        />
                                    </el-select>
                                </el-form-item>

                                <el-divider class="!my-4" />

                                <el-form-item label="Responsable interno" prop="user_id">
                                    <el-select v-model="form.user_id" placeholder="Asignar a..." class="w-full" filterable>
                                        <el-option 
                                            v-for="user in users" 
                                            :key="user.id" 
                                            :label="user.name" 
                                            :value="user.id" 
                                        />
                                    </el-select>
                                </el-form-item>
                            </div>

                            <div class="mt-8 pt-4 border-t border-gray-100 dark:border-gray-700">
                                <el-button 
                                    type="primary" 
                                    native-type="submit" 
                                    class="w-full !font-bold !h-12 !text-lg" 
                                    color="#f26c17"
                                    :loading="form.processing"
                                >
                                    Actualizar presupuesto
                                </el-button>
                                <div class="text-center mt-3">
                                    <Link :href="route('budgets.index')" class="text-sm text-gray-500 hover:text-primary">
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