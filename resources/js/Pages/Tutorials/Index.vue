<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';
import { VideoPlay } from '@element-plus/icons-vue';

defineProps({
    videos: Array,
});

const activeVideo = ref(null);

const openVideo = (video) => {
    activeVideo.value = video;
};

const closeVideo = () => {
    activeVideo.value = null;
};
</script>

<template>
    <AppLayout title="Tutoriales">
        <div class="space-y-6">

            <!-- Header -->
            <div class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Tutoriales</h2>
                <p class="text-sm text-gray-500 mt-1">Explora los videos explicativos para conocer a detalle cada módulo del sistema.</p>
            </div>

            <!-- Videos grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div
                    v-for="video in videos"
                    :key="video.id"
                    class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden hover:shadow-md hover:border-[#f26c17] dark:hover:border-[#f26c17] transition-all cursor-pointer group"
                    @click="openVideo(video)"
                >
                    <!-- Thumbnail area -->
                    <div class="relative aspect-video bg-gray-100 dark:bg-[#27272a] flex items-center justify-center overflow-hidden">
                        <img
                            v-if="video.thumbnail"
                            :src="'/videos/' + video.thumbnail"
                            :alt="video.title"
                            class="w-full h-full object-cover"
                            @error="$event.target.style.display='none'"
                        />
                        <div class="absolute inset-0 flex items-center justify-center bg-black/20 group-hover:bg-black/40 transition-colors">
                            <div class="w-12 h-12 rounded-full bg-[#f26c17] flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                                <el-icon color="#ffffff" size="20"><VideoPlay /></el-icon>
                            </div>
                        </div>
                        <span class="absolute bottom-2 right-2 bg-black/70 text-white text-xs px-2 py-0.5 rounded">
                            {{ video.duration }}
                        </span>
                    </div>

                    <!-- Info -->
                    <div class="p-3">
                        <h3 class="font-semibold text-sm text-gray-800 dark:text-white truncate">{{ video.title }}</h3>
                        <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ video.description }}</p>
                    </div>
                </div>
            </div>

            <!-- Video player modal -->
            <el-dialog
                v-model="activeVideo"
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
                        :src="'/videos/' + activeVideo.filename"
                    >
                        Tu navegador no soporta la reproducción de video.
                    </video>
                </div>
                <div v-if="activeVideo" class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    <p>{{ activeVideo.description }}</p>
                    <p class="mt-1 text-xs text-gray-400">Duración: {{ activeVideo.duration }}</p>
                </div>
            </el-dialog>

        </div>
    </AppLayout>
</template>
