<script setup>
import { ref, reactive, computed } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox, ElNotification } from 'element-plus';
import { 
    Check, Share, CopyDocument, ChatDotSquare, Edit, Delete, VideoPlay, 
    Calendar, ZoomIn, Camera, Plus, Link as IconLink, StarFilled, Warning,
    Phone, Message, TopRight, Upload
} from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';
import QuickTechnicianModal from './QuickTechnicianModal.vue';
import axios from 'axios';

const { can } = usePermissions();

const props = defineProps({
    ticket: { type: Object, required: true },
    users: { type: Array, default: () => [] }
});

// --- GESTIÓN DE TÉCNICOS LOCALES ---
// Solo mostrar usuarios que tengan relación technician (no admins/asesores)
const localUsers = ref([...props.users.filter(u => u.technician)]);

// --- HELPERS DE PAGOS A TÉCNICOS ---
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: props.ticket.budget?.currency || 'MXN',
    }).format(value || 0);
};

const totalTechnicianAmount = computed(() => {
    if (!props.ticket.budget?.concepts) return 0;
    return props.ticket.budget.concepts
        .filter(c => c.paid_to_technician)
        .reduce((sum, c) => sum + parseFloat(c.amount), 0);
});

const totalTechnicianPaid = computed(() => {
    if (!props.ticket.budget?.technician_payments) return 0;
    return props.ticket.budget.technician_payments.reduce((sum, p) => sum + parseFloat(p.amount), 0);
});

const technicianPaymentProgress = computed(() => {
    if (totalTechnicianAmount.value <= 0) return 100;
    return Math.min(Math.round((totalTechnicianPaid.value / totalTechnicianAmount.value) * 100), 100);
});

// --- GESTIÓN DE PAGOS A TÉCNICOS ---
const showPaymentModal = ref(false);
const paymentUploadRef = ref(null);
const selectedTechForPayment = ref(null);

const paymentForm = useForm({
    user_id: null,
    amount: 0,
    payment_date: new Date().toISOString().split('T')[0],
    payment_method: 'Transferencia',
    reference: '',
    notes: '',
    proof: null,
});

const openPaymentModal = (tech) => {
    selectedTechForPayment.value = tech;
    paymentForm.reset();
    paymentForm.user_id = tech.id;
    if (paymentUploadRef.value) paymentUploadRef.value.clearFiles();
    showPaymentModal.value = true;
};

const handlePaymentProofChange = (file) => {
    paymentForm.proof = file.raw;
};

const submitPayment = () => {
    paymentForm.post(route('budgets.technician-payments.store', props.ticket.budget?.id), {
        onSuccess: () => {
            showPaymentModal.value = false;
            paymentForm.reset();
            ElMessage.success('Pago a técnico registrado');
        },
        onError: () => ElMessage.error('Error al registrar pago'),
        forceFormData: true,
    });
};

const deleteTechPayment = (paymentId) => {
    ElMessageBox.confirm('¿Eliminar este registro de pago?', 'Confirmar', {
        type: 'warning',
    }).then(() => {
        router.delete(route('budgets.technician-payments.destroy', paymentId), {
            onSuccess: () => ElMessage.success('Pago eliminado'),
        });
    }).catch(() => {});
};

const showQuickTechModal = ref(false);

const handleTechCreated = (newUser) => {
    localUsers.value.push(newUser);
    taskForm.user_id = newUser.id;
};

// --- INTEGRAR TÉCNICOS AL TICKET ---
const showIntegrateTechsModal = ref(false);
const isIntegrating = ref(false);

const integrateForm = reactive({
    technicians: [...(props.ticket.technicians || []).map(Number)],
    assistant_technicians: [...(props.ticket.assistant_technicians || []).map(Number)],
});

const integrateEncargados = computed(() => {
    return localUsers.value.filter(u => u.technician?.level === 'Encargado');
});

const integrateAssistants = computed(() => {
    return localUsers.value.filter(u => u.technician?.level === 'Auxiliar/Ayudante');
});

const openIntegrateModal = () => {
    integrateForm.technicians = [...(props.ticket.technicians || []).map(Number)];
    integrateForm.assistant_technicians = [...(props.ticket.assistant_technicians || []).map(Number)];
    showIntegrateTechsModal.value = true;
};

const submitIntegrate = () => {
    isIntegrating.value = true;
    router.put(route('tickets.update-technicians', props.ticket.id), {
        technicians: integrateForm.technicians,
        assistant_technicians: integrateForm.assistant_technicians,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showIntegrateTechsModal.value = false;
            ElMessage.success('Técnicos integrados correctamente.');
            router.reload({ only: ['ticket'] });
        },
        onError: () => {
            ElMessage.error('Error al integrar técnicos.');
        },
        onFinish: () => {
            isIntegrating.value = false;
        },
    });
};

// --- COMPUTED: TÉCNICOS ASIGNADOS (incluye encargados y auxiliares) ---
const assignedTechnicians = computed(() => {
    const techs = new Map();

    // Collect all technician IDs from ticket fields and tasks
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

        techs.set(userId, {
            id: userId,
            name,
            email,
            profile_photo_url: photo,
            share_url: shareUrl,
            phone,
            level,
            status: userFullInfo?.technician?.status || 'N/A',
            rating_avg: userFullInfo?.technician?.rating_avg || 0,
            task_count: taskCount,
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

const openCreateModalForTech = (techId) => {
    isEditing.value = false;
    taskForm.reset();
    taskForm.clearErrors();
    taskForm.user_id = techId;
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
// Per-task evidence tracking: key = task.id, value = File[]
const pendingEvidence = reactive({});
const uploadingTasks = reactive({});

const handleEvidenceUpload = (file, task) => {
    if (!pendingEvidence[task.id]) {
        pendingEvidence[task.id] = [];
    }
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

const openInNewTab = (url) => {
    window.open(url, '_blank');
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

// --- VISOR DE COMPROBANTE DE PAGO ---
const proofPreviewVisible = ref(false);
const proofPreviewUrl = ref('');
const showPaymentProof = (pay) => {
    if (pay.media && pay.media.length > 0) {
        proofPreviewUrl.value = pay.media[0].original_url;
        proofPreviewVisible.value = true;
    }
};
</script>

<template>
    <div class="py-4">
        <!-- SECCIÓN DE PAGO A TÉCNICO -->
        <div v-if="totalTechnicianAmount > 0" class="mb-8">
            <div class="bg-gray-50 dark:bg-[#252529] rounded-lg p-4 border border-gray-200 dark:border-[#3f3f46]">
                <div class="flex justify-between items-center mb-3">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                        <el-icon><Money /></el-icon> Pago a técnico
                    </h4>
                    <span class="text-xs font-bold">{{ formatCurrency(totalTechnicianPaid) }} / {{ formatCurrency(totalTechnicianAmount) }}</span>
                </div>
                <el-progress
                    :percentage="technicianPaymentProgress"
                    :status="technicianPaymentProgress >= 100 ? 'success' : ''"
                    :stroke-width="14"
                    class="mb-4"
                >
                    <span class="text-xs font-bold">{{ technicianPaymentProgress }}%</span>
                </el-progress>

                <!-- Técnicos con pagos -->
                <div v-for="tech in assignedTechnicians" :key="tech.id" class="flex items-center justify-between p-3 bg-white dark:bg-[#1e1e20] rounded-lg border border-gray-200 dark:border-gray-700 mb-2 last:mb-0">
                    <div class="flex items-center gap-2">
                        <el-avatar :size="28" :src="tech.profile_photo_url">{{ tech.name.charAt(0) }}</el-avatar>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ tech.name }}</span>
                    </div>
                    <el-button type="success" size="small" plain @click="openPaymentModal(tech)">
                        Pagar
                    </el-button>
                </div>

                <!-- Historial de pagos -->
                <div v-if="ticket.budget?.technician_payments?.length" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-[11px] font-bold text-gray-400 uppercase mb-2">Historial de pagos</p>
                    <div v-for="pay in ticket.budget.technician_payments" :key="pay.id" class="flex items-center justify-between py-1.5 text-sm border-b border-gray-100 dark:border-gray-800 last:border-0">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-green-600">{{ formatCurrency(pay.amount) }}</span>
                                <span class="text-xs text-gray-400">{{ formatDateLong(pay.payment_date) }}</span>
                                <el-tag v-if="pay.reference" size="small" type="info" class="scale-75">{{ pay.reference }}</el-tag>
                            </div>
                            <p v-if="pay.notes" class="text-xs text-gray-500 mt-0.5 truncate" :title="pay.notes">{{ pay.notes }}</p>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <el-tooltip v-if="pay.media?.length" content="Ver comprobante">
                                <el-button circle size="small" type="info" plain icon="ZoomIn" @click="showPaymentProof(pay)" />
                            </el-tooltip>
                            <el-button circle size="small" type="danger" plain icon="Delete" @click="deleteTechPayment(pay.id)" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
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
                        <!-- Detalles de Desempeño y Estatus -->
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
                            <div v-for="media in task.media" :key="media.id" class="relative group">
                                <!-- Image -->
                                <div v-if="media.mime_type?.startsWith('image/')" class="w-12 h-12 rounded overflow-hidden border cursor-pointer" @click="showImage(media.original_url)">
                                    <img :src="media.original_url" class="w-full h-full object-cover" />
                                </div>
                                <!-- Video -->
                                <div v-else-if="media.mime_type?.startsWith('video/')" class="w-12 h-12 rounded overflow-hidden border cursor-pointer bg-gray-100 dark:bg-gray-800 flex items-center justify-center" @click="showImage(media.original_url)">
                                    <el-icon class="text-gray-500 dark:text-gray-400" :size="22"><VideoPlay /></el-icon>
                                </div>
                                <el-button
                                    v-if="can('tickets.tasks.edit')"
                                    size="small"
                                    type="danger"
                                    :icon="Delete"
                                    circle
                                    class="!absolute -top-1.5 -right-1.5 !p-0 !min-w-0 !w-4 !h-4 !text-[10px] opacity-0 group-hover:opacity-100 transition-opacity"
                                    @click.stop="deleteEvidence(media.id)"
                                />
                            </div>
                            <div v-if="can('tickets.tasks.edit')" class="flex items-center gap-2">
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
                            <!-- Grupo: Encargados -->
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
                            <!-- Grupo: Auxiliares / Ayudantes -->
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

        <!-- MODAL Gestión TÉCNICOS -->
        <el-dialog
            v-model="showIntegrateTechsModal"
            title="Gestión de técnicos al ticket"
            width="500px"
            destroy-on-close
        >
            <p class="text-sm text-gray-500 mb-4">
                Selecciona los técnicos que participarán en este ticket. Puedes agregar tanto encargados como auxiliares.
            </p>
            <el-form label-position="top">
                <el-form-item label="Técnicos encargados">
                    <el-select
                        v-model="integrateForm.technicians"
                        multiple
                        placeholder="Seleccionar encargados..."
                        class="w-full"
                        filterable
                        collapse-tags
                        collapse-tags-tooltip
                    >
                        <el-option
                            v-for="tech in integrateEncargados"
                            :key="tech.id"
                            :label="tech.name"
                            :value="tech.id"
                        />
                    </el-select>
                </el-form-item>

                <el-form-item label="Técnicos auxiliares / ayudantes">
                    <el-select
                        v-model="integrateForm.assistant_technicians"
                        multiple
                        placeholder="Seleccionar auxiliares..."
                        class="w-full"
                        filterable
                        collapse-tags
                        collapse-tags-tooltip
                    >
                        <el-option
                            v-for="tech in integrateAssistants"
                            :key="tech.id"
                            :label="tech.name"
                            :value="tech.id"
                        />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showIntegrateTechsModal = false">Cancelar</el-button>
                <el-button type="primary" color="#f26c17" @click="submitIntegrate" :loading="isIntegrating">
                    Guardar cambios
                </el-button>
            </template>
        </el-dialog>

        <el-image-viewer v-if="previewVisible" :url-list="[previewImage]" @close="previewVisible = false" />
    </div>

    <!-- MODAL PAGO A TÉCNICO -->
    <el-dialog
        v-model="showPaymentModal"
        :title="`Pago a técnico: ${selectedTechForPayment?.name || ''}`"
        width="450px"
        append-to-body
    >
        <el-form :model="paymentForm" label-position="top">
            <el-alert
                title="Importante"
                type="info"
                description="El comprobante es obligatorio para los pagos a personal técnico."
                show-icon
                :closable="false"
                class="mb-4"
            />

            <el-form-item label="Monto a pagar">
                <el-input-number v-model="paymentForm.amount" :min="0.01" :precision="2" class="!w-full">
                    <template #prefix>$</template>
                </el-input-number>
            </el-form-item>

            <div class="grid grid-cols-2 gap-4">
                <el-form-item label="Fecha">
                    <el-date-picker v-model="paymentForm.payment_date" type="date" class="!w-full" format="DD/MM/YYYY" value-format="YYYY-MM-DD" />
                </el-form-item>
                <el-form-item label="Método">
                    <el-select v-model="paymentForm.payment_method" class="w-full">
                        <el-option label="Transferencia" value="Transferencia" />
                        <el-option label="Efectivo" value="Efectivo" />
                        <el-option label="Nómina" value="Nómina" />
                    </el-select>
                </el-form-item>
            </div>

            <el-form-item label="Referencia bancaria">
                <el-input v-model="paymentForm.reference" placeholder="Ej. SPEI-123456" />
            </el-form-item>

            <el-form-item label="Comprobante de pago (Obligatorio)" :error="paymentForm.errors.proof">
                <el-upload
                    ref="paymentUploadRef"
                    class="w-full"
                    :auto-upload="false"
                    :limit="1"
                    :on-change="handlePaymentProofChange"
                    accept="image/*,.pdf"
                >
                    <template #trigger>
                        <el-button type="primary" plain icon="Upload">Adjuntar comprobante</el-button>
                    </template>
                    <template #tip>
                        <div class="el-upload__tip">Archivos PDF o Imagen (Máx. 5MB)</div>
                    </template>
                </el-upload>
            </el-form-item>
        </el-form>
        <template #footer>
            <span class="dialog-footer">
                <el-button @click="showPaymentModal = false">Cancelar</el-button>
                <el-button type="success" @click="submitPayment" :loading="paymentForm.processing">
                    Registrar pago
                </el-button>
            </span>
        </template>
    </el-dialog>

    <!-- VISOR DE COMPROBANTE DE PAGO -->
    <el-image-viewer v-if="proofPreviewVisible" :url-list="[proofPreviewUrl]" @close="proofPreviewVisible = false" />
</template>