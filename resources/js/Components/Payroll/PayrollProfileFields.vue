<script setup>
import { computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    // False for technicians: they record attendance but are not in the payroll.
    showPayrollFields: {
        type: Boolean,
        default: true,
    },
});

const { can } = usePermissions();

const canManage = computed(() => can('payroll.profiles.manage'));
const canManageRemote = computed(() => can('payroll.remote-attendance.manage'));
const isVisible = computed(() => canManage.value || canManageRemote.value);
</script>

<template>
    <div v-if="isVisible" class="mb-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 border-b border-gray-100 dark:border-gray-700 pb-2 mb-3 flex items-center gap-2">
            <el-icon class="text-primary"><Suitcase /></el-icon> Nómina y asistencia
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <template v-if="canManage">
                <el-form-item label="Número de empleado" prop="employee_number" :error="form.errors.employee_number">
                    <el-input v-model="form.employee_number" placeholder="Se genera automáticamente" />
                </el-form-item>

                <el-form-item label="Fecha de ingreso" prop="hire_date" :error="form.errors.hire_date">
                    <el-date-picker
                        v-model="form.hire_date"
                        type="date"
                        value-format="YYYY-MM-DD"
                        placeholder="Seleccionar fecha"
                        class="w-full"
                    />
                </el-form-item>

                <el-form-item label="PIN de kiosco" prop="kiosk_pin" :error="form.errors.kiosk_pin">
                    <el-input
                        v-model="form.kiosk_pin"
                        show-password
                        maxlength="12"
                        placeholder="Dejar vacío para conservar el actual"
                    />
                </el-form-item>

                <template v-if="showPayrollFields">
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

                    <el-form-item label="Horas por día" prop="daily_hours" :error="form.errors.daily_hours">
                        <el-input-number
                            v-model="form.daily_hours"
                            :min="1"
                            :max="24"
                            :precision="2"
                            :controls="false"
                            placeholder="8"
                            style="width: 100%"
                        />
                    </el-form-item>
                </template>
            </template>

            <el-form-item
                v-if="canManage && showPayrollFields"
                label="Sujeto a nómina"
                prop="is_payroll_subject"
                :error="form.errors.is_payroll_subject"
            >
                <el-switch v-model="form.is_payroll_subject" />
            </el-form-item>

            <el-form-item
                v-if="canManage"
                label="Registra asistencia"
                prop="is_attendance_subject"
                :error="form.errors.is_attendance_subject"
            >
                <el-switch v-model="form.is_attendance_subject" />
            </el-form-item>

            <el-form-item
                v-if="canManage || canManageRemote"
                label="Asistencia remota"
                prop="can_remote_attendance"
                :error="form.errors.can_remote_attendance"
            >
                <el-switch v-model="form.can_remote_attendance" :disabled="!form.is_attendance_subject" />
            </el-form-item>
        </div>

        <p class="text-xs text-gray-400 dark:text-gray-500">
            El PIN de kiosco funciona como respaldo cuando el reconocimiento facial no identifica al colaborador.
            La asistencia remota permite marcar entrada y salida desde su cuenta con ubicación.
        </p>
    </div>
</template>
