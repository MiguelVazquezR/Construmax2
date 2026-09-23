<script setup>
import { computed } from 'vue';
import { Clock, Money, Suitcase, User } from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';
import ShiftSelectField from '@/Components/Payroll/ShiftSelectField.vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    // Full payroll block: termination date, salary, hours and the payroll subject
    // switch. False for technicians (they follow the "internal" prop instead).
    showPayrollFields: {
        type: Boolean,
        default: true,
    },
    // Internal technicians are the only ones paid through payroll, so they get the
    // payroll card (salary, hours and "Sujeto a nómina"); external ones do not.
    internal: {
        type: Boolean,
        default: true,
    },
    // Whether the profile already stores a kiosk pin (the pin itself is never exposed).
    hasKioskPin: {
        type: Boolean,
        default: false,
    },
    // Active shifts available to assign (empty list hides the selector).
    shifts: {
        type: Array,
        default: () => [],
    },
});

const { can } = usePermissions();

const canManage = computed(() => can('payroll.profiles.manage'));
const canManageRemote = computed(() => can('payroll.remote-attendance.manage'));
const isVisible = computed(() => canManage.value || canManageRemote.value);

// External technicians are providers, not company staff: the whole "Nómina y
// asistencia" block (collaborator data, payroll, schedule and attendance)
// stays hidden for them, and their payroll/attendance state is cleared when
// the "Empleado interno" switch turns off.
const showsSection = computed(() => isVisible.value && (props.showPayrollFields || props.internal));

// --- Asistencia ---

// The kiosk pin is digits only (4 to 12).
const onPinInput = (value) => {
    props.form.kiosk_pin = String(value ?? '').replace(/\D/g, '');
};
</script>

<template>
    <div v-if="showsSection" class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 border-b border-gray-100 dark:border-gray-700 pb-2 flex items-center gap-2">
            <el-icon class="text-primary"><Suitcase /></el-icon> Nómina y asistencia
        </h3>

        <!-- Datos del colaborador -->
        <div v-if="canManage" class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529] p-4">
            <p class="flex items-center gap-2 text-xs uppercase tracking-wider text-gray-400 font-bold mb-3">
                <el-icon><User /></el-icon> Colaborador
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
                <el-form-item label="Número de empleado" prop="employee_number" :error="form.errors.employee_number">
                    <el-input v-model="form.employee_number" placeholder="Se genera automáticamente" />
                </el-form-item>

                <el-form-item label="Fecha de ingreso" prop="hire_date" :error="form.errors.hire_date">
                    <el-date-picker
                        v-model="form.hire_date"
                        type="date"
                        value-format="YYYY-MM-DD"
                        placeholder="Seleccionar fecha"
                        style="width: 100%"
                    />
                </el-form-item>

                <el-form-item
                    v-if="showPayrollFields"
                    label="Fecha de baja"
                    prop="termination_date"
                    :error="form.errors.termination_date"
                >
                    <el-date-picker
                        v-model="form.termination_date"
                        type="date"
                        value-format="YYYY-MM-DD"
                        placeholder="Vacío si sigue activo"
                        style="width: 100%"
                    />
                </el-form-item>
            </div>

            <p v-if="showPayrollFields" class="text-xs text-gray-400">
                Con fecha de baja, el colaborador permanece en la nómina hasta ese día y deja de aparecer en los periodos siguientes.
            </p>
        </div>

        <!-- Nómina -->
        <div v-if="canManage" class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529] p-4">
            <p class="flex items-center gap-2 text-xs uppercase tracking-wider text-gray-400 font-bold mb-3">
                <el-icon><Money /></el-icon> Nómina
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
                <el-form-item label="Sueldo diario" prop="daily_salary" :error="form.errors.daily_salary">
                    <el-input-number
                        v-model="form.daily_salary"
                        :min="0"
                        :max="9999999"
                        :precision="2"
                        :controls="false"
                        placeholder="0.00"
                        style="width: 100%"
                    />
                </el-form-item>

                <ShiftSelectField v-if="shifts.length > 0" :form="form" :shifts="shifts" />
            </div>

            <p v-if="shifts.length === 0" class="text-xs text-gray-400 mb-3">
                Aún no hay horarios creados. Créalos en «Horarios del personal» para asignarlos aquí.
            </p>

            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 dark:border-[#2b2b2e] bg-white dark:bg-[#1e1e20] px-3 py-2.5">
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Sujeto a nómina</p>
                    <p class="text-xs text-gray-400">Aparece en los periodos y recibe sueldo por sus días pagados. Requiere un horario asignado.</p>
                </div>
                <el-switch v-model="form.is_payroll_subject" style="--el-switch-on-color: #f26c17;" />
            </div>
        </div>

        <!-- Asistencia -->
        <div v-if="canManage || canManageRemote" class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50/60 dark:bg-[#252529] p-4 space-y-3">
            <p class="flex items-center gap-2 text-xs uppercase tracking-wider text-gray-400 font-bold">
                <el-icon><Clock /></el-icon> Asistencia
            </p>

            <div
                v-if="canManage"
                class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 dark:border-[#2b2b2e] bg-white dark:bg-[#1e1e20] px-3 py-2.5"
            >
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Registra asistencia</p>
                    <p class="text-xs text-gray-400">Puede registrar en el kiosco y en el portal Mi asistencia.</p>
                </div>
                <el-switch v-model="form.is_attendance_subject" style="--el-switch-on-color: #f26c17;" />
            </div>

            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 dark:border-[#2b2b2e] bg-white dark:bg-[#1e1e20] px-3 py-2.5">
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Asistencia remota</p>
                    <p class="text-xs text-gray-400">
                        Puede registrar entrada y salida desde su cuenta con ubicación.
                        <template v-if="!form.is_attendance_subject">Requiere activar «Registra asistencia».</template>
                    </p>
                </div>
                <el-switch
                    v-model="form.can_remote_attendance"
                    :disabled="!form.is_attendance_subject"
                    style="--el-switch-on-color: #f26c17;"
                />
            </div>

            <!-- PIN de kiosco -->
            <div v-if="canManage" class="rounded-lg border border-gray-100 dark:border-[#2b2b2e] bg-white dark:bg-[#1e1e20] px-3 py-2.5">
                <el-form-item label="PIN de kiosco" prop="kiosk_pin" :error="form.errors.kiosk_pin" class="!mb-0">
                    <el-input
                        v-model="form.kiosk_pin"
                        maxlength="12"
                        show-password
                        inputmode="numeric"
                        :disabled="!form.is_attendance_subject"
                        :placeholder="hasKioskPin ? 'Configurado · escribe uno nuevo' : '4 a 12 dígitos'"
                        @input="onPinInput"
                    />
                </el-form-item>
                <p class="text-xs text-gray-400 mt-1">
                    Permite registrar asistencia en el kiosco con número de empleado y PIN, sin usar el rostro.
                    <template v-if="hasKioskPin">Escribe uno nuevo para reemplazarlo o déjalo vacío para conservar el actual.</template>
                    <template v-if="!form.is_attendance_subject">Requiere activar «Registra asistencia».</template>
                </p>
            </div>
        </div>
    </div>
</template>
