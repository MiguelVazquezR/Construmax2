<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DayRecordsDialog from '@/Components/Payroll/DayRecordsDialog.vue';
import MyVacationPanel from '@/Components/Payroll/MyVacationPanel.vue';
import { Camera, Calendar, CircleCheck, Clock, Document, Location, User } from '@element-plus/icons-vue';
import { useFlashMessages } from '@/Composables/useFlashMessages';
import axios from 'axios';

const props = defineProps({
    profile: Object,
    today: Object,
    suggestedNextType: String,
    periodDays: Array,
    historyRange: Object,
    vacationBalance: Object,
    vacationMovements: Array,
    vacationPeriods: Array,
    vacationRequests: Array,
    payslips: Array,
    punchTypes: Object,
    vacationMinDays: Number,
    remoteGeolocationRequired: Boolean,
    remotePinEnabled: Boolean,
    faceRecognition: Object,
});

useFlashMessages();

const page = usePage();

const activeTab = ref('my-day');
const clock = ref(new Date());
const selectedType = ref(null);
const submitting = ref(false);
const errorMessage = ref('');
const result = ref(null);
const cameraReady = ref(false);
const cameraError = ref(false);
const recordsDialog = ref(false);
const recordsDay = ref(null);
const todayDialog = ref(false);
// Identification method of the remote punch: the collaborator chooses face
// or pin when both are available; otherwise the single active one is used.
const punchMethod = ref(null);
const punchPin = ref('');

const videoRef = ref(null);
let stream = null;
let clockTimer = null;
let resultTimer = null;

const typeEntries = computed(() =>
    Object.entries(props.punchTypes || {}).map(([value, label]) => ({ value, label }))
);

const canRemote = computed(() => props.profile?.can_remote_attendance === true);
const canPunch = computed(() => props.profile?.can_punch !== false);
const faceVerificationActive = computed(
    () => props.faceRecognition?.enabled === true && props.faceRecognition?.configured === true
);

// The collaborator can identify with a pin when the feature is active and
// their profile has one configured.
const pinAvailable = computed(() => props.remotePinEnabled === true && props.profile?.has_kiosk_pin === true);

// Both methods active: the collaborator must choose one first.
const needsMethodChoice = computed(() => faceVerificationActive.value && pinAvailable.value);

const effectiveMethod = computed(() => {
    if (needsMethodChoice.value) {
        return punchMethod.value;
    }

    if (faceVerificationActive.value) {
        return 'face';
    }

    if (pinAvailable.value) {
        return 'pin';
    }

    return 'manual';
});

const identificationTagLabel = computed(() => {
    if (needsMethodChoice.value) return 'Elige rostro o PIN';
    if (faceVerificationActive.value) return 'Verificación facial activa';
    if (pinAvailable.value) return 'Registro con PIN';

    return null;
});

// The camera is needed only while the face method is the one in use.
const cameraNeeded = computed(() => Boolean(selectedType.value) && effectiveMethod.value === 'face');

const timeLabel = computed(() =>
    clock.value.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
);

const dateLabel = computed(() =>
    clock.value.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' })
);

const workState = computed(() => {
    if (props.today?.is_paused) return { label: 'En descanso', type: 'warning' };
    if (props.today?.is_working) return { label: 'Dentro de horario', type: 'success' };
    if (props.today?.status === 'present') return { label: 'Jornada terminada', type: 'info' };
    return { label: 'Fuera de horario', type: 'info' };
});

const suggestedNextLabel = computed(() =>
    props.suggestedNextType ? props.punchTypes?.[props.suggestedNextType] : null
);

const selectedTypeLabel = computed(() => (selectedType.value ? props.punchTypes?.[selectedType.value] : null));

// Records captured today (shown as the timeline of "My day").
const todayPunches = computed(() => props.today?.punches ?? []);

// The suggested next punch is only relevant while the day is still open.
const showNextHint = computed(
    () => canPunch.value && canRemote.value && Boolean(suggestedNextLabel.value) && todayPunches.value.length > 0
);

// Totals of the days shown in the history table (current payroll period).
const historySummary = computed(() => {
    const days = props.periodDays ?? [];

    return {
        count: days.length,
        worked: days.reduce((total, day) => total + Number(day.worked_minutes || 0), 0),
        late: days.reduce((total, day) => total + (day.late_ignored ? 0 : Number(day.late_minutes || 0)), 0),
        overtime: days.reduce((total, day) => total + Number(day.overtime_minutes || 0), 0),
    };
});

const formatMinutes = (minutes) => {
    const value = Number(minutes || 0);
    const wholeHours = Math.floor(value / 60);
    const rest = value % 60;

    if (wholeHours === 0) return `${rest} min`;
    return rest === 0 ? `${wholeHours} h` : `${wholeHours} h ${rest} min`;
};

const fmtDate = (value) => {
    if (!value) return '-';
    return new Date(`${value}T00:00:00`).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const money = (value) => `$${Number(value || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

// "H:MM" hours, same format used by the payroll detail.
const hours = (minutes) => `${Math.floor(Number(minutes || 0) / 60)}:${String(Math.round(Number(minutes || 0) % 60)).padStart(2, '0')}`;

// --- Day statuses (same labels and colors as the payroll detail) ---

const incidentTagType = (type) => ({
    absence_unjustified: 'danger',
    absence_justified: 'primary',
    medical_leave: 'info',
    work_incapacity: 'info',
    permission_paid: 'primary',
    permission_unpaid: 'warning',
    vacation: 'warning',
    other: 'info',
}[type] || 'info');

const dayTagType = (day) => {
    if (day.status === 'incident') {
        return day.incident_type_key ? incidentTagType(day.incident_type_key) : 'warning';
    }

    return {
        present: 'success',
        absent: 'danger',
        no_record: 'info',
        rest_day: 'success',
        no_schedule: 'info',
        holiday: 'success',
    }[day.status] || 'info';
};

// Days without a single punch collapse into one full-width status pill.
const isPillRow = (day) => (day.punches || []).length === 0;

const daySpanMethod = ({ row, columnIndex }) => {
    if (! isPillRow(row)) {
        return [1, 1];
    }

    // Columns: 0 day · 1..5 (entry..extra) merged into the pill.
    if (columnIndex === 1) {
        return [1, 5];
    }

    if (columnIndex >= 2 && columnIndex <= 5) {
        return [0, 0];
    }

    return [1, 1];
};

// Minutes of the meal break of a day; null when the pair is incomplete.
const lunchMinutes = (day) => {
    if (! day.lunch_start || ! day.lunch_end) {
        return null;
    }

    const toMinutes = (time) => {
        const [hour, minute] = String(time).split(':').map(Number);

        return hour * 60 + minute;
    };

    const diff = toMinutes(day.lunch_end) - toMinutes(day.lunch_start);

    return diff > 0 ? diff : null;
};

// The remote pin accepts digits only.
const onPunchPinInput = (value) => {
    punchPin.value = String(value ?? '').replace(/\D/g, '');
};

const openRecords = (day) => {
    recordsDay.value = day;
    recordsDialog.value = true;
};

const initCamera = async () => {
    cameraError.value = false;

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { width: 640, height: 480, facingMode: 'user' },
            audio: false,
        });

        // The video element only exists once a punch type is selected.
        await nextTick();

        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            cameraReady.value = true;
        }
    } catch {
        cameraReady.value = false;
        cameraError.value = true;
    }
};

const stopCamera = () => {
    if (stream) {
        stream.getTracks().forEach((track) => track.stop());
        stream = null;
    }

    cameraReady.value = false;
};

// The camera is requested only while the face method is the one in use:
// picking the pin (or leaving the tab) releases it so no camera access
// stays active.
watch(cameraNeeded, async (needed) => {
    if (needed) {
        if (! cameraReady.value) {
            await initCamera();
        }

        return;
    }

    stopCamera();
});

// A new selection starts fresh: the method is chosen again and the pin is
// emptied.
watch(selectedType, (type) => {
    if (type === null) {
        punchMethod.value = null;
        punchPin.value = '';
    }
});

// Leaving the tab releases the camera and clears the half-done selection.
watch(activeTab, (tab) => {
    if (tab !== 'my-day') {
        selectedType.value = null;
    }
});

const capturePhoto = () => {
    if (!cameraReady.value || !videoRef.value) return null;

    const video = videoRef.value;
    const canvas = document.createElement('canvas');
    canvas.width = 480;
    canvas.height = 360;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

    return canvas.toDataURL('image/jpeg', 0.7);
};

const locate = () =>
    new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('unsupported'));
            return;
        }

        navigator.geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0,
        });
    });

const submitPunch = async () => {
    errorMessage.value = '';

    if (!selectedType.value) {
        errorMessage.value = 'Selecciona el tipo de registro.';
        return;
    }

    submitting.value = true;

    try {
        let latitude = null;
        let longitude = null;
        let accuracy = null;

        try {
            const position = await locate();
            latitude = position.coords.latitude;
            longitude = position.coords.longitude;
            accuracy = position.coords.accuracy;
        } catch {
            if (props.remoteGeolocationRequired) {
                errorMessage.value = 'No se pudo obtener tu ubicación. Actívala e intenta de nuevo.';
                return;
            }
        }

        const method = effectiveMethod.value;
        let photo = null;

        if (method === 'face') {
            photo = capturePhoto();

            if (! photo) {
                errorMessage.value = 'Se requiere la cámara para verificar tu rostro.';
                return;
            }
        }

        if (method === 'pin' && ! punchPin.value) {
            errorMessage.value = 'Escribe tu PIN para continuar.';
            return;
        }

        const { data } = await axios.post(route('payroll.my-attendance.punch'), {
            type: selectedType.value,
            method: method === 'manual' ? null : method,
            pin: method === 'pin' ? punchPin.value : null,
            latitude,
            longitude,
            location_accuracy: accuracy,
            photo,
        });

        result.value = data;
        selectedType.value = null;

        clearTimeout(resultTimer);
        resultTimer = setTimeout(() => {
            result.value = null;
        }, 6000);

        router.reload({ only: ['today', 'periodDays', 'suggestedNextType', 'historyRange'] });
    } catch (error) {
        const errors = error.response?.data?.errors;
        errorMessage.value = errors
            ? Object.values(errors).flat()[0]
            : 'No se pudo guardar el registro. Intenta de nuevo.';
    } finally {
        submitting.value = false;
    }
};

// Vacation requests live in the vacations panel (MyVacationPanel).
const printPayslip = (row) => {
    window.open(
        route('payroll.periods.payslips.print', { period: row.period_id, users: [page.props.auth.user.id] }),
        '_blank'
    );
};

// The vacations panel asks the page to refresh its vacation props after a
// request or a cancellation.
const reloadVacation = () => {
    router.reload({
        only: ['vacationBalance', 'vacationMovements', 'vacationPeriods', 'vacationRequests'],
    });
};

onMounted(() => {
    clockTimer = setInterval(() => {
        clock.value = new Date();
    }, 1000);
});

onBeforeUnmount(() => {
    clearInterval(clockTimer);
    clearTimeout(resultTimer);
    stopCamera();
});
</script>

<template>
    <AppLayout title="Mi asistencia">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-base text-gray-800 dark:text-white leading-tight">
                        Mi asistencia
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Colaborador {{ profile?.employee_number || 'sin número asignado' }}
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-tabs v-model="activeTab" class="px-4 pt-2">

                    <!-- MI DÍA -->
                    <el-tab-pane name="my-day">
                        <template #label>
                            <span class="flex items-center gap-2 px-2">
                                <el-icon><User /></el-icon> Mi día
                            </span>
                        </template>

                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                                <!-- Clock and today's totals -->
                                <div class="bg-gray-50 dark:bg-[#252529]/60 rounded-2xl border border-gray-100 dark:border-[#2b2b2e] p-6 flex flex-col">
                                    <div class="text-center">
                                        <p class="text-5xl font-bold tabular-nums tracking-tight text-gray-800 dark:text-white">{{ timeLabel }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 capitalize mt-1">{{ dateLabel }}</p>
                                        <div class="flex flex-wrap items-center justify-center gap-2 mt-4">
                                            <el-tag :type="workState.type" effect="dark" class="rounded-full">{{ workState.label }}</el-tag>
                                            <el-tag v-if="today?.shift_name" type="info" effect="plain" class="rounded-full">
                                                Horario: {{ today.shift_name }}
                                            </el-tag>
                                        </div>

                                        <el-button
                                            class="!mt-4 w-full !rounded-xl"
                                            size="large"
                                            :icon="Clock"
                                            @click="todayDialog = true"
                                        >
                                            Ver registros de hoy ({{ todayPunches.length }})
                                        </el-button>

                                        <p v-if="showNextHint" class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                            Siguiente registro sugerido:
                                            <span class="font-semibold text-gray-700 dark:text-gray-200">{{ suggestedNextLabel }}</span>
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 mt-6">
                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-white/70 dark:bg-[#1e1e20]/70 px-4 py-3">
                                            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Entrada</p>
                                            <p class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ today?.first_in || '—' }}</p>
                                        </div>
                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-white/70 dark:bg-[#1e1e20]/70 px-4 py-3">
                                            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Salida</p>
                                            <p class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ today?.last_out || '—' }}</p>
                                        </div>
                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-white/70 dark:bg-[#1e1e20]/70 px-4 py-3">
                                            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Comida</p>
                                            <p class="text-lg font-bold text-gray-800 dark:text-gray-100">
                                                <template v-if="today?.lunch_start && today?.lunch_end">{{ today.lunch_start }} – {{ today.lunch_end }}</template>
                                                <template v-else>—</template>
                                            </p>
                                        </div>
                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-white/70 dark:bg-[#1e1e20]/70 px-4 py-3">
                                            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Trabajado</p>
                                            <p class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ hours(today?.worked_minutes ?? 0) }}</p>
                                            <p v-if="today?.expected_minutes > 0" class="text-[11px] text-gray-400">de {{ hours(today.expected_minutes) }}</p>
                                        </div>
                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-white/70 dark:bg-[#1e1e20]/70 px-4 py-3">
                                            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Retardo</p>
                                            <p
                                                class="text-lg font-bold"
                                                :class="today?.late_minutes > 0 && ! today?.late_ignored ? 'text-amber-600' : 'text-gray-800 dark:text-gray-100'"
                                            >
                                                {{ formatMinutes(today?.late_minutes) }}
                                            </p>
                                        </div>
                                        <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-white/70 dark:bg-[#1e1e20]/70 px-4 py-3">
                                            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Tiempo extra</p>
                                            <p class="text-lg font-bold text-emerald-600">{{ hours(today?.overtime_minutes ?? 0) }}</p>
                                        </div>
                                    </div>

                                    <p v-if="today?.notes" class="text-xs text-gray-400 mt-3">{{ today.notes }}</p>
                                </div>

                                <!-- Remote attendance: punch type first, identity afterwards.
                                     The camera turns on only when the face method is in use. -->
                                <div class="xl:col-span-2 bg-gray-50 dark:bg-[#252529]/60 rounded-2xl border border-gray-100 dark:border-[#2b2b2e] p-6">
                                    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                                        <div>
                                            <h3 class="font-semibold text-gray-800 dark:text-gray-200">Registrar asistencia remota</h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Elige el registro y confirma tu identidad para guardarlo.</p>
                                        </div>
                                        <el-tag v-if="identificationTagLabel" type="success" effect="plain" size="small">
                                            {{ identificationTagLabel }}
                                        </el-tag>
                                    </div>

                                    <el-alert
                                        v-if="!canPunch"
                                        type="warning"
                                        :closable="false"
                                        show-icon
                                        class="mb-4"
                                        title="Ya no puedes registrar asistencia"
                                        :description="`Estás dado de baja desde el ${fmtDate(profile.termination_date)}. Tu historial, tus vacaciones y tus recibos siguen disponibles.`"
                                    />

                                    <el-alert
                                        v-else-if="!canRemote"
                                        type="warning"
                                        :closable="false"
                                        show-icon
                                        class="mb-4"
                                        title="Tu asistencia remota no está habilitada"
                                        description="Solicita al administrador que habilite la asistencia remota en tu perfil para registrar desde este dispositivo."
                                    />

                                    <template v-else>
                                        <p class="text-xs uppercase tracking-wider text-gray-400 font-bold mb-2">1 · ¿Qué registro harás?</p>
                                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2.5">
                                            <button
                                                v-for="entry in typeEntries"
                                                :key="entry.value"
                                                type="button"
                                                class="rounded-xl border px-3 py-3.5 text-sm font-semibold transition-colors flex items-center justify-center gap-2"
                                                :class="selectedType === entry.value
                                                    ? 'bg-orange-500 border-orange-400 text-white shadow-sm'
                                                    : 'bg-white dark:bg-[#1e1e20] border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-orange-400'"
                                                @click="selectedType = selectedType === entry.value ? null : entry.value"
                                            >
                                                <el-icon v-if="selectedType === entry.value" :size="16"><CircleCheck /></el-icon>
                                                {{ entry.label }}
                                            </button>
                                        </div>

                                        <div v-if="selectedType" class="mt-5 pt-5 border-t border-dashed border-gray-200 dark:border-gray-700">
                                            <!-- Both methods active: the collaborator chooses one -->
                                            <template v-if="needsMethodChoice">
                                                <p class="text-xs uppercase tracking-wider text-gray-400 font-bold mb-2">2 · ¿Cómo quieres identificarte?</p>
                                                <div class="grid grid-cols-2 gap-2.5 mb-5">
                                                    <button
                                                        type="button"
                                                        class="rounded-xl border px-3 py-3.5 text-sm font-semibold transition-colors flex items-center justify-center gap-2"
                                                        :class="punchMethod === 'face'
                                                            ? 'bg-orange-500 border-orange-400 text-white shadow-sm'
                                                            : 'bg-white dark:bg-[#1e1e20] border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-orange-400'"
                                                        @click="punchMethod = 'face'"
                                                    >
                                                        <el-icon :size="16"><Camera /></el-icon>
                                                        Con mi rostro
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="rounded-xl border px-3 py-3.5 text-sm font-semibold transition-colors flex items-center justify-center gap-2"
                                                        :class="punchMethod === 'pin'
                                                            ? 'bg-orange-500 border-orange-400 text-white shadow-sm'
                                                            : 'bg-white dark:bg-[#1e1e20] border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-orange-400'"
                                                        @click="punchMethod = 'pin'"
                                                    >
                                                        <el-icon :size="16"><Key /></el-icon>
                                                        Con mi PIN
                                                    </button>
                                                </div>
                                            </template>
                                            <p v-else class="text-xs uppercase tracking-wider text-gray-400 font-bold mb-3">2 · Confirma tu identidad</p>

                                            <p v-if="needsMethodChoice && ! punchMethod" class="text-xs text-gray-400">
                                                Elige un método para continuar.
                                            </p>

                                            <!-- Face method -->
                                            <div v-else-if="effectiveMethod === 'face'" class="flex flex-col md:flex-row gap-5">
                                                <div class="w-full max-w-xs shrink-0">
                                                    <div class="rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-black aspect-[4/3] flex items-center justify-center">
                                                        <video
                                                            ref="videoRef"
                                                            autoplay
                                                            muted
                                                            playsinline
                                                            class="w-full h-full object-cover"
                                                            :class="{ hidden: !cameraReady }"
                                                        ></video>
                                                        <div v-if="!cameraReady" class="text-center text-gray-400 px-4 py-6">
                                                            <el-icon :size="30"><Camera /></el-icon>
                                                            <p class="text-xs mt-2">{{ cameraError ? 'Sin cámara disponible' : 'Activando cámara…' }}</p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="flex-1 w-full">
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                                        La foto de tu rostro se verificará antes de guardar el registro.
                                                    </p>
                                                    <p v-if="remoteGeolocationRequired" class="text-xs text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-1">
                                                        <el-icon><Location /></el-icon>
                                                        Se registrará tu ubicación como evidencia del registro.
                                                    </p>

                                                    <p v-if="errorMessage" class="text-sm text-red-600 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-400/30 rounded-lg px-4 py-3 mb-3">
                                                        {{ errorMessage }}
                                                    </p>

                                                    <el-button
                                                        type="primary"
                                                        color="#f26c17"
                                                        size="large"
                                                        class="w-full"
                                                        :loading="submitting"
                                                        @click="submitPunch"
                                                    >
                                                        Registrar {{ selectedTypeLabel?.toLowerCase() }}
                                                    </el-button>
                                                </div>
                                            </div>

                                            <!-- Pin method -->
                                            <div v-else-if="effectiveMethod === 'pin'" class="max-w-md space-y-2">
                                                <p class="text-sm text-gray-600 dark:text-gray-300">Escribe tu PIN de asistencia.</p>
                                                <el-input
                                                    v-model="punchPin"
                                                    size="large"
                                                    maxlength="12"
                                                    show-password
                                                    inputmode="numeric"
                                                    placeholder="Tu PIN"
                                                    @input="onPunchPinInput"
                                                    @keyup.enter="submitPunch"
                                                />
                                                <p class="text-xs text-gray-400">
                                                    Si olvidaste tu PIN, pide ayuda al administrador.
                                                </p>
                                                <p v-if="remoteGeolocationRequired" class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                                    <el-icon><Location /></el-icon>
                                                    Se registrará tu ubicación como evidencia del registro.
                                                </p>

                                                <p v-if="errorMessage" class="text-sm text-red-600 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-400/30 rounded-lg px-4 py-3">
                                                    {{ errorMessage }}
                                                </p>

                                                <el-button
                                                    type="primary"
                                                    color="#f26c17"
                                                    size="large"
                                                    class="w-full"
                                                    :loading="submitting"
                                                    @click="submitPunch"
                                                >
                                                    Registrar con PIN
                                                </el-button>
                                            </div>

                                            <!-- No identification method configured -->
                                            <div v-else class="max-w-md space-y-2">
                                                <p v-if="remoteGeolocationRequired" class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                                    <el-icon><Location /></el-icon>
                                                    Se registrará tu ubicación como evidencia del registro.
                                                </p>

                                                <p v-if="errorMessage" class="text-sm text-red-600 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-400/30 rounded-lg px-4 py-3">
                                                    {{ errorMessage }}
                                                </p>

                                                <el-button
                                                    type="primary"
                                                    color="#f26c17"
                                                    size="large"
                                                    class="w-full"
                                                    :loading="submitting"
                                                    @click="submitPunch"
                                                >
                                                    Registrar {{ selectedTypeLabel?.toLowerCase() }}
                                                </el-button>
                                            </div>
                                        </div>

                                        <p v-else class="text-xs text-gray-500 dark:text-gray-400 mt-3 flex items-center gap-1">
                                            <el-icon v-if="faceVerificationActive"><Camera /></el-icon>
                                            {{
                                                faceVerificationActive
                                                    ? 'La cámara se enciende hasta que elijas el tipo de registro, para no estar activa todo el tiempo.'
                                                    : 'Elige el tipo de registro para continuar.'
                                            }}
                                        </p>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </el-tab-pane>

                    <!-- HISTORIAL -->
                    <el-tab-pane name="history">
                        <template #label>
                            <span class="flex items-center gap-2 px-2">
                                <el-icon><Calendar /></el-icon> Historial
                            </span>
                        </template>

                        <div class="p-6 space-y-6">
                            <!-- Same summary tiles as the payroll detail -->
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529]/60 px-4 py-3">
                                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Días del periodo</p>
                                    <p class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ historySummary.count }}</p>
                                </div>
                                <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529]/60 px-4 py-3">
                                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Tiempo trabajado</p>
                                    <p class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ hours(historySummary.worked) }}</p>
                                </div>
                                <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529]/60 px-4 py-3">
                                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Retardos</p>
                                    <p class="text-lg font-bold" :class="historySummary.late > 0 ? 'text-red-500' : 'text-gray-800 dark:text-gray-100'">
                                        {{ historySummary.late }} min
                                    </p>
                                </div>
                                <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529]/60 px-4 py-3">
                                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Tiempo extra</p>
                                    <p class="text-lg font-bold text-emerald-600">{{ hours(historySummary.overtime) }}</p>
                                </div>
                            </div>

                            <!-- Day by day detail of the current payroll period -->
                            <div>
                                <div class="mb-3">
                                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">Periodo de nómina en curso</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ fmtDate(historyRange?.start_date) }} — {{ fmtDate(historyRange?.end_date) }} · el historial cambia automáticamente cada lunes.
                                    </p>
                                </div>

                                <div class="my-days-card rounded-2xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                                    <el-table
                                        :data="periodDays"
                                        :span-method="daySpanMethod"
                                        class="my-days-table"
                                        style="width: 100%"
                                    >
                                    <el-table-column label="Día" min-width="130">
                                        <template #default="scope">
                                            <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">
                                                {{ scope.row.date_label.split(' ')[0] }}
                                            </p>
                                            <p class="font-semibold text-gray-800 dark:text-gray-100">{{ scope.row.date_label.split(' ')[1] }}</p>
                                            <p v-if="scope.row.shift_name" class="text-xs text-gray-400 mt-0.5">{{ scope.row.shift_name }}</p>
                                            <p v-if="scope.row.notes" class="text-xs text-gray-400 mt-0.5">{{ scope.row.notes }}</p>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Entrada" min-width="105" align="center">
                                        <template #default="scope">
                                            <el-tag
                                                v-if="isPillRow(scope.row)"
                                                :type="dayTagType(scope.row)"
                                                effect="light"
                                                size="large"
                                                class="day-status-pill"
                                            >
                                                {{ scope.row.status_label }}
                                            </el-tag>
                                            <template v-else-if="scope.row.first_in">
                                                <p class="font-medium text-gray-700 dark:text-gray-200">{{ scope.row.first_in }}</p>
                                                <p
                                                    v-if="scope.row.late_minutes > 0"
                                                    class="text-xs mt-0.5"
                                                    :class="scope.row.late_ignored ? 'text-gray-400 line-through' : 'text-red-500'"
                                                    :title="scope.row.late_ignored ? 'Retardo ignorado: no se descuenta del pago' : 'Minutos de retardo después de la tolerancia'"
                                                >
                                                    {{ scope.row.late_minutes }} min tarde
                                                </p>
                                            </template>
                                            <span v-else class="text-gray-400">—</span>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Salida" min-width="90" align="center">
                                        <template #default="scope">
                                            <p v-if="scope.row.last_out" class="font-medium text-gray-700 dark:text-gray-200">{{ scope.row.last_out }}</p>
                                            <span v-else class="text-gray-400">—</span>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Tiempo de comida" min-width="120" align="center">
                                        <template #default="scope">
                                            <el-tooltip
                                                v-if="lunchMinutes(scope.row) !== null"
                                                :content="`${scope.row.lunch_start} – ${scope.row.lunch_end}`"
                                                placement="top"
                                            >
                                                <p class="font-medium text-gray-700 dark:text-gray-200">{{ hours(lunchMinutes(scope.row)) }}</p>
                                            </el-tooltip>
                                            <span v-else class="text-gray-400">—</span>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Tiempo trabajado" min-width="125" align="center">
                                        <template #default="scope">
                                            <p class="font-semibold text-gray-800 dark:text-gray-100">{{ hours(scope.row.worked_minutes) }}</p>
                                            <p v-if="scope.row.expected_minutes > 0" class="text-xs text-gray-400 mt-0.5">
                                                de {{ hours(scope.row.expected_minutes) }}
                                            </p>
                                            <el-button
                                                v-if="(scope.row.punches || []).length > 0"
                                                type="primary"
                                                link
                                                :icon="Clock"
                                                class="!text-[#f26c17] !font-semibold mt-1"
                                                @click="openRecords(scope.row)"
                                            >
                                                {{ (scope.row.punches || []).length }} registro(s)
                                            </el-button>
                                            <p v-else class="text-xs text-gray-400 mt-0.5">Sin registros</p>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Extra" min-width="85" align="center">
                                        <template #default="scope">
                                            <p v-if="scope.row.overtime_minutes > 0" class="font-semibold text-emerald-600">
                                                {{ hours(scope.row.overtime_minutes) }}
                                            </p>
                                            <span v-else class="text-gray-400">—</span>
                                        </template>
                                    </el-table-column>
                                    </el-table>
                                </div>
                            </div>
                        </div>
                    </el-tab-pane>

                    <!-- VACACIONES -->
                    <el-tab-pane name="vacations">
                        <template #label>
                            <span class="flex items-center gap-2 px-2">
                                <el-icon><Calendar /></el-icon> Vacaciones
                            </span>
                        </template>

                        <!-- Read-only balance, periods, ledger and requests; the
                             only action for the collaborator is requesting days. -->
                        <MyVacationPanel
                            :balance="vacationBalance"
                            :periods="vacationPeriods"
                            :movements="vacationMovements"
                            :requests="vacationRequests"
                            :minimum-days="vacationMinDays"
                            @changed="reloadVacation"
                        />
                    </el-tab-pane>

                    <!-- RECIBOS -->
                    <el-tab-pane name="payslips">
                        <template #label>
                            <span class="flex items-center gap-2 px-2">
                                <el-icon><Document /></el-icon> Mis recibos
                            </span>
                        </template>

                        <div class="p-6 space-y-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">Mis recibos</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Recibos generados al cerrar cada periodo de nómina.</p>
                                </div>
                                <el-tag v-if="payslips.length" type="info" effect="plain" round>
                                    {{ payslips.length }} recibo(s)
                                </el-tag>
                            </div>

                            <div v-if="payslips.length" class="my-payslips-card rounded-2xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                                <el-table :data="payslips" style="width: 100%">
                                    <el-table-column label="Periodo" min-width="230">
                                        <template #default="scope">
                                            <p class="font-semibold text-gray-800 dark:text-gray-100">{{ scope.row.period_label }}</p>
                                            <p class="text-xs text-gray-400 mt-0.5">Generado {{ scope.row.generated_at || '—' }}</p>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Días pagados" width="120" align="center">
                                        <template #default="scope">
                                            <span class="font-medium text-gray-700 dark:text-gray-200">{{ scope.row.days_paid }}</span>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Percepciones" width="130" align="right">
                                        <template #default="scope">
                                            <span class="text-gray-700 dark:text-gray-200">{{ money(scope.row.total_gross) }}</span>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Deducciones" width="130" align="right">
                                        <template #default="scope">
                                            <span :class="Number(scope.row.total_deductions) > 0 ? 'text-red-500' : 'text-gray-400'">
                                                {{ money(scope.row.total_deductions) }}
                                            </span>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Neto" width="140" align="right">
                                        <template #default="scope">
                                            <span class="font-bold text-emerald-600">{{ money(scope.row.total_net) }}</span>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="" width="130" align="center">
                                        <template #default="scope">
                                            <el-button type="primary" size="small" plain @click="printPayslip(scope.row)">
                                                Ver recibo
                                            </el-button>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </div>

                            <el-empty v-else description="Aún no tienes recibos de nómina" :image-size="90" />
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </div>

        <!-- Day records of the history table (read only) -->
        <DayRecordsDialog v-model="recordsDialog" :day="recordsDay" />

        <!-- Today's records: modern read-only summary without photos or map -->
        <DayRecordsDialog v-model="todayDialog" :day="today" :show-map="false" />

        <!-- Punch result -->
        <div v-if="result" class="fixed bottom-6 right-6 bg-white dark:bg-[#1e1e20] border border-emerald-300 dark:border-emerald-500/40 shadow-lg rounded-2xl px-6 py-4 flex items-center gap-4 z-50">
            <el-icon :size="36" class="text-emerald-500"><CircleCheck /></el-icon>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100">
                    {{ result.type_label }} registrada a las {{ result.punched_at }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    <template v-if="result.similarity">Rostro verificado · {{ result.similarity }}% · </template>
                    <template v-if="result.suggested_next_label">Siguiente sugerido: {{ result.suggested_next_label }}</template>
                </p>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.my-days-table :deep(.el-table__cell) {
    vertical-align: top;
    padding-top: 12px;
    padding-bottom: 12px;
}

.my-days-table :deep(.el-table__header) .el-table__cell {
    padding-top: 8px;
    padding-bottom: 8px;
}

/* Full-width status pill of days without punches. */
.day-status-pill {
    width: 100%;
    height: 34px;
    justify-content: center;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
}

/* The table has a fixed minimum width, so on narrow screens it overflows
   horizontally and Element Plus paints its own scrollbar bars inside the
   table. The table keeps its native scrolling, we only remove that paint. */
.my-days-table :deep(.el-scrollbar__bar) {
    display: none;
}

/* The days table blends with its rounded card: same background as the page
   instead of the dark block Element Plus paints. */
.my-days-card :deep(.el-table) {
    --el-table-bg-color: transparent;
    --el-table-tr-bg-color: transparent;
    --el-table-row-hover-bg-color: rgba(242, 108, 23, 0.06);
}

.my-days-card :deep(.el-table th.el-table__cell) {
    background-color: transparent;
    font-size: 12px;
    color: var(--el-text-color-secondary);
}

/* The payslips table blends with its rounded card: same background as the
   rest of the page instead of the dark block Element Plus paints. */
.my-payslips-card :deep(.el-table) {
    --el-table-bg-color: transparent;
    --el-table-tr-bg-color: transparent;
    --el-table-row-hover-bg-color: rgba(242, 108, 23, 0.06);
}

.my-payslips-card :deep(.el-table th.el-table__cell) {
    background-color: transparent;
    font-size: 12px;
    color: var(--el-text-color-secondary);
}
</style>
