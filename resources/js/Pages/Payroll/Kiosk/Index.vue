<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Camera, CircleCheck, WarningFilled } from '@element-plus/icons-vue';
import axios from 'axios';

const props = defineProps({
    punchTypes: Object,
    faceRecognitionEnabled: Boolean,
    appName: String,
});

const TOKEN_KEY = 'attendance_device_token';
const SESSION_RESET_MS = 7000;

const token = ref(null);
const device = ref(null);
const status = ref('checking'); // checking | authorized | unauthorized
const clock = ref(new Date());

const selectedType = ref(null);
const faceSubmitting = ref(false);
const errorMessage = ref('');
const result = ref(null);
const cameraReady = ref(false);

const videoRef = ref(null);
let stream = null;
let clockTimer = null;
let resultTimer = null;

const typeEntries = computed(() =>
    Object.entries(props.punchTypes || {}).map(([value, label]) => ({ value, label }))
);

const timeLabel = computed(() =>
    clock.value.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
);

const dateLabel = computed(() =>
    clock.value.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
);

const bootstrap = async () => {
    token.value = localStorage.getItem(TOKEN_KEY);

    if (!token.value) {
        status.value = 'unauthorized';
        return;
    }

    try {
        const { data } = await axios.post(route('attendance.kiosk.bootstrap'), {}, {
            headers: { 'X-Attendance-Device': token.value },
        });

        device.value = data.device;
        status.value = 'authorized';
    } catch {
        localStorage.removeItem(TOKEN_KEY);
        token.value = null;
        status.value = 'unauthorized';
    }
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

const submitFace = async () => {
    errorMessage.value = '';

    if (!selectedType.value) {
        errorMessage.value = 'Selecciona el tipo de registro.';
        return;
    }

    const photo = capturePhoto();

    if (!photo) {
        errorMessage.value = 'La cámara no está disponible en este equipo.';
        return;
    }

    faceSubmitting.value = true;

    try {
        const { data } = await axios.post(route('attendance.kiosk.face-punch'), {
            type: selectedType.value,
            photo,
        }, {
            headers: { 'X-Attendance-Device': token.value },
        });

        result.value = data;
        selectedType.value = null;

        clearTimeout(resultTimer);
        resultTimer = setTimeout(() => {
            result.value = null;
        }, SESSION_RESET_MS);
    } catch (error) {
        const errors = error.response?.data?.errors;
        errorMessage.value = errors
            ? Object.values(errors).flat()[0]
            : 'No se pudo guardar el registro. Intenta de nuevo.';
    } finally {
        faceSubmitting.value = false;
    }
};

onMounted(async () => {
    clockTimer = setInterval(() => {
        clock.value = new Date();
    }, 1000);

    await bootstrap();

    if (status.value === 'authorized') {
        await initCamera();
    }
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
    <Head title="Kiosco de asistencia" />

    <div class="min-h-screen bg-slate-900 text-white flex flex-col">
        <!-- Top bar -->
        <header class="flex items-center justify-between px-8 py-4 border-b border-white/10">
            <div class="flex items-center gap-3">
                <span class="text-lg font-bold tracking-tight">{{ appName }}</span>
                <span class="text-xs uppercase tracking-widest text-slate-400">Kiosco de asistencia</span>
            </div>
            <div v-if="device" class="flex items-center gap-2 text-sm text-slate-300">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                {{ device.name }}<span v-if="device.location"> · {{ device.location }}</span>
            </div>
        </header>

        <!-- Checking -->
        <main v-if="status === 'checking'" class="flex-1 flex items-center justify-center">
            <p class="text-slate-300">Verificando dispositivo...</p>
        </main>

        <!-- Unauthorized -->
        <main v-else-if="status === 'unauthorized'" class="flex-1 flex items-center justify-center px-6">
            <div class="max-w-xl text-center bg-white/5 border border-white/10 rounded-2xl p-10">
                <el-icon :size="52" class="text-amber-400"><WarningFilled /></el-icon>
                <h1 class="text-2xl font-bold mt-4">Dispositivo no autorizado</h1>
                <p class="text-slate-300 mt-3">
                    Este equipo no está registrado para usar el kiosco de asistencia.
                </p>
                <p class="text-slate-400 text-sm mt-4">
                    Pide al administrador que abra <span class="font-semibold text-slate-200">Recursos Humanos → Dispositivos de asistencia</span>
                    en este mismo equipo y use la opción "Registrar este dispositivo".
                </p>
            </div>
        </main>

        <!-- Kiosk -->
        <main v-else class="flex-1 grid grid-cols-1 lg:grid-cols-5 gap-8 px-8 py-8">
            <!-- Left: clock + form -->
            <section class="lg:col-span-3 flex flex-col">
                <div class="text-center mb-8">
                    <p class="text-6xl font-bold tabular-nums tracking-tight">{{ timeLabel }}</p>
                    <p class="text-slate-400 capitalize mt-2">{{ dateLabel }}</p>
                </div>

                <div class="bg-white/5 border border-white/10 rounded-2xl p-8 flex-1 flex flex-col">
                    <h2 class="text-xl font-semibold mb-6">Registra tu asistencia</h2>

                    <!-- Punch type -->
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
                        <button
                            v-for="entry in typeEntries"
                            :key="entry.value"
                            type="button"
                            class="rounded-xl border px-4 py-4 text-sm font-semibold transition-colors"
                            :class="selectedType === entry.value
                                ? 'bg-orange-500 border-orange-400 text-white'
                                : 'bg-white/5 border-white/10 text-slate-200 hover:bg-white/10'"
                            @click="selectedType = entry.value"
                        >
                            {{ entry.label }}
                        </button>
                    </div>

                    <!-- Identification -->
                    <p v-if="errorMessage" class="text-sm text-red-300 bg-red-500/10 border border-red-400/30 rounded-lg px-4 py-3 mb-6">
                        {{ errorMessage }}
                    </p>

                    <div class="mt-auto">
                        <el-button
                            v-if="faceRecognitionEnabled"
                            type="success"
                            size="large"
                            class="!h-14 !text-base w-full"
                            :disabled="!cameraReady"
                            :loading="faceSubmitting"
                            @click="submitFace"
                        >
                            Registrar con rostro
                        </el-button>
                    </div>

                    <p class="text-xs text-slate-500 mt-4">
                        <template v-if="!faceRecognitionEnabled">
                            El reconocimiento facial no está activo en este kiosco. Pide al administrador que lo habilite en Configuración de nómina.
                        </template>
                        <template v-else-if="!cameraReady">
                            Se requiere la cámara para identificar tu rostro. Pide ayuda al administrador.
                        </template>
                        <template v-else>
                            Presiona "Registrar con rostro" y mira de frente a la cámara.
                        </template>
                    </p>
                </div>
            </section>

            <!-- Right: camera -->
            <section class="lg:col-span-2 flex flex-col gap-4">
                <div class="relative rounded-2xl overflow-hidden border border-white/10 bg-black aspect-[4/3] flex items-center justify-center">
                    <video
                        ref="videoRef"
                        autoplay
                        muted
                        playsinline
                        class="w-full h-full object-cover"
                        :class="{ hidden: !cameraReady }"
                    ></video>
                    <div v-if="!cameraReady" class="text-center text-slate-400 px-6">
                        <el-icon :size="40"><Camera /></el-icon>
                        <p class="text-sm mt-3">Sin cámara disponible</p>
                    </div>
                </div>

                <div v-if="faceRecognitionEnabled" class="bg-emerald-500/10 border border-emerald-400/30 rounded-xl px-4 py-3 text-sm text-emerald-200">
                    Reconocimiento facial activo: presiona "Registrar con rostro" para identificarte.
                </div>

                <p v-else class="bg-red-500/10 border border-red-400/30 rounded-xl px-4 py-3 text-sm text-red-200">
                    El reconocimiento facial no está habilitado en este kiosco. Pide al administrador que lo active en Configuración de nómina.
                </p>
            </section>
        </main>

        <!-- Success overlay -->
        <div v-if="result" class="fixed inset-0 bg-slate-950/85 backdrop-blur-sm flex items-center justify-center z-50 px-6">
            <div class="bg-white/10 border border-emerald-400/40 rounded-3xl p-12 text-center max-w-lg w-full">
                <el-icon :size="64" class="text-emerald-400"><CircleCheck /></el-icon>
                <h2 class="text-3xl font-bold mt-4">{{ result.user_name }}</h2>
                <p class="text-emerald-300 text-lg mt-2">{{ result.type_label }} · {{ result.punched_at }}</p>
                <p v-if="result.similarity" class="text-emerald-200/80 text-sm mt-1">
                    Identificado por rostro · {{ result.similarity }}% de coincidencia
                </p>
                <p class="text-slate-300 text-sm mt-6">
                    <template v-if="result.suggested_next_label">
                        Siguiente registro sugerido: <span class="font-semibold text-slate-100">{{ result.suggested_next_label }}</span>
                    </template>
                </p>
                <p class="text-slate-400 text-xs mt-8">Puedes retirarte, el kiosco se reinicia en unos segundos.</p>
            </div>
        </div>
    </div>
</template>
