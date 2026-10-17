export const isHeicFile = (file) => {
    const extension = file?.name?.split('.').pop()?.toLowerCase();
    const mimeType = file?.type?.toLowerCase();

    return ['heic', 'heif'].includes(extension)
        || ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'].includes(mimeType);
};

export const prepareImageUploadFile = async (file) => {
    if (!isHeicFile(file)) {
        return file;
    }

    const { default: convertHeic } = await import('heic2any');
    const converted = await convertHeic({
        blob: file,
        toType: 'image/jpeg',
        quality: 0.9,
    });
    const image = Array.isArray(converted) ? converted[0] : converted;

    if (!(image instanceof Blob)) {
        throw new Error('El archivo HEIC no produjo una imagen JPEG válida.');
    }

    const baseName = file.name.replace(/\.(heic|heif)$/i, '') || 'imagen';

    return new File([image], `${baseName}.jpg`, {
        type: 'image/jpeg',
        lastModified: file.lastModified,
    });
};

export const getUploadErrorMessage = (error, fallback) => {
    const errors = error?.response?.data?.errors ?? error?.errors;
    const validationMessages = errors && typeof errors === 'object'
        ? [...new Set(Object.values(errors).flat().filter((message) => typeof message === 'string'))]
        : [];

    if (validationMessages.length > 0) {
        return validationMessages.join(' ');
    }

    return error?.response?.data?.message
        || error?.message
        || fallback;
};
