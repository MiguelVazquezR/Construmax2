<script setup>
import { computed } from 'vue';
import { InfoFilled } from '@element-plus/icons-vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    // Active shifts available to assign (empty list hides the popover help).
    shifts: {
        type: Array,
        default: () => [],
    },
});

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
    <div>
        <el-form-item label="Horario asignado" prop="shift_id" :error="form.errors.shift_id" :required="form.is_payroll_subject" class="!mb-1">
            <template #label>
                <span class="inline-flex items-center gap-1.5">
                    Horario asignado
                    <el-popover v-if="selectedShift" placement="bottom-start" :width="300" trigger="click">
                        <template #reference>
                            <button
                                type="button"
                                title="Ver resumen del horario"
                                class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-gray-200 dark:border-[#3f3f46] text-gray-400 transition hover:border-[#f26c17] hover:text-[#f26c17]"
                            >
                                <el-icon :size="13"><InfoFilled /></el-icon>
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
                </span>
            </template>

            <el-select v-model="form.shift_id" filterable placeholder="Sin horario asignado" class="w-full">
                <el-option v-for="shift in shifts" :key="shift.id" :label="shift.name" :value="shift.id" />
            </el-select>
        </el-form-item>

        <p class="text-xs text-gray-400">
            Define sus días laborables
        </p>
    </div>
</template>
