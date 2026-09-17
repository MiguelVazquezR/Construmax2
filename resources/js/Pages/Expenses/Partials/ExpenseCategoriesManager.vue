<script setup>
import { onMounted, ref, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Edit, Delete } from '@element-plus/icons-vue';
import axios from 'axios';

const props = defineProps({
    modelValue: Boolean,
});

const emit = defineEmits(['update:modelValue', 'changed']);

const dialogVisible = ref(props.modelValue);
watch(() => props.modelValue, (value) => { dialogVisible.value = value; });
watch(dialogVisible, (value) => emit('update:modelValue', value));

const categories = ref([]);
const loading = ref(false);

async function loadCategories() {
    loading.value = true;

    try {
        const { data } = await axios.get(route('expenses.categories.index'));
        categories.value = data;
    } catch {
        ElMessage.error('No se pudieron cargar las categorías.');
    } finally {
        loading.value = false;
    }
}

onMounted(loadCategories);

// --- Create / Edit sub-dialog ---
const editForm = ref({ id: null, name: '', is_active: true });
const showEditDialog = ref(false);
const editLoading = ref(false);

function openCreate() {
    editForm.value = { id: null, name: '', is_active: true };
    showEditDialog.value = true;
}

function openEdit(category) {
    editForm.value = {
        id: category.id,
        name: category.name,
        is_active: category.is_active,
    };
    showEditDialog.value = true;
}

async function saveCategory() {
    if (!editForm.value.name.trim()) {
        ElMessage.warning('Escribe el nombre de la categoría.');
        return;
    }

    editLoading.value = true;

    try {
        if (editForm.value.id) {
            await axios.put(route('expenses.categories.update', editForm.value.id), {
                name: editForm.value.name,
                is_active: editForm.value.is_active,
            });

            ElMessage.success('Categoría actualizada correctamente.');
        } else {
            await axios.post(route('expenses.categories.store'), {
                name: editForm.value.name,
            });

            ElMessage.success('Categoría creada correctamente.');
        }

        showEditDialog.value = false;
        await loadCategories();
        emit('changed');
    } catch (error) {
        ElMessage.error(error.response?.data?.message || 'No se pudo guardar la categoría.');
    } finally {
        editLoading.value = false;
    }
}

async function deleteCategory(category) {
    const expenseCount = category.expenses_count ?? 0;

    const message = expenseCount > 0
        ? `La categoría "${category.name}" está en uso por ${expenseCount} gasto(s); esos gastos quedarán como "Sin categoría".`
        : `¿Eliminar la categoría "${category.name}"?`;

    try {
        await ElMessageBox.confirm(message, 'Confirmar eliminación', { type: 'warning' });
        await axios.delete(route('expenses.categories.destroy', category.id));
        ElMessage.success('Categoría eliminada correctamente.');
        await loadCategories();
        emit('changed');
    } catch { /* cancelled */ }
}
</script>

<template>
    <el-dialog v-model="dialogVisible" title="Gestionar categorías de gasto" width="580px" destroy-on-close>
        <div class="flex justify-end mb-3">
            <el-button type="primary" size="small" @click="openCreate">Agregar categoría</el-button>
        </div>

        <el-table :data="categories" v-loading="loading" size="small" max-height="400">
            <el-table-column prop="name" label="Nombre" min-width="170" show-overflow-tooltip />
            <el-table-column label="Gastos" width="80" align="center">
                <template #default="{ row }">
                    <span class="text-xs text-gray-500">{{ row.expenses_count }}</span>
                </template>
            </el-table-column>
            <el-table-column label="Activa" width="90" align="center">
                <template #default="{ row }">
                    <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                        {{ row.is_active ? 'Sí' : 'No' }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column label="Acciones" width="120" align="center">
                <template #default="{ row }">
                    <el-button size="small" :icon="Edit" @click="openEdit(row)" />
                    <el-button size="small" type="danger" :icon="Delete" @click="deleteCategory(row)" />
                </template>
            </el-table-column>

            <template #empty>
                <el-empty :image-size="70" description="Aún no hay categorías. Agrega la primera para clasificar los gastos." />
            </template>
        </el-table>

        <!-- Create / Edit sub-dialog -->
        <el-dialog
            v-model="showEditDialog"
            :title="editForm.id ? 'Editar categoría' : 'Nueva categoría'"
            width="420px"
            append-to-body
        >
            <el-form label-position="top">
                <el-form-item label="Nombre" required>
                    <el-input
                        v-model="editForm.name"
                        placeholder="Ej. Mantenimiento de vehículos"
                        maxlength="255"
                        @keyup.enter="saveCategory"
                    />
                </el-form-item>

                <el-form-item v-if="editForm.id" label="Activa">
                    <el-switch v-model="editForm.is_active" />
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="showEditDialog = false">Cancelar</el-button>
                <el-button type="primary" :loading="editLoading" @click="saveCategory">Guardar</el-button>
            </template>
        </el-dialog>
    </el-dialog>
</template>
