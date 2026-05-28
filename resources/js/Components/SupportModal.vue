<script setup>
import { ElDialog, ElButton } from 'element-plus';

defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <el-dialog
        :model-value="modelValue"
        @update:model-value="emit('update:modelValue', $event)"
        title="Soporte técnico"
        width="420px"
        :close-on-click-modal="false"
        center
    >
        <div class="flex flex-col items-center gap-5 py-2">
            <!-- Logo -->
            <img
                src="/images/DTW_logo_negro.webp"
                alt="DTW Logo"
                class="h-16 w-auto object-contain"
            />

            <!-- Description -->
            <p class="text-sm text-gray-600 text-center leading-relaxed">
                Puedes reportar cualquier error, solicitar soporte o solicitar nuevas funciones.
                Atendemos de <strong>lunes a viernes de 8:00 a. m. a 7:00 p. m.</strong>
            </p>

            <!-- Contact buttons -->
            <div class="flex flex-col gap-3 w-full">
                <!-- Email -->
                <a href="mailto:contacto@dtw.com.mx" class="w-full">
                    <el-button class="w-full" size="default">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                        contacto@dtw.com.mx
                    </el-button>
                </a>

                <!-- WhatsApp -->
                <a
                    href="https://wa.me/523315314045"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="w-full"
                >
                    <el-button type="success" class="w-full" size="default">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                        </svg>
                        33 1531 4045
                    </el-button>
                </a>
            </div>
        </div>

        <template #footer>
            <el-button @click="emit('update:modelValue', false)">
                Cerrar
            </el-button>
        </template>
    </el-dialog>
</template>
