<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ElMessage, ElNotification } from 'element-plus';
import { StarFilled } from '@element-plus/icons-vue';
import QuickTechnicianModal from './QuickTechnicianModal.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    ticketId: { type: [Number, String], required: true },
    users: { type: Array, default: () => [] },
    task: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'saved']);

// --- LOCAL USERS ---
const localUsers = ref([...props.users.filter(u => u.technician)]);

// --- MODAL VISIBILITY ---
const visible = ref(props.modelValue);
watch(() => props.modelValue, (v) => { visible.value = v; });
watch(visible, (v) => emit('update:modelValue', v));

// --- FORM ---
const isEditing = computed(() => !!props.task);

const formRef = ref();

const rules = reactive({
    name: [{ required: true, message: 'Requerido', trigger: 'blur' }],
    user_id: [{ required: true, message: 'Requerido', trigger: 'change' }],
    start_date: [{ required: true, message: 'Requerido', trigger: 'change' }],
    due_date: [{ required: true, message: 'Requerido', trigger: 'change' }]
});

const taskForm = useForm({
    id: null,
    name: '',
    description: '',
    user_id: '',
    start_date: '',
    due_date: '',
});

// --- INIT FORM WHEN OPENING ---
watch(visible, (isOpen) => {
    if (isOpen) {
        if (props.task) {
            taskForm.clearErrors();
            taskForm.id = props.task.id;
            taskForm.name = props.task.name;
            taskForm.description = props.task.description;
            taskForm.user_id = props.task.user_id;
            taskForm.start_date = formatDateForInput(props.task.start_date);
            taskForm.due_date = formatDateForInput(props.task.due_date);
        } else {
            taskForm.reset();
            taskForm.clearErrors();
        }
    }
});

// --- HELPERS ---
const formatDateForInput = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
};

const getTechnicianLabel = (user) => {
    let label = user.name;
    if (user.technician) {
        label += user.technician.is_internal ? ' (Interno)' : ' (Externo)';
        if (user.technician.state) {
            label += ` — ${user.technician.state}`;
        }
    }
    return label;
};

const getStatusColor = (status) => {
    const map = {
        'Activo': 'success',
        'Inactivo': 'info',
        'En revisión': 'warning',
        'Vetado': 'danger'
    };
    return map[status] || 'info';
};

const handleFormError = (errors) => {
    if (errors.start_date) {
        ElNotification({
            title: 'Conflicto de Agenda',
            message: errors.start_date,
            type: 'error',
            duration: 8000,
            position: 'top-right',
        });
    } else {
        ElMessage.error('Por favor revisa los campos requeridos.');
    }
};

// --- SUBMIT ---
const submitTask = () => {
    if (!formRef.value) return;
    formRef.value.validate((valid) => {
        if (valid) {
            const options = {
                onSuccess: () => {
                    visible.value = false;
                    taskForm.reset();
                    ElMessage.success(isEditing.value ? 'Tarea actualizada' : 'Tarea agendada');
                    if (usePage().props.flash.warning) {
                        ElNotification({
                            title: 'Conflicto de agenda',
                            message: usePage().props.flash.warning,
                            type: 'warning',
                            duration: 8000,
                            position: 'top-right',
                        });
                    }
                    emit('saved');
                },
                onError: (errors) => handleFormError(errors)
            };

            if (isEditing.value) {
                taskForm.put(route('tickets.tasks.update', taskForm.id), options);
            } else {
                taskForm.post(route('tickets.tasks.store', props.ticketId), options);
            }
        }
    });
};

// --- QUICK TECHNICIAN ---
const showQuickTechModal = ref(false);

const handleTechCreated = (newUser) => {
    localUsers.value.push(newUser);
    taskForm.user_id = newUser.id;
};
</script>

<template>
    <el-dialog v-model="visible" :title="isEditing ? 'Editar tarea' : 'Nueva tarea operativa'" width="500px" destroy-on-close>
        <el-form ref="formRef" :model="taskForm" :rules="rules" label-position="top">
            <el-alert
                v-if="taskForm.errors.start_date"
                :title="taskForm.errors.start_date"
                type="error"
                show-icon
                :closable="false"
                class="mb-4 whitespace-pre-wrap"
            />

            <el-form-item label="Actividad / Tarea" prop="name" :error="taskForm.errors.name">
                <el-input v-model="taskForm.name" placeholder="Ej. Instalación de cableado" />
            </el-form-item>

            <el-form-item label="Detalles" prop="description">
                <el-input v-model="taskForm.description" type="textarea" placeholder="Instrucciones específicas..." />
            </el-form-item>

            <!-- FECHAS -->
            <div class="grid grid-cols-2 gap-4">
                <el-form-item label="Fecha Inicio" prop="start_date" :error="taskForm.errors.start_date ? ' ' : ''">
                    <el-date-picker
                        v-model="taskForm.start_date"
                        type="datetime"
                        class="!w-full"
                        placeholder="Seleccionar"
                        format="DD/MM/YYYY hh:mm A"
                        value-format="YYYY-MM-DD HH:mm:ss"
                    />
                </el-form-item>
                <el-form-item label="Fecha Término" prop="due_date" :error="taskForm.errors.due_date">
                    <el-date-picker
                        v-model="taskForm.due_date"
                        type="datetime"
                        class="!w-full"
                        placeholder="Seleccionar"
                        format="DD/MM/YYYY hh:mm A"
                        value-format="YYYY-MM-DD HH:mm:ss"
                    />
                </el-form-item>
            </div>

            <!-- SELECTOR DE TÉCNICOS -->
            <div>
                <div class="flex justify-between items-center mb-1">
                    <label class="text-sm font-bold text-gray-700 dark:text-gray-300">Asignar a</label>
                    <el-button type="primary" link size="small" @click="showQuickTechModal = true">
                        Registro rápido de técnico
                    </el-button>
                </div>
                <el-form-item prop="user_id" :error="taskForm.errors.user_id">
                    <el-select v-model="taskForm.user_id" placeholder="Seleccionar técnico" class="w-full" clearable filterable>
                        <el-option-group
                            v-if="localUsers.filter(u => u.technician?.level === 'Encargado').length"
                            label="Encargados"
                        >
                            <el-option
                                v-for="user in localUsers.filter(u => u.technician?.level === 'Encargado')"
                                :key="user.id"
                                :label="getTechnicianLabel(user)"
                                :value="user.id"
                                class="!h-auto py-1.5"
                            >
                                <div class="flex justify-between items-center w-full">
                                    <span class="font-medium text-gray-800 dark:text-gray-200">
                                        {{ getTechnicianLabel(user) }}
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <el-tag :type="getStatusColor(user.technician?.status)" size="small" effect="plain" class="scale-90">
                                            {{ user.technician?.status || 'N/A' }}
                                        </el-tag>
                                        <span class="flex items-center text-yellow-500 font-bold text-xs bg-yellow-50 dark:bg-yellow-900/20 px-1.5 py-0.5 rounded">
                                            {{ user.technician?.rating_avg || 0 }} <el-icon class="ml-0.5"><StarFilled /></el-icon>
                                        </span>
                                    </div>
                                </div>
                            </el-option>
                        </el-option-group>
                        <el-option-group
                            v-if="localUsers.filter(u => u.technician?.level === 'Auxiliar/Ayudante').length"
                            label="Auxiliares / Ayudantes"
                        >
                            <el-option
                                v-for="user in localUsers.filter(u => u.technician?.level === 'Auxiliar/Ayudante')"
                                :key="user.id"
                                :label="getTechnicianLabel(user)"
                                :value="user.id"
                                class="!h-auto py-1.5"
                            >
                                <div class="flex justify-between items-center w-full">
                                    <span class="font-medium text-gray-800 dark:text-gray-200">
                                        {{ getTechnicianLabel(user) }}
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <el-tag :type="getStatusColor(user.technician?.status)" size="small" effect="plain" class="scale-90">
                                            {{ user.technician?.status || 'N/A' }}
                                        </el-tag>
                                        <span class="flex items-center text-yellow-500 font-bold text-xs bg-yellow-50 dark:bg-yellow-900/20 px-1.5 py-0.5 rounded">
                                            {{ user.technician?.rating_avg || 0 }} <el-icon class="ml-0.5"><StarFilled /></el-icon>
                                        </span>
                                    </div>
                                </div>
                            </el-option>
                        </el-option-group>
                    </el-select>
                </el-form-item>
            </div>
        </el-form>
        <template #footer>
            <el-button @click="visible = false">Cancelar</el-button>
            <el-button type="primary" color="#f26c17" @click="submitTask" :loading="taskForm.processing">
                {{ isEditing ? 'Actualizar' : 'Guardar' }}
            </el-button>
        </template>
    </el-dialog>

    <!-- QUICK TECHNICIAN MODAL -->
    <QuickTechnicianModal
        v-model="showQuickTechModal"
        @created="handleTechCreated"
    />
</template>
