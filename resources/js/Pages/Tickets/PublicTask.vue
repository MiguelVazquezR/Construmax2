<template>
  <Head :title="`Orden de Trabajo #${ticket.id}`" />
  
  <div class="min-h-screen bg-gray-50/50">
    <div class="max-w-3xl mx-auto p-4 sm:p-6 lg:p-8">
      
      <!-- 1. AVISO DE SEGURIDAD (IMPORTANTE) -->
      <div class="sticky top-4 z-50 mb-8">
        <div class="bg-orange-50 border border-orange-200 rounded-xl shadow-sm p-4 backdrop-blur-sm bg-opacity-95">
          <div class="flex items-start gap-3">
            <el-icon class="text-orange-500 mt-0.5" :size="20"><Warning /></el-icon>
            <div>
              <h3 class="text-sm font-bold text-orange-800 mb-2 uppercase tracking-wide">Aviso de Seguridad Industrial</h3>
              <p class="text-xs font-semibold text-orange-700 mb-2">Reglas obligatorias para iniciar labores:</p>
              <ul class="text-xs text-orange-700 list-disc pl-4 space-y-1">
                  <li>Usar equipo de protección personal (EPP) completo.</li>
                  <li>Verificar riesgos eléctricos o de altura.</li>
                  <li>Reportar condiciones inseguras antes de iniciar.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- HEADER DEL TICKET -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-10 relative overflow-hidden">
        <!-- Decoración sutil de fondo -->
        <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-gray-50 rounded-full opacity-50 pointer-events-none"></div>
        
        <div class="flex flex-col sm:flex-row justify-between items-start gap-4 mb-6 relative z-10">
            <div>
                <div class="flex items-center gap-3 mb-2">
                  <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Ticket {{ ticket.folio }}</span>
                  <el-tag effect="light" :type="getPriorityColor(ticket.priority)" size="small" class="!rounded-full !border-none !px-3 font-semibold">
                    Prioridad {{ ticket.priority }}
                  </el-tag>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight leading-tight">{{ ticket.budget?.customer?.name }}</h1>
                <p class="text-sm text-gray-500 mt-1 font-medium">{{ ticket.budget?.customer?.business_name }}</p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-6 border-t border-gray-100 relative z-10">
          <div>
            <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Técnico asignado</span>
            <div class="flex items-center gap-3">
                <el-avatar :size="32" :src="technician.profile_photo_url" class="border border-gray-100 shadow-sm">{{ technician.name.charAt(0) }}</el-avatar>
                <span class="text-sm font-semibold text-gray-700">{{ technician.name }}</span>
            </div>
          </div>
          <div>
            <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Instrucciones Generales</span>
            <p class="text-sm text-gray-600 leading-relaxed bg-gray-50 p-3 rounded-lg border border-gray-100">
              {{ ticket.instructions || 'No se proporcionaron instrucciones específicas para esta orden.' }}
            </p>
          </div>
        </div>
      </div>

      <!-- INFORMACIÓN DE PAGOS AL TÉCNICO -->
      <div v-if="ticket.budget?.technician_payments?.length > 0 || totalTechnicianAmount > 0" class="mb-8">
        <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
          <el-icon class="text-green-500"><Money /></el-icon> Información de pagos
        </h2>
        
        <div v-if="totalTechnicianAmount > 0" class="bg-white rounded-xl border border-gray-100 p-4 mb-4 shadow-sm">
          <div class="flex justify-between items-center mb-2">
            <span class="text-sm font-medium text-gray-600">Progreso de pagos</span>
            <span class="text-sm font-bold">{{ formatPaymentCurrency(totalTechnicianPaid) }} / {{ formatPaymentCurrency(totalTechnicianAmount) }}</span>
          </div>
          <el-progress
            :percentage="technicianPaymentProgress"
            :status="technicianPaymentProgress >= 100 ? 'success' : ''"
            :stroke-width="14"
          >
            <span class="text-xs font-bold">{{ technicianPaymentProgress }}%</span>
          </el-progress>
        </div>

        <div v-if="ticket.budget?.technician_payments?.length > 0" class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm">
          <h3 class="text-sm font-bold text-gray-700 mb-3">Historial de pagos</h3>
          <div class="space-y-2">
            <div v-for="pay in ticket.budget.technician_payments" :key="pay.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-100 text-sm">
              <div class="flex items-center gap-2">
                <span class="font-bold text-green-600">{{ formatPaymentCurrency(pay.amount) }}</span>
                <span class="text-xs text-gray-400">{{ dayjs(pay.payment_date).format('DD MMM YYYY') }}</span>
                <el-tag v-if="pay.reference" size="small" type="info" class="scale-75">{{ pay.reference }}</el-tag>
              </div>
              <div>
                <el-tooltip v-if="pay.media?.length" content="Ver comprobante">
                  <el-button circle size="small" icon="Document" @click="showImage(pay.media[0].original_url)" />
                </el-tooltip>
              </div>
            </div>
          </div>
        </div>
      </div>

       <!-- RECURSOS DEL TICKET (Imágenes de apoyo subidas desde la oficina) -->
      <div v-if="ticket.media && ticket.media.length > 0" class="mb-8">
        <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
          <el-icon class="text-primary"><IconPicture /></el-icon> Recursos e imágenes de apoyo
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
          <div v-for="file in ticket.media" :key="file.id" class="border border-gray-200 rounded-lg overflow-hidden bg-white">
            <div class="aspect-video bg-gray-100 flex items-center justify-center">
              <img v-if="file.mime_type?.startsWith('image/')" :src="file.original_url" class="w-full h-full object-cover cursor-pointer" @click="showImage(file.original_url)" />
              <el-icon v-else :size="32" class="text-gray-400"><Document /></el-icon>
            </div>
            <p class="text-xs p-2 truncate text-gray-500">{{ file.file_name }}</p>
          </div>
        </div>
      </div>


      <!-- LISTA DE TAREAS SECUENCIALES -->
      <div class="mb-8">
        <h2 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
          <el-icon class="text-primary"><List /></el-icon> Plan de Trabajo
        </h2>
        
        <div class="space-y-6 relative">
            <!-- Línea conectora vertical sutil -->
            <div class="absolute left-[1.15rem] top-4 bottom-4 w-px bg-gray-200 z-0 hidden sm:block"></div>

            <div 
                v-for="(task, index) in tasks" 
                :key="task.id" 
                class="relative z-10 sm:pl-12 transition-all duration-300 group"
                :class="{ 'opacity-50 grayscale': isTaskLocked(index) }"
            >
                <!-- Badge Circular del Paso -->
                <div 
                    class="hidden sm:flex absolute left-0 top-6 w-10 h-10 rounded-full items-center justify-center font-bold text-sm shadow-sm border-4 border-gray-50 transition-colors"
                    :class="getStepColorClass(task, index)"
                >
                    <el-icon v-if="task.status === 'Completada'" :size="16"><Check /></el-icon>
                    <span v-else>{{ index + 1 }}</span>
                </div>

                <!-- Tarjeta de Tarea -->
                <el-card class="!rounded-2xl !border-gray-100 !shadow-sm hover:!shadow-md transition-shadow" :shadow="isTaskLocked(index) ? 'never' : 'hover'">
                    <template #header>
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b-0 pb-0">
                            <div class="flex items-center gap-3">
                              <span class="sm:hidden flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold text-white" :class="getStepColorClass(task, index).replace('border-4 border-gray-50', '')">
                                <el-icon v-if="task.status === 'Completada'" :size="12"><Check /></el-icon>
                                <span v-else>{{ index + 1 }}</span>
                              </span>
                              <h3 class="text-base font-bold text-gray-800 leading-tight m-0">{{ task.name }}</h3>
                            </div>
                            <el-tag size="small" effect="light" :type="getStatusType(task.status)" class="!rounded-full !px-3 font-medium !border-none bg-opacity-20">{{ task.status }}</el-tag>
                        </div>
                    </template>

                    <div class="text-sm text-gray-600 mb-6 leading-relaxed whitespace-pre-line">
                        {{ task.description || 'Sin detalles adicionales para esta tarea.' }}
                    </div>
                    
                    <div class="flex flex-wrap gap-4 mb-6 pb-6 border-b border-gray-50">
                        <div class="flex items-center gap-2 text-xs font-medium text-gray-500 bg-gray-50/80 px-3 py-1.5 rounded-md">
                          <el-icon class="text-gray-400"><Calendar /></el-icon> 
                          <span>Inicio: {{ formatDate(task.start_date) }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs font-medium text-gray-500 bg-gray-50/80 px-3 py-1.5 rounded-md">
                          <el-icon class="text-gray-400"><Timer /></el-icon> 
                          <span>Límite: {{ formatDate(task.due_date) }}</span>
                        </div>
                    </div>

                    <!-- SECCIÓN DE EVIDENCIAS -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                          <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider flex items-center gap-2">
                              <el-icon><Camera /></el-icon> Evidencias Fotográficas
                          </h4>
                          <el-upload
                              v-if="task.status !== 'Completada' && !isTaskLocked(index)"
                              action="#"
                              :auto-upload="true"
                              :show-file-list="false"
                              :http-request="(opts) => handleUpload(opts, task)"
                              accept="image/*"
                          >
                              <el-button size="small" :icon="Camera" :loading="uploadingTaskId === task.id" plain type="primary" class="!rounded-full">
                                  Subir Foto
                              </el-button>
                          </el-upload>
                        </div>
                        
                        <!-- Galería -->
                        <div v-if="task.media && task.media.length > 0" class="flex flex-wrap gap-3">
                            <div 
                                v-for="(img, imgIndex) in task.media" 
                                :key="img.id" 
                                class="relative w-24 h-24 rounded-xl overflow-hidden border border-gray-100 shadow-sm group/img"
                            >
                                <el-image 
                                    :src="img.original_url" 
                                    class="w-full h-full transition-transform duration-300 group-hover/img:scale-110" 
                                    fit="cover"
                                    :preview-src-list="getTaskImageUrls(task)"
                                    :initial-index="imgIndex"
                                    preview-teleported
                                    hide-on-click-modal
                                >
                                    <template #error>
                                        <div class="flex items-center justify-center w-full h-full bg-gray-50 text-gray-300">
                                            <el-icon :size="24"><icon-picture /></el-icon>
                                        </div>
                                    </template>
                                </el-image>

                                <!-- Botón borrar -->
                                <el-popconfirm
                                    v-if="task.status !== 'Completada'"
                                    title="¿Eliminar foto?"
                                    confirm-button-text="Sí"
                                    cancel-button-text="No"
                                    width="180"
                                    @confirm="deleteEvidence(img.delete_url)"
                                >
                                    <template #reference>
                                        <div class="absolute top-1.5 right-1.5 bg-white/90 backdrop-blur-sm text-red-500 rounded-full p-1.5 shadow-sm cursor-pointer hover:bg-red-50 hover:text-red-600 transition-colors z-10 flex items-center justify-center opacity-0 group-hover/img:opacity-100 sm:opacity-100">
                                            <el-icon :size="14"><Delete /></el-icon>
                                        </div>
                                    </template>
                                </el-popconfirm>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-6 px-4 text-center bg-gray-50 border border-dashed border-gray-200 rounded-xl">
                            <el-icon class="text-gray-300 mb-2" :size="24"><PictureFilled /></el-icon>
                            <span class="text-sm font-medium text-gray-500">Sin evidencias registradas</span>
                            <span class="text-xs text-gray-400 mt-1">Sube fotos del antes, durante y después.</span>
                        </div>
                    </div>

                    <!-- SECCIÓN DE COMENTARIOS DEL TÉCNICO -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider flex items-center gap-2">
                                <el-icon><ChatDotSquare /></el-icon> Notas y comentarios del técnico
                            </h4>
                        </div>
                        <div class="bg-gray-50 rounded-xl border border-gray-100 p-3">
                            <el-input
                                v-model="task.technician_notes"
                                type="textarea"
                                :rows="3"
                                placeholder="Escribe aquí tus notas, observaciones o comentarios sobre esta tarea..."
                                @blur="saveNotes(task)"
                            />
                            <div class="flex justify-end mt-2">
                                <el-button size="small" type="primary" plain @click="saveNotes(task)" :loading="savingNotesId === task.id">
                                    Guardar notas
                                </el-button>
                            </div>
                        </div>
                    </div>

                    <!-- ACCIÓN PRINCIPAL -->
                    <el-button 
                        v-if="!isTaskLocked(index)"
                        :type="task.status === 'Completada' ? 'info' : 'success'" 
                        class="w-full !py-6 !text-base !font-bold !rounded-xl transition-all"
                        :plain="task.status === 'Completada'"
                        :icon="task.status === 'Completada' ? RefreshLeft : Select"
                        @click="toggleStatus(task)"
                        :loading="togglingTaskId === task.id"
                    >
                        {{ task.status === 'Completada' ? 'Reabrir tarea para edición' : 'Marcar tarea como completada' }}
                    </el-button>
                    
                    <div v-else class="flex items-center justify-center gap-2 p-4 bg-gray-50 rounded-xl border border-gray-100 text-sm font-medium text-gray-400">
                        <el-icon><Lock /></el-icon> Completa el paso anterior para desbloquear
                    </div>

                </el-card>
            </div>
        </div>
      </div>

      <!-- 3. RECORDATORIO FINAL (CHECKLIST DE CIERRE) -->
      <div class="bg-blue-50/50 border border-blue-100 rounded-2xl p-6 sm:p-8 mt-12 relative overflow-hidden">
          <div class="absolute -right-6 -top-6 text-blue-100 opacity-50">
            <el-icon :size="120"><DocumentChecked /></el-icon>
          </div>
          
          <div class="relative z-10">
            <h3 class="text-blue-900 text-base font-bold flex items-center gap-2 mb-4">
                <el-icon class="text-blue-500"><List /></el-icon> Requisitos para liberación de pago
            </h3>
            <div class="bg-white rounded-xl p-5 border border-blue-100 shadow-sm mb-4">
              <ul class="text-sm text-blue-800 space-y-4 list-none">
                  <li class="flex items-start gap-3">
                      <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <el-icon :size="12"><Check /></el-icon>
                      </div>
                      <span class="font-medium leading-relaxed">Subir evidencias fotográficas claras del <strong class="text-blue-900">Antes, Durante y Después</strong>.</span>
                  </li>
                  <li class="flex items-start gap-3">
                      <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <el-icon :size="12"><Check /></el-icon>
                      </div>
                      <span class="font-medium leading-relaxed">Firma de conformidad del cliente en la <strong class="text-blue-900">Hoja de servicio</strong>.</span>
                  </li>
                  <li class="flex items-start gap-3">
                      <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <el-icon :size="12"><Check /></el-icon>
                      </div>
                      <span class="font-medium leading-relaxed">Área de trabajo completamente <strong class="text-blue-900">limpia y libre de escombro</strong>.</span>
                  </li>
              </ul>
            </div>
            <div class="text-xs text-blue-600/70 font-medium">
                * Construmax de Occidente validará estos puntos antes de procesar la estimación correspondiente.
            </div>
          </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
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
    Warning,
    List,
    PictureFilled,
    Picture as IconPicture,
    Document,
    Money,
    ChatDotSquare
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
const savingNotesId = ref(null);

const saveNotes = (task) => {
    savingNotesId.value = task.id;
    router.put(task.urls.notes, {
        technician_notes: task.technician_notes,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Notas guardadas');
            savingNotesId.value = null;
        },
        onError: () => {
            ElMessage.error('Error al guardar notas');
            savingNotesId.value = null;
        },
    });
};

// Lógica de Bloqueo Secuencial
const isTaskLocked = (index) => {
    if (index === 0) return false;
    const previousTask = props.tasks[index - 1];
    return previousTask.status !== 'Completada';
};

// --- HELPERS ---

const getStepColorClass = (task, index) => {
    if (task.status === 'Completada') return 'bg-emerald-500 text-white border-white';
    if (isTaskLocked(index)) return 'bg-gray-100 text-gray-400 border-white';
    return 'bg-blue-600 text-white border-blue-50'; 
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

// --- PAGOS A TÉCNICO ---
const formatPaymentCurrency = (value) => {
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

// --- ACCIONES ---

const toggleStatus = (task) => {
    togglingTaskId.value = task.id;
    router.put(task.urls.toggle, {}, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success(task.status === 'Completada' ? 'Tarea reabierta exitosamente' : 'Tarea finalizada correctamente');
            togglingTaskId.value = null;
        },
        onError: () => {
            ElMessage.error('Error al actualizar el estado de la tarea');
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
        ElMessage.error('Solo se permiten imágenes menores a 15MB');
        return;
    }

    uploadingTaskId.value = task.id;
    const form = useForm({ file: file });
    
    form.post(task.urls.evidence, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Evidencia fotográfica subida correctamente');
            uploadingTaskId.value = null;
        },
        onError: () => {
            ElMessage.error('Ocurrió un error al subir la imagen');
            uploadingTaskId.value = null;
        }
    });
};

const deleteEvidence = (url) => {
    router.delete(url, {
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Imagen eliminada correctamente')
    });
};

const showImage = (url) => {
    window.open(url, '_blank');
};
</script>

<style scoped>
/* Ajustes específicos para Element Plus en este contexto */
:deep(.el-card__header) {
    padding: 20px 24px 0;
    border-bottom: none;
}

:deep(.el-card__body) {
    padding: 24px;
}
</style>