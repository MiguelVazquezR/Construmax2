<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessageBox } from 'element-plus';
import { Back, Download, Lock, Unlock, Document, Tickets, Expand, Fold, ArrowLeft, ArrowRight } from '@element-plus/icons-vue';
import { useFlashMessages } from '@/Composables/useFlashMessages';
import { usePermissions } from '@/Composables/usePermissions';
import EmployeePeriodPanel from '@/Components/Payroll/EmployeePeriodPanel.vue';

const props = defineProps({
    period: Object,
    rows: Array,
    stats: Object,
    adjustments: Array,
    incidents: Array,
    notes: Array,
    previousPeriod: Object,
    nextPeriod: Object,
    typeLabels: Object,
});

useFlashMessages();

const { can } = usePermissions();
const canManage = can('payroll.periods.manage');
const canClose = can('payroll.periods.close');
const canManageIncidents = can('payroll.incidents.manage');

// --- Formatting ---

const parseDate = (value) => {
    const raw = String(value).substring(0, 10);
    const [year, month, day] = raw.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const formatDate = (value) => {
    if (!value) return '—';
    return parseDate(value).toLocaleDateString('es-MX', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
};

const money = (value) => `$${Number(value || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const hours = (minutes) => `${Math.floor(Number(minutes || 0) / 60)}:${String(Math.round(Number(minutes || 0) % 60)).padStart(2, '0')}`;

const initials = (name) => {
    const parts = String(name || '').trim().split(/\s+/).slice(0, 2);
    return parts.map((part) => part.charAt(0).toUpperCase()).join('') || '?';
};

// --- Collaborator panels: the detail is fetched when a panel opens for the first time ---

const activePanels = ref([]);
const mountedPanels = ref([]);

const handlePanelsChange = (names) => {
    mountedPanels.value = Array.from(new Set([...mountedPanels.value, ...names]));
};

// --- Collaborator filter: shows only the selected collaborators' panels ---

const collaboratorFilter = ref([]);

const visibleRows = computed(() => {
    const rows = props.rows || [];

    if (collaboratorFilter.value.length === 0) {
        return rows;
    }

    return rows.filter((row) => collaboratorFilter.value.includes(row.user_id));
});

// The selection travels to the pre-payroll and the receipts, so both pages
// can be printed for a subset of the period's collaborators.
const selectedUserIds = computed(() => (collaboratorFilter.value.length > 0 ? [...collaboratorFilter.value] : null));

const collaboratorLabel = (row) => `${row.name}${row.employee_number ? ` · ${row.employee_number}` : ''}`;

// Opening the filter expands the panels of the selected collaborators.
watch(collaboratorFilter, (selected) => {
    if (selected.length > 0) {
        activePanels.value = [...selected];
        handlePanelsChange(selected);
    }
});

const expandAll = () => {
    activePanels.value = visibleRows.value.map((row) => row.user_id);
    handlePanelsChange(activePanels.value);
};

const collapseAll = () => {
    activePanels.value = [];
};

const reloadPeriod = () => {
    router.reload({ only: ['rows', 'stats', 'adjustments', 'incidents', 'notes'] });
};

const incidentsCount = (row) => (props.incidents || []).filter((incident) => incident.user_id === row.user_id).length;

const notesCount = (row) => (props.notes || []).filter((note) => note.user_id === row.user_id).length;

// --- Date filter: narrows the days and punches of every collaborator ---

const fullRange = [String(props.period.start_date).substring(0, 10), String(props.period.end_date).substring(0, 10)];

const dateRange = ref([...fullRange]);

const isFiltered = computed(() => dateRange.value?.[0] !== fullRange[0] || dateRange.value?.[1] !== fullRange[1]);

const rangeFrom = computed(() => dateRange.value?.[0] || fullRange[0]);
const rangeTo = computed(() => dateRange.value?.[1] || fullRange[1]);

const rangeLabel = computed(() =>
    `${parseDate(rangeFrom.value).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' })} — ${parseDate(rangeTo.value).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' })}`
);

const disableOutsidePeriod = (date) =>
    date < parseDate(fullRange[0]) || date > parseDate(fullRange[1]);

const resetRange = () => {
    dateRange.value = [...fullRange];
};

// --- Period actions ---

const form = useForm({});

const closePeriod = () => {
    ElMessageBox.confirm(
        'Al cerrar el periodo se generan los recibos y se registra el gasto de nómina. ¿Continuar?',
        'Cerrar periodo',
        { confirmButtonText: 'Cerrar periodo', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        form.transform((data) => data).post(route('payroll.periods.close', props.period.id), {
            onSuccess: () => router.reload(),
        });
    }).catch(() => {});
};

const reopenPeriod = () => {
    ElMessageBox.confirm(
        'Se eliminarán los recibos y el gasto del periodo para poder editar la pre-nómina. ¿Continuar?',
        'Reabrir periodo',
        { confirmButtonText: 'Reabrir', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        form.transform((data) => data).post(route('payroll.periods.reopen', props.period.id), {
            onSuccess: () => router.reload(),
        });
    }).catch(() => {});
};

const exportPeriod = () => {
    window.location.href = route('payroll.periods.export', props.period.id);
};

const openPayslips = () => {
    const users = selectedUserIds.value;

    window.open(
        route('payroll.periods.payslips.print', { period: props.period.id, ...(users ? { users } : {}) }),
        '_blank'
    );
};

const openPrePayroll = () => {
    const users = selectedUserIds.value;

    window.open(
        route('payroll.periods.pre-payroll', { period: props.period.id, ...(users ? { users } : {}) }),
        '_blank'
    );
};
</script>

<template>
    <AppLayout :title="`Nómina ${formatDate(period.start_date)}`">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link :href="route('payroll.periods.index')">
                        <el-button :icon="Back" circle plain />
                    </Link>
                    <div class="flex items-center gap-1">
                        <el-tooltip
                            :content="previousPeriod ? `Periodo anterior · ${previousPeriod.label}` : 'No hay periodo anterior'"
                            placement="bottom"
                        >
                            <span>
                                <Link v-if="previousPeriod" :href="route('payroll.periods.show', previousPeriod.id)">
                                    <el-button :icon="ArrowLeft" circle plain />
                                </Link>
                                <el-button v-else :icon="ArrowLeft" circle plain disabled />
                            </span>
                        </el-tooltip>
                        <el-tooltip
                            :content="nextPeriod ? `Periodo siguiente · ${nextPeriod.label}` : 'No hay periodo siguiente'"
                            placement="bottom"
                        >
                            <span>
                                <Link v-if="nextPeriod" :href="route('payroll.periods.show', nextPeriod.id)">
                                    <el-button :icon="ArrowRight" circle plain />
                                </Link>
                                <el-button v-else :icon="ArrowRight" circle plain disabled />
                            </span>
                        </el-tooltip>
                    </div>
                    <div>
                        <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">
                            Nómina {{ formatDate(period.start_date) }} — {{ formatDate(period.end_date) }}
                        </h2>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ typeLabels[period.type] }} ·
                            <el-tag :type="period.status === 'open' ? 'warning' : 'success'" size="small" effect="plain" class="ml-1">
                                {{ period.status === 'open' ? 'Abierto (pre-nómina en tiempo real)' : 'Cerrado' }}
                            </el-tag>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <el-button
                        :icon="Document"
                        :title="collaboratorFilter.length > 0
                            ? 'Imprimir los recibos de los colaboradores seleccionados'
                            : (period.status === 'open'
                                ? 'Imprimir la pre-nómina del periodo abierto'
                                : 'Imprimir los recibos del periodo cerrado')"
                        @click="openPayslips"
                    >
                        Recibos
                    </el-button>
                    <el-button
                        :icon="Tickets"
                        :title="collaboratorFilter.length > 0
                            ? 'Ver la pre-nómina de los colaboradores seleccionados'
                            : (period.status === 'open'
                                ? 'Ver la pre-nómina de todos los colaboradores (cálculo en tiempo real)'
                                : 'Ver la nómina de todos los colaboradores del periodo')"
                        @click="openPrePayroll"
                    >
                        Pre-nómina
                    </el-button>
                    <el-button :icon="Download" @click="exportPeriod">Exportar</el-button>
                    <el-button v-if="canClose && period.status === 'open'" type="primary" color="#f26c17" :icon="Lock" @click="closePeriod">
                        Cerrar periodo
                    </el-button>
                    <el-button v-if="canClose && period.status === 'closed'" type="warning" plain :icon="Unlock" @click="reopenPeriod">
                        Reabrir periodo
                    </el-button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-4">
                <div class="bg-white dark:bg-[#1e1e20] rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Días pagados</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ stats.days_paid }}</p>
                </div>
                <div class="bg-white dark:bg-[#1e1e20] rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Días no pagados</p>
                    <p class="text-xl font-bold text-red-500">{{ stats.unpaid_days }}</p>
                </div>
                <div class="bg-white dark:bg-[#1e1e20] rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Retardos (min)</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ stats.late_minutes }}</p>
                </div>
                <div class="bg-white dark:bg-[#1e1e20] rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Horas extra</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ stats.overtime_hours }}</p>
                </div>
                <div class="bg-white dark:bg-[#1e1e20] rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Percepciones</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ money(stats.total_gross) }}</p>
                </div>
                <div class="bg-white dark:bg-[#1e1e20] rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Deducciones</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ money(stats.total_deductions) }}</p>
                </div>
                <div class="bg-white dark:bg-[#1e1e20] rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Neto</p>
                    <p class="text-xl font-bold text-emerald-600">{{ money(stats.total_net) }}</p>
                </div>
            </div>

            <!-- Filters: date range and collaborators (applies to the days and records of every collaborator) -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                <div class="flex flex-wrap items-end gap-x-6 gap-y-4">
                    <div class="shrink-0">
                        <p class="mb-1.5 text-xs font-bold uppercase tracking-wider text-gray-400">Rango de fechas</p>
                        <div class="flex items-center gap-2">
                            <el-date-picker
                                v-model="dateRange"
                                type="daterange"
                                unlink-panels
                                value-format="YYYY-MM-DD"
                                format="DD/MM/YYYY"
                                range-separator="a"
                                start-placeholder="Desde"
                                end-placeholder="Hasta"
                                :clearable="false"
                                :disabled-date="disableOutsidePeriod"
                            />
                            <el-button v-if="isFiltered" @click="resetRange">Ver todo el periodo</el-button>
                        </div>
                    </div>

                    <div class="hidden w-px self-stretch bg-gray-100 dark:bg-[#2b2b2e] lg:block"></div>

                    <div class="shrink-0">
                        <p class="mb-1.5 text-xs font-bold uppercase tracking-wider text-gray-400">Colaboradores</p>
                        <el-select
                            v-model="collaboratorFilter"
                            multiple
                            collapse-tags
                            collapse-tags-tooltip
                            filterable
                            clearable
                            placeholder="Todos"
                            class="!w-80 shrink-0"
                        >
                            <el-option
                                v-for="row in rows"
                                :key="row.user_id"
                                :label="collaboratorLabel(row)"
                                :value="row.user_id"
                            />
                        </el-select>
                    </div>
                </div>

                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Mostrando <strong>{{ rangeLabel }}</strong> en los días y registros.
                </p>
            </div>

            <!-- Collaborators: the detail of every one of them, collapsible -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-100 dark:border-[#2b2b2e]">
                    <p class="font-semibold text-gray-800 dark:text-gray-200">
                        Colaboradores ({{ visibleRows.length }}<template v-if="collaboratorFilter.length > 0"> de {{ rows.length }}</template>)
                    </p>
                    <div class="flex gap-2">
                        <el-button size="small" :icon="Expand" @click="expandAll">Expandir todos</el-button>
                        <el-button size="small" :icon="Fold" @click="collapseAll">Contraer todos</el-button>
                    </div>
                </div>

                <el-collapse v-model="activePanels" class="payroll-panels" @change="handlePanelsChange">
                    <el-collapse-item v-for="row in visibleRows" :key="row.user_id" :name="row.user_id">
                        <template #title>
                            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 w-full pr-4">
                                <div class="flex items-center gap-3 min-w-[230px]">
                                    <div class="w-9 h-9 rounded-full bg-[#fdf0e7] dark:bg-[#3a2a1d] text-[#f26c17] text-xs font-bold flex items-center justify-center shrink-0">
                                        {{ initials(row.name) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800 dark:text-gray-200">{{ row.name }}</p>
                                        <p class="text-xs text-gray-500 font-normal">
                                            {{ row.employee_number || 'Sin número' }}
                                            <template v-if="row.department"> · {{ row.department }}</template>
                                        </p>
                                    </div>
                                </div>

                                <div v-if="row.termination_date" class="text-xs font-normal">
                                    <el-tag type="danger" size="small" effect="plain">
                                        Baja {{ parseDate(row.termination_date).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' }) }}
                                    </el-tag>
                                </div>

                                <div class="text-xs text-gray-500 font-normal">
                                    Días pagados: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ row.days_paid }}</span>
                                </div>
                                <div class="text-xs text-gray-500 font-normal">
                                    No pagados:
                                    <span class="font-semibold" :class="row.unpaid_days > 0 ? 'text-red-500' : 'text-gray-700 dark:text-gray-200'">
                                        {{ row.unpaid_days }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 font-normal">
                                    Retardos: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ row.late_minutes }} min</span>
                                </div>
                                <div class="text-xs text-gray-500 font-normal">
                                    Extra: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ hours(row.overtime_minutes) }}</span>
                                </div>
                                <div class="text-xs text-gray-500 font-normal">
                                    Vacaciones: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ row.vacation_days }}</span>
                                </div>
                                <div class="text-xs text-gray-500 font-normal">
                                    Incapacidad: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ row.incapacity_days }}</span>
                                </div>
                                <div class="text-xs text-gray-500 font-normal">
                                    Ajustes:
                                    <span class="font-semibold text-gray-700 dark:text-gray-200">
                                        +{{ money(row.adjustments_earnings) }} / -{{ money(row.adjustments_deductions) }}
                                    </span>
                                </div>
                                <div v-if="incidentsCount(row) > 0" class="text-xs font-normal">
                                    <el-tag type="warning" size="small" effect="plain">
                                        {{ incidentsCount(row) }} incidencia(s)
                                    </el-tag>
                                </div>
                                <div v-if="notesCount(row) > 0" class="text-xs font-normal">
                                    <el-tag type="info" size="small" effect="plain">
                                        {{ notesCount(row) }} comentario(s)
                                    </el-tag>
                                </div>
                                <div class="ml-auto rounded-lg bg-emerald-50 dark:bg-emerald-900/20 px-2.5 py-1.5 text-sm font-semibold text-emerald-600">
                                    {{ money(row.total_net) }}
                                </div>
                            </div>
                        </template>

                        <EmployeePeriodPanel
                            v-if="mountedPanels.includes(row.user_id)"
                            :row="row"
                            :period="period"
                            :incidents="incidents"
                            :adjustments="adjustments"
                            :notes="notes"
                            :from="rangeFrom"
                            :to="rangeTo"
                            :can-manage="canManage"
                            :can-manage-incidents="canManageIncidents"
                            @changed="reloadPeriod"
                        />
                    </el-collapse-item>
                </el-collapse>

                <div v-if="rows.length === 0" class="text-center py-12">
                    <el-empty description="Sin colaboradores sujetos a nómina" :image-size="100" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.payroll-panels :deep(.el-collapse-item__header) {
    height: auto;
    min-height: 56px;
    padding: 8px 16px;
    line-height: 1.35;
}

.payroll-panels :deep(.el-collapse-item__content) {
    padding: 16px;
}

.payroll-panels :deep(.el-collapse-item__header .el-collapse-item__arrow) {
    margin-left: 8px;
}
</style>
