<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessageBox } from 'element-plus';
import { Clock, Delete, Edit, InfoFilled, OfficeBuilding, Plus, Search } from '@element-plus/icons-vue';
import { useFlashMessages } from '@/Composables/useFlashMessages';

const props = defineProps({
    shifts: Array,
    assignments: Array,
    users: Array,
    shiftTypes: Object,
    shiftTypeDescriptions: Object,
    weekDays: Object,
    assignmentTypes: Object,
});

useFlashMessages();

const activeTab = ref('shifts');

const weekDayOptions = computed(() =>
    Object.entries(props.weekDays || {}).map(([value, label]) => ({ value: Number(value), label }))
);

const shiftTypeOptions = computed(() =>
    Object.entries(props.shiftTypes || {}).map(([value, label]) => ({ value, label }))
);

const assignmentTypeOptions = computed(() =>
    Object.entries(props.assignmentTypes || {}).map(([value, label]) => ({ value, label }))
);

const formatTime = (value) => (value ? String(value).substring(0, 5) : null);

// "Lunes" → "Lun" for the compact day chips.
const dayShort = (day) => String(props.weekDays?.[day] || '').slice(0, 3);

const formatDuration = (minutes) => {
    const hours = Math.floor(Number(minutes || 0) / 60);
    const rest = Math.round(Number(minutes || 0) % 60);

    return rest > 0 ? `${hours} h ${rest} min` : `${hours} h`;
};

// --- Shifts ---

const DAY_DEFAULTS = { start_time: '09:00:00', end_time: '18:00:00', meal_minutes: 60 };

const emptyDayRows = () =>
    Object.keys(props.weekDays || {}).map((day) => ({
        day: Number(day),
        enabled: false,
        ...DAY_DEFAULTS,
    }));

const shiftDialog = ref(false);
const editingShift = ref(null);
const dayRows = ref(emptyDayRows());

const shiftForm = useForm({
    name: '',
    type: 'fixed',
    start_time: '09:00:00',
    end_time: '18:00:00',
    meal_minutes: 60,
    is_meal_paid: false,
    days: [1, 2, 3, 4, 5],
    day_schedules: {},
    required_daily_hours: 8,
    late_tolerance_minutes: null,
    is_active: true,
    description: '',
});

const openShiftDialog = (shift = null) => {
    editingShift.value = shift;
    shiftForm.clearErrors();
    shiftForm.day_schedules = {};
    dayRows.value = emptyDayRows();

    if (shift) {
        shiftForm.name = shift.name;
        shiftForm.type = shift.type;
        shiftForm.start_time = shift.start_time;
        shiftForm.end_time = shift.end_time;
        shiftForm.meal_minutes = shift.meal_minutes;
        shiftForm.is_meal_paid = Boolean(shift.is_meal_paid);
        shiftForm.days = (shift.days || []).map(Number);
        shiftForm.required_daily_hours = shift.required_daily_hours ? Number(shift.required_daily_hours) : 8;
        shiftForm.late_tolerance_minutes = shift.late_tolerance_minutes;
        shiftForm.is_active = Boolean(shift.is_active);
        shiftForm.description = shift.description || '';

        // Per-day shifts load their schedule into the day editor.
        if (shift.type === 'per_day') {
            const schedules = shift.day_schedules || {};

            dayRows.value = dayRows.value.map((row) => {
                const schedule = schedules[row.day] ?? schedules[String(row.day)] ?? null;

                return schedule
                    ? {
                        ...row,
                        enabled: true,
                        start_time: schedule.start_time,
                        end_time: schedule.end_time,
                        meal_minutes: Number(schedule.meal_minutes ?? 0),
                    }
                    : row;
            });
        }
    } else {
        shiftForm.reset();
        shiftForm.days = [1, 2, 3, 4, 5];
    }

    shiftDialog.value = true;
};

// Switching to "Por día" pre-fills monday to friday with the default schedule.
const onTypeChange = (type) => {
    if (type === 'per_day' && ! dayRows.value.some((row) => row.enabled)) {
        dayRows.value = dayRows.value.map((row) => (row.day <= 5 ? { ...row, enabled: true } : row));
    }
};

const dayMinutes = (row) => {
    const [startHour, startMinute] = String(row.start_time || '0:0').split(':').map(Number);
    const [endHour, endMinute] = String(row.end_time || '0:0').split(':').map(Number);

    let minutes = (endHour * 60 + endMinute) - (startHour * 60 + startMinute);

    if (minutes <= 0) {
        minutes += 24 * 60;
    }

    return Math.max(0, minutes - (shiftForm.is_meal_paid ? 0 : Number(row.meal_minutes || 0)));
};

const weeklyMinutes = computed(() =>
    shiftForm.type === 'per_day'
        ? dayRows.value.filter((row) => row.enabled).reduce((total, row) => total + dayMinutes(row), 0)
        : 0
);

// Jornada of a fixed shift (start → end, net of the unpaid meal time).
const fixedSummary = computed(() => {
    if (shiftForm.type !== 'fixed') {
        return '';
    }

    const start = formatTime(shiftForm.start_time);
    const end = formatTime(shiftForm.end_time);

    if (! start || ! end) {
        return '';
    }

    const minutes = dayMinutes({
        start_time: shiftForm.start_time,
        end_time: shiftForm.end_time,
        meal_minutes: shiftForm.meal_minutes,
    });

    if (minutes <= 0) {
        return `${start} a ${end}`;
    }

    const meal = Number(shiftForm.meal_minutes || 0);
    const mealNote = shiftForm.is_meal_paid
        ? 'comida pagada incluida'
        : (meal > 0 ? `sin contar ${meal} min de comida` : 'sin comida');

    return `${start} a ${end} · ${formatDuration(minutes)} al día (${mealNote})`;
});

// Day-by-day schedule of a per-day shift, ready for the table.
const shiftDayEntries = (shift) => {
    if (shift.type !== 'per_day') {
        return [];
    }

    const schedules = shift.day_schedules || {};

    return (shift.days || []).map((day) => {
        const schedule = schedules[day] ?? schedules[String(day)] ?? null;

        return {
            day,
            label: dayShort(day),
            start: formatTime(schedule?.start_time),
            end: formatTime(schedule?.end_time),
        };
    });
};

const saveShift = () => {
    const options = {
        onSuccess: () => {
            shiftDialog.value = false;
        },
    };

    shiftForm
        .transform((data) => {
            if (data.type !== 'per_day') {
                return data;
            }

            const schedules = {};

            dayRows.value.filter((row) => row.enabled).forEach((row) => {
                schedules[row.day] = {
                    start_time: row.start_time,
                    end_time: row.end_time,
                    meal_minutes: Number(row.meal_minutes ?? 0),
                };
            });

            return {
                ...data,
                day_schedules: schedules,
                days: Object.keys(schedules).map(Number),
            };
        });

    if (editingShift.value) {
        shiftForm.put(route('payroll.shifts.update', editingShift.value.id), options);
    } else {
        shiftForm.post(route('payroll.shifts.store'), options);
    }
};

const destroyShift = (shift) => {
    ElMessageBox.confirm(
        `¿Eliminar el turno "${shift.name}"?`,
        'Eliminar turno',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        shiftForm.delete(route('payroll.shifts.destroy', shift.id));
    }).catch(() => {});
};

// --- Assignments ---

const assignmentDialog = ref(false);
const targetType = ref('user');

// Filters over the loaded assignment rows (search by name and by the type of
// schedule of the assigned shift(s)).
const assignmentSearch = ref('');
const assignmentShiftType = ref(null);

const normalizeText = (value) =>
    String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

// Shift id → schedule type, to know the type of the shift(s) of an assignment.
const shiftTypeById = computed(() =>
    Object.fromEntries((props.shifts || []).map((shift) => [shift.id, shift.type]))
);

const shiftTypesOfAssignment = (assignment) => {
    const ids = assignment.type === 'rotation'
        ? (assignment.rotation || [])
        : (assignment.shift_id ? [assignment.shift_id] : []);

    return ids
        .map((id) => shiftTypeById.value[id])
        .filter((type) => Boolean(type));
};

const shiftById = computed(() =>
    Object.fromEntries((props.shifts || []).map((shift) => [shift.id, shift]))
);

// Shift name + type chips of an assignment (fixed shift or rotation cycle).
const shiftEntriesOfAssignment = (assignment) => {
    if (assignment.type === 'rotation') {
        return (assignment.rotation || [])
            .map((id) => shiftById.value[id])
            .filter(Boolean)
            .map((shift) => ({ name: shift.name, type: shift.type }));
    }

    const shift = assignment.shift_id ? shiftById.value[assignment.shift_id] : null;

    if (shift) {
        return [{ name: shift.name, type: shift.type }];
    }

    return assignment.shift_name ? [{ name: assignment.shift_name, type: null }] : [];
};

const shiftTagType = (type) => (type === 'per_day' ? 'success' : (type === 'fixed' ? 'primary' : 'warning'));

const initialsOf = (name) => {
    const parts = String(name || '').trim().split(/\s+/).slice(0, 2);
    return parts.map((part) => part.charAt(0).toUpperCase()).join('') || '?';
};

const formatDate = (value) => {
    if (! value) {
        return null;
    }

    const raw = String(value).substring(0, 10);
    const [year, month, day] = raw.split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
};

const filteredAssignments = computed(() => {
    const term = normalizeText(assignmentSearch.value).trim();
    const type = assignmentShiftType.value;

    return (props.assignments || []).filter((assignment) => {
        const matchesName = term === '' || normalizeText(assignment.target_label).includes(term);
        const matchesType = ! type || shiftTypesOfAssignment(assignment).includes(type);

        return matchesName && matchesType;
    });
});

const assignmentForm = useForm({
    user_id: null,
    department: '',
    type: 'fixed',
    shift_id: null,
    rotation: [],
    start_date: null,
    end_date: null,
    is_active: true,
    notes: '',
});

const openAssignmentDialog = () => {
    assignmentForm.reset();
    assignmentForm.clearErrors();
    targetType.value = 'user';
    assignmentDialog.value = true;
};

const saveAssignment = () => {
    if (targetType.value === 'user') {
        assignmentForm.department = '';
    } else {
        assignmentForm.user_id = null;
    }

    assignmentForm.post(route('payroll.shift-assignments.store'), {
        onSuccess: () => {
            assignmentDialog.value = false;
        },
    });
};

const destroyAssignment = (assignment) => {
    ElMessageBox.confirm(
        `¿Eliminar la asignación de "${assignment.target_label}"?`,
        'Eliminar asignación',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        assignmentForm.delete(route('payroll.shift-assignments.destroy', assignment.id));
    }).catch(() => {});
};
</script>

<template>
    <AppLayout title="Horarios del personal">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">Horarios del personal</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Configura horarios fijos, flexibles o por día y asígnalos a colaboradores o departamentos.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <el-button v-if="activeTab === 'shifts'" type="primary" color="#f26c17" :icon="Plus" @click="openShiftDialog()">
                        Nuevo turno
                    </el-button>
                    <el-button v-else type="primary" color="#f26c17" :icon="Plus" @click="openAssignmentDialog">
                        Nueva asignación
                    </el-button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-tabs v-model="activeTab" class="px-2 pt-2">
                    <!-- Shifts -->
                    <el-tab-pane name="shifts">
                        <template #label>
                            <span class="px-2">Turnos</span>
                        </template>

                        <el-table :data="shifts" style="width: 100%" stripe>
                            <el-table-column label="Turno" min-width="230">
                                <template #default="scope">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-gray-800 dark:text-gray-200">{{ scope.row.name }}</span>
                                        <el-tooltip :content="shiftTypeDescriptions?.[scope.row.type]" placement="top" :show-after="200">
                                            <el-tag size="small" effect="plain" :type="shiftTagType(scope.row.type)">
                                                {{ shiftTypes[scope.row.type] }}
                                            </el-tag>
                                        </el-tooltip>
                                        <el-tag v-if="! scope.row.is_active" type="danger" size="small" effect="plain">Inactivo</el-tag>
                                    </div>
                                    <div v-if="scope.row.description" class="text-xs text-gray-500 mt-0.5">{{ scope.row.description }}</div>
                                </template>
                            </el-table-column>

                            <el-table-column label="Horario" min-width="280">
                                <template #default="scope">
                                    <template v-if="scope.row.type === 'per_day'">
                                        <div class="space-y-1">
                                            <div v-for="entry in shiftDayEntries(scope.row)" :key="entry.day" class="flex items-center gap-2">
                                                <span class="inline-flex w-10 justify-center rounded bg-gray-100 dark:bg-[#2b2b2e] px-1 py-0.5 text-[11px] font-semibold text-gray-600 dark:text-gray-300">
                                                    {{ entry.label }}
                                                </span>
                                                <span class="text-sm text-gray-700 dark:text-gray-200">{{ entry.start }} – {{ entry.end }}</span>
                                            </div>
                                        </div>
                                    </template>

                                    <template v-else-if="scope.row.type === 'fixed'">
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                            {{ formatTime(scope.row.start_time) }} – {{ formatTime(scope.row.end_time) }}
                                        </p>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            <span
                                                v-for="day in scope.row.days || []"
                                                :key="day"
                                                class="inline-flex rounded bg-gray-100 dark:bg-[#2b2b2e] px-1.5 py-0.5 text-[11px] font-medium text-gray-600 dark:text-gray-300"
                                            >
                                                {{ dayShort(day) }}
                                            </span>
                                        </div>
                                    </template>

                                    <template v-else>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                            {{ Number(scope.row.required_daily_hours) }} h flexibles
                                        </p>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            <span
                                                v-for="day in scope.row.days || []"
                                                :key="day"
                                                class="inline-flex rounded bg-gray-100 dark:bg-[#2b2b2e] px-1.5 py-0.5 text-[11px] font-medium text-gray-600 dark:text-gray-300"
                                            >
                                                {{ dayShort(day) }}
                                            </span>
                                        </div>
                                    </template>
                                </template>
                            </el-table-column>

                            <el-table-column label="Comida" width="120" align="center">
                                <template #default="scope">
                                    <span class="text-sm">{{ scope.row.type === 'per_day' ? 'Según el día' : `${scope.row.meal_minutes} min` }}</span>
                                    <div v-if="scope.row.is_meal_paid" class="text-[11px] text-emerald-600 mt-0.5">Pagada</div>
                                    <div v-else-if="scope.row.late_tolerance_minutes !== null" class="text-[11px] text-gray-400 mt-0.5">
                                        Tolerancia {{ scope.row.late_tolerance_minutes }} min
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column label="Asignaciones" width="115" align="center">
                                <template #default="scope">
                                    <el-tag size="small" type="info" effect="plain">{{ scope.row.assignments_count }}</el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column label="" width="100" align="right">
                                <template #default="scope">
                                    <el-button :icon="Edit" circle plain size="small" @click="openShiftDialog(scope.row)" />
                                    <el-button :icon="Delete" circle plain size="small" type="danger" class="!ml-2" @click="destroyShift(scope.row)" />
                                </template>
                            </el-table-column>
                        </el-table>

                        <div v-if="shifts.length === 0" class="text-center py-12">
                            <el-empty description="Sin turnos registrados" :image-size="100">
                                <template #default>
                                    <p class="text-sm text-gray-500">Crea el primer turno para comenzar a asignar horarios.</p>
                                </template>
                            </el-empty>
                        </div>
                    </el-tab-pane>

                    <!-- Assignments -->
                    <el-tab-pane name="assignments">
                        <template #label>
                            <span class="px-2">Asignaciones</span>
                        </template>

                        <div class="px-4 pt-2 pb-4">
                            <el-alert
                                type="info"
                                :closable="false"
                                show-icon
                                title="Cómo se aplican las asignaciones"
                                description="La asignación individual tiene prioridad sobre la del departamento. En asignaciones rotativas, el turno cambia cada semana siguiendo el orden definido."
                            />
                        </div>

                        <div class="flex flex-wrap items-center gap-3 px-4 pb-4">
                            <el-input
                                v-model="assignmentSearch"
                                placeholder="Buscar por nombre"
                                :prefix-icon="Search"
                                clearable
                                class="!w-64"
                            />
                            <el-select v-model="assignmentShiftType" placeholder="Tipo de horario" clearable class="!w-44">
                                <el-option v-for="option in shiftTypeOptions" :key="option.value" :label="option.label" :value="option.value" />
                            </el-select>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                Mostrando {{ filteredAssignments.length }} de {{ assignments.length }} asignaciones.
                            </span>
                        </div>

                        <el-table :data="filteredAssignments" style="width: 100%" empty-text="Sin asignaciones que coincidan con el filtro">
                            <el-table-column label="Asignado a" min-width="240">
                                <template #default="scope">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 text-xs font-bold"
                                            :class="scope.row.user_id
                                                ? 'bg-[#fdf0e7] dark:bg-[#3a2a1d] text-[#f26c17]'
                                                : 'bg-blue-50 dark:bg-blue-500/10 text-blue-500'"
                                        >
                                            <template v-if="scope.row.user_id">{{ initialsOf(scope.row.target_label) }}</template>
                                            <el-icon v-else :size="16"><OfficeBuilding /></el-icon>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800 dark:text-gray-100 leading-tight">{{ scope.row.target_label }}</p>
                                            <p class="text-xs text-gray-400">{{ scope.row.user_id ? 'Colaborador' : 'Departamento' }}</p>
                                        </div>
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column label="Tipo" width="120" align="center">
                                <template #default="scope">
                                    <el-tag :type="scope.row.type === 'fixed' ? 'primary' : 'warning'" size="small" effect="light">
                                        {{ assignmentTypes[scope.row.type] }}
                                    </el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column label="Horario(s) asignado(s)" min-width="260">
                                <template #default="scope">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <template v-for="(entry, index) in shiftEntriesOfAssignment(scope.row)" :key="index">
                                            <span v-if="index > 0" class="text-gray-300 dark:text-gray-600 text-xs">›</span>
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529] pl-2.5 pr-2 py-1">
                                                <span class="text-xs font-medium text-gray-700 dark:text-gray-200">{{ entry.name }}</span>
                                                <el-tag v-if="entry.type" :type="shiftTagType(entry.type)" size="small" effect="plain" class="!border-0">
                                                    {{ shiftTypes[entry.type] }}
                                                </el-tag>
                                            </span>
                                        </template>
                                        <span v-if="shiftEntriesOfAssignment(scope.row).length === 0" class="text-sm text-gray-400">—</span>
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column label="Vigencia" min-width="200">
                                <template #default="scope">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Desde {{ formatDate(scope.row.start_date) }}
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        {{ scope.row.end_date ? `Hasta ${formatDate(scope.row.end_date)}` : 'Sin fecha de término' }}
                                    </p>
                                </template>
                            </el-table-column>

                            <el-table-column label="Estatus" width="110" align="center">
                                <template #default="scope">
                                    <el-tag :type="scope.row.is_active ? 'success' : 'danger'" size="small" effect="plain">
                                        {{ scope.row.is_active ? 'Activa' : 'Inactiva' }}
                                    </el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column label="" width="80" align="right">
                                <template #default="scope">
                                    <el-button :icon="Delete" circle plain size="small" type="danger" @click="destroyAssignment(scope.row)" />
                                </template>
                            </el-table-column>
                        </el-table>

                        <div v-if="assignments.length === 0" class="text-center py-12">
                            <el-empty description="Sin asignaciones registradas" :image-size="100">
                                <template #default>
                                    <p class="text-sm text-gray-500">Asigna turnos a colaboradores o departamentos.</p>
                                </template>
                            </el-empty>
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </div>

        <!-- Shift dialog -->
        <el-dialog
            v-model="shiftDialog"
            :title="editingShift ? `Editar turno: ${editingShift.name}` : 'Nuevo turno'"
            width="680px"
            top="8vh"
        >
            <el-form :model="shiftForm" label-position="top" size="default">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Nombre" required :error="shiftForm.errors.name">
                        <el-input v-model="shiftForm.name" placeholder="Ej. Turno matutino" maxlength="120" />
                    </el-form-item>

                    <el-form-item label="Tipo de turno" required :error="shiftForm.errors.type">
                        <el-select v-model="shiftForm.type" class="w-full" @change="onTypeChange">
                            <el-option v-for="option in shiftTypeOptions" :key="option.value" :label="option.label" :value="option.value" />
                        </el-select>
                    </el-form-item>
                </div>

                <!-- Cuándo usar el tipo de turno seleccionado -->
                <div class="flex items-start gap-2 rounded-lg bg-[#fdf0e7] dark:bg-[#3a2a1d] px-3 py-2 mb-4">
                    <el-icon class="mt-0.5 shrink-0 text-[#f26c17]"><InfoFilled /></el-icon>
                    <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                        {{ shiftTypeDescriptions?.[shiftForm.type] }}
                    </p>
                </div>

                <template v-if="shiftForm.type === 'fixed'">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <el-form-item label="Hora de entrada" required :error="shiftForm.errors.start_time">
                            <el-time-picker v-model="shiftForm.start_time" value-format="HH:mm:ss" format="HH:mm" placeholder="Entrada" class="w-full" />
                        </el-form-item>

                        <el-form-item label="Hora de salida" required :error="shiftForm.errors.end_time">
                            <el-time-picker v-model="shiftForm.end_time" value-format="HH:mm:ss" format="HH:mm" placeholder="Salida" class="w-full" />
                        </el-form-item>
                    </div>

                    <div v-if="fixedSummary" class="flex items-center gap-2 rounded-lg bg-gray-50 dark:bg-[#252529] px-3 py-2 mb-4 text-xs text-gray-500 dark:text-gray-400">
                        <el-icon class="shrink-0"><Clock /></el-icon>
                        <p>Jornada: <strong class="text-gray-700 dark:text-gray-200">{{ fixedSummary }}</strong></p>
                    </div>
                </template>

                <template v-else-if="shiftForm.type === 'flexible'">
                    <el-form-item label="Horas diarias requeridas" required :error="shiftForm.errors.required_daily_hours">
                        <el-input-number v-model="shiftForm.required_daily_hours" :min="0.5" :max="24" :step="0.5" :precision="2" :controls="false" style="width: 100%" />
                    </el-form-item>

                    <div class="flex items-center gap-2 rounded-lg bg-gray-50 dark:bg-[#252529] px-3 py-2 mb-4 text-xs text-gray-500 dark:text-gray-400">
                        <el-icon class="shrink-0"><Clock /></el-icon>
                        <p>
                            El colaborador puede elegir su hora de entrada y salida, siempre que cubra
                            <strong class="text-gray-700 dark:text-gray-200">{{ shiftForm.required_daily_hours }} h</strong>
                            en los días marcados abajo. No se miden retardos.
                        </p>
                    </div>
                </template>

                <!-- Personalizado: cada día tiene su propio horario -->
                <template v-else>
                    <el-form-item label="Horario por día" required :error="shiftForm.errors.day_schedules || shiftForm.errors.days">
                        <div class="w-full space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-[#fdf0e7] dark:bg-[#3a2a1d] px-3 py-2">
                                <span class="text-xs text-gray-600 dark:text-gray-300">
                                    Activa los días que se trabajan y ajusta su horario y tiempo de comida.
                                </span>
                                <span v-if="weeklyMinutes > 0" class="text-xs text-gray-600 dark:text-gray-300">
                                    Total semanal: <strong class="text-gray-800 dark:text-gray-100">{{ formatDuration(weeklyMinutes) }}</strong>
                                </span>
                            </div>

                            <div
                                v-for="row in dayRows"
                                :key="row.day"
                                class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-100 dark:border-[#2b2b2e] px-3 py-2"
                                :class="row.enabled ? 'bg-white dark:bg-[#1e1e20]' : 'bg-gray-50/60 dark:bg-[#252529]'"
                            >
                                <el-switch v-model="row.enabled" style="--el-switch-on-color: #f26c17;" />
                                <span
                                    class="w-24 text-sm font-medium"
                                    :class="row.enabled ? 'text-gray-800 dark:text-gray-100' : 'text-gray-400'"
                                >
                                    {{ weekDays[row.day] }}
                                </span>

                                <template v-if="row.enabled">
                                    <el-time-picker
                                        v-model="row.start_time"
                                        value-format="HH:mm:ss"
                                        format="HH:mm"
                                        placeholder="Entrada"
                                        style="width: 108px"
                                    />
                                    <span class="text-gray-400">–</span>
                                    <el-time-picker
                                        v-model="row.end_time"
                                        value-format="HH:mm:ss"
                                        format="HH:mm"
                                        placeholder="Salida"
                                        style="width: 108px"
                                    />
                                    <div class="flex items-center gap-2 ml-auto">
                                        <span class="text-xs text-gray-500">Comida</span>
                                        <el-input-number
                                            v-model="row.meal_minutes"
                                            :min="0"
                                            :max="480"
                                            :controls="false"
                                            style="width: 64px"
                                        />
                                        <span class="text-xs text-gray-500">min</span>
                                    </div>
                                </template>
                                <span v-else class="text-xs text-gray-400">Día de descanso</span>
                            </div>
                        </div>
                    </el-form-item>
                </template>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item v-if="shiftForm.type !== 'per_day'" label="Minutos de comida" :error="shiftForm.errors.meal_minutes">
                        <el-input-number v-model="shiftForm.meal_minutes" :min="0" :max="480" :controls="false" style="width: 100%" />
                    </el-form-item>

                    <el-form-item label="Comida pagada" :error="shiftForm.errors.is_meal_paid">
                        <el-switch v-model="shiftForm.is_meal_paid" />
                    </el-form-item>
                </div>

                <el-form-item v-if="shiftForm.type !== 'per_day'" label="Días de la semana" required :error="shiftForm.errors.days">
                    <el-checkbox-group v-model="shiftForm.days">
                        <el-checkbox-button v-for="day in weekDayOptions" :key="day.value" :value="day.value" :title="day.label">
                            {{ dayShort(day.value) }}
                        </el-checkbox-button>
                    </el-checkbox-group>
                </el-form-item>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Tolerancia de retardo (min, opcional)" :error="shiftForm.errors.late_tolerance_minutes">
                        <el-input-number v-model="shiftForm.late_tolerance_minutes" :min="0" :max="120" :controls="false" placeholder="Usar la global" style="width: 100%" />
                    </el-form-item>

                    <el-form-item label="Turno activo" :error="shiftForm.errors.is_active">
                        <el-switch v-model="shiftForm.is_active" />
                    </el-form-item>
                </div>

                <el-form-item label="Descripción" :error="shiftForm.errors.description">
                    <el-input v-model="shiftForm.description" type="textarea" :rows="2" maxlength="255" placeholder="Notas opcionales" />
                </el-form-item>
            </el-form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="shiftDialog = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="shiftForm.processing" @click="saveShift">
                        {{ editingShift ? 'Guardar cambios' : 'Crear turno' }}
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Assignment dialog -->
        <el-dialog v-model="assignmentDialog" title="Nueva asignación de turno" width="640px" top="8vh">
            <el-form :model="assignmentForm" label-position="top" size="default">
                <el-form-item label="Asignar a" :error="assignmentForm.errors.user_id || assignmentForm.errors.department">
                    <el-radio-group v-model="targetType">
                        <el-radio-button value="user">Colaborador</el-radio-button>
                        <el-radio-button value="department">Departamento</el-radio-button>
                    </el-radio-group>
                </el-form-item>

                <el-form-item v-if="targetType === 'user'" label="Colaborador" required :error="assignmentForm.errors.user_id">
                    <el-select v-model="assignmentForm.user_id" filterable placeholder="Seleccionar colaborador" class="w-full">
                        <el-option v-for="user in users" :key="user.id" :label="user.name" :value="user.id" />
                    </el-select>
                    <p class="text-xs text-gray-400 mt-1">Solo aparecen colaboradores con asistencia habilitada.</p>
                </el-form-item>

                <el-form-item v-else label="Departamento" required :error="assignmentForm.errors.department">
                    <el-input v-model="assignmentForm.department" placeholder="Ej. Obras" maxlength="255" />
                </el-form-item>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Tipo de asignación" required :error="assignmentForm.errors.type">
                        <el-select v-model="assignmentForm.type" class="w-full">
                            <el-option v-for="option in assignmentTypeOptions" :key="option.value" :label="option.label" :value="option.value" />
                        </el-select>
                    </el-form-item>

                    <el-form-item v-if="assignmentForm.type === 'fixed'" label="Turno" required :error="assignmentForm.errors.shift_id">
                        <el-select v-model="assignmentForm.shift_id" placeholder="Seleccionar turno" class="w-full">
                            <el-option v-for="shift in shifts" :key="shift.id" :label="shift.name" :value="shift.id" />
                        </el-select>
                    </el-form-item>
                </div>

                <el-form-item v-if="assignmentForm.type === 'rotation'" label="Turnos de la rotación (en orden semanal)" required :error="assignmentForm.errors.rotation">
                    <el-select v-model="assignmentForm.rotation" multiple placeholder="Selecciona los turnos en el orden de rotación" class="w-full">
                        <el-option v-for="shift in shifts" :key="shift.id" :label="shift.name" :value="shift.id" />
                    </el-select>
                    <p class="text-xs text-gray-400 mt-1">El primer turno se aplica la primera semana y rota en el orden seleccionado.</p>
                </el-form-item>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Fecha de inicio" required :error="assignmentForm.errors.start_date">
                        <el-date-picker v-model="assignmentForm.start_date" type="date" value-format="YYYY-MM-DD" placeholder="Seleccionar fecha" class="w-full" />
                    </el-form-item>

                    <el-form-item label="Fecha final (opcional)" :error="assignmentForm.errors.end_date">
                        <el-date-picker v-model="assignmentForm.end_date" type="date" value-format="YYYY-MM-DD" placeholder="Indefinida" class="w-full" />
                    </el-form-item>
                </div>

                <el-form-item label="Notas" :error="assignmentForm.errors.notes">
                    <el-input v-model="assignmentForm.notes" maxlength="255" placeholder="Comentarios opcionales" />
                </el-form-item>
            </el-form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="assignmentDialog = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="assignmentForm.processing" @click="saveAssignment">
                        Guardar asignación
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </AppLayout>
</template>

<style scoped>
/* Softer tables that blend with the card background in both themes. */
:deep(.el-table) {
    --el-table-bg-color: transparent;
    --el-table-tr-bg-color: transparent;
    --el-table-row-hover-bg-color: rgba(242, 108, 23, 0.06);
}

:deep(.el-table th.el-table__cell) {
    background-color: transparent;
    font-size: 12px;
    color: var(--el-text-color-secondary);
}

:deep(.el-table .el-table__cell) {
    padding: 12px 0;
}
</style>
