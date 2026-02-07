<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    ticket: Object,
    users: Array,
});

// --- UTILS ---
const formatDateLong = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const day = date.toLocaleDateString('es-ES', { day: '2-digit' });
    const month = date.toLocaleDateString('es-ES', { month: 'long' });
    const year = date.getFullYear();
    return `${day} ${month}, ${year}`;
};

const isOverdue = (dateString) => {
    const due = new Date(dateString);
    const now = new Date();
    return due < now;
};

// --- GESTIÓN DE TAREAS ---
const showTaskModal = ref(false);
const isEditing = ref(false);

const taskForm = useForm({
    id: null,
    name: '',
    description: '',
    user_id: '',
    start_date: '', // Nuevo campo
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
    taskForm.start_date = task.start_date; // Cargar fecha inicio
    taskForm.due_date = task.due_date;
    
    showTaskModal.value = true;
};

const submitTask = () => {
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
};

const toggleTask = (task) => {
    router.put(route('tickets.tasks.toggle', task.id), {}, {
        preserveScroll: true,
        onSuccess: () => { }
    });
};

const deleteTask = (task) => {
    ElMessageBox.confirm('¿Eliminar esta tarea y sus evidencias?', 'Confirmar', { 
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
        type: 'warning' 
    })
    .then(() => {
        router.delete(route('tickets.tasks.destroy', task.id), {
            onSuccess: () => ElMessage.success('Tarea eliminada')
        });
    }).catch(() => {});
};

// --- EVIDENCIAS ---
const evidenceForm = useForm({ file: null });

const handleEvidenceUpload = (file, task) => {
    evidenceForm.file = file.raw;
    evidenceForm.post(route('tickets.tasks.evidence.store', task.id), {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Evidencia subida');
            evidenceForm.reset();
        },
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
                        <div class="flex justify-between items-start">
                            <h4 
                                class="font-bold text-gray-800 dark:text-gray-200 text-base"
                                :class="{ 'line-through text-gray-500': task.status === 'Completada' }"
                            >
                                {{ task.name }}
                            </h4>
                            <div class="flex items-center gap-1">
                                <el-button v-if="can('tickets.tasks.edit')" type="primary" icon="Edit" circle size="small" plain @click="openEditModal(task)" />
                                <el-button v-if="can('tickets.tasks.delete')" type="danger" icon="Delete" circle size="small" plain @click="deleteTask(task)" />
                            </div>
                        </div>
                        
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 mb-3">
                            {{ task.description || 'Sin descripción adicional.' }}
                        </p>

                        <div class="flex flex-wrap items-center gap-4 text-xs mt-2 pb-2 border-b border-gray-100 dark:border-[#3f3f46]">
                            <div v-if="task.assignee" class="flex items-center gap-1.5 bg-gray-100 dark:bg-[#3f3f46] px-2 py-1 rounded-full text-gray-600 dark:text-gray-300">
                                <el-avatar :size="16" class="!text-[10px] bg-white text-gray-500 border border-gray-200">
                                    {{ task.assignee.name.charAt(0) }}
                                </el-avatar>
                                <span class="font-medium">{{ task.assignee.name }}</span>
                            </div>
                            
                            <!-- Fechas -->
                            <div class="flex items-center gap-3">
                                <div v-if="task.start_date" class="flex items-center gap-1 text-gray-500">
                                    <el-icon><VideoPlay /></el-icon>
                                    <span>Inicia: {{ formatDateLong(task.start_date) }}</span>
                                </div>
                                <div v-if="task.due_date" class="flex items-center gap-1">
                                    <el-icon :class="isOverdue(task.due_date) && task.status !== 'Completada' ? 'text-red-500' : 'text-gray-400'"><Calendar /></el-icon>
                                    <span :class="{'text-red-600 font-bold': isOverdue(task.due_date) && task.status !== 'Completada', 'text-gray-500': !isOverdue(task.due_date) || task.status === 'Completada'}">
                                        Vence: {{ formatDateLong(task.due_date) }}
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
            <el-form :model="taskForm" label-position="top">
                <el-form-item label="Actividad / Tarea">
                    <el-input v-model="taskForm.name" placeholder="Ej. Instalación de cableado" />
                </el-form-item>
                
                <el-form-item label="Detalles">
                    <el-input v-model="taskForm.description" type="textarea" placeholder="Instrucciones específicas..." />
                </el-form-item>

                <!-- FECHAS -->
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Fecha Inicio (Planificada)">
                        <el-date-picker 
                            v-model="taskForm.start_date" 
                            type="datetime" 
                            class="!w-full" 
                            placeholder="Seleccionar"
                            format="DD/MM/YYYY HH:mm"
                        />
                    </el-form-item>
                    <el-form-item label="Fecha Límite (Fin)">
                        <el-date-picker 
                            v-model="taskForm.due_date" 
                            type="datetime" 
                            class="!w-full" 
                            placeholder="Seleccionar"
                            format="DD/MM/YYYY HH:mm"
                        />
                    </el-form-item>
                </div>

                <el-form-item label="Asignar a (Opcional)">
                    <el-select v-model="taskForm.user_id" placeholder="Técnico" class="w-full" clearable filterable>
                        <el-option v-for="user in users" :key="user.id" :label="user.name" :value="user.id" />
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