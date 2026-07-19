<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import SignaturePad from '@/Components/Signature/SignaturePad.vue';

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
    technicianNames: {
        type: Array,
        default: () => [],
    },
    submitUrl: {
        type: String,
        required: true,
    },
});

const form = useForm({
    signature_data: '',
    signatory_name: '',
    manager_name: props.report.ticket?.contact?.name || '',
    client_comments: '',
});

function submitSignature() {
    if (!form.signature_data) {
        ElMessage.warning('Por favor proporcione una firma antes de enviar.');
        return;
    }
    if (!form.signatory_name) {
        ElMessage.warning('Por favor ingrese el nombre de quien firma.');
        return;
    }

    form.post(props.submitUrl, {
        onSuccess: () => {
            ElMessage.success('Acta de recepción firmada exitosamente.');
        },
        onError: (errors) => {
            ElMessage.error('Error al firmar el acta de recepción.');
        },
    });
}

const contractorName = 'Construmax de Occidente S.A. de C.V.';
const contractorRfc = 'COC150717MI8';

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('es-MX', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatDateTime = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleString('es-MX', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head :title="`Firmar Acta de recepción - ${report.ticket?.folio || ''}`" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <div class="max-w-2xl mx-auto p-4 sm:p-6 lg:p-8">

            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                    Acta de recepción
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Ticket {{ report.ticket?.folio || report.ticket?.id }} — {{ report.ticket?.customer?.name }}
                </p>
            </div>

            <!-- Read-only summary card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6 dark:bg-gray-800 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-4 uppercase tracking-wide">Resumen de trabajos</h2>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Cliente</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">{{ report.ticket?.customer?.name || 'N/D' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Sucursal</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">{{ report.ticket?.branch?.branch_name || 'N/D' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Tipo de servicio</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">{{ report.ticket?.service_type || 'N/D' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Contratista</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">{{ contractorName }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Fecha del reporte</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">{{ formatDate(report.report_date) }}</dd>
                    </div>
                </dl>

                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Trabajos realizados</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ report.work_description || 'Sin descripción.' }}</p>
                </div>

                <div v-if="technicianNames.length" class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Técnicos</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ technicianNames.join(', ') }}</p>
                </div>
            </div>

            <!-- Signature form -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 dark:bg-gray-800 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-6 uppercase tracking-wide">Firma del gerente de sucursal</h2>

                <!-- Manager name -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Nombre del gerente
                    </label>
                    <input
                        v-model="form.manager_name"
                        type="text"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm"
                        placeholder="Nombre completo del gerente de sucursal"
                    />
                </div>

                <!-- Signature pad -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Firma digital
                    </label>
                    <SignaturePad
                        v-model="form.signature_data"
                        :width="600"
                        :height="180"
                    />
                </div>

                <!-- Signatory name -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Nombre de quien firma <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.signatory_name"
                        type="text"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm"
                        placeholder="Nombre completo"
                        required
                    />
                </div>

                <!-- Client comments -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Comentarios del cliente
                    </label>
                    <textarea
                        v-model="form.client_comments"
                        rows="3"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm"
                        placeholder="Observaciones, comentarios o retroalimentación..."
                    ></textarea>
                </div>

                <!-- Disclaimer -->
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 mb-6 dark:bg-orange-900/20 dark:border-orange-800">
                    <p class="text-xs text-orange-700 dark:text-orange-300 leading-relaxed">
                        Al firmar este documento, usted confirma que los trabajos descritos han sido realizados satisfactoriamente y autoriza los trámites correspondientes.
                    </p>
                </div>

                <!-- Submit -->
                <button
                    type="button"
                    @click="submitSignature"
                    :disabled="form.processing || !form.signature_data"
                    class="w-full bg-[#f26c17] hover:bg-[#d95d0f] disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold py-3 px-4 rounded-lg transition-colors"
                >
                    {{ form.processing ? 'Firmando...' : 'Firmar acta de recepción' }}
                </button>
            </div>

        </div>
    </div>
</template>
