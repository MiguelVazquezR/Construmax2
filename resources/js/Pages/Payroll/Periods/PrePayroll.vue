<script setup>
import { computed, onBeforeUnmount } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Back, Calendar, ChatDotRound, Printer, User, Warning } from '@element-plus/icons-vue';

const props = defineProps({
    period: Object,
    rows: Array,
    stats: Object,
    incidents: Array,
    notes: Array,
    typeLabels: Object,
});

// The pre-nómina is a light-only document: while it is open the dark theme is
// removed from the document and the user preference is restored when leaving.
const hadDarkTheme = document.documentElement.classList.contains('dark');
document.documentElement.classList.remove('dark');

onBeforeUnmount(() => {
    if (hadDarkTheme) {
        document.documentElement.classList.add('dark');
    }
});

const isPreview = computed(() => props.period?.status === 'open');

const listedUserIds = computed(() => new Set((props.rows || []).map((row) => row.user_id)));

// --- Formatting helpers ---

const parseDate = (value) => {
    const raw = String(value).substring(0, 10);
    const [year, month, day] = raw.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const formatDate = (value) => {
    if (!value) return '—';
    return parseDate(value).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
};

const formatLongDate = (value) => {
    if (!value) return '—';
    return parseDate(value).toLocaleDateString('es-MX', { day: '2-digit', month: 'long', year: 'numeric' });
};

const formatDays = (value) => Number(value || 0).toLocaleString('es-MX', { maximumFractionDigits: 2 });

const dayUnit = (value) => (Number(value) === 1 ? 'día' : 'días');

const hasUnpaidDays = (row) => Number(row.unpaid_days || 0) > 0;

const subtitleOf = (row) => {
    const parts = [row.employee_number, row.position, row.department].filter(
        (part) => part && String(part).trim() !== '',
    );

    return parts.length > 0 ? parts.join(' · ') : 'Sin datos de perfil';
};

// --- Incidents ---

const incidentTagType = (type) => ({
    absence_unjustified: 'danger',
    absence_justified: 'primary',
    medical_leave: 'info',
    permission_paid: 'primary',
    permission_unpaid: 'warning',
    vacation: 'warning',
    other: 'info',
}[type] || 'info');

const incidentsOf = (row) => (props.incidents || []).filter((incident) => incident.user_id === row.user_id);

const incidentDates = (incident) => {
    if (!incident.end_date || incident.end_date === incident.start_date) {
        return formatDate(incident.start_date);
    }

    return `${formatDate(incident.start_date)} — ${formatDate(incident.end_date)}`;
};

// Holidays are paid by law (and pay an extra day when worked), so they travel
// with the incidents to explain every paid day of the collaborator.
const holidayEntries = (row) => (row.days || [])
    .filter((day) => day.status === 'holiday')
    .map((day) => ({
        key: `holiday-${day.date}`,
        date: day.date,
        tag: 'Día festivo',
        tagType: 'success',
        dates: formatDate(day.date),
        days: 1,
        paid: true,
        notes: [
            day.holiday_name,
            Number(day.worked_minutes || 0) > 0 ? 'trabajado (pago extra)' : null,
        ].filter(Boolean).join(' · ') || null,
    }));

const incidentEntries = (row) => incidentsOf(row).map((incident) => ({
    key: `incident-${incident.id}`,
    date: incident.start_date,
    tag: incident.type_label,
    tagType: incidentTagType(incident.type),
    dates: incidentDates(incident),
    days: incident.days,
    paid: incident.resolved_is_paid,
    notes: incident.notes,
}));

const entriesOf = (row) => [...holidayEntries(row), ...incidentEntries(row)]
    .sort((a, b) => (a.date < b.date ? -1 : 1));

const periodEntriesCount = computed(
    () => (props.rows || []).reduce((total, row) => total + entriesOf(row).length, 0),
);

// --- Comments ---

const notesOf = (row) => (props.notes || []).filter((note) => note.user_id === row.user_id);

const periodNotes = computed(
    () => (props.notes || []).filter((note) => listedUserIds.value.has(note.user_id)),
);

// --- Totals row ---

const summary = ({ columns }) => columns.map((column, index) => {
    if (index === 0) return `${(props.rows || []).length} colaborador(es)`;

    switch (column.property) {
        case 'days_paid':
            return formatDays(props.stats?.days_paid);
        case 'incidents':
            return `${periodEntriesCount.value}`;
        case 'comments':
            return `${periodNotes.value.length}`;
        default:
            return '';
    }
});

const print = () => window.print();

const goBack = () => window.close();
</script>

<template>
    <Head title="Pre-nómina" />

    <div class="pre-payroll-page min-h-screen bg-[#f4f6f8] print:bg-white">
        <!-- Toolbar (hidden when printing) -->
        <div class="print:hidden sticky top-0 z-10 bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between gap-4">
            <div>
                <h1 class="font-semibold text-gray-800">Pre-nómina</h1>
                <p class="text-sm text-gray-500">
                    Periodo {{ period.label }} · {{ typeLabels?.[period.type] }}
                    <el-tag :type="isPreview ? 'warning' : 'success'" size="small" effect="plain" class="ml-2">
                        {{ isPreview ? 'Abierto (cálculo en tiempo real)' : 'Cerrado' }}
                    </el-tag>
                </p>
            </div>
            <div class="flex gap-2">
                <el-button :icon="Back" @click="goBack">Cerrar</el-button>
                <el-button type="primary" color="#f26c17" :icon="Printer" @click="print">
                    Imprimir / Guardar PDF
                </el-button>
            </div>
        </div>

        <div class="pre-payroll max-w-[1200px] mx-auto py-6 px-4 print:max-w-none print:py-0 print:px-0 space-y-4">
            <!-- Heading, only when printing -->
            <div class="hidden print:block">
                <h1 class="text-lg font-bold text-gray-900">Pre-nómina</h1>
                <p class="text-sm text-gray-600">
                    Periodo {{ period.label }} · {{ rows.length }} colaborador(es) ·
                    {{ isPreview ? 'Documento preliminar' : 'Periodo cerrado' }}
                </p>
            </div>

            <!-- Period date range and head count -->
            <div class="bg-white rounded-lg border border-gray-200 px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-[#fdf0e7] text-[#f26c17] flex items-center justify-center shrink-0">
                        <el-icon :size="18"><Calendar /></el-icon>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Periodo de pago</p>
                        <p class="font-semibold text-gray-800">
                            Del {{ formatLongDate(period.start_date) }} al {{ formatLongDate(period.end_date) }}
                        </p>
                    </div>
                </div>
                <div class="sm:text-right">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Total de empleados</p>
                    <p class="font-semibold text-gray-800">{{ rows.length }} colaborador(es)</p>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-white rounded-lg border border-gray-200 px-5 py-4 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center shrink-0">
                        <el-icon :size="18"><User /></el-icon>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Colaboradores</p>
                        <p class="text-xl font-bold text-gray-800">{{ rows.length }}</p>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 px-5 py-4 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-[#fdf0e7] text-[#f26c17] flex items-center justify-center shrink-0">
                        <el-icon :size="18"><Calendar /></el-icon>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Días a pagar</p>
                        <p class="text-xl font-bold text-[#f26c17]">{{ formatDays(stats?.days_paid) }}</p>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 px-5 py-4 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">
                        <el-icon :size="18"><Warning /></el-icon>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Incidencias</p>
                        <p class="text-xl font-bold text-gray-800">{{ periodEntriesCount }}</p>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 px-5 py-4 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-sky-50 text-sky-500 flex items-center justify-center shrink-0">
                        <el-icon :size="18"><ChatDotRound /></el-icon>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Comentarios</p>
                        <p class="text-xl font-bold text-gray-800">{{ periodNotes.length }}</p>
                    </div>
                </div>
            </div>

            <!-- Pre-payroll table -->
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <el-table
                    :data="rows"
                    style="width: 100%"
                    stripe
                    show-summary
                    :summary-method="summary"
                    row-key="user_id"
                >
                    <el-table-column label="Colaborador" min-width="230">
                        <template #default="scope">
                            <div>
                                <p class="font-semibold text-gray-800">{{ scope.row.user_name }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ subtitleOf(scope.row) }}</p>
                                <el-tag v-if="scope.row.termination_date" type="danger" size="small" effect="plain" class="mt-1">
                                    Baja {{ formatDate(scope.row.termination_date) }}
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column prop="days_paid" label="Días a pagar" width="130" align="center">
                        <template #default="scope">
                            <p class="text-lg font-semibold text-gray-800 leading-6">{{ formatDays(scope.row.days_paid) }}</p>
                            <p v-if="hasUnpaidDays(scope.row)" class="text-xs text-red-500 mt-0.5">
                                {{ formatDays(scope.row.unpaid_days) }} sin pago
                            </p>
                        </template>
                    </el-table-column>

                    <el-table-column prop="incidents" label="Incidencias" min-width="300">
                        <template #default="scope">
                            <div v-if="entriesOf(scope.row).length > 0" class="space-y-2 py-0.5">
                                <div v-for="entry in entriesOf(scope.row)" :key="entry.key" class="flex items-start gap-2">
                                    <el-tag :type="entry.tagType" size="small" effect="plain" class="shrink-0 mt-0.5">
                                        {{ entry.tag }}
                                    </el-tag>
                                    <div class="min-w-0">
                                        <p class="text-xs text-gray-700 leading-5">
                                            {{ entry.dates }} · {{ formatDays(entry.days) }} {{ dayUnit(entry.days) }} ·
                                            <span :class="entry.paid ? 'text-emerald-600' : 'text-red-500'">
                                                {{ entry.paid ? 'con goce' : 'sin goce' }}
                                            </span>
                                        </p>
                                        <p v-if="entry.notes" class="text-xs text-gray-400 leading-5">{{ entry.notes }}</p>
                                    </div>
                                </div>
                            </div>
                            <span v-else class="text-sm text-gray-400">Sin incidencias</span>
                        </template>
                    </el-table-column>

                    <el-table-column prop="comments" label="Comentarios" min-width="260">
                        <template #default="scope">
                            <div v-if="notesOf(scope.row).length > 0" class="space-y-2 py-0.5">
                                <div v-for="note in notesOf(scope.row)" :key="note.id">
                                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ note.body }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        {{ note.author_name || 'Sistema' }} · {{ note.created_at }}<template v-if="note.is_edited"> · editado</template>
                                    </p>
                                </div>
                            </div>
                            <span v-else class="text-sm text-gray-400">Sin comentarios</span>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-if="rows.length === 0" class="text-center py-16 text-gray-500">
                    No hay colaboradores sujetos a nómina en este periodo.
                </div>
            </div>

            <p v-if="isPreview" class="text-xs text-amber-700">
                Documento preliminar de pre-nómina: los días pueden cambiar hasta el cierre del periodo.
            </p>
            <p class="text-xs text-gray-500">
                Los importes de percepciones, deducciones y neto se consultan en el detalle del periodo.
            </p>
        </div>
    </div>
</template>

<style scoped>
.pre-payroll-page {
    color-scheme: light;
}

@media print {
    .pre-payroll {
        padding: 0;
        margin: 0;
    }

    .pre-payroll :deep(.el-table),
    .pre-payroll :deep(.el-table__inner-wrapper),
    .pre-payroll :deep(.el-table__body-wrapper),
    .pre-payroll :deep(.el-scrollbar__wrap) {
        overflow: visible !important;
        height: auto !important;
    }
}

@page {
    size: A4 landscape;
    margin: 8mm;
}
</style>
