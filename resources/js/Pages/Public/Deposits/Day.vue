<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import CompleteDepositModal from './Partials/CompleteDepositModal.vue'
import DepositCard from './Partials/DepositCard.vue'

const props = defineProps({
  deposits: Object,   // { matutino: [...], vespertino: [...] }
  date: String,
})

const matutinoDeposits = computed(() => props.deposits?.matutino || [])
const vespertinoDeposits = computed(() => props.deposits?.vespertino || [])

// Track which deposit cards are expanded
const expandedIds = ref({})

function toggleExpand(id) {
  expandedIds.value[id] = !expandedIds.value[id]
}

function isExpanded(id) {
  return !!expandedIds.value[id]
}

// Track which deposit is showing the complete modal
const completingDeposit = ref(null)
const showCompleteModal = ref(false)

function openCompleteModal(deposit) {
  completingDeposit.value = deposit
  showCompleteModal.value = true
}

function onCompleted() {
  showCompleteModal.value = false
  completingDeposit.value = null
  window.location.reload()
}

function parseDate(dateString) {
  if (!dateString) return null
  const datePart = dateString.split('T')[0]
  return new Date(datePart + 'T12:00:00Z')
}

function formatAmount(amount) {
  return Number(amount).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(dateString) {
  const date = parseDate(dateString)
  if (!date) return ''
  return date.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' })
}

function formatDateTime(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return ''
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
      <template v-if="matutinoDeposits.length">
        <h2 class="text-lg font-bold text-blue-600 dark:text-blue-400 mb-4 flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-blue-500 inline-block"></span>
          Matutino ({{ matutinoDeposits.length }})
        </h2>
        <div class="space-y-4 mb-8">
          <DepositCard
            v-for="d in matutinoDeposits"
            :key="d.id"
            :deposit="d"
            :expanded="isExpanded(d.id)"
            :bank-qr-url="bankQrUrl(d.bank_account)"
            :format-amount="formatAmount"
            :format-date="formatDate"
            :format-date-time="formatDateTime"
            :status-label="statusLabel"
            :status-tag-type="statusTagType"
            @toggle="toggleExpand(d.id)"
            @complete="openCompleteModal(d)"
          />
        </div>
      </template>

      <!-- Vespertino -->
      <template v-if="vespertinoDeposits.length">
        <h2 class="text-lg font-bold text-orange-600 dark:text-orange-400 mb-4 flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-orange-500 inline-block"></span>
          Vespertino ({{ vespertinoDeposits.length }})
        </h2>
        <div class="space-y-4 mb-8">
          <DepositCard
            v-for="d in vespertinoDeposits"
            :key="d.id"
            :deposit="d"
            :expanded="isExpanded(d.id)"
            :bank-qr-url="bankQrUrl(d.bank_account)"
            :format-amount="formatAmount"
            :format-date="formatDate"
            :format-date-time="formatDateTime"
            :status-label="statusLabel"
            :status-tag-type="statusTagType"
            @toggle="toggleExpand(d.id)"
            @complete="openCompleteModal(d)"
          />
        </div>
      </template>

      <!-- Empty state -->
      <div v-if="!matutinoDeposits.length && !vespertinoDeposits.length" class="text-center py-12 text-gray-400">
        No hay depósitos programados para esta fecha.
      </div>
    </div>

    <!-- Complete modal -->
    <CompleteDepositModal
      v-if="completingDeposit"
      v-model="showCompleteModal"
      :deposit="completingDeposit"
      :complete-url="completingDeposit.complete_url"
      @update:model-value="showCompleteModal = false; completingDeposit = null"
      @completed="onCompleted"
    />
  </div>
</template>

