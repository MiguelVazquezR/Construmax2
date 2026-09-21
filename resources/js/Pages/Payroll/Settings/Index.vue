<script setup>
import { computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';

const props = defineProps({
    settings: Object,
    periodTypes: Object,
    lateDiscountModes: Object,
    expenseCategories: Array,
});

const toNumber = (value, fallback = null) => {
    if (value === null || value === undefined || value === '') return fallback;
    const parsed = Number(value);
    return Number.isNaN(parsed) ? fallback : parsed;
};

const toDateInput = (value) => (value ? String(value).substring(0, 10) : null);

const form = useForm({
    // Payroll period
    period_type: props.settings.period_type || 'weekly',
    period_anchor_date: toDateInput(props.settings.period_anchor_date),

    // Attendance and late arrivals
    late_tolerance_minutes: toNumber(props.settings.late_tolerance_minutes, 10),
    late_discount_mode: props.settings.late_discount_mode || 'track_only',
    remote_geolocation_required: Boolean(props.settings.remote_geolocation_required),

    // Overtime
    overtime_double_multiplier: toNumber(props.settings.overtime_double_multiplier, 2),
    overtime_triple_multiplier: toNumber(props.settings.overtime_triple_multiplier, 3),
    overtime_weekly_threshold_hours: toNumber(props.settings.overtime_weekly_threshold_hours, 9),

    // Worked holidays
    holiday_worked_extra_multiplier: toNumber(props.settings.holiday_worked_extra_multiplier, 2),

    // Vacations
    vacation_min_days_to_request: toNumber(props.settings.vacation_min_days_to_request, 1),
    vacation_carryover_months: toNumber(props.settings.vacation_carryover_months, 18),

    // Medical leaves
    incapacity_paid: Boolean(props.settings.incapacity_paid),
    incapacity_pay_percentage: toNumber(props.settings.incapacity_pay_percentage, 60),

    // Defaults
    default_daily_hours: toNumber(props.settings.default_daily_hours, 8),
    payroll_expense_category_id: props.settings.payroll_expense_category_id ?? null,

    // Face recognition
    face_recognition_enabled: Boolean(props.settings.face_recognition_enabled),
    face_match_threshold: toNumber(props.settings.face_match_threshold, 90),
    kiosk_pin_fallback_enabled: Boolean(props.settings.kiosk_pin_fallback_enabled),
    rekognition_collection_id: props.settings.rekognition_collection_id || 'construmax-attendance',

    // Attendance evidence
    attendance_capture_retention_months: toNumber(props.settings.attendance_capture_retention_months, 12),
});

const periodTypeOptions = computed(() =>
    Object.entries(props.periodTypes || {}).map(([value, label]) => ({ value, label }))
);

const lateDiscountOptions = computed(() =>
    Object.entries(props.lateDiscountModes || {}).map(([value, label]) => ({ value, label }))
);

const submit = () => {
    form.put(route('payroll.settings.update'), {
        onSuccess: () => ElMessage.success('Configuración de nómina actualizada.'),
    });
};
</script>

<template>
    <AppLayout title="Configuración de nómina">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">
                        Configuración de nómina
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Parámetros generales del módulo de Recursos Humanos y Nómina.
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <el-form :model="form" label-position="top" size="default" @submit.prevent="submit">
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

                    <!-- Periodo de nómina -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-6">
                        <h3 class="font-bold text-gray-800 dark:text-gray-100 mb-4">Periodo de nómina</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <el-form-item label="Tipo de periodo" prop="period_type" :error="form.errors.period_type">
                                <el-select v-model="form.period_type" class="w-full">
                                    <el-option
                                        v-for="option in periodTypeOptions"
                                        :key="option.value"
                                        :label="option.label"
                                        :value="option.value"
                                    />
                                </el-select>
                            </el-form-item>

                            <el-form-item label="Fecha de inicio del primer periodo" prop="period_anchor_date" :error="form.errors.period_anchor_date">
                                <el-date-picker
                                    v-model="form.period_anchor_date"
                                    type="date"
                                    value-format="YYYY-MM-DD"
                                    placeholder="Seleccionar fecha"
                                    class="w-full"
                                />
                            </el-form-item>
                        </div>

                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            El cierre del periodo y la creación del siguiente se ejecutan automáticamente a la 01:00
                            del día de inicio del nuevo periodo (semanal: 7 días, catorcenal: 14 días,
                            quincenal: día 1 al 15 y 16 al último día del mes).
                        </p>
                    </div>

                    <!-- Asistencia y retardos -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-6">
                        <h3 class="font-bold text-gray-800 dark:text-gray-100 mb-4">Asistencia y retardos</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <el-form-item label="Tolerancia de retardo (minutos)" prop="late_tolerance_minutes" :error="form.errors.late_tolerance_minutes">
                                <el-input-number
                                    v-model="form.late_tolerance_minutes"
                                    :min="0"
                                    :max="120"
                                    :controls="false"
                                    style="width: 100%"
                                />
                            </el-form-item>

                            <el-form-item label="Manejo de retardos en nómina" prop="late_discount_mode" :error="form.errors.late_discount_mode">
                                <el-select v-model="form.late_discount_mode" class="w-full">
                                    <el-option
                                        v-for="option in lateDiscountOptions"
                                        :key="option.value"
                                        :label="option.label"
                                        :value="option.value"
                                    />
                                </el-select>
                            </el-form-item>
                        </div>

                        <el-form-item label="Ubicación obligatoria en asistencia remota" prop="remote_geolocation_required" :error="form.errors.remote_geolocation_required">
                            <el-switch v-model="form.remote_geolocation_required" />
                        </el-form-item>
                    </div>

                    <!-- Tiempo extra -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-6">
                        <h3 class="font-bold text-gray-800 dark:text-gray-100 mb-4">Tiempo extra</h3>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <el-form-item label="Multiplicador doble" prop="overtime_double_multiplier" :error="form.errors.overtime_double_multiplier">
                                <el-input-number
                                    v-model="form.overtime_double_multiplier"
                                    :min="1"
                                    :max="10"
                                    :step="0.5"
                                    :precision="2"
                                    :controls="false"
                                    style="width: 100%"
                                />
                            </el-form-item>

                            <el-form-item label="Multiplicador triple" prop="overtime_triple_multiplier" :error="form.errors.overtime_triple_multiplier">
                                <el-input-number
                                    v-model="form.overtime_triple_multiplier"
                                    :min="1"
                                    :max="10"
                                    :step="0.5"
                                    :precision="2"
                                    :controls="false"
                                    style="width: 100%"
                                />
                            </el-form-item>

                            <el-form-item label="Umbral semanal (horas)" prop="overtime_weekly_threshold_hours" :error="form.errors.overtime_weekly_threshold_hours">
                                <el-input-number
                                    v-model="form.overtime_weekly_threshold_hours"
                                    :min="0"
                                    :max="48"
                                    :step="0.5"
                                    :precision="2"
                                    :controls="false"
                                    style="width: 100%"
                                />
                            </el-form-item>
                        </div>

                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            Regla predeterminada LFT: las primeras horas extra de la semana se pagan al multiplicador
                            doble; al superar el umbral semanal, el excedente se paga al triple.
                        </p>
                    </div>

                    <!-- Días festivos -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-6">
                        <h3 class="font-bold text-gray-800 dark:text-gray-100 mb-4">Días festivos</h3>

                        <el-form-item label="Pago extra por jornada festiva laborada (multiplicador)" prop="holiday_worked_extra_multiplier" :error="form.errors.holiday_worked_extra_multiplier">
                            <el-input-number
                                v-model="form.holiday_worked_extra_multiplier"
                                :min="1"
                                :max="10"
                                :step="0.5"
                                :precision="2"
                                :controls="false"
                                style="width: 100%"
                            />
                        </el-form-item>

                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            Art. 75 LFT: si el colaborador trabaja un día de descanso obligatorio, además del salario
                            del día recibe un salario doble (multiplicador 2). El catálogo de días festivos se
                            calcula automáticamente y puede ajustarse manualmente.
                        </p>
                    </div>

                    <!-- Vacaciones -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-6">
                        <h3 class="font-bold text-gray-800 dark:text-gray-100 mb-4">Vacaciones</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <el-form-item label="Días acumulados mínimos para solicitar" prop="vacation_min_days_to_request" :error="form.errors.vacation_min_days_to_request">
                                <el-input-number
                                    v-model="form.vacation_min_days_to_request"
                                    :min="0"
                                    :max="60"
                                    :step="0.5"
                                    :precision="2"
                                    :controls="false"
                                    style="width: 100%"
                                />
                            </el-form-item>

                            <el-form-item label="Vigencia del saldo arrastrado (meses)" prop="vacation_carryover_months" :error="form.errors.vacation_carryover_months">
                                <el-input-number
                                    v-model="form.vacation_carryover_months"
                                    :min="0"
                                    :max="60"
                                    :controls="false"
                                    style="width: 100%"
                                />
                            </el-form-item>
                        </div>

                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            Los días por antigüedad se calculan según la LFT (12 días el primer año, +2 por año hasta 20,
                            después +2 cada 5 años) y se acumulan de forma proporcional semanal.
                        </p>
                    </div>

                    <!-- Incapacidades -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-6">
                        <h3 class="font-bold text-gray-800 dark:text-gray-100 mb-4">Incapacidades</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <el-form-item label="Se paga la incapacidad" prop="incapacity_paid" :error="form.errors.incapacity_paid">
                                <el-switch v-model="form.incapacity_paid" />
                            </el-form-item>

                            <el-form-item label="Porcentaje del día que se paga" prop="incapacity_pay_percentage" :error="form.errors.incapacity_pay_percentage">
                                <el-input-number
                                    v-model="form.incapacity_pay_percentage"
                                    :min="0"
                                    :max="100"
                                    :disabled="!form.incapacity_paid"
                                    :controls="false"
                                    style="width: 100%"
                                />
                            </el-form-item>
                        </div>

                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            Si no se paga, los días de incapacidad se registran como incidencia sin goce de sueldo.
                        </p>
                    </div>

                    <!-- Reconocimiento facial -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-6">
                        <h3 class="font-bold text-gray-800 dark:text-gray-100 mb-4">Reconocimiento facial (AWS Rekognition)</h3>

                        <el-form-item label="Reconocimiento facial activo" prop="face_recognition_enabled" :error="form.errors.face_recognition_enabled">
                            <el-switch v-model="form.face_recognition_enabled" />
                        </el-form-item>

                        <el-form-item label="Umbral de coincidencia (similitud mínima)" prop="face_match_threshold" :error="form.errors.face_match_threshold">
                            <el-slider v-model="form.face_match_threshold" :min="50" :max="100" show-input :disabled="!form.face_recognition_enabled" />
                        </el-form-item>

                        <el-form-item label="Respaldo con número de empleado y PIN en kiosco" prop="kiosk_pin_fallback_enabled" :error="form.errors.kiosk_pin_fallback_enabled">
                            <el-switch v-model="form.kiosk_pin_fallback_enabled" />
                        </el-form-item>

                        <el-form-item label="Colección de rostros en Rekognition" prop="rekognition_collection_id" :error="form.errors.rekognition_collection_id">
                            <el-input v-model="form.rekognition_collection_id" placeholder="construmax-attendance" />
                        </el-form-item>

                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            Requiere credenciales de AWS configuradas en el servidor. Antes de activarlo puedes operar
                            el kiosco con el respaldo de PIN.
                        </p>
                    </div>

                    <!-- Valores predeterminados -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-6">
                        <h3 class="font-bold text-gray-800 dark:text-gray-100 mb-4">Valores predeterminados</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <el-form-item label="Jornada diaria por defecto (horas)" prop="default_daily_hours" :error="form.errors.default_daily_hours">
                                <el-input-number
                                    v-model="form.default_daily_hours"
                                    :min="1"
                                    :max="24"
                                    :step="0.5"
                                    :precision="2"
                                    :controls="false"
                                    style="width: 100%"
                                />
                            </el-form-item>

                            <el-form-item label="Categoría del gasto por nómina" prop="payroll_expense_category_id" :error="form.errors.payroll_expense_category_id">
                                <el-select v-model="form.payroll_expense_category_id" placeholder="Seleccionar categoría" clearable class="w-full">
                                    <el-option
                                        v-for="category in expenseCategories"
                                        :key="category.id"
                                        :label="category.name"
                                        :value="category.id"
                                    />
                                </el-select>
                            </el-form-item>
                        </div>

                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            Al cerrar cada periodo se registra un gasto con el total de la nómina en Control de gastos.
                        </p>
                    </div>

                    <!-- Evidencia de asistencia -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-6">
                        <h3 class="font-bold text-gray-800 dark:text-gray-100 mb-4">Evidencia de asistencia</h3>

                        <el-form-item label="Conservar fotos de registro (meses)" prop="attendance_capture_retention_months" :error="form.errors.attendance_capture_retention_months">
                            <el-input-number
                                v-model="form.attendance_capture_retention_months"
                                :min="1"
                                :max="120"
                                :controls="false"
                                style="width: 100%"
                            />
                        </el-form-item>

                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            Las fotos capturadas en cada registro se eliminan automáticamente al vencer este plazo.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-6">
                    <el-button type="primary" native-type="submit" :loading="form.processing" color="#f26c17">
                        Guardar configuración
                    </el-button>
                </div>
            </el-form>
        </div>
    </AppLayout>
</template>
