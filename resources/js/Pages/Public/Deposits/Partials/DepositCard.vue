<script setup>
defineProps({
  deposit: Object,
  expanded: Boolean,
  bankQrUrl: { type: String, default: null },
  formatAmount: Function,
  formatDate: Function,
  formatDateTime: Function,
  statusLabel: Function,
  statusTagType: Function,
})

defineEmits(['toggle', 'complete'])
</script>

<template>
  <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
    <!-- Pending: only show notice -->
    <div v-if="deposit.status === 'pending'" class="bg-orange-50 dark:bg-orange-900/30 rounded-lg p-4 text-sm text-orange-700 dark:text-orange-300">
      <p class="font-bold mb-1">{{ deposit.technician?.user?.name ?? 'N/A' }}</p>
      <p>Este depósito aún no ha sido autorizado. La información no se puede mostrar hasta que sea aprobado.</p>
    </div>

    <!-- Approved or completed -->
    <template v-if="deposit.status !== 'pending'">
      <!-- Summary row -->
      <div class="flex justify-between items-start mb-3">
        <div>
          <p class="font-bold text-gray-800 dark:text-white">{{ deposit.technician?.user?.name ?? 'N/A' }}</p>
          <p class="text-sm text-gray-500">{{ deposit.deposit_type?.name ?? 'N/A' }} — {{ deposit.ticket?.folio ?? 'N/A' }}</p>
        </div>
        <div class="text-right">
          <p class="font-mono font-bold text-gray-800 dark:text-white">${{ formatAmount(deposit.amount) }}</p>
          <el-tag :type="statusTagType(deposit.status)" size="small">
            {{ statusLabel(deposit.status) }}
          </el-tag>
        </div>
      </div>

      <!-- Completed badge -->
      <div v-if="deposit.status === 'completed'" class="bg-green-50 dark:bg-green-900/30 rounded-lg p-3 text-xs text-green-700 dark:text-green-300 mt-2">
        ✓ Completado {{ formatDateTime(deposit.completed_at) }}
        <span v-if="deposit.commission_amount"> — Comisión: ${{ formatAmount(deposit.commission_amount) }}</span>
      </div>

      <!-- Expand toggle -->
      <div class="mt-3 text-right">
        <el-button
          text
          size="small"
          type="primary"
          @click="$emit('toggle')"
        >
          {{ expanded ? 'Ocultar detalles ↑' : 'Ver detalles →' }}
        </el-button>
      </div>

      <!-- Expandable full details (same structure as public Show view) -->
      <div v-if="expanded" class="space-y-4 mt-4">

        <!-- Técnico -->
        <div>
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Técnico</h3>
          <p class="font-bold text-gray-800 dark:text-white">{{ deposit.technician?.user?.name ?? 'N/A' }}</p>
        </div>

        <!-- Detalles del depósito -->
        <div>
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Detalles del depósito</h3>
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
              <span class="text-xs text-gray-400">Monto</span>
              <p class="font-mono font-bold text-gray-800 dark:text-white">${{ formatAmount(deposit.amount) }}</p>
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
              <p class="text-gray-800 dark:text-white">{{ formatDate(deposit.scheduled_date) }}</p>
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

        <!-- Información bancaria -->
        <div v-if="deposit.status === 'approved' || deposit.status === 'completed'">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Información bancaria</h3>
          <div class="grid grid-cols-1 gap-3 text-sm">
            <div>
              <span class="text-xs text-gray-400">Banco</span>
              <p class="font-medium text-gray-800 dark:text-white">{{ deposit.bank_account?.bank_name ?? 'N/A' }}</p>
            </div>
            <div v-if="deposit.bank_account?.card_owner_name">
              <span class="text-xs text-gray-400">Titular de la tarjeta</span>
              <p class="text-gray-800 dark:text-white">{{ deposit.bank_account?.card_owner_name }}</p>
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

          <!-- QR image -->
          <div v-if="bankQrUrl" class="mt-4 flex justify-center">
            <img :src="bankQrUrl" alt="QR de pago" class="max-w-[200px] rounded-lg border" />
          </div>
        </div>

        <!-- Complete button (only if approved) -->
        <div v-if="deposit.status === 'approved'" class="text-center pt-2">
          <el-button type="primary" @click="$emit('complete')">
            Marcar como realizado
          </el-button>
        </div>
      </div>
    </template>
  </div>
</template>
