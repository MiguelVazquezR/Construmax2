<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { Picture, UploadFilled } from '@element-plus/icons-vue';

const emit = defineEmits(['saved']);

const MAX_VIDEO_MB = 512;
const MAX_THUMBNAIL_MB = 4;

const visible = ref(false);
const editing = ref(null);
const formRef = ref(null);

// Tracks the picked files and forces the upload inputs to re-render on open.
const videoName = ref(null);
const videoInputKey = ref(0);
const thumbnailPreview = ref(null);
const thumbnailInputKey = ref(0);

const form = useForm({
    title: '',
    description: '',
    duration: '',
    video: null,
    thumbnail: null,
    remove_thumbnail: false,
});

const isEditing = computed(() => !!editing.value);

const dialogTitle = computed(() => (isEditing.value ? 'Editar tutorial' : 'Agregar tutorial'));

const rules = computed(() => ({
    title: [{ required: true, message: 'Escribe el título del tutorial.', trigger: 'blur' }],
    video: isEditing.value
        ? []
        : [{ required: true, message: 'Selecciona el archivo de video.', trigger: 'change' }],
}));

// Upload percentage while the multipart request is in flight (large videos can take a while).
const progressPercentage = computed(() => {
    const percentage = Number(form.progress?.percentage ?? 0);

    return Math.min(100, Math.max(0, Math.round(percentage)));
});

const fileName = (path) => (path ? path.split('/').pop() : null);

function open(tutorial = null) {
    editing.value = tutorial;
    form.reset();
    form.clearErrors();
    form.title = tutorial?.title ?? '';
    form.description = tutorial?.description ?? '';
    form.duration = tutorial?.duration ?? '';
    form.video = null;
    form.thumbnail = null;
    form.remove_thumbnail = false;
    videoName.value = null;
    thumbnailPreview.value = tutorial?.thumbnail_url ?? null;
    videoInputKey.value += 1;
    thumbnailInputKey.value += 1;
    visible.value = true;
}

defineExpose({ open });

const formatDuration = (seconds) => {
    if (!Number.isFinite(seconds) || seconds <= 0) {
        return '';
    }

    const total = Math.round(seconds);
    const minutes = Math.floor(total / 60);
    const rest = total % 60;

    return `${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
};

const ALLOWED_VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'mkv', 'webm'];

const isAllowedVideo = (file) => {
    if (String(file?.type ?? '').startsWith('video/')) {
        return true;
    }

    // Some browsers report an empty MIME type; fall back to the file extension.
    const extension = String(file?.name ?? '').split('.').pop()?.toLowerCase();

    return ALLOWED_VIDEO_EXTENSIONS.includes(extension);
};

const onVideoChange = (file) => {
    const raw = file?.raw;

    if (!raw || !isAllowedVideo(raw)) {
        ElMessage.error('El video debe ser un archivo MP4, MOV, AVI, MKV o WEBM.');
        return;
    }

    if (raw.size / 1024 / 1024 > MAX_VIDEO_MB) {
        ElMessage.error(`El video no debe superar los ${MAX_VIDEO_MB} MB.`);
        return;
    }

    form.video = raw;
    videoName.value = raw.name;
    form.clearErrors('video');

    // Detect the video duration to prefill the mm:ss field.
    const probeUrl = URL.createObjectURL(raw);
    const probe = document.createElement('video');
    probe.preload = 'metadata';
    probe.onloadedmetadata = () => {
        form.duration = formatDuration(probe.duration) || form.duration;
        URL.revokeObjectURL(probeUrl);
    };
    probe.onerror = () => URL.revokeObjectURL(probeUrl);
    probe.src = probeUrl;
};

const onThumbnailChange = (file) => {
    const raw = file?.raw;

    if (!raw || !String(raw.type).startsWith('image/')) {
        ElMessage.error('La miniatura debe ser una imagen.');
        return;
    }

    if (raw.size / 1024 / 1024 > MAX_THUMBNAIL_MB) {
        ElMessage.error(`La miniatura no debe superar los ${MAX_THUMBNAIL_MB} MB.`);
        return;
    }

    if (String(thumbnailPreview.value ?? '').startsWith('blob:')) {
        URL.revokeObjectURL(thumbnailPreview.value);
    }

    form.thumbnail = raw;
    form.remove_thumbnail = false;
    thumbnailPreview.value = URL.createObjectURL(raw);
    form.clearErrors('thumbnail');
};

const clearThumbnail = () => {
    if (String(thumbnailPreview.value ?? '').startsWith('blob:')) {
        URL.revokeObjectURL(thumbnailPreview.value);
    }

    form.thumbnail = null;
    form.remove_thumbnail = !!editing.value?.thumbnail_url;
    thumbnailPreview.value = null;
    thumbnailInputKey.value += 1;
};

function submit() {
    formRef.value?.validate((valid) => {
        if (!valid) {
            return;
        }

        const options = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success(
                    isEditing.value
                        ? 'Tutorial actualizado correctamente.'
                        : 'Tutorial agregado correctamente.',
                );
                visible.value = false;
                emit('saved');
            },
        };

        if (isEditing.value) {
            // PHP does not parse multipart bodies on real PUT requests, so the update is sent
            // as POST with method spoofing (_method=PUT). This keeps file uploads working.
            form.transform((data) => ({ ...data, _method: 'PUT' }))
                .post(route('tutorials.update', editing.value.id), options);

            return;
        }

        form.transform((data) => data);
        form.post(route('tutorials.store'), options);
    });
}
</script>

<template>
    <el-dialog
        v-model="visible"
        :title="dialogTitle"
        width="660px"
        top="6vh"
        destroy-on-close
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-position="top" size="default">
            <el-form-item label="Título" prop="title" :error="form.errors.title">
                <el-input
                    v-model="form.title"
                    maxlength="150"
                    show-word-limit
                    placeholder="Ej. Módulo de tickets"
                />
            </el-form-item>

            <el-form-item label="Descripción" prop="description" :error="form.errors.description">
                <el-input
                    v-model="form.description"
                    type="textarea"
                    :rows="3"
                    maxlength="2000"
                    show-word-limit
                    placeholder="Describe brevemente el contenido del tutorial."
                />
            </el-form-item>

            <el-form-item label="Duración (mm:ss)" prop="duration" :error="form.errors.duration">
                <el-input
                    v-model="form.duration"
                    placeholder="Se calcula al elegir el video; puedes ajustarla"
                    maxlength="10"
                />
            </el-form-item>

            <el-form-item label="Video" prop="video" :error="form.errors.video">
                <el-upload
                    :key="videoInputKey"
                    class="tutorial-video-upload w-full"
                    action="#"
                    drag
                    :auto-upload="false"
                    :show-file-list="false"
                    :on-change="onVideoChange"
                    accept="video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm"
                >
                    <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
                    <div class="el-upload__text">
                        <template v-if="videoName">
                            <strong>{{ videoName }}</strong>
                        </template>
                        <template v-else-if="isEditing">
                            Arrastra el video aquí o haz clic para <em>reemplazar</em> el actual
                        </template>
                        <template v-else>
                            Arrastra el video aquí o haz clic para seleccionarlo
                        </template>
                    </div>
                    <template #tip>
                        <div class="el-upload__tip">
                            MP4, MOV, AVI, MKV o WEBM · Máx. {{ MAX_VIDEO_MB }} MB.
                            <template v-if="isEditing && !videoName">
                                Video actual: {{ fileName(editing?.video_url) }}.
                            </template>
                        </div>
                    </template>
                </el-upload>
                <p v-if="isEditing && videoName" class="mt-1 text-xs text-gray-400">
                    El video anterior será reemplazado al guardar.
                </p>
            </el-form-item>

            <el-form-item label="Miniatura (opcional)" prop="thumbnail" :error="form.errors.thumbnail">
                <div class="w-full">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="tutorial-thumbnail-preview flex aspect-video w-44 items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-[#3f3f46] dark:bg-[#252529]">
                            <img
                                v-if="thumbnailPreview"
                                :src="thumbnailPreview"
                                alt="Miniatura del tutorial"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex flex-col items-center text-gray-400">
                                <el-icon :size="22"><Picture /></el-icon>
                                <span class="mt-1 text-[11px]">Sin miniatura</span>
                            </div>
                        </div>

                        <div class="flex flex-col items-start gap-2">
                            <el-upload
                                :key="thumbnailInputKey"
                                action="#"
                                :auto-upload="false"
                                :show-file-list="false"
                                :on-change="onThumbnailChange"
                                accept="image/jpeg,image/png,image/webp"
                            >
                                <el-button size="small">Seleccionar imagen</el-button>
                            </el-upload>

                            <el-button
                                v-if="thumbnailPreview"
                                size="small"
                                text
                                type="danger"
                                @click="clearThumbnail"
                            >
                                Quitar miniatura
                            </el-button>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">
                        JPG, PNG o WEBP · Máx. {{ MAX_THUMBNAIL_MB }} MB. Sin miniatura se muestra el icono de reproducción.
                    </p>
                </div>
            </el-form-item>
        </el-form>

        <template #footer>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <div
                    v-if="form.processing || form.progress"
                    class="mr-auto w-full sm:w-72"
                >
                    <el-progress
                        :percentage="progressPercentage"
                        :indeterminate="!form.progress"
                        :duration="2"
                        :stroke-width="8"
                    />
                    <p class="mt-1 text-xs text-gray-500">
                        Subiendo archivos. No cierres esta ventana.
                    </p>
                </div>
                <el-button @click="visible = false">Cancelar</el-button>
                <el-button type="primary" :loading="form.processing" @click="submit">
                    {{ isEditing ? 'Guardar cambios' : 'Agregar tutorial' }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<style scoped>
.tutorial-video-upload :deep(.el-upload) {
    width: 100%;
}

.tutorial-video-upload :deep(.el-upload-dragger) {
    width: 100%;
}
</style>
