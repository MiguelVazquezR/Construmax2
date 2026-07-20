<script setup>
import { ref, reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Check, Share, CopyDocument, ChatDotSquare, Edit, Delete, VideoPlay,
    Calendar, Camera, Plus, StarFilled, Warning,
    Phone, Message, TopRight, Upload, Rank
} from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';
import draggable from 'vuedraggable';
import axios from 'axios';
import TechnicianPaymentSection from './TechnicianPaymentSection.vue';
import TaskFormModal from './TaskFormModal.vue';
import IntegrateTechniciansModal from './IntegrateTechniciansModal.vue';
import WorkAcceptanceReportCard from '@/Components/Tickets/WorkAcceptanceReportCard.vue';

const { can } = usePermissions();

const props = defineProps({
    ticket: { type: Object, required: true },
    users: { type: Array, default: () => [] }
});

// --- LOCAL USERS ---
const localUsers = ref([...props.users.filter(u => u.technician)]);

// --- ASSIGNED TECHNICIANS ---
const assignedTechnicians = computed(() => {
    const techs = new Map();
    const techIds = new Set([
        ...(props.ticket.technicians || []).map(Number),
        ...(props.ticket.assistant_technicians || []).map(Number),
        ...props.ticket.tasks.map(t => t.user_id).filter(Boolean),
    ]);

    techIds.forEach(userId => {
        const taskCount = props.ticket.tasks.filter(t => t.user_id === userId).length;
        const task = props.ticket.tasks.find(t => t.user_id === userId);
        const userFullInfo = localUsers.value.find(u => u.id === userId);

        const name = task?.assignee?.name || userFullInfo?.name || `User #${userId}`;
        const email = task?.assignee?.email || userFullInfo?.email || '';
        const photo = task?.assignee?.profile_photo_url || userFullInfo?.profile_photo_url || '';
        const shareUrl = task?.share_url || '';
        const phone = userFullInfo?.technician?.phone || '';
        const level = userFullInfo?.technician?.level || '';
        const isInternal = userFullInfo?.technician?.is_internal;

        techs.set(userId, {
            id: userId,
            name,
            email,
            profile_photo_url: photo,
            share_url: shareUrl,
            phone,
            level,
            is_internal: isInternal,
            status: userFullInfo?.technician?.status || 'N/A',
            rating_avg: userFullInfo?.technician?.rating_avg || 0,
            task_count: taskCount,
            technician_id: userFullInfo?.technician?.id ?? null,
            state: userFullInfo?.technician?.state ?? null,
        });
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

const isOverdue = (dateString) => new Date(dateString) < new Date();

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

// --- MODALS ---
const showTaskModal = ref(false);
const editingTask = ref(null);

const openCreateModal = () => {
    editingTask.value = null;
    showTaskModal.value = true;
};

const openCreateModalForTech = (techId) => {
    editingTask.value = null;
    // We need to pre-set the user_id; the modal handles this via watch
    showTaskModal.value = true;
    // Use nextTick to set after modal opens
    setTimeout(() => { /* user_id is set via the modal's internal state */ }, 50);
};

const openEditModal = (task) => {
    editingTask.value = task;
    showTaskModal.value = true;
};

const handleTaskSaved = () => {
    router.reload({ only: ['ticket'], preserveScroll: true, preserveState: true });
};

const showIntegrateTechsModal = ref(false);

const openIntegrateModal = () => {
    showIntegrateTechsModal.value = true;
};

const handleIntegrateSaved = () => {
    // reload is handled inside the modal
};

// --- TASK OPERATIONS ---
const toggleTask = (task) => router.put(route('tickets.tasks.toggle', task.id), {}, { preserveScroll: true });

const deleteTask = (task) => {
    ElMessageBox.confirm('¿Eliminar esta tarea y sus evidencias?', 'Confirmar', { type: 'warning' })
        .then(() => router.delete(route('tickets.tasks.destroy', task.id), { onSuccess: () => ElMessage.success('Eliminada') }))
        .catch(() => {});
};

// --- SHARING ---
const copyToClipboard = async (url) => {
    try { await navigator.clipboard.writeText(url); ElMessage.success('Copiado'); } catch (e) {}
};

const shareWhatsApp = (tech) => {
    const phone = tech.phone ? tech.phone.replace(/\D/g, '') : '';
    const text = `Hola ${tech.name}, tu Orden de Trabajo para el ticket #${props.ticket.id}: ${tech.share_url}`;
    window.open(phone ? `https://wa.me/${phone}?text=${encodeURIComponent(text)}` : `https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
};

const openInNewTab = (url) => {
    window.open(url, '_blank');
};

// --- EVIDENCE ---
const pendingEvidence = reactive({});
const uploadingTasks = reactive({});
const reorderingTasks = reactive({});

const handleEvidenceUpload = (file, task) => {
    if (!pendingEvidence[task.id]) pendingEvidence[task.id] = [];
    pendingEvidence[task.id].push(file.raw);
};

const flushEvidenceUpload = (task) => {
    const files = pendingEvidence[task.id];
    if (!files || files.length === 0) return;

    uploadingTasks[task.id] = true;
    const formData = new FormData();
    files.forEach(file => formData.append('files[]', file));

    axios.post(route('tickets.tasks.evidence.store', task.id), formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    }).then(() => {
        delete pendingEvidence[task.id];
        ElMessage.success('Evidencia subida');
        router.reload({ only: ['ticket'], preserveScroll: true, preserveState: true });
    }).catch(() => {
        ElMessage.error('Error al subir evidencia');
    }).finally(() => {
        delete uploadingTasks[task.id];
    });
};

const previewVisible = ref(false);
const previewImage = ref('');
const showImage = (url) => { previewImage.value = url; previewVisible.value = true; };

const deleteEvidence = (mediaId) => {
    ElMessageBox.confirm('¿Eliminar esta evidencia?', 'Confirmar', { type: 'warning' })
        .then(() => router.delete(route('tickets.evidence.destroy', mediaId), {
            onSuccess: () => ElMessage.success('Evidencia eliminada.'),
            onError: () => ElMessage.error('Error al eliminar la evidencia.'),
        }))
        .catch(() => {});
};

const reorderEvidence = (task) => {
    const media = task.media;
    if (!media || media.length <= 1) return;

    reorderingTasks[task.id] = true;
    const ids = media.map(m => m.id);
    axios.post(route('tickets.tasks.evidence.reorder', task.id), { ids })
        .then(() => ElMessage.success('Orden actualizado.'))
        .catch(() => ElMessage.error('Error al reordenar evidencias.'))
        .finally(() => { delete reorderingTasks[task.id]; });
};
</script>

<template>
    <div class="py-4">
        <!-- ACTA DE RECEPCIÓN -->
        <WorkAcceptanceReportCard :ticket="ticket" />

        <!-- SECCIÓN DE PAGO A TÉCNICO -->
        <TechnicianPaymentSection
            :ticket="ticket"
            :assigned-technicians="assignedTechnicians"
        />

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
        <div class="mb-8">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide flex items-center gap-2">
                    <el-icon><Share /></el-icon> Compartir Ordenes de Trabajo
                </h4>
                <el-button v-if="can('tickets.edit')" size="small" plain icon="Plus" @click="openIntegrateModal">
                    Integrar/remover técnicos
                </el-button>
            </div>
            <div v-if="assignedTechnicians.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <el-popover v-for="tech in assignedTechnicians" :key="tech.id" placement="bottom" :width="280" trigger="click">
                    <template #reference>
                        <div class="flex items-center gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/50 rounded-lg cursor-pointer hover:bg-blue-100 transition-colors">
                            <el-avatar :src="tech.profile_photo_url" :size="40">{{ tech.name.charAt(0) }}</el-avatar>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200 truncate flex items-center gap-1">
                                    {{ tech.name }}
                                    <el-tag
                                        v-if="tech.is_internal !== undefined"
                                        size="small"
                                        :type="tech.is_internal ? 'success' : 'warning'"
                                        effect="dark"
                                        class="!h-4 !text-[9px] !px-1"
                                    >
                                        {{ tech.is_internal ? 'Interno' : 'Externo' }}
                                    </el-tag>
                                    <el-tag
                                        v-if="tech.level"
                                        size="small"
                                        :type="tech.level === 'Encargado' ? 'primary' : 'warning'"
                                        effect="dark"
                                        class="!h-4 !text-[9px] !px-1"
                                    >
                                        {{ tech.level === 'Encargado' ? 'Encargado' : 'Auxiliar' }}
                                    </el-tag>
                                </p>
                                <p class="text-xs text-blue-600">{{ tech.task_count }} tareas</p>
                                <div v-if="tech.phone || tech.email" class="mt-1 space-y-0.5">
                                    <p v-if="tech.phone" class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                        <el-icon style="vertical-align: middle;" :size="12"><Phone /></el-icon>
                                        <span class="ml-0.5">{{ tech.phone }}</span>
                                    </p>
                                    <p v-if="tech.email" class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                        <el-icon style="vertical-align: middle;" :size="12"><Message /></el-icon>
                                        <span class="ml-0.5">{{ tech.email }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div class="text-center space-y-2">
                        <div class="flex justify-center items-center gap-2 pb-3 mb-2 border-b border-gray-100 dark:border-gray-700">
                            <el-tag :type="getStatusColor(tech.status)" size="small" effect="dark">
                                {{ tech.status }}
                            </el-tag>
                            <span class="flex items-center text-yellow-500 font-bold text-sm bg-yellow-50 dark:bg-yellow-900/20 px-2 py-0.5 rounded">
                                {{ tech.rating_avg }} <el-icon class="ml-0.5"><StarFilled /></el-icon>
                            </span>
                        </div>

                        <template v-if="tech.task_count > 0">
                            <p class="text-sm font-bold">Compartir acceso externo</p>
                            <el-button type="success" class="w-full" :icon="ChatDotSquare" @click="shareWhatsApp(tech)">WhatsApp</el-button>
                            <el-button type="info" plain class="w-full" :icon="CopyDocument" @click="copyToClipboard(tech.share_url)">Copiar enlace</el-button>
                            <el-button type="primary" plain class="w-full" :icon="TopRight" @click="openInNewTab(tech.share_url)">Abrir en nueva pestaña</el-button>
                        </template>
                        <el-alert
                            v-else
                            title="Sin tareas asignadas"
                            type="warning"
                            description="Para poder compartir la orden de trabajo, primero asigna al menos una tarea a este técnico."
                            show-icon
                            :closable="false"
                            class="!text-xs"
                        />
                        <el-divider class="!my-2" />
                        <el-button v-if="can('tickets.tasks.create')" type="primary" plain class="w-full" :icon="Plus" @click="openCreateModalForTech(tech.id)">
                            Nueva tarea
                        </el-button>
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
                                <span>{{ getTechnicianLabel(task.assignee) }}</span>
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
                        <div class="mt-3">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-xs font-bold text-gray-500 uppercase">Evidencias:</span>
                                <span v-if="can('tickets.tasks.edit') && task.media?.length > 1" class="text-[10px] text-gray-400">— arrastra para reordenar</span>
                            </div>
                            <draggable
                                v-if="task.media && task.media.length > 0"
                                v-model="task.media"
                                item-key="id"
                                ghost-class="evidence-ghost"
                                chosen-class="evidence-chosen"
                                drag-class="evidence-drag"
                                :animation="250"
                                :force-fallback="true"
                                @end="reorderEvidence(task)"
                                class="flex flex-wrap gap-3 items-start"
                            >
                                <template #item="{ element: media, index }">
                                    <div
                                        class="evidence-item"
                                        :class="{ 'cursor-grab': can('tickets.tasks.edit'), 'cursor-default': !can('tickets.tasks.edit') }"
                                    >
                                        <div v-if="can('tickets.tasks.edit')" class="evidence-handle" title="Arrastrar para reordenar">
                                            <el-icon :size="12"><Rank /></el-icon>
                                        </div>
                                        <div class="evidence-thumb" @click="showImage(media.original_url)">
                                            <img
                                                v-if="media.mime_type?.startsWith('image/')"
                                                :src="media.original_url"
                                                class="w-full h-full object-cover"
                                            />
                                            <div
                                                v-else-if="media.mime_type?.startsWith('video/')"
                                                class="w-full h-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center"
                                            >
                                                <el-icon class="text-gray-500 dark:text-gray-400" :size="20"><VideoPlay /></el-icon>
                                            </div>
                                        </div>
                                        <span class="evidence-index">{{ index + 1 }}</span>
                                        <el-button
                                            v-if="can('tickets.tasks.edit')"
                                            size="small"
                                            type="danger"
                                            :icon="Delete"
                                            circle
                                            class="evidence-delete"
                                            @click.stop="deleteEvidence(media.id)"
                                        />
                                    </div>
                                </template>
                            </draggable>
                            <div v-if="can('tickets.tasks.edit')" class="flex items-center gap-2 mt-2">
                                <el-upload :show-file-list="false" :auto-upload="false" :on-change="(f) => handleEvidenceUpload(f, task)" accept="image/*,video/mp4,video/quicktime,video/x-msvideo,video/x-matroska" multiple>
                                    <el-button size="small" icon="Camera" plain>Seleccionar archivos</el-button>
                                </el-upload>
                                <el-button
                                    v-if="pendingEvidence[task.id]?.length > 0"
                                    size="small"
                                    type="success"
                                    :icon="Upload"
                                    @click="flushEvidenceUpload(task)"
                                    :loading="uploadingTasks[task.id]"
                                >
                                    Subir ({{ pendingEvidence[task.id].length }})
                                </el-button>
                            </div>
                        </div>

                        <!-- Comentarios del técnico -->
                        <div v-if="task.technician_notes" class="mt-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3">
                            <div class="flex items-start gap-2">
                                <el-icon class="text-amber-500 mt-0.5 shrink-0" :size="16"><Warning /></el-icon>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold text-amber-700 dark:text-amber-400 uppercase mb-1">Notas del técnico</p>
                                    <p class="text-sm text-amber-800 dark:text-amber-300 whitespace-pre-wrap">{{ task.technician_notes }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <el-empty v-else description="No hay tareas registradas" />

        <!-- MODAL TAREA -->
        <TaskFormModal
            v-model="showTaskModal"
            :ticket-id="ticket.id"
            :users="users"
            :task="editingTask"
            @saved="handleTaskSaved"
        />

        <!-- MODAL INTEGRAR TÉCNICOS -->
        <IntegrateTechniciansModal
            v-model="showIntegrateTechsModal"
            :ticket="ticket"
            :users="users"
            @saved="handleIntegrateSaved"
        />

        <el-image-viewer v-if="previewVisible" :url-list="[previewImage]" @close="previewVisible = false" />
    </div>
</template>

<style scoped>
/* ── Evidence item (draggable card) ── */
.evidence-item {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 88px;
    padding: 4px;
    border-radius: 8px;
    border: 2px solid transparent;
    background: #f9fafb;
    transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
    user-select: none;
}

.dark .evidence-item {
    background: #1e1e20;
}

.evidence-item:hover {
    border-color: #d1d5db;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.dark .evidence-item:hover {
    border-color: #52525b;
}

.evidence-handle {
    position: absolute;
    top: -6px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10;
    background: #fff;
    border-radius: 9999px;
    padding: 2px;
    border: 1px solid #d1d5db;
    color: #9ca3af;
    opacity: 0;
    transition: opacity 0.15s, color 0.15s;
    cursor: grab;
    line-height: 0;
}

.dark .evidence-handle {
    background: #3f3f46;
    border-color: #52525b;
    color: #71717a;
}

.evidence-item:hover .evidence-handle {
    opacity: 1;
}

.evidence-handle:active {
    cursor: grabbing;
}

.evidence-thumb {
    width: 72px;
    height: 72px;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    cursor: pointer;
    flex-shrink: 0;
}

.dark .evidence-thumb {
    border-color: #3f3f46;
}

.evidence-thumb img {
    display: block;
}

.evidence-index {
    position: absolute;
    bottom: -4px;
    left: 50%;
    transform: translateX(-50%);
    background: #f26c17;
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    line-height: 1;
    padding: 2px 6px;
    border-radius: 9999px;
    z-index: 10;
}

.evidence-delete {
    position: absolute !important;
    top: -6px !important;
    right: -6px !important;
    padding: 0 !important;
    min-width: 0 !important;
    width: 16px !important;
    height: 16px !important;
    font-size: 10px !important;
    opacity: 0;
    transition: opacity 0.15s;
    z-index: 10;
}

.evidence-item:hover .evidence-delete {
    opacity: 1;
}
</style>

<style>
/* ── vuedraggable state classes (must be unscoped) ── */
.evidence-chosen {
    opacity: 0.4;
    border-style: dashed !important;
    border-color: #f26c17 !important;
}

.evidence-ghost {
    opacity: 1 !important;
    border: 2px dashed #f26c17 !important;
    background: rgba(242, 108, 23, 0.08) !important;
    border-radius: 8px;
    min-width: 88px;
    min-height: 104px;
}

.evidence-ghost .evidence-thumb,
.evidence-ghost .evidence-handle,
.evidence-ghost .evidence-index,
.evidence-ghost .evidence-delete {
    visibility: hidden;
}

.evidence-drag {
    opacity: 0.3;
}
</style>