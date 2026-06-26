<script setup>
import { ref, reactive } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';
import { Back } from '@element-plus/icons-vue';
import TicketForm from './Partials/TicketForm.vue';
import QuickTechnicianModal from './Partials/QuickTechnicianModal.vue';

const props = defineProps({
    ticket: Object,
    users: Array,
    customers: Array,
    templates: Array,
    serviceTypes: Array,
});

const formRef = ref();

const localUsers = ref([...props.users]);
const showQuickTechModal = ref(false);

const form = useForm({
    customer_id: props.ticket.customer_id,
    customer_contact_id: props.ticket.customer_contact_id,
    customer_branch_id: props.ticket.customer_branch_id || '', // Actualizado para usar ID
    seller_id: props.ticket.seller_id || '',
    name: props.ticket.name,
    service_type: props.ticket.service_type,
    duration: props.ticket.duration || '',
    user_id: props.ticket.user_id,
    technicians: (props.ticket.technicians || []).map(Number),
    assistant_technicians: (props.ticket.assistant_technicians || []).map(Number),
    priority: props.ticket.priority,
    status: props.ticket.status,
    scheduled_start: props.ticket.scheduled_start,
    scheduled_end: props.ticket.scheduled_end,
    instructions: props.ticket.instructions,
    uploaded_files: [],
});

const rules = reactive({
    customer_id: [{ required: true, message: 'Selecciona un cliente', trigger: 'change' }],
    customer_contact_id: [{ required: true, message: 'Selecciona un contacto', trigger: 'change' }],
    name: [{ required: true, message: 'El nombre del proyecto es obligatorio', trigger: 'blur' }],
    service_type: [{ required: true, message: 'Selecciona el tipo de servicio', trigger: 'change' }],
    user_id: [{ required: true, message: 'Requerido', trigger: 'change' }],
    priority: [{ required: true, message: 'Requerido', trigger: 'change' }],
    status: [{ required: true, message: 'Requerido', trigger: 'change' }],
});

const handleTechCreated = (newUser) => {
    localUsers.value.push(newUser);
    form.user_id = newUser.id;
    const id = Number(newUser.id);
    const level = newUser.technician?.level || 'Encargado';
    if (level === 'Auxiliar/Ayudante') {
        if (!form.assistant_technicians.includes(id)) {
            form.assistant_technicians.push(id);
        }
    } else {
        if (!form.technicians.includes(id)) {
            form.technicians.push(id);
        }
    }
};

const submit = () => {
    if (!formRef.value) return;
    
    formRef.value.validate((valid) => {
        if (valid) {
            const hasFiles = form.uploaded_files && form.uploaded_files.length > 0;

            form.transform((data) => ({
                customer_id: data.customer_id,
                customer_contact_id: data.customer_contact_id,
                customer_branch_id: data.customer_branch_id,
                seller_id: data.seller_id,
                name: data.name,
                service_type: data.service_type,
                duration: data.duration,
                user_id: data.user_id,
                technicians: data.technicians,
                assistant_technicians: data.assistant_technicians,
                priority: data.priority,
                status: data.status,
                scheduled_start: data.scheduled_start,
                scheduled_end: data.scheduled_end,
                instructions: data.instructions,
            }));

            form.put(route('tickets.update', props.ticket.id), {
                onSuccess: () => {
                    if (hasFiles) {
                        uploadTicketFiles(props.ticket.id);
                    } else {
                        ElMessage.success('Ticket actualizado correctamente');
                    }
                },
            });
        } else {
            ElMessage.error('Por favor revisa los campos marcados');
        }
    });
};

const uploadTicketFiles = (ticketId) => {
    const uploadForm = new FormData();
    form.uploaded_files.forEach((file) => {
        uploadForm.append('files[]', file);
    });

    router.post(route('tickets.evidence.store', ticketId), uploadForm, {
        forceFormData: true,
        preserveState: false,
        onSuccess: () => {
            ElMessage.success('Ticket actualizado correctamente');
        },
    });
};
</script>

<template>
    <AppLayout :title="`Editar ticket #${ticket.id}`">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                    Editar ticket de servicio #{{ ticket.id }}
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
                    :service-types="serviceTypes"
                    :is-edit="true" 
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
                        Actualizar ticket
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