<script setup>
import { ref, reactive } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';
import { Back } from '@element-plus/icons-vue';
import TicketForm from './Partials/TicketForm.vue';
import QuickTechnicianModal from './Partials/QuickTechnicianModal.vue';

const props = defineProps({
    users: Array,   
    customers: Array,
    templates: Array, // Reintegrado para el soporte de plantillas
});

const formRef = ref();
const localUsers = ref([...props.users]);
const showQuickTechModal = ref(false);

const handleTechCreated = (newUser) => {
    localUsers.value.push(newUser);
    form.user_id = newUser.id;
    if (newUser.technician && !form.technicians.includes(newUser.id)) {
        form.technicians.push(newUser.id);
    }
};

const form = useForm({
    customer_id: '',
    customer_contact_id: '',
    customer_branch_id: '', // Actualizado para usar ID en vez del string 'branch'
    seller_id: '',
    name: '',
    service_type: '',
    duration: '',
    user_id: '', 
    technicians: [], 
    task_template_id: '', // Integrado
    priority: 'Media',
    scheduled_start: '',
    scheduled_end: '',
    instructions: '',
});

const rules = reactive({
    customer_id: [{ required: true, message: 'Selecciona un cliente', trigger: 'change' }],
    customer_contact_id: [{ required: true, message: 'Selecciona un contacto', trigger: 'change' }],
    name: [{ required: true, message: 'El nombre del proyecto es obligatorio', trigger: 'blur' }],
    service_type: [{ required: true, message: 'Selecciona el tipo de servicio', trigger: 'change' }],
    user_id: [{ required: true, message: 'Asigna un supervisor/encargado', trigger: 'change' }],
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
            ElMessage.error('Por favor completa los campos obligatorios');
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

        <div class="max-w-5xl mx-auto py-6 sm:px-6 lg:px-8">
            <el-form 
                ref="formRef"
                :model="form" 
                :rules="rules" 
                label-position="top"
                require-asterisk-position="right"
                size="large"
                @submit.prevent="submit"
            >
                <TicketForm 
                    :form="form" 
                    :users="localUsers" 
                    :customers="customers"
                    :templates="templates"
                    @open-quick-tech="showQuickTechModal = true"
                />

                <div class="flex justify-end pt-6 border-t border-gray-100 dark:border-gray-700 mt-6">
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
                        Generar ticket
                    </el-button>
                </div>
            </el-form>
        </div>

        <QuickTechnicianModal 
            v-model="showQuickTechModal" 
            @created="handleTechCreated" 
        />
    </AppLayout>
</template>