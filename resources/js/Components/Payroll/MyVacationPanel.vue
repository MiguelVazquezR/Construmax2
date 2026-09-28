<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessageBox } from 'element-plus';
import { Calendar, CircleCheck, Promotion } from '@element-plus/icons-vue';
import VacationRequestDialog from '@/Components/Payroll/VacationRequestDialog.vue';

const props = defineProps({
    balance: { type: Object, default: null },
    periods: { type: Array, default: () => [] },
    movements: { type: Array, default: () => [] },
    requests: { type: Array, default: () => [] },
    minimumDays: { type: Number, default: 0 },
});

const emit = defineEmits(['changed']);

const requestDialog = ref(null);

const formatDays = (value) => {
    const number = Number(value ?? 0);

    if (! Number.isFinite(number)) return '0';

    return String(Math.round(number * 100) / 100);
};

const formatSignedDays = (value) => {
    const number = Number(value ?? 0);
    const absolute = formatDays(Math.abs(number));

    if (number > 0) return `+${absolute}`;
    if (number < 0) return `-${absolute}`;

    return '0';
};

const fmtDate = (value) => {
    if (! value) return '-';

    return new Date(`${value}T00:00:00`).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const movementTagType = (movement) => {
    if (movement.kind === 'request') return 'info';

    return { initial: 'primary', grant: 'success', taken: 'info', adjustment: 'warning' }[movement.type] || 'info';
};

const requestStatusTagType = (status) => ({
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
    cancelled: 'info',
}[status] || 'info');

const availabilityCaption = computed(() => {
    if (Number(props.balance?.available_days ?? 0) <= 0) {
        return 'Sin días disponibles';
    }

    const expiring = (props.balance?.seasons ?? [])
        .filter((season) => Number(season.available) > 0)
        .map((season) => season.expiry_date)
        .sort();

    return expiring.length
        ? `Los días más antiguos vencen el ${fmtDate(expiring[0])}`
        : 'Saldo acumulado a la fecha';
});

const openRequestDialog = () => requestDialog.value?.open();

const onRequestSaved = () => emit('changed');

const cancelRequest = (request) => {
    ElMessageBox.confirm('La solicitud pendiente será cancelada.', 'Cancelar solicitud', {
        confirmButtonText: 'Cancelar solicitud',
        cancelButtonText: 'Conservar',
        type: 'warning',
    })
        .then(() => {
            router.delete(route('payroll.vacations.requests.cancel', request.id), {
                preserveScroll: true,
                onSuccess: () => emit('changed'),
            });
        })
        .catch(() => {});
};
</script>

<template>
    <div class="my-vacation-panel p-6 space-y-6">
        <!-- Header with the self-service action -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="font-semibold text-gray-800 dark:text-gray-200">Mis vacaciones</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Saldo por antigüedad, movimientos y solicitudes.
                    <template v-if="minimumDays > 0">Necesitas al menos {{ formatDays(minimumDays) }} día(s) disponibles para solicitar.</template>
                </p>
            </div>
            <el-button type="primary" color="#f26c17" :icon="Calendar" @click="openRequestDialog">
                Solicitar vacaciones
            </el-button>
        </div>

        <!-- Balance at a glance -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529]/60 px-5 py-4">
                <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Disponibles</p>
                <p class="text-2xl font-bold text-emerald-600">{{ formatDays(balance?.available_days) }}</p>
                <p class="text-[11px] text-gray-400 dark:text-gray-500">{{ availabilityCaption }}</p>
            </div>
            <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529]/60 px-5 py-4">
                <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Acumulados</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ formatDays(balance?.accrued_days) }}</p>
                <p class="text-[11px] text-gray-400 dark:text-gray-500">Devengados a la fecha</p>
            </div>
            <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529]/60 px-5 py-4">
                <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Tomados</p>
                <p class="text-2xl font-bold text-blue-600">{{ formatDays(balance?.taken_days) }}</p>
                <p v-if="Number(balance?.manual_taken_days) > 0" class="text-[11px] text-gray-400 dark:text-gray-500">
                    incluye {{ formatDays(balance.manual_taken_days) }} registrados manualmente
                </p>
                <p v-else class="text-[11px] text-gray-400 dark:text-gray-500">Histórico</p>
            </div>
            <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529]/60 px-5 py-4">
                <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">En solicitud</p>
                <p class="text-2xl font-bold text-amber-500">{{ formatDays(balance?.pending_days) }}</p>
                <p class="text-[11px] text-gray-400 dark:text-gray-500">Pendientes de aprobación</p>
            </div>
        </div>

        <!-- Periods and vacation premiums by service year (read only) -->
        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-gray-50 dark:bg-[#252529]/50 border-b border-gray-100 dark:border-[#2b2b2e]">
                <div>
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Periodos y primas vacacionales</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Estado de tus vacaciones por año de servicio.</p>
                </div>
            </div>

            <el-table v-if="periods.length" :data="periods" style="width: 100%" stripe>
                <el-table-column label="Año" min-width="190">
                    <template #default="scope">
                        <p class="font-semibold text-gray-800 dark:text-gray-100">Año {{ scope.row.year_number }}</p>
                        <p class="text-xs text-gray-400">{{ fmtDate(scope.row.start_date) }} — {{ fmtDate(scope.row.end_date) }}</p>
                    </template>
                </el-table-column>

                <el-table-column label="Días" min-width="170">
                    <template #default="scope">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Otorgados: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ formatDays(scope.row.entitled_days) }}</span></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Devengados: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ formatDays(scope.row.accrued_days) }}</span></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Tomados: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ formatDays(scope.row.taken_days) }}</span></p>
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
                            <p class="text-xs text-gray-400">{{ fmtDate(scope.row.premium_paid_at) }}</p>
                        </div>
                        <span v-else class="text-sm text-gray-400">Pendiente</span>
                    </template>
                </el-table-column>
            </el-table>

            <p v-else class="text-xs text-gray-400 dark:text-gray-500 italic px-4 py-4">
                Aún no tienes periodos registrados.
            </p>
        </div>

        <!-- Movements ledger with the running balance (read only) -->
        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-gray-50 dark:bg-[#252529]/50 border-b border-gray-100 dark:border-[#2b2b2e]">
                <div>
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Historial de movimientos</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Bitácora detallada de transacciones.</p>
                </div>
            </div>

            <el-table
                v-if="movements.length"
                :data="movements"
                size="small"
                stripe
                max-height="320"
                style="width: 100%"
            >
                <el-table-column label="Fecha" min-width="130">
                    <template #default="scope">
                        <span class="text-xs text-gray-500">{{ fmtDate(scope.row.date) }}</span>
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
                        <span class="font-semibold text-gray-700 dark:text-gray-200">{{ formatDays(scope.row.balance_after) }}</span>
                    </template>
                </el-table-column>
            </el-table>

            <p v-else class="text-xs text-gray-400 dark:text-gray-500 italic px-4 py-4">
                Sin movimientos registrados.
            </p>

            <!-- Running balance summary -->
            <div class="grid grid-cols-1 sm:grid-cols-2 divide-y divide-gray-100 sm:divide-y-0 sm:divide-x dark:divide-[#2b2b2e] border-t border-gray-100 dark:border-[#2b2b2e]">
                <div class="flex items-center gap-3 px-5 py-4">
                    <el-icon :size="20" class="text-blue-500"><Promotion /></el-icon>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Días disponibles (global)</p>
                        <p class="text-xl font-bold text-blue-600">{{ formatDays(balance?.available_days) }}</p>
                        <p class="text-[11px] text-gray-400 dark:text-gray-500">{{ availabilityCaption }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-5 py-4">
                    <el-icon :size="20" class="text-emerald-500"><Calendar /></el-icon>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Días tomados (histórico)</p>
                        <p class="text-xl font-bold text-emerald-600">{{ formatDays(balance?.taken_days) }}</p>
                        <p v-if="Number(balance?.manual_taken_days) > 0" class="text-[11px] text-gray-400 dark:text-gray-500">
                            incluye {{ formatDays(balance.manual_taken_days) }} registrados manualmente
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Own requests -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <div>
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Mis solicitudes</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Solicitudes de vacaciones que has registrado.</p>
                </div>
            </div>

            <div v-if="requests.length" class="grid grid-cols-1 xl:grid-cols-2 gap-2">
                <div
                    v-for="request in requests"
                    :key="request.id"
                    class="flex items-start justify-between gap-3 rounded-xl border border-gray-100 dark:border-[#2b2b2e] px-3.5 py-3"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="w-8 h-8 rounded-full flex items-center justify-center shrink-0"
                            :class="{
                                'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600': request.status === 'approved',
                                'bg-amber-50 dark:bg-amber-500/10 text-amber-500': request.status === 'pending',
                                'bg-red-50 dark:bg-red-500/10 text-red-500': request.status === 'rejected',
                                'bg-gray-100 dark:bg-[#252529] text-gray-400': ! ['approved', 'pending', 'rejected'].includes(request.status),
                            }"
                        >
                            <el-icon :size="15"><Calendar /></el-icon>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
                                {{ fmtDate(request.start_date) }} — {{ fmtDate(request.end_date) }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ formatDays(request.days) }} día(s)
                                <template v-if="request.reason"> · {{ request.reason }}</template>
                            </p>
                            <p v-if="request.reviewer_name" class="text-[11px] text-gray-400 mt-0.5">
                                Revisó {{ request.reviewer_name }}<template v-if="request.review_notes"> · {{ request.review_notes }}</template>
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1 shrink-0">
                        <el-tag :type="requestStatusTagType(request.status)" size="small" effect="light">
                            {{ request.status_label }}
                        </el-tag>
                        <el-button
                            v-if="request.is_pending"
                            size="small"
                            text
                            type="danger"
                            @click="cancelRequest(request)"
                        >
                            Cancelar
                        </el-button>
                    </div>
                </div>
            </div>

            <p v-else class="text-xs text-gray-400 dark:text-gray-500 italic py-4">
                Sin solicitudes de vacaciones registradas.
            </p>
        </div>

        <VacationRequestDialog
            ref="requestDialog"
            :available-days="Number(balance?.available_days ?? 0)"
            :pending-days="Number(balance?.pending_days ?? 0)"
            :minimum-days="minimumDays"
            :current-season="Number(balance?.current_season ?? 0)"
            @saved="onRequestSaved"
        />
    </div>
</template>

<style scoped>
/* Tables inside the panel blend with the card background, same look as the
   vacations module and the collaborator profile. */
.my-vacation-panel :deep(.el-table) {
    --el-table-bg-color: transparent;
    --el-table-tr-bg-color: transparent;
    --el-table-row-hover-bg-color: rgba(242, 108, 23, 0.06);
}

.my-vacation-panel :deep(.el-table th.el-table__cell) {
    background-color: transparent;
    font-size: 12px;
    color: var(--el-text-color-secondary);
}
</style>
