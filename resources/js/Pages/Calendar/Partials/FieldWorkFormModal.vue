<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useForm, router, Link, usePage } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Link as LinkIcon } from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';
import axios from 'axios';

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------

const props = defineProps({
    visible: { type: Boolean, default: false },
    schedule: { type: Object, default: null },
    prefilledDate: { type: String, default: '' },
    prefilledTime: { type: String, default: '' },
});

// ---------------------------------------------------------------------------
// Emits
// ---------------------------------------------------------------------------

const emit = defineEmits(['update:visible', 'saved']);

// ---------------------------------------------------------------------------
// Permissions
// ---------------------------------------------------------------------------

const { can } = usePermissions();
const canEditTicket = computed(() => can('tickets.edit'));
const canCreateFieldWork = computed(() => can('tickets.calendar.create'));

// ---------------------------------------------------------------------------
// Ownership & access control
// ---------------------------------------------------------------------------

const currentUser = usePage().props.auth.user;

/** Whether the current user created this schedule. */
const isOwner = computed(() => {
    if (!props.schedule) return true; // creating a new schedule — always "owner"
    return currentUser?.id === props.schedule.user_id;
});

/** Whether the current user can edit/delete this schedule (owner only). */
const canEdit = computed(() => isOwner.value);

/** Read-only mode: viewing someone else's schedule without edit permission. */
const readOnly = computed(() => isEdit.value && !canEdit.value);

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------

const tickets = ref([]);
const selectedTicket = ref(null);
const loadingTickets = ref(false);
const serviceTypes = ref([]);
const loadingServiceTypes = ref(false);
const saving = ref(false);

const form = useForm({
    ticket_id: null,
    start_time: '',
    end_time: '',
    color: '#409EFF',
    notes: '',
});

const ticketForm = useForm({
    ticket_name: '',
    ticket_report_number: '',
    ticket_service_type: '',
});

// ---------------------------------------------------------------------------
// Color palette
// ---------------------------------------------------------------------------

const paletteColors = [
    '#409EFF', '#337ECC', '#36CFC9', '#13C2C2', '#52C41A',
    '#73D13D', '#FADB14', '#FAAD14', '#FA8C16', '#FF7A45',
    '#F5222D', '#EB2F96', '#722ED1', '#9254DE', '#597EF7',
    '#85A5FF', '#40A9FF', '#69C0FF', '#87E8DE', '#95DE64',
    '#FFE58F', '#FFD666', '#FFBB96', '#FF9C6E', '#FF85C0',
];

// ---------------------------------------------------------------------------
// Computed
// ---------------------------------------------------------------------------

const dialogTitle = computed(() => {
    if (!props.schedule) return 'Agendar trabajo en campo';
    return readOnly.value
        ? 'Detalles de agenda de trabajo en campo'
        : 'Editar agenda de trabajo en campo';
});

const isEdit = computed(() => !!props.schedule);

const ticketShowUrl = computed(() => {
    if (!props.schedule?.ticket_id) return '#';
    return route('tickets.show', props.schedule.ticket_id);
});

// ---------------------------------------------------------------------------
// Data fetching
// ---------------------------------------------------------------------------

async function fetchTickets() {
    loadingTickets.value = true;
    try {
        const resp = await fetch('/field-work/available-tickets', {
            headers: { 'Accept': 'application/json' },
        });
        tickets.value = await resp.json();
    } catch {
        ElMessage.error('Error al cargar los tickets disponibles.');
    } finally {
        loadingTickets.value = false;
    }
}

async function fetchServiceTypes() {
    loadingServiceTypes.value = true;
    try {
        const { data } = await axios.get(route('service-types.index'));
        serviceTypes.value = data?.serviceTypes || data || [];
    } catch {
        // Not critical — the text input still works
    } finally {
        loadingServiceTypes.value = false;
    }
}

onMounted(fetchServiceTypes);

// ---------------------------------------------------------------------------
// Ticket selection
// ---------------------------------------------------------------------------

function onTicketSelect(ticketId) {
    selectedTicket.value = tickets.value.find((t) => t.id === ticketId) || null;

    if (selectedTicket.value && !isEdit.value) {
        form.start_time = selectedTicket.value.first_task_date || form.start_time;
        form.end_time = selectedTicket.value.last_task_date || form.end_time;
    }
}

function buildSelectedTicketFromSchedule() {
    if (!props.schedule) return null;
    return {
        id: props.schedule.ticket_id,
        folio: props.schedule.ticket_folio || '',
        name: props.schedule.title || '',
        customer_name: props.schedule.customer || '',
        branch_name: props.schedule.branch || '',
        contact_name: props.schedule.contact_name || '',
        seller_name: props.schedule.seller_name || '',
        technician_names: props.schedule.technician_names || [],
    };
}

// ---------------------------------------------------------------------------
// Modal lifecycle
// ---------------------------------------------------------------------------

function openModal() {
    if (!isEdit.value) fetchTickets();

    if (props.schedule) {
        form.ticket_id = props.schedule.ticket_id;
        form.start_time = props.schedule.start_time;
        form.end_time = props.schedule.end_time;
        form.color = props.schedule.color || '#409EFF';
        form.notes = props.schedule.notes || props.schedule.description || '';

        selectedTicket.value = buildSelectedTicketFromSchedule();

        ticketForm.ticket_name = props.schedule.title || '';
        ticketForm.ticket_report_number = props.schedule.ticket_report_number || '';
        ticketForm.ticket_service_type = props.schedule.ticket_service_type || '';
    } else {
        form.reset();
        form.ticket_id = null;
        form.start_time = props.prefilledDate
            ? `${props.prefilledDate} ${props.prefilledTime || '09:00:00'}`
            : '';
        form.end_time = props.prefilledDate
            ? `${props.prefilledDate} ${props.prefilledTime ? incrementHour(props.prefilledTime) : '10:00:00'}`
            : '';
        form.color = '#409EFF';
        form.notes = '';
        selectedTicket.value = null;
        ticketForm.reset();
    }
}

function incrementHour(timeStr) {
    const [h, m] = timeStr.split(':').map(Number);
    const next = h + 1;
    return `${String(next).padStart(2, '0')}:${String(m).padStart(2, '0')}:00`;
}

// ---------------------------------------------------------------------------
// Submit
// ---------------------------------------------------------------------------

async function submit() {
    if (isEdit.value) {
        saving.value = true; // instant feedback before async work starts

        // Update ticket fields FIRST via axios (no navigation), then submit the schedule form
        let ticketError = false;

        if (canEditTicket.value) {
            try {
                ticketError = await updateTicketFields();
            } catch {
                ticketError = true;
            }
        }

        saving.value = false; // let form.processing take over

        form.put(route('field-work.update', props.schedule.id), {
            onSuccess: () => {
                const msg = ticketError
                    ? 'Agenda actualizada. Algunos campos del ticket no se guardaron.'
                    : 'Agenda y ticket actualizados correctamente.';
                ElMessage.success(msg);
                emit('update:visible', false);
                emit('saved');
            },
            onError: () => {
                ElMessage.error('Error al actualizar la agenda.');
            },
        });
    } else {
        form.post(route('field-work.store'), {
            onSuccess: () => {
                ElMessage.success('Trabajo en campo agendado correctamente.');
                emit('update:visible', false);
                emit('saved');
            },
            onError: () => {
                ElMessage.error('Error al agendar el trabajo en campo.');
            },
        });
    }
}

/**
 * Update ticket fields (name, report_number, service_type) via axios.
 * Returns true if any update failed.
 */
async function updateTicketFields() {
    const ticketId = props.schedule.ticket_id;
    const updates = [];

    if (ticketForm.ticket_name) updates.push({ field: 'name', value: ticketForm.ticket_name });
    if (ticketForm.ticket_report_number) updates.push({ field: 'report_number', value: ticketForm.ticket_report_number });
    if (ticketForm.ticket_service_type) updates.push({ field: 'service_type', value: ticketForm.ticket_service_type });

    if (updates.length === 0) return false;

    let hasError = false;

    await Promise.all(updates.map((u) =>
        axios.put(route('tickets.update-field', ticketId), u, {
            headers: { 'Accept': 'application/json' },
        }).catch(() => {
            hasError = true;
        })
    ));

    return hasError;
}

function deleteSchedule() {
    ElMessageBox.confirm(
        '¿Eliminar esta agenda de trabajo en campo? Las fechas de las tareas se limpiarán.',
        'Confirmar eliminación',
        { type: 'warning', confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar' },
    ).then(() => {
        router.delete(route('field-work.destroy', props.schedule.id), {
            onSuccess: () => {
                ElMessage.success('Agenda de trabajo en campo eliminada.');
                emit('update:visible', false);
                emit('saved');
            },
            onError: () => {
                ElMessage.error('Error al eliminar la agenda.');
            },
        });
    }).catch(() => {});
}

// ---------------------------------------------------------------------------
// Watchers
// ---------------------------------------------------------------------------

watch(() => props.visible, (val) => {
    if (val) openModal();
});
</script>

<template>
    <el-dialog
        :model-value="visible"
        :title="dialogTitle"
        width="700px"
        destroy-on-close
        @update:model-value="emit('update:visible', $event)"
    >
        <el-form :model="form" label-position="top" size="default">

            <!-- Ticket selector (create mode) or read-only display (edit mode) -->
            <template v-if="!isEdit">
                <el-form-item label="Ticket de servicio" required>
                    <el-select
                        v-model="form.ticket_id"
                        placeholder="Buscar y seleccionar un ticket..."
                        filterable
                        class="w-full"
                        :loading="loadingTickets"
                        @change="onTicketSelect"
                    >
                        <el-option
                            v-for="t in tickets"
                            :key="t.id"
                            :label="`${t.folio} — ${t.name}`"
                            :value="t.id"
                        >
                            <div class="flex flex-col">
                                <span class="font-medium">{{ t.folio }} — {{ t.name }}</span>
                                <span class="text-xs text-zinc-400">{{ t.customer_name }} / {{ t.branch_name }}</span>
                            </div>
                        </el-option>
                    </el-select>
                </el-form-item>
                <p class="text-xs text-zinc-400 -mt-2 mb-2">
                    Solo se muestran tickets con estatus "Proceso de ejecución" que aún no tengan una agenda de trabajo en campo asignada.
                </p>
            </template>
            <template v-else>
                <!-- Edit mode: read-only ticket display -->
                <el-form-item label="Ticket de servicio">
                    <div class="px-3 py-2 rounded border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 text-sm text-zinc-700 dark:text-zinc-300">
                        {{ schedule?.ticket_folio || '' }} — {{ schedule?.title || 'Sin nombre' }}
                    </div>
                </el-form-item>
            </template>

            <!-- Dynamic ticket info card -->
            <div
                v-if="selectedTicket"
                class="mb-4 p-4 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50"
            >
                <h4 class="text-sm font-semibold text-zinc-600 dark:text-zinc-300 mb-2">Detalles del ticket</h4>
                <div class="grid grid-cols-2 gap-x-6 gap-y-1.5 text-sm">
                    <div class="text-zinc-400">Cliente</div>
                    <div class="text-zinc-700 dark:text-zinc-200">{{ selectedTicket.customer_name || '—' }}</div>

                    <div class="text-zinc-400">Sucursal</div>
                    <div class="text-zinc-700 dark:text-zinc-200">{{ selectedTicket.branch_name || '—' }}</div>

                    <div class="text-zinc-400">Contacto</div>
                    <div class="text-zinc-700 dark:text-zinc-200">{{ selectedTicket.contact_name || '—' }}</div>

                    <div class="text-zinc-400">Asesor</div>
                    <div class="text-zinc-700 dark:text-zinc-200">{{ selectedTicket.seller_name || '—' }}</div>

                    <div class="text-zinc-400">Ticket</div>
                    <div class="text-zinc-700 dark:text-zinc-200">{{ selectedTicket.name }}</div>

                    <div class="text-zinc-400">Técnicos</div>
                    <div class="text-zinc-700 dark:text-zinc-200">
                        {{ (selectedTicket.technician_names || []).join(', ') || '—' }}
                    </div>
                </div>
            </div>

            <!-- Editable ticket fields (only in edit mode + tickets.edit permission + NOT read-only) -->
            <div
                v-if="isEdit && canEditTicket && !readOnly"
                class="mb-4 p-4 rounded-lg border border-dashed border-primary/30 bg-primary/[0.02] dark:bg-primary/[0.03]"
            >
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-semibold text-primary">Editar datos del ticket</h4>
                    <a
                        :href="ticketShowUrl"
                        class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                    >
                        <el-icon size="12"><LinkIcon /></el-icon>
                        Ver ticket completo
                    </a>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Nombre del ticket">
                        <el-input v-model="ticketForm.ticket_name" placeholder="Nombre del ticket" />
                    </el-form-item>
                    <el-form-item label="Número de reporte">
                        <el-input v-model="ticketForm.ticket_report_number" placeholder="Número de reporte" />
                    </el-form-item>
                </div>
                <el-form-item label="Tipo de servicio">
                    <el-select
                        v-model="ticketForm.ticket_service_type"
                        filterable
                        allow-create
                        class="w-full"
                        placeholder="Seleccionar o escribir tipo de servicio"
                        :loading="loadingServiceTypes"
                    >
                        <el-option
                            v-for="st in serviceTypes"
                            :key="st.id || st.name"
                            :label="st.name"
                            :value="st.name"
                        />
                    </el-select>
                </el-form-item>
            </div>

            <!-- Date & time pickers -->
            <div class="grid grid-cols-2 gap-4">
                <el-form-item label="Fecha y hora de inicio" required>
                    <el-date-picker
                        v-model="form.start_time"
                        type="datetime"
                        class="!w-full"
                        format="DD/MM/YYYY HH:mm"
                        value-format="YYYY-MM-DD HH:mm:ss"
                        placeholder="Seleccionar inicio"
                        :disabled="readOnly"
                    />
                </el-form-item>
                <el-form-item label="Fecha y hora de fin" required>
                    <el-date-picker
                        v-model="form.end_time"
                        type="datetime"
                        class="!w-full"
                        format="DD/MM/YYYY HH:mm"
                        value-format="YYYY-MM-DD HH:mm:ss"
                        placeholder="Seleccionar fin"
                        :disabled="readOnly"
                    />
                </el-form-item>
            </div>

            <!-- Color selection -->
            <el-form-item label="Color">
                <div class="flex flex-wrap gap-2 mb-3">
                    <button
                        v-for="c in paletteColors"
                        :key="c"
                        type="button"
                        class="w-7 h-7 rounded-full border-2 transition-transform hover:scale-110"
                        :class="form.color === c
                            ? 'border-zinc-800 dark:border-white scale-110 shadow-md'
                            : 'border-transparent'"
                        :style="{ backgroundColor: c }"
                        :disabled="readOnly"
                        @click="form.color = c"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-zinc-400">Personalizado:</span>
                    <el-color-picker v-model="form.color" size="default" :disabled="readOnly" />
                </div>
            </el-form-item>

            <!-- Notes -->
            <el-form-item label="Notas">
                <el-input
                    v-model="form.notes"
                    type="textarea"
                    :rows="3"
                    placeholder="Comentarios o especificaciones para este trabajo en campo..."
                    :disabled="readOnly"
                />
            </el-form-item>

        </el-form>

        <template #footer>
            <div class="flex justify-between">
                <el-button
                    v-if="isEdit && canEdit"
                    type="danger"
                    link
                    @click="deleteSchedule"
                >
                    Eliminar agenda
                </el-button>
                <div v-else />

                <div class="flex gap-2">
                    <el-button @click="emit('update:visible', false)">
                        {{ readOnly ? 'Cerrar' : 'Cancelar' }}
                    </el-button>
                    <el-button
                        v-if="!readOnly"
                        type="primary"
                        color="#f26c17"
                        :loading="saving || form.processing"
                        :disabled="!form.ticket_id || !form.start_time || !form.end_time"
                        @click="submit"
                    >
                        {{ isEdit ? 'Guardar cambios' : 'Agendar trabajo en campo' }}
                    </el-button>
                </div>
            </div>
        </template>
    </el-dialog>
</template>
