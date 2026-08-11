<script setup>
import { computed } from 'vue';
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

    /** The day being viewed — a Date, ISO string, or null (defaults to today). */
    currentDate: { type: [Date, String], default: null },
});

// ---------------------------------------------------------------------------
// Emits
// ---------------------------------------------------------------------------

const emit = defineEmits([
    'create',          // payload: { date: string, time: string }
    'edit',            // payload: event object
    'delete',          // payload: event object
    'date-change',     // payload: new Date
]);

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

/** Height (in px) allocated per one hour slot. */
const ROW_HEIGHT = 60;

/** Labels for the time axis (1 AM … 12 AM, 1 PM … 11 PM). */
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

const TOTAL_HOURS = 24;

// ---------------------------------------------------------------------------
// Reactive state
// ---------------------------------------------------------------------------

/** The date that is actually rendered (normalised from the prop). */
const viewingDate = computed(() => {
    if (props.currentDate) return new Date(props.currentDate);
    return new Date();
});

/** Start-of-day Date object (midnight, local). */
const dayStart = computed(() => {
    const d = new Date(viewingDate.value);
    d.setHours(0, 0, 0, 0);
    return d;
});

// ---------------------------------------------------------------------------
// Derived data
// ---------------------------------------------------------------------------

/** All-day events (sorted by title). */
const allDayEvents = computed(() =>
    props.events
        .filter((e) => e.is_all_day)
        .sort((a, b) => a.title.localeCompare(b.title)),
);

/** Hourly events that fall (at least partially) on the viewing date. */
const hourlyEvents = computed(() => {
    const startTs = dayStart.value.getTime();
    const endTs = startTs + 24 * 60 * 60 * 1000;

    return props.events
        .filter((e) => {
            if (e.is_all_day) return false;
            const s = new Date(e.start_time).getTime();
            const e2 = new Date(e.end_time).getTime();
            return s < endTs && e2 > startTs;
        })
        .sort((a, b) => {
            const diff = new Date(a.start_time) - new Date(b.start_time);
            if (diff !== 0) return diff;
            // Longer events first to improve collision layout
            const durA = new Date(a.end_time) - new Date(a.start_time);
            const durB = new Date(b.end_time) - new Date(b.start_time);
            return durB - durA;
        });
});

// ---------------------------------------------------------------------------
// Collision / overlap algorithm
// ---------------------------------------------------------------------------

/**
 * Given an array of events that occur on the same day, this function
 * computes per-event width / left offsets so that overlapping events
 * are laid out side-by-side instead of being hidden behind one another.
 */
function computeLayout(events) {
    if (!events.length) return [];

    const startTs = dayStart.value.getTime();
    const maxTs = startTs + TOTAL_HOURS * 60 * 60 * 1000;

    // 1. Build normalised event objects with pixel positions
    const items = events.map((ev) => {
        const s = Math.max(new Date(ev.start_time).getTime(), startTs);
        const e = Math.min(new Date(ev.end_time).getTime(), maxTs);
        const top = ((s - startTs) / (1000 * 60)) * (ROW_HEIGHT / 60);
        const height = ((e - s) / (1000 * 60)) * (ROW_HEIGHT / 60);
        return { ...ev, _start: s, _end: e, _top: top, _height: Math.max(height, 15) };
    });

    // 2. Group into collision clusters
    const clusters = [];
    let currentCluster = [items[0]];
    let clusterEnd = items[0]._end;

    for (let i = 1; i < items.length; i++) {
        if (items[i]._start < clusterEnd) {
            currentCluster.push(items[i]);
            clusterEnd = Math.max(clusterEnd, items[i]._end);
        } else {
            clusters.push(currentCluster);
            currentCluster = [items[i]];
            clusterEnd = items[i]._end;
        }
    }
    clusters.push(currentCluster);

    // 3. Assign columns within each cluster
    const result = [];

    for (const cluster of clusters) {
        const cols = []; // each element is the last end-time for that column

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

/** Events with layout metadata (top, height, width, left). */
const laidOutEvents = computed(() => computeLayout(hourlyEvents.value));

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function formatTime(iso) {
    const d = new Date(iso);
    const h = d.getHours();
    const m = d.getMinutes().toString().padStart(2, '0');
    if (h === 0) return `12:${m} AM`;
    if (h < 12) return `${h}:${m} AM`;
    if (h === 12) return `12:${m} PM`;
    return `${h - 12}:${m} PM`;
}

function formatDateHeader(d) {
    return d.toLocaleDateString('es-MX', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function isToday(d) {
    const now = new Date();
    return (
        d.getFullYear() === now.getFullYear() &&
        d.getMonth() === now.getMonth() &&
        d.getDate() === now.getDate()
    );
}

// ---------------------------------------------------------------------------
// Navigation
// ---------------------------------------------------------------------------

function goPrev() {
    const d = new Date(viewingDate.value);
    d.setDate(d.getDate() - 1);
    emit('date-change', d);
}

function goToday() {
    emit('date-change', new Date());
}

function goNext() {
    const d = new Date(viewingDate.value);
    d.setDate(d.getDate() + 1);
    emit('date-change', d);
}

// ---------------------------------------------------------------------------
// Interaction handlers
// ---------------------------------------------------------------------------

/**
 * Click on an empty slot of the grid. Determines the target time from the
 * Y offset of the click and emits a `create` event.
 */
function onGridClick(event) {
    // Only react to clicks directly on the grid background, not on events
    if (event.target !== event.currentTarget) return;

    const rect = event.currentTarget.getBoundingClientRect();
    const y = event.clientY - rect.top;
    const totalMinutes = (y / ROW_HEIGHT) * 60;
    const hours = Math.floor(totalMinutes / 60);
    const minutes = Math.floor(totalMinutes % 60);

    const target = new Date(dayStart.value);
    target.setHours(hours, minutes, 0, 0);

    const dateStr = target.toISOString().slice(0, 10);
    const timeStr = target.toTimeString().slice(0, 5);

    emit('create', { date: dateStr, time: timeStr });
}

function onEventClick(event) {
    emit('edit', event);
}

// ---------------------------------------------------------------------------
// Exposed for potential parent interaction
// ---------------------------------------------------------------------------

defineExpose({ viewingDate, allDayEvents, laidOutEvents });
</script>

<template>
    <div class="flex flex-col h-full bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200/60 dark:border-zinc-800 overflow-hidden">

        <!-- ================================================================= -->
        <!-- Header                                                           -->
        <!-- ================================================================= -->
        <div class="flex items-center justify-between px-5 py-3 border-b border-zinc-200/60 dark:border-zinc-800 shrink-0">
            <!-- Left: date title -->
            <h2 class="text-lg font-medium text-zinc-700 dark:text-zinc-300 select-none">
                {{ formatDateHeader(viewingDate) }}
            </h2>

            <!-- Right: navigation -->
            <div class="flex items-center gap-1">
                <el-button size="small" text @click="goPrev">
                    <el-icon><ArrowLeft /></el-icon>
                </el-button>
                <el-button size="small" text @click="goToday">
                    Hoy
                </el-button>
                <el-button size="small" text @click="goNext">
                    <el-icon><ArrowRight /></el-icon>
                </el-button>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- All-day section                                                  -->
        <!-- ================================================================= -->
        <div
            v-if="allDayEvents.length > 0"
            class="px-4 py-2 border-b border-zinc-200/60 dark:border-zinc-800 shrink-0 space-y-1"
        >
            <div
                v-for="ev in allDayEvents"
                :key="'all-' + ev.id"
                class="px-3 py-1 text-sm rounded bg-primary/10 text-primary dark:bg-primary/20 dark:text-orange-300 cursor-pointer hover:bg-primary/20 dark:hover:bg-primary/30 transition-colors truncate"
                @click="onEventClick(ev)"
            >
                {{ ev.title }}
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- Time grid                                                        -->
        <!-- ================================================================= -->
        <div class="flex flex-1 overflow-y-auto relative">
            <!-- Time labels column (fixed width) -->
            <div
                class="w-16 shrink-0 border-r border-zinc-200/60 dark:border-zinc-800 bg-white dark:bg-zinc-900 select-none z-[1]"
            >
                <div
                    v-for="(label, idx) in HOUR_LABELS"
                    :key="'lbl-' + idx"
                    class="flex items-start justify-end pr-2 text-xs text-zinc-400 dark:text-zinc-500"
                    :style="{ height: ROW_HEIGHT + 'px' }"
                >
                    <!-- Only show label for even hours to keep it clean -->
                    <span v-if="idx % 2 === 0">{{ label }}</span>
                </div>
            </div>

            <!-- Main event canvas -->
            <div
                class="relative flex-1"
                :style="{ height: TOTAL_HOURS * ROW_HEIGHT + 'px', minHeight: TOTAL_HOURS * ROW_HEIGHT + 'px' }"
                @click="onGridClick"
            >
                <!-- Hour grid lines -->
                <div
                    v-for="h in TOTAL_HOURS"
                    :key="'grid-' + h"
                    class="absolute left-0 right-0 border-t border-zinc-100/80 dark:border-zinc-800/60"
                    :class="h % 2 === 0 ? 'border-zinc-200/40 dark:border-zinc-700/40' : ''"
                    :style="{ top: (h - 1) * ROW_HEIGHT + 'px' }"
                />

                <!-- Current-time red line -->
                <div
                    v-if="isToday(viewingDate)"
                    class="absolute left-0 right-0 z-[2] pointer-events-none"
                    :style="{ top: (new Date().getHours() * 60 + new Date().getMinutes()) * (ROW_HEIGHT / 60) + 'px' }"
                >
                    <div class="w-2 h-2 rounded-full bg-red-500 -ml-1 -mt-1" />
                    <div class="h-px bg-red-500/70 w-full" />
                </div>

                <!-- Events -->
                <div
                    v-for="ev in laidOutEvents"
                    :key="'ev-' + ev.id"
                    class="absolute rounded px-2 py-0.5 text-xs cursor-pointer overflow-hidden transition-shadow hover:shadow-md hover:z-[3] border-l-2"
                    :class="{ 'opacity-60': ev._completed }"
                    :style="{
                        top: ev._top + 'px',
                        height: ev._height + 'px',
                        left: ev._left + '%',
                        width: 'calc(' + ev._width + '% - 4px)',
                        backgroundColor: ev._completed ? '#9ca3af20' : (ev.color || '#409EFF') + '18',
                        borderLeftColor: ev._completed ? '#9ca3af' : (ev.color || '#409EFF'),
                        color: ev._completed ? '#6b7280' : (ev.color || '#3b82f6'),
                    }"
                    @click.stop="onEventClick(ev)"
                >
                    <div class="font-medium leading-tight truncate">{{ ev.title }}</div>
                    <div class="opacity-70 leading-tight">
                        {{ formatTime(ev.start_time) }} – {{ formatTime(ev.end_time) }}
                    </div>
                </div>

                <!-- Also render all-day events as tiny strips inside the grid if needed (optional) -->
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Subtle scrollbar styling consistent with Element Plus minimalism */
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
