<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  deposits: Object,   // { matutino: [...], vespertino: [...] }
  date: String,
})

const matutinoDeposits = computed(() => props.deposits?.matutino || [])
const vespertinoDeposits = computed(() => props.deposits?.vespertino || [])

function formatAmount(amount) {
  return Number(amount).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString + 'T00:00:00')
  return date.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatDateTime(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function statusLabel(status) {
  return { pending: 'Pendiente', approved: 'Aprobado', completed: 'Realizado' }[status] || status
}

function statusTagType(status) {
  return { pending: 'warning', approved: 'success', completed: 'info' }[status] || 'info'
}

function bankQrUrl(account) {
  if (!account?.media) return null
  const qrMedia = account.media.find(m => m.collection_name === 'bank_qr')
  return qrMedia?.original_url ?? null
}
</script>

<template>
  <Head :title="`Depósitos del ${date}`" />

  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="max-w-3xl mx-auto p-4 sm:p-6 lg:p-8">

      <!-- Header -->
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-8">
        <h1 class="text-xl font-bold text-gray-800 dark:text-white">
          Depósitos del {{ formatDate(date) }}
        </h1>
        <p class="text-sm text-gray-500 mt-1">
          {{ matutinoDeposits.length + vespertinoDeposits.length }} depósito(s) programado(s)
        </p>
      </div>

      <!-- Matutino -->
      <div v-if="matutinoDeposits.length" class="mb-8">
        <h2 class="text-lg font-bold text-blue-600 dark:text-blue-400 mb-4 flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-blue-500 inline-block"></span>
          Matutino ({{ matutinoDeposits.length }})
        </h2>
        <div class="space-y-4">
          <div
            v-for="d in matutinoDeposits"
            :key="d.id"
            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5"
          >
            <div class="flex justify-between items-start mb-3">
              <div>
                <p class="font-bold text-gray-800 dark:text-white">{{ d.technician?.user?.name ?? 'N/A' }}</p>
                <p class="text-sm text-gray-500">{{ d.deposit_type?.name ?? 'N/A' }} — {{ d.ticket?.folio ?? 'N/A' }}</p>
              </div>
              <div class="text-right">
                <p class="font-mono font-bold text-gray-800 dark:text-white">${{ formatAmount(d.amount) }}</p>
                <el-tag :type="statusTagType(d.status)" size="small">
                  {{ statusLabel(d.status) }}
                </el-tag>
              </div>
            </div>

            <!-- Banking info for approved/completed -->
            <div v-if="d.status === 'approved' || d.status === 'completed'" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-xs mt-2">
              <div class="grid grid-cols-2 gap-2">
                <div><span class="text-gray-400">Banco:</span> {{ d.bank_account?.bank_name ?? 'N/A' }}</div>
                <div><span class="text-gray-400">Cuenta:</span> {{ d.bank_account?.account_number ?? 'N/A' }}</div>
                <div><span class="text-gray-400">CLABE:</span> {{ d.bank_account?.clabe ?? 'N/A' }}</div>
                <div><span class="text-gray-400">Tarjeta:</span> {{ d.bank_account?.card_number ?? 'N/A' }}</div>
                <div v-if="d.approved_by"><span class="text-gray-400">Aprobado por:</span> {{ d.approved_by?.name ?? 'N/A' }}</div>
              </div>
              <!-- QR image -->
              <div v-if="bankQrUrl(d.bank_account)" class="mt-3 flex justify-center">
                <img :src="bankQrUrl(d.bank_account)" alt="QR de pago" class="max-w-[160px] rounded-lg border" />
              </div>
            </div>

            <!-- Pending notice -->
            <div v-if="d.status === 'pending'" class="bg-orange-50 dark:bg-orange-900/30 rounded-lg p-3 text-xs text-orange-700 dark:text-orange-300 mt-2">
              Este depósito aún no ha sido autorizado.
            </div>

            <!-- Completed badge -->
            <div v-if="d.status === 'completed'" class="bg-green-50 dark:bg-green-900/30 rounded-lg p-3 text-xs text-green-700 dark:text-green-300 mt-2">
              ✓ Completado {{ formatDateTime(d.completed_at) }}
              <span v-if="d.commission_amount"> — Comisión: ${{ formatAmount(d.commission_amount) }}</span>
            </div>

            <!-- Direct link -->
            <div class="mt-3 text-right">
              <a
                :href="route('public.deposits.show', d.id)"
                class="text-xs text-primary hover:underline"
              >
                Ver detalles →
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Vespertino -->
      <div v-if="vespertinoDeposits.length" class="mb-8">
        <h2 class="text-lg font-bold text-orange-600 dark:text-orange-400 mb-4 flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-orange-500 inline-block"></span>
          Vespertino ({{ vespertinoDeposits.length }})
        </h2>
        <div class="space-y-4">
          <div
            v-for="d in vespertinoDeposits"
            :key="d.id"
            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5"
          >
            <div class="flex justify-between items-start mb-3">
              <div>
                <p class="font-bold text-gray-800 dark:text-white">{{ d.technician?.user?.name ?? 'N/A' }}</p>
                <p class="text-sm text-gray-500">{{ d.deposit_type?.name ?? 'N/A' }} — {{ d.ticket?.folio ?? 'N/A' }}</p>
              </div>
              <div class="text-right">
                <p class="font-mono font-bold text-gray-800 dark:text-white">${{ formatAmount(d.amount) }}</p>
                <el-tag :type="statusTagType(d.status)" size="small">
                  {{ statusLabel(d.status) }}
                </el-tag>
              </div>
            </div>

            <div v-if="d.status === 'approved' || d.status === 'completed'" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-xs mt-2">
              <div class="grid grid-cols-2 gap-2">
                <div><span class="text-gray-400">Banco:</span> {{ d.bank_account?.bank_name ?? 'N/A' }}</div>
                <div><span class="text-gray-400">Cuenta:</span> {{ d.bank_account?.account_number ?? 'N/A' }}</div>
                <div><span class="text-gray-400">CLABE:</span> {{ d.bank_account?.clabe ?? 'N/A' }}</div>
                <div><span class="text-gray-400">Tarjeta:</span> {{ d.bank_account?.card_number ?? 'N/A' }}</div>
                <div v-if="d.approved_by"><span class="text-gray-400">Aprobado por:</span> {{ d.approved_by?.name ?? 'N/A' }}</div>
              </div>
              <div v-if="bankQrUrl(d.bank_account)" class="mt-3 flex justify-center">
                <img :src="bankQrUrl(d.bank_account)" alt="QR de pago" class="max-w-[160px] rounded-lg border" />
              </div>
            </div>

            <div v-if="d.status === 'pending'" class="bg-orange-50 dark:bg-orange-900/30 rounded-lg p-3 text-xs text-orange-700 dark:text-orange-300 mt-2">
              Este depósito aún no ha sido autorizado.
            </div>

            <div v-if="d.status === 'completed'" class="bg-green-50 dark:bg-green-900/30 rounded-lg p-3 text-xs text-green-700 dark:text-green-300 mt-2">
              ✓ Completado {{ formatDateTime(d.completed_at) }}
              <span v-if="d.commission_amount"> — Comisión: ${{ formatAmount(d.commission_amount) }}</span>
            </div>

            <div class="mt-3 text-right">
              <a
                :href="route('public.deposits.show', d.id)"
                class="text-xs text-primary hover:underline"
              >
                Ver detalles →
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="!matutinoDeposits.length && !vespertinoDeposits.length" class="text-center py-12 text-gray-400">
        No hay depósitos programados para esta fecha.
      </div>
    </div>
  </div>
</template>
