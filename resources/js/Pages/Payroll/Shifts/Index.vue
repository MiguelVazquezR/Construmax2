<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessageBox } from 'element-plus';
import { Delete, Edit, InfoFilled, OfficeBuilding, Plus } from '@element-plus/icons-vue';
import { useFlashMessages } from '@/Composables/useFlashMessages';

// NOTE: the assignments panel was retired from this screen and kept as legacy
// (the payroll.shift-assignments.* endpoints remain available for a future
// re-enable). The shift is assigned from the user and technician forms through
// AssignUserShiftAction, while the assignments prop feeds the "who has this
// schedule" dialog of the shift rows.
const props = defineProps({
    shifts: Array,
    assignments: Array,
    shiftTypes: Object,
    shiftTypeDescriptions: Object,
    weekDays: Object,
    globalLateToleranceMinutes: { type: Number, default: null },
});

useFlashMessages();

const formatTime = (value) => (value ? String(value).substring(0, 5) : null);

// "Lunes" → "Lun" for the compact day chips.
const dayShort = (day) => String(props.weekDays?.[day] || '').slice(0, 3);

const formatDuration = (minutes) => {
    const hours = Math.floor(Number(minutes || 0) / 60);
    const rest = Math.round(Number(minutes || 0) % 60);

    return rest > 0 ? `${hours} h ${rest} min` : `${hours} h`;
};

// "ANA LÓPEZ" → "AL" avatar initials.
const initialsOf = (name) => {
    const parts = String(name || '').trim().split(/\s+/).slice(0, 2);

    return parts.map((part) => part.charAt(0).toUpperCase()).join('') || '?';
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
    // Shifts are always built with the per-day editor: the fixed and flexible
    // types are kept as legacy and remain renderable for old records only.
    type: 'per_day',
    meal_minutes: 60,
    // New schedules start with paid meals and paid rest days: the usual
    // arrangement of the company.
    is_meal_paid: true,
    pays_rest_days: true,
    days: [1, 2, 3, 4, 5],
    day_schedules: {},
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
        shiftForm.type = 'per_day';
        shiftForm.meal_minutes = shift.meal_minutes;
        shiftForm.is_meal_paid = Boolean(shift.is_meal_paid);
        shiftForm.pays_rest_days = Boolean(shift.pays_rest_days);
        shiftForm.late_tolerance_minutes = shift.late_tolerance_minutes;
        shiftForm.is_active = Boolean(shift.is_active);
        shiftForm.description = shift.description || '';

        // Per-day shifts load their schedule into the day editor. Legacy
        // fixed/flexible shifts are translated to the per-day editor: their
        // worked days are enabled with the shift times (or the defaults when
        // the legacy type had no fixed schedule).
        const schedules = shift.day_schedules || {};
        const legacySchedule = shift.type === 'fixed'
            ? {
                start_time: shift.start_time,
                end_time: shift.end_time,
                meal_minutes: Number(shift.meal_minutes ?? 0),
            }
            : DAY_DEFAULTS;
        const legacyDays = (shift.days || []).map(Number);

        dayRows.value = dayRows.value.map((row) => {
            const schedule = schedules[row.day] ?? schedules[String(row.day)] ?? null;

            if (schedule) {
                return {
                    ...row,
                    enabled: true,
                    start_time: schedule.start_time,
                    end_time: schedule.end_time,
                    meal_minutes: Number(schedule.meal_minutes ?? 0),
                };
            }

            if (shift.type !== 'per_day' && legacyDays.includes(row.day)) {
                return { ...row, enabled: true, ...legacySchedule };
            }

            return row;
        });
    } else {
        shiftForm.reset();

        // A new shift starts with monday to friday enabled on the default
        // schedule.
        dayRows.value = dayRows.value.map((row) => (row.day <= 5 ? { ...row, enabled: true } : row));
    }

    shiftDialog.value = true;
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
    dayRows.value.filter((row) => row.enabled).reduce((total, row) => total + dayMinutes(row), 0)
);

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
        `¿Eliminar el horario "${shift.name}"?`,
        'Eliminar horario',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        shiftForm.delete(route('payroll.shifts.destroy', shift.id));
    }).catch(() => {});
};

// --- Assignees dialog: who has this schedule ---

const assigneesDialog = ref(false);
const assigneesShift = ref(null);

const assigneesDialogTitle = computed(() =>
    assigneesShift.value
        ? `Asignaciones del horario "${assigneesShift.value.name}"`
        : 'Asignaciones del horario'
);

const openAssigneesDialog = (shift) => {
    assigneesShift.value = shift;
    assigneesDialog.value = true;
};

// Assignments that reference a shift: fixed and department rows plus legacy
// rotations whose weekly cycle includes it. Active ones come first.
const shiftAssignees = (shift) => {
    if (! shift) {
        return [];
    }

    return (props.assignments || [])
        .filter((assignment) =>
            Number(assignment.shift_id) === Number(shift.id)
            || (assignment.type === 'rotation' && (assignment.rotation || []).map(Number).includes(Number(shift.id)))
        )
        .sort((left, right) => {
            if (left.is_active !== right.is_active) {
                return left.is_active ? -1 : 1;
            }

            return String(right.start_date || '').localeCompare(String(left.start_date || ''));
        });
};

const shiftTagType = (type) => (type === 'per_day' ? 'success' : (type === 'fixed' ? 'primary' : 'warning'));

</script>

<template>
    <AppLayout title="Horarios del personal">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">Horarios del personal</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Configura el horario por día de la semana y define si los días de descanso se pagan. Asigna el horario desde la ficha del colaborador.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <el-button type="primary" color="#f26c17" :icon="Plus" @click="openShiftDialog()">
                        Nuevo horario
                    </el-button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <div class="px-4 pt-4">
                    <el-alert
                        type="info"
                        :closable="false"
                        show-icon
                        title="Los horarios se asignan desde la ficha del colaborador"
                        description="Al crear o editar un usuario o técnico, elige su horario en la sección «Nómina y asistencia». Los horarios que registres aquí quedarán disponibles para asignarlos."
                    />
                </div>

                <el-table :data="shifts" style="width: 100%" stripe>
                    <el-table-column label="Horario" min-width="230">
                        <template #default="scope">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ scope.row.name }}</span>
                                <el-tooltip :content="shiftTypeDescriptions?.[scope.row.type]" placement="top" :show-after="200">
                                    <el-tag size="small" effect="plain" :type="shiftTagType(scope.row.type)">
                                        {{ shiftTypes[scope.row.type] }}
                                    </el-tag>
                                </el-tooltip>
                                <el-tag v-if="! scope.row.is_active" type="danger" size="small" effect="plain">Inactivo</el-tag>
                                <el-tag
                                    v-if="scope.row.pays_rest_days"
                                    type="success"
                                    size="small"
                                    effect="plain"
                                    title="Los días de descanso de este horario se pagan"
                                >
                                    Descanso pagado
                                </el-tag>
                            </div>
                            <div v-if="scope.row.description" class="text-xs text-gray-500 mt-0.5">{{ scope.row.description }}</div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Días y horas" min-width="280">
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
                        </template>
                    </el-table-column>

                    <el-table-column label="Tolerancia" width="140" align="center">
                        <template #default="scope">
                            <span v-if="scope.row.late_tolerance_minutes !== null" class="text-sm">
                                {{ scope.row.late_tolerance_minutes }} min
                            </span>
                            <el-tooltip v-else content="Se usa la tolerancia global de ajustes de nómina" placement="top" :show-after="200">
                                <span class="text-sm text-gray-500">Global ({{ globalLateToleranceMinutes }} min)</span>
                            </el-tooltip>
                        </template>
                    </el-table-column>

                    <el-table-column label="Asignaciones" width="125" align="center">
                        <template #default="scope">
                            <el-tooltip content="Ver los colaboradores con este horario" placement="top" :show-after="200">
                                <el-tag
                                    size="small"
                                    :type="scope.row.assignments_count > 0 ? 'primary' : 'info'"
                                    effect="plain"
                                    class="cursor-pointer"
                                    @click="openAssigneesDialog(scope.row)"
                                >
                                    {{ scope.row.assignments_count }}
                                </el-tag>
                            </el-tooltip>
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
                    <el-empty description="Sin horarios registrados" :image-size="100">
                        <template #default>
                            <p class="text-sm text-gray-500">Crea el primer horario para asignarlo desde la ficha de un colaborador.</p>
                        </template>
                    </el-empty>
                </div>
            </div>
        </div>

        <!-- Shift dialog -->
        <el-dialog
            v-model="shiftDialog"
            :title="editingShift ? `Editar horario: ${editingShift.name}` : 'Nuevo horario'"
            width="680px"
            top="8vh"
        >
            <el-form :model="shiftForm" label-position="top" size="default">
                <el-form-item label="Nombre" required :error="shiftForm.errors.name">
                    <el-input v-model="shiftForm.name" placeholder="Ej. Horario matutino" maxlength="120" />
                </el-form-item>

                <!-- Cómo funciona el horario por día -->
                <div class="flex items-start gap-2 rounded-lg bg-[#fdf0e7] dark:bg-[#3a2a1d] px-3 py-2 mb-4">
                    <el-icon class="mt-0.5 shrink-0 text-[#f26c17]"><InfoFilled /></el-icon>
                    <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                        {{ shiftTypeDescriptions?.per_day }}
                    </p>
                </div>

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

                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529] px-3 py-2.5 mb-4">
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Pagar días de descanso</p>
                        <p class="text-xs text-gray-400">
                            Los días sin horario se pagan de forma proporcional a los días con goce de la semana
                            (asistencia, vacaciones, permisos con goce y días festivos).
                        </p>
                    </div>
                    <el-switch v-model="shiftForm.pays_rest_days" style="--el-switch-on-color: #f26c17;" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529] px-3 py-2.5">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Comida pagada</p>
                            <p class="text-xs text-gray-400">El tiempo de comida cuenta como trabajado.</p>
                        </div>
                        <el-switch v-model="shiftForm.is_meal_paid" style="--el-switch-on-color: #f26c17;" />
                    </div>

                    <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529] px-3 py-2.5">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Horario activo</p>
                            <p class="text-xs text-gray-400">Disponible para asignarlo a los colaboradores.</p>
                        </div>
                        <el-switch v-model="shiftForm.is_active" style="--el-switch-on-color: #f26c17;" />
                    </div>
                </div>

                <el-form-item label="Tolerancia de retardo (min, opcional)" :error="shiftForm.errors.late_tolerance_minutes">
                    <el-input-number v-model="shiftForm.late_tolerance_minutes" :min="0" :max="120" :controls="false" placeholder="Usar la global" style="width: 100%" />
                </el-form-item>

                <el-form-item label="Descripción" :error="shiftForm.errors.description">
                    <el-input v-model="shiftForm.description" type="textarea" :rows="2" maxlength="255" placeholder="Notas opcionales" />
                </el-form-item>
            </el-form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="shiftDialog = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="shiftForm.processing" @click="saveShift">
                        {{ editingShift ? 'Guardar cambios' : 'Crear horario' }}
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Assignees dialog: collaborators with this schedule -->
        <el-dialog
            v-model="assigneesDialog"
            :title="assigneesDialogTitle"
            width="480px"
            top="12vh"
        >
            <el-empty
                v-if="shiftAssignees(assigneesShift).length === 0"
                description="Sin colaboradores asignados"
                :image-size="80"
            >
                <template #default>
                    <p class="text-sm text-gray-500">Asigna este horario desde la ficha del colaborador (usuario o técnico).</p>
                </template>
            </el-empty>

            <el-table v-else :data="shiftAssignees(assigneesShift)" size="small" style="width: 100%">
                <el-table-column label="Colaborador" min-width="230">
                    <template #default="scope">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-[11px] font-bold"
                                :class="scope.row.user_id
                                    ? 'bg-[#fdf0e7] dark:bg-[#3a2a1d] text-[#f26c17]'
                                    : 'bg-blue-50 dark:bg-blue-500/10 text-blue-500'"
                            >
                                <template v-if="scope.row.user_id">{{ initialsOf(scope.row.target_label) }}</template>
                                <el-icon v-else :size="14"><OfficeBuilding /></el-icon>
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <p class="font-semibold text-gray-800 dark:text-gray-100 leading-tight">{{ scope.row.target_label }}</p>
                                    <el-tag v-if="scope.row.user?.technician" size="small" type="warning" effect="plain">Técnico</el-tag>
                                    <el-tag v-if="scope.row.type === 'rotation'" size="small" type="warning" effect="light">Rotación</el-tag>
                                    <el-tag v-if="! scope.row.is_active" size="small" type="info" effect="plain">Inactiva</el-tag>
                                </div>
                                <p class="text-xs text-gray-400">{{ scope.row.user_id ? 'Colaborador' : 'Departamento' }}</p>
                            </div>
                        </div>
                    </template>
                </el-table-column>
            </el-table>

            <template #footer>
                <el-button @click="assigneesDialog = false">Cerrar</el-button>
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
