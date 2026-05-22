<script setup>
import { ref, reactive, computed } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox, ElNotification } from 'element-plus';
import { 
    Check, Share, CopyDocument, ChatDotSquare, Edit, Delete, VideoPlay, 
    Calendar, ZoomIn, Camera, Plus, Link as IconLink, StarFilled
} from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';
import QuickTechnicianModal from './QuickTechnicianModal.vue';

const { can } = usePermissions();

const props = defineProps({
    ticket: { type: Object, required: true },
    users: { type: Array, default: () => [] }
});

// --- GESTIÓN DE TÉCNICOS LOCALES ---
// Permite agregar al vuelo los creados rápidamente
const localUsers = ref([...props.users]);
const showQuickTechModal = ref(false);

const handleTechCreated = (newUser) => {
    localUsers.value.push(newUser);
    taskForm.user_id = newUser.id;
};

// --- COMPUTED: TÉCNICOS ASIGNADOS ---
const assignedTechnicians = computed(() => {
    const techs = new Map();
    props.ticket.tasks.forEach(task => {
        if (task.assignee && task.user_id) {
            if (!techs.has(task.user_id)) {
                const userFullInfo = localUsers.value.find(u => u.id === task.user_id);
                const phone = userFullInfo?.technician?.phone || '';
                techs.set(task.user_id, {
                    id: task.user_id,
                    name: task.assignee.name,
                    profile_photo_url: task.assignee.profile_photo_url,
                    share_url: task.share_url,
                    phone: phone,
                    status: userFullInfo?.technician?.status || 'N/A', // Obtenemos el estatus
                    rating_avg: userFullInfo?.technician?.rating_avg || 0, // Obtenemos el rating
                    task_count: 1
                });
            } else {
                const existing = techs.get(task.user_id);
                existing.task_count++;
            }
        }
    });
    return Array.from(techs.values());
});

// --- UTILS ---
const formatDateLong = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('es-ES', { 
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: true
    });
};

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

const isOverdue = (dateString) => new Date(dateString) < new Date();

const getTechnicianLabel = (user) => {
    let label = user.name;
    if (user.technician) {
        label += user.technician.is_internal ? ' (Interno)' : ' (Externo)';
    }
    return label;
};

// Helper para colores de estatus del técnico
const getStatusColor = (status) => {
    const map = {
        'Activo': 'success',
        'Inactivo': 'info',
        'En revisión': 'warning',
        'Vetado': 'danger'
    };
    return map[status] || 'info';
};

// --- GESTIÓN DE TAREAS ---
const showTaskModal = ref(false);
const isEditing = ref(false);
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

const openCreateModal = () => {
    isEditing.value = false;
    taskForm.reset();
    taskForm.clearErrors();
    showTaskModal.value = true;
};

const openEditModal = (task) => {
    isEditing.value = true;
    taskForm.clearErrors();
    taskForm.id = task.id;
    taskForm.name = task.name;
    taskForm.description = task.description;
    taskForm.user_id = task.user_id;
    taskForm.start_date = formatDateForInput(task.start_date); 
    taskForm.due_date = formatDateForInput(task.due_date);
    showTaskModal.value = true;
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

const submitTask = () => {
    if (!formRef.value) return;
    formRef.value.validate((valid) => {
        if (valid) {
            const options = {
                onSuccess: () => {
                    showTaskModal.value = false;
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
                },
                onError: (errors) => handleFormError(errors)
            };

            if (isEditing.value) {
                taskForm.put(route('tickets.tasks.update', taskForm.id), options);
            } else {
                taskForm.post(route('tickets.tasks.store', props.ticket.id), options);
            }
        }
    });
};

const toggleTask = (task) => router.put(route('tickets.tasks.toggle', task.id), {}, { preserveScroll: true });

const deleteTask = (task) => {
    ElMessageBox.confirm('¿Eliminar esta tarea y sus evidencias?', 'Confirmar', { type: 'warning' })
        .then(() => router.delete(route('tickets.tasks.destroy', task.id), { onSuccess: () => ElMessage.success('Eliminada') }))
        .catch(() => {});
};

// --- COMPARTIR Y EVIDENCIAS ---
const copyToClipboard = async (url) => {
    try { await navigator.clipboard.writeText(url); ElMessage.success('Copiado'); } catch (e) {}
};
const shareWhatsApp = (tech) => {
    const phone = tech.phone ? tech.phone.replace(/\D/g, '') : '';
    const text = `Hola ${tech.name}, tu Orden de Trabajo para el ticket #${props.ticket.id}: ${tech.share_url}`;
    window.open(phone ? `https://wa.me/${phone}?text=${encodeURIComponent(text)}` : `https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
};
const evidenceForm = useForm({ file: null });
const handleEvidenceUpload = (file, task) => {
    evidenceForm.file = file.raw;
    evidenceForm.post(route('tickets.tasks.evidence.store', task.id), {
        onSuccess: () => { ElMessage.success('Evidencia subida'); evidenceForm.reset(); },
        onError: () => ElMessage.error('Error al subir')
    });
};
const previewVisible = ref(false);
const previewImage = ref('');
const showImage = (url) => { previewImage.value = url; previewVisible.value = true; };
</script>

<template>
    <div class="py-4">
        <!-- Header y Botón Nueva Tarea -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">Lista de actividades</h3>
                <p class="text-sm text-gray-500">Planifica las tareas operativas y evita cruces de horarios.</p>
            </div>
            <el-button v-if="can('tickets.tasks.create')" type="primary" plain icon="Plus" @click="openCreateModal">
                Nueva tarea
            </el-button>
        </div>

        <!-- Popovers de compartir -->
        <div v-if="assignedTechnicians.length > 0" class="mb-8">
            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3 flex items-center gap-2">
                <el-icon><Share /></el-icon> Compartir Ordenes de Trabajo
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <el-popover v-for="tech in assignedTechnicians" :key="tech.id" placement="bottom" :width="280" trigger="click">
                    <template #reference>
                        <div class="flex items-center gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/50 rounded-lg cursor-pointer hover:bg-blue-100 transition-colors">
                            <el-avatar :src="tech.profile_photo_url" :size="40">{{ tech.name.charAt(0) }}</el-avatar>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200 truncate">{{ tech.name }}</p>
                                <p class="text-xs text-blue-600">{{ tech.task_count }} tareas</p>
                            </div>
                        </div>
                    </template>
                    <div class="text-center space-y-2">
                        <!-- Detalles de Desempeño y Estatus -->
                        <div class="flex justify-center items-center gap-2 pb-3 mb-2 border-b border-gray-100 dark:border-gray-700">
                            <el-tag :type="getStatusColor(tech.status)" size="small" effect="dark">
                                {{ tech.status }}
                            </el-tag>
                            <span class="flex items-center text-yellow-500 font-bold text-sm bg-yellow-50 dark:bg-yellow-900/20 px-2 py-0.5 rounded">
                                {{ tech.rating_avg }} <el-icon class="ml-0.5"><StarFilled /></el-icon>
                            </span>
                        </div>
                        
                        <p class="text-sm font-bold">Compartir acceso externo</p>
                        <el-button type="success" class="w-full" :icon="ChatDotSquare" @click="shareWhatsApp(tech)">WhatsApp</el-button>
                        <el-button type="info" plain class="w-full" :icon="CopyDocument" @click="copyToClipboard(tech.share_url)">Copiar link</el-button>
                    </div>
                </el-popover>
            </div>
        </div>

        <!-- Lista de Tareas -->
        <div v-if="ticket.tasks.length > 0" class="space-y-4">
            <div v-for="task in ticket.tasks" :key="task.id" class="border border-gray-200 dark:border-[#3f3f46] rounded-lg p-4 bg-white dark:bg-[#252529]" :class="{ 'opacity-75 bg-gray-50': task.status === 'Completada' }">
                <div class="flex items-start gap-4">
                    <div v-if="can('tickets.tasks.toggle')" @click="toggleTask(task)" class="w-6 h-6 rounded-full border-2 flex items-center justify-center cursor-pointer transition-colors" :class="task.status === 'Completada' ? 'bg-green-500 border-green-500' : 'border-gray-300 hover:border-primary'">
                        <el-icon v-if="task.status === 'Completada'" class="text-white"><Check /></el-icon>
                    </div>
                    <div class="flex-1">
                        <div class="flex justify-between items-start">
                            <h4 class="font-bold text-gray-800 dark:text-gray-200" :class="{ 'line-through text-gray-500': task.status === 'Completada' }">{{ task.name }}</h4>
                            <div class="flex gap-1">
                                <el-button v-if="can('tickets.tasks.edit')" type="primary" icon="Edit" circle size="small" plain @click="openEditModal(task)" />
                                <el-button v-if="can('tickets.tasks.delete')" type="danger" icon="Delete" circle size="small" plain @click="deleteTask(task)" />
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 mb-2">{{ task.description }}</p>
                        <div class="flex flex-wrap items-center gap-4 text-xs pb-2 border-b border-gray-100 dark:border-[#3f3f46]">
                            <div v-if="task.assignee" class="flex items-center gap-1 bg-gray-100 px-2 py-1 rounded-full text-gray-600">
                                <el-avatar :size="16" :src="task.assignee.profile_photo_url">{{ task.assignee.name.charAt(0) }}</el-avatar>
                                <span>{{ task.assignee.name }}</span>
                            </div>
                            <div v-if="task.start_date" class="flex items-center gap-1 text-gray-500">
                                <el-icon><VideoPlay /></el-icon><span>{{ formatDateLong(task.start_date) }}</span>
                            </div>
                            <div v-if="task.due_date" class="flex items-center gap-1">
                                <el-icon :class="isOverdue(task.due_date) ? 'text-red-500' : 'text-gray-400'"><Calendar /></el-icon>
                                <span :class="{'text-red-600 font-bold': isOverdue(task.due_date) && task.status !== 'Completada'}">{{ formatDateLong(task.due_date) }}</span>
                            </div>
                        </div>
                        <!-- Evidencias -->
                        <div class="mt-3 flex flex-wrap gap-3 items-center">
                            <span class="text-xs font-bold text-gray-500 uppercase">Evidencias:</span>
                            <div v-for="media in task.media" :key="media.id" class="w-12 h-12 rounded overflow-hidden border cursor-pointer" @click="showImage(media.original_url)">
                                <img :src="media.original_url" class="w-full h-full object-cover" />
                            </div>
                            <el-upload v-if="can('tickets.tasks.edit')" :show-file-list="false" :auto-upload="false" :on-change="(f) => handleEvidenceUpload(f, task)" accept="image/*">
                                <el-button size="small" icon="Camera" plain>Adjuntar</el-button>
                            </el-upload>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <el-empty v-else description="No hay tareas registradas" />

        <!-- MODAL TAREA -->
        <el-dialog v-model="showTaskModal" :title="isEditing ? 'Editar tarea' : 'Nueva tarea operativa'" width="500px">
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

                <!-- SELECTOR DE TÉCNICOS CON BOTÓN RÁPIDO Y DETALLES -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="text-sm font-bold text-gray-700 dark:text-gray-300">Asignar a</label>
                        <el-button type="primary" link size="small" @click="showQuickTechModal = true">
                            Registro rápido de técnico
                        </el-button>
                    </div>
                    <el-form-item prop="user_id" :error="taskForm.errors.user_id">
                        <el-select v-model="taskForm.user_id" placeholder="Seleccionar técnico" class="w-full" clearable filterable>
                            <!-- Personalizamos el diseño del Option dentro del selector -->
                            <el-option 
                                v-for="user in localUsers" 
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
                        </el-select>
                    </el-form-item>
                </div>
            </el-form>
            <template #footer>
                <el-button @click="showTaskModal = false">Cancelar</el-button>
                <el-button type="primary" color="#f26c17" @click="submitTask" :loading="taskForm.processing">
                    {{ isEditing ? 'Actualizar' : 'Guardar' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- COMPONENTE MODAL DE TÉCNICO RÁPIDO -->
        <QuickTechnicianModal 
            v-model="showQuickTechModal" 
            @created="handleTechCreated" 
        />

        <el-image-viewer v-if="previewVisible" :url-list="[previewImage]" @close="previewVisible = false" />
    </div>
</template>