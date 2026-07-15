<script setup>
import { ref, computed } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Check, CopyDocument, Clock, Delete, Edit, Money, Share } from '@element-plus/icons-vue'
import DepositListView from './Partials/DepositListView.vue'
import DepositCalendarView from './Partials/DepositCalendarView.vue'
import DepositForm from './Partials/DepositForm.vue'
import DepositTypesManager from './Partials/DepositTypesManager.vue'
import ShareLinkDialog from './Partials/ShareLinkDialog.vue'

const props = defineProps({
  deposits: Object,
  calendarEvents: Object,
  depositTypes: Array,
  can: Object,
  defaultShift: String,
  filters: Object,
})

const activeTab = ref('list')

// --- Deposit form dialog ---
const showFormDialog = ref(false)
const editingDeposit = ref(null)
const preselectedDate = ref('')
const depositFormRef = ref(null)

function openCreate(date = '') {
  editingDeposit.value = null
  preselectedDate.value = date
  showFormDialog.value = true
}

function openEdit(deposit) {
  editingDeposit.value = deposit
  preselectedDate.value = ''
  showFormDialog.value = true
}

function onFormSaved() {
  showFormDialog.value = false
  router.reload()
}

function onTypesChanged() {
  // Refresh deposit types in the form without reloading the page
  if (depositFormRef.value?.refreshTypes) {
    depositFormRef.value.refreshTypes()
  }
}

// --- Share link dialog ---
const showShareDialog = ref(false)
const shareTarget = ref(null) // { type: 'deposit', deposit } or { type: 'day', date }

function openShareForDeposit(deposit) {
  shareTarget.value = { type: 'deposit', deposit }
  showShareDialog.value = true
}

function openShareForDay(date) {
  shareTarget.value = { type: 'day', date }
  showShareDialog.value = true
}

// --- Approval ---
function approveDeposit(deposit) {
  ElMessageBox.confirm(
    `¿Aprobar el depósito para ${deposit.technician?.user?.name} por $${Number(deposit.amount).toFixed(2)}?`,
    'Confirmar aprobación',
    { type: 'info' }
  ).then(() => {
    router.post(route('deposits.approve', deposit.id), {}, {
      onSuccess: () => ElMessage.success('Depósito aprobado correctamente.'),
    })
  })
}

// --- Delete ---
function deleteDeposit(deposit) {
  ElMessageBox.confirm(
    `¿Eliminar el depósito para ${deposit.technician?.user?.name}?`,
    'Confirmar eliminación',
    { type: 'warning' }
  ).then(() => {
    router.delete(route('deposits.destroy', deposit.id), {
      onSuccess: () => ElMessage.success('Depósito eliminado correctamente.'),
    })
  })
}

// --- Types manager ---
const showTypesManager = ref(false)

// --- Status helpers ---
function statusColor(status) {
  return { pending: 'warning', approved: 'success', completed: 'info' }[status] || 'info'
}

function statusLabel(status) {
  return { pending: 'Pendiente', approved: 'Aprobado', completed: 'Completado' }[status] || status
}
</script>

<template>
  <AppLayout title="Depósitos">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
          Depósitos
        </h2>
        <div class="flex gap-2">
          <el-button v-if="can.manageTypes" @click="showTypesManager = true">
            Gestionar tipos de depósito
          </el-button>
          <el-button v-if="can.create" type="primary" :icon="Plus" @click="openCreate">
            Nuevo depósito
          </el-button>
        </div>
      </div>
    </template>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg">
        <el-tabs v-model="activeTab" class="px-4 pt-4">
          <el-tab-pane label="Lista" name="list">
            <DepositListView
              :deposits="deposits"
              :filters="filters"
              :can="can"
              @edit="openEdit"
              @approve="approveDeposit"
              @delete="deleteDeposit"
              @share="openShareForDeposit"
              @create="openCreate"
            />
          </el-tab-pane>
          <el-tab-pane label="Calendario" name="calendar">
            <DepositCalendarView
              :events="calendarEvents"
              :can="can"
              @create="openCreate"
              @create-with-date="openCreate"
              @edit="openEdit"
              @approve="approveDeposit"
              @share-day="openShareForDay"
            />
          </el-tab-pane>
        </el-tabs>
      </div>
    </div>

    <!-- Deposit form dialog -->
    <DepositForm
      v-if="showFormDialog"
      ref="depositFormRef"
      v-model="showFormDialog"
      :deposit="editingDeposit"
      :deposit-types="depositTypes"
      :default-shift="defaultShift"
      :preselected-date="preselectedDate"
      :can="can"
      @saved="onFormSaved"
    />

    <!-- Share link dialog -->
    <ShareLinkDialog
      v-if="showShareDialog"
      v-model="showShareDialog"
      :target="shareTarget"
    />

    <!-- Deposit types manager -->
    <DepositTypesManager
      v-if="showTypesManager"
      v-model="showTypesManager"
      @types-changed="onTypesChanged"
    />
  </AppLayout>
</template>
