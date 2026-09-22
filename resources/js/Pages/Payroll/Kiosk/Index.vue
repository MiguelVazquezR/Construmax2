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

    <div class="min-h-screen bg-[#f4f6f8] dark:bg-[#141416] text-gray-800 dark:text-gray-100 flex flex-col">
        <!-- Top bar -->
        <header class="flex items-center justify-between px-8 py-4 bg-white dark:bg-[#1e1e20] border-b border-gray-100 dark:border-[#2b2b2e]">
            <div class="flex items-center gap-3">
                <span class="text-lg font-bold tracking-tight text-[#f26c17]">{{ appName }}</span>
                <span class="text-xs uppercase tracking-widest text-gray-400">Kiosco de asistencia</span>
            </div>
            <div v-if="device" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                {{ device.name }}<span v-if="device.location"> · {{ device.location }}</span>
            </div>
        </header>

        <!-- Checking -->
        <main v-if="status === 'checking'" class="flex-1 flex items-center justify-center">
            <p class="text-gray-500">Verificando dispositivo…</p>
        </main>

        <!-- Unauthorized -->
        <main v-else-if="status === 'unauthorized'" class="flex-1 flex items-center justify-center px-6">
            <div class="max-w-xl text-center bg-white dark:bg-[#1e1e20] border border-gray-100 dark:border-[#2b2b2e] rounded-2xl p-10 shadow-sm">
                <div class="w-16 h-16 mx-auto rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-500 flex items-center justify-center">
                    <el-icon :size="30"><WarningFilled /></el-icon>
                </div>
                <h1 class="text-2xl font-bold mt-4">Dispositivo no autorizado</h1>
                <p class="text-gray-500 mt-3">
                    Este equipo no está registrado para usar el kiosco de asistencia.
                </p>
                <p class="text-gray-400 text-sm mt-4">
                    Pide al administrador que abra <span class="font-semibold text-gray-600 dark:text-gray-300">Recursos Humanos → Dispositivos de asistencia</span>
                    en este mismo equipo y use la opción "Registrar este dispositivo".
                </p>
            </div>
        </main>

        <!-- Kiosk -->
        <main v-else class="flex-1 grid grid-cols-1 lg:grid-cols-5 gap-8 px-8 py-8">
            <!-- Left: clock + form -->
            <section class="lg:col-span-3 flex flex-col">
                <div class="text-center mb-8">
                    <p class="text-6xl font-bold tabular-nums tracking-tight text-gray-800 dark:text-white">{{ timeLabel }}</p>
                    <p class="text-gray-400 capitalize mt-2">{{ dateLabel }}</p>
                </div>

                <div class="bg-white dark:bg-[#1e1e20] border border-gray-100 dark:border-[#2b2b2e] rounded-2xl p-8 flex-1 flex flex-col shadow-sm">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="w-7 h-7 rounded-full bg-[#f26c17] text-white text-sm font-bold flex items-center justify-center shrink-0">1</span>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Elige el tipo de registro</h2>
                            <p class="text-xs text-gray-400">¿Qué vas a registrar en este momento?</p>
                        </div>
                    </div>

                    <!-- Punch type -->
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-8">
                        <button
                            v-for="entry in typeEntries"
                            :key="entry.value"
                            type="button"
                            class="rounded-xl border px-4 py-4 text-sm font-semibold transition-all"
                            :class="selectedType === entry.value
                                ? 'bg-[#f26c17] border-[#f26c17] text-white shadow-md shadow-[#f26c17]/30'
                                : 'bg-white dark:bg-[#252529] border-gray-200 dark:border-[#2b2b2e] text-gray-600 dark:text-gray-300 hover:border-[#f26c17]/60 hover:text-[#f26c17]'"
                            @click="selectedType = entry.value"
                        >
                            {{ entry.label }}
                        </button>
                    </div>

                    <div class="flex items-center gap-3 mb-4">
                        <span class="w-7 h-7 rounded-full bg-[#f26c17] text-white text-sm font-bold flex items-center justify-center shrink-0">2</span>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Identifícate con tu rostro</h2>
                            <p class="text-xs text-gray-400">Mira de frente a la cámara y presiona el botón.</p>
                        </div>
                    </div>

                    <!-- Identification -->
                    <p v-if="errorMessage" class="text-sm text-red-600 dark:text-red-300 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-400/30 rounded-lg px-4 py-3 mb-5">
                        {{ errorMessage }}
                    </p>

                    <div class="mt-auto">
                        <el-button
                            v-if="faceRecognitionEnabled"
                            type="primary"
                            color="#f26c17"
                            size="large"
                            class="!h-14 !text-base w-full"
                            :disabled="!cameraReady"
                            :loading="faceSubmitting"
                            @click="submitFace"
                        >
                            Registrar con rostro
                        </el-button>
                    </div>

                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-4">
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
                <div class="relative rounded-2xl overflow-hidden border border-gray-200 dark:border-[#2b2b2e] bg-gray-900 aspect-[4/3] flex items-center justify-center shadow-sm">
                    <video
                        ref="videoRef"
                        autoplay
                        muted
                        playsinline
                        class="w-full h-full object-cover"
                        :class="{ hidden: !cameraReady }"
                    ></video>
                    <div v-if="!cameraReady" class="text-center text-gray-400 px-6">
                        <el-icon :size="40"><Camera /></el-icon>
                        <p class="text-sm mt-3">Sin cámara disponible</p>
                    </div>
                </div>

                <div v-if="faceRecognitionEnabled" class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-400/30 rounded-xl px-4 py-3 text-sm text-emerald-700 dark:text-emerald-200">
                    Reconocimiento facial activo: presiona "Registrar con rostro" para identificarte.
                </div>

                <p v-else class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-400/30 rounded-xl px-4 py-3 text-sm text-red-600 dark:text-red-200">
                    El reconocimiento facial no está habilitado en este kiosco. Pide al administrador que lo active en Configuración de nómina.
                </p>
            </section>
        </main>

        <!-- Success overlay -->
        <div v-if="result" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm flex items-center justify-center z-50 px-6">
            <div class="bg-white dark:bg-[#1e1e20] border border-gray-100 dark:border-[#2b2b2e] rounded-3xl p-12 text-center max-w-lg w-full shadow-2xl">
                <div class="w-20 h-20 mx-auto rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                    <el-icon :size="44"><CircleCheck /></el-icon>
                </div>
                <h2 class="text-3xl font-bold mt-5 text-gray-800 dark:text-white">{{ result.user_name }}</h2>
                <p class="mt-3">
                    <span class="inline-flex items-center rounded-full bg-[#fdf0e7] dark:bg-[#3a2a1d] text-[#f26c17] font-semibold text-sm px-3 py-1">
                        {{ result.type_label }}
                    </span>
                    <span class="ml-2 text-gray-500 font-medium tabular-nums">{{ result.punched_at }}</span>
                </p>
                <p v-if="result.similarity" class="text-emerald-600 dark:text-emerald-300 text-sm mt-2">
                    Identificado por rostro · {{ result.similarity }}% de coincidencia
                </p>
                <p class="text-gray-500 text-sm mt-6">
                    <template v-if="result.suggested_next_label">
                        Siguiente registro sugerido: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ result.suggested_next_label }}</span>
                    </template>
                </p>
                <p class="text-gray-400 text-xs mt-8">Puedes retirarte, el kiosco se reinicia en unos segundos.</p>
            </div>
        </div>
    </div>
</template>
