<template>
  <Head :title="`Ticket #${ticket.id}`" />
  
  <div class="public-task-container">
    <el-card class="box-card" shadow="hover">
      <!-- Encabezado de la Tarea -->
      <template #header>
        <div class="card-header">
          <div class="header-left">
            <span class="task-id">
              Ticket #{{ ticket.id }}
            </span>
            <span class="task-title">{{ task.name }}</span>
          </div>
          
          <!-- Botón de Acción Principal (Toggle) -->
          <div class="header-right">
             <el-tag :type="getStatusType(task.status)" effect="dark" class="status-tag">
                {{ task.status }}
             </el-tag>
          </div>
        </div>
      </template>

      <!-- Detalles de la Tarea -->
      <div class="task-body">
        
        <!-- Acciones Rápidas -->
        <div class="action-bar mb-4">
             <el-button 
                :type="task.status === 'Completada' ? 'warning' : 'success'" 
                :icon="task.status === 'Completada' ? RefreshLeft : Check"
                :loading="toggleForm.processing"
                @click="toggleStatus"
                class="w-full-mobile"
             >
                {{ task.status === 'Completada' ? 'Reabrir Tarea' : 'Marcar como Completada' }}
             </el-button>
        </div>

        <el-descriptions :column="1" border class="mb-4">
          <el-descriptions-item label="Cliente">
            {{ ticket.budget?.customer?.name || 'N/A' }}
          </el-descriptions-item>
          <el-descriptions-item label="Descripción">
            <span style="white-space: pre-wrap;">{{ task.description || 'Sin descripción' }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="Fechas">
            Del: {{ formatDate(task.start_date) }} <br>
            Al: {{ formatDate(task.due_date) }}
          </el-descriptions-item>
          <el-descriptions-item label="Asignado a" v-if="task.assignee">
            <el-avatar :size="24" :src="task.assignee.profile_photo_url" style="vertical-align: middle; margin-right: 5px;">
                {{ task.assignee.name.charAt(0) }}
            </el-avatar>
            {{ task.assignee.name }}
          </el-descriptions-item>
        </el-descriptions>

        <el-divider content-position="left">Evidencias Adjuntas</el-divider>

        <!-- Formulario de Subida -->
        <div class="upload-section mb-4">
            <el-upload
                class="upload-demo"
                drag
                action="#"
                :auto-upload="true"
                :show-file-list="false"
                :http-request="handleUpload"
                :disabled="uploadForm.processing"
                accept="image/*"
            >
                <el-icon class="el-icon--upload"><upload-filled /></el-icon>
                <div class="el-upload__text">
                    Arrastra una imagen o <em>haz clic para subir</em>
                </div>
                <template #tip>
                    <div class="el-upload__tip">
                        Archivos jpg/png menores a 10MB.
                    </div>
                </template>
            </el-upload>
            
            <!-- Barra de progreso de subida manual si Inertia está procesando -->
            <el-progress 
                v-if="uploadForm.processing" 
                :percentage="uploadForm.progress?.percentage || 50" 
                status="success"
                :indeterminate="true"
                class="mt-2"
            />
        </div>

        <!-- SECCIÓN DE EVIDENCIAS -->
        <div class="evidence-gallery-container" v-if="task.media && task.media.length > 0">
          <div 
            v-for="(img, index) in task.media" 
            :key="img.id" 
            class="evidence-item-wrapper"
          >
            <!-- Contenedor relativo para posicionar el botón de borrar -->
            <div class="thumbnail-container">
                <el-image
                  class="evidence-thumbnail"
                  :src="img.original_url"
                  :zoom-rate="1.2"
                  :max-scale="7"
                  :min-scale="0.2"
                  :preview-src-list="getAllImageUrls(task.media)"
                  :initial-index="index"
                  fit="cover"
                  loading="lazy"
                  preview-teleported
                  hide-on-click-modal
                >
                  <template #error>
                    <div class="image-slot-error">
                      <el-icon><icon-picture /></el-icon>
                    </div>
                  </template>
                  <template #placeholder>
                    <div class="image-slot-loading">...</div>
                  </template>
                </el-image>

                <!-- Botón de eliminar (Solo visible si tenemos URL de borrado) -->
                <el-popconfirm
                    v-if="img.delete_url"
                    title="¿Eliminar esta evidencia?"
                    confirm-button-text="Sí"
                    cancel-button-text="No"
                    @confirm="deleteEvidence(img.delete_url)"
                >
                    <template #reference>
                        <div class="delete-overlay">
                            <el-icon><Delete /></el-icon>
                        </div>
                    </template>
                </el-popconfirm>
            </div>
            
            <span class="evidence-label">{{ img.file_name }}</span>
            <span class="evidence-date">{{ formatDate(img.created_at) }}</span>
          </div>
        </div>

        <el-empty v-else description="Sin evidencias adjuntas. Sube la primera imagen arriba." :image-size="60"></el-empty>
        
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { 
    Picture as IconPicture, 
    Check, 
    RefreshLeft, 
    UploadFilled,
    Delete
} from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import dayjs from 'dayjs'; 
import 'dayjs/locale/es'; // Asegúrate de tener configurado locale si lo deseas

// Props recibidas de Inertia (TicketTaskController@publicShow)
const props = defineProps({
  task: Object,
  ticket: Object,
  urls: Object,
});

// --- LÓGICA DE ESTADO ---
const toggleForm = useForm({});
const uploadForm = useForm({
    file: null
});

const getStatusType = (status) => {
  const map = {
    'Pendiente': 'warning',
    'En proceso': 'primary',
    'Completada': 'success',
  };
  return map[status] || 'info';
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  return dayjs(date).format('DD/MM/YYYY HH:mm');
};

const getAllImageUrls = (mediaArray) => {
  return mediaArray.map(item => item.original_url);
};

// --- ACCIONES ---

// 1. Cambiar estado
const toggleStatus = () => {
    toggleForm.put(props.urls.toggle, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Estado actualizado correctamente');
        },
        onError: () => {
            ElMessage.error('No se pudo actualizar el estado');
        }
    });
};

// 2. Subir evidencia (Usando Inertia a través de Element Upload)
const handleUpload = (options) => {
    const { file } = options;
    
    // Validación básica frontend
    const isImage = file.type.startsWith('image/');
    const isLt10M = file.size / 1024 / 1024 < 10;

    if (!isImage) {
        ElMessage.error('El archivo debe ser una imagen');
        return;
    }
    if (!isLt10M) {
        ElMessage.error('La imagen debe pesar menos de 10MB');
        return;
    }

    uploadForm.file = file;
    
    uploadForm.post(props.urls.evidence, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Evidencia subida correctamente');
            uploadForm.reset();
        },
        onError: (errors) => {
            let msg = 'Error al subir';
            if(errors.file) msg = errors.file;
            ElMessage.error(msg);
        },
        onFinish: () => {
            // Limpia el estado de carga
        }
    });
};

// 3. Eliminar evidencia
const deleteEvidence = (deleteUrl) => {
    router.delete(deleteUrl, {
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Evidencia eliminada'),
        onError: () => ElMessage.error('No se pudo eliminar la evidencia')
    });
};
</script>

<style scoped>
.public-task-container {
  max-width: 800px;
  margin: 20px auto;
  font-family: 'Inter', sans-serif;
  padding: 0 10px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.header-left {
  display: flex;
  flex-direction: column;
}

.task-id {
  font-size: 0.85rem;
  color: #909399;
  font-weight: 600;
}

.task-title {
  font-size: 1.2rem;
  font-weight: 700;
  color: #303133;
}

.mb-4 {
  margin-bottom: 1.5rem;
}

.mt-2 {
    margin-top: 0.5rem;
}

/* Acciones */
.w-full-mobile {
    width: auto;
}
@media (max-width: 480px) {
    .w-full-mobile {
        width: 100%;
    }
}

/* --- ESTILOS DE LA GALERÍA DE EVIDENCIAS --- */

.evidence-gallery-container {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  margin-top: 20px;
}

.evidence-item-wrapper {
  display: flex;
  flex-direction: column;
  width: 130px;
}

.thumbnail-container {
    position: relative;
    width: 130px;
    height: 130px;
    border-radius: 8px;
    overflow: hidden;
}

.evidence-thumbnail {
  width: 100%;
  height: 100%;
  border-radius: 8px;
  border: 1px solid #e4e7ed;
  background-color: #f5f7fa;
  cursor: zoom-in;
  transition: transform 0.2s;
}

/* Efecto Hover para mostrar botón eliminar */
.thumbnail-container:hover .evidence-thumbnail {
    transform: scale(1.05);
}

.delete-overlay {
    position: absolute;
    top: 5px;
    right: 5px;
    background-color: rgba(245, 108, 108, 0.9);
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    opacity: 0; /* Oculto por defecto */
    transition: opacity 0.2s;
    z-index: 10;
}

.thumbnail-container:hover .delete-overlay {
    opacity: 1; /* Visible al hover */
}

/* En móbiles, el hover no funciona bien, mejor mostrarlo siempre o con un botón más accesible.
   Aquí hacemos que siempre sea un poco visible en touch screens si se desea, 
   o confiamos en el tap. Para simplicidad, lo dejamos con hover/tap logic */
@media (hover: none) {
    .delete-overlay {
        opacity: 0.8;
        background-color: rgba(245, 108, 108, 1);
    }
}

.evidence-label {
  font-size: 0.75rem;
  color: #606266;
  margin-top: 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 500;
}

.evidence-date {
    font-size: 0.65rem;
    color: #909399;
}

/* Slots de carga y error */
.image-slot-error, .image-slot-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 100%;
  height: 100%;
  background: #f5f7fa;
  color: #909399;
  font-size: 20px;
}

/* Upload Style Override */
:deep(.el-upload-dragger) {
    padding: 20px 10px;
}
</style>