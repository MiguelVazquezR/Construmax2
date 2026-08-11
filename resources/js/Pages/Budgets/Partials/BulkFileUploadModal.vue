<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import axios from 'axios';
import { debounce } from 'lodash';

const props = defineProps({
    show: Boolean,
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

const options = ref([]);
const loadedCount = ref(0);
const total = ref(0);
const loading = ref(false);

const isFilterActive = computed(() => customerFilter.value !== null);

const budgetLabel = (budget) =>
    `${budget.ticket?.folio || '#' + budget.id} — ${budget.ticket?.name || 'N/A'} (${budget.ticket?.customer?.name || 'N/A'})`;

const fetchOptions = async (search = '', customerId = null) => {
    loading.value = true;
    try {
        const { data } = await axios.get(route('budgets.options'), {
            params: {
                search,
                customer_id: customerId,
                limit: 50,
            },
        });

        loadedCount.value = (data.data || []).length;
        total.value = data.total || 0;

        // Mantiene visibles los presupuestos ya seleccionados aunque no estén en los resultados actuales
        const merged = [...(data.data || [])];
        selectedBudgets.value.forEach((budget) => {
            if (!merged.some((option) => option.id === budget.id)) {
                merged.push(budget);
            }
        });
        options.value = merged;
    } catch {
        options.value = [];
        loadedCount.value = 0;
        total.value = 0;
    } finally {
        loading.value = false;
    }
};

const remoteMethod = debounce((query) => {
    fetchOptions(query || '', customerFilter.value);
}, 300);

watch(customerFilter, (value) => {
    fetchOptions('', value);
});

watch(
    () => props.show,
    (visible) => {
        if (visible) {
            customerFilter.value = null;
            fetchOptions();
        }
    }
);

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

    form.budget_ids = selectedBudgets.value.map((budget) => budget.id);

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
                        {{ loadedCount }} de {{ total }} presupuestos
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
                    remote
                    :remote-method="remoteMethod"
                    :loading="loading"
                    placeholder="Buscar y seleccionar presupuestos..."
                    class="w-full"
                    collapse-tags
                    collapse-tags-tooltip
                    value-key="id"
                >
                    <el-option
                        v-for="budget in options"
                        :key="budget.id"
                        :label="budgetLabel(budget)"
                        :value="budget"
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
