<script setup>
import { ref, reactive, computed } from 'vue';
import { useForm, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';
import { Tools, Back } from '@element-plus/icons-vue';
import axios from 'axios';
import TicketForm from './Partials/TicketForm.vue';

const props = defineProps({
    budgets: Array, // Presupuestos existentes
    users: Array,   // Usuarios (Supervisores/Encargados)
    customers: Array // Clientes (para el modal rápido)
});

const formRef = ref();
const quickBudgetFormRef = ref();

// --- FORMULARIO PRINCIPAL (TICKET) ---
const form = useForm({
    budget_id: '',
    user_id: '', // Supervisor/Encargado
    priority: 'Media',
    scheduled_start: '',
    scheduled_end: '',
    instructions: '',
});

const rules = reactive({
    budget_id: [{ required: true, message: 'Selecciona un presupuesto', trigger: 'change' }],
    user_id: [{ required: true, message: 'Asigna un encargado de obra', trigger: 'change' }],
    priority: [{ required: true, message: 'Requerido', trigger: 'change' }],
});

const submit = () => {
    if (!formRef.value) return;
    
    formRef.value.validate((valid) => {
        if (valid) {
            form.post(route('tickets.store'), {
                onSuccess: () => ElMessage.success('Ticket creado correctamente')
            });
        } else {
            ElMessage.error('Completa los campos obligatorios');
        }
    });
};

// --- LÓGICA DE PRESUPUESTO RÁPIDO ---
const showQuickBudgetModal = ref(false);
const isCreatingBudget = ref(false);
const localBudgets = ref([...props.budgets]);

const quickForm = reactive({
    name: '',
    service_type: '',
    status: 'Trabajo en proceso',
    priority: 'Media',
    
    // CORRECCIÓN: Campos obligatorios agregados
    user_id: usePage().props.auth.user.id, // Asignamos por defecto al creador
    currency: 'MXN', // Moneda por defecto
    exchange_rate: 1, // Tipo de cambio por defecto
    
    customer_id: '',
    customer_contact_id: '',
    branch: '',
    duration: '',
    description: 'Generado desde Ticket Rápido',
    concepts: [{ concept: 'Costo inicial estimado', amount: 0 }],
    quick_create: true
});

const quickRules = reactive({
    name: [{ required: true, message: 'Requerido', trigger: 'blur' }],
    service_type: [{ required: true, message: 'Requerido', trigger: 'change' }],
    customer_id: [{ required: true, message: 'Requerido', trigger: 'change' }],
    customer_contact_id: [{ required: true, message: 'Requerido', trigger: 'change' }],
    branch: [{ required: true, message: 'Requerido', trigger: 'change' }],
});

const serviceTypes = [
    'Iluminación', 'Herrería', 'Acabados', 'Eléctrico', 'Aire acondicionado', 
    'Sanitario', 'Anuncios', 'Pintura', 'Carpintería', 'Vidrio', 
    'Aluminio', 'Protección civil STPS', 'Monta cargas', 'Control de plagas', 
    'Impermeabilización', 'Servicios varios'
];

const quickFilteredContacts = computed(() => {
    if (!quickForm.customer_id) return [];
    const customer = props.customers.find(c => c.id === quickForm.customer_id);
    return customer ? customer.contacts : [];
});

const quickContactBranches = computed(() => {
    if (!quickForm.customer_contact_id) return [];
    const contact = quickFilteredContacts.value.find(c => c.id === quickForm.customer_contact_id);
    if (!contact || !contact.branches) return [];
    return contact.branches.split(',').map(b => b.trim()).filter(b => b !== '');
});

const handleQuickCustomerChange = () => {
    quickForm.customer_contact_id = '';
    quickForm.branch = '';
};

const openQuickBudget = () => {
    quickForm.name = '';
    quickForm.service_type = '';
    quickForm.customer_id = '';
    quickForm.customer_contact_id = '';
    quickForm.branch = '';
    showQuickBudgetModal.value = true;
};

const submitQuickBudget = () => {
    if (!quickBudgetFormRef.value) return;

    quickBudgetFormRef.value.validate(async (valid) => {
        if (valid) {
            isCreatingBudget.value = true;
            try {
                const response = await axios.post(route('budgets.store'), quickForm);
                const newBudget = response.data.budget;
                localBudgets.value.unshift(newBudget);
                form.budget_id = newBudget.id;
                
                ElMessage.success('Presupuesto creado y seleccionado.');
                showQuickBudgetModal.value = false;
            } catch (error) {
                console.error(error);
                ElMessage.error('Error al crear presupuesto. Verifica los datos.');
            } finally {
                isCreatingBudget.value = false;
            }
        }
    });
};
</script>

<template>
    <AppLayout title="Nuevo ticket de servicio">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">
                    Nuevo ticket de servicio
                </h2>
                <Link :href="route('tickets.index')">
                    <el-button :icon="Back" circle />
                </Link>
            </div>
        </template>

        <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
            
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-[#2b2b2e] bg-gray-50/50 dark:bg-[#252529]">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <el-icon class="text-primary"><Tools /></el-icon> 
                        Información operativa
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Un ticket debe estar vinculado a un presupuesto aprobado o activo.
                    </p>
                </div>

                <div class="p-6">
                    <el-form 
                        ref="formRef"
                        :model="form" 
                        :rules="rules" 
                        label-position="top"
                        require-asterisk-position="right"
                        size="large"
                        @submit.prevent="submit"
                    >
                        <!-- SELECCIÓN DE PRESUPUESTO -->
                        <div class="mb-6">
                            <div class="flex justify-between items-center mb-1">
                                <label class="text-sm font-bold text-gray-700 dark:text-gray-300">Presupuesto / Servicio Base</label>
                                <el-button type="primary" link size="small" @click="openQuickBudget">
                                    ¿No existe? Regístralo rápido
                                </el-button>
                            </div>
                            <el-form-item prop="budget_id">
                                <el-select 
                                    v-model="form.budget_id" 
                                    placeholder="Buscar por nombre, cliente o folio..." 
                                    class="w-full"
                                    filterable
                                    no-data-text="No hay presupuestos disponibles"
                                >
                                    <el-option 
                                        v-for="budget in localBudgets" 
                                        :key="budget.id" 
                                        :label="`${budget.name} - ${budget.customer?.name} (${budget.status})`" 
                                        :value="budget.id" 
                                    />
                                </el-select>
                            </el-form-item>
                        </div>

                        <!-- CAMPOS REUTILIZABLES -->
                        <TicketForm 
                            :form="form" 
                            :users="users" 
                        />

                        <div class="flex justify-end pt-6 border-t border-gray-100 dark:border-gray-700 mt-4">
                            <Link :href="route('tickets.index')" class="mr-4">
                                <el-button size="large">Cancelar</el-button>
                            </Link>
                            <el-button 
                                type="primary" 
                                native-type="submit" 
                                size="large" 
                                color="#f26c17" 
                                :loading="form.processing"
                                class="!font-bold"
                            >
                                Generar Ticket
                            </el-button>
                        </div>
                    </el-form>
                </div>
            </div>
        </div>

        <el-dialog
            v-model="showQuickBudgetModal"
            title="Registro rápido de servicio"
            width="600px"
            destroy-on-close
        >
            <el-form 
                ref="quickBudgetFormRef"
                :model="quickForm" 
                :rules="quickRules" 
                label-position="top"
            >
                <el-form-item label="Nombre del Proyecto" prop="name">
                    <el-input v-model="quickForm.name" placeholder="Ej. Reparación urgente aire acondicionado" />
                </el-form-item>

                <el-form-item label="Tipo de Servicio" prop="service_type">
                    <el-select v-model="quickForm.service_type" class="w-full" placeholder="Seleccionar" filterable>
                        <el-option v-for="item in serviceTypes" :key="item" :label="item" :value="item" />
                    </el-select>
                </el-form-item>

                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Cliente" prop="customer_id">
                        <el-select 
                            v-model="quickForm.customer_id" 
                            class="w-full" 
                            filterable 
                            placeholder="Buscar cliente"
                            @change="handleQuickCustomerChange"
                        >
                            <el-option 
                                v-for="c in customers" 
                                :key="c.id" 
                                :label="c.name" 
                                :value="c.id" 
                            />
                        </el-select>
                    </el-form-item>

                    <el-form-item label="Contacto" prop="customer_contact_id">
                        <el-select 
                            v-model="quickForm.customer_contact_id" 
                            class="w-full" 
                            placeholder="Seleccionar contacto"
                            :disabled="!quickForm.customer_id"
                        >
                            <el-option 
                                v-for="contact in quickFilteredContacts" 
                                :key="contact.id" 
                                :label="contact.name" 
                                :value="contact.id" 
                            />
                        </el-select>
                    </el-form-item>
                </div>

                <el-form-item label="Sucursal / Sitio" prop="branch">
                    <el-select 
                        v-model="quickForm.branch" 
                        class="w-full" 
                        placeholder="Seleccionar o escribir"
                        :disabled="!quickForm.customer_contact_id"
                        allow-create
                        filterable
                        default-first-option
                    >
                        <el-option 
                            v-for="branch in quickContactBranches" 
                            :key="branch" 
                            :label="branch" 
                            :value="branch" 
                        />
                    </el-select>
                </el-form-item>
            </el-form>

            <template #footer>
                <div class="dialog-footer">
                    <el-button @click="showQuickBudgetModal = false">Cancelar</el-button>
                    <el-button 
                        type="primary" 
                        color="#f26c17" 
                        @click="submitQuickBudget" 
                        :loading="isCreatingBudget"
                    >
                        Crear y seleccionar
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </AppLayout>
</template>