<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Camera, Close, WarningFilled } from '@element-plus/icons-vue';
import { ElMessageBox } from 'element-plus';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    user: {
        type: Object,
        required: true,
    },
    configured: {
        type: Boolean,
        default: true,
    },
    activeCount: {
        type: Number,
        default: 0,
    },
    selfService: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const MAX_PHOTOS = 3;

const form = useForm({
    photos: [],
});

const videoRef = ref(null);
const cameraReady = ref(false);
const deleting = ref(false);

let stream = null;

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const startCamera = async () => {
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { width: 640, height: 480, facingMode: 'user' },
            audio: false,
        });

        await nextTick();

        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            cameraReady.value = true;
        }
    } catch {
        cameraReady.value = false;
    }
};

const stopCamera = () => {
    if (stream) {
        stream.getTracks().forEach((track) => track.stop());
        stream = null;
    }

    cameraReady.value = false;
};

watch(visible, async (isVisible) => {
    if (isVisible) {
        form.reset();
        form.clearErrors();
        await startCamera();
    } else {
        stopCamera();
    }
});

const capture = () => {
    if (!cameraReady.value || !videoRef.value || form.photos.length >= MAX_PHOTOS) {
        return;
    }

    const video = videoRef.value;
    const canvas = document.createElement('canvas');
    canvas.width = 480;
    canvas.height = 360;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

    form.photos.push(canvas.toDataURL('image/jpeg', 0.8));
};

const removePhoto = (index) => {
    form.photos.splice(index, 1);
};

const submit = () => {
    if (form.photos.length === 0) {
        form.setError('photos', 'Captura al menos una foto del rostro.');
        return;
    }

    form.post(
        props.selfService
            ? route('payroll.my-attendance.faces.store')
            : route('payroll.faces.store', props.user.id),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                visible.value = false;
                emit('saved');
            },
        }
    );
};

const removeFaces = () => {
    ElMessageBox.confirm(
        'Se eliminarán las fotos registradas del rostro. El colaborador podrá registrarlo de nuevo.',
        'Eliminar registro facial',
        {
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            type: 'warning',
        }
    )
        .then(() => {
            deleting.value = true;

            router.delete(
                props.selfService
                    ? route('payroll.my-attendance.faces.destroy')
                    : route('payroll.faces.destroy', props.user.id),
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        visible.value = false;
                        emit('saved');
                    },
                    onFinish: () => {
                        deleting.value = false;
                    },
                }
            );
        })
        .catch(() => {});
};
</script>

<template>
    <el-dialog v-model="visible" title="Registro facial" width="640px" :close-on-click-modal="false">
        <el-alert
            v-if="!configured"
            type="warning"
            :closable="false"
            show-icon
            class="mb-4"
            title="El reconocimiento facial no está configurado"
            description="Captura las credenciales de AWS en el archivo .env para poder registrar rostros."
        />

        <div class="flex items-start justify-between mb-4">
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100">{{ user.name }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Rostros registrados:
                    <span class="font-medium">{{ activeCount }}</span>
                </p>
            </div>
            <el-button
                v-if="activeCount > 0"
                type="danger"
                plain
                size="small"
                :loading="deleting"
                @click="removeFaces"
            >
                Eliminar registro
            </el-button>
        </div>

        <div class="rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-black aspect-[4/3] flex items-center justify-center mb-4">
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

        <div class="flex items-center gap-3 mb-4">
            <el-button
                type="primary"
                :disabled="!cameraReady || !configured || form.photos.length >= MAX_PHOTOS"
                @click="capture"
            >
                Capturar foto
            </el-button>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ form.photos.length }} de {{ MAX_PHOTOS }} fotos · mira de frente y con buena iluminación
            </span>
        </div>

        <div v-if="form.photos.length > 0" class="flex gap-3 mb-4">
            <div
                v-for="(photo, index) in form.photos"
                :key="index"
                class="relative w-24 h-24 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700"
            >
                <img :src="photo" alt="Foto capturada" class="w-full h-full object-cover" />
                <button
                    type="button"
                    class="absolute top-1 right-1 bg-black/60 text-white rounded-full p-0.5"
                    title="Quitar foto"
                    @click="removePhoto(index)"
                >
                    <el-icon :size="14"><Close /></el-icon>
                </button>
            </div>
        </div>

        <el-alert
            v-if="form.errors.photos"
            type="error"
            :closable="false"
            show-icon
            class="mb-4"
            :title="form.errors.photos"
        />

        <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
            <el-icon><WarningFilled /></el-icon>
            Las fotos se envían a AWS Rekognition y se usan solo para identificar el marcaje de asistencia.
        </p>

        <template #footer>
            <el-button @click="visible = false">Cancelar</el-button>
            <el-button type="primary" :loading="form.processing" :disabled="!configured" @click="submit">
                Guardar registro facial
            </el-button>
        </template>
    </el-dialog>
</template>
