<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FaceEnrollmentDialog from '@/Components/Payroll/FaceEnrollmentDialog.vue';
import ShiftSummaryPopover from '@/Components/Payroll/ShiftSummaryPopover.vue';
import VacationAdjustmentDialog from '@/Components/Payroll/VacationAdjustmentDialog.vue';
import UserStatusDialog from '@/Components/Users/UserStatusDialog.vue';
import { ElMessageBox } from 'element-plus';
import { useFlashMessages } from '@/Composables/useFlashMessages';
import { usePermissions } from '@/Composables/usePermissions';
import { Back, Calendar, Camera, Delete, Edit, Suitcase, Sunny, SwitchButton, Ticket, User } from '@element-plus/icons-vue';

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
const vacationAdjustments = computed(() => props.vacation?.adjustments ?? []);
const canManageVacations = computed(() => props.vacation?.can_manage === true);

const showVacationCard = computed(() => Boolean(props.user.payroll_profile) || vacationAdjustments.value.length > 0);

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

const adjustmentTagType = (type) => ({
    initial: 'primary',
    grant: 'success',
    adjustment: 'warning',
}[type] || 'info');

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
                                class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden"
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

                                    <div v-if="canManageVacations" class="flex flex-wrap gap-2">
                                        <el-button size="small" plain @click="openAdjustmentDialog('initial')">
                                            Saldo inicial
                                        </el-button>
                                        <el-button size="small" plain @click="openAdjustmentDialog('grant')">
                                            Agregar días
                                        </el-button>
                                        <el-button size="small" plain @click="openAdjustmentDialog('adjustment')">
                                            Ajustar días
                                        </el-button>
                                    </div>
                                </div>

                                <div class="p-5 space-y-5">
                                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                                            <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Disponibles</p>
                                            <p class="text-2xl font-bold text-emerald-600">
                                                {{ formatDays(vacationBalance.available_days) }}
                                            </p>
                                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">{{ availabilityCaption }}</p>
                                        </div>

                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                                            <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Tomados</p>
                                            <p class="text-2xl font-bold text-gray-800 dark:text-gray-100">
                                                {{ formatDays(vacationBalance.taken_days) }}
                                            </p>
                                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">
                                                de {{ formatDays(vacationBalance.accrued_days) }} acumulados
                                            </p>
                                        </div>

                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                                            <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Pendientes</p>
                                            <p class="text-2xl font-bold text-amber-500">
                                                {{ formatDays(vacationBalance.pending_days) }}
                                            </p>
                                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">
                                                en solicitudes por aprobar
                                            </p>
                                        </div>

                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                                            <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Ajustes manuales</p>
                                            <p
                                                class="text-2xl font-bold"
                                                :class="Number(vacationBalance.adjustment_days) < 0 ? 'text-red-500' : 'text-blue-600 dark:text-blue-400'"
                                            >
                                                {{ formatSignedDays(vacationBalance.adjustment_days) }}
                                            </p>
                                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">
                                                saldo inicial y días agregados
                                            </p>
                                        </div>
                                    </div>

                                    <el-alert
                                        v-if="! user.payroll_profile?.hire_date"
                                        type="warning"
                                        :closable="false"
                                        show-icon
                                        title="Sin fecha de ingreso"
                                        description="Captura la fecha de ingreso en la sección Nómina y asistencia (Información general) para calcular el saldo por antigüedad. Mientras tanto solo cuentan los movimientos manuales."
                                    />

                                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                                        <div>
                                            <div class="flex items-center justify-between mb-2">
                                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Solicitudes recientes</p>
                                                <Link
                                                    v-if="vacation?.can_view_module"
                                                    :href="route('payroll.vacations.index', { user_id: user.id })"
                                                    class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline"
                                                >
                                                    Ver todas
                                                </Link>
                                            </div>

                                            <div v-if="vacationRequests.length" class="space-y-2">
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

                                        <div>
                                            <div class="flex items-center justify-between mb-2">
                                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                                    Movimientos de saldo
                                                    <span v-if="vacationAdjustments.length" class="text-gray-400 font-normal">
                                                        ({{ vacationAdjustments.length }})
                                                    </span>
                                                </p>
                                            </div>

                                            <el-table
                                                v-if="vacationAdjustments.length"
                                                :data="vacationAdjustments"
                                                size="small"
                                                stripe
                                                style="width: 100%"
                                            >
                                                <el-table-column label="Tipo" min-width="140">
                                                    <template #default="scope">
                                                        <el-tag :type="adjustmentTagType(scope.row.type)" size="small" effect="plain">
                                                            {{ scope.row.type_label }}
                                                        </el-tag>
                                                    </template>
                                                </el-table-column>

                                                <el-table-column label="Días" width="80" align="center">
                                                    <template #default="scope">
                                                        <span
                                                            class="font-semibold"
                                                            :class="Number(scope.row.days) < 0 ? 'text-red-500' : 'text-emerald-600'"
                                                        >
                                                            {{ formatSignedDays(scope.row.days) }}
                                                        </span>
                                                    </template>
                                                </el-table-column>

                                                <el-table-column label="Motivo" min-width="150">
                                                    <template #default="scope">
                                                        <span class="text-xs text-gray-500">{{ scope.row.reason || '—' }}</span>
                                                    </template>
                                                </el-table-column>

                                                <el-table-column label="Registró" min-width="140">
                                                    <template #default="scope">
                                                        <span class="text-xs text-gray-500">
                                                            {{ scope.row.author_name || '—' }}
                                                            <br>
                                                            {{ formatDate(scope.row.created_at) }}
                                                        </span>
                                                    </template>
                                                </el-table-column>

                                                <el-table-column v-if="canManageVacations" label="" width="60" align="right">
                                                    <template #default="scope">
                                                        <el-button
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

                                            <p v-else class="text-xs text-gray-400 dark:text-gray-500 italic py-4">
                                                Sin movimientos manuales registrados.
                                            </p>
                                        </div>
                                    </div>

                                    <el-collapse v-if="(vacationBalance.seasons || []).length">
                                        <el-collapse-item name="seasons">
                                            <template #title>
                                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                                    Detalle por temporada (antigüedad)
                                                </span>
                                            </template>

                                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                                Los días se descuentan de la temporada más antigua con saldo, aunque las vacaciones
                                                se tomen en un año posterior; abajo de cada temporada se indica de dónde salió cada solicitud.
                                            </p>

                                            <div class="space-y-3">
                                                <div
                                                    v-for="season in vacationBalance.seasons"
                                                    :key="season.season"
                                                    class="rounded-xl border p-4"
                                                    :class="season.is_current
                                                        ? 'border-[#f26c17]/25 bg-[#fffaf5] dark:bg-[#262019]/60 dark:border-[#f26c17]/20'
                                                        : 'border-gray-100 dark:border-[#2b2b2e]'"
                                                >
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <div class="flex items-center gap-2">
                                                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Año {{ season.season }}</p>
                                                            <el-tag v-if="season.is_current" size="small" type="primary" effect="plain">En curso</el-tag>
                                                        </div>
                                                        <p class="text-xs text-gray-400">
                                                            {{ formatDate(season.start) }} — {{ formatDate(season.end) }} · vence el {{ formatDate(season.expiry_date) }}
                                                        </p>
                                                    </div>

                                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-3">
                                                        <div>
                                                            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Por derecho</p>
                                                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ formatDays(season.entitled) }}</p>
                                                        </div>
                                                        <div>
                                                            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Obtenidos</p>
                                                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ formatDays(season.accrued) }}</p>
                                                        </div>
                                                        <div>
                                                            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Tomados</p>
                                                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ formatDays(season.taken) }}</p>
                                                        </div>
                                                        <div>
                                                            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Disponibles</p>
                                                            <p class="text-sm font-semibold text-emerald-600">{{ formatDays(season.available) }}</p>
                                                        </div>
                                                    </div>

                                                    <div v-if="(season.consumptions || []).length" class="mt-3 pt-3 border-t border-dashed border-gray-100 dark:border-[#2b2b2e]">
                                                        <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold mb-1.5">Días tomados de esta temporada</p>
                                                        <div class="flex flex-wrap gap-1.5">
                                                            <span
                                                                v-for="(consumption, index) in season.consumptions"
                                                                :key="index"
                                                                class="inline-flex items-center gap-1.5 rounded-full border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529] px-2.5 py-1 text-xs text-gray-600 dark:text-gray-300"
                                                            >
                                                                <strong class="text-gray-800 dark:text-gray-100">{{ formatDays(consumption.days) }}</strong>
                                                                <span>{{ formatDate(consumption.start_date) }} — {{ formatDate(consumption.end_date) }}</span>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <p v-else class="mt-3 text-xs text-gray-400 italic">Sin días tomados de esta temporada.</p>

                                                    <p v-if="Number(season.expired) > 0" class="text-xs text-red-500 mt-2">
                                                        {{ formatDays(season.expired) }} día(s) vencidos por no usarse antes de la fecha límite.
                                                    </p>
                                                </div>
                                            </div>
                                        </el-collapse-item>
                                    </el-collapse>
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

/* Season detail: blends with the card background instead of EP's darker panel. */
:deep(.el-collapse) {
    --el-collapse-header-bg-color: transparent;
    --el-collapse-content-bg-color: transparent;
    border-top: 1px solid var(--el-border-color-lighter);
    border-bottom: none;
}

:deep(.el-collapse-item__header) {
    background-color: transparent;
    border-bottom: none;
    height: auto;
    padding: 12px 0;
}

:deep(.el-collapse-item__wrap) {
    background-color: transparent;
    border-bottom: none;
}

:deep(.el-collapse-item__content) {
    padding: 0 0 4px;
}
</style>