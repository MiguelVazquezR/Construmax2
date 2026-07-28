<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PdfInstructionsDialog from '@/Components/PdfInstructionsDialog.vue';

const showPdfInstructions = ref(false);

// Slider index 0-5 maps to: 1, 2, 4, 6, 8, 10 images per page
const perPageOptions = [1, 2, 4, 6, 8, 10];
const sliderIndex = ref(2); // default: 4 images per page (index 2)

const imagesPerPage = computed(() => perPageOptions[sliderIndex.value]);
const sliderMarks = computed(() => {
    const marks = {};
    perPageOptions.forEach((val) => {
        marks[perPageOptions.indexOf(val)] = String(val);
    });
    return marks;
});

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

// Flat list of all images across all tasks, enriched with task name
const flattenedImages = computed(() => {
    if (!props.ticket.tasks) return [];

    const images = [];
    props.ticket.tasks.forEach(task => {
        const taskImages = (task.media || []).filter(m => m.mime_type?.startsWith('image/'));
        taskImages.forEach((img, imgIdx) => {
            images.push({
                ...img,
                taskName: task.name,
                imgIdx: imgIdx,
                totalInTask: taskImages.length,
            });
        });
    });
    return images;
});

// Group images into pages of N each
const pages = computed(() => {
    const chunks = [];
    const perPage = imagesPerPage.value;
    for (let i = 0; i < flattenedImages.value.length; i += perPage) {
        chunks.push(flattenedImages.value.slice(i, i + perPage));
    }
    return chunks;
});

// Grid layout based on images per page
const gridLayout = computed(() => {
    const n = imagesPerPage.value;
    switch (n) {
        case 1:  return { cols: 1, rows: 1 };
        case 2:  return { cols: 1, rows: 2 };
        case 4:  return { cols: 2, rows: 2 };
        case 6:  return { cols: 2, rows: 3 };
        case 8:  return { cols: 2, rows: 4 };
        case 10: return { cols: 2, rows: 5 };
        default: return { cols: 2, rows: 3 };
    }
});

const gridTemplateRows = computed(() => {
    return `repeat(${gridLayout.value.rows}, minmax(0, 1fr))`;
});

const gridTemplateCols = computed(() => {
    return `repeat(${gridLayout.value.cols}, minmax(0, 1fr))`;
});

// Height of the header block (gray bar + accent line) in print mode, in px
const HEADER_PRINT_HEIGHT = 120;

const pageHeight = (pageIdx) => {
    const fullPage = 'calc(297mm - 2cm)';
    if (pageIdx === 0) {
        return `calc(297mm - 2cm - ${HEADER_PRINT_HEIGHT}px)`;
    }
    return fullPage;
};

const pageMinHeight = (pageIdx) => {
    const fullMin = 'calc(100vh - 200px)';
    if (pageIdx === 0) {
        return `calc(100vh - 200px - ${HEADER_PRINT_HEIGHT}px)`;
    }
    return fullMin;
};
</script>

<template>
    <div class="min-h-screen bg-white print:bg-white">
        <Head title="Plantilla de evidencias" />

        <!-- Top orange bar -->
        <div class="h-2 bg-[#f26c17] print:hidden"></div>

        <div class="px-8 py-6 print:px-0 print:py-0">
            <!-- Controls bar (hidden when printing) -->
            <div class="flex items-center justify-between gap-4 mb-6 print:hidden">
                <!-- Images per page slider -->
                <div class="flex items-center gap-4">
                    <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">Imágenes por hoja</span>

                    <div class="w-56">
                        <el-slider
                            v-model="sliderIndex"
                            :min="0"
                            :max="5"
                            :step="1"
                            :marks="sliderMarks"
                            :format-tooltip="(idx) => perPageOptions[idx]"
                            show-stops
                            size="small"
                        />
                    </div>

                    <span class="text-sm text-gray-500 w-6 text-center tabular-nums font-semibold">
                        {{ imagesPerPage }}
                    </span>
                </div>

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
            <div class="bg-[#7a7a7a] rounded-t-lg px-6 py-5 print:bg-[#7a7a7a] print:rounded-none print:px-4 print:py-3">
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
            <div class="h-1 bg-[#f26c17]"></div>

            <!-- Evidence pages -->
            <div class="mt-0">
                <div v-if="pages.length > 0">
                    <div
                        v-for="(page, pageIdx) in pages"
                        :key="pageIdx"
                        class="evidence-page print:mb-0"
                        :class="{ 'page-break': pageIdx < pages.length - 1 }"
                        :style="{
                            height: pageHeight(pageIdx),
                            minHeight: pageMinHeight(pageIdx),
                        }"
                    >
                        <div
                            class="grid gap-3 print:gap-2 h-full"
                            :style="{
                                gridTemplateColumns: gridTemplateCols,
                                gridTemplateRows: gridTemplateRows,
                            }"
                        >
                            <div
                                v-for="(img, imgIdx) in page"
                                :key="img.id"
                                class="border border-gray-200 rounded-lg overflow-hidden bg-gray-50 flex flex-col print:rounded-none print:border-gray-300"
                            >
                                <div class="flex-1 bg-white flex items-center justify-center p-3 print:p-2 min-h-0">
                                    <img
                                        :src="img.original_url"
                                        :alt="img.file_name"
                                        class="max-w-full max-h-full object-contain"
                                    />
                                </div>
                                <div class="p-2 print:p-1.5 border-t border-gray-200 shrink-0">
                                    <p class="text-xs print:text-[9px] font-semibold text-[#7a7a7a] truncate">{{ img.taskName }}</p>
                                    <p class="text-[10px] print:text-[8px] text-gray-400">
                                        Imagen {{ img.imgIdx + 1 }} de {{ img.totalInTask }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-16 print:py-8">
                    <p class="text-gray-400">No hay evidencias registradas para este ticket.</p>
                </div>
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
    @page { margin: 1cm; size: A4; }

    .evidence-page {
        overflow: hidden;
        break-inside: avoid;
    }

    .evidence-page.page-break {
        page-break-after: always;
    }
}

/* Screen preview: each page fills the viewport */
.evidence-page {
    break-inside: avoid;
}

@media screen {
    .evidence-page.page-break {
        page-break-after: always;
    }
}
</style>