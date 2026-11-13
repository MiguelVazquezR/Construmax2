<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Camera, CircleCheck, Location, WarningFilled } from '@element-plus/icons-vue';
import axios from 'axios';
import AttendanceAlreadyRegisteredDialog from '@/Components/Payroll/AttendanceAlreadyRegisteredDialog.vue';
import AttendanceRegistrationSuccessDialog from '@/Components/Payroll/AttendanceRegistrationSuccessDialog.vue';
import AttendanceKeypad from '@/Components/Payroll/AttendanceKeypad.vue';
import { attendanceErrorMessage, isDeviceRejection } from '@/Utils/attendanceRequestError';
import { readDeviceKey } from '@/Utils/attendanceDeviceKey';
import {
    forgetDeviceToken,
    readDeviceToken,
    readDeviceTokenFromUrl,
    saveDeviceToken,
} from '@/Utils/attendanceDeviceToken';

const props = defineProps({
    punchTypes: Object,
    faceRecognitionEnabled: Boolean,
    employeeNumberEnabled: Boolean,
    registrationUrl: String,
    recoveryPasswordConfigured: Boolean,
    appName: String,
});

const SESSION_RESET_MS = 7000;
const BOOTSTRAP_RETRY_MS = 15000;
// The face is recognized once. The collaborator keeps the same identification
// while registering movements; it ends when they choose not to register
// anything or after this inactivity period, so the kiosk is ready for the next
// person.
const IDENTIFICATION_IDLE_MS = 3 * 60 * 1000;

const token = ref(null);
const device = ref(null);
// checking | authorized | unreachable | unauthorized | recovery
const status = ref('checking');
const clock = ref(new Date());
const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine);
const connectionMessage = ref(null);

const deviceKey = ref(readDeviceKey());

// Reactivation of a device that lost its token (cleared browsing data, iOS
// storage eviction or a replaced token).
const recovery = reactive({ status: 'idle', device: null, devices: [], message: '' });
const recoveryPassword = ref('');
const recoveryDeviceId = ref(null);
const recoverySubmitting = ref(false);
const recoveryError = ref('');

const selectedType = ref(null);
// Face capture is always offered. With both methods enabled the collaborator
// chooses first, so the camera is not requested from the start.
const method = ref(props.employeeNumberEnabled ? null : 'face');
const employeeNumber = ref('');
// Identification returned by the server: the person's name, the movement
// options available today and the token reused by the following punches.
const identification = ref(null);
const pendingIdentification = ref(null);
const identificationPhoto = ref(null);
const identifying = ref(false);
const submittingPunch = ref(false);
const processing = ref(false);
const alreadyRegisteredDialogVisible = ref(false);
const alreadyRegisteredTitle = ref('Ese registro ya se realizó');
const alreadyRegisteredMessage = ref('');
const alreadyRegisteredTypeLabel = ref('');
const alreadyRegisteredTime = ref('');
const errorMessage = ref('');
const errorDetail = ref('');
const result = ref(null);
const locationStatus = ref('idle'); // idle | loading | ready | unavailable
// Why the last location attempt failed: insecure | unsupported | denied | timeout | unavailable
const locationIssue = ref(null);
// Browser permission for geolocation: unknown | prompt | granted | denied
const locationPermission = ref('unknown');
const locationHelpVisible = ref(false);
// The operator pressed "Ya la activé" but the browser is still blocking it.
const locationHelpChecked = ref(false);
const locationHelpChecking = ref(false);
// Message of the last location failure, so it can be cleared once it works.
const lastLocationError = ref(null);
let permissionStatus = null;
let permissionListener = null;
const cameraReady = ref(false);

const videoRef = ref(null);
const photoInputRef = ref(null);
let stream = null;
let clockTimer = null;
let resultTimer = null;
let bootstrapRetryTimer = null;
let identificationTimer = null;

// The on-screen keypad replaces the native keyboard only when the employee
// number is the selected method: it keeps the whole kiosk on a single screen.
const employeeNumberMode = computed(
    () => props.employeeNumberEnabled && method.value === 'employee_number' && !identification.value
);

const initialTypeEntries = computed(() =>
    Object.entries(props.punchTypes || {}).map(([value, label]) => ({ value, label }))
);

const currentOptions = computed(() => identification.value?.options ?? []);

const selectedTypeLabel = computed(() => {
    const options = identification.value ? currentOptions.value : initialTypeEntries.value;
    const match = options.find((entry) => (entry.type ?? entry.value) === selectedType.value);

    return match?.label ?? '';
});

const identificationMethodLabel = computed(() => {
    if (!identification.value) return '';
    if (identification.value.identifier_method === 'face') return 'Identificado por rostro';
    if (identification.value.identifier_method === 'employee_number') return 'Identificado con número de empleado';

    return '';
});

const clearIdentificationTimer = () => {
    clearTimeout(identificationTimer);
    identificationTimer = null;
};

const startIdentificationTimer = () => {
    clearIdentificationTimer();
    identificationTimer = setTimeout(() => {
        if (!identification.value) return;

        finishIdentification();
        errorMessage.value = 'La sesión de registro se cerró por inactividad. Vuelve a identificarte.';
    }, IDENTIFICATION_IDLE_MS);
};

const resetIdentificationTimer = () => {
    if (identification.value) startIdentificationTimer();
};

const finishIdentification = () => {
    clearIdentificationTimer();
    clearTimeout(resultTimer);
    result.value = null;
    identification.value = null;
    pendingIdentification.value = null;
    identificationPhoto.value = null;
    selectedType.value = null;
    employeeNumber.value = '';
    alreadyRegisteredDialogVisible.value = false;
    processing.value = false;
    // Start the next registration from scratch: no identification method
    // selected (when both are available) and no employee number typed.
    method.value = props.employeeNumberEnabled ? null : 'face';
    errorMessage.value = '';
    errorDetail.value = '';

    if (method.value === 'face' && status.value === 'authorized') {
        initCamera();
    }
};

const timeLabel = computed(() =>
    clock.value.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
);

const dateLabel = computed(() =>
    clock.value.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
);

/**
 * Forget the device token: only a device rejection from the server requires a
 * new authorization, so a network or server failure never wipes it.
 */
const clearDeviceToken = () => {
    forgetDeviceToken();
    token.value = null;
    device.value = null;
};

const prepareAuthorizedDevice = async () => {
    // Ask for the device location as soon as the kiosk is ready, so the
    // permission prompt appears before the first punch.
    captureLocation();

    if (method.value === 'face') {
        await initCamera();
    }
};

const applyDeviceSession = async (deviceToken, deviceInfo) => {
    saveDeviceToken(deviceToken);
    token.value = deviceToken;
    device.value = deviceInfo;
    connectionMessage.value = null;
    recovery.status = 'idle';
    status.value = 'authorized';

    await prepareAuthorizedDevice();
};

/**
 * Ask the server to recognize this browser through its fingerprint.
 *
 * When the browsing data is cleared the token is gone, but the device is still
 * registered. The server authorizes it again on its own when the fingerprint
 * matches a single device on the same network; in any other case it asks for
 * the registration password, because a fingerprint alone is not a secret.
 */
const recoverDevice = async ({ password = null, deviceId = null } = {}) => {
    clearTimeout(bootstrapRetryTimer);

    if (!deviceKey.value) {
        // A browser that cannot be identified can only be registered.
        status.value = 'unauthorized';
        recoverySubmitting.value = false;

        return;
    }

    try {
        const { data } = await axios.post(route('attendance.kiosk.recover'), {
            device_key: deviceKey.value,
            password,
            device_id: deviceId,
        });

        if (data.status === 'authorized') {
            recoveryError.value = '';
            await applyDeviceSession(data.token, data.device);
            return;
        }

        recovery.status = data.status;
        recovery.device = data.device ?? null;
        recovery.devices = data.devices ?? [];
        recovery.message = data.message ?? '';
        recoveryDeviceId.value = data.device?.id ?? null;
        recoveryError.value = '';
        status.value = 'recovery';
    } catch (error) {
        if (error?.response?.status === 422) {
            // Wrong password: keep the form so it can be corrected.
            recoveryError.value = attendanceErrorMessage(error).message;
            status.value = 'recovery';
            return;
        }

        connectionMessage.value = attendanceErrorMessage(error);
        status.value = 'unreachable';
        bootstrapRetryTimer = setTimeout(bootstrap, BOOTSTRAP_RETRY_MS);
    } finally {
        recoverySubmitting.value = false;
    }
};

const bootstrap = async () => {
    clearTimeout(bootstrapRetryTimer);

    // The registration page hands the token through the link: iOS in-app
    // browsers and home-screen apps do not share web storage.
    const tokenFromLink = readDeviceTokenFromUrl();

    if (tokenFromLink) {
        saveDeviceToken(tokenFromLink);
    }

    token.value = tokenFromLink || readDeviceToken();

    if (!token.value) {
        await recoverDevice();
        return;
    }

    try {
        const { data } = await axios.post(route('attendance.kiosk.bootstrap'), {
            device_key: deviceKey.value,
        }, {
            headers: { 'X-Attendance-Device': token.value },
        });

        device.value = data.device;
        connectionMessage.value = null;
        status.value = 'authorized';
        await prepareAuthorizedDevice();
    } catch (error) {
        if (isDeviceRejection(error)) {
            // The token was revoked or replaced: recognize the device again
            // before asking for a new registration.
            clearDeviceToken();
            await recoverDevice();
            return;
        }

        // The kiosk stays usable on its own: it keeps retrying in the
        // background so nobody has to reload the page.
        connectionMessage.value = attendanceErrorMessage(error);
        status.value = 'unreachable';
        bootstrapRetryTimer = setTimeout(bootstrap, BOOTSTRAP_RETRY_MS);
    }
};

const submitReactivation = () => {
    recoveryError.value = '';

    if (!recoveryPassword.value) {
        recoveryError.value = 'Escribe la contraseña de registro.';
        return;
    }

    recoverySubmitting.value = true;
    recoverDevice({ password: recoveryPassword.value, deviceId: recoveryDeviceId.value });
};

/**
 * Opened from the unauthorized screen: the fingerprint may not identify the
 * device (a browser update changed it), but it can still be reactivated.
 */
const showReactivationForm = () => {
    recovery.status = 'password_required';
    recovery.device = null;
    recovery.devices = [];
    recovery.message = 'Escribe la contraseña de registro para reactivar un dispositivo ya registrado.';
    recoveryError.value = '';
    status.value = 'recovery';
};

/**
 * Kiosk punches always store the device location: the coordinates are required
 * by the server, so the collaborator is asked to retry when they are missing.
 */
const locate = () =>
    new Promise((resolve) => {
        // Browsers only share the location on secure origins (HTTPS or
        // localhost); over plain HTTP the request always fails.
        if (typeof window !== 'undefined' && window.isSecureContext === false) {
            locationIssue.value = 'insecure';
            resolve(null);

            return;
        }

        if (!navigator.geolocation) {
            locationIssue.value = 'unsupported';
            resolve(null);

            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                locationIssue.value = null;
                resolve({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    location_accuracy: position.coords.accuracy,
                });
            },
            (error) => {
                // 1 = permission denied, 2 = position unavailable, 3 = timeout
                locationIssue.value = error?.code === 1
                    ? 'denied'
                    : error?.code === 3 ? 'timeout' : 'unavailable';
                resolve(null);
            },
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
        );
    });

/**
 * Keep track of the browser permission so the kiosk can tell "the collaborator
 * must allow it" apart from "the browser is blocking it for this site".
 */
const watchLocationPermission = async () => {
    if (!navigator.permissions?.query) {
        return;
    }

    if (permissionStatus) {
        locationPermission.value = permissionStatus.state;

        return;
    }

    try {
        permissionStatus = await navigator.permissions.query({ name: 'geolocation' });
        locationPermission.value = permissionStatus.state;
        permissionListener = () => {
            locationPermission.value = permissionStatus.state;

            // The operator allowed the location while the guide was open: the
            // kiosk continues on its own, without pressing anything else.
            if (locationHelpVisible.value && locationPermission.value !== 'denied') {
                continueAfterLocationEnabled();
            }
        };
        permissionStatus.addEventListener?.('change', permissionListener);
    } catch {
        // Safari before 16 does not implement the geolocation permission query.
        locationPermission.value = 'unknown';
    }
};

const captureLocation = async () => {
    locationStatus.value = 'loading';

    const position = await locate();
    locationStatus.value = position ? 'ready' : 'unavailable';

    if (!position) {
        // The permission may have changed while the browser was requesting it.
        await watchLocationPermission();
    }

    return position;
};

const locationIssueMessage = computed(() => {
    switch (locationIssue.value) {
        case 'insecure':
            return 'Este kiosco no está abierto en una conexión segura (https), así que el navegador no puede dar la ubicación. Avísale al administrador.';
        case 'denied':
            return 'La ubicación está bloqueada en este navegador. Toca "Cómo activarla" para permitirla de nuevo.';
        case 'timeout':
            return 'La ubicación tardó demasiado en responder. Intenta de nuevo cerca de una ventana o al aire libre.';
        case 'unavailable':
            return 'No se pudo obtener la ubicación. Revisa que la ubicación del dispositivo esté encendida e intenta de nuevo.';
        case 'unsupported':
            return 'Este navegador no puede obtener la ubicación. Pide al administrador que use otro navegador en el kiosco.';
        default:
            return 'No se pudo obtener la ubicación del dispositivo. Activa la ubicación e intenta de nuevo.';
    }
});

const isIOSDevice = () =>
    /iPad|iPhone|iPod/.test(window.navigator?.userAgent ?? '')
    || (window.navigator?.platform === 'MacIntel' && (window.navigator?.maxTouchPoints ?? 0) > 1);

// Address shown in the guide, so the operator knows which site is being allowed.
const siteAddress = computed(() => {
    try {
        return window.location.host;
    } catch {
        return '';
    }
});

/**
 * Concrete steps for the current browser. Once the browser blocked the
 * permission it never asks again by itself, so the only way is to allow it from
 * the site settings; the guide says exactly where.
 */
const locationHelpSteps = computed(() => {
    if (locationIssue.value === 'insecure') {
        return [
            { title: 'Abre el kiosco con una dirección segura', detail: 'Debe empezar por https:// (por ejemplo https://' + siteAddress.value + ').' },
            { title: 'Si no conoces la dirección segura', detail: 'Pide al administrador que abra el kiosco con https://.' },
            { title: 'Vuelve a intentar el registro', detail: 'Con la dirección segura la ubicación se activa sola.' },
        ];
    }

    if (locationIssue.value === 'unsupported') {
        return [
            { title: 'Este navegador no puede compartir la ubicación', detail: null },
            { title: 'Usa Chrome, Edge o Safari', detail: 'Pide al administrador que abra el kiosco con otro navegador.' },
        ];
    }

    const userAgent = window.navigator?.userAgent ?? '';

    if (/Android/.test(userAgent)) {
        return [
            { title: 'Toca el candado junto a la dirección', detail: `Está a la izquierda de ${siteAddress.value} (o en el menú ⋮).` },
            { title: 'Entra en "Permisos" y luego en "Ubicación"', detail: 'Elige "Permitir".' },
            { title: 'Revisa la ubicación del dispositivo', detail: 'Ajustes → Ubicación: debe estar encendida.' },
            { title: 'Regresa aquí y toca "Ya la activé"', detail: null },
        ];
    }

    if (isIOSDevice()) {
        return [
            { title: 'Abre Ajustes en el dispositivo', detail: 'Ajustes → Privacidad y seguridad → Localización: actívala.' },
            { title: 'Permite el navegador que usa el kiosco', detail: 'En la lista elige Safari (o el navegador del kiosco) → "Al usar la app".' },
            { title: 'Revisa también Ajustes → Safari → Ubicación', detail: 'Elige "Preguntar" o "Permitir".' },
            { title: 'Regresa aquí y toca "Ya la activé"', detail: null },
        ];
    }

    if (/Firefox\//.test(userAgent)) {
        return [
            { title: 'Toca el candado junto a la dirección', detail: `Está a la izquierda de ${siteAddress.value}.` },
            { title: 'Abre "Permisos" y cambia "Acceso a la ubicación"', detail: 'Elige "Permitir".' },
            { title: 'Regresa aquí y toca "Ya la activé"', detail: null },
        ];
    }

    return [
        { title: 'Haz clic en el candado junto a la dirección', detail: `Está a la izquierda de ${siteAddress.value}.` },
        { title: 'Abre "Permisos del sitio" y busca "Ubicación"', detail: 'Cámbialo a "Permitir".' },
        { title: 'Regresa aquí y toca "Ya la activé"', detail: null },
    ];
});

const showLocationHelp = () => {
    locationHelpChecked.value = false;
    locationHelpVisible.value = true;
};

const clearLocationError = () => {
    if (lastLocationError.value && errorMessage.value === lastLocationError.value) {
        errorMessage.value = '';
        errorDetail.value = '';
    }
};

/**
 * Try the capture again. Returns true when the location was obtained and the
 * guide can be closed.
 */
const continueAfterLocationEnabled = async () => {
    await captureLocation();

    if (locationStatus.value === 'ready') {
        clearLocationError();
        locationHelpVisible.value = false;
        locationHelpChecked.value = false;

        return true;
    }

    return false;
};

/**
 * Opens the native browser prompt when it can still appear. When the browser
 * already blocked the permission nothing can prompt it again, so the guide is
 * shown instead of a retry that would look broken.
 */
const requestLocation = async () => {
    // The permission may have changed outside the kiosk (site settings).
    await watchLocationPermission();

    if (['insecure', 'unsupported'].includes(locationIssue.value)) {
        showLocationHelp();

        return;
    }

    if (locationPermission.value === 'denied') {
        showLocationHelp();

        return;
    }

    await captureLocation();

    if (locationStatus.value === 'ready') {
        clearLocationError();
        return;
    }

    if (locationPermission.value === 'denied' || locationIssue.value === 'denied') {
        showLocationHelp();
    }
};

// "Ya la activé": re-checks the permission and continues. It never reopens the
// same dialog; when it is still blocked the reason is shown inside it.
const confirmLocationEnabled = async () => {
    locationHelpChecking.value = true;
    locationHelpChecked.value = false;

    await watchLocationPermission();
    const ready = await continueAfterLocationEnabled();

    locationHelpChecking.value = false;
    locationHelpChecked.value = !ready;
};

// Labels the button: while the browser may still ask, the kiosk asks again;
// when it is blocked, it explains how to unblock it.
const locationActionLabel = computed(() =>
    locationPermission.value === 'denied'
        || ['denied', 'insecure', 'unsupported'].includes(locationIssue.value)
        ? 'Cómo activarla'
        : 'Permitir ubicación'
);

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
        method.value = 'face';
        await identify(await readPhotoFile(file));
    } catch {
        errorMessage.value = 'La foto no se pudo leer. Intenta de nuevo.';
    }
};

/**
 * Recognize the collaborator once. The server returns the movements available
 * today and a token that the following punches reuse, so the face is not
 * searched again in Amazon.
 */
const identify = async (capturedPhoto = null) => {
    errorMessage.value = '';
    errorDetail.value = '';

    if (!selectedType.value) {
        errorMessage.value = 'Selecciona el tipo de registro.';
        return;
    }

    if (method.value === 'employee_number' && !employeeNumber.value.trim()) {
        errorMessage.value = 'Escribe tu número de empleado.';
        return;
    }

    if (method.value !== 'face' && method.value !== 'employee_number') {
        errorMessage.value = 'Elige cómo quieres identificarte.';
        return;
    }

    let photo = null;

    if (method.value === 'face') {
        photo = capturedPhoto || await capturePhoto();

        if (!photo) {
            if (cameraReady.value) {
                errorMessage.value = 'La cámara todavía se está activando. Espera un momento e intenta de nuevo.';
                return;
            }

            pickPhoto();
            return;
        }
    }

    identifying.value = true;
    processing.value = true;

    try {
        const payload = method.value === 'face'
            ? { method: 'face', photo }
            : { method: 'employee_number', employee_number: employeeNumber.value.trim() };

        const { data } = await axios.post(route('attendance.kiosk.identify'), payload, {
            headers: { 'X-Attendance-Device': token.value },
        });

        const requestedType = selectedType.value;
        const requestedOption = (data.options || []).find((option) => option.type === requestedType);

        identificationPhoto.value = photo;
        employeeNumber.value = '';
        blurActiveElement();

        if (requestedOption?.allowed) {
            // Keep the initial view and register with the token that was just
            // issued. The options view is not rendered for a valid first
            // registration; the loading overlay covers both requests.
            pendingIdentification.value = data;
            selectedType.value = requestedType;
            await submitPunch();
            return;
        }

        // The person already registered that movement (or it does not follow
        // the daily sequence): only now switch to the options view so they
        // choose another one without identifying again.
        releaseCamera();
        pendingIdentification.value = null;
        identification.value = data;
        selectedType.value = null;
        startIdentificationTimer();
        processing.value = false;
        showAlreadyRegisteredDialog(requestedOption);
    } catch (error) {
        if (isDeviceRejection(error)) {
            // The token may have been replaced by another browsing context:
            // recognize the device again instead of blocking the kiosk.
            clearDeviceToken();
            await recoverDevice();
            return;
        }

        const { message, detail } = attendanceErrorMessage(error);
        errorMessage.value = message;
        errorDetail.value = detail;
    } finally {
        identifying.value = false;
        processing.value = false;
    }
};

const showAlreadyRegisteredDialog = (option = null) => {
    alreadyRegisteredTitle.value = option?.last_registered_at
        ? 'Ese registro ya se realizó'
        : 'No se pudo registrar ese movimiento';
    alreadyRegisteredMessage.value = option?.reason
        ?? 'Ese registro ya se realizó o no está disponible en este momento.';
    alreadyRegisteredTypeLabel.value = option?.label ?? '';
    alreadyRegisteredTime.value = option?.last_registered_at
        ? `Registrado a las ${option.last_registered_at}`
        : '';
    alreadyRegisteredDialogVisible.value = true;
};

const onAlreadyRegisteredDialogClosed = () => {
    // The dialog also closes when the kiosk session ends; in that case the
    // initial screen must stay clean.
    if (!identification.value) return;

    selectedType.value = null;
    errorMessage.value = '';
    errorDetail.value = '';
};

const handleOptionClick = (option) => {
    errorMessage.value = '';
    errorDetail.value = '';
    resetIdentificationTimer();

    if (!option.allowed) {
        selectedType.value = null;
        showAlreadyRegisteredDialog(option);
        return;
    }

    selectedType.value = selectedType.value === option.type ? null : option.type;
};

const submitPunch = async () => {
    errorMessage.value = '';
    errorDetail.value = '';

    const identificationToken = pendingIdentification.value?.identification_token
        ?? identification.value?.identification_token;

    if (!identificationToken) {
        errorMessage.value = 'Primero identifícate para continuar.';
        return;
    }

    if (!selectedType.value) {
        errorMessage.value = 'Selecciona el tipo de registro.';
        return;
    }

    submittingPunch.value = true;

    try {
        const position = await captureLocation();

        if (!position) {
            errorMessage.value = locationIssueMessage.value;
            lastLocationError.value = errorMessage.value;
            errorDetail.value = null;
            return;
        }

        const { data } = await axios.post(route('attendance.kiosk.punch'), {
            identification_token: identificationToken,
            type: selectedType.value,
            photo: identificationPhoto.value,
            latitude: position.latitude,
            longitude: position.longitude,
            location_accuracy: position.location_accuracy,
        }, {
            headers: { 'X-Attendance-Device': token.value },
        });

        result.value = data;
        processing.value = false;

        if (identification.value) {
            identification.value = {
                ...identification.value,
                identification_token: data.identification_token,
                user_name: data.user_name,
                employee_number: data.employee_number,
                identifier_method: data.identifier_method,
                similarity: data.similarity,
                options: data.options,
            };
        }

        // The identification photo is stored as evidence of the first punch only.
        pendingIdentification.value = null;
        identificationPhoto.value = null;
        selectedType.value = null;
        blurActiveElement();
        resetIdentificationTimer();

        clearTimeout(resultTimer);
        resultTimer = setTimeout(() => {
            // A successful registration returns to the initial screen; the
            // options view only stays open when the movement was repeated.
            finishIdentification();
        }, SESSION_RESET_MS);
    } catch (error) {
        if (isDeviceRejection(error)) {
            // The token may have been replaced by another browsing context:
            // recognize the device again instead of blocking the kiosk.
            clearDeviceToken();
            await recoverDevice();
            return;
        }

        const data = error.response?.data;

        if (data?.code === 'identification_expired') {
            finishIdentification();
            errorMessage.value = data.message || 'Tu identificación expiró. Vuelve a identificarte.';
            return;
        }

        if (data?.code === 'attendance_punch_not_allowed') {
            const base = pendingIdentification.value ?? identification.value;

            if (base) {
                identification.value = {
                    ...base,
                    options: data.options ?? base.options,
                };
            }

            releaseCamera();
            pendingIdentification.value = null;
            selectedType.value = null;
            startIdentificationTimer();
            processing.value = false;

            const option = (data.options || []).find((entry) => entry.type === data.type)
                ?? { reason: data.message, type: data.type, last_registered_at: data.last_registered_at };

            showAlreadyRegisteredDialog(option);
            return;
        }

        if (data?.options && identification.value) {
            identification.value = { ...identification.value, options: data.options };
        }

        const { message, detail } = attendanceErrorMessage(error);
        errorMessage.value = message;
        errorDetail.value = detail;
    } finally {
        submittingPunch.value = false;
        processing.value = false;
    }
};

// Hides the caret of the on-screen keypad input so no keyboard appears on
// tablets while the next collaborator registers.
const blurActiveElement = () => {
    if (document.activeElement instanceof HTMLElement) {
        document.activeElement.blur();
    }
};

// The kiosk recovers by itself when the network comes back, so nobody has to
// reload the page or register the tablet again.
const handleOnline = () => {
    online.value = true;

    if (status.value === 'unreachable') {
        bootstrap();
    }
};

const handleOffline = () => {
    online.value = false;
};

// Opens the public registration page in the same browsing context, so the new
// token is stored where the kiosk can read it.
const openRegistration = () => {
    if (props.registrationUrl) {
        window.location.href = props.registrationUrl;
    }
};

// The camera is requested only while the face method is selected: choosing
// the employee number releases it so no camera access stays active.
watch(method, (value) => {
    if (identification.value) return;

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

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    watchLocationPermission();

    await bootstrap();
});

onBeforeUnmount(() => {
    clearInterval(clockTimer);
    clearTimeout(resultTimer);
    clearTimeout(bootstrapRetryTimer);
    clearIdentificationTimer();
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);

    if (permissionStatus && permissionListener) {
        permissionStatus.removeEventListener?.('change', permissionListener);
    }

    releaseCamera();
});</script>

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
                <span class="inline-block w-2 h-2 rounded-full" :class="online ? 'bg-emerald-500' : 'bg-amber-500'"></span>
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
                <div class="flex flex-wrap items-center justify-center gap-2 mt-5">
                    <el-button v-if="recoveryPasswordConfigured" type="primary" color="#f26c17" @click="showReactivationForm">
                        Este dispositivo ya está registrado
                    </el-button>
                    <el-button v-if="registrationUrl" :type="recoveryPasswordConfigured ? 'default' : 'primary'" :color="recoveryPasswordConfigured ? undefined : '#f26c17'" @click="openRegistration">
                        Registrar este dispositivo
                    </el-button>
                </div>
            </div>
        </main>

        <!-- Registered device that lost its token: reactivate it -->
        <main v-else-if="status === 'recovery'" class="flex-1 flex items-center justify-center px-6">
            <div class="w-full max-w-xl bg-white dark:bg-[#1e1e20] border border-gray-100 dark:border-[#2b2b2e] rounded-2xl p-8 shadow-sm">
                <div class="w-16 h-16 mx-auto rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-500 flex items-center justify-center">
                    <el-icon :size="30"><WarningFilled /></el-icon>
                </div>
                <h1 class="text-2xl font-bold mt-4 text-center">Reactiva este dispositivo</h1>
                <p class="text-gray-500 mt-3 text-center">{{ recovery.message }}</p>

                <p v-if="recovery.device" class="mt-3 text-center font-semibold text-gray-700 dark:text-gray-200">
                    {{ recovery.device.name }}
                    <span v-if="recovery.device.location" class="block text-sm font-normal text-gray-500">{{ recovery.device.location }}</span>
                </p>

                <el-form
                    v-if="recovery.status !== 'unavailable'"
                    label-position="top"
                    size="default"
                    class="mt-6"
                    @submit.prevent="submitReactivation"
                >
                    <el-form-item v-if="recovery.devices.length" label="Dispositivo">
                        <el-select v-model="recoveryDeviceId" class="w-full" placeholder="Selecciona el dispositivo">
                            <el-option
                                v-for="option in recovery.devices"
                                :key="option.id"
                                :label="option.location ? `${option.name} · ${option.location}` : option.name"
                                :value="option.id"
                            />
                        </el-select>
                    </el-form-item>

                    <el-form-item label="Contraseña de registro">
                        <el-input
                            v-model="recoveryPassword"
                            type="password"
                            name="device_registration_password"
                            autocomplete="off"
                            show-password
                            placeholder="Contraseña proporcionada por el administrador"
                            @keyup.enter="submitReactivation"
                        />
                    </el-form-item>

                    <p v-if="recoveryError" class="text-sm text-red-600 dark:text-red-300 mb-3">{{ recoveryError }}</p>

                    <el-button
                        type="primary"
                        color="#f26c17"
                        class="w-full"
                        :loading="recoverySubmitting"
                        @click="submitReactivation"
                    >
                        Reactivar dispositivo
                    </el-button>
                </el-form>

                <div class="flex flex-wrap items-center justify-center gap-2 mt-4">
                    <el-button text @click="bootstrap">Reintentar</el-button>
                    <el-button v-if="registrationUrl" text @click="openRegistration">
                        Registrar un dispositivo distinto
                    </el-button>
                </div>
            </div>
        </main>

        <!-- Server unreachable: the kiosk keeps retrying on its own -->
        <main v-else-if="status === 'unreachable'" class="flex-1 flex items-center justify-center px-6">
            <div class="max-w-xl text-center bg-white dark:bg-[#1e1e20] border border-gray-100 dark:border-[#2b2b2e] rounded-2xl p-10 shadow-sm">
                <div class="w-16 h-16 mx-auto rounded-full bg-red-50 dark:bg-red-500/10 text-red-500 flex items-center justify-center">
                    <el-icon :size="30"><WarningFilled /></el-icon>
                </div>
                <h1 class="text-2xl font-bold mt-4">No hay conexión con el sistema</h1>
                <p class="text-gray-500 mt-3">{{ connectionMessage?.message }}</p>
                <p v-if="connectionMessage?.detail" class="text-gray-400 text-sm mt-2">{{ connectionMessage.detail }}</p>
                <p class="text-gray-400 text-sm mt-4">
                    El kiosco seguirá intentando conectarse; no es necesario recargar la página.
                </p>
                <el-button class="mt-5" type="primary" @click="bootstrap">Reintentar ahora</el-button>
            </div>
        </main>

        <!-- Kiosk -->
        <main v-else class="flex-1 min-h-0 overflow-y-auto px-4 py-3 lg:overflow-hidden lg:px-8 lg:py-6 grid grid-cols-1 gap-3 lg:grid-cols-5 lg:gap-6">
            <!-- Clock + form -->
            <section
                :class="[
                    identification ? 'lg:col-span-5 lg:order-1' : (method ? 'lg:col-span-3' : 'lg:col-span-5'),
                    employeeNumberMode ? 'lg:order-2' : 'lg:order-1',
                ]"
                class="order-first flex min-h-0 flex-col"
            >
                <p v-if="!online" class="text-xs text-amber-700 dark:text-amber-200 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-400/30 rounded-lg px-3 py-2 mb-3">
                    Sin conexión a internet. Vuelve a intentar cuando se restablezca la red.
                </p>

                <div class="text-center mb-3 lg:mb-5">
                    <p class="text-2xl sm:text-4xl lg:text-5xl font-bold tabular-nums tracking-tight text-gray-800 dark:text-white">{{ timeLabel }}</p>
                    <p class="hidden sm:block text-gray-400 capitalize mt-1">{{ dateLabel }}</p>
                </div>

                <div v-if="errorMessage" class="text-sm text-red-600 dark:text-red-300 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-400/30 rounded-lg px-3 py-2 mb-3">
                    <p>{{ errorMessage }}</p>
                    <span v-if="errorDetail" class="block mt-1 text-xs font-medium opacity-70">{{ errorDetail }}</span>
                    <el-button
                        v-if="locationIssue && locationStatus === 'unavailable'"
                        class="mt-2"
                        type="primary"
                        plain
                        size="default"
                        :icon="Location"
                        @click="requestLocation"
                    >
                        {{ locationActionLabel }}
                    </el-button>
                </div>

                <div class="bg-white dark:bg-[#1e1e20] border border-gray-100 dark:border-[#2b2b2e] rounded-2xl p-4 lg:p-6 flex-1 flex-col flex shadow-sm min-h-0 overflow-y-auto">
                    <!-- Step 1: choose the movement, then identify the collaborator -->
                    <template v-if="!identification">
                        <div class="flex items-center gap-2 mb-3 lg:mb-4">
                            <span class="w-6 h-6 lg:w-7 lg:h-7 rounded-full bg-[#f26c17] text-white text-xs lg:text-sm font-bold flex items-center justify-center shrink-0">1</span>
                            <div>
                                <h2 class="text-sm lg:text-base font-semibold text-gray-800 dark:text-white">Elige el tipo de registro</h2>
                                <p class="hidden sm:block text-xs text-gray-400">¿Qué vas a registrar en este momento?</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-4 lg:mb-5">
                            <button
                                v-for="entry in initialTypeEntries"
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
                                <h2 class="text-sm lg:text-base font-semibold text-gray-800 dark:text-white">Identifícate</h2>
                                <p class="hidden sm:block text-xs text-gray-400">
                                    <template v-if="employeeNumberEnabled">Elige cómo quieres identificarte.</template>
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

                        <!-- Personal registration notice (only with the employee number: the face identifies the collaborator) -->
                        <div v-if="method === 'employee_number'" class="flex items-start gap-2 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-400/30 px-3 py-2 mb-3 text-[11px] leading-snug text-amber-700 dark:text-amber-200">
                            <el-icon class="mt-0.5 shrink-0"><WarningFilled /></el-icon>
                            <p>
                                El registro es personal e individual: no registres la asistencia de otra persona.
                                Hacerlo afecta su nómina y puede generar penalizaciones.
                            </p>
                        </div>

                        <div class="mt-auto space-y-3">
                            <p v-if="!selectedType" class="text-center text-xs text-amber-600 dark:text-amber-300">
                                Primero elige el tipo de registro.
                            </p>

                            <!-- Employee number: the submit button lives in the on-screen keypad -->
                            <p
                                v-if="employeeNumberMode"
                                class="text-center text-xs text-gray-400 dark:text-gray-500"
                            >
                                Escribe tu número con el teclado en pantalla y presiona "Identificarme".
                            </p>

                            <!-- Face -->
                            <el-button
                                v-else-if="method === 'face'"
                                type="primary"
                                color="#f26c17"
                                size="large"
                                class="!h-14 !text-base w-full"
                                :loading="identifying"
                                :disabled="!selectedType"
                                @click="identify()"
                            >
                                {{ cameraReady ? 'Identificarme con mi rostro' : 'Tomar foto e identificarme' }}
                            </el-button>

                            <div v-if="locationStatus !== 'idle'" class="space-y-2">
                                <p
                                    class="flex items-center justify-center gap-1.5 text-xs text-center"
                                    :class="locationStatus === 'ready'
                                        ? 'text-emerald-600 dark:text-emerald-300'
                                        : locationStatus === 'loading'
                                            ? 'text-gray-400 dark:text-gray-500'
                                            : 'text-red-600 dark:text-red-400'"
                                >
                                    <el-icon><Location /></el-icon>
                                    <template v-if="locationStatus === 'loading'">Obteniendo la ubicación del dispositivo…</template>
                                    <template v-else-if="locationStatus === 'ready'">Se registrará la ubicación de este dispositivo.</template>
                                    <template v-else>{{ locationIssueMessage }}</template>
                                </p>

                                <div v-if="locationStatus === 'unavailable'" class="flex justify-center">
                                    <el-button type="primary" plain size="default" :icon="Location" @click="requestLocation">
                                        {{ locationActionLabel }}
                                    </el-button>
                                </div>
                            </div>
                        </div>

                        <p class="hidden sm:block text-xs text-gray-400 dark:text-gray-500 mt-3">
                            <template v-if="!selectedType">
                                Primero elige el tipo de registro.
                            </template>
                            <template v-else-if="!method">
                                Elige cómo quieres identificarte para continuar.
                            </template>
                            <template v-else-if="method === 'employee_number'">
                                Si no conoces tu número de empleado, pide ayuda al administrador.
                            </template>
                            <template v-else-if="!cameraReady">
                                Presiona "Tomar foto e identificarme" para capturar tu rostro con la cámara del dispositivo.
                                <template v-if="employeeNumberEnabled"> También puedes identificarte con tu número de empleado.</template>
                            </template>
                            <template v-else>
                                Presiona "Identificarme con mi rostro" y mira de frente a la cámara.
                            </template>
                        </p>
                    </template>

                    <!-- Step 2: choose the movement, without recognizing the face again -->
                    <template v-else>
                        <div class="flex flex-wrap items-start justify-between gap-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-400/30 px-4 py-3 mb-4">
                            <div class="min-w-0">
                                <p class="text-[10px] uppercase tracking-widest text-emerald-600 dark:text-emerald-300">Colaborador identificado</p>
                                <p class="truncate text-lg font-bold text-gray-800 dark:text-white">{{ identification.user_name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ identification.employee_number }} · {{ identificationMethodLabel }}
                                    <template v-if="identification.similarity"> · {{ identification.similarity }}% de coincidencia</template>
                                </p>
                            </div>
                            <el-button text size="small" class="!text-xs !font-semibold !text-[#f26c17]" @click="finishIdentification">
                                No quiero hacer ningún registro
                            </el-button>
                        </div>

                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-6 h-6 lg:w-7 lg:h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0">
                                <el-icon :size="16"><CircleCheck /></el-icon>
                            </span>
                            <div>
                                <h2 class="text-sm lg:text-base font-semibold text-gray-800 dark:text-white">Elige otro tipo de registro</h2>
                                <p class="hidden sm:block text-xs text-gray-400">Los registros ya realizados aparecen deshabilitados.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-4">
                            <button
                                v-for="option in currentOptions"
                                :key="option.type"
                                type="button"
                                class="rounded-xl border px-2 py-3 text-xs lg:text-sm font-semibold transition-all text-left"
                                :class="[
                                    selectedType === option.type
                                        ? 'bg-[#f26c17] border-[#f26c17] text-white shadow-md shadow-[#f26c17]/30'
                                        : option.allowed
                                            ? 'bg-white dark:bg-[#252529] border-gray-200 dark:border-[#2b2b2e] text-gray-600 dark:text-gray-300 hover:border-[#f26c17]/60 hover:text-[#f26c17]'
                                            : 'bg-gray-100 dark:bg-[#252529]/50 border-gray-200 dark:border-[#2b2b2e] text-gray-400 dark:text-gray-500 cursor-not-allowed',
                                ]"
                                :aria-disabled="!option.allowed"
                                @click="handleOptionClick(option)"
                            >
                                <span class="block">{{ option.label }}</span>
                                <span v-if="!option.allowed && option.last_registered_at" class="mt-0.5 block text-[10px] font-medium opacity-80">
                                    Registrado {{ option.last_registered_at }}
                                </span>
                                <span v-else-if="!option.allowed" class="mt-0.5 block text-[10px] font-medium opacity-80">
                                    No disponible
                                </span>
                            </button>
                        </div>

                        <div class="mt-auto space-y-3">
                            <el-button
                                v-if="selectedType"
                                type="primary"
                                color="#f26c17"
                                size="large"
                                class="!h-14 !text-base w-full"
                                :loading="submittingPunch"
                                @click="submitPunch"
                            >
                                Registrar {{ selectedTypeLabel.toLowerCase() }}
                            </el-button>

                            <div v-if="locationStatus !== 'idle'" class="space-y-2">
                                <p
                                    class="flex items-center justify-center gap-1.5 text-xs text-center"
                                    :class="locationStatus === 'ready'
                                        ? 'text-emerald-600 dark:text-emerald-300'
                                        : locationStatus === 'loading'
                                            ? 'text-gray-400 dark:text-gray-500'
                                            : 'text-red-600 dark:text-red-400'"
                                >
                                    <el-icon><Location /></el-icon>
                                    <template v-if="locationStatus === 'loading'">Obteniendo la ubicación del dispositivo…</template>
                                    <template v-else-if="locationStatus === 'ready'">Se registrará la ubicación de este dispositivo.</template>
                                    <template v-else>{{ locationIssueMessage }}</template>
                                </p>

                                <div v-if="locationStatus === 'unavailable'" class="flex justify-center">
                                    <el-button type="primary" plain size="default" :icon="Location" @click="requestLocation">
                                        {{ locationActionLabel }}
                                    </el-button>
                                </div>
                            </div>

                            <p v-else class="text-center text-xs text-gray-400 dark:text-gray-500">
                                Puedes registrar varios movimientos sin volver a identificarte. Si no quieres continuar, usa "No quiero hacer ningún registro".
                            </p>
                        </div>
                    </template>
                </div>
            </section>

            <!-- Right: camera (only while identifying with the face) -->
            <section v-if="method === 'face' && !identification" class="lg:col-span-2 lg:order-2 flex min-h-0 flex-col gap-4">
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
                        <p class="text-xs mt-1">Usa el botón "Tomar foto e identificarme".</p>
                    </div>
                </div>

                <div v-if="faceRecognitionEnabled" class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-400/30 rounded-xl px-4 py-3 text-sm text-emerald-700 dark:text-emerald-200">
                    Reconocimiento facial activo: presiona "Identificarme con mi rostro" para comenzar.
                </div>
                <div v-else class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-400/30 rounded-xl px-4 py-3 text-sm text-amber-700 dark:text-amber-200">
                    El reconocimiento facial aún no está configurado en el servidor. Pide ayuda al administrador.
                </div>
            </section>

            <!-- Left: on-screen keypad (employee number only, before identifying) -->
            <section v-if="employeeNumberMode" class="lg:col-span-2 lg:order-1 flex min-h-0 flex-col">
                <AttendanceKeypad
                    v-model="employeeNumber"
                    :loading="identifying"
                    :submit-disabled="!selectedType"
                    submit-label="Identificarme con número de empleado"
                    compact-submit-label="Identificarme"
                    @submit="identify()"
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

        <!-- Verification and registration in progress -->
        <div v-if="processing" role="status" aria-live="polite" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 px-4 backdrop-blur-sm">
            <div class="flex w-full max-w-xs flex-col items-center gap-4 rounded-3xl bg-white px-8 py-8 text-center shadow-2xl dark:bg-[#1e1e20]">
                <span class="h-12 w-12 animate-spin rounded-full border-4 border-[#f26c17]/20 border-t-[#f26c17]"></span>
                <div>
                    <p class="text-base font-semibold text-gray-800 dark:text-white">Procesando tu registro…</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Esto tarda solo un momento.</p>
                </div>
            </div>
        </div>

        <AttendanceRegistrationSuccessDialog
            :model-value="Boolean(result)"
            :user-name="result?.user_name ?? ''"
            :type-label="result?.type_label ?? ''"
            :punched-at="result?.punched_at ?? ''"
            :similarity="result?.similarity ?? null"
            :suggested-next-label="result?.suggested_next_label ?? ''"
            @update:model-value="finishIdentification"
        />

        <AttendanceAlreadyRegisteredDialog
            v-model="alreadyRegisteredDialogVisible"
            :title="alreadyRegisteredTitle"
            :message="alreadyRegisteredMessage"
            :type-label="alreadyRegisteredTypeLabel"
            :registered-at="alreadyRegisteredTime"
            @closed="onAlreadyRegisteredDialogClosed"
        />

        <!-- Location guide: once the browser blocks the permission it never asks again -->
        <el-dialog v-model="locationHelpVisible" width="560px" top="6vh" :show-close="true">
            <template #header>
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-500 flex items-center justify-center">
                        <el-icon :size="20"><Location /></el-icon>
                    </span>
                    <div>
                        <p class="text-base font-bold text-gray-800 dark:text-white">Activa la ubicación del kiosco</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Sin ubicación no se puede registrar la asistencia.</p>
                    </div>
                </div>
            </template>

            <div class="space-y-4">
                <div class="flex items-start gap-2 rounded-xl border border-amber-200 dark:border-amber-400/30 bg-amber-50 dark:bg-amber-500/10 px-4 py-3">
                    <el-icon class="mt-0.5 shrink-0 text-amber-500"><WarningFilled /></el-icon>
                    <p class="text-sm text-amber-800 dark:text-amber-200">
                        El navegador ya no volverá a mostrar el aviso de ubicación porque antes se eligió «Bloquear».
                        Para activarla, permítela en la configuración del sitio
                        <span class="font-semibold">{{ siteAddress }}</span>.
                    </p>
                </div>

                <ol class="space-y-3">
                    <li v-for="(step, index) in locationHelpSteps" :key="step.title" class="flex gap-3">
                        <span class="w-7 h-7 shrink-0 rounded-full bg-[#f26c17] text-white text-sm font-bold flex items-center justify-center">
                            {{ index + 1 }}
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 leading-snug">{{ step.title }}</p>
                            <p v-if="step.detail" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 leading-snug">{{ step.detail }}</p>
                        </div>
                    </li>
                </ol>

                <p class="text-xs text-gray-400 dark:text-gray-500">
                    En cuanto la permitas, el kiosco la toma sola: no hace falta recargar la página.
                </p>

                <p v-if="locationHelpChecked" class="text-sm text-red-600 dark:text-red-300 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-400/30 rounded-lg px-3 py-2">
                    El navegador todavía la tiene bloqueada. Revisa que hayas tocado "Permitir" en los pasos y vuelve a intentarlo.
                </p>
            </div>

            <template #footer>
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                    <el-button @click="locationHelpVisible = false">Cerrar</el-button>
                    <el-button
                        type="primary"
                        color="#f26c17"
                        :icon="Location"
                        :loading="locationHelpChecking"
                        @click="confirmLocationEnabled"
                    >
                        Ya la activé
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
.kiosk-screen {
    height: 100vh;
    height: 100dvh;
}
</style>
