<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Lock } from '@element-plus/icons-vue';

const props = defineProps({
    pendingSince: String,
    amount: String,
    currency: String,
    contactEmail: String,
    contactPhone: String,
});

const pendingSinceLabel = computed(() => {
    if (! props.pendingSince) return null;

    const date = new Date(`${props.pendingSince}T00:00:00`);

    if (Number.isNaN(date.getTime())) return null;

    return new Intl.DateTimeFormat('es-MX', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date);
});

const pendingAmountLabel = computed(() => {
    if (! props.amount) return null;

    const value = Number(props.amount);
    const currency = props.currency || 'MXN';

    if (Number.isNaN(value)) return `${props.amount} ${currency}`;

    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency,
    }).format(value);
});
</script>

<template>
    <AppLayout title="Recursos humanos en pausa">
        <template #header>
            <div>
                <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">
                    Recursos humanos
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Acceso temporalmente pausado.
                </p>
            </div>
        </template>

        <div class="max-w-3xl mx-auto py-6 sm:px-6 lg:px-8">
            <el-card shadow="never" class="!border-amber-200 dark:!border-amber-900/60">
                <div class="flex flex-col items-center gap-4 px-2 py-4 text-center sm:px-8">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-900/30">
                        <el-icon :size="32" class="text-amber-500"><Lock /></el-icon>
                    </div>

                    <h1 class="text-xl font-semibold text-gray-800 dark:text-white">
                        Este módulo está temporalmente pausado
                    </h1>

                    <p class="max-w-xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                        Lamentamos las molestias por la pausa de este módulo. Para reabrirlo solo falta liquidar
                        un saldo pendiente desde el
                        <span class="font-semibold">{{ pendingSinceLabel || '28 de septiembre de 2026' }}</span>.
                    </p>

                    <el-alert
                        v-if="pendingAmountLabel"
                        type="warning"
                        :closable="false"
                        show-icon
                        class="!w-full max-w-md"
                    >
                        <template #title>
                            Saldo pendiente: {{ pendingAmountLabel }}
                        </template>
                    </el-alert>

                    <p class="max-w-xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                        En cuanto se cubra el total, reabriremos todo el módulo de Recursos humanos y
                        podrás retomar tus actividades con total normalidad.
                    </p>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Gracias por tu paciencia y nuevamente nos disculpamos por los inconvenientes; estamos aquí para apoyarte si
                        tienes alguna duda.
                    </p>

                    <div class="mt-2 flex flex-wrap items-center justify-center gap-3">
                        <Link :href="route('dashboard')">
                            <el-button type="primary">Volver al inicio</el-button>
                        </Link>

                        <el-button
                            v-if="contactEmail"
                            tag="a"
                            :href="`mailto:${contactEmail}`"
                        >
                            Escríbenos a soporte
                        </el-button>

                        <el-button
                            v-if="contactPhone"
                            tag="a"
                            :href="`tel:${contactPhone}`"
                        >
                            Llámanos a soporte
                        </el-button>
                    </div>
                </div>
            </el-card>
        </div>
    </AppLayout>
</template>
