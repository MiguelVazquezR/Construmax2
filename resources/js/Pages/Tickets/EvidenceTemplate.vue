<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PdfInstructionsDialog from '@/Components/PdfInstructionsDialog.vue';

const showPdfInstructions = ref(false);

const handlePrintModal = () => {
    showPdfInstructions.value = true;
};

const handlePrint = () => {
    window.print();
};

const props = defineProps({
    ticket: Object,
});

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('es-MX', {
        year: 'numeric', month: 'long', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};

const getLogoUrl = () => {
    if (!props.ticket.budget?.customer?.media) return null;
    const logo = props.ticket.budget.customer.media.find(m => m.collection_name === 'logo');
    return logo ? logo.original_url : null;
};

// Reunir todas las evidencias de todas las tareas, ordenadas cronológicamente
const allEvidences = computed(() => {
    const items = [];
    if (props.ticket.tasks) {
        props.ticket.tasks.forEach(task => {
            if (task.media && task.media.length > 0) {
                task.media.forEach(media => {
                    items.push({
                        task_name: task.name,
                        task_status: task.status,
                        date: task.updated_at || task.created_at,
                        url: media.original_url,
                        file_name: media.file_name,
                    });
                });
            }
        });
    }
    // También incluir evidencias generales del ticket
    if (props.ticket.media) {
        props.ticket.media.forEach(media => {
            if (media.mime_type?.startsWith('image/')) {
                items.push({
                    task_name: 'Archivo general',
                    task_status: '',
                    date: media.created_at,
                    url: media.original_url,
                    file_name: media.file_name,
                });
            }
        });
    }
    items.sort((a, b) => new Date(a.date) - new Date(b.date));
    return items;
});
</script>

<template>
    <div class="min-h-screen bg-white print:bg-white">
        <Head title="Plantilla de evidencias" />

        <!-- Top orange bar -->
        <div class="h-2 bg-[#f26c17] print:h-1"></div>

        <div class="px-8 py-6 print:px-4 print:py-3">
            <!-- Print / PDF button (hidden when printing) -->
            <div class="flex items-center justify-end gap-3 mb-6 print:hidden">
                <el-button
                    type="primary"
                    color="#f26c17"
                    size="large"
                    @click="handlePrintModal"
                >
                    Guardar como PDF / Imprimir
                </el-button>
            </div>

            <!-- Header with corporate styling -->
            <div class="bg-[#1e1e20] rounded-t-lg px-6 py-5 print:bg-[#1e1e20] print:px-4 print:py-3">
                <div class="flex items-center gap-5">
                    <div
                        v-if="getLogoUrl()"
                        class="w-16 h-16 rounded-lg overflow-hidden border-2 border-white/20 bg-white shrink-0 print:w-14 print:h-14"
                    >
                        <img :src="getLogoUrl()" alt="Logo" class="w-full h-full object-contain" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white print:text-white">Recopilación de evidencias</h1>
                        <p class="text-sm text-orange-300 mt-0.5">{{ ticket.name }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Cliente: {{ ticket.customer?.name }} | {{ ticket.branch?.branch_name }}
                            <template v-if="ticket.branch?.city"> - {{ ticket.branch.city }}</template>
                            <template v-if="ticket.branch?.region">, {{ ticket.branch.region }}</template>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Thin orange accent line -->
            <div class="h-1 bg-[#f26c17] print:h-0.5"></div>

            <!-- Evidence grid -->
            <div class="mt-6 print:mt-4">
                <div v-if="allEvidences.length > 0">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 print:gap-3">
                        <div
                            v-for="(item, index) in allEvidences"
                            :key="index"
                            class="border border-gray-200 rounded-lg overflow-hidden bg-gray-50 break-inside-avoid"
                        >
                            <div class="aspect-square bg-white flex items-center justify-center p-2">
                                <img
                                    :src="item.url"
                                    :alt="item.file_name"
                                    class="max-w-full max-h-full object-contain"
                                />
                            </div>
                            <div class="p-3 border-t border-gray-200">
                                <p class="text-xs font-semibold text-[#1e1e20] truncate">{{ item.task_name }}</p>
                                <p class="text-[10px] text-gray-400">{{ formatDate(item.date) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-16">
                    <p class="text-gray-400">No hay evidencias registradas para este ticket.</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-10 pt-5 border-t border-gray-200 text-center print:mt-6">
                <p class="text-xs text-gray-400">
                    Generado el {{ new Date().toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' }) }}
                </p>
                <div class="mt-2 h-1 w-16 mx-auto bg-[#f26c17]/60 rounded-full"></div>
            </div>
        </div>

        <!-- Reusable PDF instructions dialog -->
        <PdfInstructionsDialog
            v-model="showPdfInstructions"
            @print="handlePrint"
        />
    </div>
</template>

<style>
@media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @page { margin: 1cm; }
}
</style>