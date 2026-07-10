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

// Tasks with at least one image evidence, in natural order.
// Within each task, media is already ordered by order_column via the model.
const tasksWithImages = computed(() => {
    if (!props.ticket.tasks) return [];

    return props.ticket.tasks
        .map(task => ({
            ...task,
            images: (task.media || []).filter(m => m.mime_type?.startsWith('image/')),
        }))
        .filter(task => task.images.length > 0);
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
            <div class="bg-[#7a7a7a] rounded-t-lg px-6 py-5 print:bg-[#7a7a7a] print:px-4 print:py-3">
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
                        <p class="text-xs text-gray-300 mt-0.5">
                            Cliente: {{ ticket.customer?.name }} | {{ ticket.branch?.branch_name }}
                            <template v-if="ticket.branch?.city"> - {{ ticket.branch.city }}</template>
                            <template v-if="ticket.branch?.region">, {{ ticket.branch.region }}</template>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Thin orange accent line -->
            <div class="h-1 bg-[#f26c17] print:h-0.5"></div>

            <!-- Evidence by task -->
            <div class="mt-6 print:mt-4">
                <div v-if="tasksWithImages.length > 0">
                    <div
                        v-for="(task, taskIdx) in tasksWithImages"
                        :key="task.id"
                        class="mb-8 print:mb-4 break-inside-avoid"
                    >
                        <!-- Task header -->
                        <div class="flex items-center gap-3 mb-3 print:mb-1.5">
                            <span class="flex items-center justify-center w-6 h-6 print:w-5 print:h-5 rounded-full bg-[#f26c17] text-white text-xs print:text-[10px] font-bold shrink-0">
                                {{ taskIdx + 1 }}
                            </span>
                            <h3 class="text-sm print:text-xs font-bold text-[#7a7a7a] uppercase tracking-wide">
                                {{ task.name }}
                            </h3>
                            <span class="text-xs print:text-[10px] text-gray-400">
                                {{ task.images.length }} {{ task.images.length === 1 ? 'imagen' : 'imágenes' }}
                            </span>
                        </div>

                        <!-- Images for this task -->
                        <div
                            :class="[
                                'grid gap-4 print:gap-2',
                                task.images.length === 1
                                    ? 'grid-cols-1'
                                    : 'grid-cols-1 sm:grid-cols-3'
                            ]"
                        >
                            <div
                                v-for="(img, imgIdx) in task.images"
                                :key="img.id"
                                class="border border-gray-200 rounded-lg overflow-hidden bg-gray-50"
                            >
                                <div
                                    :class="[
                                        'bg-white flex items-center justify-center p-2 print:p-2',
                                        task.images.length === 1 ? 'h-80 print:h-64' : 'h-48 print:h-32'
                                    ]"
                                >
                                    <img
                                        :src="img.original_url"
                                        :alt="img.file_name"
                                        class="max-w-full max-h-full object-contain"
                                    />
                                </div>
                                <div class="p-2 print:p-1.5 border-t border-gray-200">
                                    <p class="text-xs print:text-[9px] font-semibold text-[#7a7a7a] truncate">{{ task.name }}</p>
                                    <p class="text-[10px] print:text-[8px] text-gray-400">
                                        Imagen {{ imgIdx + 1 }} de {{ task.images.length }}
                                    </p>
                                </div>
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