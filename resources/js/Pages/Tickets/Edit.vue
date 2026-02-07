<script setup>
import { ref, reactive } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';

const props = defineProps({
    ticket: Object,
    users: Array,
});

const formRef = ref();

// Listas de opciones
const priorities = ['Baja', 'Media', 'Alta', 'Urgente'];
const statuses = [
    'Programado', 
    'En proceso', 
    'En espera', 
    'Revisión', 
    'Completado', 
    'Cancelado'
];

const form = useForm({
    user_id: props.ticket.user_id,
    priority: props.ticket.priority,
    status: props.ticket.status,
    scheduled_start: props.ticket.scheduled_start,
    scheduled_end: props.ticket.scheduled_end,
    instructions: props.ticket.instructions,
});

const rules = reactive({
    user_id: [{ required: true, message: 'Requerido', trigger: 'change' }],
    priority: [{ required: true, message: 'Requerido', trigger: 'change' }],
    status: [{ required: true, message: 'Requerido', trigger: 'change' }],
});

const submit = () => {
    if (!formRef.value) return;
    
    formRef.value.validate((valid) => {
        if (valid) {
            form.put(route('tickets.update', props.ticket.id), {
                onSuccess: () => ElMessage.success('Ticket actualizado correctamente')
            });
        }
    });
};
</script>

<template>
    <AppLayout :title="`Editar Ticket #${ticket.id}`">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                    Editar ticket de servicio #{{ ticket.id }}
                </h2>
                <Link :href="route('tickets.index')">
                    <el-button icon="Back" circle />
                </Link>
            </div>
        </template>

        <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-6">
                
                <!-- Contexto del Presupuesto (Solo lectura) -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-lg p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <p class="text-xs text-blue-500 uppercase font-bold tracking-wide">Presupuesto Vinculado</p>
                        <h3 class="text-lg font-bold text-blue-800 dark:text-blue-100">{{ ticket.budget?.name }}</h3>
                        <p class="text-sm text-blue-600 dark:text-blue-300">{{ ticket.budget?.service_type }}</p>
                    </div>
                    <Link :href="route('budgets.show', ticket.budget_id)">
                        <el-button size="small" type="primary" plain>Ver Presupuesto</el-button>
                    </Link>
                </div>

                <!-- Formulario de Edición -->
                <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-[#2b2b2e] bg-gray-50/50 dark:bg-[#252529]">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <el-icon class="text-primary"><Tools /></el-icon> 
                            Configuración operativa
                        </h3>
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
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Responsable -->
                                <el-form-item label="Técnico responsable" prop="user_id">
                                    <el-select v-model="form.user_id" placeholder="Seleccionar técnico" class="w-full" filterable>
                                        <el-option 
                                            v-for="user in users" 
                                            :key="user.id" 
                                            :label="user.name" 
                                            :value="user.id" 
                                        />
                                    </el-select>
                                </el-form-item>

                                <!-- Estado -->
                                <el-form-item label="Estado actual" prop="status">
                                    <el-select v-model="form.status" class="w-full">
                                        <el-option v-for="item in statuses" :key="item" :label="item" :value="item" />
                                    </el-select>
                                </el-form-item>

                                <!-- Prioridad -->
                                <el-form-item label="Prioridad" prop="priority">
                                    <el-select v-model="form.priority" class="w-full">
                                        <el-option label="Baja" value="Baja" />
                                        <el-option label="Media" value="Media" />
                                        <el-option label="Alta" value="Alta" />
                                        <el-option label="Urgente" value="Urgente" />
                                    </el-select>
                                </el-form-item>

                                <!-- Fechas -->
                                <el-form-item label="Programación (Inicio - Fin)">
                                    <div class="flex gap-2 w-full">
                                        <el-date-picker 
                                            v-model="form.scheduled_start" 
                                            type="date" 
                                            placeholder="Inicio"
                                            class="!w-full"
                                            format="DD/MM/YYYY"
                                            value-format="YYYY-MM-DD"
                                        />
                                        <el-date-picker 
                                            v-model="form.scheduled_end" 
                                            type="date" 
                                            placeholder="Fin"
                                            class="!w-full"
                                            format="DD/MM/YYYY"
                                            value-format="YYYY-MM-DD"
                                        />
                                    </div>
                                </el-form-item>
                            </div>

                            <el-form-item label="Instrucciones especiales" prop="instructions" class="mt-2">
                                <el-input 
                                    v-model="form.instructions" 
                                    type="textarea" 
                                    :rows="5" 
                                    placeholder="Detalles operativos, acceso, herramientas necesarias..." 
                                />
                            </el-form-item>

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
                                    Actualizar ticket
                                </el-button>
                            </div>
                        </el-form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>