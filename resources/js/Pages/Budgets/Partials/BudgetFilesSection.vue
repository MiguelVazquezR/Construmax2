<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Folder, Document, Camera } from '@element-plus/icons-vue';

const props = defineProps({
    budget: Object,
});

const emit = defineEmits(['preview']);

// Separar media por colección
const surveyImages = computed(() =>
    (props.budget.media || []).filter(m => m.collection_name === 'survey_images')
);

const budgetFiles = computed(() =>
    (props.budget.media || []).filter(m => m.collection_name === 'budget_files')
);

const invoiceDocs = computed(() =>
    (props.budget.media || []).filter(m => m.collection_name === 'invoice_document')
);

// --- SUBIR ARCHIVOS ADICIONALES (budget_files) ---
const fileUploadRef = ref(null);
const fileList = ref([]);

const uploadForm = useForm({
    files: [],
});

const handleFilesChange = (file, fileListArg) => {
    fileList.value = fileListArg;
    uploadForm.files = fileListArg.map(f => f.raw);
};

const submitFiles = () => {
    if (uploadForm.files.length === 0) return;

    uploadForm.post(route('budgets.files.store', props.budget.id), {
        onSuccess: () => {
            ElMessage.success('Archivos subidos correctamente');
            fileList.value = [];
            uploadForm.reset();
            if (fileUploadRef.value) fileUploadRef.value.clearFiles();
        },
        onError: () => ElMessage.error('Error al subir archivos'),
        forceFormData: true,
    });
};

const deleteFile = (mediaId) => {
    ElMessageBox.confirm('¿Eliminar este archivo permanentemente?', 'Confirmar', {
        type: 'warning',
    }).then(() => {
        router.delete(route('budgets.files.destroy', mediaId), {
            onSuccess: () => ElMessage.success('Archivo eliminado'),
        });
    }).catch(() => {});
};

const openPreview = (file) => {
    emit('preview', file);
};

// --- HELPERS ---
const formatSize = (bytes) => {
    if (!bytes) return '0 KB';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(2) + ' KB';
    return (bytes / 1048576).toFixed(2) + ' MB';
};

const hasAnyMedia = computed(() =>
    surveyImages.value.length > 0 || budgetFiles.value.length > 0 || invoiceDocs.value.length > 0
);
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
        <!-- CABECERA -->
        <div class="p-4 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#2b2b2e] flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h3 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                <el-icon><Folder /></el-icon> Documentos y archivos
            </h3>
            <!-- Subir archivos adicionales -->
            <div class="flex gap-2 items-center">
                <el-upload
                    ref="fileUploadRef"
                    :show-file-list="false"
                    :auto-upload="false"
                    :on-change="handleFilesChange"
                    multiple
                    accept=".pdf,.jpg,.png,.doc,.docx,.xls,.xlsx"
                >
                    <template #trigger>
                        <el-button type="primary" plain size="small" icon="FolderAdd">Agregar archivos</el-button>
                    </template>
                </el-upload>
                <el-button
                    v-if="uploadForm.files.length > 0"
                    type="success"
                    size="small"
                    icon="Upload"
                    @click="submitFiles"
                    :loading="uploadForm.processing"
                >
                    Subir ({{ uploadForm.files.length }})
                </el-button>
            </div>
        </div>

        <!-- CONTENIDO -->
        <div class="p-4 space-y-6">
            <el-empty v-if="!hasAnyMedia" description="No hay archivos adjuntos" :image-size="80" />

            <!-- 1. IMÁGENES DE LEVANTAMIENTO -->
            <div v-if="surveyImages.length > 0">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                    <el-icon class="text-orange-500"><Camera /></el-icon>
                    Imágenes de levantamiento
                    <el-tag size="small" round>{{ surveyImages.length }}</el-tag>
                </h4>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    <div
                        v-for="file in surveyImages"
                        :key="file.id"
                        class="relative group rounded-lg overflow-hidden border border-gray-200 dark:border-[#3f3f46] bg-gray-100 dark:bg-[#252529] aspect-square cursor-pointer"
                        @click="openPreview(file)"
                    >
                        <img
                            :src="file.original_url"
                            class="w-full h-full object-cover"
                            :alt="file.file_name"
                        />
                        <!-- Overlay hover -->
                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                            <el-button circle size="small" type="primary" icon="View" @click.stop="openPreview(file)" />
                            <a :href="file.original_url" target="_blank" download @click.stop>
                                <el-button circle size="small" icon="Download" />
                            </a>
                            <el-button circle size="small" type="danger" icon="Delete" plain @click.stop="deleteFile(file.id)" />
                        </div>
                        <!-- Nombre al pie -->
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent p-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <p class="text-white text-xs truncate">{{ file.file_name }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. DOCUMENTO DE FACTURA -->
            <div v-if="invoiceDocs.length > 0">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                    <el-icon class="text-blue-500"><Document /></el-icon>
                    Documento de factura
                    <el-tag size="small" round>{{ invoiceDocs.length }}</el-tag>
                </h4>
                <div class="space-y-2">
                    <div
                        v-for="file in invoiceDocs"
                        :key="file.id"
                        class="flex items-center justify-between p-3 bg-gray-50 dark:bg-[#252529] rounded-lg border border-gray-100 dark:border-[#3f3f46]"
                    >
                        <div class="flex items-center gap-3 overflow-hidden cursor-pointer min-w-0" @click="openPreview(file)">
                            <div class="bg-blue-100 text-blue-600 p-2 rounded shrink-0">
                                <el-icon><Document /></el-icon>
                            </div>
                            <div class="flex flex-col min-w-0">
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate hover:text-primary transition-colors">
                                    {{ file.file_name }}
                                </span>
                                <span class="text-xs text-gray-400">{{ formatSize(file.size) }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 ml-2">
                            <el-button circle size="small" type="primary" plain icon="View" @click="openPreview(file)" />
                            <a :href="file.original_url" target="_blank" download>
                                <el-button circle size="small" icon="Download" />
                            </a>
                            <el-button circle size="small" type="danger" icon="Delete" plain @click="deleteFile(file.id)" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. ARCHIVOS ADJUNTOS (subidos desde detalle) -->
            <div v-if="budgetFiles.length > 0">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                    <el-icon class="text-green-500"><Folder /></el-icon>
                    Archivos adjuntos
                    <el-tag size="small" round>{{ budgetFiles.length }}</el-tag>
                </h4>
                <div class="space-y-2">
                    <div
                        v-for="file in budgetFiles"
                        :key="file.id"
                        class="flex items-center justify-between p-3 bg-gray-50 dark:bg-[#252529] rounded-lg border border-gray-100 dark:border-[#3f3f46]"
                    >
                        <div class="flex items-center gap-3 overflow-hidden cursor-pointer min-w-0" @click="openPreview(file)">
                            <div class="bg-green-100 text-green-600 p-2 rounded shrink-0">
                                <el-icon><Document /></el-icon>
                            </div>
                            <div class="flex flex-col min-w-0">
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate hover:text-primary transition-colors">
                                    {{ file.file_name }}
                                </span>
                                <span class="text-xs text-gray-400">{{ formatSize(file.size) }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 ml-2">
                            <el-button circle size="small" type="primary" plain icon="View" @click="openPreview(file)" />
                            <a :href="file.original_url" target="_blank" download>
                                <el-button circle size="small" icon="Download" />
                            </a>
                            <el-button circle size="small" type="danger" icon="Delete" plain @click="deleteFile(file.id)" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
