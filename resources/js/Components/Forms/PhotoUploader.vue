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
    <div class="relative group">
        <el-upload
            class="avatar-uploader"
            action="#"
            :auto-upload="false"
            :show-file-list="false"
            :on-change="handleChange"
            accept="image/jpeg,image/png,image/webp"
        >
            <div v-if="preview" class="relative">
                <el-avatar :size="100" :src="preview" class="border-2 border-gray-200" />
                <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-40 rounded-full opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                    <el-icon class="text-white text-xl"><Camera /></el-icon>
                </div>
            </div>
            <div v-else class="w-[100px] h-[100px] rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center border-2 border-dashed border-gray-300 dark:border-gray-600 cursor-pointer hover:border-primary transition-colors">
                <div class="text-center text-gray-400">
                    <el-icon class="text-xl mb-1"><Plus /></el-icon>
                    <div class="text-[10px]">Foto</div>
                </div>
            </div>
        </el-upload>
        <p v-if="hint" class="text-center text-xs text-gray-400 mt-2">{{ hint }}</p>
    </div>
</template>
