<script setup>
import { computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsSection from '@/Components/Payroll/SettingsSection.vue';
import { ElMessage } from 'element-plus';
import {
    Calendar, Camera, Clock, Coin, FirstAidKit, InfoFilled, Picture, Suitcase, Sunny, Timer,
} from '@element-plus/icons-vue';

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
                    <SettingsSection
                        title="Periodo de nómina"
                        description="Cada cuánto se calcula la nómina y desde qué fecha se cuentan los periodos."
                    >
                        <template #icon><el-icon :size="18"><Calendar /></el-icon></template>

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
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Define la duración de cada periodo y sus fechas de pago.</p>
                            </el-form-item>

                            <el-form-item label="Fecha de inicio del primer periodo" prop="period_anchor_date" :error="form.errors.period_anchor_date">
                                <el-date-picker
                                    v-model="form.period_anchor_date"
                                    type="date"
                                    value-format="YYYY-MM-DD"
                                    placeholder="Seleccionar fecha"
                                    class="w-full"
                                />
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Solo se usa para generar el primer periodo; después avanzan solos.</p>
                            </el-form-item>
                        </div>

                        <div class="flex items-start gap-2 rounded-lg bg-gray-50 dark:bg-[#252529] px-3 py-2.5 text-xs text-gray-500 dark:text-gray-400">
                            <el-icon class="mt-0.5 shrink-0"><InfoFilled /></el-icon>
                            <p>
                                El cierre y la creación del siguiente periodo se ejecutan automáticamente a la 01:00:
                                semanal (7 días), catorcenal (14 días) y quincenal (día 1 al 15 y 16 al último del mes).
                                El periodo abierto se recalcula en tiempo real con los registros del día.
                            </p>
                        </div>
                    </SettingsSection>

                    <!-- Asistencia y retardos -->
                    <SettingsSection
                        title="Asistencia y retardos"
                        description="Cómo se miden los retardos y desde dónde puede registrar el colaborador."
                    >
                        <template #icon><el-icon :size="18"><Clock /></el-icon></template>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <el-form-item label="Tolerancia de retardo (minutos)" prop="late_tolerance_minutes" :error="form.errors.late_tolerance_minutes">
                                <el-input-number
                                    v-model="form.late_tolerance_minutes"
                                    :min="0"
                                    :max="120"
                                    :controls="false"
                                    style="width: 100%"
                                />
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Minutos de gracia antes de considerar un retardo. Cada horario puede tener su propio valor.</p>
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
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Elige si el retardo solo se registra o también se descuenta del pago.</p>
                            </el-form-item>
                        </div>

                        <el-form-item label="Ubicación obligatoria en asistencia remota" prop="remote_geolocation_required" :error="form.errors.remote_geolocation_required">
                            <el-switch v-model="form.remote_geolocation_required" style="--el-switch-on-color: #f26c17;" />
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Si está activo, el colaborador no puede registrar desde su celular sin compartir su ubicación.</p>
                        </el-form-item>
                    </SettingsSection>

                    <!-- Tiempo extra -->
                    <SettingsSection
                        title="Tiempo extra"
                        description="Cómo se pagan las horas trabajadas más allá de la jornada."
                    >
                        <template #icon><el-icon :size="18"><Timer /></el-icon></template>

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
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Pago de las primeras horas extra de la semana.</p>
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
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Pago del excedente al superar el umbral.</p>
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
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Horas extra por semana que se pagan al doble.</p>
                            </el-form-item>
                        </div>

                        <div class="flex items-start gap-2 rounded-lg bg-gray-50 dark:bg-[#252529] px-3 py-2.5 text-xs text-gray-500 dark:text-gray-400">
                            <el-icon class="mt-0.5 shrink-0"><InfoFilled /></el-icon>
                            <p>
                                Regla LFT: hasta el umbral semanal el tiempo extra se paga al doble; lo que lo supera
                                se paga al triple. El tiempo trabajado en un día de descanso también cuenta como extra.
                            </p>
                        </div>
                    </SettingsSection>

                    <!-- Días festivos -->
                    <SettingsSection
                        title="Días festivos"
                        description="Pago adicional cuando un colaborador trabaja en un día festivo."
                    >
                        <template #icon><el-icon :size="18"><Sunny /></el-icon></template>

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
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Además del sueldo del día, cuánto se paga por laborar el festivo (LFT art. 75: 2 = un salario doble adicional).</p>
                        </el-form-item>

                        <div class="flex items-start gap-2 rounded-lg bg-gray-50 dark:bg-[#252529] px-3 py-2.5 text-xs text-gray-500 dark:text-gray-400">
                            <el-icon class="mt-0.5 shrink-0"><InfoFilled /></el-icon>
                            <p>
                                Los festivos oficiales se generan solos desde el catálogo LFT y también puedes agregar
                                días de la empresa desde la pantalla <strong>Días festivos</strong>.
                            </p>
                        </div>
                    </SettingsSection>

                    <!-- Vacaciones -->
                    <SettingsSection
                        title="Vacaciones"
                        description="Reglas con las que se solicitan y vencen los días de vacaciones."
                    >
                        <template #icon><el-icon :size="18"><Suitcase /></el-icon></template>

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
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Saldo mínimo que debe tener el colaborador para poder solicitar.</p>
                            </el-form-item>

                            <el-form-item label="Vigencia del saldo arrastrado (meses)" prop="vacation_carryover_months" :error="form.errors.vacation_carryover_months">
                                <el-input-number
                                    v-model="form.vacation_carryover_months"
                                    :min="0"
                                    :max="60"
                                    :controls="false"
                                    style="width: 100%"
                                />
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Meses que sobrevive el saldo no usado de cada temporada antes de vencer.</p>
                            </el-form-item>
                        </div>

                        <div class="flex items-start gap-2 rounded-lg bg-gray-50 dark:bg-[#252529] px-3 py-2.5 text-xs text-gray-500 dark:text-gray-400">
                            <el-icon class="mt-0.5 shrink-0"><InfoFilled /></el-icon>
                            <p>
                                Los días por antigüedad son los de la LFT: 12 el primer año, +2 por año hasta 20 y +2
                                cada 5 años. Se acumulan por semana y se consumen de la temporada más antigua a la más
                                reciente.
                            </p>
                        </div>
                    </SettingsSection>

                    <!-- Incapacidades -->
                    <SettingsSection
                        title="Incapacidades"
                        description="Qué parte del día se paga cuando existe una incapacidad médica."
                    >
                        <template #icon><el-icon :size="18"><FirstAidKit /></el-icon></template>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <el-form-item label="Se paga la incapacidad" prop="incapacity_paid" :error="form.errors.incapacity_paid">
                                <el-switch v-model="form.incapacity_paid" style="--el-switch-on-color: #f26c17;" />
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Si se desactiva, los días de incapacidad quedan sin goce de sueldo.</p>
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
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Porcentaje del sueldo diario que se paga por cada día de incapacidad.</p>
                            </el-form-item>
                        </div>
                    </SettingsSection>

                    <!-- Reconocimiento facial -->
                    <SettingsSection
                        title="Reconocimiento facial (AWS Rekognition)"
                        description="Identificación por rostro en el kiosco de asistencia."
                    >
                        <template #icon><el-icon :size="18"><Camera /></el-icon></template>

                        <el-form-item label="Reconocimiento facial activo" prop="face_recognition_enabled" :error="form.errors.face_recognition_enabled">
                            <el-switch v-model="form.face_recognition_enabled" style="--el-switch-on-color: #f26c17;" />
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Habilita el botón «Registrar con rostro» en el kiosco.</p>
                        </el-form-item>

                        <el-form-item label="Umbral de coincidencia (similitud mínima)" prop="face_match_threshold" :error="form.errors.face_match_threshold">
                            <el-slider v-model="form.face_match_threshold" :min="50" :max="100" show-input :disabled="!form.face_recognition_enabled" />
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Similitud mínima para aceptar la identificación; más alto es más estricto.</p>
                        </el-form-item>

                        <el-form-item label="Respaldo con número de empleado y PIN en kiosco" prop="kiosk_pin_fallback_enabled" :error="form.errors.kiosk_pin_fallback_enabled">
                            <el-switch v-model="form.kiosk_pin_fallback_enabled" style="--el-switch-on-color: #f26c17;" />
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Permite registrar con número de empleado y PIN si el rostro no se reconoce.</p>
                        </el-form-item>

                        <el-form-item label="Colección de rostros en Rekognition" prop="rekognition_collection_id" :error="form.errors.rekognition_collection_id">
                            <el-input v-model="form.rekognition_collection_id" placeholder="construmax-attendance" />
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Nombre de la colección en AWS; no lo cambies si ya hay colaboradores enrolados.</p>
                        </el-form-item>

                        <div class="flex items-start gap-2 rounded-lg bg-gray-50 dark:bg-[#252529] px-3 py-2.5 text-xs text-gray-500 dark:text-gray-400">
                            <el-icon class="mt-0.5 shrink-0"><InfoFilled /></el-icon>
                            <p>
                                Requiere credenciales de AWS configuradas en el servidor. Mientras no esté activo, el
                                kiosco puede operar con el respaldo de PIN.
                            </p>
                        </div>
                    </SettingsSection>

                    <!-- Valores predeterminados -->
                    <SettingsSection
                        title="Valores predeterminados"
                        description="Valores que se usan cuando el colaborador no tiene otro configurado."
                    >
                        <template #icon><el-icon :size="18"><Coin /></el-icon></template>

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
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Horas de una jornada completa para quien no tiene horario asignado.</p>
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
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Categoría con la que se registra el gasto de la nómina en Control de gastos.</p>
                            </el-form-item>
                        </div>
                    </SettingsSection>

                    <!-- Evidencia de asistencia -->
                    <SettingsSection
                        title="Evidencia de asistencia"
                        description="Cuánto tiempo se guardan las fotos de los registros."
                    >
                        <template #icon><el-icon :size="18"><Picture /></el-icon></template>

                        <el-form-item label="Conservar fotos de registro (meses)" prop="attendance_capture_retention_months" :error="form.errors.attendance_capture_retention_months">
                            <el-input-number
                                v-model="form.attendance_capture_retention_months"
                                :min="1"
                                :max="120"
                                :controls="false"
                                style="width: 100%"
                            />
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Las fotos de cada registro se eliminan automáticamente al cumplir este plazo.</p>
                        </el-form-item>
                    </SettingsSection>
                </div>

                <div class="sticky bottom-4 z-10 pt-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-white/95 dark:bg-[#1e1e20]/95 backdrop-blur px-4 py-3 shadow-sm">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Los cambios se aplican de inmediato a los cálculos de los periodos abiertos.
                        </p>
                        <el-button type="primary" native-type="submit" :loading="form.processing" color="#f26c17">
                            Guardar configuración
                        </el-button>
                    </div>
                </div>
            </el-form>
        </div>
    </AppLayout>
</template>
