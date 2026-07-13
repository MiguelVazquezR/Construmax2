<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

const props = defineProps({
    show: Boolean,
    budgets: Array,
    customers: Array,
});

const emit = defineEmits(['update:show']);

const form = useForm({
    budget_ids: [],
    files: [],
});

const selectedBudgets = ref([]);
const uploadedFiles = ref([]);
const uploadRef = ref(null);

const customerFilter = ref(null);

const validBudgets = computed(() => {
    if (!props.budgets) return [];
    return props.budgets.filter(b => b.ticket?.name);
});

const filteredBudgets = computed(() => {
    if (!customerFilter.value) return validBudgets.value;
    return validBudgets.value.filter(b =>
        b.ticket?.customer?.id === customerFilter.value
    );
});

const isFilterActive = computed(() => customerFilter.value !== null);

const totalCount = computed(() => validBudgets.value.length);

const selectedCustomerName = computed(() => {
    if (!customerFilter.value || !props.customers) return '';
    return props.customers.find(c => c.id === customerFilter.value)?.name || '';
});

const handleFileChange = (file) => {
    form.files.push(file.raw);
    uploadedFiles.value.push(file);
};

const handleFileRemove = (file) => {
    const idx = uploadedFiles.value.findIndex(f => f.uid === file.uid);
    if (idx !== -1) {
        uploadedFiles.value.splice(idx, 1);
        form.files.splice(idx, 1);
    }
};

const submit = () => {
    if (!selectedBudgets.value.length) {
        ElMessage.warning('Selecciona al menos un presupuesto.');
        return;
    }
    if (!form.files.length) {
        ElMessage.warning('Adjunta al menos un archivo.');
        return;
    }

    form.budget_ids = selectedBudgets.value;

    form.post(route('budgets.bulk-upload-files'), {
        forceFormData: true,
        onSuccess: () => {
            emit('update:show', false);
            selectedBudgets.value = [];
            uploadedFiles.value = [];
            form.reset();
            ElMessage.success('Archivos adjuntados correctamente.');
        },
        onError: () => ElMessage.error('Error al adjuntar archivos.'),
    });
};

const close = () => {
    selectedBudgets.value = [];
    uploadedFiles.value = [];
    customerFilter.value = null;
    form.reset();
    emit('update:show', false);
};
</script>

<template>
    <el-dialog
        :model-value="show"
        @update:model-value="emit('update:show', $event)"
        title="Adjuntar archivos a múltiples presupuestos"
        width="600px"
        @close="close"
    >
        <el-form :model="form" label-position="top">
            <el-form-item label="Filtrar por cliente">
                <el-select
                    v-model="customerFilter"
                    filterable
                    clearable
                    placeholder="Buscar y seleccionar cliente..."
                    class="w-full"
                    :class="{ 'is-filter-active': isFilterActive }"
                >
                    <el-option
                        v-for="customer in customers"
                        :key="customer.id"
                        :label="customer.name"
                        :value="customer.id"
                    />
                </el-select>
                <div
                    v-if="isFilterActive"
                    class="filter-feedback"
                >
                    <el-tag size="small" type="warning" effect="plain" round>
                        {{ filteredBudgets.length }} de {{ totalCount }} presupuestos
                    </el-tag>
                    <div class="filter-hint">
                        <span class="filter-hint-text">Despliega el selector de abajo para ver los elementos filtrados</span>
                        <span class="filter-hint-arrow">▼</span>
                    </div>
                </div>
            </el-form-item>

            <el-form-item label="Seleccionar presupuestos">
                <el-select
                    v-model="selectedBudgets"
                    multiple
                    filterable
                    placeholder="Buscar y seleccionar presupuestos..."
                    class="w-full"
                    collapse-tags
                    collapse-tags-tooltip
                >
                    <el-option
                        v-for="budget in filteredBudgets"
                        :key="budget.id"
                        :label="`#${budget.id} — ${budget.ticket?.name || 'N/A'} (${budget.ticket?.customer?.name || 'N/A'})`"
                        :value="budget.id"
                    />
                </el-select>
            </el-form-item>

            <el-form-item label="Archivos a adjuntar">
                <el-upload
                    ref="uploadRef"
                    :auto-upload="false"
                    :on-change="handleFileChange"
                    :on-remove="handleFileRemove"
                    :file-list="uploadedFiles"
                    multiple
                    class="w-full"
                >
                    <el-button type="primary" plain icon="Upload">Seleccionar archivos</el-button>
                    <template #tip>
                        <div class="el-upload__tip">PDF, imágenes, documentos (Máx. 20MB c/u)</div>
                    </template>
                </el-upload>
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="close">Cancelar</el-button>
            <el-button type="primary" @click="submit" :loading="form.processing">
                Adjuntar archivos
            </el-button>
        </template>
    </el-dialog>
</template>

<style scoped>
.filter-feedback {
    margin-top: 6px;
}

.filter-hint {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
    font-size: 12px;
    color: var(--el-text-color-secondary);
}

.filter-hint-arrow {
    animation: bounce-down 0.8s ease-in-out infinite;
}

@keyframes bounce-down {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(4px); }
}

.is-filter-active :deep(.el-input__wrapper) {
    box-shadow: 0 0 0 1px var(--el-color-warning) inset;
}
</style>
