<script setup>
import { ref, reactive, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { 
    Check, 
    Share, 
    CopyDocument, 
    ChatDotSquare, 
    Edit, 
    Delete, 
    VideoPlay, 
    Calendar, 
    ZoomIn, 
    Camera,
    Plus,
    UserFilled,
    Link as IconLink
} from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    ticket: {
        type: Object,
        required: true
    },
    users: {
        type: Array,
        default: () => []
    }
});

// --- COMPUTED: TÉCNICOS ASIGNADOS ---
// Agrupamos las tareas por usuario para mostrar los botones de compartir una sola vez por técnico
const assignedTechnicians = computed(() => {
    const techs = new Map();

    props.ticket.tasks.forEach(task => {
        if (task.assignee && task.user_id) {
            if (!techs.has(task.user_id)) {
                // Buscamos info extra del técnico (como el teléfono) en el prop 'users'
                // ya que task.assignee solo trae datos básicos del User
                const userFullInfo = props.users.find(u => u.id === task.user_id);
                const phone = userFullInfo?.technician?.phone || '';

                techs.set(task.user_id, {
                    id: task.user_id,
                    name: task.assignee.name,
                    profile_photo_url: task.assignee.profile_photo_url,
                    share_url: task.share_url, // URL firmada de la Orden de Trabajo (viene del backend)
                    phone: phone,
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
        day: '2-digit', 
        month: 'short', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
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

const isOverdue = (dateString) => {
    const due = new Date(dateString);
    const now = new Date();
    return due < now;
};

const getTechnicianLabel = (user) => {
    let label = user.name;
    if (user.technician) {
        label += user.technician.is_internal ? ' (Interno)' : ' (Externo)';
    }
    return label;
};

// --- GESTIÓN DE TAREAS ---
const showTaskModal = ref(false);
const isEditing = ref(false);
const formRef = ref();

const rules = reactive({
    name: [
        { required: true, message: 'El nombre de la actividad es obligatorio', trigger: 'blur' },
        { min: 3, message: 'Debe tener al menos 3 caracteres', trigger: 'blur' }
    ],
    user_id: [
        { required: true, message: 'Debes asignar un técnico responsable', trigger: 'change' }
    ]
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
    setTimeout(() => { if (formRef.value) formRef.value.clearValidate(); }, 50);
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
    setTimeout(() => { if (formRef.value) formRef.value.clearValidate(); }, 50);
};

const submitTask = () => {
    if (!formRef.value) return;
    formRef.value.validate((valid) => {
        if (valid) {
            if (isEditing.value) {
                taskForm.put(route('tickets.tasks.update', taskForm.id), {
                    onSuccess: () => {
                        showTaskModal.value = false;
                        taskForm.reset();
                        ElMessage.success('Tarea actualizada correctamente');
                    }
                });
            } else {
                taskForm.post(route('tickets.tasks.store', props.ticket.id), {
                    onSuccess: () => {
                        showTaskModal.value = false;
                        taskForm.reset();
                        ElMessage.success('Tarea agregada');
                    }
                });
            }
        }
    });
};

const toggleTask = (task) => {
    router.put(route('tickets.tasks.toggle', task.id), {}, { preserveScroll: true });
};

const deleteTask = (task) => {
    ElMessageBox.confirm('¿Eliminar esta tarea y sus evidencias?', 'Confirmar', { 
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
        type: 'warning' 
    }).then(() => {
        router.delete(route('tickets.tasks.destroy', task.id), {
            onSuccess: () => ElMessage.success('Tarea eliminada')
        });
    }).catch(() => {});
};

// --- COMPARTIR (NIVEL TÉCNICO) ---
const copyToClipboard = async (url) => {
    try {
        await navigator.clipboard.writeText(url);
        ElMessage.success('Enlace de Orden de Trabajo copiado');
    } catch (err) {
        ElMessage.error('No se pudo copiar el enlace');
    }
};

const shareWhatsApp = (tech) => {
    // Si tenemos el teléfono del técnico, lo usamos. Si no, abrimos whatsapp general.
    const phone = tech.phone ? tech.phone.replace(/\D/g, '') : '';
    
    const text = `Hola ${tech.name}, aquí tienes tu Orden de Trabajo Digital para el ticket #${props.ticket.id}.\n\nPor favor revisa tus actividades y reporta avances aquí: ${tech.share_url}`;
    
    const url = phone 
        ? `https://wa.me/${phone}?text=${encodeURIComponent(text)}`
        : `https://wa.me/?text=${encodeURIComponent(text)}`;
        
    window.open(url, '_blank');
};

// --- EVIDENCIAS ---
const evidenceForm = useForm({ file: null });
const handleEvidenceUpload = (file, task) => {
    evidenceForm.file = file.raw;
    evidenceForm.post(route('tickets.tasks.evidence.store', task.id), {
        preserveScroll: true,
        onSuccess: () => { ElMessage.success('Evidencia subida'); evidenceForm.reset(); },
        onError: () => ElMessage.error('Error al subir imagen')
    });
};

// --- PREVISUALIZACIÓN ---
const previewVisible = ref(false);
const previewImage = ref('');
const showImage = (url) => {
    previewImage.value = url;
    previewVisible.value = true;
};
</script>

<template>
    <div class="py-4">
        
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">Lista de actividades</h3>
                <p class="text-sm text-gray-500">Gestiona las tareas operativas y sus evidencias.</p>
            </div>
            <el-button v-if="can('tickets.tasks.create')" type="primary" plain icon="Plus" @click="openCreateModal">
                Nueva tarea
            </el-button>
        </div>

        <!-- SECCIÓN: COMPARTIR ORDENES DE TRABAJO (Técnicos) -->
        <div v-if="assignedTechnicians.length > 0" class="mb-8">
            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3 flex items-center gap-2">
                <el-icon><Share /></el-icon> Compartir Ordenes de Trabajo
            </h4>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <el-popover
                    v-for="tech in assignedTechnicians"
                    :key="tech.id"
                    placement="bottom"
                    :width="280"
                    trigger="click"
                >
                    <template #reference>
                        <div class="flex items-center gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/50 rounded-lg cursor-pointer hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors group">
                            <div class="relative">
                                <el-avatar :src="tech.profile_photo_url" :size="40" class="border-2 border-white dark:border-gray-800 shadow-sm">
                                    {{ tech.name.charAt(0) }}
                                </el-avatar>
                                <div class="absolute -bottom-0 -right-1 bg-green-500 rounded-full size-5 flex items-center justify-center border-2 border-white dark:border-gray-800">
                                    <el-icon class="text-white text-[9px]"><Link /></el-icon>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200 truncate">{{ tech.name }}</p>
                                <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">
                                    {{ tech.task_count }} {{ tech.task_count === 1 ? 'tarea asignada' : 'tareas asignadas' }}
                                </p>
                            </div>
                        </div>
                    </template>
                    
                    <!-- Contenido del Popover -->
                    <div class="text-center">
                        <p class="text-sm font-bold text-gray-800 mb-1">Compartir acceso externo</p>
                        <p class="text-xs text-gray-500 mb-3">
                            Este enlace da acceso a la vista "Orden de Trabajo Digital" con todas sus tareas.
                        </p>
                        <div class="flex flex-col items-center gap-2">
                            <el-button type="success" class="w-full" :icon="ChatDotSquare" @click="shareWhatsApp(tech)">
                                Enviar por WhatsApp
                            </el-button>
                            <el-button type="info" plain class="w-full" :icon="CopyDocument" @click="copyToClipboard(tech.share_url)">
                                Copiar enlace
                            </el-button>
                        </div>
                    </div>
                </el-popover>
            </div>
        </div>

        <!-- LISTA DE TAREAS -->
        <div v-if="ticket.tasks.length > 0" class="space-y-4">
            <div 
                v-for="task in ticket.tasks" 
                :key="task.id" 
                class="border border-gray-200 dark:border-[#3f3f46] rounded-lg p-4 transition-all hover:shadow-sm bg-white dark:bg-[#252529]"
                :class="{ 'opacity-75 bg-gray-50 dark:bg-[#18181b]': task.status === 'Completada' }"
            >
                <div class="flex items-start gap-4">
                    <div class="pt-1">
                        <div v-if="can('tickets.tasks.toggle')"
                            @click="toggleTask(task)"
                            class="w-6 h-6 rounded-full border-2 flex items-center justify-center cursor-pointer transition-colors"
                            :class="task.status === 'Completada' ? 'bg-green-500 border-green-500' : 'border-gray-300 hover:border-primary'"
                        >
                            <el-icon v-if="task.status === 'Completada'" class="text-white font-bold"><Check /></el-icon>
                        </div>
                    </div>

                    <div class="flex-1">
                        <div class="flex flex-col sm:flex-row justify-between items-start gap-2">
                            <h4 
                                class="font-bold text-gray-800 dark:text-gray-200 text-base"
                                :class="{ 'line-through text-gray-500': task.status === 'Completada' }"
                            >
                                {{ task.name }}
                            </h4>
                            <div class="flex items-center gap-1">
                                <!-- Botones de edición (Ya no hay compartir individual) -->
                                <el-button v-if="can('tickets.tasks.edit')" type="primary" icon="Edit" circle size="small" plain @click="openEditModal(task)" />
                                <el-button v-if="can('tickets.tasks.delete')" type="danger" icon="Delete" circle size="small" plain @click="deleteTask(task)" />
                            </div>
                        </div>
                        
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 mb-3">
                            {{ task.description || 'Sin descripción adicional.' }}
                        </p>

                        <div class="flex flex-wrap items-center gap-4 text-xs mt-2 pb-2 border-b border-gray-100 dark:border-[#3f3f46]">
                            <div v-if="task.assignee" class="flex items-center gap-1.5 bg-gray-100 dark:bg-[#3f3f46] px-2 py-1 rounded-full text-gray-600 dark:text-gray-300">
                                <el-avatar :size="16" :src="task.assignee.profile_photo_url" class="bg-white text-gray-500 border border-gray-200">
                                    {{ task.assignee.name.charAt(0) }}
                                </el-avatar>
                                <span class="font-medium">{{ task.assignee.name }}</span>
                            </div>
                            
                            <!-- Fechas -->
                            <div class="flex items-center gap-3 flex-wrap">
                                <div v-if="task.start_date" class="flex items-center gap-1 text-gray-500">
                                    <el-icon><VideoPlay /></el-icon>
                                    <span>{{ formatDateLong(task.start_date) }}</span>
                                </div>
                                <div v-if="task.due_date" class="flex items-center gap-1">
                                    <el-icon :class="isOverdue(task.due_date) && task.status !== 'Completada' ? 'text-red-500' : 'text-gray-400'"><Calendar /></el-icon>
                                    <span :class="{'text-red-600 font-bold': isOverdue(task.due_date) && task.status !== 'Completada', 'text-gray-500': !isOverdue(task.due_date) || task.status === 'Completada'}">
                                        {{ formatDateLong(task.due_date) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="flex flex-wrap gap-3 items-center">
                                <span class="text-xs font-bold text-gray-500 uppercase">Evidencias:</span>
                                <div 
                                    v-for="media in task.media" 
                                    :key="media.id" 
                                    class="relative w-12 h-12 rounded overflow-hidden border border-gray-200 cursor-pointer group"
                                    @click="showImage(media.original_url)"
                                >
                                    <img :src="media.original_url" class="w-full h-full object-cover" />
                                    <div class="absolute inset-0 bg-black/40 hidden group-hover:flex items-center justify-center text-white">
                                        <el-icon><ZoomIn /></el-icon>
                                    </div>
                                </div>
                                <el-upload v-if="can('tickets.tasks.edit')"
                                    :show-file-list="false"
                                    :auto-upload="false"
                                    :on-change="(file) => handleEvidenceUpload(file, task)"
                                    accept="image/*"
                                >
                                    <el-button size="small" icon="Camera" plain>Adjuntar</el-button>
                                </el-upload>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <el-empty v-else description="No hay tareas registradas para este ticket" />

        <!-- MODAL TAREA -->
        <el-dialog 
            v-model="showTaskModal" 
            :title="isEditing ? 'Editar tarea' : 'Nueva tarea operativa'" 
            width="500px"
        >
            <el-form 
                ref="formRef" 
                :model="taskForm" 
                :rules="rules"
                label-position="top"
                require-asterisk-position="right"
            >
                <el-form-item label="Actividad / Tarea" prop="name">
                    <el-input v-model="taskForm.name" placeholder="Ej. Instalación de cableado" />
                </el-form-item>
                
                <el-form-item label="Detalles" prop="description">
                    <el-input v-model="taskForm.description" type="textarea" placeholder="Instrucciones específicas..." />
                </el-form-item>

                <!-- FECHAS -->
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Fecha Inicio" prop="start_date">
                        <el-date-picker 
                            v-model="taskForm.start_date" 
                            type="datetime" 
                            class="!w-full" 
                            placeholder="Seleccionar"
                            format="DD/MM/YYYY hh:mm A"
                            value-format="YYYY-MM-DD HH:mm:ss"
                        />
                    </el-form-item>
                    <el-form-item label="Fecha Límite" prop="due_date">
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

                <el-form-item label="Asignar a" prop="user_id">
                    <el-select v-model="taskForm.user_id" placeholder="Seleccionar técnico" class="w-full" clearable filterable>
                        <el-option 
                            v-for="user in users" 
                            :key="user.id" 
                            :label="getTechnicianLabel(user)" 
                            :value="user.id" 
                        />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showTaskModal = false">Cancelar</el-button>
                <el-button 
                    type="primary" 
                    color="#f26c17" 
                    @click="submitTask" 
                    :loading="taskForm.processing"
                >
                    {{ isEditing ? 'Actualizar' : 'Guardar' }}
                </el-button>
            </template>
        </el-dialog>

        <el-image-viewer 
            v-if="previewVisible" 
            :url-list="[previewImage]" 
            @close="previewVisible = false" 
        />
    </div>
</template>