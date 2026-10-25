<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Camera, CircleCheck, Location, WarningFilled } from '@element-plus/icons-vue';
import axios from 'axios';
import AttendanceKeypad from '@/Components/Payroll/AttendanceKeypad.vue';

const props = defineProps({
    punchTypes: Object,
    faceRecognitionEnabled: Boolean,
    employeeNumberEnabled: Boolean,
    appName: String,
});

const TOKEN_KEY = 'attendance_device_token';
const SESSION_RESET_MS = 7000;

const token = ref(null);
const device = ref(null);
const status = ref('checking'); // checking | authorized | unauthorized
const clock = ref(new Date());

const selectedType = ref(null);
// Face capture is always offered. With both methods enabled the collaborator
// chooses first, so the camera is not requested from the start.
const method = ref(props.employeeNumberEnabled ? null : 'face');
const employeeNumber = ref('');
const employeeNumberSubmitting = ref(false);
const faceSubmitting = ref(false);
const errorMessage = ref('');
const result = ref(null);
const locationStatus = ref('idle'); // idle | loading | ready | unavailable
const cameraReady = ref(false);

const videoRef = ref(null);
const photoInputRef = ref(null);
let stream = null;
let clockTimer = null;
let resultTimer = null;

// The on-screen keypad replaces the native keyboard only when the employee
// number is the selected method: it keeps the whole kiosk on a single screen.
const employeeNumberMode = computed(() => props.employeeNumberEnabled && method.value === 'employee_number');

const stepTwoTitle = computed(() => {
    if (method.value === 'employee_number') return 'Identifícate con tu número de empleado';
    if (method.value === 'face') return 'Identifícate con tu rostro';

    return 'Identifícate';
});

const typeEntries = computed(() =>
    Object.entries(props.punchTypes || {}).map(([value, label]) => ({ value, label }))
);

const selectedTypeLabel = computed(
    () => typeEntries.value.find((entry) => entry.value === selectedType.value)?.label ?? ''
);

// On phones the setup card is replaced by a compact summary once the method is
// chosen: the keypad or the camera gets the whole screen without scrolling.
const phoneCompact = computed(() => Boolean(method.value) && Boolean(selectedType.value));

const methodLabel = computed(() => {
    if (method.value === 'employee_number') return 'Con número de empleado';
    if (method.value === 'face') return 'Con mi rostro';

    return '';
});

const changeSelection = () => {
    selectedType.value = null;
    // With a single identification method there is no chooser to return to.
    method.value = props.employeeNumberEnabled ? null : 'face';
    errorMessage.value = '';
};

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

/**
 * Kiosk punches also store the device location, just like remote attendance.
 * The punch is never blocked when the location is unavailable.
 */
const locate = () =>
    new Promise((resolve) => {
        if (!navigator.geolocation) {
            resolve(null);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => resolve({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                location_accuracy: position.coords.accuracy,
            }),
            () => resolve(null),
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
        );
    });

const captureLocation = async () => {
    locationStatus.value = 'loading';

    const position = await locate();
    locationStatus.value = position ? 'ready' : 'unavailable';

    return position;
};

const initCamera = async () => {
    if (stream) return;

    // Android browsers only expose getUserMedia on secure origins (HTTPS).
    // In that case the capture falls back to the device camera app.
    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        cameraReady.value = false;
        return;
    }

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { width: 640, height: 480, facingMode: 'user' },
            audio: false,
        });

        await nextTick();

        // The method may have changed to the employee number while the camera
        // was starting: release it immediately so no camera access stays active.
        if (method.value !== 'face') {
            releaseCamera();
            return;
        }

        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            cameraReady.value = true;
        }
    } catch {
        cameraReady.value = false;
    }
};

// Releases the camera completely (the browser's camera indicator turns off).
const releaseCamera = () => {
    if (stream) {
        stream.getTracks().forEach((track) => track.stop());
        stream = null;
    }

    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }

    cameraReady.value = false;
};

const videoHasFrames = (video) => Boolean(video && video.readyState >= 2 && video.videoWidth > 0);

// Resolves once the stream delivers its first frame (or after a short timeout
// when the browser never reports one).
const waitForVideoFrames = (video) =>
    new Promise((resolve) => {
        if (videoHasFrames(video)) {
            resolve(true);
            return;
        }

        let timer = null;

        const finish = () => {
            video.removeEventListener('loadeddata', finish);
            video.removeEventListener('playing', finish);
            clearTimeout(timer);
            resolve(videoHasFrames(video));
        };

        timer = setTimeout(finish, 4000);

        video.addEventListener('loadeddata', finish);
        video.addEventListener('playing', finish);
    });

const capturePhoto = async () => {
    const video = videoRef.value;

    if (!cameraReady.value || !video) return null;

    // The element exists before the first frame arrives; capturing then would
    // send a black image that the provider rejects as "no face".
    if (!await waitForVideoFrames(video)) return null;

    const canvas = document.createElement('canvas');
    const scale = Math.min(1, 640 / video.videoWidth);
    canvas.width = Math.round(video.videoWidth * scale);
    canvas.height = Math.round(video.videoHeight * scale);
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

    return canvas.toDataURL('image/jpeg', 0.8);
};

// Fallback for browsers without live camera access (for example Android over
// plain HTTP): the hidden file input opens the device camera and returns a photo.
const pickPhoto = () => {
    photoInputRef.value?.click();
};

const readPhotoFile = (file) =>
    new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = () => {
            const image = new Image();

            image.onload = () => {
                const maxWidth = 640;
                const scale = Math.min(1, maxWidth / image.width);
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(image.width * scale);
                canvas.height = Math.round(image.height * scale);
                canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
                resolve(canvas.toDataURL('image/jpeg', 0.8));
            };

            image.onerror = () => reject(new Error('La foto no se pudo leer.'));
            image.src = reader.result;
        };

        reader.onerror = () => reject(new Error('La foto no se pudo leer.'));
        reader.readAsDataURL(file);
    });

const onPhotoSelected = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) return;

    try {
        await submitFace(await readPhotoFile(file));
    } catch {
        errorMessage.value = 'La foto no se pudo leer. Intenta de nuevo.';
    }
};

const submitFace = async (capturedPhoto = null) => {
    errorMessage.value = '';

    if (!selectedType.value) {
        errorMessage.value = 'Selecciona el tipo de registro.';
        return;
    }

    const photo = capturedPhoto || await capturePhoto();

    if (!photo) {
        if (cameraReady.value) {
            errorMessage.value = 'La cámara todavía se está activando. Espera un momento e intenta de nuevo.';
            return;
        }

        pickPhoto();
        return;
    }

    faceSubmitting.value = true;

    try {
        const position = await captureLocation();

        const { data } = await axios.post(route('attendance.kiosk.face-punch'), {
            type: selectedType.value,
            photo,
            latitude: position?.latitude ?? null,
            longitude: position?.longitude ?? null,
            location_accuracy: position?.location_accuracy ?? null,
        }, {
            headers: { 'X-Attendance-Device': token.value },
        });

        result.value = data;
        selectedType.value = null;
        blurActiveElement();

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

const submitEmployeeNumber = async () => {
    errorMessage.value = '';

    if (!selectedType.value) {
        errorMessage.value = 'Selecciona el tipo de registro.';
        return;
    }

    if (!employeeNumber.value.trim()) {
        errorMessage.value = 'Escribe tu número de empleado.';
        return;
    }

    employeeNumberSubmitting.value = true;

    try {
        const position = await captureLocation();

        const { data } = await axios.post(route('attendance.kiosk.punch'), {
            employee_number: employeeNumber.value.trim(),
            type: selectedType.value,
            latitude: position?.latitude ?? null,
            longitude: position?.longitude ?? null,
            location_accuracy: position?.location_accuracy ?? null,
        }, {
            headers: { 'X-Attendance-Device': token.value },
        });

        result.value = data;
        selectedType.value = null;
        employeeNumber.value = '';
        blurActiveElement();

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
        employeeNumberSubmitting.value = false;
    }
};

// Hides the caret of the on-screen keypad input so no keyboard appears on
// tablets while the next collaborator registers.
const blurActiveElement = () => {
    if (document.activeElement instanceof HTMLElement) {
        document.activeElement.blur();
    }
};

// Closes the success dialog immediately instead of waiting for the automatic
// reset, so the next collaborator can register right away.
const closeResult = () => {
    clearTimeout(resultTimer);
    result.value = null;
    selectedType.value = null;
    employeeNumber.value = '';
    errorMessage.value = '';
};

// The camera is requested only while the face method is selected: choosing
// the employee number releases it so no camera access stays active.
watch(method, (value) => {
    if (value === 'face' && status.value === 'authorized') {
        initCamera();
    } else {
        releaseCamera();
    }
});

onMounted(async () => {
    clockTimer = setInterval(() => {
        clock.value = new Date();
    }, 1000);

    await bootstrap();

    if (status.value === 'authorized' && method.value === 'face') {
        await initCamera();
    }
});

onBeforeUnmount(() => {
    clearInterval(clockTimer);
    clearTimeout(resultTimer);
    releaseCamera();
});
</script>

<template>
    <Head title="Kiosco de asistencia" />

    <div class="kiosk-screen bg-[#f4f6f8] dark:bg-[#141416] text-gray-800 dark:text-gray-100 flex flex-col overflow-hidden">
        <!-- Top bar -->
        <header class="flex items-center justify-between px-4 py-3 lg:px-8 bg-white dark:bg-[#1e1e20] border-b border-gray-100 dark:border-[#2b2b2e]">
            <div class="flex items-center gap-3">
                <span class="text-base lg:text-lg font-bold tracking-tight text-[#f26c17]">{{ appName }}</span>
                <span class="hidden sm:inline text-xs uppercase tracking-widest text-gray-400">Kiosco de asistencia</span>
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
        <main v-else class="flex-1 min-h-0 overflow-y-auto px-4 py-3 lg:overflow-hidden lg:px-8 lg:py-6 grid grid-cols-1 gap-3 lg:grid-cols-5 lg:gap-6">
            <!-- Clock + form -->
            <section
                :class="[method ? 'lg:col-span-3' : 'lg:col-span-5', employeeNumberMode ? 'lg:order-2' : 'lg:order-1']"
                class="order-first flex min-h-0 flex-col"
            >
                <div class="text-center mb-3 lg:mb-5">
                    <p class="text-2xl sm:text-4xl lg:text-5xl font-bold tabular-nums tracking-tight text-gray-800 dark:text-white">{{ timeLabel }}</p>
                    <p class="hidden sm:block text-gray-400 capitalize mt-1">{{ dateLabel }}</p>
                </div>

                <p v-if="errorMessage" class="text-sm text-red-600 dark:text-red-300 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-400/30 rounded-lg px-3 py-2 mb-3">
                    {{ errorMessage }}
                </p>

                <!-- Phone: compact summary so the keypad or the camera gets the full screen -->
                <div
                    v-if="phoneCompact"
                    class="lg:hidden flex items-center justify-between gap-3 rounded-2xl border border-gray-100 dark:border-[#2b2b2e] bg-white dark:bg-[#1e1e20] px-4 py-2.5 mb-3 shadow-sm"
                >
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">Tipo de registro</p>
                        <p class="truncate text-sm font-semibold text-gray-700 dark:text-gray-200">{{ selectedTypeLabel }}</p>
                        <p class="truncate text-[11px] text-gray-400">{{ methodLabel }}</p>
                    </div>
                    <el-button text size="small" class="!text-xs !font-semibold !text-[#f26c17]" @click="changeSelection">
                        Cambiar
                    </el-button>
                </div>

                <div
                    class="bg-white dark:bg-[#1e1e20] border border-gray-100 dark:border-[#2b2b2e] rounded-2xl p-4 lg:p-6 flex-1 flex-col shadow-sm min-h-0"
                    :class="phoneCompact ? 'hidden lg:flex' : 'flex'"
                >
                    <div class="flex items-center gap-2 mb-3 lg:mb-4">
                        <span class="w-6 h-6 lg:w-7 lg:h-7 rounded-full bg-[#f26c17] text-white text-xs lg:text-sm font-bold flex items-center justify-center shrink-0">1</span>
                        <div>
                            <h2 class="text-sm lg:text-base font-semibold text-gray-800 dark:text-white">Elige el tipo de registro</h2>
                            <p class="hidden sm:block text-xs text-gray-400">¿Qué vas a registrar en este momento?</p>
                        </div>
                    </div>

                    <!-- Punch type -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-4 lg:mb-5">
                        <button
                            v-for="entry in typeEntries"
                            :key="entry.value"
                            type="button"
                            class="rounded-xl border px-2 py-2.5 text-xs lg:text-sm font-semibold transition-all"
                            :class="selectedType === entry.value
                                ? 'bg-[#f26c17] border-[#f26c17] text-white shadow-md shadow-[#f26c17]/30'
                                : 'bg-white dark:bg-[#252529] border-gray-200 dark:border-[#2b2b2e] text-gray-600 dark:text-gray-300 hover:border-[#f26c17]/60 hover:text-[#f26c17]'"
                            @click="selectedType = entry.value"
                        >
                            {{ entry.label }}
                        </button>
                    </div>

                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-6 h-6 lg:w-7 lg:h-7 rounded-full bg-[#f26c17] text-white text-xs lg:text-sm font-bold flex items-center justify-center shrink-0">2</span>
                        <div>
                            <h2 class="text-sm lg:text-base font-semibold text-gray-800 dark:text-white">
                                {{ stepTwoTitle }}
                            </h2>
                            <p class="hidden sm:block text-xs text-gray-400">
                                <template v-if="employeeNumberEnabled">Elige cómo quieres identificarte.</template>
                                <template v-else-if="method === 'employee_number'">Escribe tu número de empleado.</template>
                                <template v-else>Mira de frente a la cámara y presiona el botón.</template>
                            </p>
                        </div>
                    </div>

                    <!-- Identification method -->
                    <div v-if="employeeNumberEnabled" class="grid grid-cols-2 gap-2 mb-3">
                        <button
                            type="button"
                            class="rounded-xl border px-3 py-2.5 text-xs lg:text-sm font-semibold transition-all"
                            :class="method === 'face'
                                ? 'bg-[#f26c17] border-[#f26c17] text-white shadow-md shadow-[#f26c17]/30'
                                : 'bg-white dark:bg-[#252529] border-gray-200 dark:border-[#2b2b2e] text-gray-600 dark:text-gray-300 hover:border-[#f26c17]/60 hover:text-[#f26c17]'"
                            @click="method = 'face'"
                        >
                            Con mi rostro
                        </button>
                        <button
                            type="button"
                            class="rounded-xl border px-3 py-2.5 text-xs lg:text-sm font-semibold transition-all"
                            :class="method === 'employee_number'
                                ? 'bg-[#f26c17] border-[#f26c17] text-white shadow-md shadow-[#f26c17]/30'
                                : 'bg-white dark:bg-[#252529] border-gray-200 dark:border-[#2b2b2e] text-gray-600 dark:text-gray-300 hover:border-[#f26c17]/60 hover:text-[#f26c17]'"
                            @click="method = 'employee_number'"
                        >
                            Con número de empleado
                        </button>
                    </div>

                    <!-- Identification -->
                    <!-- Personal registration notice (only with the employee number: the face identifies the collaborator) -->
                    <div v-if="method === 'employee_number'" class="flex items-start gap-2 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-400/30 px-3 py-2 mb-3 text-[11px] leading-snug text-amber-700 dark:text-amber-200">
                        <el-icon class="mt-0.5 shrink-0"><WarningFilled /></el-icon>
                        <p>
                            El registro es personal e individual: no registres la asistencia de otra persona.
                            Hacerlo afecta su nómina y puede generar penalizaciones.
                        </p>
                    </div>

                    <div class="mt-auto space-y-3">
                        <!-- Employee number: the submit button lives in the on-screen keypad -->
                        <p
                            v-if="employeeNumberMode"
                            class="text-center text-xs text-gray-400 dark:text-gray-500"
                        >
                            Escribe tu número con el teclado en pantalla y presiona "Registrar con número de empleado".
                        </p>

                        <!-- Face -->
                        <el-button
                            v-else-if="method === 'face'"
                            type="primary"
                            color="#f26c17"
                            size="large"
                            class="!h-14 !text-base w-full"
                            :loading="faceSubmitting"
                            @click="submitFace()"
                        >
                            {{ cameraReady ? 'Registrar con rostro' : 'Tomar foto y registrar' }}
                        </el-button>

                        <p
                            v-if="locationStatus !== 'idle'"
                            class="flex items-center justify-center gap-1.5 text-xs"
                            :class="locationStatus === 'ready'
                                ? 'text-emerald-600 dark:text-emerald-300'
                                : 'text-gray-400 dark:text-gray-500'"
                        >
                            <el-icon><Location /></el-icon>
                            <template v-if="locationStatus === 'loading'">Obteniendo la ubicación del dispositivo…</template>
                            <template v-else-if="locationStatus === 'ready'">Se registrará la ubicación de este dispositivo.</template>
                            <template v-else>No se pudo obtener la ubicación; el registro se guardará sin ella.</template>
                        </p>
                    </div>

                    <p class="hidden sm:block text-xs text-gray-400 dark:text-gray-500 mt-3">
                        <template v-if="!method">
                            Elige cómo quieres identificarte para continuar.
                        </template>
                        <template v-else-if="method === 'employee_number'">
                            Si no conoces tu número de empleado, pide ayuda al administrador.
                        </template>
                        <template v-else-if="!cameraReady">
                            Presiona "Tomar foto y registrar" para capturar tu rostro con la cámara del dispositivo.
                            <template v-if="employeeNumberEnabled"> También puedes registrar con tu número de empleado.</template>
                        </template>
                        <template v-else>
                            Presiona "Registrar con rostro" y mira de frente a la cámara.
                        </template>
                    </p>
                </div>
            </section>

            <!-- Right: camera (only when identifying with the face) -->
            <section v-if="method === 'face'" class="lg:col-span-2 lg:order-2 flex min-h-0 flex-col gap-4">
                <div class="relative rounded-2xl overflow-hidden border border-gray-200 dark:border-[#2b2b2e] bg-gray-900 aspect-[4/3] max-h-full flex items-center justify-center shadow-sm">
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
                        <p class="text-sm mt-3">Cámara no disponible en este navegador</p>
                        <p class="text-xs mt-1">Usa el botón "Tomar foto y registrar".</p>
                    </div>
                </div>

                <!-- Phone: the register button lives here because the setup card is hidden -->
                <el-button
                    v-if="phoneCompact"
                    type="primary"
                    color="#f26c17"
                    size="large"
                    class="lg:hidden !h-14 !text-base w-full"
                    :loading="faceSubmitting"
                    @click="submitFace()"
                >
                    {{ cameraReady ? 'Registrar con rostro' : 'Tomar foto y registrar' }}
                </el-button>

                <div v-if="faceRecognitionEnabled" class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-400/30 rounded-xl px-4 py-3 text-sm text-emerald-700 dark:text-emerald-200">
                    Reconocimiento facial activo: presiona "Registrar con rostro" para identificarte.
                </div>
                <div v-else class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-400/30 rounded-xl px-4 py-3 text-sm text-amber-700 dark:text-amber-200">
                    El reconocimiento facial aún no está configurado en el servidor. Pide ayuda al administrador.
                </div>
            </section>

            <!-- Left: on-screen keypad (employee number only) -->
            <section v-if="employeeNumberMode" class="lg:col-span-2 lg:order-1 flex min-h-0 flex-col">
                <AttendanceKeypad
                    v-model="employeeNumber"
                    :loading="employeeNumberSubmitting"
                    submit-label="Registrar con número de empleado"
                    @submit="submitEmployeeNumber"
                />
            </section>
        </main>

        <!-- Camera fallback for browsers without live camera access -->
        <input
            ref="photoInputRef"
            type="file"
            accept="image/*"
            capture="user"
            class="hidden"
            @change="onPhotoSelected"
        />

        <!-- Success overlay -->
        <div v-if="result" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm flex items-center justify-center z-50 px-4 py-4">
            <div class="bg-white dark:bg-[#1e1e20] border border-gray-100 dark:border-[#2b2b2e] rounded-3xl p-8 lg:p-10 text-center max-w-lg w-full max-h-full overflow-y-auto shadow-2xl">
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
                <el-button class="!mt-8 !h-12 w-full !text-base" color="#f26c17" @click="closeResult">
                    Cerrar
                </el-button>
                <p class="text-gray-400 text-xs mt-3">Si nadie lo cierra, el kiosco se reinicia solo en unos segundos.</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.kiosk-screen {
    height: 100vh;
    height: 100dvh;
}
</style>
