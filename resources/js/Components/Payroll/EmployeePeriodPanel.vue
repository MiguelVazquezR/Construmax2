<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Clock, Delete, Document, Edit, Location, Picture, Plus } from '@element-plus/icons-vue';
import axios from 'axios';

const props = defineProps({
    row: Object,
    period: Object,
    incidents: Array,
    adjustments: Array,
    notes: Array,
    from: String,
    to: String,
    canManage: Boolean,
    canManageIncidents: Boolean,
});

const emit = defineEmits(['changed']);

const statusLabels = {
    present: 'Asistió',
    absent: 'Falta',
    rest_day: 'Día de descanso',
    no_schedule: 'Sin horario',
    holiday: 'Día festivo',
    incident: 'Incidencia',
};

// --- Formatting ---

const parseDate = (value) => {
    const raw = String(value).substring(0, 10);
    const [year, month, day] = raw.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const weekdayOf = (value) => {
    const label = parseDate(value).toLocaleDateString('es-MX', { weekday: 'long' });
    return label.charAt(0).toUpperCase() + label.slice(1);
};

const dayMonthOf = (value) => parseDate(value).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });

const dateRangeLabel = (start, end) => (!end || end === start ? dayMonthOf(start) : `${dayMonthOf(start)} — ${dayMonthOf(end)}`);

const initials = (name) => {
    const parts = String(name || '').trim().split(/\s+/).slice(0, 2);
    return parts.map((part) => part.charAt(0).toUpperCase()).join('') || '?';
};

const incidentTagType = (type) => ({
    absence_unjustified: 'danger',
    absence_justified: 'primary',
    medical_leave: 'info',
    permission_paid: 'primary',
    permission_unpaid: 'warning',
    vacation: 'warning',
    other: 'info',
}[type] || 'info');

// Day status tags; incident days inherit the color of their incident type.
const dayTagType = (day) => {
    if (day.status === 'incident') {
        return day.incident_type_key ? incidentTagType(day.incident_type_key) : 'warning';
    }

    return {
        present: 'success',
        absent: 'danger',
        rest_day: 'info',
        no_schedule: 'info',
        holiday: 'success',
    }[day.status] || 'info';
};

const hours = (minutes) => `${Math.floor(Number(minutes || 0) / 60)}:${String(Math.round(Number(minutes || 0) % 60)).padStart(2, '0')}`;

const recordLabel = (count) => `${count} registro${count === 1 ? '' : 's'}`;

const sourceLabel = (source) => ({
    kiosk: 'Kiosco',
    remote: 'Remoto',
    manual: 'Manual',
}[source] || source);

const identifierLabel = (method) => ({
    face: 'rostro',
    pin: 'PIN',
    manual: 'manual',
}[method] || method);

const punchMapsLink = (punch) => (punch.latitude && punch.longitude
    ? `https://www.google.com/maps?q=${punch.latitude},${punch.longitude}`
    : null);

// --- State ---

const loading = ref(false);
const days = ref([]);
const schedule = ref([]);
const tab = ref('days');

const isOpen = computed(() => props.period?.status === 'open');
const canEdit = computed(() => props.canManage && isOpen.value);

const userIncidents = computed(() => (props.incidents || []).filter((incident) => incident.user_id === props.row?.user_id));
const userAdjustments = computed(() => (props.adjustments || []).filter((adjustment) => adjustment.user_id === props.row?.user_id));
const userNotes = computed(() => (props.notes || []).filter((note) => note.user_id === props.row?.user_id));

const daysSummary = computed(() => {
    const list = days.value;

    return {
        count: list.length,
        worked: list.reduce((total, day) => total + Number(day.worked_minutes || 0), 0),
        late: list.reduce((total, day) => total + (day.late_ignored ? 0 : Number(day.late_minutes || 0)), 0),
        overtime: list.reduce((total, day) => total + Number(day.overtime_minutes || 0), 0),
    };
});

const loadDetail = async () => {
    loading.value = true;

    try {
        const { data } = await axios.get(route('payroll.periods.days', [props.period.id, props.row.user_id]), {
            params: { from: props.from, to: props.to },
        });
        days.value = data.days;
        schedule.value = data.weekly_schedule;
    } catch {
        ElMessage.error('No se pudo cargar el detalle del colaborador.');
    } finally {
        loading.value = false;
    }
};

const refreshAll = () => {
    emit('changed');
    loadDetail();
};

onMounted(loadDetail);

// The page date filter narrows the days and punches shown here
watch(() => [props.from, props.to], loadDetail);

// --- Punch records of a day ---

const recordsDialog = ref(false);
const recordsDate = ref(null);

const recordsDay = computed(() => days.value.find((day) => day.date === recordsDate.value) || null);

const recordsTitle = computed(() => (recordsDay.value
    ? `Registros del ${weekdayOf(recordsDay.value.date).toLowerCase()} ${dayMonthOf(recordsDay.value.date)}`
    : 'Registros del día'));

const openRecords = (day) => {
    recordsDate.value = day.date;
    recordsDialog.value = true;
};

// --- Punch editing ---

const PUNCH_TYPE_LABELS = {
    check_in: 'Entrada',
    lunch_start: 'Inicio de comida',
    lunch_end: 'Fin de comida',
    break_start: 'Permiso / salida',
    break_end: 'Regreso de permiso',
    check_out: 'Salida',
};

const punchDialog = ref(false);
const editingPunch = ref(null);
const punchForm = useForm({ punched_at: null, type: null, edit_reason: '' });

// Date and time travel in separate inputs and are combined on save.
const punchDate = ref(null);
const punchTime = ref(null);

// Recommended next punch of a day: entry → lunch start → lunch end → exit.
// The suggestion comes from the backend with the day detail.
const suggestedTypeFor = (date) => days.value.find((day) => day.date === date)?.suggested_next || 'check_in';

const suggestedLabelFor = (date) => (date ? PUNCH_TYPE_LABELS[suggestedTypeFor(date)] : null);

const editSuggestedLabel = computed(() => suggestedLabelFor(punchDate.value));
const addSuggestedLabel = computed(() => suggestedLabelFor(newPunchDate.value));

const openPunchDialog = (punch) => {
    editingPunch.value = punch;
    punchForm.clearErrors();
    punchDate.value = String(punch.punched_at).substring(0, 10);
    punchTime.value = punch.time;
    punchForm.type = punch.type;
    // The stored reason is kept on screen so saving without touching it does not lose it.
    punchForm.edit_reason = punch.edit_reason || '';
    punchDialog.value = true;
};

const savePunch = () => {
    punchForm
        .transform(() => ({
            punched_at: `${punchDate.value ?? ''} ${punchTime.value ?? ''}:00`,
            type: punchForm.type,
            edit_reason: punchForm.edit_reason,
        }))
        .put(route('payroll.attendance-logs.update', editingPunch.value.id), {
            onSuccess: () => {
                punchDialog.value = false;
                refreshAll();
            },
        });
};

const deletePunch = (punch) => {
    ElMessageBox.confirm('¿Eliminar este registro?', 'Eliminar registro', {
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
        type: 'warning',
    }).then(() => {
        punchForm.delete(route('payroll.attendance-logs.destroy', punch.id), {
            onSuccess: () => refreshAll(),
        });
    }).catch(() => {});
};

const addPunchDialog = ref(false);
const addPunchForm = useForm({ user_id: null, type: 'check_in', punched_at: null, edit_reason: '' });

const newPunchDate = ref(null);
const newPunchTime = ref(null);

const todayIso = () => {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
};

// Opened from the toolbar (today) or from a day's records dialog (that day).
const openAddPunchDialog = (day = null) => {
    addPunchForm.clearErrors();
    addPunchForm.user_id = props.row.user_id;
    newPunchDate.value = day?.date || todayIso();
    // Pre-selects the recommended punch of that day (entry, lunch start, lunch end, exit).
    addPunchForm.type = day?.suggested_next || suggestedTypeFor(newPunchDate.value);
    newPunchTime.value = null;
    addPunchForm.edit_reason = '';
    addPunchDialog.value = true;
};

const saveNewPunch = () => {
    addPunchForm
        .transform(() => ({
            user_id: addPunchForm.user_id,
            type: addPunchForm.type,
            punched_at: `${newPunchDate.value ?? ''} ${newPunchTime.value ?? ''}:00`,
            edit_reason: addPunchForm.edit_reason,
        }))
        .post(route('payroll.attendance-logs.store'), {
            onSuccess: () => {
                addPunchDialog.value = false;
                refreshAll();
            },
        });
};

// --- Day overrides ---

const toggleLate = (day) => {
    punchForm
        .transform(() => ({
            date: day.date,
            late_ignored: day.late_ignored,
            notes: day.notes,
        }))
        .put(route('payroll.periods.override', [props.period.id, props.row.user_id]), {
            preserveScroll: true,
            onSuccess: () => refreshAll(),
        });
};

// --- Capture ---

const captureDialog = ref(false);
const captureUrl = ref(null);

const viewPunchCapture = (punch) => {
    captureUrl.value = punch.capture_url;
    captureDialog.value = true;
};

// --- Incidents ---

const incidentDialog = ref(false);
const incidentForm = useForm({ user_id: null, type: 'absence_justified', start_date: null, end_date: null, notes: '' });

const openIncidentDialog = (day = null) => {
    incidentForm.clearErrors();
    incidentForm.user_id = props.row.user_id;
    incidentForm.type = 'absence_justified';
    incidentForm.start_date = day?.date || null;
    incidentForm.end_date = day?.date || null;
    incidentForm.notes = '';
    incidentDialog.value = true;
};

const saveIncident = () => {
    incidentForm.post(route('payroll.incidents.store'), {
        onSuccess: () => {
            incidentDialog.value = false;
            refreshAll();
        },
    });
};

const destroyIncident = (incident) => {
    ElMessageBox.confirm(
        `¿Eliminar la incidencia "${incident.type_label}"?`,
        'Eliminar incidencia',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        incidentForm.delete(route('payroll.incidents.destroy', incident.id), {
            onSuccess: () => refreshAll(),
        });
    }).catch(() => {});
};

const openSupport = (url) => {
    if (url) window.open(url, '_blank');
};

// --- Adjustments ---

const adjustmentsDialog = ref(false);
const adjustmentForm = useForm({ user_id: null, type: 'earning', concept: '', amount: null, notes: '' });

const openAdjustmentsDialog = () => {
    adjustmentForm.clearErrors();
    adjustmentForm.user_id = props.row.user_id;
    adjustmentForm.type = 'earning';
    adjustmentForm.concept = '';
    adjustmentForm.amount = null;
    adjustmentForm.notes = '';
    adjustmentsDialog.value = true;
};

const saveAdjustment = () => {
    adjustmentForm.post(route('payroll.periods.adjustments.store', props.period.id), {
        onSuccess: () => {
            adjustmentForm.reset('concept', 'amount', 'notes');
            emit('changed');
        },
    });
};

const deleteAdjustment = (adjustment) => {
    adjustmentForm.delete(route('payroll.adjustments.destroy', adjustment.id), {
        onSuccess: () => emit('changed'),
    });
};

// --- Payslip ---

const openPayslip = () => {
    window.open(
        route('payroll.periods.payslips.print', { period: props.period.id, users: [props.row.user_id] }),
        '_blank'
    );
};

// --- Comments ---

const noteForm = useForm({ user_id: null, body: '' });
const editingNoteId = ref(null);
const editingNoteBody = ref('');

const addNote = () => {
    noteForm.clearErrors();
    noteForm.user_id = props.row.user_id;

    noteForm.post(route('payroll.periods.notes.store', props.period.id), {
        preserveScroll: true,
        onSuccess: () => {
            noteForm.reset('body');
            emit('changed');
        },
    });
};

const startEditNote = (note) => {
    editingNoteId.value = note.id;
    editingNoteBody.value = note.body;
};

const cancelEditNote = () => {
    editingNoteId.value = null;
    editingNoteBody.value = '';
};

const saveNote = (note) => {
    noteForm.clearErrors();

    noteForm
        .transform(() => ({ body: editingNoteBody.value }))
        .put(route('payroll.notes.update', note.id), {
            preserveScroll: true,
            onSuccess: () => {
                cancelEditNote();
                emit('changed');
            },
        });
};

const destroyNote = (note) => {
    ElMessageBox.confirm('¿Eliminar este comentario?', 'Eliminar comentario', {
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
        type: 'warning',
    }).then(() => {
        noteForm.delete(route('payroll.notes.destroy', note.id), {
            preserveScroll: true,
            onSuccess: () => emit('changed'),
        });
    }).catch(() => {});
};
</script>

<template>
    <div class="space-y-4">
        <!-- Tabs and actions -->
        <div class="flex flex-wrap items-center justify-between gap-2">
            <el-radio-group v-model="tab" size="small">
                <el-radio-button value="days">
                    Días y registros<template v-if="days.length > 0"> ({{ days.length }})</template>
                </el-radio-button>
                <el-radio-button value="schedule">Horario semanal</el-radio-button>
                <el-radio-button value="incidents">
                    Incidencias<template v-if="userIncidents.length > 0"> ({{ userIncidents.length }})</template>
                </el-radio-button>
            </el-radio-group>

            <div class="flex flex-wrap gap-2">
                <el-button size="small" :icon="Document" @click="openPayslip">Recibo</el-button>
                <el-button v-if="canEdit" size="small" plain @click="openAdjustmentsDialog">
                    Ajustes<template v-if="userAdjustments.length > 0"> ({{ userAdjustments.length }})</template>
                </el-button>
                <el-button v-if="canEdit" size="small" :icon="Plus" @click="openAddPunchDialog">Agregar registro</el-button>
                <el-button v-if="canManageIncidents" size="small" :icon="Plus" plain @click="openIncidentDialog()">
                    Agregar incidencia
                </el-button>
            </div>
        </div>

        <div v-loading="loading">
            <!-- Days and punches -->
            <template v-if="tab === 'days'">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529] px-4 py-3">
                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Días mostrados</p>
                        <p class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ daysSummary.count }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529] px-4 py-3">
                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Tiempo trabajado</p>
                        <p class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ hours(daysSummary.worked) }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529] px-4 py-3">
                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Retardos</p>
                        <p class="text-lg font-bold" :class="daysSummary.late > 0 ? 'text-red-500' : 'text-gray-800 dark:text-gray-100'">
                            {{ daysSummary.late }} min
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529] px-4 py-3">
                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Tiempo extra</p>
                        <p class="text-lg font-bold text-emerald-600">{{ hours(daysSummary.overtime) }}</p>
                    </div>
                </div>

                <el-table v-if="days.length > 0" :data="days" class="payroll-days-table" style="width: 100%">
                    <el-table-column label="Día" min-width="120">
                        <template #default="scope">
                            <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">{{ weekdayOf(scope.row.date) }}</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-100">{{ dayMonthOf(scope.row.date) }}</p>
                            <p v-if="scope.row.shift" class="text-xs text-gray-400 mt-0.5">{{ scope.row.shift }}</p>
                        </template>
                    </el-table-column>

                    <el-table-column label="Estatus" min-width="160">
                        <template #default="scope">
                            <el-tag :type="dayTagType(scope.row)" size="small" effect="plain">
                                {{ scope.row.status_label || statusLabels[scope.row.status] || scope.row.status }}
                            </el-tag>
                            <p v-if="scope.row.notes" class="text-xs text-gray-400 mt-1">{{ scope.row.notes }}</p>
                        </template>
                    </el-table-column>

                    <el-table-column label="Jornada" min-width="150">
                        <template #default="scope">
                            <template v-if="scope.row.first_in || scope.row.last_out">
                                <p class="font-medium text-gray-700 dark:text-gray-200">
                                    {{ scope.row.first_in || '—' }}
                                    <span class="text-gray-300 dark:text-gray-600 mx-1">→</span>
                                    {{ scope.row.last_out || '—' }}
                                </p>
                                <p v-if="scope.row.lunch_start || scope.row.lunch_end" class="text-xs text-gray-400 mt-0.5">
                                    Comida {{ scope.row.lunch_start || '—' }} – {{ scope.row.lunch_end || '—' }}
                                </p>
                            </template>
                            <span v-else class="text-gray-400">Sin registros</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Registros" min-width="130" align="center">
                        <template #default="scope">
                            <el-button
                                v-if="(scope.row.punches || []).length > 0"
                                type="primary"
                                link
                                :icon="Clock"
                                class="!text-[#f26c17] !font-semibold"
                                @click="openRecords(scope.row)"
                            >
                                {{ recordLabel((scope.row.punches || []).length) }}
                            </el-button>
                            <span v-else class="text-sm text-gray-400">Sin registros</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Trabajado" min-width="95" align="center">
                        <template #default="scope">
                            <p class="font-semibold text-gray-800 dark:text-gray-100">{{ hours(scope.row.worked_minutes) }}</p>
                            <p v-if="scope.row.expected_minutes > 0" class="text-xs text-gray-400 mt-0.5">
                                de {{ hours(scope.row.expected_minutes) }}
                            </p>
                        </template>
                    </el-table-column>

                    <el-table-column label="Retardo" min-width="120" align="center">
                        <template #default="scope">
                            <template v-if="scope.row.late_minutes > 0">
                                <p class="font-semibold" :class="scope.row.late_ignored ? 'text-gray-400 line-through' : 'text-red-500'">
                                    {{ scope.row.late_minutes }} min
                                </p>
                                <el-tag v-if="scope.row.late_ignored && !canEdit" size="small" type="success" effect="plain" class="mt-1">
                                    Ignorado
                                </el-tag>
                                <el-checkbox
                                    v-if="canEdit"
                                    size="small"
                                    class="mt-1"
                                    :model-value="scope.row.late_ignored"
                                    @change="(value) => { scope.row.late_ignored = value; toggleLate(scope.row); }"
                                >
                                    Ignorar
                                </el-checkbox>
                            </template>
                            <span v-else class="text-gray-400">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Extra" min-width="85" align="center">
                        <template #default="scope">
                            <p v-if="scope.row.overtime_minutes > 0" class="font-semibold text-emerald-600">
                                {{ hours(scope.row.overtime_minutes) }}
                            </p>
                            <span v-else class="text-gray-400">—</span>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-if="days.length === 0" class="text-center py-10">
                    <el-empty description="Sin días en el rango seleccionado" :image-size="90" />
                </div>
            </template>

            <!-- Weekly schedule -->
            <template v-else-if="tab === 'schedule'">
                <p v-if="schedule.length === 7" class="text-xs text-gray-400 mb-2">
                    Semana del {{ dayMonthOf(schedule[0].date) }} al {{ dayMonthOf(schedule[6].date) }}.
                </p>

                <el-table :data="schedule" style="width: 100%">
                    <el-table-column label="Día" min-width="160">
                        <template #default="scope">
                            <p class="font-semibold" :class="scope.row.workday ? 'text-gray-800 dark:text-gray-100' : 'text-gray-400'">
                                {{ scope.row.label }}
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ dayMonthOf(scope.row.date) }}</p>
                        </template>
                    </el-table-column>

                    <el-table-column label="Turno" min-width="220">
                        <template #default="scope">
                            <span v-if="scope.row.shift" class="text-gray-700 dark:text-gray-200">{{ scope.row.shift }}</span>
                            <span v-else class="text-gray-400">Sin turno asignado</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Horario" min-width="180" align="center">
                        <template #default="scope">
                            <template v-if="scope.row.flexible">{{ hours(scope.row.expected_minutes) }} h flexibles</template>
                            <template v-else-if="scope.row.start">{{ scope.row.start }} – {{ scope.row.end }}</template>
                            <template v-else><span class="text-gray-400">—</span></template>
                        </template>
                    </el-table-column>

                    <el-table-column label="Jornada esperada" min-width="160" align="center">
                        <template #default="scope">
                            <span v-if="scope.row.expected_minutes > 0" class="font-semibold text-gray-800 dark:text-gray-100">
                                {{ hours(scope.row.expected_minutes) }}
                            </span>
                            <span v-else class="text-gray-400">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Laborable" min-width="130" align="center">
                        <template #default="scope">
                            <el-tag :type="scope.row.workday ? 'success' : 'info'" size="small" effect="plain">
                                {{ scope.row.workday ? 'Sí' : 'Descanso' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                </el-table>
            </template>

            <!-- Incidents -->
            <template v-else>
                <el-table v-if="userIncidents.length > 0" :data="userIncidents" style="width: 100%">
                    <el-table-column label="Tipo" min-width="200">
                        <template #default="scope">
                            <el-tag :type="incidentTagType(scope.row.type)" size="small" effect="plain">
                                {{ scope.row.type_label }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Fechas" min-width="220">
                        <template #default="scope">
                            {{ dateRangeLabel(scope.row.start_date, scope.row.end_date) }}
                        </template>
                    </el-table-column>

                    <el-table-column label="Días" min-width="90" align="center">
                        <template #default="scope">{{ scope.row.days }}</template>
                    </el-table-column>

                    <el-table-column label="Pago" min-width="140" align="center">
                        <template #default="scope">
                            <el-tag :type="scope.row.resolved_is_paid ? 'success' : 'danger'" size="small" effect="plain">
                                {{ scope.row.resolved_is_paid ? 'Con goce' : 'Sin goce' }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Notas" min-width="220" show-overflow-tooltip>
                        <template #default="scope">
                            <span v-if="scope.row.notes" class="text-gray-600 dark:text-gray-300">{{ scope.row.notes }}</span>
                            <span v-else class="text-gray-400">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="" min-width="200" align="right">
                        <template #default="scope">
                            <el-button v-if="scope.row.support_url" size="small" text :icon="Document" @click="openSupport(scope.row.support_url)">
                                Comprobante
                            </el-button>
                            <el-button v-if="canManageIncidents" size="small" text type="danger" :icon="Delete" @click="destroyIncident(scope.row)">
                                Eliminar
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-if="userIncidents.length === 0" class="text-center py-10">
                    <el-empty description="Sin incidencias en este periodo" :image-size="90" />
                </div>
            </template>
        </div>

        <!-- Comments of the collaborator in this period -->
        <div class="border-t border-gray-100 dark:border-[#2b2b2e] pt-5 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">
                    Comentarios<template v-if="userNotes.length > 0"> ({{ userNotes.length }})</template>
                </p>
                <p v-if="userNotes.length === 0" class="text-xs text-gray-400">Sin comentarios por ahora</p>
            </div>

            <div
                v-for="note in userNotes"
                :key="note.id"
                class="flex items-start gap-3 rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529] p-3"
            >
                <div class="w-8 h-8 rounded-full bg-[#fdf0e7] dark:bg-[#3a2a1d] text-[#f26c17] text-xs font-bold flex items-center justify-center shrink-0">
                    {{ initials(note.author_name || 'Sistema') }}
                </div>

                <div class="flex-1 min-w-0">
                    <template v-if="editingNoteId === note.id">
                        <el-input v-model="editingNoteBody" type="textarea" :rows="3" maxlength="1000" show-word-limit />
                        <p v-if="noteForm.errors.body" class="text-xs text-red-500 mt-1">{{ noteForm.errors.body }}</p>
                        <div class="flex justify-end gap-2 mt-2">
                            <el-button size="small" @click="cancelEditNote">Cancelar</el-button>
                            <el-button size="small" type="primary" color="#f26c17" :loading="noteForm.processing" @click="saveNote(note)">
                                Guardar
                            </el-button>
                        </div>
                    </template>
                    <template v-else>
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-line">{{ note.body }}</p>
                            <div v-if="canManage" class="flex shrink-0 gap-1">
                                <el-button size="small" text :icon="Edit" title="Editar comentario" @click="startEditNote(note)" />
                                <el-button size="small" text type="danger" :icon="Delete" title="Eliminar comentario" @click="destroyNote(note)" />
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">
                            {{ note.author_name || 'Sistema' }} · {{ note.created_at }}
                            <template v-if="note.is_edited"> · editado {{ note.updated_at }}</template>
                        </p>
                    </template>
                </div>
            </div>

            <div v-if="canManage" class="space-y-2">
                <el-input
                    v-model="noteForm.body"
                    type="textarea"
                    :rows="2"
                    maxlength="1000"
                    show-word-limit
                    placeholder="Escribe un comentario sobre este colaborador"
                />
                <p v-if="noteForm.errors.body && !editingNoteId" class="text-xs text-red-500">{{ noteForm.errors.body }}</p>
                <div class="flex justify-end">
                    <el-button size="small" type="primary" color="#f26c17" :icon="Plus" :loading="noteForm.processing" @click="addNote">
                        Agregar comentario
                    </el-button>
                </div>
            </div>
        </div>

        <!-- Punch edit dialog -->
        <el-dialog v-model="punchDialog" :title="`Editar registro de ${row?.name}`" width="460px" top="12vh">
            <el-form :model="punchForm" label-position="top" size="default">
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Fecha" required :error="punchForm.errors.punched_at">
                        <el-date-picker
                            v-model="punchDate"
                            type="date"
                            value-format="YYYY-MM-DD"
                            format="DD/MM/YYYY"
                            placeholder="Seleccionar fecha"
                            class="w-full"
                        />
                    </el-form-item>
                    <el-form-item label="Hora" required :error="punchForm.errors.punched_at">
                        <el-time-picker
                            v-model="punchTime"
                            value-format="HH:mm"
                            format="HH:mm"
                            placeholder="Seleccionar hora"
                            class="w-full"
                        />
                    </el-form-item>
                </div>
                <el-form-item label="Tipo de registro" :error="punchForm.errors.type">
                    <div class="w-full">
                        <el-select v-model="punchForm.type" class="w-full">
                            <el-option label="Entrada" value="check_in" />
                            <el-option label="Inicio de comida" value="lunch_start" />
                            <el-option label="Fin de comida" value="lunch_end" />
                            <el-option label="Permiso / salida" value="break_start" />
                            <el-option label="Regreso de permiso" value="break_end" />
                            <el-option label="Salida" value="check_out" />
                        </el-select>
                        <p v-if="editSuggestedLabel" class="text-xs text-gray-400 mt-1">
                            Recomendado para este día: <span class="font-medium text-gray-600 dark:text-gray-300">{{ editSuggestedLabel }}</span>
                        </p>
                    </div>
                </el-form-item>
                <el-form-item label="Motivo del cambio (opcional)" :error="punchForm.errors.edit_reason">
                    <el-input v-model="punchForm.edit_reason" maxlength="255" placeholder="Opcional: quedará en la auditoría" />
                </el-form-item>
            </el-form>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="punchDialog = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="punchForm.processing" @click="savePunch">Guardar</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Add punch dialog -->
        <el-dialog v-model="addPunchDialog" :title="`Agregar registro manual a ${row?.name}`" width="460px" top="12vh">
            <el-form :model="addPunchForm" label-position="top" size="default">
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Fecha" required :error="addPunchForm.errors.punched_at">
                        <el-date-picker
                            v-model="newPunchDate"
                            type="date"
                            value-format="YYYY-MM-DD"
                            format="DD/MM/YYYY"
                            placeholder="Seleccionar fecha"
                            class="w-full"
                        />
                    </el-form-item>
                    <el-form-item label="Hora" required :error="addPunchForm.errors.punched_at">
                        <el-time-picker
                            v-model="newPunchTime"
                            value-format="HH:mm"
                            format="HH:mm"
                            placeholder="Seleccionar hora"
                            class="w-full"
                        />
                    </el-form-item>
                </div>
                <el-form-item label="Tipo de registro" required :error="addPunchForm.errors.type">
                    <div class="w-full">
                        <el-select v-model="addPunchForm.type" class="w-full">
                            <el-option label="Entrada" value="check_in" />
                            <el-option label="Inicio de comida" value="lunch_start" />
                            <el-option label="Fin de comida" value="lunch_end" />
                            <el-option label="Permiso / salida" value="break_start" />
                            <el-option label="Regreso de permiso" value="break_end" />
                            <el-option label="Salida" value="check_out" />
                        </el-select>
                        <p v-if="addSuggestedLabel" class="text-xs text-gray-400 mt-1">
                            Recomendado para este día: <span class="font-medium text-gray-600 dark:text-gray-300">{{ addSuggestedLabel }}</span>
                        </p>
                    </div>
                </el-form-item>
                <el-form-item label="Motivo (opcional)" :error="addPunchForm.errors.edit_reason">
                    <el-input v-model="addPunchForm.edit_reason" maxlength="255" placeholder="Opcional: quedará en la auditoría" />
                </el-form-item>
            </el-form>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="addPunchDialog = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="addPunchForm.processing" @click="saveNewPunch">Registrar</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Incident dialog -->
        <el-dialog v-model="incidentDialog" :title="`Agregar incidencia a ${row?.name}`" width="520px" top="10vh">
            <el-form :model="incidentForm" label-position="top" size="default">
                <el-form-item label="Tipo de incidencia" required :error="incidentForm.errors.type">
                    <el-select v-model="incidentForm.type" class="w-full">
                        <el-option label="Falta justificada" value="absence_justified" />
                        <el-option label="Falta injustificada" value="absence_unjustified" />
                        <el-option label="Incapacidad médica" value="medical_leave" />
                        <el-option label="Permiso con goce de sueldo" value="permission_paid" />
                        <el-option label="Permiso sin goce de sueldo" value="permission_unpaid" />
                        <el-option label="Vacaciones" value="vacation" />
                        <el-option label="Otro" value="other" />
                    </el-select>
                </el-form-item>
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Desde" required :error="incidentForm.errors.start_date">
                        <el-date-picker v-model="incidentForm.start_date" type="date" value-format="YYYY-MM-DD" class="w-full" />
                    </el-form-item>
                    <el-form-item label="Hasta" :error="incidentForm.errors.end_date">
                        <el-date-picker v-model="incidentForm.end_date" type="date" value-format="YYYY-MM-DD" class="w-full" />
                    </el-form-item>
                </div>
                <el-form-item label="Notas" :error="incidentForm.errors.notes">
                    <el-input v-model="incidentForm.notes" maxlength="255" />
                </el-form-item>
            </el-form>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="incidentDialog = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="incidentForm.processing" @click="saveIncident">Guardar</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Adjustments dialog -->
        <el-dialog v-model="adjustmentsDialog" :title="`Ajustes manuales de ${row?.name}`" width="560px" top="10vh">
            <div class="space-y-4">
                <el-table :data="userAdjustments" size="small" style="width: 100%">
                    <el-table-column label="Tipo" width="110">
                        <template #default="scope">
                            <el-tag :type="scope.row.type === 'earning' ? 'success' : 'danger'" size="small" effect="plain">
                                {{ scope.row.type === 'earning' ? 'Percepción' : 'Deducción' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="Concepto" min-width="140">
                        <template #default="scope">{{ scope.row.concept }}</template>
                    </el-table-column>
                    <el-table-column label="Monto" width="110" align="right">
                        <template #default="scope">${{ Number(scope.row.amount || 0).toFixed(2) }}</template>
                    </el-table-column>
                    <el-table-column label="" width="60" align="right">
                        <template #default="scope">
                            <el-button size="small" text type="danger" :icon="Delete" @click="deleteAdjustment(scope.row)" />
                        </template>
                    </el-table-column>
                </el-table>

                <el-form :model="adjustmentForm" label-position="top" size="default">
                    <div class="grid grid-cols-3 gap-4">
                        <el-form-item label="Tipo" :error="adjustmentForm.errors.type">
                            <el-select v-model="adjustmentForm.type" class="w-full">
                                <el-option label="Percepción" value="earning" />
                                <el-option label="Deducción" value="deduction" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="Concepto" required :error="adjustmentForm.errors.concept" class="col-span-2">
                            <el-input v-model="adjustmentForm.concept" maxlength="120" />
                        </el-form-item>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <el-form-item label="Monto" required :error="adjustmentForm.errors.amount">
                            <el-input-number v-model="adjustmentForm.amount" :min="0.01" :precision="2" :controls="false" style="width: 100%" />
                        </el-form-item>
                        <el-form-item label="Notas" :error="adjustmentForm.errors.notes" class="col-span-2">
                            <el-input v-model="adjustmentForm.notes" maxlength="255" />
                        </el-form-item>
                    </div>
                </el-form>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="adjustmentsDialog = false">Cerrar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="adjustmentForm.processing" @click="saveAdjustment">
                        Agregar ajuste
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Records of a day: summary with their audited actions -->
        <el-dialog v-model="recordsDialog" :title="recordsTitle" width="560px" top="10vh">
            <div v-if="recordsDay && (recordsDay.punches || []).length > 0" class="space-y-2">
                <div
                    v-for="punch in recordsDay.punches"
                    :key="punch.id"
                    class="flex items-center gap-3 rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529] px-3 py-2.5"
                >
                    <div class="w-9 h-9 rounded-lg bg-[#fdf0e7] dark:bg-[#3a2a1d] text-[#f26c17] flex items-center justify-center shrink-0">
                        <el-icon :size="16"><Clock /></el-icon>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                            {{ punch.time }} · {{ punch.type_label }}
                        </p>
                        <p class="text-xs text-gray-400">
                            {{ sourceLabel(punch.source) }}
                            <template v-if="punch.device"> · {{ punch.device }}</template>
                            <template v-if="punch.identifier_method"> · {{ identifierLabel(punch.identifier_method) }}</template>
                            <template v-if="punch.edited">
                                · editado<template v-if="punch.edit_reason"> ({{ punch.edit_reason }})</template>
                            </template>
                        </p>
                    </div>

                    <div class="flex items-center gap-1 shrink-0">
                        <el-button v-if="punch.capture_url" size="small" text :icon="Picture" title="Ver foto de evidencia" @click="viewPunchCapture(punch)" />
                        <el-tooltip v-if="punchMapsLink(punch)" content="Ver ubicación en Google Maps" placement="top">
                            <a :href="punchMapsLink(punch)" target="_blank" class="inline-flex items-center px-1 text-[#f26c17]">
                                <el-icon><Location /></el-icon>
                            </a>
                        </el-tooltip>
                        <template v-if="canEdit">
                            <el-button size="small" text :icon="Edit" title="Editar registro" @click="openPunchDialog(punch)" />
                            <el-button size="small" text type="danger" :icon="Delete" title="Eliminar registro" @click="deletePunch(punch)" />
                        </template>
                    </div>
                </div>
            </div>

            <el-empty v-else description="Sin registros para este día" :image-size="80" />

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="recordsDialog = false">Cerrar</el-button>
                    <el-button v-if="canEdit" type="primary" color="#f26c17" :icon="Plus" @click="openAddPunchDialog(recordsDay)">
                        Agregar registro
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Capture dialog -->
        <el-dialog v-model="captureDialog" title="Evidencia del registro" width="520px" top="8vh">
            <img v-if="captureUrl" :src="captureUrl" alt="Captura del registro" class="w-full rounded-lg" />
        </el-dialog>
    </div>
</template>

<style scoped>
.payroll-days-table :deep(.el-table__cell) {
    vertical-align: top;
    padding-top: 12px;
    padding-bottom: 12px;
}

.payroll-days-table :deep(.el-table__header) .el-table__cell {
    padding-top: 8px;
    padding-bottom: 8px;
}
</style>
