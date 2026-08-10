<script setup>
import { ref, onMounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { Document, Edit, Delete, Check, CircleCheck, Share } from '@element-plus/icons-vue'
import axios from 'axios'
import CompleteDepositModal from '@/Pages/Public/Deposits/Partials/CompleteDepositModal.vue'

const props = defineProps({
  deposits: Object,
  filters: Object,
  can: Object,
})

const emit = defineEmits(['edit', 'approve', 'delete', 'share', 'create'])

function statusColor(status) {
  return { pending: 'warning', approved: 'success', completed: 'info' }[status] || 'info'
}

function statusLabel(status) {
  return { pending: 'Pendiente', approved: 'Aprobado', completed: 'Completado' }[status] || status
}

function shiftLabel(shift) {
  return shift === 'matutino' ? 'Matutino' : 'Vespertino'
}

function formatDate(dateString) {
  if (!dateString) return ''
  // Handle both "2026-07-15" and "2026-07-15T00:00:00.000000Z" formats
  const datePart = dateString.split('T')[0]
  const date = new Date(datePart + 'T12:00:00Z')
  return date.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' })
}

function formatAmount(amount) {
  return Number(amount).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function ticketRoute(ticketId) {
  return route('tickets.show', ticketId)
}

function depositRecipient(deposit) {
  if (deposit.is_external) {
    return deposit.external_beneficiary_name || 'Depósito externo'
  }
  return deposit.technician?.user?.name ?? 'N/A'
}

// --- Complete deposit modal ---
const completingDeposit = ref(null)
const showCompleteModal = ref(false)

function openCompleteModal(deposit) {
  completingDeposit.value = deposit
  showCompleteModal.value = true
}

function onCompleted() {
  showCompleteModal.value = false
  completingDeposit.value = null
  router.reload()
}

// --- Technician filter (load all external technicians on mount) ---
const allTechnicians = ref([])
const techniciansLoading = ref(false)

async function loadTechnicians() {
  techniciansLoading.value = true
  try {
    const { data } = await axios.get(route('technicians.index'), {
      params: { is_internal: false, perPage: 200 }
    })
    allTechnicians.value = (data.data || data) ?? []
  } catch {
    allTechnicians.value = []
  } finally {
    techniciansLoading.value = false
  }
}

onMounted(() => {
  loadTechnicians()
})

const getTechLabel = (tech) => {
    let label = tech.user?.name ?? tech.name ?? 'N/A';
    label += tech.is_internal ? ' (Interno)' : ' (Externo)';
    if (tech.state) {
        label += ` — ${tech.state}`;
    }
    return label;
};

function updateFilter(key, value) {
  const params = { ...props.filters }
  // Always include the param, even if empty string (means "show all")
  params[key] = value ?? ''
  router.get(route('deposits.index'), params, { preserveState: true, replace: true })
}
</script>

<template>
  <div class="p-4">
    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-4">
      <el-select
        :model-value="filters.technician_id ? Number(filters.technician_id) : ''"
        filterable
        :loading="techniciansLoading"
        placeholder="Buscar por nombre de técnico"
        class="w-60"
        clearable
        @change="updateFilter('technician_id', $event)"
      >
        <el-option
          v-for="tech in allTechnicians"
          :key="tech.id"
          :label="getTechLabel(tech)"
          :value="tech.id"
        />
      </el-select>
      <el-select
        :model-value="filters.status ?? ''"
        placeholder="Estado"
        class="w-40"
        @change="updateFilter('status', $event)"
      >
        <el-option label="Pendiente" value="pending" />
        <el-option label="Aprobado" value="approved" />
        <el-option label="Completado" value="completed" />
        <el-option label="Todos los estados" value="" />
      </el-select>
      <el-select
        :model-value="filters.shift ?? ''"
        placeholder="Turno"
        class="w-40"
        @change="updateFilter('shift', $event)"
      >
        <el-option label="Ambos turnos" value="" />
        <el-option label="Matutino" value="matutino" />
        <el-option label="Vespertino" value="vespertino" />
      </el-select>
      <el-button v-if="can.create" type="primary" @click="$emit('create')">
        Nuevo depósito
      </el-button>
    </div>

    <!-- Table -->
    <el-table :data="deposits.data" stripe class="w-full">
      <el-table-column :label="(deposits.data || []).some(d => d.is_external) ? 'Técnico / Beneficiario' : 'Técnico'" min-width="160">
        <template #default="{ row }">
          <div class="flex items-center gap-2">
            <span class="font-medium">{{ depositRecipient(row) }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="Ticket" min-width="140">
        <template #default="{ row }">
          <Link
            v-if="can.viewTickets && row.ticket?.id"
            :href="ticketRoute(row.ticket.id)"
            class="text-primary hover:underline font-medium"
          >
            {{ row.ticket?.folio ?? 'N/A' }}
          </Link>
          <span v-else>{{ row.ticket?.folio ?? 'N/A' }}</span>
        </template>
      </el-table-column>
      <el-table-column label="Tipo" min-width="110">
        <template #default="{ row }">
          {{ row.deposit_type?.name ?? 'N/A' }}
        </template>
      </el-table-column>
      <el-table-column label="Monto" width="140" align="right">
        <template #default="{ row }">
          <span class="font-mono">${{ formatAmount(row.amount) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="Comisión" width="120" align="right">
        <template #default="{ row }">
          <span v-if="row.commission_amount != null" class="font-mono text-emerald-600 dark:text-emerald-400">
            ${{ formatAmount(row.commission_amount) }}
          </span>
          <span v-else class="text-gray-400">—</span>
        </template>
      </el-table-column>
      <el-table-column label="Comprobante" width="130">
        <template #default="{ row }">
          <a
            v-if="row.voucher_url"
            :href="row.voucher_url"
            target="_blank"
            rel="noopener"
            class="text-primary hover:underline inline-flex items-center gap-1"
          >
            <el-icon><Document /></el-icon>
            Ver comprobante
          </a>
          <span v-else class="text-gray-400">—</span>
        </template>
      </el-table-column>
      <el-table-column label="Turno" width="100">
        <template #default="{ row }">
          {{ shiftLabel(row.shift) }}
        </template>
      </el-table-column>
      <el-table-column label="Fecha" width="130">
        <template #default="{ row }">
          {{ formatDate(row.scheduled_date) }}
        </template>
      </el-table-column>
      <el-table-column label="Estado" width="110">
        <template #default="{ row }">
          <el-tag :type="statusColor(row.status)" size="small">
            {{ statusLabel(row.status) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Aprobado por" min-width="140">
        <template #default="{ row }">
          {{ row.approved_by?.name ?? '—' }}
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="290" fixed="right">
        <template #default="{ row }">
          <div class="flex gap-1">
            <el-button
              v-if="row.status === 'pending' && can.approve"
              type="success"
              size="small"
              :icon="Check"
              @click="$emit('approve', row)"
            >
              Aprobar
            </el-button>
            <el-button
              v-if="row.status === 'approved' && can.approve"
              type="primary"
              size="small"
              :icon="CircleCheck"
              @click="openCompleteModal(row)"
            >
              Realizado
            </el-button>
            <el-button
              v-if="row.status !== 'completed' && can.edit"
              size="small"
              :icon="Edit"
              @click="$emit('edit', row)"
            />
            <el-button
              v-if="row.status === 'approved' || row.status === 'completed'"
              size="small"
              :icon="Share"
              @click="$emit('share', row)"
            />
            <el-button
              v-if="row.status !== 'completed' && can.delete"
              type="danger"
              size="small"
              :icon="Delete"
              @click="$emit('delete', row)"
            />
          </div>
        </template>
      </el-table-column>
    </el-table>

    <!-- Pagination -->
    <div class="mt-4 flex justify-center" v-if="deposits.links">
      <el-pagination
        :current-page="deposits.current_page"
        :total="deposits.total"
        :page-size="deposits.per_page"
        layout="prev, pager, next"
        background
        @current-change="(page) => router.get(route('deposits.index'), { ...filters, page }, { preserveState: true })"
      />
    </div>

    <!-- Complete deposit modal -->
    <CompleteDepositModal
      v-if="completingDeposit"
      v-model="showCompleteModal"
      :deposit="completingDeposit"
      :complete-url="route('deposits.complete', completingDeposit.id)"
      @update:model-value="showCompleteModal = false; completingDeposit = null"
      @completed="onCompleted"
    />
  </div>
</template>