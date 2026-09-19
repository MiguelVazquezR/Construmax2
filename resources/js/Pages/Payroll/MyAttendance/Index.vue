<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FaceEnrollmentDialog from '@/Components/Payroll/FaceEnrollmentDialog.vue';
import { ElMessageBox } from 'element-plus';
import { Camera, Calendar, CircleCheck, Document, Location, User } from '@element-plus/icons-vue';
import { useFlashMessages } from '@/Composables/useFlashMessages';
import axios from 'axios';

const props = defineProps({
    profile: Object,
    today: Object,
    suggestedNextType: String,
    recentDays: Array,
    punches: Array,
    vacationBalance: Object,
    vacationRequests: Array,
    payslips: Array,
    punchTypes: Object,
    vacationMinDays: Number,
    remoteGeolocationRequired: Boolean,
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
const faceDialogVisible = ref(false);

const videoRef = ref(null);
let stream = null;
let clockTimer = null;
let resultTimer = null;

const typeEntries = computed(() =>
    Object.entries(props.punchTypes || {}).map(([value, label]) => ({ value, label }))
);

const canRemote = computed(() => props.profile?.can_remote_attendance === true);
const faceVerificationActive = computed(
    () => props.faceRecognition?.enabled === true && props.faceRecognition?.configured === true
);

const timeLabel = computed(() =>
    clock.value.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
);

const dateLabel = computed(() =>
    clock.value.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' })
);

const workState = computed(() => {
    if (props.today?.is_paused) return { label: 'En descanso', type: 'warning' };
    if (props.today?.is_working) return { label: 'En turno', type: 'success' };
    if (props.today?.status === 'present') return { label: 'Jornada terminada', type: 'info' };
    return { label: 'Fuera de turno', type: 'info' };
});

const suggestedNextLabel = computed(() =>
    props.suggestedNextType ? props.punchTypes?.[props.suggestedNextType] : null
);

const vacationSeasons = computed(() => {
    const seasons = props.vacationBalance?.seasons;

    return seasons ? Object.values(seasons).sort((a, b) => b.season - a.season) : [];
});

const formatMinutes = (minutes) => {
    const value = Number(minutes || 0);
    const hours = Math.floor(value / 60);
    const rest = value % 60;

    if (hours === 0) return `${rest} min`;
    return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`;
};

const fmtDate = (value) => {
    if (!value) return '-';
    return new Date(`${value}T00:00:00`).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const initCamera = async () => {
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { width: 640, height: 480, facingMode: 'user' },
            audio: false,
        });

        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            cameraReady.value = true;
        }
    } catch {
        cameraReady.value = false;
    }
};

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
        errorMessage.value = 'Selecciona el tipo de marcaje.';
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

        const photo = capturePhoto();

        if (faceVerificationActive.value && !photo) {
            errorMessage.value = 'Se requiere la cámara para verificar tu rostro.';
            return;
        }

        const { data } = await axios.post(route('payroll.my-attendance.punch'), {
            type: selectedType.value,
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

        router.reload({ only: ['today', 'recentDays', 'punches', 'suggestedNextType'] });
    } catch (error) {
        const errors = error.response?.data?.errors;
        errorMessage.value = errors
            ? Object.values(errors).flat()[0]
            : 'No se pudo registrar el marcaje. Intenta de nuevo.';
    } finally {
        submitting.value = false;
    }
};

// Vacation requests
const vacationDialogVisible = ref(false);
const vacationForm = useForm({
    start_date: '',
    end_date: '',
    reason: '',
});

const openVacationDialog = () => {
    vacationForm.reset();
    vacationForm.clearErrors();
    vacationDialogVisible.value = true;
};

const submitVacation = () => {
    vacationForm.post(route('payroll.vacations.requests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            vacationDialogVisible.value = false;
            router.reload({ only: ['vacationBalance', 'vacationRequests'] });
        },
    });
};

const cancelVacation = (id) => {
    ElMessageBox.confirm('La solicitud pendiente será cancelada.', 'Cancelar solicitud', {
        confirmButtonText: 'Cancelar solicitud',
        cancelButtonText: 'Conservar',
        type: 'warning',
    })
        .then(() => {
            router.delete(route('payroll.vacations.requests.cancel', id), {
                preserveScroll: true,
                onSuccess: () => router.reload({ only: ['vacationBalance', 'vacationRequests'] }),
            });
        })
        .catch(() => {});
};

const vacationStatusTag = (status) => {
    const map = {
        pending: 'warning',
        approved: 'success',
        rejected: 'danger',
        cancelled: 'info',
    };

    return map[status] || 'info';
};

const printPayslip = (row) => {
    window.open(
        route('payroll.periods.payslips.print', { period: row.period_id, users: [page.props.auth.user.id] }),
        '_blank'
    );
};

onMounted(async () => {
    clockTimer = setInterval(() => {
        clock.value = new Date();
    }, 1000);

    await initCamera();
});

onBeforeUnmount(() => {
    clearInterval(clockTimer);
    clearTimeout(resultTimer);

    if (stream) {
        stream.getTracks().forEach((track) => track.stop());
    }
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

                        <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Today summary -->
                            <div class="bg-gray-50 dark:bg-[#252529]/60 rounded-xl border border-gray-100 dark:border-gray-800 p-6 flex flex-col">
                                <div class="text-center mb-4">
                                    <p class="text-4xl font-bold tabular-nums text-gray-800 dark:text-white">{{ timeLabel }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 capitalize">{{ dateLabel }}</p>
                                </div>

                                <div class="flex items-center justify-center gap-2 mb-6">
                                    <el-tag :type="workState.type" effect="dark" class="rounded-full">{{ workState.label }}</el-tag>
                                    <el-tag v-if="today?.shift_name" type="info" effect="plain" class="rounded-full">
                                        Turno: {{ today.shift_name }}
                                    </el-tag>
                                </div>

                                <el-descriptions :column="2" border size="small">
                                    <el-descriptions-item label="Entrada">{{ today?.first_in || '-' }}</el-descriptions-item>
                                    <el-descriptions-item label="Salida">{{ today?.last_out || '-' }}</el-descriptions-item>
                                    <el-descriptions-item label="Comida">
                                        {{ today?.lunch_start || '-' }} - {{ today?.lunch_end || '-' }}
                                    </el-descriptions-item>
                                    <el-descriptions-item label="Trabajado">{{ formatMinutes(today?.worked_minutes) }}</el-descriptions-item>
                                    <el-descriptions-item label="Retardo">
                                        <span :class="{ 'text-amber-600 font-semibold': today?.late_minutes > 0 }">
                                            {{ formatMinutes(today?.late_minutes) }}
                                        </span>
                                    </el-descriptions-item>
                                    <el-descriptions-item label="Tiempo extra">{{ formatMinutes(today?.overtime_minutes) }}</el-descriptions-item>
                                    <el-descriptions-item label="Objetivo">{{ formatMinutes(today?.expected_minutes) }}</el-descriptions-item>
                                    <el-descriptions-item label="Estado">{{ today?.status_label }}</el-descriptions-item>
                                </el-descriptions>

                                <div v-if="suggestedNextLabel" class="mt-4 text-sm text-center text-gray-500 dark:text-gray-400">
                                    Siguiente marcaje sugerido:
                                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ suggestedNextLabel }}</span>
                                </div>
                            </div>

                            <!-- Punch -->
                            <div class="lg:col-span-2 bg-gray-50 dark:bg-[#252529]/60 rounded-xl border border-gray-100 dark:border-gray-800 p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">Registrar marcaje remoto</h3>
                                    <el-tag v-if="faceVerificationActive" type="success" effect="plain" size="small">
                                        Verificación facial activa
                                    </el-tag>
                                </div>

                                <el-alert
                                    v-if="!canRemote"
                                    type="warning"
                                    :closable="false"
                                    show-icon
                                    class="mb-4"
                                    title="Tu asistencia remota no está habilitada"
                                    description="Solicita al administrador que habilite la asistencia remota en tu perfil para marcar desde este dispositivo."
                                />

                                <template v-else>
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
                                        <button
                                            v-for="entry in typeEntries"
                                            :key="entry.value"
                                            type="button"
                                            class="rounded-xl border px-3 py-4 text-sm font-semibold transition-colors"
                                            :class="selectedType === entry.value
                                                ? 'bg-orange-500 border-orange-400 text-white'
                                                : 'bg-white dark:bg-[#1e1e20] border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-orange-400'"
                                            @click="selectedType = entry.value"
                                        >
                                            {{ entry.label }}
                                        </button>
                                    </div>

                                    <div class="flex flex-col md:flex-row gap-4 items-start">
                                        <div class="flex-1">
                                            <div class="rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-black aspect-[4/3] max-w-xs flex items-center justify-center">
                                                <video
                                                    ref="videoRef"
                                                    autoplay
                                                    muted
                                                    playsinline
                                                    class="w-full h-full object-cover"
                                                    :class="{ hidden: !cameraReady }"
                                                ></video>
                                                <div v-if="!cameraReady" class="text-center text-gray-400 px-4 py-8">
                                                    <el-icon :size="32"><Camera /></el-icon>
                                                    <p class="text-xs mt-2">Sin cámara disponible</p>
                                                </div>
                                            </div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                                <template v-if="faceVerificationActive">
                                                    La foto de tu rostro se verificará antes de registrar el marcaje.
                                                </template>
                                                <template v-else>
                                                    La cámara no es obligatoria, pero mejora la evidencia del marcaje.
                                                </template>
                                            </p>
                                        </div>

                                        <div class="flex-1 w-full">
                                            <p v-if="remoteGeolocationRequired" class="text-xs text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-1">
                                                <el-icon><Location /></el-icon>
                                                Se registrará tu ubicación como evidencia del marcaje.
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
                                                Registrar marcaje
                                            </el-button>
                                        </div>
                                    </div>
                                </template>
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

                        <div class="p-6 space-y-8">
                            <div>
                                <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Últimos 7 días</h3>
                                <el-table :data="recentDays" stripe>
                                    <el-table-column label="Fecha" prop="date_label" width="120" />
                                    <el-table-column label="Estado" width="150">
                                        <template #default="scope">
                                            <el-tag size="small" effect="plain">{{ scope.row.status_label }}</el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="Entrada" prop="first_in" width="90" />
                                    <el-table-column label="Salida" prop="last_out" width="90" />
                                    <el-table-column label="Trabajado" width="120">
                                        <template #default="scope">{{ formatMinutes(scope.row.worked_minutes) }}</template>
                                    </el-table-column>
                                    <el-table-column label="Retardo" width="110">
                                        <template #default="scope">{{ formatMinutes(scope.row.late_minutes) }}</template>
                                    </el-table-column>
                                    <el-table-column label="Extra" width="110">
                                        <template #default="scope">{{ formatMinutes(scope.row.overtime_minutes) }}</template>
                                    </el-table-column>
                                    <el-table-column label="Turno" prop="shift_name" min-width="140" />
                                </el-table>
                            </div>

                            <div>
                                <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Marcajes recientes</h3>
                                <el-table :data="punches" stripe>
                                    <el-table-column label="Fecha y hora" prop="punched_at" width="160" />
                                    <el-table-column label="Marcaje" width="160">
                                        <template #default="scope">
                                            <el-tag size="small" type="info" effect="plain">{{ scope.row.type_label }}</el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="Origen" width="110">
                                        <template #default="scope">{{ scope.row.source_label }}</template>
                                    </el-table-column>
                                    <el-table-column label="Ubicación" min-width="140">
                                        <template #default="scope">
                                            <a
                                                v-if="scope.row.latitude && scope.row.longitude"
                                                :href="`https://www.google.com/maps?q=${scope.row.latitude},${scope.row.longitude}`"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-blue-600 hover:underline"
                                            >
                                                Ver en Google Maps
                                            </a>
                                            <span v-else class="text-gray-400 italic">Sin registro</span>
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="Evidencia" width="110">
                                        <template #default="scope">
                                            <el-image
                                                v-if="scope.row.capture_url"
                                                :src="scope.row.capture_url"
                                                :preview-src-list="[scope.row.capture_url]"
                                                fit="cover"
                                                class="w-10 h-10 rounded"
                                                preview-teleported
                                            />
                                            <span v-else class="text-gray-400 italic">Sin foto</span>
                                        </template>
                                    </el-table-column>
                                </el-table>
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

                        <div class="p-6 space-y-8">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-[#252529]/60 p-5 text-center">
                                    <p class="text-xs uppercase tracking-wider text-gray-400 mb-1">Disponibles</p>
                                    <p class="text-3xl font-bold text-emerald-600">{{ vacationBalance?.available_days ?? 0 }}</p>
                                    <p class="text-xs text-gray-400 mt-1">días</p>
                                </div>
                                <div class="rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-[#252529]/60 p-5 text-center">
                                    <p class="text-xs uppercase tracking-wider text-gray-400 mb-1">Devengados</p>
                                    <p class="text-3xl font-bold text-gray-700 dark:text-gray-200">{{ vacationBalance?.accrued_days ?? 0 }}</p>
                                    <p class="text-xs text-gray-400 mt-1">días</p>
                                </div>
                                <div class="rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-[#252529]/60 p-5 text-center">
                                    <p class="text-xs uppercase tracking-wider text-gray-400 mb-1">Tomados</p>
                                    <p class="text-3xl font-bold text-blue-600">{{ vacationBalance?.taken_days ?? 0 }}</p>
                                    <p class="text-xs text-gray-400 mt-1">días</p>
                                </div>
                                <div class="rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-[#252529]/60 p-5 text-center">
                                    <p class="text-xs uppercase tracking-wider text-gray-400 mb-1">En solicitud</p>
                                    <p class="text-3xl font-bold text-amber-600">{{ vacationBalance?.pending_days ?? 0 }}</p>
                                    <p class="text-xs text-gray-400 mt-1">días</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">Mis solicitudes</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Solicitud mínima de {{ vacationMinDays || 1 }} día(s) · temporada actual: año {{ vacationBalance?.current_season ?? 1 }}
                                    </p>
                                </div>
                                <el-button type="primary" color="#f26c17" :icon="Calendar" @click="openVacationDialog">
                                    Solicitar vacaciones
                                </el-button>
                            </div>

                            <el-table :data="vacationRequests" stripe>
                                <el-table-column label="Inicio" width="120">
                                    <template #default="scope">{{ fmtDate(scope.row.start_date) }}</template>
                                </el-table-column>
                                <el-table-column label="Fin" width="120">
                                    <template #default="scope">{{ fmtDate(scope.row.end_date) }}</template>
                                </el-table-column>
                                <el-table-column label="Días" prop="days" width="80" />
                                <el-table-column label="Estado" width="130">
                                    <template #default="scope">
                                        <el-tag size="small" :type="vacationStatusTag(scope.row.status)" effect="plain">
                                            {{ scope.row.status_label }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Comentarios" prop="review_notes" min-width="180" />
                                <el-table-column label="Acciones" width="130" align="center">
                                    <template #default="scope">
                                        <el-button
                                            v-if="scope.row.is_pending"
                                            type="danger"
                                            size="small"
                                            plain
                                            @click="cancelVacation(scope.row.id)"
                                        >
                                            Cancelar
                                        </el-button>
                                    </template>
                                </el-table-column>
                            </el-table>

                            <div>
                                <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Saldos por temporada</h3>
                                <el-table :data="vacationSeasons" stripe>
                                    <el-table-column label="Temporada" width="110">
                                        <template #default="scope">Año {{ scope.row.season }}</template>
                                    </el-table-column>
                                    <el-table-column label="Periodo" min-width="200">
                                        <template #default="scope">
                                            {{ fmtDate(scope.row.start) }} - {{ fmtDate(scope.row.end) }}
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="Derecho" prop="entitled" width="90" />
                                    <el-table-column label="Devengado" prop="accrued" width="100" />
                                    <el-table-column label="Tomado" prop="taken" width="90" />
                                    <el-table-column label="Disponible" width="110">
                                        <template #default="scope">
                                            <span class="font-semibold">{{ scope.row.available }}</span>
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="Vence" width="120">
                                        <template #default="scope">{{ fmtDate(scope.row.expiry_date) }}</template>
                                    </el-table-column>
                                </el-table>
                            </div>
                        </div>
                    </el-tab-pane>

                    <!-- RECIBOS -->
                    <el-tab-pane name="payslips">
                        <template #label>
                            <span class="flex items-center gap-2 px-2">
                                <el-icon><Document /></el-icon> Mis recibos
                            </span>
                        </template>

                        <div class="p-6">
                            <el-table :data="payslips" stripe>
                                <el-table-column label="Periodo" prop="period_label" min-width="200" />
                                <el-table-column label="Días pagados" prop="days_paid" width="120" />
                                <el-table-column label="Neto" width="140">
                                    <template #default="scope">
                                        <span class="font-semibold">${{ Number(scope.row.total_net).toFixed(2) }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Generado" prop="generated_at" width="160" />
                                <el-table-column label="Acciones" width="130" align="center">
                                    <template #default="scope">
                                        <el-button type="primary" size="small" plain @click="printPayslip(scope.row)">
                                            Ver recibo
                                        </el-button>
                                    </template>
                                </el-table-column>
                            </el-table>

                            <el-empty v-if="!payslips || payslips.length === 0" description="Aún no tienes recibos de nómina" :image-size="90" />
                        </div>
                    </el-tab-pane>

                    <!-- MI ROSTRO -->
                    <el-tab-pane name="face">
                        <template #label>
                            <span class="flex items-center gap-2 px-2">
                                <el-icon><Camera /></el-icon> Mi rostro
                            </span>
                        </template>

                        <div class="p-6 max-w-2xl">
                            <div class="flex items-center justify-between gap-4 bg-gray-50 dark:bg-[#252529]/60 rounded-xl border border-gray-100 dark:border-gray-800 px-5 py-4">
                                <div>
                                    <p class="font-semibold text-gray-800 dark:text-gray-200">
                                        Registro facial para el kiosco y la asistencia remota
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        <template v-if="faceRecognition?.activeCount > 0">
                                            Tienes {{ faceRecognition.activeCount }} foto(s) registradas. Puedes actualizarlas cuando lo necesites.
                                        </template>
                                        <template v-else>
                                            Aún no has registrado tu rostro.
                                        </template>
                                    </p>
                                </div>
                                <el-button type="primary" plain :icon="Camera" @click="faceDialogVisible = true">
                                    {{ faceRecognition?.activeCount > 0 ? 'Actualizar rostro' : 'Registrar mi rostro' }}
                                </el-button>
                            </div>

                            <el-alert
                                v-if="!faceRecognition?.configured"
                                type="warning"
                                :closable="false"
                                show-icon
                                class="mt-4"
                                title="El reconocimiento facial aún no está disponible"
                                description="El administrador debe configurar las credenciales de AWS para habilitarlo."
                            />
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </div>

        <!-- Vacation request dialog -->
        <el-dialog v-model="vacationDialogVisible" title="Solicitar vacaciones" width="480px">
            <el-form :model="vacationForm" label-position="top">
                <el-form-item label="Fecha de inicio" :error="vacationForm.errors.start_date">
                    <el-date-picker v-model="vacationForm.start_date" type="date" class="w-full" value-format="YYYY-MM-DD" />
                </el-form-item>
                <el-form-item label="Fecha final" :error="vacationForm.errors.end_date">
                    <el-date-picker v-model="vacationForm.end_date" type="date" class="w-full" value-format="YYYY-MM-DD" />
                </el-form-item>
                <el-form-item label="Motivo (opcional)" :error="vacationForm.errors.reason">
                    <el-input v-model="vacationForm.reason" type="textarea" :rows="2" maxlength="255" show-word-limit />
                </el-form-item>
            </el-form>

            <el-alert
                v-if="Object.keys(vacationForm.errors).length > 0"
                type="error"
                :closable="false"
                show-icon
                class="mb-2"
                :title="Object.values(vacationForm.errors)[0]"
            />

            <template #footer>
                <el-button @click="vacationDialogVisible = false">Cancelar</el-button>
                <el-button type="primary" color="#f26c17" :loading="vacationForm.processing" @click="submitVacation">
                    Enviar solicitud
                </el-button>
            </template>
        </el-dialog>

        <!-- Face enrollment -->
        <FaceEnrollmentDialog
            v-model="faceDialogVisible"
            :user="page.props.auth.user"
            :configured="faceRecognition?.configured === true"
            :active-count="faceRecognition?.activeCount || 0"
            self-service
            @saved="router.reload({ only: ['faceRecognition'] })"
        />

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
