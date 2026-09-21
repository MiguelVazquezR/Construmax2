<script setup>
import { computed } from 'vue';
import { Calendar, Clock, InfoFilled, Money, Suitcase, User } from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    // False for technicians: they record attendance but are not in the payroll.
    showPayrollFields: {
        type: Boolean,
        default: true,
    },
    // Active shifts available to assign (empty list hides the selector).
    shifts: {
        type: Array,
        default: () => [],
    },
});

const { can } = usePermissions();

const canManage = computed(() => can('payroll.profiles.manage'));
const canManageRemote = computed(() => can('payroll.remote-attendance.manage'));
const isVisible = computed(() => canManage.value || canManageRemote.value);

// --- Horario ---

const SHIFT_TYPE_LABELS = {
    fixed: 'Fijo',
    flexible: 'Flexible',
    per_day: 'Por día',
};

const WEEKDAY_LABELS = {
    1: 'Lunes',
    2: 'Martes',
    3: 'Miércoles',
    4: 'Jueves',
    5: 'Viernes',
    6: 'Sábado',
    7: 'Domingo',
};

const selectedShift = computed(
    () => props.shifts.find((shift) => shift.id === props.form.shift_id) || null
);

const shiftTypeLabel = computed(() =>
    selectedShift.value ? SHIFT_TYPE_LABELS[selectedShift.value.type] || selectedShift.value.type : null
);

const timeLabel = (value) => (value ? String(value).substring(0, 5) : null);

// Summary of the selected shift: which days are worked and their schedule.
const shiftDays = computed(() => {
    const shift = selectedShift.value;

    if (! shift) {
        return [];
    }

    const schedules = shift.day_schedules || {};
    const workdays = (shift.days || []).map(Number);

    return Object.entries(WEEKDAY_LABELS).map(([day, fullLabel]) => {
        const weekday = Number(day);
        const schedule = schedules[weekday] ?? schedules[day] ?? null;
        const works = shift.type === 'per_day' ? Boolean(schedule) : workdays.includes(weekday);

        return {
            day: weekday,
            label: fullLabel.slice(0, 3),
            fullLabel,
            works,
            start: works && schedule ? timeLabel(schedule.start_time) : (works ? timeLabel(shift.start_time) : null),
            end: works && schedule ? timeLabel(schedule.end_time) : (works ? timeLabel(shift.end_time) : null),
        };
    });
});

const workedDaysLabel = computed(() =>
    shiftDays.value.filter((day) => day.works).map((day) => day.fullLabel).join(', ')
);

const mealLabel = computed(() => {
    const shift = selectedShift.value;

    if (! shift) {
        return null;
    }

    if (shift.type === 'per_day') {
        return 'según el día';
    }

    return `${shift.meal_minutes} min${shift.is_meal_paid ? ' (pagada)' : ''}`;
});
</script>

<template>
    <div v-if="isVisible" class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 border-b border-gray-100 dark:border-gray-700 pb-2 flex items-center gap-2">
            <el-icon class="text-primary"><Suitcase /></el-icon> Nómina y asistencia
        </h3>

        <!-- Datos del colaborador -->
        <div v-if="canManage" class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529] p-4">
            <p class="flex items-center gap-2 text-xs uppercase tracking-wider text-gray-400 font-bold mb-3">
                <el-icon><User /></el-icon> Colaborador
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
                <el-form-item label="Número de empleado" prop="employee_number" :error="form.errors.employee_number">
                    <el-input v-model="form.employee_number" placeholder="Se genera automáticamente" />
                </el-form-item>

                <el-form-item label="Fecha de ingreso" prop="hire_date" :error="form.errors.hire_date">
                    <el-date-picker
                        v-model="form.hire_date"
                        type="date"
                        value-format="YYYY-MM-DD"
                        placeholder="Seleccionar fecha"
                        class="w-full"
                    />
                </el-form-item>

                <el-form-item
                    v-if="showPayrollFields"
                    label="Fecha de baja"
                    prop="termination_date"
                    :error="form.errors.termination_date"
                >
                    <el-date-picker
                        v-model="form.termination_date"
                        type="date"
                        value-format="YYYY-MM-DD"
                        placeholder="Vacío si sigue activo"
                        class="w-full"
                    />
                </el-form-item>
            </div>

            <p v-if="showPayrollFields" class="text-xs text-gray-400">
                Con fecha de baja, el colaborador permanece en la nómina hasta ese día y deja de aparecer en los periodos siguientes.
            </p>
        </div>

        <!-- Nómina -->
        <div v-if="canManage && showPayrollFields" class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529] p-4">
            <p class="flex items-center gap-2 text-xs uppercase tracking-wider text-gray-400 font-bold mb-3">
                <el-icon><Money /></el-icon> Nómina
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
                <el-form-item label="Sueldo diario" prop="daily_salary" :error="form.errors.daily_salary">
                    <el-input-number
                        v-model="form.daily_salary"
                        :min="0"
                        :max="9999999"
                        :precision="2"
                        :controls="false"
                        placeholder="0.00"
                        style="width: 100%"
                    />
                </el-form-item>

                <el-form-item label="Horas por día" prop="daily_hours" :error="form.errors.daily_hours">
                    <el-input-number
                        v-model="form.daily_hours"
                        :min="1"
                        :max="24"
                        :precision="2"
                        :controls="false"
                        placeholder="8"
                        style="width: 100%"
                    />
                </el-form-item>
            </div>

            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 dark:border-[#2b2b2e] bg-white dark:bg-[#1e1e20] px-3 py-2.5">
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Sujeto a nómina</p>
                    <p class="text-xs text-gray-400">Aparece en los periodos y recibe sueldo por sus días pagados.</p>
                </div>
                <el-switch v-model="form.is_payroll_subject" style="--el-switch-on-color: #f26c17;" />
            </div>
        </div>

        <!-- Horario -->
        <div v-if="canManage && shifts.length > 0" class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529] p-4">
            <div class="flex items-center justify-between gap-3 mb-3">
                <p class="flex items-center gap-2 text-xs uppercase tracking-wider text-gray-400 font-bold">
                    <el-icon><Calendar /></el-icon> Horario
                </p>

                <el-popover v-if="selectedShift" placement="bottom-end" :width="300" trigger="click">
                    <template #reference>
                        <button
                            type="button"
                            title="Ver resumen del horario"
                            class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-200 dark:border-[#3f3f46] text-gray-400 transition hover:border-[#f26c17] hover:text-[#f26c17]"
                        >
                            <el-icon :size="15"><InfoFilled /></el-icon>
                        </button>
                    </template>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ selectedShift.name }}</p>
                            <el-tag
                                size="small"
                                effect="plain"
                                :type="selectedShift.type === 'per_day' ? 'success' : (selectedShift.type === 'fixed' ? 'primary' : 'warning')"
                            >
                                {{ shiftTypeLabel }}
                            </el-tag>
                        </div>

                        <template v-if="selectedShift.type === 'per_day'">
                            <div class="space-y-1.5">
                                <div
                                    v-for="day in shiftDays"
                                    :key="day.day"
                                    class="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span class="w-16 text-gray-500">{{ day.label }}</span>
                                    <span v-if="day.works" class="text-gray-700 dark:text-gray-200">{{ day.start }} – {{ day.end }}</span>
                                    <span v-else class="text-gray-300 dark:text-gray-600">Descanso</span>
                                </div>
                            </div>
                        </template>

                        <template v-else>
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="day in shiftDays"
                                    :key="day.day"
                                    class="rounded px-1.5 py-0.5 text-[11px] font-medium"
                                    :class="day.works
                                        ? 'bg-[#fdf0e7] text-[#c2540c] dark:bg-[#3a2a1d] dark:text-[#f9a05f]'
                                        : 'bg-gray-100 text-gray-400 dark:bg-[#2b2b2e] dark:text-gray-500'"
                                >
                                    {{ day.label }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-700 dark:text-gray-200">
                                <template v-if="selectedShift.type === 'fixed'">{{ timeLabel(selectedShift.start_time) }} – {{ timeLabel(selectedShift.end_time) }}</template>
                                <template v-else>{{ Number(selectedShift.required_daily_hours) }} h flexibles al día</template>
                            </p>
                        </template>

                        <p class="text-xs text-gray-400">Días: {{ workedDaysLabel }}</p>
                        <p class="text-xs text-gray-400">Comida: {{ mealLabel }}</p>
                    </div>
                </el-popover>
            </div>

            <el-form-item label="Horario asignado" prop="shift_id" :error="form.errors.shift_id" class="!mb-1">
                <el-select v-model="form.shift_id" filterable placeholder="Sin horario asignado" class="w-full">
                    <el-option v-for="shift in shifts" :key="shift.id" :label="shift.name" :value="shift.id" />
                </el-select>
            </el-form-item>
            <p class="text-xs text-gray-400">
                Define sus días laborables, el horario esperado y los retardos. Sin horario propio se usa el asignado a su departamento.
            </p>
        </div>

        <!-- Asistencia -->
        <div v-if="canManage || canManageRemote" class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529] p-4 space-y-3">
            <p class="flex items-center gap-2 text-xs uppercase tracking-wider text-gray-400 font-bold">
                <el-icon><Clock /></el-icon> Asistencia
            </p>

            <div
                v-if="canManage"
                class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 dark:border-[#2b2b2e] bg-white dark:bg-[#1e1e20] px-3 py-2.5"
            >
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Registra asistencia</p>
                    <p class="text-xs text-gray-400">Puede registrar en el kiosco y en el portal Mi asistencia.</p>
                </div>
                <el-switch v-model="form.is_attendance_subject" style="--el-switch-on-color: #f26c17;" />
            </div>

            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 dark:border-[#2b2b2e] bg-white dark:bg-[#1e1e20] px-3 py-2.5">
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Asistencia remota</p>
                    <p class="text-xs text-gray-400">
                        Puede registrar entrada y salida desde su cuenta con ubicación.
                        <template v-if="!form.is_attendance_subject">Requiere activar «Registra asistencia».</template>
                    </p>
                </div>
                <el-switch
                    v-model="form.can_remote_attendance"
                    :disabled="!form.is_attendance_subject"
                    style="--el-switch-on-color: #f26c17;"
                />
            </div>
        </div>
    </div>
</template>
