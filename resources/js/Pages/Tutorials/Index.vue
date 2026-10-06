<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Delete, Edit, MoreFilled, Plus, VideoPlay } from '@element-plus/icons-vue';
import TutorialFormDialog from './Partials/TutorialFormDialog.vue';

const props = defineProps({
    videos: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const activeVideo = ref(null);
const videoDialogVisible = ref(false);
const formDialogRef = ref(null);

const openVideo = (video) => {
    if (!video.video_available) {
        ElMessage.warning(
            props.can?.edit
                ? 'El archivo de video no está disponible. Edita el tutorial para subirlo o reemplazarlo.'
                : 'El video de este tutorial aún no está disponible.',
        );

        return;
    }

    activeVideo.value = video;
    videoDialogVisible.value = true;
};

const openCreateDialog = () => {
    formDialogRef.value?.open();
};

const openEditDialog = (video) => {
    formDialogRef.value?.open(video);
};

const deleteVideo = (video) => {
    ElMessageBox.confirm(
        `¿Eliminar el tutorial "${video.title}"? Esta acción no se puede deshacer.`,
        'Eliminar tutorial',
        {
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            type: 'warning',
        },
    ).then(() => {
        router.delete(route('tutorials.destroy', video.id), {
            preserveScroll: true,
            onSuccess: () => ElMessage.success('Tutorial eliminado correctamente.'),
        });
    }).catch(() => {});
};

const handleCommand = (command, video) => {
    if (command === 'edit' && props.can?.edit) {
        openEditDialog(video);
    } else if (command === 'delete' && props.can?.delete) {
        deleteVideo(video);
    }
};
</script>

<template>
    <AppLayout title="Tutoriales">
        <div class="space-y-6">

            <!-- Header -->
            <div class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Tutoriales</h2>
                        <p class="text-sm text-gray-500 mt-1">Explora los videos explicativos para conocer a detalle cada módulo del sistema.</p>
                    </div>
                    <el-button
                        v-if="can.create"
                        type="primary"
                        :icon="Plus"
                        @click="openCreateDialog"
                    >
                        Agregar tutorial
                    </el-button>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-if="videos.length === 0"
                class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-10"
            >
                <el-empty description="Aún no hay tutoriales">
                    <el-button v-if="can.create" type="primary" :icon="Plus" @click="openCreateDialog">
                        Agregar el primer tutorial
                    </el-button>
                </el-empty>
            </div>

            <!-- Videos grid -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div
                    v-for="video in videos"
                    :key="video.id"
                    class="relative bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden hover:shadow-md hover:border-[#f26c17] dark:hover:border-[#f26c17] transition-all cursor-pointer group"
                    @click="openVideo(video)"
                >
                    <!-- Thumbnail area -->
                    <div class="relative aspect-video bg-gray-100 dark:bg-[#27272a] flex items-center justify-center overflow-hidden">
                        <img
                            v-if="video.thumbnail_url"
                            :src="video.thumbnail_url"
                            :alt="video.title"
                            class="w-full h-full object-cover"
                            @error="$event.target.style.display='none'"
                        />
                        <div class="absolute inset-0 flex items-center justify-center bg-black/20 group-hover:bg-black/40 transition-colors">
                            <div class="w-12 h-12 rounded-full bg-[#f26c17] flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                                <el-icon color="#ffffff" size="20"><VideoPlay /></el-icon>
                            </div>
                        </div>
                        <span
                            v-if="video.duration"
                            class="absolute bottom-2 right-2 bg-black/70 text-white text-xs px-2 py-0.5 rounded"
                        >
                            {{ video.duration }}
                        </span>
                        <span
                            v-if="!video.video_available"
                            class="absolute top-2 right-2 bg-amber-500/90 text-white text-[11px] px-2 py-0.5 rounded"
                        >
                            Video no disponible
                        </span>

                        <!-- Manage actions -->
                        <el-dropdown
                            v-if="can.edit || can.delete"
                            trigger="click"
                            @command="(command) => handleCommand(command, video)"
                        >
                            <el-button
                                class="tutorial-actions absolute top-2 left-2"
                                circle
                                size="small"
                                :icon="MoreFilled"
                                title="Acciones del tutorial"
                                @click.stop
                            />
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item v-if="can.edit" command="edit" :icon="Edit">Editar</el-dropdown-item>
                                    <el-dropdown-item v-if="can.delete" command="delete" :icon="Delete" :divided="can.edit">Eliminar</el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </div>

                    <!-- Info -->
                    <div class="p-3">
                        <h3 class="font-semibold text-sm text-gray-800 dark:text-white truncate">{{ video.title }}</h3>
                        <p v-if="video.description" class="text-xs text-gray-500 mt-1 line-clamp-2">{{ video.description }}</p>
                    </div>
                </div>
            </div>

            <!-- Video player modal -->
            <el-dialog
                v-model="videoDialogVisible"
                :title="activeVideo?.title"
                width="800px"
                destroy-on-close
                center
            >
                <div v-if="activeVideo" class="aspect-video bg-black rounded overflow-hidden">
                    <video
                        :key="activeVideo.id"
                        controls
                        autoplay
                        class="w-full h-full"
                        :src="activeVideo.video_url"
                    >
                        Tu navegador no soporta la reproducción de video.
                    </video>
                </div>
                <div v-if="activeVideo" class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    <p v-if="activeVideo.description">{{ activeVideo.description }}</p>
                    <p v-if="activeVideo.duration" class="mt-1 text-xs text-gray-400">Duración: {{ activeVideo.duration }}</p>
                </div>
            </el-dialog>

            <!-- Create / edit dialog -->
            <TutorialFormDialog ref="formDialogRef" />
        </div>
    </AppLayout>
</template>

<style scoped>
.tutorial-actions {
    --el-button-bg-color: rgba(0, 0, 0, 0.45);
    --el-button-border-color: rgba(255, 255, 255, 0.35);
    --el-button-text-color: #ffffff;
    --el-button-hover-bg-color: rgba(0, 0, 0, 0.7);
    --el-button-hover-border-color: rgba(255, 255, 255, 0.6);
    --el-button-hover-text-color: #ffffff;
}
</style>

