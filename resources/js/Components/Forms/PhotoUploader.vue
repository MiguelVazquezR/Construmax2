<script setup>
import { Camera, Plus } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';

const props = defineProps({
    preview: {
        type: String,
        default: null,
    },
    maxSizeMb: {
        type: Number,
        default: 2,
    },
    hint: {
        type: String,
        default: 'Opcional',
    },
});

const emit = defineEmits(['change']);

const handleChange = (file) => {
    const raw = file.raw;

    if (!raw?.type?.startsWith('image/')) {
        ElMessage.error('El archivo debe ser una imagen.');
        return false;
    }

    if (raw.size / 1024 / 1024 > props.maxSizeMb) {
        ElMessage.error(`La imagen no debe exceder ${props.maxSizeMb} MB.`);
        return false;
    }

    emit('change', raw);
};
</script>

<template>
    <div class="photo-uploader text-center">
        <el-upload
            class="avatar-uploader"
            action="#"
            :auto-upload="false"
            :show-file-list="false"
            :on-change="handleChange"
            accept="image/jpeg,image/png,image/webp"
        >
            <div v-if="preview" class="photo-preview relative">
                <el-avatar :size="104" :src="preview" class="border border-gray-200 dark:border-[#3f3f46]" />
                <div class="photo-overlay absolute inset-0 flex items-center justify-center rounded-full bg-black/45 opacity-0 transition-opacity">
                    <el-icon class="text-white" :size="20"><Camera /></el-icon>
                </div>
            </div>

            <div v-else class="photo-empty flex h-[104px] w-[104px] items-center justify-center rounded-full border-2 border-dashed border-gray-300 bg-gray-50 transition-colors dark:border-[#3f3f46] dark:bg-[#252529]">
                <div class="photo-empty-content text-center text-gray-400 transition-colors">
                    <el-icon :size="20"><Plus /></el-icon>
                    <p class="mt-0.5 text-[10px] font-medium">Subir foto</p>
                </div>
            </div>
        </el-upload>

        <p v-if="hint" class="mt-2 text-xs text-gray-400">{{ hint }}</p>
    </div>
</template>

<style scoped>
.avatar-uploader :deep(.el-upload) {
    border-radius: 50%;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.photo-preview:hover .photo-overlay {
    opacity: 1;
}

.photo-empty:hover {
    border-color: #f26c17;
}

.photo-empty:hover .photo-empty-content {
    color: #f26c17;
}
</style>
