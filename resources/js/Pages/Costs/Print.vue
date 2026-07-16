<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import PdfInstructionsDialog from '@/Components/PdfInstructionsDialog.vue';

const showPdfInstructions = ref(false);

const props = defineProps({
    budget: {
        type: Object,
        required: true,
    },
    version: {
        type: [String, Number],
        default: null,
    },
});

const catalog = computed(() => {
    if (props.version && props.budget.catalogs) {
        return props.budget.catalogs.find(c => String(c.version) === String(props.version)) || props.budget.latest_catalog;
    }
    return props.budget.latest_catalog;
});

const isApproved = computed(() => catalog.value?.is_approved === true);

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('es-MX');
};

const handlePrint = () => {
    window.print();
};
</script>

<template>
    <Head :title="`Impresión Catálogo - ${budget.ticket?.folio}`" />
    
    <div class="min-h-screen bg-gray-100 print:bg-white p-4 print:p-0 font-sans text-xs text-gray-900 dark:text-gray-900">
        <!-- Top orange bar -->
        <div class="h-1.5 bg-[#f26c17] print:h-1 max-w-5xl mx-auto mb-4 rounded-t"></div>

        <div class="print:hidden flex justify-end max-w-5xl mx-auto mb-4">
            <button @click="showPdfInstructions = true" class="bg-[#f26c17] hover:bg-[#d95d0f] text-white font-bold py-2 px-4 rounded shadow transition-colors">
                Guardar como PDF / Imprimir
            </button>
        </div>

        <div class="max-w-5xl mx-auto bg-white dark:bg-white print:shadow-none shadow-lg p-8 print:p-0 text-gray-900 dark:text-gray-900 relative">

            <!-- Approval watermark overlay -->
            <div v-if="!isApproved" class="absolute inset-0 flex items-center justify-center z-50 pointer-events-none print:flex">
                <div class="transform -rotate-12 bg-red-500/10 border-4 border-red-500 rounded-lg px-12 py-4">
                    <span class="text-6xl font-black text-red-500 opacity-90 select-none">ESPERANDO APROBACIÓN</span>
                </div>
            </div>

            <!-- Header principal -->
            <div class="flex border-2 border-[#b9b9b9] mb-2">
                <!-- Logo Area -->
                <div class="w-1/3 flex justify-center items-center border-r-2 border-[#b9b9b9] p-1 bg-white dark:bg-white">
                    <ApplicationLogo class="h-auto w-full object-contain" />
                </div>
                
                <!-- Info Area -->
                <div class="w-2/3 flex flex-col">
                    <div class="bg-[#f26c17] text-white text-center font-bold py-1 border-b-2 border-[#b9b9b9] uppercase">
                        PRESUPUESTO
                    </div>
                    
                    <div class="flex border-b border-[#b9b9b9] flex-1">
                        <div class="w-1/4 bg-[#b9b9b9] text-white px-2 py-1.5 font-bold border-r border-[#b9b9b9] flex items-center">Nombre</div>
                        <div class="w-3/4 px-2 py-1.5 text-center font-bold text-[#f26c17] flex items-center justify-center truncate">{{ budget.ticket?.branch?.branch_name || 'N/D' }}</div>
                    </div>

                    <div class="flex border-b border-[#b9b9b9] flex-1">
                        <div class="w-1/4 bg-[#b9b9b9] text-white px-2 py-1.5 font-bold border-r border-[#b9b9b9] flex items-center">Ciudad</div>
                        <div class="w-3/4 px-2 py-1.5 text-center font-bold text-[#f26c17] flex items-center justify-center truncate">{{ budget.ticket?.branch?.city || 'N/D' }}</div>
                    </div>
                    
                    <div class="flex flex-1">
                        <div class="w-1/4 bg-[#b9b9b9] text-white px-2 py-1.5 font-bold border-r border-[#b9b9b9] flex items-center">Estado</div>
                        <div class="w-1/4 px-2 py-1.5 text-center font-bold text-[#f26c17] border-r border-[#b9b9b9] flex items-center justify-center uppercase">{{ budget.ticket?.branch?.region || 'N/D' }}</div>
                        <div class="w-1/4 bg-[#b9b9b9] text-white px-2 py-1.5 font-bold border-r border-[#b9b9b9] flex items-center">País</div>
                        <div class="w-1/4 px-2 py-1.5 text-center font-bold text-[#f26c17] flex items-center justify-center uppercase">{{ budget.ticket?.branch?.country || 'N/D' }}</div>
                    </div>
                </div>
            </div>

            <!-- Provider & General Data -->
            <div class="flex border-2 border-[#b9b9b9] border-b text-[11px] mt-2 bg-orange-50">
                <div class="w-[15%] bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9]">Proveedor / Cliente</div>
                <div class="w-[35%] px-2 py-1 text-center font-bold border-r border-[#b9b9b9] text-gray-900">{{ budget.ticket?.customer?.name || 'N/D' }}</div>
                <div class="w-[10%] bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9] text-center">RFC</div>
                <div class="w-[20%] px-2 py-1 text-center font-bold border-r border-[#b9b9b9] text-gray-900">{{ budget.ticket?.customer?.rfc || 'N/D' }}</div>
                <div class="w-[10%] bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9] text-center">No. Reporte</div>
                <div class="w-[10%] px-2 py-1 text-center font-bold text-gray-900">{{ budget.ticket?.report_number || budget.ticket?.folio || budget.ticket?.id }}</div>
            </div>

            <div class="flex border-2 border-t-0 border-[#b9b9b9] text-[11px] bg-orange-50">
                <div class="w-[15%] bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9]">Tipo de servicio</div>
                <div class="w-[35%] px-2 py-1 text-center font-bold border-r border-[#b9b9b9] uppercase text-gray-900">{{ budget.ticket?.service_type || 'N/D' }}</div>
                <div class="w-[10%] bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9] text-center flex items-center justify-center">Fecha inicio</div>
                <div class="w-[20%] px-2 py-1 text-center font-bold border-r border-[#b9b9b9] flex items-center justify-center text-gray-900">{{ formatDate(budget.ticket?.scheduled_start) }}</div>
                <div class="w-[10%] bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9] text-center flex items-center justify-center">Fecha fin</div>
                <div class="w-[10%] px-2 py-1 text-center font-bold flex items-center justify-center text-gray-900">{{ formatDate(budget.ticket?.scheduled_end) }}</div>
            </div>

            <div class="flex border-2 border-t-0 border-[#b9b9b9] text-[11px] bg-orange-50 mb-4">
                <div class="w-[15%] bg-[#b9b9b9] text-white px-2 py-1 font-bold border-r border-[#b9b9b9]">Servicio</div>
                <div class="flex-1 px-2 py-1 text-center font-bold uppercase text-gray-900">{{ budget.ticket?.name || 'N/D' }}</div>
            </div>

            <!-- Items Table -->
            <table class="w-full text-[11px] border-collapse border-2 border-[#b9b9b9] text-gray-900">
                <thead>
                    <tr class="bg-[#f26c17] text-white text-center">
                        <th class="border border-[#b9b9b9] py-2 w-16">PARTIDA<br>CLAVE</th>
                        <th class="border border-[#b9b9b9] py-2">DESCRIPCIÓN</th>
                        <th class="border border-[#b9b9b9] py-2 w-20">UNIDAD</th>
                        <th class="border border-[#b9b9b9] py-2 w-20">CANTIDAD</th>
                        <th class="border border-[#b9b9b9] py-2 w-24">P.U.</th>
                        <th class="border border-[#b9b9b9] py-2 w-28">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in catalog?.items" :key="item.id" class="text-center">
                        <td class="border border-[#b9b9b9] py-1.5 px-1 text-gray-900">{{ index + 1 }}</td>
                        <td class="border border-[#b9b9b9] py-1.5 px-2 text-left leading-tight break-words text-gray-900">{{ item.description }}</td>
                        <td class="border border-[#b9b9b9] py-1.5 px-1 text-gray-900">{{ item.unit }}</td>
                        <td class="border border-[#b9b9b9] py-1.5 px-1 text-gray-900">{{ Number(item.quantity).toFixed(2) }}</td>
                        <td class="border border-[#b9b9b9] py-1.5 px-2 text-right text-gray-900">
                            <div class="flex justify-between w-full"><span>$</span><span>{{ formatCurrency(item.unit_price) }}</span></div>
                        </td>
                        <td class="border border-[#b9b9b9] py-1.5 px-2 text-right bg-orange-50 text-gray-900">
                            <div class="flex justify-between w-full"><span>$</span><span>{{ formatCurrency(item.total) }}</span></div>
                        </td>
                    </tr>
                    <!-- Fill empty rows if items are too few to maintain table structure -->
                    <tr v-if="!catalog?.items?.length">
                        <td colspan="6" class="border border-[#b9b9b9] py-4 text-center text-gray-400">Sin partidas registradas en esta versión.</td>
                    </tr>
                </tbody>
                <tfoot v-if="catalog">
                    <tr>
                        <td colspan="4" class="border-t-2 border-[#b9b9b9] border-b-0 border-l-0"></td>
                        <td class="border border-[#b9b9b9] py-1.5 px-2 font-bold text-white bg-[#b9b9b9] text-center">SUB-TOTAL</td>
                        <td class="border border-[#b9b9b9] py-1.5 px-2 font-bold text-right bg-[#b9b9b9] text-white">
                            <div class="flex justify-between w-full"><span>$</span><span>{{ formatCurrency(catalog.subtotal) }}</span></div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="border-none"></td>
                        <td class="border border-[#b9b9b9] py-1.5 px-2 font-bold text-white bg-[#b9b9b9] text-center">IVA</td>
                        <td class="border border-[#b9b9b9] py-1.5 px-2 font-bold text-right bg-[#b9b9b9] text-white">
                            <div class="flex justify-between w-full"><span>$</span><span>{{ formatCurrency(catalog.iva) }}</span></div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="border-none"></td>
                        <td class="border border-[#b9b9b9] py-1.5 px-2 font-bold text-white bg-[#b9b9b9] text-center">TOTAL</td>
                        <td class="border border-[#b9b9b9] py-1.5 px-2 font-bold text-right bg-[#f26c17] text-white">
                            <div class="flex justify-between w-full"><span>$</span><span>{{ formatCurrency(catalog.total) }}</span></div>
                        </td>
                    </tr>
                </tfoot>
            </table>
            <div class="mt-2 text-[10px] text-right font-bold text-[#b9b9b9] uppercase">
                MONEDA: {{ budget.currency }}
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
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    body {
        background-color: white !important;
    }
    @page {
        margin: 10mm;
        size: A4 portrait;
    }
}
</style>