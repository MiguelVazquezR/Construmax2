<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
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

const materialsItems = computed(() =>
    (catalog.value?.items || []).filter(i => i.type === 'material')
);

const laborItems = computed(() =>
    (catalog.value?.items || []).filter(i => i.type === 'labor')
);

const materialsSubtotal = computed(() =>
    materialsItems.value.reduce((sum, i) => sum + Number(i.total || 0), 0)
);

const laborSubtotal = computed(() =>
    laborItems.value.reduce((sum, i) => sum + Number(i.total || 0), 0)
);

const combinedSubtotal = computed(() => materialsSubtotal.value + laborSubtotal.value);

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value || 0);
};

const formatNumber = (value) => {
    return new Intl.NumberFormat('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value || 0);
};

const copyToClipboard = (value) => {
    const text = typeof value === 'number' ? formatNumber(value) : String(value);
    navigator.clipboard.writeText(text).then(() => {
        ElMessage({ message: 'Copied', type: 'success', duration: 800, showClose: false });
    }).catch(() => {
        ElMessage({ message: 'Copy failed', type: 'error', duration: 1000, showClose: false });
    });
};

const handlePrint = () => {
    window.print();
};
</script>

<template>
    <Head :title="`Catálogo Empeño Fácil - ${budget.ticket?.folio}`" />

    <div class="min-h-screen bg-gray-100 print:bg-white p-4 print:p-0 font-sans text-xs text-gray-900 dark:text-gray-900">
        <!-- Top orange bar -->
        <div class="h-1.5 bg-[#f26c17] print:h-1 max-w-5xl mx-auto mb-4 rounded-t"></div>

        <div class="print:hidden flex justify-end max-w-5xl mx-auto mb-4 gap-3 items-center">
            <span class="text-xs text-gray-500 flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Click en cualquier valor para copiar
            </span>
            <button @click="showPdfInstructions = true" class="bg-[#f26c17] hover:bg-[#d95d0f] text-white font-bold py-2 px-4 rounded shadow transition-colors">
                Guardar como PDF / Imprimir
            </button>
        </div>

        <div class="max-w-5xl mx-auto bg-white dark:bg-white print:shadow-none shadow-lg p-8 print:p-0 text-gray-900 dark:text-gray-900">

            <!-- ============================================================ -->
            <!-- HEADER                                                       -->
            <!-- ============================================================ -->
            <div class="flex border-2 border-[#1e1e20] mb-2">
                <!-- Logo Area -->
                <div class="w-1/3 flex justify-center items-center border-r-2 border-[#1e1e20] p-1 bg-white dark:bg-white">
                    <ApplicationLogo class="h-auto w-full object-contain" />
                </div>

                <!-- Info Area -->
                <div class="w-2/3 flex flex-col">
                    <div class="bg-[#f26c17] text-white text-center font-bold py-1 border-b-2 border-[#1e1e20] uppercase">
                        Catálogo de costos
                    </div>

                    <div class="flex border-b border-[#1e1e20] flex-1">
                        <div class="w-1/4 bg-[#1e1e20] text-white px-2 py-1.5 font-bold border-r border-[#1e1e20] flex items-center">Sucursal</div>
                        <div class="w-3/4 px-2 py-1.5 text-center font-bold text-[#f26c17] flex items-center justify-center truncate cursor-pointer hover:text-[#d95d0f] transition" @click="copyToClipboard(budget.ticket?.branch?.branch_name)" title="Clic para copiar">{{ budget.ticket?.branch?.branch_name || 'N/D' }}</div>
                    </div>

                    <div class="flex border-b border-[#1e1e20] flex-1">
                        <div class="w-1/4 bg-[#1e1e20] text-white px-2 py-1.5 font-bold border-r border-[#1e1e20] flex items-center">Ticket</div>
                        <div class="w-3/4 px-2 py-1.5 text-center font-bold text-[#f26c17] flex items-center justify-center truncate cursor-pointer hover:text-[#d95d0f] transition" @click="copyToClipboard(budget.ticket?.folio)" title="Clic para copiar">{{ budget.ticket?.folio || 'N/D' }}</div>
                    </div>

                    <div class="flex border-b border-[#1e1e20] flex-1">
                        <div class="w-1/4 bg-[#1e1e20] text-white px-2 py-1.5 font-bold border-r border-[#1e1e20] flex items-center">No. de sucursal</div>
                        <div class="w-3/4 px-2 py-1.5 text-center font-bold text-[#f26c17] flex items-center justify-center truncate cursor-pointer hover:text-[#d95d0f] transition" @click="copyToClipboard(budget.ticket?.branch?.unit)" title="Clic para copiar">{{ budget.ticket?.branch?.unit || 'N/D' }}</div>
                    </div>

                    <div class="flex flex-1">
                        <div class="w-1/4 bg-[#1e1e20] text-white px-2 py-1.5 font-bold border-r border-[#1e1e20] flex items-center">Descripción</div>
                        <div class="w-3/4 px-2 py-1.5 text-center font-bold text-[#f26c17] flex items-center justify-center truncate cursor-pointer hover:text-[#d95d0f] transition" @click="copyToClipboard(budget.ticket?.name)" title="Clic para copiar">{{ budget.ticket?.name || 'N/D' }}</div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TABLA DE MATERIALES                                          -->
            <!-- ============================================================ -->
            <table class="w-full text-[11px] border-collapse border-2 border-[#1e1e20] text-gray-900 mb-4">
                <thead>
                    <tr class="bg-[#f26c17] text-white text-center">
                        <th class="border border-[#1e1e20] py-2 w-16">DESGLOCE</th>
                        <th class="border border-[#1e1e20] py-2">DESCRIPCIÓN</th>
                        <th class="border border-[#1e1e20] py-2 w-20">UNIDAD</th>
                        <th class="border border-[#1e1e20] py-2 w-20">QTY</th>
                        <th class="border border-[#1e1e20] py-2 w-24">PRECIO</th>
                        <th class="border border-[#1e1e20] py-2 w-28">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in materialsItems" :key="'m'+item.id" class="text-center">
                        <td class="border border-[#1e1e20] py-1.5 px-1 text-gray-900">{{ index + 1 }}</td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 text-left leading-tight break-words text-gray-900 cursor-pointer hover:text-[#f26c17] transition" @click="copyToClipboard(item.description)" title="Clic para copiar">{{ item.description }}</td>
                        <td class="border border-[#1e1e20] py-1.5 px-1 text-gray-900 cursor-pointer hover:text-[#f26c17] transition" @click="copyToClipboard(item.unit)" title="Clic para copiar">{{ item.unit }}</td>
                        <td class="border border-[#1e1e20] py-1.5 px-1 text-gray-900">
                            <span class="cursor-pointer hover:text-[#f26c17] transition" @click="copyToClipboard(Number(item.quantity))" title="Clic para copiar">{{ Number(item.quantity).toFixed(2) }}</span>
                        </td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 text-right text-gray-900">
                            <div class="flex justify-between w-full cursor-pointer hover:text-[#f26c17] transition" @click="copyToClipboard(Number(item.unit_price))" title="Clic para copiar"><span>$</span><span>{{ formatCurrency(item.unit_price) }}</span></div>
                        </td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 text-right bg-orange-50 text-gray-900">
                            <div class="flex justify-between w-full cursor-pointer hover:text-[#f26c17] transition font-bold" @click="copyToClipboard(Number(item.total))" title="Clic para copiar"><span>$</span><span>{{ formatCurrency(item.total) }}</span></div>
                        </td>
                    </tr>
                    <tr v-if="materialsItems.length === 0">
                        <td colspan="6" class="border border-[#1e1e20] py-4 text-center text-gray-400">Sin materiales registrados.</td>
                    </tr>
                </tbody>
                <tfoot v-if="materialsItems.length > 0">
                    <tr>
                        <td colspan="4" class="border-t-2 border-[#1e1e20] border-b-0 border-l-0"></td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 font-bold text-white bg-[#1e1e20] text-center">Materiales subtotal</td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 font-bold text-right bg-[#1e1e20] text-white">
                            <div class="flex justify-between w-full cursor-pointer hover:text-[#f26c17] transition" @click="copyToClipboard(materialsSubtotal)" title="Clic para copiar"><span>$</span><span>{{ formatCurrency(materialsSubtotal) }}</span></div>
                        </td>
                    </tr>
                </tfoot>
            </table>

            <!-- ============================================================ -->
            <!-- TABLA DE MANO DE OBRA                                        -->
            <!-- ============================================================ -->
            <table class="w-full text-[11px] border-collapse border-2 border-[#1e1e20] text-gray-900 mb-4">
                <thead>
                    <tr class="bg-[#f26c17] text-white text-center">
                        <th class="border border-[#1e1e20] py-2 w-16">MO</th>
                        <th class="border border-[#1e1e20] py-2">DESCRIPCIÓN</th>
                        <th class="border border-[#1e1e20] py-2 w-32">TÉCNICO</th>
                        <th class="border border-[#1e1e20] py-2 w-20">HORAS</th>
                        <th class="border border-[#1e1e20] py-2 w-24">RATE</th>
                        <th class="border border-[#1e1e20] py-2 w-28">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in laborItems" :key="'l'+item.id" class="text-center">
                        <td class="border border-[#1e1e20] py-1.5 px-1 text-gray-900">{{ index + 1 }}</td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 text-left leading-tight break-words text-gray-900 cursor-pointer hover:text-[#f26c17] transition" @click="copyToClipboard(item.description)" title="Clic para copiar">{{ item.description }}</td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 text-left text-gray-900 cursor-pointer hover:text-[#f26c17] transition" @click="copyToClipboard(item.technician)" title="Clic para copiar">{{ item.technician || '—' }}</td>
                        <td class="border border-[#1e1e20] py-1.5 px-1 text-gray-900">
                            <span class="cursor-pointer hover:text-[#f26c17] transition" @click="copyToClipboard(Number(item.hours))" title="Clic para copiar">{{ Number(item.hours).toFixed(2) }}</span>
                        </td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 text-right text-gray-900">
                            <div class="flex justify-between w-full cursor-pointer hover:text-[#f26c17] transition" @click="copyToClipboard(Number(item.rate))" title="Clic para copiar"><span>$</span><span>{{ formatCurrency(item.rate) }}</span></div>
                        </td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 text-right bg-orange-50 text-gray-900">
                            <div class="flex justify-between w-full cursor-pointer hover:text-[#f26c17] transition font-bold" @click="copyToClipboard(Number(item.total))" title="Clic para copiar"><span>$</span><span>{{ formatCurrency(item.total) }}</span></div>
                        </td>
                    </tr>
                    <tr v-if="laborItems.length === 0">
                        <td colspan="6" class="border border-[#1e1e20] py-4 text-center text-gray-400">Sin mano de obra registrada.</td>
                    </tr>
                </tbody>
                <tfoot v-if="laborItems.length > 0">
                    <tr>
                        <td colspan="4" class="border-t-2 border-[#1e1e20] border-b-0 border-l-0"></td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 font-bold text-white bg-[#1e1e20] text-center">Instalación laboral subtotal</td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 font-bold text-right bg-[#1e1e20] text-white">
                            <div class="flex justify-between w-full cursor-pointer hover:text-[#f26c17] transition" @click="copyToClipboard(laborSubtotal)" title="Clic para copiar"><span>$</span><span>{{ formatCurrency(laborSubtotal) }}</span></div>
                        </td>
                    </tr>
                </tfoot>
            </table>

            <!-- ============================================================ -->
            <!-- PIE: TOTALES GENERALES                                       -->
            <!-- ============================================================ -->
            <table class="w-full text-[11px] border-collapse border-2 border-[#1e1e20] text-gray-900" v-if="catalog">
                <tfoot>
                    <tr>
                        <td colspan="4" class="border-none"></td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 font-bold text-white bg-[#1e1e20] text-center w-56">Subtotal</td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 font-bold text-right bg-[#1e1e20] text-white">
                            <div class="flex justify-between w-full cursor-pointer hover:text-[#f26c17] transition" @click="copyToClipboard(combinedSubtotal)" title="Clic para copiar"><span>$</span><span>{{ formatCurrency(combinedSubtotal) }}</span></div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="border-none"></td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 font-bold text-white bg-[#1e1e20] text-center">Non installation labor</td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 font-bold text-right bg-[#1e1e20] text-white">
                            <div class="flex justify-between w-full cursor-pointer hover:text-[#f26c17] transition" @click="copyToClipboard(Number(catalog.non_installation_labor))" title="Clic para copiar"><span>$</span><span>{{ formatCurrency(Number(catalog.non_installation_labor)) }}</span></div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="border-none"></td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 font-bold text-white bg-[#1e1e20] text-center">Utilidad de mano de obra</td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 font-bold text-right bg-[#1e1e20] text-white">
                            <div class="flex justify-between w-full cursor-pointer hover:text-[#f26c17] transition" @click="copyToClipboard(Number(catalog.labor_utility))" title="Clic para copiar"><span>$</span><span>{{ formatCurrency(Number(catalog.labor_utility)) }}</span></div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="border-none"></td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 font-bold text-white bg-[#1e1e20] text-center">IVA</td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 font-bold text-right bg-[#1e1e20] text-white">
                            <div class="flex justify-between w-full cursor-pointer hover:text-[#f26c17] transition" @click="copyToClipboard(Number(catalog.iva))" title="Clic para copiar"><span>$</span><span>{{ formatCurrency(Number(catalog.iva)) }}</span></div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="border-none"></td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 font-bold text-white bg-[#1e1e20] text-center">Subtotal general</td>
                        <td class="border border-[#1e1e20] py-1.5 px-2 font-bold text-right bg-[#f26c17] text-white">
                            <div class="flex justify-between w-full cursor-pointer hover:text-white/80 transition" @click="copyToClipboard(Number(catalog.total))" title="Clic para copiar"><span>$</span><span>{{ formatCurrency(Number(catalog.total)) }}</span></div>
                        </td>
                    </tr>
                </tfoot>
            </table>

            <div class="mt-2 text-[10px] text-right font-bold text-[#1e1e20] uppercase">
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
