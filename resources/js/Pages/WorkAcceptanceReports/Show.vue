<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Edit, Delete } from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import SignaturePad from '@/Components/Signature/SignaturePad.vue';
import PdfInstructionsDialog from '@/Components/PdfInstructionsDialog.vue';

const { can } = usePermissions();

const showPdfInstructions = ref(false);
const showSignModal = ref(false);
const showEditModal = ref(false);

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
    technicianNames: {
        type: Array,
        default: () => [],
    },
    isPublic: {
        type: Boolean,
        default: false,
    },
    submitUrl: {
        type: String,
        default: null,
    },
});

const contractorName = 'Construmax de Occidente S.A. de C.V.';
const contractorRfc = 'COC150717MI8';

const clientLogoUrl = computed(() => {
    const customer = props.report.ticket?.customer;
    if (customer?.media?.length) {
        return customer.media[0].original_url;
    }
    return null;
});

const managerName = computed(() => {
    return props.report.ticket?.contact?.name || '';
});

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('es-MX', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    });
};

const formatDateTime = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleString('es-MX', {
        year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
};

const handlePrint = () => {
    window.print();
};

// --- Responsive signature pad width ---
const signaturePadWidth = computed(() => {
    if (typeof window === 'undefined') return 500;
    return Math.min(500, window.innerWidth - 80);
});

// --- Signature form ---
const signForm = useForm({
    signature_data: '',
    signatory_name: '',
    manager_name: managerName.value,
    client_comments: '',
});

function openSignModal() {
    if (props.report.is_signed || !props.submitUrl) return;
    signForm.manager_name = managerName.value;
    signForm.signatory_name = '';
    signForm.client_comments = '';
    signForm.signature_data = '';
    showSignModal.value = true;
}

function submitSignature() {
    if (!signForm.signature_data) {
        ElMessage.warning('Por favor dibuje una firma antes de enviar.');
        return;
    }
    if (!signForm.signatory_name) {
        ElMessage.warning('Por favor ingrese el nombre de quien firma.');
        return;
    }

    signForm.post(props.submitUrl, {
        preserveState: false,
        onSuccess: () => {
            showSignModal.value = false;
            ElMessage.success('Acta de recepción firmada exitosamente.');
        },
        onError: () => {
            ElMessage.error('Error al firmar el acta de recepción.');
        },
    });
}

// --- Edit form (internal only) ---
const editForm = useForm({
    work_description: '',
    on_site_start: '',
    on_site_end: '',
    technician_comments: '',
});

function openEditModal() {
    editForm.work_description = props.report.work_description || '';
    editForm.on_site_start = props.report.on_site_start || '';
    editForm.on_site_end = props.report.on_site_end || '';
    editForm.technician_comments = props.report.technician_comments || '';
    showEditModal.value = true;
}

function submitEdit() {
    editForm.put(route('work-acceptance-reports.update', props.report.id), {
        preserveState: false,
        onSuccess: () => {
            showEditModal.value = false;
            ElMessage.success('Acta de recepción actualizada correctamente.');
        },
        onError: () => {
            ElMessage.error('Error al actualizar el acta de recepción.');
        },
    });
}

// --- Delete signature ---
function confirmDeleteSignature() {
    ElMessageBox.confirm(
        '¿Eliminar la firma de esta acta de recepción? El acta quedará pendiente de firma nuevamente.',
        'Confirmar',
        { confirmButtonText: 'Eliminar firma', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        router.delete(route('work-acceptance-reports.delete-signature', props.report.id), {
            preserveState: false,
            onSuccess: () => ElMessage.success('Firma eliminada correctamente.'),
            onError: () => ElMessage.error('Error al eliminar la firma.'),
        });
    }).catch(() => {});
}
</script>

<template>
    <Head :title="`Acta de recepción - ${report.ticket?.folio || ''}`" />

    <div class="min-h-screen bg-gray-100 print:bg-white p-4 print:p-0 font-sans text-[10px] text-gray-900 dark:text-gray-900">
        <!-- Top orange bar -->
        <div class="h-1 bg-[#f26c17] print:h-0.5 max-w-4xl mx-auto mb-3 rounded-t"></div>

        <div class="print:hidden flex justify-end max-w-4xl mx-auto mb-3 gap-2">
            <button
                v-if="!isPublic && can('tickets.edit')"
                @click="openEditModal"
                class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded shadow transition-colors text-sm flex items-center gap-1"
            >
                <el-icon :size="16"><Edit /></el-icon> Editar acta
            </button>
            <button
                v-if="report.is_signed && !isPublic && can('tickets.edit')"
                @click="confirmDeleteSignature"
                class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded shadow transition-colors text-sm flex items-center gap-1"
            >
                <el-icon :size="16"><Delete /></el-icon> Eliminar firma
            </button>
            <button
                @click="showPdfInstructions = true"
                class="bg-[#f26c17] hover:bg-[#d95d0f] text-white font-bold py-2 px-4 rounded shadow transition-colors text-sm"
            >
                Guardar como PDF / Imprimir
            </button>
        </div>

        <div class="max-w-4xl mx-auto bg-white dark:bg-white print:shadow-none shadow-lg p-5 print:p-0 text-gray-900 dark:text-gray-900 relative">

            <!-- Signed watermark overlay -->
            <div
                v-if="report.is_signed"
                class="absolute inset-0 flex items-center justify-center z-50 pointer-events-none print:flex"
            >
                <div class="transform -rotate-12 bg-green-500/10 border-4 border-green-500 rounded-lg px-8 py-3">
                    <span class="text-4xl font-black text-green-500 opacity-70 select-none">FIRMADO</span>
                </div>
            </div>

            <!-- HEADER: Company Logo + Title + Client Logo -->
            <div class="flex border border-[#b9b9b9] mb-1.5">
                <!-- Company Logo -->
                <div class="w-1/4 flex justify-center items-center border-r border-[#b9b9b9] p-0.5 bg-white dark:bg-white">
                    <ApplicationLogo class="h-auto w-full object-contain max-h-16" />
                </div>

                <!-- Title -->
                <div class="w-2/4 flex flex-col">
                    <div class="bg-[#f26c17] text-white text-center font-bold py-1.5 border-b border-[#b9b9b9] uppercase text-[11px]">
                        Acta de recepción
                    </div>
                    <div class="flex-1 flex items-center justify-center px-1.5 text-[7px] text-gray-500 text-center leading-tight">
                        Este formato es exclusivamente para el gerente de sucursal, es un documento que avala los trabajos realizados y autoriza los trámites correspondientes.
                    </div>
                </div>

                <!-- Client Logo -->
                <div class="w-1/4 flex justify-center items-center border-l border-[#b9b9b9] p-0.5 bg-white dark:bg-white">
                    <img
                        v-if="clientLogoUrl"
                        :src="clientLogoUrl"
                        class="h-auto max-h-14 w-full object-contain"
                        alt="Client logo"
                    />
                    <span v-else class="text-[7px] text-gray-400 text-center">Sin logo de cliente</span>
                </div>
            </div>

            <!-- CLIENT DATA -->
            <div class="flex border border-[#b9b9b9] mb-1.5">
                <div class="w-1/4 bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9] text-[10px]">Fecha</div>
                <div class="w-1/4 px-2 py-1 text-center font-bold text-[#f26c17] border-r border-[#b9b9b9] text-[10px]">{{ formatDate(report.report_date) }}</div>
                <div class="w-1/4 bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9] text-[10px]">Cliente</div>
                <div class="w-1/4 px-2 py-1 text-center font-bold text-[#f26c17] text-[10px]">{{ report.ticket?.customer?.name || 'N/D' }}</div>
            </div>

            <div class="flex border border-t-0 border-[#b9b9b9] mb-1.5">
                <div class="w-1/4 bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9] text-[10px]">Gerente de sucursal</div>
                <div class="w-1/4 px-2 py-1 text-center font-bold text-[#f26c17] border-r border-[#b9b9b9] text-[10px]">{{ managerName || 'N/D' }}</div>
                <div class="w-1/4 bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9] text-[10px]">Sucursal</div>
                <div class="w-1/4 px-2 py-1 text-center font-bold text-[#f26c17] text-[10px]">{{ report.ticket?.branch?.branch_name || 'N/D' }}</div>
            </div>

            <div class="flex border border-t-0 border-[#b9b9b9] mb-1.5">
                <div class="w-1/4 bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9] text-[10px]">Estado</div>
                <div class="w-1/4 px-2 py-1 text-center font-bold text-[#f26c17] border-r border-[#b9b9b9] text-[10px]">{{ report.ticket?.branch?.region || 'N/D' }}</div>
                <div class="w-1/4 bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9] text-[10px]">Ciudad</div>
                <div class="w-1/4 px-2 py-1 text-center font-bold text-[#f26c17] text-[10px]">{{ report.ticket?.branch?.city || 'N/D' }}</div>
            </div>

            <!-- CONTRACTOR DATA -->
            <div class="flex border border-[#b9b9b9] mb-1.5">
                <div class="w-1/4 bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9] text-[10px]">Contratista</div>
                <div class="w-1/2 px-2 py-1 text-center font-bold text-gray-900 border-r border-[#b9b9b9] text-[10px]">{{ contractorName }}</div>
                <div class="w-1/4 bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9] text-[10px]">RFC</div>
                <div class="w-1/4 px-2 py-1 text-center font-bold text-gray-900 text-[10px]">{{ contractorRfc }}</div>
            </div>

            <!-- PROJECT DATA -->
            <div class="flex border border-[#b9b9b9] mb-1.5">
                <div class="w-1/4 bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9] text-[10px]">No. de ticket</div>
                <div class="w-1/4 px-2 py-1 text-center font-bold text-[#f26c17] border-r border-[#b9b9b9] text-[10px]">{{ report.ticket?.folio || report.ticket?.id }}</div>
                <div class="w-1/4 bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9] text-[10px]">Tipo de servicio</div>
                <div class="w-1/4 px-2 py-1 text-center font-bold text-[#f26c17] text-[10px]">{{ report.ticket?.service_type || 'N/D' }}</div>
            </div>

            <!-- WORK DESCRIPTION -->
            <div class="border border-[#b9b9b9] mb-2">
                <div class="bg-[#b9b9b9] text-white px-2 py-1 font-bold border-b border-[#b9b9b9] text-[10px]">Descripción de trabajos realizados</div>
                <div class="p-2 min-h-[55px] whitespace-pre-line text-gray-900 leading-snug text-[10px]">
                    {{ report.work_description || 'Sin descripción registrada.' }}
                </div>
            </div>

            <!-- ON-SITE TIMESTAMPS -->
            <div class="flex border border-[#b9b9b9] mb-2">
                <div class="w-1/4 bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9] text-[10px]">Inicio en sitio</div>
                <div class="w-1/4 px-2 py-1 text-center font-bold text-[#f26c17] border-r border-[#b9b9b9] text-[10px]">{{ formatDateTime(report.on_site_start) }}</div>
                <div class="w-1/4 bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9] text-[10px]">Fin en sitio</div>
                <div class="w-1/4 px-2 py-1 text-center font-bold text-[#f26c17] text-[10px]">{{ formatDateTime(report.on_site_end) }}</div>
            </div>

            <!-- TECHNICIANS -->
            <div class="border border-[#b9b9b9] mb-2">
                <div class="bg-[#b9b9b9] text-white px-2 py-1 font-bold border-b border-[#b9b9b9] text-[10px]">Técnicos asignados</div>
                <div class="p-2 text-gray-900">
                    <ul class="list-disc pl-4 space-y-0.5">
                        <li v-for="name in technicianNames" :key="name" class="font-medium text-[10px]">{{ name }}</li>
                        <li v-if="technicianNames.length === 0" class="text-gray-400 text-[10px]">Sin técnicos asignados.</li>
                    </ul>
                </div>
            </div>

            <!-- TECHNICIAN COMMENTS -->
            <div class="border border-[#b9b9b9] mb-2">
                <div class="bg-[#b9b9b9] text-white px-2 py-1 font-bold border-b border-[#b9b9b9] text-[10px]">Comentarios del técnico</div>
                <div class="p-2 min-h-[35px] whitespace-pre-line text-gray-900 leading-snug text-[10px]">
                    {{ report.technician_comments || 'Sin comentarios.' }}
                </div>
            </div>

            <!-- CLIENT COMMENTS -->
            <div class="border border-[#b9b9b9] mb-2">
                <div class="bg-[#b9b9b9] text-white px-2 py-1 font-bold border-b border-[#b9b9b9] text-[10px]">Comentarios del cliente</div>
                <div class="p-2 min-h-[35px] whitespace-pre-line text-gray-900 leading-snug text-[10px]">
                    {{ report.client_comments || 'Sin comentarios.' }}
                </div>
            </div>

            <!-- FOOTER: DISCLAIMER -->
            <div class="border border-[#b9b9b9] p-2 mb-4 bg-orange-50">
                <p class="text-[7px] text-gray-600 text-center leading-tight font-medium">
                    Este formato es exclusivamente para el gerente de sucursal, es un documento que avala los trabajos realizados y autoriza los trámites correspondientes, ninguna empresa o contratista puede llenar este formato.
                </p>
            </div>

            <!-- SIGNATURES -->
            <div class="flex gap-4 mt-2">
                <!-- Branch Manager Signature -->
                <div class="flex-1 border border-[#b9b9b9]">
                    <div class="bg-[#f26c17] text-white text-center font-bold py-1 uppercase text-[10px]">Firma del gerente de sucursal</div>
                    <div
                        class="p-2"
                        :class="{ 'cursor-pointer hover:bg-orange-50 print:cursor-default': !report.is_signed && submitUrl }"
                        @click="openSignModal"
                    >
                        <div v-if="report.signature_url" class="mb-2 border border-gray-200 rounded">
                            <img :src="report.signature_url" class="w-full h-auto max-h-[80px] object-contain" alt="Manager signature" />
                        </div>
                        <div v-else class="mb-2 border border-dashed border-gray-300 rounded h-[55px] flex items-center justify-center text-gray-400 text-[9px]">
                            {{ report.is_signed ? 'Firma no disponible' : 'Clic para firmar' }}
                        </div>
                        <div class="text-center">
                            <p class="font-bold text-gray-900 text-[10px]">{{ report.signatory_name || '________________________' }}</p>
                            <p class="text-[8px] text-gray-500 mt-0.5">Nombre y firma</p>
                            <p v-if="report.signed_at" class="text-[8px] text-gray-400 mt-0.5">Firmado el {{ formatDateTime(report.signed_at) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Contractor Signature -->
                <div class="flex-1 border border-[#b9b9b9]">
                    <div class="bg-[#f26c17] text-white text-center font-bold py-1 uppercase text-[10px]">
                        Firma del contratista
                    </div>
                    <div class="p-2 flex flex-col items-center justify-center" style="min-height: 100px;">
                        <p class="font-bold text-gray-900 text-[10px] text-center">{{ contractorName }}</p>
                        <p class="text-[8px] text-gray-500 mt-0.5">Contratista</p>
                    </div>
                </div>
            </div>

            <!-- Signed metadata -->
            <div v-if="report.is_signed" class="mt-4 text-center">
                <p class="text-[7px] text-gray-400">
                    Documento firmado electrónicamente el {{ formatDateTime(report.signed_at) }}
                </p>
            </div>

        </div>

        <!-- Signature Modal -->
        <el-dialog
            v-model="showSignModal"
            title="Firmar acta de recepción"
            width="95%"
            :close-on-click-modal="false"
            class="print:hidden sign-modal"
        >
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre completo de quien firma <span class="text-red-500">*</span></label>
                    <el-input v-model="signForm.signatory_name" placeholder="Nombre completo" maxlength="255" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Firma digital <span class="text-red-500">*</span></label>
                    <SignaturePad v-model="signForm.signature_data" :width="signaturePadWidth" :height="160" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Comentarios del cliente</label>
                    <el-input v-model="signForm.client_comments" type="textarea" :rows="3" placeholder="Observaciones o comentarios..." maxlength="2000" show-word-limit />
                </div>
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-3">
                    <p class="text-xs text-orange-700 leading-relaxed">
                        Al firmar este documento, confirma que los trabajos descritos han sido realizados satisfactoriamente y autoriza los trámites correspondientes.
                    </p>
                </div>
            </div>
            <template #footer>
                <el-button @click="showSignModal = false">Cancelar</el-button>
                <el-button type="primary" :loading="signForm.processing" :disabled="!signForm.signature_data || !signForm.signatory_name" @click="submitSignature">
                    Firmar acta de recepción
                </el-button>
            </template>
        </el-dialog>

        <!-- Edit Modal (internal only) -->
        <el-dialog
            v-model="showEditModal"
            title="Editar acta de recepción"
            width="600px"
            :close-on-click-modal="false"
            class="print:hidden"
        >
            <el-alert
                type="info"
                :closable="false"
                show-icon
                class="mb-4"
            >
                <template #title>
                    Los datos del cliente, sucursal, tipo de servicio y técnicos se toman directamente del ticket.
                </template>
                Para modificarlos, edita el ticket y los cambios se reflejarán automáticamente en el acta de recepción.
            </el-alert>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descripción de trabajos realizados</label>
                    <el-input v-model="editForm.work_description" type="textarea" :rows="4" placeholder="Describe los trabajos realizados..." maxlength="5000" show-word-limit />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Inicio en sitio</label>
                        <el-date-picker v-model="editForm.on_site_start" type="datetime" placeholder="Seleccionar fecha y hora" class="w-full" format="DD/MM/YYYY HH:mm" value-format="YYYY-MM-DDTHH:mm:ss" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fin en sitio</label>
                        <el-date-picker v-model="editForm.on_site_end" type="datetime" placeholder="Seleccionar fecha y hora" class="w-full" format="DD/MM/YYYY HH:mm" value-format="YYYY-MM-DDTHH:mm:ss" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Comentarios del técnico</label>
                    <el-input v-model="editForm.technician_comments" type="textarea" :rows="3" placeholder="Comentarios del técnico..." maxlength="2000" show-word-limit />
                </div>
            </div>
            <template #footer>
                <el-button @click="showEditModal = false">Cancelar</el-button>
                <el-button type="primary" :loading="editForm.processing" @click="submitEdit">
                    Guardar cambios
                </el-button>
            </template>
        </el-dialog>

        <!-- PDF instructions dialog -->
        <PdfInstructionsDialog
            v-model="showPdfInstructions"
            @print="handlePrint"
        />
    </div>
</template>

<style>
@media (max-width: 640px) {
    .sign-modal {
        --el-dialog-width: 95% !important;
    }
    .sign-modal .signature-canvas-container {
        max-width: 100%;
        overflow: hidden;
    }
    .sign-modal .signature-canvas-container canvas {
        max-width: 100%;
        height: auto !important;
    }
}

@media (min-width: 641px) {
    .sign-modal {
        --el-dialog-width: 580px !important;
    }
}

@media print {
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    body {
        background-color: white !important;
    }
    @page {
        margin: 5mm;
        size: A4 portrait;
    }
}
</style>
