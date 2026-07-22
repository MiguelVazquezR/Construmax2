<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { usePermissions } from '@/Composables/usePermissions';
import axios from 'axios';

const { can } = usePermissions();

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    technician: { type: Object, required: true },  // { id: userId, name, technician: { id: technicianId, is_internal, state } }
    ticket: { type: Object, required: true },       // { id: ticketId, folio, name, customer_name }
    depositTypes: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const visible = ref(props.modelValue);
watch(() => props.modelValue, (v) => { visible.value = v; });
watch(visible, (v) => emit('update:modelValue', v));

const defaultShift = new Date().getHours() >= 15 ? 'vespertino' : 'matutino';

const form = useForm({
    technician_id: props.technician?.technician?.id ?? null,
    technician_bank_account_id: null,
    ticket_id: props.ticket?.id ?? null,
    deposit_type_id: null,
    amount: '',
    shift: defaultShift,
    scheduled_date: '',
    notes: '',
});

// --- Deposit types ---
const depositTypesList = ref([]);
const depositTypesLoading = ref(false);

const displayDepositTypes = computed(() => {
    return depositTypesList.value.length ? depositTypesList.value : props.depositTypes;
});

async function loadDepositTypes() {
    depositTypesLoading.value = true;
    try {
        const { data } = await axios.get(route('deposits.types.index'));
        depositTypesList.value = (Array.isArray(data) ? data : []).filter(t => t.is_active);
    } catch {
        depositTypesList.value = [];
    } finally {
        depositTypesLoading.value = false;
    }
}

// --- Bank accounts ---
const bankAccounts = ref([]);
const selectedBankAccount = ref(null);
const bankAccountsLoading = ref(false);

async function loadBankAccounts() {
    const techId = props.technician?.technician?.id;
    if (!techId) {
        bankAccounts.value = [];
        selectedBankAccount.value = null;
        return;
    }
    bankAccountsLoading.value = true;
    try {
        const { data } = await axios.get(route('deposits.technician-bank-accounts', techId));
        bankAccounts.value = data;
        const fav = data.find(a => a.is_favorite);
        if (fav && !form.technician_bank_account_id) {
            form.technician_bank_account_id = fav.id;
            selectedBankAccount.value = fav;
        }
    } catch {
        bankAccounts.value = [];
        selectedBankAccount.value = null;
    } finally {
        bankAccountsLoading.value = false;
    }
}

function onBankAccountChange(accountId) {
    selectedBankAccount.value = bankAccounts.value.find(a => a.id === accountId) || null;
}

// Reset form when modal opens
watch(visible, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.technician_id = props.technician?.technician?.id ?? null;
        form.ticket_id = props.ticket?.id ?? null;
        form.shift = defaultShift;
        form.scheduled_date = '';
        form.amount = '';
        form.notes = '';
        form.technician_bank_account_id = null;
        form.deposit_type_id = null;
        selectedBankAccount.value = null;
        loadBankAccounts();
        loadDepositTypes();
    }
}, { immediate: true });

// --- Labels ---
const getTechLabel = computed(() => {
    let label = props.technician?.name ?? 'N/A';
    if (props.technician?.technician?.is_internal !== undefined) {
        label += props.technician.technician.is_internal ? ' (Interno)' : ' (Externo)';
    }
    if (props.technician?.technician?.state) {
        label += ` — ${props.technician.technician.state}`;
    }
    return label;
});

const ticketLabel = computed(() => {
    return `${props.ticket?.folio ?? 'N/A'} — ${props.ticket?.customer_name ?? props.ticket?.name ?? 'N/A'}`;
});

// --- Submit ---
function submit() {
    form.post(route('deposits.store'), {
        onSuccess: () => {
            ElMessage.success('Depósito programado correctamente.');
            visible.value = false;
            emit('saved');
        },
    });
}
</script>

<template>
    <el-dialog
        v-model="visible"
        title="Solicitar depósito"
        width="560px"
        class="deposit-form-dialog"
        destroy-on-close
    >
        <el-form :model="form" label-position="top">
            <el-alert
                type="info"
                :closable="false"
                show-icon
                class="mb-4"
            >
                <template #title>
                    Programa un depósito para este técnico. El ticket y el técnico ya están asignados.
                </template>
            </el-alert>

            <!-- Technician (disabled) -->
            <el-form-item label="Técnico">
                <el-input :model-value="getTechLabel" disabled />
            </el-form-item>

            <!-- Ticket (disabled) -->
            <el-form-item label="Ticket">
                <el-input :model-value="ticketLabel" disabled />
            </el-form-item>

            <!-- Bank account -->
            <el-form-item label="Cuenta bancaria" required>
                <el-select
                    v-model="form.technician_bank_account_id"
                    :loading="bankAccountsLoading"
                    :disabled="bankAccounts.length === 0"
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
                <p v-if="bankAccounts.length === 0 && !bankAccountsLoading" class="text-sm text-gray-400 mt-1">
                    Este técnico no tiene cuentas bancarias registradas.
                    <template v-if="can('technicians.edit')">
                        <Link
                            :href="route('technicians.show', props.technician?.technician?.id)"
                            class="text-primary hover:underline"
                        >
                            Haz clic aquí para registrar cuentas
                        </Link>.
                    </template>
                </p>
                <!-- Bank account detail card -->
                <div v-if="selectedBankAccount" class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 mt-2 text-xs">
                    <div class="grid grid-cols-1 gap-1">
                        <div><span class="text-gray-500">Banco:</span> <span class="font-medium">{{ selectedBankAccount.bank_name ?? 'N/A' }}</span></div>
                        <div v-if="selectedBankAccount.card_owner_name"><span class="text-gray-500">Titular:</span> <span>{{ selectedBankAccount.card_owner_name }}</span></div>
                        <div><span class="text-gray-500">Cuenta:</span> <span class="font-mono">{{ selectedBankAccount.account_number ?? 'N/A' }}</span></div>
                        <div><span class="text-gray-500">CLABE:</span> <span class="font-mono">{{ selectedBankAccount.clabe ?? 'N/A' }}</span></div>
                        <div><span class="text-gray-500">Tarjeta:</span> <span class="font-mono">{{ selectedBankAccount.card_number ?? 'N/A' }}</span></div>
                        <div v-if="selectedBankAccount.branch_number"><span class="text-gray-500">Sucursal:</span> {{ selectedBankAccount.branch_number }}</div>
                    </div>
                </div>
            </el-form-item>

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
                <el-date-picker
                    v-model="form.scheduled_date"
                    type="date"
                    class="w-full"
                    placeholder="Seleccionar fecha"
                    value-format="YYYY-MM-DD"
                />
            </el-form-item>

            <!-- Deposit type -->
            <el-form-item label="Tipo de depósito" required>
                <el-select
                    v-model="form.deposit_type_id"
                    placeholder="Seleccionar tipo"
                    class="w-full"
                    :loading="depositTypesLoading"
                >
                    <el-option
                        v-for="type in displayDepositTypes"
                        :key="type.id"
                        :label="type.name"
                        :value="type.id"
                    />
                </el-select>
                <p v-if="!depositTypesLoading && displayDepositTypes.length === 0" class="text-sm text-gray-400 mt-1">
                    No hay tipos de depósito disponibles.
                </p>
            </el-form-item>

            <!-- Notes -->
            <el-form-item label="Notas">
                <el-input v-model="form.notes" type="textarea" :rows="2" placeholder="Notas opcionales" />
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="visible = false">Cancelar</el-button>
            <el-button
                type="primary"
                :loading="form.processing"
                :disabled="!form.technician_bank_account_id || !form.deposit_type_id || !form.amount || !form.scheduled_date"
                @click="submit"
            >
                Programar depósito
            </el-button>
        </template>
    </el-dialog>
</template>

<style scoped>
@media (max-width: 767px) {
  .deposit-form-dialog {
    width: 95% !important;
  }
}
</style>
