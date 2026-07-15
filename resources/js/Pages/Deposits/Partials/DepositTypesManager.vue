<script setup>
import { ref, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Edit, Delete } from '@element-plus/icons-vue'
import axios from 'axios'

const props = defineProps({
  modelValue: Boolean,
})

const emit = defineEmits(['update:modelValue', 'types-changed'])

const dialogVisible = ref(props.modelValue)
watch(() => props.modelValue, (v) => { dialogVisible.value = v })
watch(dialogVisible, (v) => emit('update:modelValue', v))

const types = ref([])
const loading = ref(false)

async function loadTypes() {
  loading.value = true
  try {
    const { data } = await axios.get(route('deposits.types.index'))
    types.value = data
  } catch {
    ElMessage.error('No se pudieron cargar los tipos de depósito.')
  } finally {
    loading.value = false
  }
}

onMounted(loadTypes)

// --- Create / Edit ---
const editForm = ref({ id: null, name: '', is_active: true })
const showEditDialog = ref(false)
const editLoading = ref(false)

function openCreate() {
  editForm.value = { id: null, name: '', is_active: true }
  showEditDialog.value = true
}

function openEdit(type) {
  editForm.value = { ...type }
  showEditDialog.value = true
}

async function saveType() {
  editLoading.value = true
  try {
    if (editForm.value.id) {
      await axios.put(route('deposits.types.update', editForm.value.id), {
        name: editForm.value.name,
        is_active: editForm.value.is_active,
      })
      ElMessage.success('Tipo de depósito actualizado correctamente.')
    } else {
      await axios.post(route('deposits.types.store'), { name: editForm.value.name })
      ElMessage.success('Tipo de depósito creado correctamente.')
    }
    showEditDialog.value = false
    await loadTypes()
    emit('types-changed')
  } catch (e) {
    ElMessage.error(e.response?.data?.message || 'No se pudo guardar el tipo de depósito.')
  } finally {
    editLoading.value = false
  }
}

async function deleteType(type) {
  try {
    await ElMessageBox.confirm(`¿Eliminar "${type.name}"?`, 'Confirmar eliminación', { type: 'warning' })
    await axios.delete(route('deposits.types.destroy', type.id))
    ElMessage.success('Tipo de depósito eliminado correctamente.')
    await loadTypes()
    emit('types-changed')
  } catch { /* cancelled */ }
}
</script>

<template>
  <el-dialog v-model="dialogVisible" title="Gestionar tipos de depósito" width="500px" destroy-on-close>
    <div class="flex justify-end mb-3">
      <el-button type="primary" size="small" @click="openCreate">Agregar tipo</el-button>
    </div>

    <el-table :data="types" v-loading="loading" size="small" max-height="400">
      <el-table-column prop="name" label="Nombre" />
      <el-table-column label="Activo" width="80">
        <template #default="{ row }">
          <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
            {{ row.is_active ? 'Sí' : 'No' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="120">
        <template #default="{ row }">
          <el-button size="small" :icon="Edit" @click="openEdit(row)" />
          <el-button size="small" type="danger" :icon="Delete" @click="deleteType(row)" />
        </template>
      </el-table-column>
    </el-table>

    <!-- Create / Edit sub-dialog -->
    <el-dialog
      v-model="showEditDialog"
      :title="editForm.id ? 'Editar tipo de depósito' : 'Nuevo tipo de depósito'"
      width="400px"
      append-to-body
    >
      <el-form label-position="top">
        <el-form-item label="Nombre">
          <el-input v-model="editForm.name" placeholder="Nombre del tipo" />
        </el-form-item>
        <el-form-item v-if="editForm.id" label="Activo">
          <el-switch v-model="editForm.is_active" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showEditDialog = false">Cancelar</el-button>
        <el-button type="primary" :loading="editLoading" @click="saveType">Guardar</el-button>
      </template>
    </el-dialog>
  </el-dialog>
</template>
