<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessageBox, ElMessage } from 'element-plus';
import { 
    Document, 
    Download, 
    Delete, 
    View,
    Picture,
    MoreFilled
} from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    technician: {
        type: Object,
        required: true
    }
});

const showViewer = ref(false);
const previewUrl = ref([]);
const isImagePreview = ref(false);

// Lógica de previsualización
const handlePreview = (media) => {
    const mime = media.mime_type || '';
    
    if (mime.startsWith('image/')) {
        previewUrl.value = [media.original_url];
        isImagePreview.value = true;
        showViewer.value = true;
    } else {
        // Abrir PDFs u otros archivos compatibles en nueva pestaña
        window.open(media.original_url, '_blank');
    }
};

const closeViewer = () => {
    showViewer.value = false;
};

// Descargar archivo
const handleDownload = (media) => {
    const link = document.createElement('a');
    link.href = media.original_url;
    link.download = media.file_name;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

// Eliminar archivo
const handleDelete = (media) => {
    ElMessageBox.confirm(
        '¿Estás seguro de eliminar este documento? Esta acción no se puede deshacer.',
        'Eliminar documento',
        {
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            type: 'warning',
        }
    ).then(() => {
        router.delete(route('technicians.delete-media', [props.technician.id, media.id]), {
            onSuccess: () => ElMessage.success('Documento eliminado exitosamente')
        });
    }).catch(() => {});
};

// Verificar si es previsualizable (Imagen o PDF)
const isPreviewable = (mime) => {
    if (!mime) return false;
    return mime.startsWith('image/') || mime === 'application/pdf';
};
</script>

<template>
    <div class="py-4 min-h-32">
        <div v-if="technician.media && technician.media.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            
            <div 
                v-for="file in technician.media" 
                :key="file.id" 
                class="border border-gray-200 dark:border-[#3f3f46] rounded-lg p-4 flex items-center gap-4 bg-gray-50 dark:bg-[#252529] relative transition-shadow hover:shadow-md"
            >
                <!-- Icono representativo -->
                <div 
                    class="p-3 rounded-lg flex-shrink-0"
                    :class="file.mime_type.startsWith('image/') ? 'bg-blue-100 text-blue-500' : 'bg-red-100 text-red-500'"
                >
                    <el-icon size="24">
                        <Picture v-if="file.mime_type.startsWith('image/')" />
                        <Document v-else />
                    </el-icon>
                </div>

                <!-- Info del archivo -->
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-gray-800 dark:text-gray-200 truncate text-sm" :title="file.file_name">
                        {{ file.file_name }}
                    </div>
                    <div class="text-xs text-gray-500 flex flex-col sm:flex-row sm:gap-2">
                        <span>{{ (file.size / 1024).toFixed(1) }} KB</span>
                        <span class="capitalize hidden sm:inline">•</span>
                        <span class="capitalize">{{ file.collection_name.replace(/_/g, ' ') }}</span>
                    </div>
                </div>

                <!-- Botón de Opciones (Siempre Visible para Mobile) -->
                <div class="flex-shrink-0">
                    <el-dropdown trigger="click" placement="bottom-end">
                        <el-button 
                            size="small" 
                            circle 
                            class="!border-none bg-transparent hover:bg-gray-200 dark:hover:bg-gray-700"
                        >
                            <el-icon><MoreFilled /></el-icon>
                        </el-button>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item 
                                    v-if="isPreviewable(file.mime_type)" 
                                    :icon="View" 
                                    @click="handlePreview(file)"
                                >
                                    Ver
                                </el-dropdown-item>
                                
                                <el-dropdown-item 
                                    :icon="Download" 
                                    @click="handleDownload(file)"
                                >
                                    Descargar
                                </el-dropdown-item>
                                
                                <el-dropdown-item 
                                    v-if="can('technicians.edit')" 
                                    :icon="Delete" 
                                    class="text-red-500" 
                                    divided 
                                    @click="handleDelete(file)"
                                >
                                    Eliminar
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </div>
            </div>

        </div>

        <el-empty v-else description="No hay documentos cargados." :image-size="100">
            <template #image>
                <el-icon :size="60" class="text-gray-300"><Document /></el-icon>
            </template>
        </el-empty>

        <!-- Visor de Imágenes de Element Plus -->
        <el-image-viewer 
            v-if="showViewer && isImagePreview" 
            :url-list="previewUrl" 
            @close="closeViewer" 
        />
    </div>
</template>

<style scoped>
/* Eliminar outline del trigger del dropdown */
.el-dropdown-link:focus {
    outline: none;
}

:deep(.el-dropdown-menu__item) {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
}
</style>