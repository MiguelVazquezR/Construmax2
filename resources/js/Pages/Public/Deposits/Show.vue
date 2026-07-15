<script setup>
import { Head } from '@inertiajs/vue3'
import CompleteDepositModal from './Partials/CompleteDepositModal.vue'
import { ref, computed } from 'vue'

const props = defineProps({
  deposit: Object,
})

const showCompleteModal = ref(false)

const canComplete = computed(() => props.deposit.status === 'approved')

function onCompleted() {
  showCompleteModal.value = false
  // Reload page to reflect changes
  window.location.reload()
}
</script>

<template>
  <Head :title="`Depósito #${deposit.id}`" />

  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="max-w-2xl mx-auto p-4 sm:p-6 lg:p-8">

      <!-- Header -->
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
        <h1 class="text-xl font-bold text-gray-800 dark:text-white mb-2">
          Depósito #{{ deposit.id }}
        </h1>
        <el-tag :type="deposit.status === 'approved' ? 'success' : deposit.status === 'completed' ? 'info' : 'warning'" size="large">
          {{ deposit.status }}
        </el-tag>
      </div>

      <!-- Pending notice -->
      <div v-if="deposit.status === 'pending'" class="bg-orange-50 dark:bg-orange-900/30 border border-orange-200 dark:border-orange-700 rounded-xl p-6 mb-6">
        <div class="flex items-start gap-3">
          <el-icon class="text-orange-500 mt-0.5" :size="20"><Warning /></el-icon>
          <div>
            <h3 class="text-sm font-bold text-orange-800 dark:text-orange-200 mb-1">Pendiente de aprobación</h3>
            <p class="text-sm text-orange-700 dark:text-orange-300">
              Este depósito aún no ha sido autorizado. La información bancaria no se puede mostrar hasta que sea aprobado.
            </p>
          </div>
        </div>
      </div>

      <!-- Technician info -->
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Técnico</h2>
        <p class="text-lg font-bold text-gray-800 dark:text-white">{{ deposit.technician?.user?.name ?? 'N/A' }}</p>
      </div>

      <!-- Deposit details -->
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Detalles del depósito</h2>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <span class="text-xs text-gray-400">Monto</span>
            <p class="font-mono font-bold text-gray-800 dark:text-white">${{ Number(deposit.amount).toFixed(2) }}</p>
          </div>
          <div>
            <span class="text-xs text-gray-400">Tipo</span>
            <p class="text-gray-800 dark:text-white">{{ deposit.deposit_type?.name ?? 'N/A' }}</p>
          </div>
          <div>
            <span class="text-xs text-gray-400">Turno</span>
            <p class="text-gray-800 dark:text-white">{{ deposit.shift === 'matutino' ? 'Matutino' : 'Vespertino' }}</p>
          </div>
          <div>
            <span class="text-xs text-gray-400">Fecha</span>
            <p class="text-gray-800 dark:text-white">{{ deposit.scheduled_date }}</p>
          </div>
          <div v-if="deposit.approved_by">
            <span class="text-xs text-gray-400">Aprobado por</span>
            <p class="text-gray-800 dark:text-white">{{ deposit.approved_by?.name ?? 'N/A' }}</p>
          </div>
          <div v-if="deposit.notes">
            <span class="text-xs text-gray-400">Notas</span>
            <p class="text-gray-800 dark:text-white">{{ deposit.notes }}</p>
          </div>
        </div>
      </div>

      <!-- Banking details (only if approved or completed) -->
      <div
        v-if="deposit.status === 'approved' || deposit.status === 'completed'"
        class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6"
      >
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Información bancaria</h2>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <span class="text-xs text-gray-400">Banco</span>
            <p class="font-medium text-gray-800 dark:text-white">{{ deposit.bank_account?.bank_name ?? 'N/A' }}</p>
          </div>
          <div>
            <span class="text-xs text-gray-400">Número de cuenta</span>
            <p class="font-mono text-gray-800 dark:text-white">{{ deposit.bank_account?.account_number ?? 'N/A' }}</p>
          </div>
          <div>
            <span class="text-xs text-gray-400">CLABE</span>
            <p class="font-mono text-gray-800 dark:text-white">{{ deposit.bank_account?.clabe ?? 'N/A' }}</p>
          </div>
          <div>
            <span class="text-xs text-gray-400">Número de tarjeta</span>
            <p class="font-mono text-gray-800 dark:text-white">{{ deposit.bank_account?.card_number ?? 'N/A' }}</p>
          </div>
          <div>
            <span class="text-xs text-gray-400">Número de sucursal</span>
            <p class="font-mono text-gray-800 dark:text-white">{{ deposit.bank_account?.branch_number ?? 'N/A' }}</p>
          </div>
        </div>
      </div>

      <!-- Completed badge -->
      <div v-if="deposit.status === 'completed'" class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-xl p-6 mb-6">
        <div class="flex items-start gap-3">
          <el-icon class="text-green-500 mt-0.5" :size="20"><CircleCheck /></el-icon>
          <div>
            <h3 class="text-sm font-bold text-green-800 dark:text-green-200 mb-1">Depositado</h3>
            <p class="text-sm text-green-700 dark:text-green-300">
              Completado el {{ deposit.completed_at }}
              <span v-if="deposit.commission_amount"> — Comisión: ${{ Number(deposit.commission_amount).toFixed(2) }}</span>
            </p>
          </div>
        </div>
      </div>

      <!-- Complete button -->
      <div v-if="canComplete" class="text-center">
        <el-button type="primary" size="large" @click="showCompleteModal = true">
          Marcar como realizado
        </el-button>
      </div>

      <!-- Complete modal -->
      <CompleteDepositModal
        v-if="showCompleteModal"
        v-model="showCompleteModal"
        :deposit="deposit"
        @completed="onCompleted"
      />
    </div>
  </div>
</template>
