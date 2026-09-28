<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FaceEnrollmentDialog from '@/Components/Payroll/FaceEnrollmentDialog.vue';
import ShiftSummaryPopover from '@/Components/Payroll/ShiftSummaryPopover.vue';
import VacationAdjustmentDialog from '@/Components/Payroll/VacationAdjustmentDialog.vue';
import VacationMovementMenu from '@/Components/Payroll/VacationMovementMenu.vue';
import VacationPeriodDialog from '@/Components/Payroll/VacationPeriodDialog.vue';
import UserStatusDialog from '@/Components/Users/UserStatusDialog.vue';
import { ElMessageBox } from 'element-plus';
import { useFlashMessages } from '@/Composables/useFlashMessages';
import { usePermissions } from '@/Composables/usePermissions';
import { Back, Calendar, Camera, CircleCheck, Delete, Edit, Plus, Promotion, Suitcase, Sunny, SwitchButton, Ticket, User } from '@element-plus/icons-vue';

const { can } = usePermissions();

useFlashMessages();

const props = defineProps({
    user: Object,
    faceEnrollment: Object,
    vacation: Object,
    currentShift: Object,
});

const activeTab = ref('general');
const faceDialogVisible = ref(false);
const statusDialog = ref(null);
const adjustmentDialog = ref(null);
const periodDialog = ref(null);

const onFaceSaved = () => {
    router.reload({ only: ['user', 'faceEnrollment'] });
};

const onVacationSaved = () => {
    router.reload({ only: ['vacation'] });
};

const openAdjustmentDialog = (type) => {
    adjustmentDialog.value?.open(type);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';

    // Dates without time (YYYY-MM-DD) are parsed as local dates: creating them
    // with `new Date('2026-01-01')` would use UTC midnight and shift the day.
    const raw = String(dateString);
    const date = /^\d{4}-\d{2}-\d{2}$/.test(raw)
        ? new Date(Number(raw.substring(0, 4)), Number(raw.substring(5, 7)) - 1, Number(raw.substring(8, 10)))
        : new Date(raw);

    const day = date.toLocaleDateString('es-ES', { day: '2-digit' });
    const month = date.toLocaleDateString('es-ES', { month: 'short' });
    const year = date.getFullYear();
    return `${day} ${month}, ${year}`;
};

const getStatusColor = (status) => {
    const map = {
        'Borrador': 'info',
        'Levantamiento': 'warning',
        'Catálogo': 'primary',
        'Proceso de ejecución': 'warning',
        'Ejecutado': 'success',
        'Facturado': 'primary',
        'Pagado': 'success',
        'Cancelado': 'danger'
    };
    return map[status] || 'info';
};

const getPriorityColor = (priority) => {
    const map = {
        'Baja': 'info',
        'Media': 'warning',
        'Alta': 'danger',
        'Urgente': 'danger'
    };
    return map[priority] || 'info';
};

const navigateToTicket = (row) => {
    router.visit(route('tickets.show', row.id));
};

// --- Nómina y asistencia (sección de Información general) ---

const SHIFT_TYPE_LABELS = {
    fixed: 'Fijo',
    flexible: 'Flexible',
    per_day: 'Por día',
};

const shiftTagType = (type) => (type === 'per_day' ? 'success' : (type === 'fixed' ? 'primary' : 'warning'));

const money = (value) =>
    `$${Number(value || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

// --- Vacaciones (pestaña Información general) ---

const vacationBalance = computed(() => props.vacation?.balance ?? null);
const vacationRequests = computed(() => props.vacation?.requests ?? []);
const vacationMovements = computed(() => props.vacation?.movements ?? []);
const vacationPeriods = computed(() => props.vacation?.periods ?? []);
const canManageVacations = computed(() => props.vacation?.can_manage === true);

const showVacationCard = computed(() => Boolean(props.user.payroll_profile) || vacationMovements.value.length > 0);

const vacationSubtitle = computed(() => {
    const hireDate = props.user.payroll_profile?.hire_date;

    if (! hireDate) {
        return 'Sin fecha de ingreso: el saldo por antigüedad no se puede calcular.';
    }

    const season = vacationBalance.value?.current_season ?? 0;

    return `Ingreso: ${formatDate(hireDate)} · ${season > 0 ? `temporada ${season} en curso` : 'sin temporada activa'}`;
});

const availabilityCaption = computed(() => {
    if (Number(vacationBalance.value?.available_days ?? 0) <= 0) {
        return 'Sin días disponibles';
    }

    const expiring = (vacationBalance.value?.seasons ?? [])
        .filter((season) => Number(season.available) > 0)
        .map((season) => season.expiry_date)
        .sort();

    return expiring.length
        ? `Los días más antiguos vencen el ${formatDate(expiring[0])}`
        : 'Saldo acumulado a la fecha';
});

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

const removeAdjustment = (adjustment) => {
    ElMessageBox.confirm(
        `¿Eliminar el movimiento «${adjustment.type_label}» de ${formatSignedDays(adjustment.days)} día(s)? El saldo se recalculará.`,
        'Eliminar movimiento de saldo',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        router.delete(route('payroll.vacations.adjustments.destroy', adjustment.id), {
            preserveScroll: true,
            onSuccess: onVacationSaved,
        });
    }).catch(() => {});
};

const openPeriodDialog = (period = null) => {
    periodDialog.value?.open(period);
};

const removePeriod = (period) => {
    ElMessageBox.confirm(
        `¿Eliminar el periodo «Año ${period.year_number}» (${formatDate(period.start_date)} — ${formatDate(period.end_date)})? El periodo se elimina de la lista y no se vuelve a generar automáticamente.`,
        'Eliminar periodo vacacional',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        router.delete(route('payroll.vacations.periods.destroy', period.id), {
            preserveScroll: true,
            onSuccess: onVacationSaved,
        });
    }).catch(() => {});
};
</script>

<template>
    <AppLayout :title="`Usuario: ${user.name}`">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('users.index')">
                        <el-button :icon="Back" circle plain />
                    </Link>
                    <div>
                        <h2 class="font-semibold text-base text-gray-800 dark:text-white leading-tight">
                            Perfil del usuario
                        </h2>
                    </div>
                </div>
                <div class="flex gap-2">
                    <el-button
                        v-if="can('users.toggle-status')"
                        :type="user.is_active ? 'warning' : 'success'"
                        plain
                        :icon="SwitchButton"
                        @click="statusDialog && statusDialog.toggle(user)"
                    >
                        {{ user.is_active ? 'Dar de baja' : 'Reactivar' }}
                    </el-button>
                    <Link v-if="can('users.edit')" :href="route('users.edit', user.id)">
                        <el-button type="primary" color="#f26c17" :icon="Edit">
                            Editar usuario
                        </el-button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">

            <!-- HEADER RESUMEN (HERO CARD) -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="flex items-center gap-5">
                    <el-avatar :size="56" :src="user.profile_photo_url" class="border-2 border-gray-200 dark:border-gray-700 shrink-0" />
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ user.name }}</h3>
                            <el-tag :type="user.is_active ? 'success' : 'danger'" size="small" effect="dark" class="rounded-full">
                                {{ user.is_active ? 'Activo' : 'Baja' }}
                            </el-tag>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm">
                            {{ user.email }}
                        </p>
                    </div>
                </div>

                <div class="flex gap-6 md:gap-10 text-left md:text-right border-t md:border-t-0 border-gray-100 dark:border-gray-800 pt-4 md:pt-0 w-full md:w-auto">
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-1">Departamento</p>
                        <p class="text-lg font-bold text-gray-800 dark:text-white">{{ user.employee?.department || 'No asignado' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-1">Puesto</p>
                        <p class="text-lg font-bold text-blue-600 dark:text-blue-400">
                            {{ user.employee?.position || 'No asignado' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO ORGANIZADO EN PESTAÑAS -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-tabs v-model="activeTab" class="px-2 pt-2 custom-tabs">

                    <!-- PESTAÑA 1: INFORMACIÓN GENERAL -->
                    <el-tab-pane name="general">
                        <template #label>
                            <span class="flex items-center gap-2 px-2">
                                <el-icon><User /></el-icon> Información general
                            </span>
                        </template>
                        <div class="p-6 space-y-6">
                            <!-- DATOS GENERALES -->
                            <div>
                                <p class="flex items-center gap-2 text-xs uppercase tracking-wider text-gray-400 font-bold mb-3">
                                    <el-icon><User /></el-icon> Datos generales
                                </p>

                                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529]/60 px-4 py-3">
                                        <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Nombre</p>
                                        <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-100 truncate" :title="user.name">{{ user.name }}</p>
                                    </div>

                                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529]/60 px-4 py-3">
                                        <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Email</p>
                                        <p class="mt-1 text-sm font-medium text-gray-700 dark:text-gray-200 truncate" :title="user.email">{{ user.email }}</p>
                                    </div>

                                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529]/60 px-4 py-3">
                                        <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Departamento</p>
                                        <p class="mt-1 text-sm font-medium text-gray-700 dark:text-gray-200 truncate">{{ user.employee?.department || 'No asignado' }}</p>
                                    </div>

                                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529]/60 px-4 py-3">
                                        <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Puesto</p>
                                        <p class="mt-1 text-sm font-medium text-gray-700 dark:text-gray-200 truncate">{{ user.employee?.position || 'No asignado' }}</p>
                                    </div>

                                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529]/60 px-4 py-3">
                                        <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Teléfono</p>
                                        <p class="mt-1 text-sm font-medium text-gray-700 dark:text-gray-200">{{ user.employee?.phone || 'No asignado' }}</p>
                                    </div>

                                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529]/60 px-4 py-3">
                                        <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Fecha de registro</p>
                                        <p class="mt-1 text-sm font-medium text-gray-700 dark:text-gray-200">{{ formatDate(user.created_at) }}</p>
                                    </div>

                                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529]/60 px-4 py-3">
                                        <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">ID de usuario</p>
                                        <p class="mt-1 text-sm font-medium text-gray-700 dark:text-gray-200 font-mono">{{ user.id }}</p>
                                    </div>

                                    <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529]/60 px-4 py-3">
                                        <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Roles</p>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <el-tag v-for="role in user.roles" :key="role.id" size="small" type="info" effect="plain">
                                                {{ role.name }}
                                            </el-tag>
                                            <span v-if="!user.roles || user.roles.length === 0" class="text-xs text-gray-400 italic">Sin rol</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- NÓMINA Y ASISTENCIA -->
                            <div v-if="can('payroll.profiles.manage') || user.payroll_profile">
                                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                                    <p class="flex items-center gap-2 text-xs uppercase tracking-wider text-gray-400 font-bold">
                                        <el-icon><Suitcase /></el-icon> Nómina y asistencia
                                    </p>
                                    <el-tag v-if="user.payroll_profile?.termination_date" type="danger" size="small" effect="light">
                                        Baja {{ formatDate(user.payroll_profile.termination_date) }}
                                    </el-tag>
                                </div>

                                <template v-if="user.payroll_profile">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529]/60 px-4 py-3">
                                            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Número de empleado</p>
                                            <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-100 font-mono">{{ user.payroll_profile.employee_number || 'Sin asignar' }}</p>
                                        </div>

                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529]/60 px-4 py-3">
                                            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Fecha de ingreso</p>
                                            <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-100">{{ formatDate(user.payroll_profile.hire_date) }}</p>
                                        </div>

                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529]/60 px-4 py-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Horario</p>
                                                <ShiftSummaryPopover v-if="currentShift" :shift="currentShift" />
                                            </div>
                                            <div v-if="currentShift" class="mt-1 flex flex-wrap items-center gap-2">
                                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{{ currentShift.name }}</p>
                                                <el-tag size="small" effect="plain" :type="shiftTagType(currentShift.type)">
                                                    {{ SHIFT_TYPE_LABELS[currentShift.type] || currentShift.type }}
                                                </el-tag>
                                            </div>
                                            <p v-else class="mt-1 text-sm text-gray-400">Sin horario asignado</p>
                                        </div>

                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529]/60 px-4 py-3">
                                            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Sueldo diario</p>
                                            <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-100">{{ user.payroll_profile.daily_salary ? money(user.payroll_profile.daily_salary) : 'No asignado' }}</p>
                                        </div>

                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529]/60 px-4 py-3">
                                            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Horas por día</p>
                                            <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-100">{{ user.payroll_profile.daily_hours ? `${Number(user.payroll_profile.daily_hours)} h` : 'No asignado' }}</p>
                                        </div>

                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529]/60 px-4 py-3">
                                            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">PIN de kiosco</p>
                                            <el-tag class="mt-1" :type="user.payroll_profile.has_kiosk_pin ? 'success' : 'info'" size="small" effect="light">
                                                {{ user.payroll_profile.has_kiosk_pin ? 'Configurado' : 'Sin configurar' }}
                                            </el-tag>
                                        </div>

                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529]/60 px-4 py-3">
                                            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Fecha de baja</p>
                                            <p class="mt-1 text-sm font-semibold" :class="user.payroll_profile.termination_date ? 'text-red-500' : 'text-gray-800 dark:text-gray-100'">
                                                {{ user.payroll_profile.termination_date ? formatDate(user.payroll_profile.termination_date) : 'Sigue activo' }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-3">
                                        <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 dark:border-[#2b2b2e] px-4 py-3">
                                            <div>
                                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Sujeto a nómina</p>
                                                <p class="text-xs text-gray-400">Aparece en los periodos de nómina.</p>
                                            </div>
                                            <el-tag :type="user.payroll_profile.is_payroll_subject ? 'success' : 'info'" size="small" effect="light">
                                                {{ user.payroll_profile.is_payroll_subject ? 'Sí' : 'No' }}
                                            </el-tag>
                                        </div>

                                        <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 dark:border-[#2b2b2e] px-4 py-3">
                                            <div>
                                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Registra asistencia</p>
                                                <p class="text-xs text-gray-400">Usa el kiosco y su portal de asistencia.</p>
                                            </div>
                                            <el-tag :type="user.payroll_profile.is_attendance_subject ? 'success' : 'info'" size="small" effect="light">
                                                {{ user.payroll_profile.is_attendance_subject ? 'Sí' : 'No' }}
                                            </el-tag>
                                        </div>

                                        <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 dark:border-[#2b2b2e] px-4 py-3">
                                            <div>
                                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Asistencia remota</p>
                                                <p class="text-xs text-gray-400">Puede registrar desde su celular.</p>
                                            </div>
                                            <el-tag :type="user.payroll_profile.can_remote_attendance ? 'success' : 'info'" size="small" effect="light">
                                                {{ user.payroll_profile.can_remote_attendance ? 'Habilitada' : 'Deshabilitada' }}
                                            </el-tag>
                                        </div>
                                    </div>

                                    <!-- Reconocimiento facial -->
                                    <div
                                        v-if="faceEnrollment"
                                        class="mt-3 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529]/60 px-4 py-3"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-[#fdf0e7] dark:bg-[#3a2a1d] text-[#f26c17] flex items-center justify-center shrink-0">
                                                <el-icon :size="17"><Camera /></el-icon>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-800 dark:text-gray-100 text-sm">Reconocimiento facial</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    <template v-if="faceEnrollment.activeCount > 0">
                                                        {{ faceEnrollment.activeCount }} {{ faceEnrollment.activeCount === 1 ? 'rostro registrado' : 'rostros registrados' }} para el kiosco y la asistencia.
                                                    </template>
                                                    <template v-else>
                                                        Sin registro facial. El colaborador puede usar su rostro en el kiosco al registrarlo.
                                                    </template>
                                                </p>
                                            </div>
                                        </div>
                                        <el-button
                                            v-if="can('payroll.faces.manage') && user.payroll_profile.is_attendance_subject"
                                            type="primary"
                                            plain
                                            :icon="Camera"
                                            @click="faceDialogVisible = true"
                                        >
                                            {{ faceEnrollment.activeCount > 0 ? 'Actualizar registro facial' : 'Registrar rostro' }}
                                        </el-button>
                                    </div>
                                </template>

                                <div v-else class="text-center py-10 bg-gray-50 dark:bg-[#252529]/50 rounded-xl border border-dashed border-gray-200 dark:border-[#2b2b2e]">
                                    <el-empty description="Sin perfil de nómina" :image-size="90">
                                        <template #default>
                                            <p class="text-sm text-gray-500">Edita el usuario para capturar sus datos de nómina y asistencia.</p>
                                        </template>
                                    </el-empty>
                                </div>
                            </div>

                            <!-- VACACIONES DEL COLABORADOR -->
                            <div
                                v-if="showVacationCard && vacationBalance"
                                class="vacation-card rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 bg-gray-50 dark:bg-[#252529]/50 border-b border-gray-100 dark:border-[#2b2b2e]">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-500 flex items-center justify-center shrink-0">
                                            <el-icon :size="18"><Sunny /></el-icon>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800 dark:text-gray-100 text-sm">Vacaciones</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ vacationSubtitle }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-5 space-y-6">
                                    <el-alert
                                        v-if="! user.payroll_profile?.hire_date"
                                        type="warning"
                                        :closable="false"
                                        show-icon
                                        title="Sin fecha de ingreso"
                                        description="Captura la fecha de ingreso en la sección Nómina y asistencia (Información general) para calcular el saldo por antigüedad. Mientras tanto solo cuentan los movimientos manuales."
                                    />

                                    <div class="space-y-6">
                                        <!-- Periods and vacation premiums by service year -->
                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                                            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-gray-50 dark:bg-[#252529]/50 border-b border-gray-100 dark:border-[#2b2b2e]">
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Periodos y primas vacacionales</p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">Estado de vacaciones por año de servicio.</p>
                                                </div>
                                                <el-button
                                                    v-if="canManageVacations"
                                                    :icon="Plus"
                                                    circle
                                                    title="Agregar periodo"
                                                    @click="openPeriodDialog()"
                                                />
                                            </div>

                                            <el-table v-if="vacationPeriods.length" :data="vacationPeriods" style="width: 100%" stripe>
                                                <el-table-column label="Año" min-width="190">
                                                    <template #default="scope">
                                                        <p class="font-semibold text-gray-800 dark:text-gray-100">Año {{ scope.row.year_number }}</p>
                                                        <p class="text-xs text-gray-400">{{ formatDate(scope.row.start_date) }} — {{ formatDate(scope.row.end_date) }}</p>
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
                                                            <p class="text-xs text-gray-400">{{ formatDate(scope.row.premium_paid_at) }}</p>
                                                        </div>
                                                        <span v-else class="text-sm text-gray-400">Pendiente</span>
                                                    </template>
                                                </el-table-column>

                                                <el-table-column v-if="canManageVacations" label="" width="110" align="right">
                                                    <template #default="scope">
                                                        <el-button :icon="Edit" size="small" text title="Editar periodo" @click="openPeriodDialog(scope.row)" />
                                                        <el-button :icon="Delete" size="small" text type="danger" title="Eliminar periodo" @click="removePeriod(scope.row)" />
                                                    </template>
                                                </el-table-column>
                                            </el-table>

                                            <p v-else class="text-xs text-gray-400 dark:text-gray-500 italic px-4 py-4">
                                                Sin periodos registrados para este colaborador.
                                            </p>
                                        </div>

                                        <!-- Movements ledger with the running balance -->
                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                                            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-gray-50 dark:bg-[#252529]/50 border-b border-gray-100 dark:border-[#2b2b2e]">
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Historial de movimientos</p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">Bitácora detallada de transacciones.</p>
                                                </div>
                                                <VacationMovementMenu v-if="canManageVacations" @select="openAdjustmentDialog" />
                                            </div>

                                            <el-table
                                                v-if="vacationMovements.length"
                                                :data="vacationMovements"
                                                size="small"
                                                stripe
                                                max-height="320"
                                                style="width: 100%"
                                            >
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
                                                        <span class="font-semibold text-gray-700 dark:text-gray-200">{{ formatDays(scope.row.balance_after) }}</span>
                                                    </template>
                                                </el-table-column>

                                                <el-table-column v-if="canManageVacations" label="" width="70" align="right">
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
                                                        <p class="text-xl font-bold text-blue-600">{{ formatDays(vacationBalance.available_days) }}</p>
                                                        <p class="text-[11px] text-gray-400 dark:text-gray-500">{{ availabilityCaption }}</p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-3 px-5 py-4">
                                                    <el-icon :size="20" class="text-emerald-500"><Calendar /></el-icon>
                                                    <div>
                                                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Días tomados (histórico)</p>
                                                        <p class="text-xl font-bold text-emerald-600">{{ formatDays(vacationBalance.taken_days) }}</p>
                                                        <p v-if="Number(vacationBalance.manual_taken_days) > 0" class="text-[11px] text-gray-400 dark:text-gray-500">
                                                            incluye {{ formatDays(vacationBalance.manual_taken_days) }} registrados manualmente
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Recent requests -->
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Solicitudes recientes</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Últimas solicitudes de vacaciones del colaborador.</p>
                                            </div>
                                            <Link
                                                v-if="vacation?.can_view_module"
                                                :href="route('payroll.vacations.index', { user_id: user.id })"
                                                class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline"
                                            >
                                                Ver todas
                                            </Link>
                                        </div>

                                        <div v-if="vacationRequests.length" class="grid grid-cols-1 xl:grid-cols-2 gap-2">
                                            <div
                                                v-for="request in vacationRequests"
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
                                                            {{ formatDate(request.start_date) }} — {{ formatDate(request.end_date) }}
                                                        </p>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                            {{ formatDays(request.days) }} día(s)
                                                            <template v-if="request.reason"> · {{ request.reason }}</template>
                                                        </p>
                                                        <p v-if="request.reviewer_name" class="text-[11px] text-gray-400 mt-0.5">
                                                            Revisó {{ request.reviewer_name }}<template v-if="request.review_notes"> · {{ request.review_notes }}</template>
                                                        </p>
                                                        <p
                                                            v-else-if="request.requested_by_name && request.requested_by_name !== user.name"
                                                            class="text-[11px] text-gray-400 mt-0.5"
                                                        >
                                                            Registró {{ request.requested_by_name }}<template v-if="request.requested_at"> el {{ formatDate(request.requested_at) }}</template>
                                                        </p>
                                                    </div>
                                                </div>
                                                <el-tag :type="requestStatusTagType(request.status)" size="small" effect="light" class="shrink-0">
                                                    {{ request.status_label }}
                                                </el-tag>
                                            </div>
                                        </div>

                                        <p v-else class="text-xs text-gray-400 dark:text-gray-500 italic py-4">
                                            Sin solicitudes de vacaciones registradas.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </el-tab-pane>

                    <!-- PESTAÑA 2: TICKETS A CARGO -->
                    <el-tab-pane name="tickets">
                        <template #label>
                            <span class="flex items-center gap-2 px-2">
                                <el-icon><Ticket /></el-icon> Tickets a cargo
                                <el-tag v-if="user.tickets_as_seller?.length" size="small" type="primary" class="ml-1 rounded-full">{{ user.tickets_as_seller.length }}</el-tag>
                            </span>
                        </template>
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-6">
                                <div>
                                    <h4 class="font-bold text-gray-800 dark:text-gray-200 text-lg">Tickets como vendedor</h4>
                                    <p class="text-sm text-gray-500">Tickets en los que este usuario figura como vendedor responsable.</p>
                                </div>
                            </div>

                            <div v-if="user.tickets_as_seller && user.tickets_as_seller.length > 0" class="border border-gray-200 dark:border-gray-800 rounded-lg overflow-hidden">
                                <el-table
                                    :data="user.tickets_as_seller"
                                    style="width: 100%"
                                    stripe
                                    @row-click="navigateToTicket"
                                    row-class-name="cursor-pointer hover:bg-gray-50 dark:hover:bg-[#27272a] transition-colors"
                                >
                                    <el-table-column label="Folio" width="100">
                                        <template #default="scope">
                                            <span class="font-mono font-bold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                                                {{ scope.row.folio || `#${scope.row.id}` }}
                                            </span>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Proyecto / Servicio" min-width="220">
                                        <template #default="scope">
                                            <div class="font-bold text-gray-800 dark:text-gray-200 truncate">{{ scope.row.name }}</div>
                                            <div class="text-xs text-gray-500 truncate">{{ scope.row.service_type }}</div>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Sucursal" min-width="180">
                                        <template #default="scope">
                                            <div v-if="scope.row.branch" class="text-sm text-gray-700 dark:text-gray-300">
                                                <div class="font-semibold truncate">{{ scope.row.branch.branch_name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                    {{ scope.row.branch.region }}, {{ scope.row.branch.country }}
                                                </div>
                                            </div>
                                            <span v-else class="text-sm text-gray-400 italic">General</span>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Fecha inicio" width="120">
                                        <template #default="scope">
                                            <div class="text-sm font-mono text-gray-600 dark:text-gray-400">
                                                {{ formatDate(scope.row.scheduled_start) }}
                                            </div>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Prioridad" width="100" align="center">
                                        <template #default="scope">
                                            <el-tag :type="getPriorityColor(scope.row.priority)" size="small" effect="plain" class="w-full text-center">
                                                {{ scope.row.priority }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Estatus" width="140" align="center">
                                        <template #default="scope">
                                            <el-tag :type="getStatusColor(scope.row.status)" size="small" effect="dark" class="w-full border-none">
                                                {{ scope.row.status }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </div>

                            <div v-else class="text-center py-12 bg-gray-50 dark:bg-[#252529]/50 rounded-lg border border-dashed border-gray-300 dark:border-gray-700">
                                <el-empty description="No hay tickets asignados" :image-size="100">
                                    <template #default>
                                        <p class="text-sm text-gray-500">Este usuario aún no tiene tickets como vendedor.</p>
                                    </template>
                                </el-empty>
                            </div>
                        </div>
                    </el-tab-pane>

                </el-tabs>
            </div>

        </div>

        <FaceEnrollmentDialog
            v-if="user.payroll_profile && faceEnrollment"
            v-model="faceDialogVisible"
            :user="user"
            :configured="faceEnrollment.configured"
            :active-count="faceEnrollment.activeCount"
            @saved="onFaceSaved"
        />

        <UserStatusDialog ref="statusDialog" />

        <VacationAdjustmentDialog
            v-if="vacation && vacationBalance"
            ref="adjustmentDialog"
            :user-id="user.id"
            :user-name="user.name"
            :available-days="Number(vacationBalance.available_days)"
            @saved="onVacationSaved"
        />

        <VacationPeriodDialog
            v-if="vacation && vacationBalance"
            ref="periodDialog"
            :user-id="user.id"
            :user-name="user.name"
            @saved="onVacationSaved"
        />
    </AppLayout>
</template>

<style scoped>
:deep(.cursor-pointer) {
    cursor: pointer;
}

:deep(.custom-tabs .el-tabs__nav-wrap::after) {
    background-color: var(--el-border-color-light);
}
:global(.dark) :deep(.custom-tabs .el-tabs__nav-wrap::after) {
    background-color: #3f3f46;
}

/* Tables inside the vacations card blend with the card background, matching
   the vacations module (same look as the payroll screen). */
.vacation-card :deep(.el-table) {
    --el-table-bg-color: transparent;
    --el-table-tr-bg-color: transparent;
    --el-table-row-hover-bg-color: rgba(242, 108, 23, 0.06);
}

.vacation-card :deep(.el-table th.el-table__cell) {
    background-color: transparent;
    font-size: 12px;
    color: var(--el-text-color-secondary);
}
</style>