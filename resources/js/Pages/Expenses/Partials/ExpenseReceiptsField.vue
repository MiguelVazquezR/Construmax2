<script setup>
import { ref } from 'vue';
import { ElMessage } from 'element-plus';
import { UploadFilled } from '@element-plus/icons-vue';

const props = defineProps({
    /** Receipts already stored in the expense: [{ id, url, name }] */
    existing: { type: Array, default: () => [] },
});

const emit = defineEmits(['change']);

const fileList = ref([]);
const removeIds = ref([]);

const isRemoved = (receipt) => removeIds.value.includes(receipt.id);

const emitChange = () => {
    emit('change', {
        files: fileList.value.map((file) => file.raw).filter(Boolean),
        remove_ids: [...removeIds.value],
    });
};

const onChange = (file) => {
    if (file.raw && file.raw.size > 10 * 1024 * 1024) {
        ElMessage.warning('El archivo no debe exceder 10 MB.');
        fileList.value = fileList.value.filter((item) => item.uid !== file.uid);
    }

    emitChange();
};

const onRemove = () => {
    emitChange();
};

const toggleExisting = (receipt) => {
    removeIds.value = isRemoved(receipt)
        ? removeIds.value.filter((id) => id !== receipt.id)
        : [...removeIds.value, receipt.id];

    emitChange();
};

const onExceed = () => {
    ElMessage.warning('Puedes adjuntar hasta 5 archivos por gasto.');
};
</script>

<template>
    <div class="w-full">
        <div v-if="existing.length" class="mb-2 space-y-1">
            <div v-for="receipt in existing" :key="receipt.id" class="flex items-center gap-2 text-sm">
                <a
                    :href="receipt.url"
                    target="_blank"
                    rel="noopener"
                    class="text-blue-600 hover:underline dark:text-blue-400"
                    :class="{ 'line-through !text-gray-400': isRemoved(receipt) }"
                >
                    {{ receipt.name }}
                </a>
                <el-button
                    link
                    :type="isRemoved(receipt) ? 'info' : 'danger'"
                    size="small"
                    @click="toggleExisting(receipt)"
                >
                    {{ isRemoved(receipt) ? 'Deshacer' : 'Quitar' }}
                </el-button>
            </div>
        </div>

        <el-upload
            v-model:file-list="fileList"
            :auto-upload="false"
            :limit="5"
            multiple
            accept=".jpg,.jpeg,.png,.webp,.pdf"
            :on-change="onChange"
            :on-remove="onRemove"
            :on-exceed="onExceed"
        >
            <el-button size="small" :icon="UploadFilled">Adjuntar archivos</el-button>
            <template #tip>
                <div class="el-upload__tip">
                    JPG, PNG, WEBP o PDF. Máx. 10 MB por archivo, hasta 5 archivos por gasto.
                </div>
            </template>
        </el-upload>
    </div>
</template>
