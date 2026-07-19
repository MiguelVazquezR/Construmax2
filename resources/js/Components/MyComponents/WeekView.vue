<script setup>
import { ref, computed } from 'vue';
import { ElButton, ElIcon } from 'element-plus';
import { ArrowLeft, ArrowRight } from '@element-plus/icons-vue';

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------

const props = defineProps({
    /**
     * Array of events to render. Each event must have:
     *  - id: number|string
     *  - title: string
     *  - start_time: string (ISO 8601 or Y-m-d H:i:s)
     *  - end_time: string   (ISO 8601 or Y-m-d H:i:s)
     *  - is_all_day: boolean
     *  - description?: string
     */
    events: { type: Array, default: () => [] },

    /**
     * Reference date for the week. The component will compute the Monday
     * of the containing week. Defaults to today.
     */
    currentDate: { type: [Date, String], default: null },
});

// ---------------------------------------------------------------------------
// Emits
// ---------------------------------------------------------------------------

const emit = defineEmits([
    'create',        // payload: { date: string, time: string }
    'edit',          // payload: event object
    'delete',        // payload: event object
    'date-change',   // payload: new Date (Monday of the new week)
]);

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const ROW_HEIGHT = 48;  // px per hour (slightly smaller than day view)
const TOTAL_HOURS = 24;

const WEEKDAY_LABELS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

const HOUR_LABELS = (() => {
    const labels = [];
    for (let h = 0; h < 24; h++) {
        if (h === 0) labels.push('12 AM');
        else if (h < 12) labels.push(`${h} AM`);
        else if (h === 12) labels.push('12 PM');
        else labels.push(`${h - 12} PM`);
    }
    return labels;
})();

// ---------------------------------------------------------------------------
// Reactive state
// ---------------------------------------------------------------------------

/** Whether the multi-day banner is collapsed. */
const bannerCollapsed = ref(false);

// ---------------------------------------------------------------------------
// Derived: week boundaries
// ---------------------------------------------------------------------------

/** Monday 00:00:00 of the week containing viewingDate. */
const weekStart = computed(() => {
    const d = new Date(props.currentDate || new Date());
    const day = d.getDay(); // 0 = Sun
    const diff = day === 0 ? -6 : 1 - day; // Move to Monday
    d.setDate(d.getDate() + diff);
    d.setHours(0, 0, 0, 0);
    return d;
});

/** Array of 7 Date objects (Sunday … Saturday). */
const weekDays = computed(() => {
    const days = [];
    // Start from Sunday of the same ISO week
    const sunday = new Date(weekStart.value);
    sunday.setDate(sunday.getDate() - 1);
    for (let i = 0; i < 7; i++) {
        const d = new Date(sunday);
        d.setDate(d.getDate() + i);
        days.push(d);
    }
    return days;
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function isToday(d) {
    const now = new Date();
    return (
        d.getFullYear() === now.getFullYear() &&
        d.getMonth() === now.getMonth() &&
        d.getDate() === now.getDate()
    );
}

function formatTime(iso) {
    const d = new Date(iso);
    const h = d.getHours();
    const m = d.getMinutes().toString().padStart(2, '0');
    if (h === 0) return `12:${m} AM`;
    if (h < 12) return `${h}:${m} AM`;
    if (h === 12) return `12:${m} PM`;
    return `${h - 12}:${m} PM`;
}

function formatWeekRange(start) {
    const end = new Date(start);
    end.setDate(end.getDate() + 6);
    const sm = start.toLocaleDateString('es-MX', { month: 'long' });
    const em = end.toLocaleDateString('es-MX', { month: 'long' });
    if (sm === em) {
        return `${sm} ${start.getDate()} – ${end.getDate()}, ${end.getFullYear()}`;
    }
    return `${sm} ${start.getDate()} – ${em} ${end.getDate()}, ${end.getFullYear()}`;
}

function dayKey(d) {
    return d.toISOString().slice(0, 10);
}

/**
 * Return the start of a given date (midnight).
 */
function startOfDay(d) {
    const copy = new Date(d);
    copy.setHours(0, 0, 0, 0);
    return copy;
}

/**
 * Return the start of the next day.
 */
function endOfDay(d) {
    const copy = startOfDay(d);
    copy.setDate(copy.getDate() + 1);
    return copy;
}

// ---------------------------------------------------------------------------
// Event classification
// ---------------------------------------------------------------------------

/** Multi-day events that span more than one calendar day AND overlap the current week. */
const multiDayEvents = computed(() => {
    const weekStartTs = weekStart.value.getTime();
    const weekEndTs = weekStartTs + 7 * 24 * 60 * 60 * 1000;

    return props.events.filter((e) => {
        const s = startOfDay(new Date(e.start_time)).getTime();
        const en = startOfDay(new Date(e.end_time)).getTime();
        // Must span more than one calendar day
        if (en <= s) return false;
        // Must overlap with the current week
        return s < weekEndTs && en > weekStartTs;
    });
});

/**
 * Returns the set of daily hourly events (non-all-day, non-multi-day)
 * keyed by day ISO string.
 */
const dailyEvents = computed(() => {
    const map = {};
    for (const d of weekDays.value) {
        map[dayKey(d)] = [];
    }

    const multiIds = new Set(multiDayEvents.value.map((e) => e.id));

    for (const ev of props.events) {
        if (ev.is_all_day) continue;
        if (multiIds.has(ev.id)) continue;

        const s = new Date(ev.start_time);
        const key = dayKey(s);
        if (map[key]) {
            map[key].push(ev);
        }
    }

    return map;
});

// ---------------------------------------------------------------------------
// Collision / overlap algorithm (per-day basis)
// ---------------------------------------------------------------------------

function computeLayout(events, dayDate) {
    if (!events.length) return [];

    const dayStartTs = startOfDay(dayDate).getTime();
    const dayEndTs = endOfDay(dayDate).getTime();

    // 1. Normalise
    const items = events.map((ev) => {
        const s = Math.max(new Date(ev.start_time).getTime(), dayStartTs);
        const e = Math.min(new Date(ev.end_time).getTime(), dayEndTs);
        const top = ((s - dayStartTs) / (1000 * 60)) * (ROW_HEIGHT / 60);
        const height = Math.max(((e - s) / (1000 * 60)) * (ROW_HEIGHT / 60), 15);
        return { ...ev, _start: s, _end: e, _top: top, _height: height };
    });

    // Sort by start time, then longer first
    items.sort((a, b) => {
        const diff = a._start - b._start;
        if (diff !== 0) return diff;
        return b._end - b._start - (a._end - a._start);
    });

    // 2. Group into clusters
    const clusters = [];
    let cur = [items[0]];
    let clusterEnd = items[0]._end;

    for (let i = 1; i < items.length; i++) {
        if (items[i]._start < clusterEnd) {
            cur.push(items[i]);
            clusterEnd = Math.max(clusterEnd, items[i]._end);
        } else {
            clusters.push(cur);
            cur = [items[i]];
            clusterEnd = items[i]._end;
        }
    }
    clusters.push(cur);

    // 3. Assign columns
    const result = [];

    for (const cluster of clusters) {
        const cols = [];
        for (const item of cluster) {
            let placed = false;
            for (let c = 0; c < cols.length; c++) {
                if (cols[c] <= item._start) {
                    cols[c] = item._end;
                    item._column = c;
                    placed = true;
                    break;
                }
            }
            if (!placed) {
                item._column = cols.length;
                cols.push(item._end);
            }
        }
        const totalCols = cols.length;
        for (const item of cluster) {
            item._width = 100 / totalCols;
            item._left = item._column * (100 / totalCols);
            result.push(item);
        }
    }

    return result;
}

/** Precomputed layouts for all 7 days. */
const laidOutEventsByDay = computed(() => {
    const map = {};
    for (const d of weekDays.value) {
        const key = dayKey(d);
        map[key] = computeLayout(dailyEvents.value[key] || [], d);
    }
    return map;
});

// ---------------------------------------------------------------------------
// Multi-day banner layout
// ---------------------------------------------------------------------------

/**
 * Build rows for the multi-day banner. Each row is an array of
 * events that do not overlap each other (similar to the collision
 * algorithm but horizontal).
 */
const multiDayRows = computed(() => {
    const evs = multiDayEvents.value
        .map((ev) => {
            const s = new Date(ev.start_time);
            const e = new Date(ev.end_time);
            // Find which day columns (0-6 = Sun-Sat) the event occupies
            let startCol = -1;
            let endCol = -1;
            for (let i = 0; i < 7; i++) {
                const day = weekDays.value[i];
                const dayStartTs = startOfDay(day).getTime();
                const dayEndTs = endOfDay(day).getTime();
                if (s.getTime() < dayEndTs && e.getTime() > dayStartTs) {
                    if (startCol === -1) startCol = i;
                    endCol = i;
                }
            }
            return { ...ev, _startCol: startCol, _endCol: endCol };
        })
        .sort((a, b) => {
            const diff = a._startCol - b._startCol;
            if (diff !== 0) return diff;
            return (b._endCol - b._startCol) - (a._endCol - a._startCol);
        });

    const rows = [];

    for (const ev of evs) {
        let placed = false;
        for (const row of rows) {
            const overlaps = row.some(
                (r) => !(ev._endCol < r._startCol || ev._startCol > r._endCol),
            );
            if (!overlaps) {
                row.push(ev);
                placed = true;
                break;
            }
        }
        if (!placed) {
            rows.push([ev]);
        }
    }

    return rows;
});

// ---------------------------------------------------------------------------
// Navigation
// ---------------------------------------------------------------------------

function goPrevWeek() {
    const d = new Date(weekStart.value);
    d.setDate(d.getDate() - 7);
    emit('date-change', d);
}

function goThisWeek() {
    const d = new Date();
    const day = d.getDay();
    d.setDate(d.getDate() - (day === 0 ? 6 : day - 1));
    emit('date-change', d);
}

function goNextWeek() {
    const d = new Date(weekStart.value);
    d.setDate(d.getDate() + 7);
    emit('date-change', d);
}

// ---------------------------------------------------------------------------
// Interaction handlers
// ---------------------------------------------------------------------------

function onSlotClick(dayDate, event) {
    if (event.target !== event.currentTarget) return;

    const rect = event.currentTarget.getBoundingClientRect();
    const y = event.clientY - rect.top;
    const totalMinutes = (y / ROW_HEIGHT) * 60;
    const hours = Math.floor(totalMinutes / 60);
    const minutes = Math.floor(totalMinutes % 60);

    const target = new Date(dayDate);
    target.setHours(hours, minutes, 0, 0);

    emit('create', {
        date: target.toISOString().slice(0, 10),
        time: target.toTimeString().slice(0, 5),
    });
}

function onEventClick(ev) {
    emit('edit', ev);
}

// ---------------------------------------------------------------------------
// Expose
// ---------------------------------------------------------------------------

defineExpose({ weekStart, weekDays });
</script>

<template>
    <div class="flex flex-col h-full bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200/60 dark:border-zinc-800 overflow-hidden">

        <!-- ================================================================= -->
        <!-- Header                                                           -->
        <!-- ================================================================= -->
        <div class="flex items-center justify-between px-5 py-3 border-b border-zinc-200/60 dark:border-zinc-800 shrink-0">
            <h2 class="text-lg font-medium text-zinc-700 dark:text-zinc-300 select-none">
                {{ formatWeekRange(weekStart) }}
            </h2>

            <div class="flex items-center gap-1">
                <el-button size="small" text @click="goPrevWeek">
                    <el-icon><ArrowLeft /></el-icon>
                </el-button>
                <el-button size="small" text @click="goThisWeek">
                    Hoy
                </el-button>
                <el-button size="small" text @click="goNextWeek">
                    <el-icon><ArrowRight /></el-icon>
                </el-button>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- Multi-day banner                                                 -->
        <!-- ================================================================= -->
        <div
            v-if="multiDayRows.length > 0"
            class="border-b border-zinc-200/60 dark:border-zinc-800 shrink-0"
        >
            <!-- Toggle bar -->
            <button
                class="w-full flex items-center gap-2 px-4 py-1.5 text-xs text-zinc-500 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors"
                @click="bannerCollapsed = !bannerCollapsed"
            >
                <svg
                    class="w-3 h-3 transition-transform"
                    :class="{ 'rotate-90': !bannerCollapsed }"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                {{ bannerCollapsed ? 'Mostrar' : 'Ocultar' }} eventos de varios días
                <span class="text-zinc-400 dark:text-zinc-500 ml-1">({{ multiDayEvents.length }})</span>
            </button>

            <!-- Banner rows -->
            <div v-if="!bannerCollapsed" class="px-4 pb-2 space-y-1">
                <!-- 8-column grid: empty col for time gutter + 7 day cols -->
                <div
                    v-for="(row, rIdx) in multiDayRows"
                    :key="'banner-row-' + rIdx"
                    class="grid grid-cols-[4rem_repeat(7,1fr)] gap-x-px"
                >
                    <div /> <!-- empty time-gutter spacer -->
                    <div
                        v-for="ev in row"
                        :key="'banner-' + ev.id"
                        class="px-3 py-1 text-xs rounded cursor-pointer hover:opacity-80 transition-colors truncate border-l-2 flex items-center gap-1"
                        :class="{ 'opacity-60': ev._completed }"
                        :style="{
                            gridColumn: Math.max(ev._startCol, 0) + 2 + ' / ' + (Math.min(ev._endCol, 6) + 3),
                            backgroundColor: ev._completed ? '#9ca3af20' : (ev.color || '#6366f1') + '18',
                            borderLeftColor: ev._completed ? '#9ca3af' : (ev.color || '#6366f1'),
                            color: ev._completed ? '#6b7280' : (ev.color || '#4338ca'),
                        }"
                        @click="onEventClick(ev)"
                    >
                        <span v-if="ev._startCol < 0" class="text-[9px] opacity-50 shrink-0" title="Proviene de semanas pasadas">←</span>
                        <span class="truncate">{{ ev.title }}</span>
                        <span v-if="ev._endCol > 6" class="text-[9px] opacity-50 shrink-0" title="Continúa en semanas futuras">→</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- Sticky weekday headers                                           -->
        <!-- ================================================================= -->
        <div
            class="grid grid-cols-[4rem_repeat(7,1fr)] shrink-0 sticky top-0 z-10 bg-white dark:bg-zinc-900 border-b border-zinc-200/60 dark:border-zinc-800"
        >
            <!-- Empty corner cell (above time labels) -->
            <div class="border-r border-zinc-200/60 dark:border-zinc-800" />

            <!-- Day headers -->
            <div
                v-for="(day, idx) in weekDays"
                :key="'hdr-' + idx"
                class="flex flex-col items-center py-2 border-r border-zinc-200/60 dark:border-zinc-800 last:border-r-0"
                :class="isToday(day) ? 'bg-orange-50/50 dark:bg-orange-900/10' : ''"
            >
                <span class="text-[11px] uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                    {{ WEEKDAY_LABELS[idx] }}
                </span>
                <span
                    class="text-base font-semibold leading-none mt-0.5"
                    :class="
                        isToday(day)
                            ? 'text-orange-600 dark:text-orange-400'
                            : 'text-zinc-700 dark:text-zinc-300'
                    "
                >
                    {{ day.getDate() }}
                </span>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- Scrollable time grid                                              -->
        <!-- ================================================================= -->
        <div class="flex-1 overflow-y-auto">
            <div class="grid grid-cols-[4rem_repeat(7,1fr)]" :style="{ minHeight: TOTAL_HOURS * ROW_HEIGHT + 'px' }">
                <!-- ---------- Time labels ---------- -->
                <div class="border-r border-zinc-200/60 dark:border-zinc-800 select-none z-[1] bg-white dark:bg-zinc-900">
                    <div
                        v-for="(label, idx) in HOUR_LABELS"
                        :key="'lbl-' + idx"
                        class="flex items-start justify-end pr-2 text-[10px] text-zinc-400 dark:text-zinc-500"
                        :style="{ height: ROW_HEIGHT + 'px' }"
                    >
                        <span v-if="idx % 2 === 0">{{ label }}</span>
                    </div>
                </div>

                <!-- ---------- 7 day columns ---------- -->
                <div
                    v-for="(day, dayIdx) in weekDays"
                    :key="'daycol-' + dayIdx"
                    class="relative border-r border-zinc-200/60 dark:border-zinc-800 last:border-r-0"
                    :class="isToday(day) ? 'bg-orange-50/10 dark:bg-orange-900/5' : ''"
                    :style="{ height: TOTAL_HOURS * ROW_HEIGHT + 'px' }"
                    @click="(e) => onSlotClick(day, e)"
                >
                    <!-- Grid lines -->
                    <div
                        v-for="h in TOTAL_HOURS"
                        :key="'gl-' + dayIdx + '-' + h"
                        class="absolute left-0 right-0 border-t border-zinc-100/80 dark:border-zinc-800/60"
                        :class="h % 2 === 0 ? 'border-zinc-200/40 dark:border-zinc-700/40' : ''"
                        :style="{ top: (h - 1) * ROW_HEIGHT + 'px' }"
                    />

                    <!-- Current-time line -->
                    <div
                        v-if="isToday(day)"
                        class="absolute left-0 right-0 z-[2] pointer-events-none"
                        :style="{ top: (new Date().getHours() * 60 + new Date().getMinutes()) * (ROW_HEIGHT / 60) + 'px' }"
                    >
                        <div class="w-1.5 h-1.5 rounded-full bg-red-500 -ml-[3px] -mt-[3px]" />
                        <div class="h-px bg-red-500/70 w-full" />
                    </div>

                    <!-- Events -->
                    <div
                        v-for="ev in laidOutEventsByDay[dayKey(day)]"
                        :key="'ev-' + ev.id"
                        class="absolute rounded px-1.5 py-0.5 text-[10px] leading-tight cursor-pointer overflow-hidden transition-shadow hover:shadow-md hover:z-[3] border-l-2"
                        :class="{ 'opacity-60': ev._completed }"
                        :style="{
                            top: ev._top + 'px',
                            height: ev._height + 'px',
                            left: ev._left + '%',
                            width: 'calc(' + ev._width + '% - 3px)',
                            backgroundColor: ev._completed ? '#9ca3af20' : (ev.color || '#409EFF') + '18',
                            borderLeftColor: ev._completed ? '#9ca3af' : (ev.color || '#409EFF'),
                            color: ev._completed ? '#6b7280' : (ev.color || '#3b82f6'),
                        }"
                        @click.stop="onEventClick(ev)"
                    >
                        <div class="font-medium truncate">{{ ev.title }}</div>
                        <div class="opacity-70 truncate">
                            {{ formatTime(ev.start_time) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Subtle scrollbar */
div::-webkit-scrollbar {
    width: 6px;
}
div::-webkit-scrollbar-track {
    background: transparent;
}
div::-webkit-scrollbar-thumb {
    background-color: #d4d4d8;
    border-radius: 3px;
}
.dark div::-webkit-scrollbar-thumb {
    background-color: #3f3f46;
}
</style>
