<script setup>
import { onMounted, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { CircleCheck, CopyDocument, Monitor, VideoCamera, WarningFilled } from '@element-plus/icons-vue';
import axios from 'axios';
import { readDeviceKey } from '@/Utils/attendanceDeviceKey';
import {
    forgetDeviceToken,
    kioskUrlWithToken,
    readDeviceToken,
    saveDeviceToken,
} from '@/Utils/attendanceDeviceToken';
import { copyText } from '@/Utils/clipboard';

const props = defineProps({
    appName: String,
    kioskUrl: String,
    passwordConfigured: Boolean,
});

const checking = ref(true);
const registeredDevice = ref(null);
const registeredToken = ref(readDeviceToken());
// Device already registered from this browser that only needs a new token.
const recoverableDevice = ref(null);
const forceNewRegistration = ref(false);
const deviceOptions = ref([]);
const selectedDeviceId = ref(null);
const reactivatePassword = ref('');
const reactivateError = ref('');
const reactivating = ref(false);
const saving = ref(false);
const errorMessage = ref('');

const deviceKey = readDeviceKey();

const form = reactive({
    name: '',
    location: '',
    notes: '',
    password: '',
});

const errors = reactive({
    name: '',
    location: '',
    notes: '',
    password: '',
});

const clearErrors = () => {
    Object.keys(errors).forEach((key) => (errors[key] = ''));
};

const isDeviceRejectionError = (error) => [401, 403].includes(error?.response?.status);

const checkCurrentDevice = async () => {
    checking.value = true;

    const token = readDeviceToken();

    if (token) {
        try {
            const { data } = await axios.post(route('attendance.kiosk.bootstrap'), {
                device_key: deviceKey,
            }, {
                headers: { 'X-Attendance-Device': token },
            });

            registeredDevice.value = data.device;
            registeredToken.value = token;
            checking.value = false;

            return;
        } catch (error) {
            if (isDeviceRejectionError(error)) {
                // The server rejected the token: this browser is no longer authorized.
                forgetDeviceToken();
                registeredDevice.value = null;
                registeredToken.value = null;
            } else {
                // A network or server failure must not lose a valid registration.
                ElMessage.warning('No se pudo verificar el dispositivo. Revisa la conexión.');
                checking.value = false;

                return;
            }
        }
    }

    // Without a token the device may still be registered: the server recognizes
    // it by its fingerprint, so it is never registered twice.
    await recognizeRegisteredDevice();
    checking.value = false;
};

const recognizeRegisteredDevice = async () => {
    if (!deviceKey) {
        recoverableDevice.value = null;

        return;
    }

    try {
        const { data } = await axios.post(route('attendance.kiosk.recover'), {
            device_key: deviceKey,
        });

        if (data.status === 'authorized') {
            saveDeviceToken(data.token);
            registeredToken.value = data.token;
            registeredDevice.value = data.device;

            return;
        }

        recoverableDevice.value = data.device ?? null;
    } catch {
        recoverableDevice.value = null;
    }
};

/**
 * Give this browser a new token for the device it already is. The registration
 * password is required, so an unknown browser cannot claim a device.
 */
const reactivate = async () => {
    reactivateError.value = '';

    if (!reactivatePassword.value) {
        reactivateError.value = 'Escribe la contraseña de registro.';
        return;
    }

    reactivating.value = true;

    try {
        const { data } = await axios.post(route('attendance.kiosk.recover'), {
            device_key: deviceKey,
            password: reactivatePassword.value,
            device_id: selectedDeviceId.value ?? recoverableDevice.value?.id ?? null,
        });

        if (data.status === 'authorized') {
            saveDeviceToken(data.token);
            registeredToken.value = data.token;
            registeredDevice.value = data.device;
            recoverableDevice.value = null;
            reactivatePassword.value = '';
            ElMessage.success('Dispositivo reactivado correctamente.');

            return;
        }

        // The fingerprint does not identify a single device: pick one.
        deviceOptions.value = data.devices ?? [];
        recoverableDevice.value = data.device ?? null;
        reactivateError.value = data.message || '';
    } catch (error) {
        reactivateError.value = error.response?.data?.errors?.password?.[0]
            || error.response?.data?.errors?.device_id?.[0]
            || 'No se pudo reactivar el dispositivo.';
    } finally {
        reactivating.value = false;
    }
};

const register = async () => {
    errorMessage.value = '';
    clearErrors();

    if (!form.name.trim()) {
        errors.name = 'Escribe el nombre del dispositivo.';
        return;
    }

    if (!form.password) {
        errors.password = 'Escribe la contraseña de registro.';
        return;
    }

    saving.value = true;

    try {
        const { data } = await axios.post(route('attendance.devices.register.store'), {
            name: form.name,
            location: form.location || null,
            notes: form.notes || null,
            password: form.password,
            device_key: deviceKey,
        });

        saveDeviceToken(data.token);
        registeredToken.value = data.token;
        registeredDevice.value = data.device;
        form.password = '';
        ElMessage.success('Dispositivo registrado correctamente.');
    } catch (error) {
        const serverErrors = error.response?.data?.errors || {};
        Object.keys(errors).forEach((key) => {
            errors[key] = serverErrors[key]?.[0] || '';
        });

        if (!Object.values(errors).some(Boolean)) {
            errorMessage.value = 'No se pudo registrar el dispositivo. Intenta de nuevo.';
        }
    } finally {
        saving.value = false;
    }
};

// The token travels in the link because iOS in-app browsers, private mode and
// home-screen apps do not share the web storage of the registration page.
const openKiosk = () => {
    window.location.href = kioskUrlWithToken(props.kioskUrl, registeredToken.value);
};

const copyKioskLink = async () => {
    if (await copyText(kioskUrlWithToken(props.kioskUrl, registeredToken.value))) {
        ElMessage.success(registeredToken.value
            ? 'Link del kiosco copiado. Incluye el acceso de este dispositivo: ábrelo en la tablet.'
            : 'Link del kiosco copiado. Registra primero este dispositivo para que el kiosco quede autorizado.');

        return;
    }

    ElMessage.warning('No se pudo copiar el link. Cópialo manualmente.');
};

onMounted(checkCurrentDevice);
</script>

<template>
    <Head title="Registro de dispositivos" />

    <div class="min-h-screen bg-[#f4f6f8] dark:bg-[#141416] text-gray-800 dark:text-gray-100 flex flex-col">
        <header class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 bg-white dark:bg-[#1e1e20] border-b border-gray-100 dark:border-[#2b2b2e]">
            <div class="flex items-center gap-3">
                <span class="text-lg font-bold tracking-tight text-[#f26c17]">{{ appName }}</span>
                <span class="text-xs uppercase tracking-widest text-gray-400">Registro de dispositivos</span>
            </div>

            <div class="flex items-center gap-2">
                <el-button :icon="CopyDocument" @click="copyKioskLink">Copiar link del kiosco</el-button>
                <el-button type="primary" color="#f26c17" :icon="VideoCamera" @click="openKiosk">
                    Abrir kiosco
                </el-button>
            </div>
        </header>

        <main class="flex-1 flex items-start justify-center px-4 py-10">
            <div class="w-full max-w-xl bg-white dark:bg-[#1e1e20] border border-gray-100 dark:border-[#2b2b2e] rounded-2xl shadow-sm p-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 rounded-full bg-[#fdf0e7] dark:bg-[#3a2a1d] text-[#f26c17] flex items-center justify-center">
                        <el-icon :size="24"><Monitor /></el-icon>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800 dark:text-white">Registrar este dispositivo</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Autoriza esta tablet o pantalla para abrir el kiosco de asistencia.
                        </p>
                    </div>
                </div>

                <p v-if="checking" class="text-sm text-gray-500">Verificando el dispositivo…</p>

                <template v-else-if="registeredDevice">
                    <div class="rounded-xl border border-emerald-200 dark:border-emerald-400/30 bg-emerald-50 dark:bg-emerald-500/10 px-4 py-4">
                        <p class="flex items-center gap-2 font-semibold text-emerald-700 dark:text-emerald-200">
                            <el-icon><CircleCheck /></el-icon>
                            Este dispositivo ya está registrado
                        </p>
                        <p class="text-sm text-emerald-700/80 dark:text-emerald-200/80 mt-1">
                            {{ registeredDevice.name }}<template v-if="registeredDevice.location"> · {{ registeredDevice.location }}</template>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-6">
                        <el-button type="primary" color="#f26c17" size="large" :icon="VideoCamera" @click="openKiosk">
                            Abrir kiosco
                        </el-button>
                        <el-button size="large" :icon="CopyDocument" @click="copyKioskLink">
                            Copiar link del kiosco
                        </el-button>
                    </div>
                </template>

                <template v-else-if="recoverableDevice && !forceNewRegistration">
                    <div class="rounded-xl border border-amber-200 dark:border-amber-400/30 bg-amber-50 dark:bg-amber-500/10 px-4 py-4">
                        <p class="flex items-center gap-2 font-semibold text-amber-700 dark:text-amber-200">
                            <el-icon><WarningFilled /></el-icon>
                            Este dispositivo ya está registrado
                        </p>
                        <p class="text-sm text-amber-700/80 dark:text-amber-200/80 mt-1">
                            {{ recoverableDevice.name }}<template v-if="recoverableDevice.location"> · {{ recoverableDevice.location }}</template>
                        </p>
                        <p class="text-sm text-amber-700/80 dark:text-amber-200/80 mt-2">
                            Este navegador perdió su acceso (por ejemplo, al borrar los datos de navegación).
                            Escribe la contraseña de registro para reactivarlo, sin registrarlo otra vez.
                        </p>
                    </div>

                    <el-form label-position="top" size="default" autocomplete="off" class="mt-5" @submit.prevent="reactivate">
                        <el-form-item v-if="deviceOptions.length" label="Dispositivo">
                            <el-select v-model="selectedDeviceId" class="w-full" placeholder="Selecciona el dispositivo">
                                <el-option
                                    v-for="option in deviceOptions"
                                    :key="option.id"
                                    :label="option.location ? `${option.name} · ${option.location}` : option.name"
                                    :value="option.id"
                                />
                            </el-select>
                        </el-form-item>

                        <el-form-item label="Contraseña de registro">
                            <el-input
                                v-model="reactivatePassword"
                                type="password"
                                name="device_registration_password"
                                autocomplete="off"
                                show-password
                                placeholder="Contraseña proporcionada por el administrador"
                                maxlength="100"
                                @keyup.enter="reactivate"
                            />
                        </el-form-item>

                        <p v-if="reactivateError" class="text-sm text-red-600 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-400/30 rounded-lg px-4 py-3 mb-4">
                            {{ reactivateError }}
                        </p>

                        <el-button
                            type="primary"
                            color="#f26c17"
                            size="large"
                            class="w-full"
                            :loading="reactivating"
                            :disabled="!passwordConfigured"
                            @click="reactivate"
                        >
                            Reactivar este dispositivo
                        </el-button>
                    </el-form>

                    <div class="text-center mt-4">
                        <el-button text class="!text-xs !font-semibold !text-gray-500" @click="forceNewRegistration = true">
                            Es un dispositivo distinto, registrarlo como nuevo
                        </el-button>
                    </div>
                </template>

                <template v-else>
                    <div
                        v-if="!passwordConfigured"
                        class="flex items-start gap-2 rounded-xl border border-amber-200 dark:border-amber-400/30 bg-amber-50 dark:bg-amber-500/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-200 mb-5"
                    >
                        <el-icon class="mt-0.5 shrink-0"><WarningFilled /></el-icon>
                        <p>
                            El registro público aún no está activo. Pide al administrador que configure la
                            contraseña de registro en Dispositivos de asistencia.
                        </p>
                    </div>

                    <el-form label-position="top" size="default" autocomplete="off" @submit.prevent="register">
                        <el-form-item label="Nombre del dispositivo" required :error="errors.name">
                            <el-input
                                v-model="form.name"
                                name="device_name"
                                autocomplete="off"
                                placeholder="Ej. Tablet recepción, Pantalla planta norte"
                                maxlength="100"
                            />
                        </el-form-item>

                        <el-form-item label="Ubicación" :error="errors.location">
                            <el-input
                                v-model="form.location"
                                name="device_location"
                                autocomplete="off"
                                placeholder="Ej. Oficina, Planta"
                                maxlength="120"
                            />
                        </el-form-item>

                        <el-form-item label="Notas" :error="errors.notes">
                            <el-input
                                v-model="form.notes"
                                name="device_notes"
                                autocomplete="off"
                                type="textarea"
                                :rows="2"
                                placeholder="Comentarios opcionales"
                                maxlength="500"
                            />
                        </el-form-item>

                        <el-form-item label="Contraseña de registro" required :error="errors.password">
                            <el-input
                                v-model="form.password"
                                name="device_registration_password"
                                autocomplete="off"
                                placeholder="Contraseña proporcionada por el administrador"
                                maxlength="100"
                                @keyup.enter="register"
                            />
                        </el-form-item>

                        <p v-if="errorMessage" class="text-sm text-red-600 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-400/30 rounded-lg px-4 py-3 mb-4">
                            {{ errorMessage }}
                        </p>

                        <el-button
                            type="primary"
                            color="#f26c17"
                            size="large"
                            class="w-full"
                            :loading="saving"
                            :disabled="!passwordConfigured"
                            @click="register"
                        >
                            Registrar este dispositivo
                        </el-button>
                    </el-form>
                </template>
            </div>
        </main>
    </div>
</template>
