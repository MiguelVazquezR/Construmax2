<script setup>
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VacationAdjustmentDialog from '@/Components/Payroll/VacationAdjustmentDialog.vue';
import VacationMovementMenu from '@/Components/Payroll/VacationMovementMenu.vue';
import VacationPeriodDialog from '@/Components/Payroll/VacationPeriodDialog.vue';
import { ElMessageBox } from 'element-plus';
import { Plus, CircleCheck, Close, Calendar, Delete, Edit, Promotion } from '@element-plus/icons-vue';
import { useFlashMessages } from '@/Composables/useFlashMessages';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    requests: Object,
    users: Array,
    statuses: Object,
    balance: Object,
    periods: Array,
    movements: Array,
    selectedUser: Object,
    selectedUserId: Number,
    filters: Object,
});

useFlashMessages();

const { can } = usePermissions();
const canApprove = computed(() => can('payroll.vacations.approve'));
const canManage = computed(() => can('payroll.vacations.manage'));

const activeTab = ref('requests');

const toUserId = (value) => {
    const parsed = Number(value);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
};

const filters = reactive({
    status: props.filters?.status || '',
    // The query string returns the id as text; converting it keeps the option
    // selected (and its name visible) after a page reload.
    user_id: toUserId(props.filters?.user_id ?? props.selectedUserId),
});

const clean = (source) =>
    Object.fromEntries(Object.entries(source).filter(([, value]) => value !== null && value !== '' && value !== undefined));

const statusOptions = computed(() =>
    Object.entries(props.statuses || {}).map(([value, label]) => ({ value, label }))
);

// Keep the selected collaborator in the filter even when they are not part of
// the default list (users without attendance or payroll profile).
const userOptions = computed(() => {
    const list = [...(props.users || [])];

    if (props.selectedUser && !list.some((user) => user.id === props.selectedUser.id)) {
        list.push(props.selectedUser);
    }

    return list;
});

const applyFilters = () => {
    router.get(route('payroll.vacations.index'), clean(filters), { preserveState: true, replace: true });
};

const changePage = (page) => {
    router.get(route('payroll.vacations.index'), { ...clean(filters), page }, { preserveState: true, replace: true });
};

const selectBalanceUser = () => {
    applyFilters();
};

// --- Manual movements of the balance ---

const adjustmentDialog = ref(null);

// The collaborator is resolved by the backend: the balance can be reviewed
// for any user, even when they are not part of the attendance kiosk list.
const selectedUser = computed(() => props.selectedUser || null);

const openAdjustmentDialog = (type) => {
    adjustmentDialog.value?.open(type);
};

const onAdjustmentSaved = () => {
    router.reload({ only: ['balance', 'movements', 'periods'] });
};

const removeAdjustment = (adjustment) => {
    ElMessageBox.confirm(
        `¿Eliminar el movimiento «${adjustment.type_label}» de ${formatSignedDays(adjustment.days)} día(s)? El saldo se recalculará.`,
        'Eliminar movimiento de saldo',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        router.delete(route('payroll.vacations.adjustments.destroy', adjustment.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: onAdjustmentSaved,
        });
    }).catch(() => {});
};

// --- Vacation periods by service year ---

const periodDialog = ref(null);

const openPeriodDialog = (period = null) => {
    periodDialog.value?.open(period);
};

const onPeriodSaved = () => {
    router.reload({ only: ['periods'] });
};

const removePeriod = (period) => {
    ElMessageBox.confirm(
        `¿Eliminar el periodo «Año ${period.year_number}» (${formatDate(period.start_date)} — ${formatDate(period.end_date)})? El periodo se elimina de la lista y no se vuelve a generar automáticamente.`,
        'Eliminar periodo vacacional',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        router.delete(route('payroll.vacations.periods.destroy', period.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: onPeriodSaved,
        });
    }).catch(() => {});
};

// --- Review ---

const reviewForm = useForm({ notes: '' });

const approve = (request) => {
    ElMessageBox.prompt('Notas de aprobación (opcional)', 'Aprobar solicitud', {
        confirmButtonText: 'Aprobar',
        cancelButtonText: 'Cancelar',
        inputType: 'textarea',
        inputValue: '',
    }).then(({ value }) => {
        reviewForm.notes = value || '';
        reviewForm.post(route('payroll.vacations.approve', request.id));
    }).catch(() => {});
};

const reject = (request) => {
    ElMessageBox.prompt('Motivo del rechazo (opcional)', 'Rechazar solicitud', {
        confirmButtonText: 'Rechazar',
        cancelButtonText: 'Cancelar',
        inputType: 'textarea',
        inputValue: '',
        confirmButtonClass: 'el-button--danger',
    }).then(({ value }) => {
        reviewForm.notes = value || '';
        reviewForm.post(route('payroll.vacations.reject', request.id));
    }).catch(() => {});
};

const cancel = (request) => {
    ElMessageBox.confirm(
        `¿Cancelar la solicitud de vacaciones de ${request.user?.name}?`,
        'Cancelar solicitud',
        { confirmButtonText: 'Cancelar solicitud', cancelButtonText: 'Volver', type: 'warning' }
    ).then(() => {
        reviewForm.delete(route('payroll.vacations.requests.cancel', request.id));
    }).catch(() => {});
};

// --- Request on behalf ---

const requestDialog = ref(false);
const requestForm = useForm({
    user_id: null,
    start_date: null,
    end_date: null,
    reason: '',
});

const openRequestDialog = () => {
    requestForm.reset();
    requestForm.clearErrors();
    requestForm.user_id = props.selectedUserId || null;
    requestDialog.value = true;
};

const saveRequest = () => {
    requestForm.post(route('payroll.vacations.requests.store'), {
        onSuccess: () => {
            requestDialog.value = false;
        },
    });
};

// --- Formatting ---

const parseDate = (value) => {
    const raw = String(value).substring(0, 10);
    const [year, month, day] = raw.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const formatDate = (value) => {
    if (!value) return '—';
    return parseDate(value).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
};

const statusTagType = (status) => ({
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
    cancelled: 'info',
}[status] || 'info');

const movementTagType = (movement) => {
    if (movement.kind === 'request') return 'info';
    return { initial: 'primary', grant: 'success', taken: 'info', adjustment: 'warning' }[movement.type] || 'info';
};

const formatSignedDays = (value) => {
    const number = Number(value ?? 0);
    const absolute = Math.round(Math.abs(number) * 100) / 100;

    if (number > 0) return `+${absolute}`;
    if (number < 0) return `-${absolute}`;

    return '0';
};

const initialsOf = (name) => {
    const parts = String(name || '').trim().split(/\s+/).slice(0, 2);
    return parts.map((part) => part.charAt(0).toUpperCase()).join('') || '?';
};
</script>

<template>
    <AppLayout title="Vacaciones">
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">Vacaciones</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Solicitudes, aprobación y saldos por antigüedad según la Ley Federal del Trabajo.
                    </p>
                </div>
                <el-button type="primary" color="#f26c17" :icon="Plus" @click="openRequestDialog">
                    Registrar solicitud
                </el-button>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-tabs v-model="activeTab" class="px-2 pt-2">
                    <!-- Requests -->
                    <el-tab-pane name="requests">
                        <template #label>
                            <span class="px-2">Solicitudes</span>
                        </template>

                        <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                            <el-select v-model="filters.user_id" filterable clearable placeholder="Colaborador" @change="applyFilters">
                                <el-option v-for="user in userOptions" :key="user.id" :label="user.name" :value="user.id" />
                            </el-select>

                            <el-select v-model="filters.status" clearable placeholder="Estatus" @change="applyFilters">
                                <el-option v-for="option in statusOptions" :key="option.value" :label="option.label" :value="option.value" />
                            </el-select>

                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Las solicitudes pendientes aparecen primero para aprobarlas o rechazarlas.
                            </p>
                        </div>

                        <el-table
                            :data="requests.data"
                            style="width: 100%"
                            :row-class-name="({ row }) => (row.status === 'pending' ? 'vacation-pending-row' : '')"
                        >
                            <el-table-column label="Colaborador" min-width="220">
                                <template #default="scope">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-[#fdf0e7] dark:bg-[#3a2a1d] text-[#f26c17] flex items-center justify-center shrink-0 text-xs font-bold">
                                            {{ initialsOf(scope.row.user?.name) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800 dark:text-gray-100 leading-tight">{{ scope.row.user?.name }}</p>
                                            <p class="text-xs text-gray-400">
                                                {{ scope.row.requested_by && scope.row.requested_by.id !== scope.row.user?.id
                                                    ? `Registró ${scope.row.requested_by.name}`
                                                    : 'Solicitud del colaborador' }}
                                            </p>
                                        </div>
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column label="Periodo solicitado" min-width="220">
                                <template #default="scope">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        {{ formatDate(scope.row.start_date) }} — {{ formatDate(scope.row.end_date) }}
                                    </p>
                                    <p class="text-xs text-gray-400">{{ Number(scope.row.days) }} día(s) hábil(es)</p>
                                </template>
                            </el-table-column>

                            <el-table-column label="Días" width="90" align="center">
                                <template #default="scope">
                                    <span class="inline-flex min-w-[44px] items-center justify-center rounded-full bg-[#fdf0e7] dark:bg-[#3a2a1d] px-2.5 py-1 text-sm font-bold text-[#f26c17]">
                                        {{ Number(scope.row.days) }}
                                    </span>
                                </template>
                            </el-table-column>

                            <el-table-column label="Estatus" width="130" align="center">
                                <template #default="scope">
                                    <el-tag :type="statusTagType(scope.row.status)" size="small" effect="light">
                                        {{ statuses[scope.row.status] }}
                                    </el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column label="Detalles" min-width="220">
                                <template #default="scope">
                                    <p v-if="scope.row.reason" class="text-xs text-gray-500 dark:text-gray-400">{{ scope.row.reason }}</p>
                                    <p v-if="scope.row.reviewer?.name" class="text-xs text-gray-400 mt-0.5">
                                        Revisó {{ scope.row.reviewer.name }}<template v-if="scope.row.review_notes"> · {{ scope.row.review_notes }}</template>
                                    </p>
                                    <p v-if="! scope.row.reason && ! scope.row.reviewer?.name" class="text-xs text-gray-400 italic">Sin detalles</p>
                                </template>
                            </el-table-column>

                            <el-table-column label="" width="230" align="right">
                                <template #default="scope">
                                    <template v-if="scope.row.status === 'pending' && canApprove">
                                        <el-button :icon="CircleCheck" size="small" type="success" @click="approve(scope.row)">
                                            Aprobar
                                        </el-button>
                                        <el-button :icon="Close" size="small" type="danger" plain class="!ml-2" @click="reject(scope.row)">
                                            Rechazar
                                        </el-button>
                                    </template>
                                    <el-button
                                        v-else-if="scope.row.status === 'pending'"
                                        size="small"
                                        plain
                                        @click="cancel(scope.row)"
                                    >
                                        Cancelar
                                    </el-button>
                                </template>
                            </el-table-column>
                        </el-table>

                        <div class="flex justify-end p-4">
                            <el-pagination
                                layout="prev, pager, next"
                                :total="requests.total"
                                :page-size="requests.per_page"
                                :current-page="requests.current_page"
                                @current-change="changePage"
                            />
                        </div>
                    </el-tab-pane>

                    <!-- Balances -->
                    <el-tab-pane name="balances">
                        <template #label>
                            <span class="px-2">Saldos por temporada</span>
                        </template>

                        <div class="p-4 space-y-6">
                            <el-select
                                v-model="filters.user_id"
                                filterable
                                clearable
                                placeholder="Selecciona un colaborador"
                                class="max-w-md"
                                @change="selectBalanceUser"
                            >
                                <el-option v-for="user in userOptions" :key="user.id" :label="user.name" :value="user.id" />
                            </el-select>

                            <template v-if="balance">
                                <el-alert
                                    v-if="! balance.hire_date"
                                    type="warning"
                                    :closable="false"
                                    show-icon
                                    title="Sin fecha de ingreso"
                                    description="Captura la fecha de ingreso del colaborador en Usuarios → Información general (sección Nómina y asistencia) para calcular el saldo por antigüedad. Mientras tanto solo cuentan los movimientos manuales."
                                />

                                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
                                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Disponibles</p>
                                        <p class="text-2xl font-bold text-emerald-600">{{ balance.available_days }}</p>
                                    </div>
                                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Acumulados</p>
                                        <p class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ balance.accrued_days }}</p>
                                    </div>
                                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Tomados</p>
                                        <p class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ balance.taken_days }}</p>
                                    </div>
                                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Pendientes</p>
                                        <p class="text-2xl font-bold text-amber-500">{{ balance.pending_days }}</p>
                                    </div>
                                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Ajustes manuales</p>
                                        <p
                                            class="text-2xl font-bold"
                                            :class="Number(balance.adjustment_days) < 0 ? 'text-red-500' : 'text-blue-600 dark:text-blue-400'"
                                        >
                                            {{ formatSignedDays(balance.adjustment_days) }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Ledger of manual movements and taken days -->
                                <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-gray-50 dark:bg-[#252529]/50 border-b border-gray-100 dark:border-[#2b2b2e]">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Historial de movimientos</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Bitácora detallada de transacciones.</p>
                                        </div>
                                        <VacationMovementMenu v-if="canManage" @select="openAdjustmentDialog" />
                                    </div>

                                    <el-table v-if="movements.length" :data="movements" style="width: 100%" stripe size="small">
                                        <el-table-column label="Fecha" min-width="130">
                                            <template #default="scope">
                                                <span class="text-xs text-gray-500">{{ formatDate(scope.row.date) }}</span>
                                            </template>
                                        </el-table-column>

                                        <el-table-column label="Tipo" min-width="150">
                                            <template #default="scope">
                                                <el-tag :type="movementTagType(scope.row)" size="small" effect="plain">
                                                    {{ scope.row.type_label }}
                                                </el-tag>
                                            </template>
                                        </el-table-column>

                                        <el-table-column label="Días" width="100" align="center">
                                            <template #default="scope">
                                                <span
                                                    class="font-semibold"
                                                    :class="Number(scope.row.days) < 0 ? 'text-red-500' : 'text-emerald-600'"
                                                >
                                                    {{ formatSignedDays(scope.row.days) }}
                                                </span>
                                            </template>
                                        </el-table-column>

                                        <el-table-column label="Saldo global" width="120" align="center">
                                            <template #default="scope">
                                                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ scope.row.balance_after }}</span>
                                            </template>
                                        </el-table-column>

                                        <el-table-column v-if="canManage" label="" width="70" align="right">
                                            <template #default="scope">
                                                <el-button
                                                    v-if="scope.row.deletable"
                                                    :icon="Delete"
                                                    size="small"
                                                    text
                                                    type="danger"
                                                    title="Eliminar movimiento"
                                                    @click="removeAdjustment(scope.row)"
                                                />
                                            </template>
                                        </el-table-column>
                                    </el-table>

                                    <p v-else class="text-xs text-gray-400 dark:text-gray-500 italic px-4 py-4">
                                        Sin movimientos registrados para este colaborador.
                                    </p>

                                    <!-- Running balance summary -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 divide-y divide-gray-100 sm:divide-y-0 sm:divide-x dark:divide-[#2b2b2e] border-t border-gray-100 dark:border-[#2b2b2e]">
                                        <div class="flex items-center gap-3 px-5 py-4">
                                            <el-icon :size="20" class="text-blue-500"><Promotion /></el-icon>
                                            <div>
                                                <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Días disponibles (global)</p>
                                                <p class="text-xl font-bold text-blue-600">{{ balance.available_days }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 px-5 py-4">
                                            <el-icon :size="20" class="text-emerald-500"><Calendar /></el-icon>
                                            <div>
                                                <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Días tomados (histórico)</p>
                                                <p class="text-xl font-bold text-emerald-600">{{ balance.taken_days }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Periods and vacation premiums by service year -->
                                <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-gray-50 dark:bg-[#252529]/50 border-b border-gray-100 dark:border-[#2b2b2e]">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Periodos y primas vacacionales</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Estado de vacaciones por año de servicio.</p>
                                        </div>
                                        <el-button
                                            v-if="canManage"
                                            :icon="Plus"
                                            circle
                                            title="Agregar periodo"
                                            @click="openPeriodDialog()"
                                        />
                                    </div>

                                    <el-table v-if="periods.length" :data="periods" style="width: 100%" stripe>
                                        <el-table-column label="Año" min-width="190">
                                            <template #default="scope">
                                                <p class="font-semibold text-gray-800 dark:text-gray-100">Año {{ scope.row.year_number }}</p>
                                                <p class="text-xs text-gray-400">{{ formatDate(scope.row.start_date) }} — {{ formatDate(scope.row.end_date) }}</p>
                                            </template>
                                        </el-table-column>

                                        <el-table-column label="Días" min-width="170">
                                            <template #default="scope">
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Otorgados: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ scope.row.entitled_days }}</span></p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Devengados: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ scope.row.accrued_days }}</span></p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Tomados: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ scope.row.taken_days }}</span></p>
                                            </template>
                                        </el-table-column>

                                        <el-table-column label="Estado" width="120" align="center">
                                            <template #default="scope">
                                                <el-tag :type="scope.row.is_completed ? 'primary' : 'success'" size="small" effect="light">
                                                    {{ scope.row.is_completed ? 'Completado' : 'En curso' }}
                                                </el-tag>
                                            </template>
                                        </el-table-column>

                                        <el-table-column label="Prima vacacional" min-width="150">
                                            <template #default="scope">
                                                <div v-if="scope.row.premium_paid_at">
                                                    <p class="flex items-center gap-1 text-sm font-semibold text-emerald-600">
                                                        <el-icon><CircleCheck /></el-icon> Pagada
                                                    </p>
                                                    <p class="text-xs text-gray-400">{{ formatDate(scope.row.premium_paid_at) }}</p>
                                                </div>
                                                <span v-else class="text-sm text-gray-400">Pendiente</span>
                                            </template>
                                        </el-table-column>

                                        <el-table-column v-if="canManage" label="" width="110" align="right">
                                            <template #default="scope">
                                                <el-button :icon="Edit" size="small" text title="Editar periodo" @click="openPeriodDialog(scope.row)" />
                                                <el-button :icon="Delete" size="small" text type="danger" title="Eliminar periodo" @click="removePeriod(scope.row)" />
                                            </template>
                                        </el-table-column>
                                    </el-table>

                                    <el-empty
                                        v-else
                                        :image-size="90"
                                        description="Sin periodos registrados para este colaborador."
                                    />
                                </div>
                            </template>

                            <el-empty
                                v-else
                                :image-size="100"
                                description="Selecciona un colaborador para ver su saldo, o captura su fecha de ingreso si aún no la tiene."
                            >
                                <template #default>
                                    <p class="text-xs text-gray-500 flex items-center justify-center gap-1">
                                        <el-icon><Calendar /></el-icon> La fecha de ingreso se captura en Usuarios → Información general (sección Nómina y asistencia).
                                    </p>
                                </template>
                            </el-empty>
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </div>

        <!-- Request dialog -->
        <el-dialog v-model="requestDialog" title="Registrar solicitud de vacaciones" width="520px" top="10vh">
            <el-form :model="requestForm" label-position="top" size="default">
                <el-form-item label="Colaborador" required :error="requestForm.errors.user_id">
                    <el-select v-model="requestForm.user_id" filterable placeholder="Seleccionar" class="w-full">
                        <el-option v-for="user in users" :key="user.id" :label="user.name" :value="user.id" />
                    </el-select>
                </el-form-item>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Fecha de inicio" required :error="requestForm.errors.start_date">
                        <el-date-picker v-model="requestForm.start_date" type="date" value-format="YYYY-MM-DD" placeholder="Seleccionar" class="w-full" />
                    </el-form-item>

                    <el-form-item label="Fecha final" required :error="requestForm.errors.end_date">
                        <el-date-picker v-model="requestForm.end_date" type="date" value-format="YYYY-MM-DD" placeholder="Seleccionar" class="w-full" />
                    </el-form-item>
                </div>

                <el-form-item label="Motivo" :error="requestForm.errors.reason">
                    <el-input v-model="requestForm.reason" maxlength="255" placeholder="Comentarios opcionales" />
                </el-form-item>

                <p v-if="requestForm.errors.balance" class="text-sm text-red-500">{{ requestForm.errors.balance }}</p>
            </el-form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="requestDialog = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="requestForm.processing" @click="saveRequest">
                        Enviar solicitud
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <VacationAdjustmentDialog
            v-if="selectedUser"
            ref="adjustmentDialog"
            :user-id="selectedUser.id"
            :user-name="selectedUser.name"
            :available-days="Number(balance?.available_days || 0)"
            @saved="onAdjustmentSaved"
        />

        <VacationPeriodDialog
            v-if="selectedUser"
            ref="periodDialog"
            :user-id="selectedUser.id"
            :user-name="selectedUser.name"
            @saved="onPeriodSaved"
        />
    </AppLayout>
</template>

<style scoped>
/* Pending requests stand out so they are easy to spot for approval. */
:deep(.vacation-pending-row > td.el-table__cell) {
    background-color: rgba(242, 108, 23, 0.06);
}

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
</style>
