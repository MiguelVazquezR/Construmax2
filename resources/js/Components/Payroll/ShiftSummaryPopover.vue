<script setup>
import { computed } from 'vue';
import { InfoFilled } from '@element-plus/icons-vue';

// Read-only summary of a shift: which days are worked and their schedule,
// shown in a popover from the info icon.
const props = defineProps({
    shift: {
        type: Object,
        required: true,
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

const shiftTypeLabel = computed(() => SHIFT_TYPE_LABELS[props.shift.type] || props.shift.type);

const shiftTagType = computed(() =>
    props.shift.type === 'per_day' ? 'success' : (props.shift.type === 'fixed' ? 'primary' : 'warning')
);

const timeLabel = (value) => (value ? String(value).substring(0, 5) : null);

const shiftDays = computed(() => {
    const shift = props.shift;
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
    shiftDays.value.filter((day) => day.works).map((day) => day.fullLabel).join(', ') || 'Ninguno'
);

const mealLabel = computed(() => {
    const shift = props.shift;

    if (shift.type === 'per_day') {
        return 'según el día';
    }

    return `${shift.meal_minutes ?? 0} min${shift.is_meal_paid ? ' (pagada)' : ''}`;
});
</script>

<template>
    <el-popover placement="bottom-end" :width="300" trigger="click">
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
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ shift.name }}</p>
                <el-tag size="small" effect="plain" :type="shiftTagType">
                    {{ shiftTypeLabel }}
                </el-tag>
            </div>

            <template v-if="shift.type === 'per_day'">
                <div class="space-y-1.5">
                    <div v-for="day in shiftDays" :key="day.day" class="flex items-center justify-between gap-3 text-sm">
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
                    <template v-if="shift.type === 'fixed'">{{ timeLabel(shift.start_time) }} – {{ timeLabel(shift.end_time) }}</template>
                    <template v-else>{{ Number(shift.required_daily_hours) }} h flexibles al día</template>
                </p>
            </template>

            <p class="text-xs text-gray-400">Días: {{ workedDaysLabel }}</p>
            <p class="text-xs text-gray-400">Comida: {{ mealLabel }}</p>
        </div>
    </el-popover>
</template>
