/**
 * Messages shown when an attendance request (kiosk or self-service) fails.
 * The HTTP status tells apart a network problem, a device problem and a
 * server problem, so the collaborator knows what to do next.
 */
const STATUS_MESSAGES = {
    400: 'La solicitud no se pudo procesar. Intenta de nuevo.',
    401: 'La sesión del kiosco expiró. Recarga la página e intenta de nuevo.',
    403: 'Este dispositivo ya no está autorizado para registrar asistencia. Pide al administrador que lo registre de nuevo.',
    404: 'No se encontró el servicio de asistencia en el servidor. Avisa al administrador.',
    405: 'El servidor rechazó la solicitud. Avisa al administrador.',
    408: 'El servidor tardó demasiado en responder. Revisa la conexión e intenta de nuevo.',
    413: 'La foto es demasiado grande para enviarla. Colócate más cerca de la cámara e intenta de nuevo.',
    419: 'La sesión del kiosco expiró. Recarga la página e intenta de nuevo.',
    422: 'Los datos enviados no son válidos. Revisa la información e intenta de nuevo.',
    429: 'Se hicieron demasiados registros seguidos. Espera un minuto e intenta de nuevo.',
    500: 'El servidor tuvo un error al guardar el registro. Avisa al administrador.',
    501: 'La función no está disponible en el servidor. Avisa al administrador.',
    502: 'El servidor no respondió correctamente. Espera un momento e intenta de nuevo.',
    503: 'El sistema está fuera de servicio por mantenimiento. Avisa al administrador.',
    504: 'El servidor tardó demasiado en responder. Intenta de nuevo en un momento.',
};

const UNKNOWN_MESSAGE = 'Ocurrió un error inesperado al guardar el registro. Intenta de nuevo o avisa al administrador.';

const isBrowserOffline = () => typeof navigator !== 'undefined' && navigator.onLine === false;

const firstValidationMessage = (errors) => {
    if (! errors || typeof errors !== 'object') return null;

    const message = Object.values(errors).flat().find((entry) => typeof entry === 'string');

    return message ?? null;
};

/**
 * Resolve the message and the technical detail of a failed request.
 *
 * @returns {{ message: string, detail: string|null }}
 */
export const attendanceErrorMessage = (error) => {
    const response = error?.response;

    const validationMessage = firstValidationMessage(response?.data?.errors);

    if (validationMessage) {
        return { message: validationMessage, detail: null };
    }

    if (! response) {
        if (error?.code === 'ECONNABORTED' || error?.code === 'ETIMEDOUT') {
            return {
                message: 'El servidor tardó demasiado en responder. Revisa la conexión del kiosco e intenta de nuevo.',
                detail: null,
            };
        }

        if (isBrowserOffline()) {
            return {
                message: 'El kiosco está sin conexión a internet. Revisa la red y vuelve a intentar.',
                detail: null,
            };
        }

        return {
            message: 'No se pudo conectar con el servidor. Revisa la red del kiosco; si el problema continúa, avisa al administrador.',
            detail: null,
        };
    }

    const serverMessage = typeof response.data?.message === 'string' ? response.data.message : '';
    const detail = `Código ${response.status}${serverMessage ? ` · ${serverMessage.slice(0, 120)}` : ''}`;

    return {
        message: STATUS_MESSAGES[response.status] ?? UNKNOWN_MESSAGE,
        detail,
    };
};

export const isDeviceRejection = (error) => [401, 403].includes(error?.response?.status);
