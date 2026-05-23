<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Folder, Document } from '@element-plus/icons-vue';

const props = defineProps({
    budget: Object,
});

const emit = defineEmits(['preview']);

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
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
        <div class="p-4 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#2b2b2e] flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h3 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                <el-icon><Folder /></el-icon> Documentos y archivos
            </h3>
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
                        <el-button type="primary" plain size="small" icon="FolderAdd">Seleccionar archivos</el-button>
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

        <div class="p-4">
            <ul v-if="budget.media.length > 0" class="space-y-3">
                <li v-for="file in budget.media" :key="file.id" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-[#252529] rounded-lg border border-gray-100 dark:border-[#3f3f46]">
                    <div class="flex items-center gap-3 overflow-hidden cursor-pointer" @click="openPreview(file)">
                        <div class="bg-blue-100 text-blue-600 p-2 rounded">
                            <el-icon><Document /></el-icon>
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate hover:text-primary transition-colors">
                                {{ file.file_name }}
                            </span>
                            <span class="text-xs text-gray-400">{{ (file.size / 1024).toFixed(2) }} KB</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <el-button circle size="small" type="primary" plain icon="View" @click="openPreview(file)" />
                        <a :href="file.original_url" target="_blank" download>
                            <el-button circle size="small" icon="Download" />
                        </a>
                        <el-button circle size="small" type="danger" icon="Delete" plain @click="deleteFile(file.id)" />
                    </div>
                </li>
            </ul>
            <el-empty v-else description="No hay archivos adjuntos" :image-size="80" />
        </div>
    </div>
</template>
