<script setup>
import { computed } from 'vue';
import { Clock } from '@element-plus/icons-vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    day: {
        type: Object,
        default: null,
    },
    // The evidence photos are never shown to the collaborator; the map link
    // is optional per screen.
    showMap: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const title = computed(() => (props.day ? `Registros del ${props.day.date_label}` : 'Registros del día'));

const punches = computed(() => props.day?.punches ?? []);

const mapsLink = (punch) => (punch.latitude && punch.longitude
    ? `https://www.google.com/maps?q=${punch.latitude},${punch.longitude}`
    : null);
</script>

<template>
    <el-dialog v-model="visible" :title="title" width="520px" top="10vh" :close-on-click-modal="false">
        <div v-if="punches.length" class="space-y-3">
            <div
                v-for="punch in punches"
                :key="punch.id"
                class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 dark:border-[#2b2b2e] px-4 py-3"
            >
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-full bg-orange-50 dark:bg-orange-500/10 text-[#f26c17] flex items-center justify-center shrink-0">
                        <el-icon :size="16"><Clock /></el-icon>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                            {{ punch.time }} · {{ punch.type_label }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ punch.source_label }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <a
                        v-if="showMap && mapsLink(punch)"
                        :href="mapsLink(punch)"
                        target="_blank"
                        rel="noopener"
                        class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline"
                    >
                        Ver mapa
                    </a>
                </div>
            </div>
        </div>

        <el-empty v-else description="Sin registros para este día" :image-size="80" />

        <template #footer>
            <el-button @click="visible = false">Cerrar</el-button>
        </template>
    </el-dialog>
</template>
