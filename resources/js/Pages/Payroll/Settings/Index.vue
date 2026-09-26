<script setup>
import { computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsSection from '@/Components/Payroll/SettingsSection.vue';
import { ElMessage } from 'element-plus';
import {
    Camera, Clock, Coin, InfoFilled, Suitcase, Sunny,
} from '@element-plus/icons-vue';

const props = defineProps({
    settings: Object,
    lateDiscountModes: Object,
    vacationPremiumNoticeModes: Object,
    expenseCategories: Array,
});

const toNumber = (value, fallback = null) => {
    if (value === null || value === undefined || value === '') return fallback;
    const parsed = Number(value);
    return Number.isNaN(parsed) ? fallback : parsed;
};

const form = useForm({
    // Attendance and late arrivals
    late_tolerance_minutes: toNumber(props.settings.late_tolerance_minutes, 10),
    late_discount_mode: props.settings.late_discount_mode || 'track_only',
    remote_geolocation_required: Boolean(props.settings.remote_geolocation_required),

    // Worked holidays
    holiday_worked_extra_multiplier: toNumber(props.settings.holiday_worked_extra_multiplier, 2),

    // Vacations
    vacation_min_days_to_request: toNumber(props.settings.vacation_min_days_to_request, 1),
    vacation_carryover_months: toNumber(props.settings.vacation_carryover_months, 18),
    vacation_premium_notice_enabled: Boolean(props.settings.vacation_premium_notice_enabled),
    vacation_premium_notice_mode: props.settings.vacation_premium_notice_mode || 'once',

    // Defaults
    payroll_expense_category_id: props.settings.payroll_expense_category_id ?? null,

    // Face recognition
    face_recognition_enabled: Boolean(props.settings.face_recognition_enabled),
    face_match_threshold: toNumber(props.settings.face_match_threshold, 90),
    kiosk_pin_fallback_enabled: Boolean(props.settings.kiosk_pin_fallback_enabled),
    rekognition_collection_id: props.settings.rekognition_collection_id || 'construmax-attendance',

    // Attendance evidence
    attendance_capture_retention_months: toNumber(props.settings.attendance_capture_retention_months, 12),
});

const lateDiscountOptions = computed(() =>
    Object.entries(props.lateDiscountModes || {}).map(([value, label]) => ({ value, label }))
);

const premiumNoticeOptions = computed(() =>
    Object.entries(props.vacationPremiumNoticeModes || {}).map(([value, label]) => ({ value, label }))
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
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 ml-3">Si está activo, el colaborador no puede registrar desde su celular sin compartir su ubicación.</p>
                        </el-form-item>
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

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <el-form-item label="Aviso de prima vacacional" prop="vacation_premium_notice_enabled" :error="form.errors.vacation_premium_notice_enabled">
                                <el-switch v-model="form.vacation_premium_notice_enabled" style="--el-switch-on-color: #f26c17;" />
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 ml-3">La prima se paga al cumplirse cada año de servicio. El aviso llega a los suscritos en Configuración → Notificaciones.</p>
                            </el-form-item>

                            <el-form-item label="Momento del aviso" prop="vacation_premium_notice_mode" :error="form.errors.vacation_premium_notice_mode">
                                <el-select v-model="form.vacation_premium_notice_mode" class="w-full" :disabled="! form.vacation_premium_notice_enabled">
                                    <el-option
                                        v-for="option in premiumNoticeOptions"
                                        :key="option.value"
                                        :label="option.label"
                                        :value="option.value"
                                    />
                                </el-select>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">El aviso se envía dentro del periodo de nómina en el que el colaborador cumple su año de servicio: un solo aviso al inicio o cada día del periodo hasta registrar el pago.</p>
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

                    <!-- Reconocimiento facial -->
                    <SettingsSection
                        title="Reconocimiento facial (AWS Rekognition)"
                        description="Identificación por rostro en el kiosco de asistencia."
                    >
                        <template #icon><el-icon :size="18"><Camera /></el-icon></template>

                        <el-form-item label="Reconocimiento facial activo" prop="face_recognition_enabled" :error="form.errors.face_recognition_enabled">
                            <el-switch v-model="form.face_recognition_enabled" style="--el-switch-on-color: #f26c17;" />
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 ml-3">Habilita el botón «Registrar con rostro» en el kiosco.</p>
                        </el-form-item>

                        <el-form-item label="Umbral de coincidencia (similitud mínima)" prop="face_match_threshold" :error="form.errors.face_match_threshold">
                            <el-slider v-model="form.face_match_threshold" :min="50" :max="100" show-input :disabled="!form.face_recognition_enabled" />
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Similitud mínima para aceptar la identificación; más alto es más estricto.</p>
                        </el-form-item>

                        <el-form-item label="Respaldo con número de empleado y PIN en kiosco" prop="kiosk_pin_fallback_enabled" :error="form.errors.kiosk_pin_fallback_enabled">
                            <el-switch v-model="form.kiosk_pin_fallback_enabled" style="--el-switch-on-color: #f26c17;" />
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 ml-3">Muestra la opción de registrar con número de empleado y PIN como alternativa al rostro.</p>
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
                        description="Ajustes generales del módulo de nómina."
                    >
                        <template #icon><el-icon :size="18"><Coin /></el-icon></template>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                        </div>
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
