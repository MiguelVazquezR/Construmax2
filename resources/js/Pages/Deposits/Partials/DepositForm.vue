<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { ElMessage } from 'element-plus'
import axios from 'axios'

const props = defineProps({
  modelValue: Boolean,
  deposit: { type: Object, default: null },
  depositTypes: Array,
  defaultShift: String,
  can: Object,
  preselectedDate: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const isEditing = computed(() => !!props.deposit)

const dialogVisible = ref(props.modelValue)
watch(() => props.modelValue, (v) => { dialogVisible.value = v })
watch(dialogVisible, (v) => emit('update:modelValue', v))

const form = useForm({
  technician_id: props.deposit?.technician_id ?? null,
  technician_bank_account_id: props.deposit?.technician_bank_account_id ?? null,
  ticket_id: props.deposit?.ticket_id ?? null,
  deposit_type_id: props.deposit?.deposit_type_id ?? null,
  amount: props.deposit?.amount ?? '',
  shift: props.deposit?.shift ?? props.defaultShift,
  scheduled_date: props.deposit?.scheduled_date ?? props.preselectedDate ?? '',
  notes: props.deposit?.notes ?? '',
})

// --- Deposit types (fetched independently for instant refresh) ---
const localDepositTypes = ref([])

async function loadDepositTypes() {
  try {
    const { data } = await axios.get(route('deposits.types.index'))
    localDepositTypes.value = data.filter(t => t.is_active)
  } catch { /* use prop fallback */ }
}

onMounted(() => {
  loadDepositTypes()
  loadAllTechnicians()
})

// Expose a refresh method for parent
defineExpose({ refreshTypes: loadDepositTypes })

const displayTypes = computed(() => localDepositTypes.value.length ? localDepositTypes.value : props.depositTypes)

// --- Technician search (load all on mount, filter locally) ---
const technicianSearch = ref('')
const allTechnicians = ref([])
const techniciansLoading = ref(false)

async function loadAllTechnicians() {
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

const filteredTechnicians = computed(() => {
  if (!technicianSearch.value || technicianSearch.value.length < 1) {
    return allTechnicians.value
  }
  const q = technicianSearch.value.toLowerCase()
  return allTechnicians.value.filter(t => (t.user?.name ?? '').toLowerCase().includes(q))
})

// --- Bank accounts ---
const bankAccounts = ref([])
const selectedBankAccount = ref(null)
const bankAccountsLoading = ref(false)

async function loadBankAccounts(technicianId) {
  if (!technicianId) {
    bankAccounts.value = []
    selectedBankAccount.value = null
    return
  }
  bankAccountsLoading.value = true
  try {
    const { data } = await axios.get(route('deposits.technician-bank-accounts', technicianId))
    bankAccounts.value = data
    const fav = data.find(a => a.is_favorite)
    if (fav && !form.technician_bank_account_id) {
      form.technician_bank_account_id = fav.id
      selectedBankAccount.value = fav
    }
  } catch {
    bankAccounts.value = []
    selectedBankAccount.value = null
  } finally {
    bankAccountsLoading.value = false
  }
}

function onBankAccountChange(accountId) {
  selectedBankAccount.value = bankAccounts.value.find(a => a.id === accountId) || null
}

// --- Pending tickets ---
const pendingTickets = ref([])
const ticketsLoading = ref(false)
const selectedTicket = ref(null)
const ticketPendingAmount = ref(0)

async function loadPendingTickets(technicianId) {
  if (!technicianId) {
    pendingTickets.value = []
    return
  }
  ticketsLoading.value = true
  try {
    const { data } = await axios.get(route('deposits.technician-pending-tickets', technicianId))
    pendingTickets.value = data
  } catch {
    pendingTickets.value = []
  } finally {
    ticketsLoading.value = false
  }
}

function onTechnicianChange(technicianId) {
  form.technician_bank_account_id = null
  form.ticket_id = null
  selectedTicket.value = null
  selectedBankAccount.value = null
  ticketPendingAmount.value = 0
  bankAccounts.value = []
  pendingTickets.value = []
  if (technicianId) {
    loadBankAccounts(technicianId)
    loadPendingTickets(technicianId)
  }
}

function onTicketChange(ticketId) {
  const ticket = pendingTickets.value.find(t => t.id === ticketId)
  selectedTicket.value = ticket || null
  ticketPendingAmount.value = ticket?.pending_amount ?? 0
}

// --- Submission ---
function submit() {
  if (isEditing.value) {
    form.put(route('deposits.update', props.deposit.id), {
      onSuccess: () => {
        ElMessage.success('Depósito actualizado correctamente.')
        emit('saved')
      },
    })
  } else {
    form.post(route('deposits.store'), {
      onSuccess: () => {
        ElMessage.success('Depósito programado correctamente.')
        emit('saved')
      },
    })
  }
}
</script>

<template>
  <el-dialog
    v-model="dialogVisible"
    :title="isEditing ? 'Editar depósito' : 'Nuevo depósito'"
    width="640px"
    destroy-on-close
    @closed="form.reset()"
  >
    <el-form :model="form" label-position="top">
      <!-- Technician -->
      <el-form-item label="Técnico" required>
        <el-select
          v-model="form.technician_id"
          filterable
          :filter-method="(val) => { technicianSearch = val }"
          :loading="techniciansLoading"
          placeholder="Buscar por nombre"
          class="w-full"
          @change="onTechnicianChange"
        >
          <el-option
            v-for="tech in filteredTechnicians"
            :key="tech.id"
            :label="tech.user?.name ?? tech.name"
            :value="tech.id"
          />
        </el-select>
      </el-form-item>

      <!-- Bank account -->
      <el-form-item label="Cuenta bancaria" required>
        <el-select
          v-model="form.technician_bank_account_id"
          :loading="bankAccountsLoading"
          :disabled="!form.technician_id || bankAccounts.length === 0"
          placeholder="Seleccionar cuenta bancaria"
          class="w-full"
          @change="onBankAccountChange"
        >
          <el-option
            v-for="acc in bankAccounts"
            :key="acc.id"
            :label="`${acc.bank_name ?? 'Banco'} — ...${(acc.account_number ?? acc.card_number ?? '').slice(-4)}${acc.is_favorite ? ' ★' : ''}`"
            :value="acc.id"
          />
        </el-select>
        <p v-if="form.technician_id && bankAccounts.length === 0 && !bankAccountsLoading" class="text-sm text-gray-400 mt-1">
          Este técnico no tiene cuentas bancarias registradas.
        </p>
        <!-- Bank account detail card -->
        <div v-if="selectedBankAccount" class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 mt-2 text-xs">
          <div class="grid grid-cols-2 gap-1">
            <div><span class="text-gray-500">Banco:</span> <span class="font-medium">{{ selectedBankAccount.bank_name ?? 'N/A' }}</span></div>
            <div><span class="text-gray-500">Cuenta:</span> <span class="font-mono">{{ selectedBankAccount.account_number ?? 'N/A' }}</span></div>
            <div><span class="text-gray-500">CLABE:</span> <span class="font-mono">{{ selectedBankAccount.clabe ?? 'N/A' }}</span></div>
            <div><span class="text-gray-500">Tarjeta:</span> <span class="font-mono">{{ selectedBankAccount.card_number ?? 'N/A' }}</span></div>
            <div v-if="selectedBankAccount.branch_number"><span class="text-gray-500">Sucursal:</span> {{ selectedBankAccount.branch_number }}</div>
          </div>
        </div>
      </el-form-item>

      <!-- Ticket -->
      <el-form-item label="Ticket" required>
        <el-select
          v-model="form.ticket_id"
          :loading="ticketsLoading"
          :disabled="!form.technician_id || pendingTickets.length === 0"
          placeholder="Seleccionar ticket"
          class="w-full"
          @change="onTicketChange"
        >
          <el-option
            v-for="ticket in pendingTickets"
            :key="ticket.id"
            :label="`${ticket.folio} — ${ticket.customer_name} (Pendiente: $${Number(ticket.pending_amount).toFixed(2)})`"
            :value="ticket.id"
          />
        </el-select>
        <p v-if="form.technician_id && pendingTickets.length === 0 && !ticketsLoading" class="text-sm text-gray-400 mt-1">
          Este técnico no tiene pagos pendientes.
        </p>
      </el-form-item>

      <!-- Ticket summary card -->
      <div v-if="selectedTicket" class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 mb-4 text-sm">
        <div class="flex justify-between">
          <span class="font-semibold">{{ selectedTicket.folio }}</span>
          <span>{{ selectedTicket.customer_name }}</span>
        </div>
        <div class="text-gray-500 mt-1">Monto pendiente: ${{ Number(selectedTicket.pending_amount).toFixed(2) }}</div>
      </div>

      <!-- Amount -->
      <el-form-item label="Monto" required>
        <el-input-number
          v-model="form.amount"
          :min="0.01"
          :precision="2"
          :controls="false"
          class="w-full"
          placeholder="0.00"
        />
        <p v-if="selectedTicket && Number(form.amount) > ticketPendingAmount" class="text-red-500 text-xs mt-1">
          Advertencia: este monto excede el saldo pendiente de ${{ Number(ticketPendingAmount).toFixed(2) }} para este técnico.
        </p>
      </el-form-item>

      <!-- Shift -->
      <el-form-item label="Turno" required>
        <el-radio-group v-model="form.shift">
          <el-radio value="matutino">Matutino</el-radio>
          <el-radio value="vespertino">Vespertino</el-radio>
        </el-radio-group>
      </el-form-item>

      <!-- Scheduled date -->
      <el-form-item label="Fecha programada" required>
        <el-date-picker v-model="form.scheduled_date" type="date" class="w-full" placeholder="Seleccionar fecha" />
      </el-form-item>

      <!-- Deposit type -->
      <el-form-item label="Tipo de depósito" required>
        <el-select v-model="form.deposit_type_id" placeholder="Seleccionar tipo" class="w-full">
          <el-option
            v-for="type in displayTypes"
            :key="type.id"
            :label="type.name"
            :value="type.id"
          />
        </el-select>
      </el-form-item>

      <!-- Notes -->
      <el-form-item label="Notas">
        <el-input v-model="form.notes" type="textarea" :rows="3" placeholder="Notas opcionales" />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="dialogVisible = false">Cancelar</el-button>
      <el-button
        type="primary"
        :loading="form.processing"
        @click="submit"
        :disabled="!form.technician_id || !form.technician_bank_account_id || !form.ticket_id || !form.deposit_type_id || !form.amount || !form.scheduled_date"
      >
        {{ isEditing ? 'Guardar cambios' : 'Programar depósito' }}
      </el-button>
    </template>
  </el-dialog>
</template>
