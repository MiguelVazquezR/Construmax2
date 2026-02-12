<template>
  <Head :title="`Orden de Trabajo #${ticket.id}`" />
  
  <div class="job-order-container">
    
    <!-- 1. AVISO DE SEGURIDAD (IMPORTANTE) -->
    <el-alert
        title="⚠️ AVISO DE SEGURIDAD INDUSTRIAL"
        type="warning"
        effect="dark"
        :closable="false"
        class="mb-6 sticky-header"
    >
        <div class="text-sm">
            <p class="font-bold mb-1">REGLAS OBLIGATORIAS PARA INICIAR LABORES:</p>
            <ul class="list-disc pl-4 space-y-1">
                <li>Usar equipo de protección personal (EPP) completo.</li>
                <li>Verificar riesgos eléctricos o de altura.</li>
                <li>Reportar condiciones inseguras antes de iniciar.</li>
            </ul>
        </div>
    </el-alert>

    <!-- HEADER DEL TICKET -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 mb-6">
        <div class="flex justify-between items-start mb-4">
            <div>
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Ticket #{{ ticket.id }}</span>
                <h1 class="text-xl font-bold text-gray-900 mt-1">{{ ticket.budget?.customer?.name }}</h1>
                <p class="text-sm text-gray-600">{{ ticket.budget?.customer?.business_name }}</p>
            </div>
            <el-tag effect="dark" :type="getPriorityColor(ticket.priority)">Prioridad {{ ticket.priority }}</el-tag>
        </div>
        
        <el-descriptions :column="1" border size="small">
            <el-descriptions-item label="Responsable">
                <div class="flex items-center gap-2">
                    <el-avatar :size="20" :src="technician.profile_photo_url">{{ technician.name.charAt(0) }}</el-avatar>
                    {{ technician.name }}
                </div>
            </el-descriptions-item>
            <el-descriptions-item label="Instrucciones">
                {{ ticket.instructions || 'Sin instrucciones generales.' }}
            </el-descriptions-item>
        </el-descriptions>
    </div>

    <!-- LISTA DE TAREAS SECUENCIALES -->
    <div class="space-y-8 relative">
        <!-- Línea conectora vertical -->
        <div class="absolute left-4 top-4 bottom-4 w-0.5 bg-gray-200 z-0"></div>

        <div 
            v-for="(task, index) in tasks" 
            :key="task.id" 
            class="relative z-10 pl-12 transition-all duration-300"
            :class="{ 'opacity-60 grayscale': isTaskLocked(index) }"
        >
            <!-- Badge Circular del Paso -->
            <div 
                class="absolute left-0 top-0 w-8 h-8 rounded-full flex items-center justify-center font-bold text-white shadow-sm border-2 border-white"
                :class="getStepColorClass(task, index)"
            >
                <el-icon v-if="task.status === 'Completada'"><Check /></el-icon>
                <span v-else>{{ index + 1 }}</span>
            </div>

            <!-- Tarjeta de Tarea -->
            <el-card class="box-card" :shadow="isTaskLocked(index) ? 'never' : 'hover'">
                <template #header>
                    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-2">
                        <span class="font-bold text-gray-800 leading-tight">{{ task.name }}</span>
                        <el-tag size="small" :type="getStatusType(task.status)" class="w-fit">{{ task.status }}</el-tag>
                    </div>
                </template>

                <div class="text-sm text-gray-600 mb-4 whitespace-pre-line">
                    {{ task.description || 'Sin detalles adicionales.' }}
                </div>
                
                <div class="text-xs text-gray-500 mb-4 flex flex-col sm:flex-row gap-2 sm:gap-4">
                    <span class="flex items-center gap-1"><el-icon><Calendar /></el-icon> {{ formatDate(task.start_date) }}</span>
                    <span class="flex items-center gap-1"><el-icon><Timer /></el-icon> Límite: {{ formatDate(task.due_date) }}</span>
                </div>

                <!-- SECCIÓN DE EVIDENCIAS -->
                <div class="bg-gray-50 rounded p-3 mb-4 border border-gray-100">
                    <p class="text-xs font-bold text-gray-500 mb-3 uppercase flex items-center gap-1">
                        <el-icon><Camera /></el-icon> Evidencias Fotográficas
                    </p>
                    
                    <!-- Galería -->
                    <div v-if="task.media && task.media.length > 0" class="flex flex-wrap gap-3 mb-3">
                        <div 
                            v-for="(img, imgIndex) in task.media" 
                            :key="img.id" 
                            class="relative w-20 h-20 rounded-lg overflow-hidden border border-gray-200 shadow-sm bg-white"
                        >
                            <el-image 
                                :src="img.original_url" 
                                class="w-full h-full" 
                                fit="cover"
                                :preview-src-list="getTaskImageUrls(task)"
                                :initial-index="imgIndex"
                                preview-teleported
                                hide-on-click-modal
                            >
                                <template #error>
                                    <div class="flex items-center justify-center w-full h-full bg-gray-100 text-gray-400">
                                        <el-icon><icon-picture /></el-icon>
                                    </div>
                                </template>
                            </el-image>

                            <!-- Botón borrar MEJORADO -->
                            <el-popconfirm
                                v-if="task.status !== 'Completada'"
                                title="¿Eliminar?"
                                confirm-button-text="Sí"
                                cancel-button-text="No"
                                width="160"
                                @confirm="deleteEvidence(img.delete_url)"
                            >
                                <template #reference>
                                    <div class="absolute top-1 right-1 bg-white text-red-500 rounded-full p-1 shadow-md cursor-pointer hover:bg-red-50 transition-colors z-10 flex items-center justify-center w-6 h-6">
                                        <el-icon :size="14"><Delete /></el-icon>
                                    </div>
                                </template>
                            </el-popconfirm>
                        </div>
                    </div>
                    <div v-else class="text-xs text-gray-400 italic mb-3 ml-1">
                        No hay fotos adjuntas.
                    </div>

                    <!-- Upload Button -->
                    <el-upload
                        v-if="task.status !== 'Completada' && !isTaskLocked(index)"
                        action="#"
                        :auto-upload="true"
                        :show-file-list="false"
                        :http-request="(opts) => handleUpload(opts, task)"
                        accept="image/*"
                    >
                        <el-button size="small" :icon="Camera" :loading="uploadingTaskId === task.id" plain type="primary">
                            Adjuntar Foto
                        </el-button>
                    </el-upload>
                </div>

                <!-- ACCIÓN PRINCIPAL -->
                <el-button 
                    v-if="!isTaskLocked(index)"
                    :type="task.status === 'Completada' ? 'warning' : 'success'" 
                    class="w-full !py-5"
                    :icon="task.status === 'Completada' ? RefreshLeft : Select"
                    @click="toggleStatus(task)"
                    :loading="togglingTaskId === task.id"
                >
                    {{ task.status === 'Completada' ? 'Reabrir tarea' : 'Finalizar tarea' }}
                </el-button>
                <div v-else class="text-xs text-orange-500 italic flex items-center gap-1 p-2 bg-orange-50 rounded border border-orange-100">
                    <el-icon><Lock /></el-icon> Completa la tarea anterior para desbloquear.
                </div>

            </el-card>
        </div>
    </div>

    <!-- 3. RECORDATORIO FINAL (CHECKLIST DE CIERRE) -->
    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-5">
        <h3 class="text-blue-800 font-bold flex items-center gap-2 mb-3">
            <el-icon><DocumentChecked /></el-icon> REQUISITOS PARA LIBERACIÓN DE PAGO
        </h3>
        <ul class="text-sm text-blue-700 space-y-2 list-none">
            <li class="flex items-start gap-2">
                <el-icon class="mt-1"><Check /></el-icon> 
                <span>Subir evidencias claras del <strong>Antes, Durante y Después</strong>.</span>
            </li>
            <li class="flex items-start gap-2">
                <el-icon class="mt-1"><Check /></el-icon> 
                <span>Firma de conformidad del cliente (Hoja de servicio).</span>
            </li>
            <li class="flex items-start gap-2">
                <el-icon class="mt-1"><Check /></el-icon> 
                <span>Área limpia y libre de escombro.</span>
            </li>
        </ul>
        <div class="mt-4 text-xs text-blue-500 font-semibold border-t border-blue-200 pt-2">
            * Construmax de Occidente validará estos puntos antes de procesar la estimación.
        </div>
    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { 
    Check, 
    RefreshLeft, 
    Camera,
    Delete,
    Calendar,
    Timer,
    Lock,
    Select,
    DocumentChecked,
    Picture as IconPicture
} from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import dayjs from 'dayjs'; 
import 'dayjs/locale/es';

// Configurar locale español globalmente para dayjs
dayjs.locale('es');

const props = defineProps({
  ticket: Object,
  technician: Object,
  tasks: Array, 
});

const togglingTaskId = ref(null);
const uploadingTaskId = ref(null);

// Lógica de Bloqueo Secuencial
const isTaskLocked = (index) => {
    if (index === 0) return false;
    const previousTask = props.tasks[index - 1];
    return previousTask.status !== 'Completada';
};

// --- HELPERS ---

const getStepColorClass = (task, index) => {
    if (task.status === 'Completada') return 'bg-green-500 border-green-500';
    if (isTaskLocked(index)) return 'bg-gray-300 border-gray-300 text-gray-500';
    return 'bg-blue-600 border-blue-600'; 
};

const getStatusType = (status) => {
    const map = { 'Pendiente': 'info', 'En proceso': 'primary', 'Completada': 'success' };
    return map[status] || 'info';
};

const getPriorityColor = (priority) => {
    const map = { 'Alta': 'danger', 'Media': 'warning', 'Baja': 'success' };
    return map[priority] || 'info';
};

// Formato Solicitado: "Jueves 12 febrero, 9:00 am"
const formatDate = (date) => {
    if (!date) return '--:--';
    // dddd=Día nombre, D=Día numero, MMMM=Mes nombre, h:mm a=hora am/pm
    const formatted = dayjs(date).format('dddd D MMMM, h:mm a');
    // Capitalizar primera letra
    return formatted.charAt(0).toUpperCase() + formatted.slice(1);
};

// Helper para obtener todas las URLs de imágenes de una tarea específica
// Esto permite navegar (next/prev) entre todas las fotos de ESA tarea en el visor
const getTaskImageUrls = (task) => {
    if (!task.media) return [];
    return task.media.map(m => m.original_url);
};

// --- ACCIONES ---

const toggleStatus = (task) => {
    togglingTaskId.value = task.id;
    router.put(task.urls.toggle, {}, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success(task.status === 'Completada' ? 'Tarea reabierta' : 'Tarea finalizada');
            togglingTaskId.value = null;
        },
        onError: () => {
            ElMessage.error('Error al actualizar');
            togglingTaskId.value = null;
        }
    });
};

const handleUpload = (options, task) => {
    const { file } = options;
    const isImage = file.type.startsWith('image/');
    // Aumentamos ligeramente el límite a 15MB por si acaso
    const isLt15M = file.size / 1024 / 1024 < 15;

    if (!isImage || !isLt15M) {
        ElMessage.error('Solo imágenes menores a 15MB');
        return;
    }

    uploadingTaskId.value = task.id;
    const form = useForm({ file: file });
    
    form.post(task.urls.evidence, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Evidencia subida');
            uploadingTaskId.value = null;
        },
        onError: () => {
            ElMessage.error('Error al subir imagen');
            uploadingTaskId.value = null;
        }
    });
};

const deleteEvidence = (url) => {
    router.delete(url, {
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Imagen eliminada')
    });
};
</script>

<style scoped>
.job-order-container {
  max-width: 600px;
  margin: 0 auto;
  padding: 15px;
  background-color: #f8fafc;
  min-height: 100vh;
  font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

.sticky-header {
    position: sticky;
    top: 0;
    z-index: 50;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

:deep(.el-card__header) {
    padding: 12px 15px;
    background-color: #fff;
    border-bottom: 1px solid #f1f5f9;
}

:deep(.el-card__body) {
    padding: 15px;
}
</style>