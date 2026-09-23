<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Clock, Delete, Document, Edit, Location, MoreFilled, Picture, Plus } from '@element-plus/icons-vue';
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
    absent: 'Falta injustificada',
    no_record: 'Sin registro',
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
    work_incapacity: 'info',
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
        no_record: 'info',
        rest_day: 'success',
        no_schedule: 'info',
        holiday: 'success',
    }[day.status] || 'info';
};

const dayStatusLabel = (day) => day.status_label || statusLabels[day.status] || day.status;

// Days without a single punch (absences, vacations, holidays, rest days...)
// collapse into one full-width status pill while keeping the date, the shift
// and the per-day menu visible.
const isPillRow = (day) => (day.punches || []).length === 0;

const daySpanMethod = ({ row, columnIndex }) => {
    if (! isPillRow(row)) {
        return [1, 1];
    }

    // Columns: 0 día · 1..5 (entrada..extra) merged into the pill · 6 acciones.
    if (columnIndex === 1) {
        return [1, 5];
    }

    if (columnIndex >= 2 && columnIndex <= 5) {
        return [0, 0];
    }

    return [1, 1];
};

const hours = (minutes) => `${Math.floor(Number(minutes || 0) / 60)}:${String(Math.round(Number(minutes || 0) % 60)).padStart(2, '0')}`;

const recordLabel = (count) => `${count} registro${count === 1 ? '' : 's'}`;

const timeToMinutes = (time) => {
    const [hour, minute] = String(time).split(':').map(Number);

    return hour * 60 + minute;
};

// Minutes of the meal break of a day; null when the pair is incomplete.
const lunchMinutes = (day) => {
    if (! day.lunch_start || ! day.lunch_end) {
        return null;
    }

    const diff = timeToMinutes(day.lunch_end) - timeToMinutes(day.lunch_start);

    return diff > 0 ? diff : null;
};

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

const recordsTitle = computed(() => {
    const date = recordsDay.value?.date || recordsDate.value;

    return date
        ? `Registros del ${weekdayOf(date).toLowerCase()} ${dayMonthOf(date)}`
        : 'Registros del día';
});

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

const INCIDENT_OPTIONS = [
    { value: 'absence_justified', label: 'Falta justificada' },
    { value: 'absence_unjustified', label: 'Falta injustificada' },
    { value: 'medical_leave', label: 'Incapacidad general' },
    { value: 'work_incapacity', label: 'Incapacidad de trabajo' },
    { value: 'permission_paid', label: 'Permiso con goce de sueldo' },
    { value: 'permission_unpaid', label: 'Permiso sin goce de sueldo' },
    { value: 'vacation', label: 'Vacaciones' },
    { value: 'other', label: 'Otro' },
];

const incidentDialog = ref(false);
const editingIncident = ref(null);
const incidentForm = useForm({ user_id: null, type: 'absence_justified', start_date: null, end_date: null, notes: '' });

const incidentDialogTitle = computed(() => (editingIncident.value
    ? `Editar incidencia de ${props.row?.name}`
    : `Agregar incidencia a ${props.row?.name}`));

// Opened from the toolbar (no day), from a day menu (that day + type) or to
// edit an incident already registered.
const openIncidentDialog = (day = null, incident = null, type = null) => {
    incidentForm.clearErrors();
    editingIncident.value = incident;

    if (incident) {
        incidentForm.user_id = incident.user_id;
        incidentForm.type = incident.type;
        incidentForm.start_date = String(incident.start_date).substring(0, 10);
        incidentForm.end_date = incident.end_date ? String(incident.end_date).substring(0, 10) : null;
        incidentForm.notes = incident.notes || '';
    } else {
        incidentForm.user_id = props.row.user_id;
        incidentForm.type = type || 'absence_justified';
        incidentForm.start_date = day?.date || null;
        incidentForm.end_date = day?.date || null;
        incidentForm.notes = '';
    }

    incidentDialog.value = true;
};

const saveIncident = () => {
    const options = {
        onSuccess: () => {
            incidentDialog.value = false;
            refreshAll();
        },
    };

    if (editingIncident.value) {
        incidentForm.put(route('payroll.incidents.update', editingIncident.value.id), options);

        return;
    }

    incidentForm.post(route('payroll.incidents.store'), options);
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

// Approved incident (if any) covering the given day, for the day menu.
const dayIncident = (day) => userIncidents.value.find((incident) => {
    const start = String(incident.start_date).substring(0, 10);
    const end = String(incident.end_date || incident.start_date).substring(0, 10);

    return start <= day.date && end >= day.date;
}) || null;

// Day menu (⋮): records, late override and incidents without picking the day.
const dayCommand = (command) => {
    switch (command.action) {
        case 'records':
            openRecords(command.day);
            break;
        case 'ignore_late':
        case 'restore_late':
            command.day.late_ignored = command.action === 'ignore_late';
            toggleLate(command.day);
            break;
        case 'add_incident':
            openIncidentDialog(command.day, null, command.type);
            break;
        case 'edit_incident':
            openIncidentDialog(null, command.incident);
            break;
        case 'remove_incident':
            destroyIncident(command.incident);
            break;
    }
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

                <el-table v-if="days.length > 0" :data="days" :span-method="daySpanMethod" class="payroll-days-table" style="width: 100%">
                    <el-table-column label="Día" min-width="130">
                        <template #default="scope">
                            <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">{{ weekdayOf(scope.row.date) }}</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-100">{{ dayMonthOf(scope.row.date) }}</p>
                            <p v-if="scope.row.shift" class="text-xs text-gray-400 mt-0.5">{{ scope.row.shift }}</p>
                            <p v-if="scope.row.notes" class="text-xs text-gray-400 mt-0.5">{{ scope.row.notes }}</p>
                        </template>
                    </el-table-column>

                    <el-table-column label="Entrada" min-width="105" align="center">
                        <template #default="scope">
                            <el-tag
                                v-if="isPillRow(scope.row)"
                                :type="dayTagType(scope.row)"
                                effect="light"
                                size="large"
                                class="day-status-pill"
                            >
                                {{ dayStatusLabel(scope.row) }}
                            </el-tag>
                            <template v-else-if="scope.row.first_in">
                                <p class="font-medium text-gray-700 dark:text-gray-200">{{ scope.row.first_in }}</p>
                                <p
                                    v-if="scope.row.late_minutes > 0"
                                    class="text-xs mt-0.5"
                                    :class="scope.row.late_ignored ? 'text-gray-400 line-through' : 'text-red-500'"
                                    :title="scope.row.late_ignored ? 'Retardo ignorado: no se descuenta del pago' : 'Minutos de retardo después de la tolerancia'"
                                >
                                    {{ scope.row.late_minutes }} min tarde
                                </p>
                            </template>
                            <span v-else class="text-gray-400">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Salida" min-width="90" align="center">
                        <template #default="scope">
                            <p v-if="scope.row.last_out" class="font-medium text-gray-700 dark:text-gray-200">{{ scope.row.last_out }}</p>
                            <span v-else class="text-gray-400">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Tiempo de comida" min-width="120" align="center">
                        <template #default="scope">
                            <el-tooltip
                                v-if="lunchMinutes(scope.row) !== null"
                                :content="`${scope.row.lunch_start} – ${scope.row.lunch_end}`"
                                placement="top"
                            >
                                <p class="font-medium text-gray-700 dark:text-gray-200">{{ hours(lunchMinutes(scope.row)) }}</p>
                            </el-tooltip>
                            <span v-else class="text-gray-400">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Tiempo trabajado" min-width="125" align="center">
                        <template #default="scope">
                            <p class="font-semibold text-gray-800 dark:text-gray-100">{{ hours(scope.row.worked_minutes) }}</p>
                            <p v-if="scope.row.expected_minutes > 0" class="text-xs text-gray-400 mt-0.5">
                                de {{ hours(scope.row.expected_minutes) }}
                            </p>
                            <el-button
                                v-if="(scope.row.punches || []).length > 0"
                                type="primary"
                                link
                                :icon="Clock"
                                class="!text-[#f26c17] !font-semibold mt-1"
                                @click="openRecords(scope.row)"
                            >
                                {{ recordLabel((scope.row.punches || []).length) }}
                            </el-button>
                            <p v-else class="text-xs text-gray-400 mt-0.5">Sin registros</p>
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

                    <el-table-column label="" width="64" align="right">
                        <template #default="scope">
                            <el-dropdown trigger="click" placement="bottom-end" @command="dayCommand">
                                <el-button text :icon="MoreFilled" title="Acciones del día" />
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item :command="{ action: 'records', day: scope.row }" :icon="Clock">
                                            {{ canEdit ? 'Editar registros' : 'Ver registros' }}
                                        </el-dropdown-item>

                                        <el-dropdown-item
                                            v-if="canEdit && scope.row.late_minutes > 0 && !scope.row.late_ignored"
                                            :command="{ action: 'ignore_late', day: scope.row }"
                                            divided
                                        >
                                            Quitar retardo
                                        </el-dropdown-item>
                                        <el-dropdown-item
                                            v-if="canEdit && scope.row.late_ignored"
                                            :command="{ action: 'restore_late', day: scope.row }"
                                            divided
                                        >
                                            Restaurar retardo
                                        </el-dropdown-item>

                                        <template v-if="canManageIncidents">
                                            <template v-if="dayIncident(scope.row)">
                                                <el-dropdown-item disabled divided>
                                                    Incidencia: {{ dayIncident(scope.row).type_label }}
                                                </el-dropdown-item>
                                                <el-dropdown-item :command="{ action: 'edit_incident', incident: dayIncident(scope.row) }" :icon="Edit">
                                                    Modificar incidencia
                                                </el-dropdown-item>
                                                <el-dropdown-item :command="{ action: 'remove_incident', incident: dayIncident(scope.row) }" :icon="Delete">
                                                    Quitar incidencia
                                                </el-dropdown-item>
                                            </template>
                                            <template v-else>
                                                <el-dropdown-item disabled divided>Registrar incidencia</el-dropdown-item>
                                                <el-dropdown-item
                                                    v-for="option in INCIDENT_OPTIONS"
                                                    :key="option.value"
                                                    :command="{ action: 'add_incident', day: scope.row, type: option.value }"
                                                >
                                                    {{ option.label }}
                                                </el-dropdown-item>
                                            </template>
                                        </template>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
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

                    <el-table-column label="" min-width="260" align="right">
                        <template #default="scope">
                            <el-button v-if="scope.row.support_url" size="small" text :icon="Document" @click="openSupport(scope.row.support_url)">
                                Comprobante
                            </el-button>
                            <el-button v-if="canManageIncidents" size="small" text :icon="Edit" @click="openIncidentDialog(null, scope.row)">
                                Editar
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

        <!-- Incident dialog (add or edit) -->
        <el-dialog v-model="incidentDialog" :title="incidentDialogTitle" width="520px" top="10vh">
            <el-form :model="incidentForm" label-position="top" size="default">
                <el-form-item label="Tipo de incidencia" required :error="incidentForm.errors.type">
                    <el-select v-model="incidentForm.type" class="w-full">
                        <el-option
                            v-for="option in INCIDENT_OPTIONS"
                            :key="option.value"
                            :label="option.label"
                            :value="option.value"
                        />
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

/* Full-width status pill of days without punches. */
.day-status-pill {
    width: 100%;
    height: 34px;
    justify-content: center;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
}

/* The table has a fixed minimum width, so on narrow screens it overflows
   horizontally and Element Plus paints its own scrollbar bars inside the
   table — they read as stray dots at the right edge of the rows. The table
   keeps its native scrolling, we only remove that decorative paint. */
.payroll-days-table :deep(.el-scrollbar__bar) {
    display: none;
}
</style>
