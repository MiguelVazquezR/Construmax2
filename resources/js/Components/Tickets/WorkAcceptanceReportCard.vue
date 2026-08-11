<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { DocumentChecked, Share, View } from '@element-plus/icons-vue';
import axios from 'axios';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
});

const generating = ref(false);
const copyingLink = ref(false);

const report = props.ticket.work_acceptance_report || null;
const hasReport = !!report;
const isSigned = report?.is_signed || false;

function generateReport() {
    generating.value = true;
    router.post(route('work-acceptance-reports.store'), {
        ticket_id: props.ticket.id,
    }, {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Acta de recepción generada correctamente.');
        },
        onError: () => {
            ElMessage.error('Error al generar el acta de recepción.');
        },
        onFinish: () => {
            generating.value = false;
        },
    });
}

function viewReport() {
    if (report?.id) {
        window.open(route('work-acceptance-reports.show', report.id), '_blank');
    }
}

async function generateShareLink() {
    if (!report?.id) return;

    copyingLink.value = true;
    try {
        const response = await axios.post(
            route('work-acceptance-reports.generate-link', report.id),
            {},
            { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } }
        );
        const publicLink = response.data.url;
        if (publicLink) {
            await navigator.clipboard.writeText(publicLink);
            ElMessage.success({
                message: 'Enlace copiado al portapapeles. Compártelo con el gerente de sucursal para que firme el acta.',
                duration: 5000,
            });
        }
    } catch {
        ElMessage.error('Error al generar el enlace público.');
    } finally {
        copyingLink.value = false;
    }
}
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] rounded-lg border border-gray-100 dark:border-gray-700 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <el-icon class="text-[#f26c17]"><DocumentChecked /></el-icon>
                Acta de recepción
            </h3>
            <el-tag v-if="isSigned" type="success" size="small" effect="light" class="!rounded-full">
                Firmado
            </el-tag>
            <el-tag v-else-if="hasReport" type="warning" size="small" effect="light" class="!rounded-full">
                Pendiente de firma
            </el-tag>
        </div>

        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            Documento de aceptación de trabajos realizados. El gerente de sucursal debe firmarlo para avalar los trabajos y autorizar los trámites correspondientes.
        </p>

        <!-- No report yet: generate button -->
        <div v-if="!hasReport" class="text-center py-4">
            <el-button
                type="primary"
                :loading="generating"
                @click="generateReport"
                :icon="DocumentChecked"
            >
                Generar acta de recepción
            </el-button>
            <p class="text-xs text-gray-400 mt-2">
                Se generará con los datos disponibles del ticket y las tareas.
            </p>
        </div>

        <!-- Report exists -->
        <div v-else class="flex flex-wrap gap-2">
            <el-button
                type="primary"
                :icon="View"
                @click="viewReport"
            >
                Ver acta de recepción
            </el-button>
            <el-button
                :icon="Share"
                :loading="copyingLink"
                @click="generateShareLink"
                plain
            >
                Copiar enlace público
            </el-button>
            <span class="text-xs text-gray-400 flex items-center ml-auto">
                {{ isSigned ? 'Firmado el ' + new Date(report.signed_at).toLocaleString('es-MX') : 'Pendiente de firma' }}
            </span>
        </div>
    </div>
</template>
