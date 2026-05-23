<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

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

const printDocument = () => {
    window.print();
};
</script>

<template>
    <Head :title="`Impresión Catálogo - ${budget.ticket?.folio}`" />
    
    <div class="min-h-screen bg-gray-100 print:bg-white p-4 print:p-0 font-sans text-xs">
        <div class="print:hidden flex justify-end max-w-5xl mx-auto mb-4">
            <button @click="printDocument" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow">
                Imprimir / Guardar PDF
            </button>
        </div>

        <div class="max-w-5xl mx-auto bg-white print:shadow-none shadow-lg p-8 print:p-0">
            
            <!-- Header principal -->
            <div class="flex border-2 border-blue-900 mb-2">
                <!-- Logo Area -->
                <div class="w-1/3 flex justify-center items-center border-r-2 border-blue-900 p-4 bg-white">
                    <ApplicationLogo class="max-h-20 w-auto object-contain" />
                </div>
                
                <!-- Info Area -->
                <div class="w-2/3 flex flex-col">
                    <div class="bg-[#4b82c3] text-white text-center font-bold py-1 border-b-2 border-blue-900 uppercase">
                        PRESUPUESTO
                    </div>
                    
                    <div class="flex border-b border-blue-900 flex-1">
                        <div class="w-1/4 bg-[#5b9bd5] text-white px-2 py-1.5 font-bold border-r border-blue-900 flex items-center">No. Tienda</div>
                        <div class="w-1/4 px-2 py-1.5 text-center font-bold text-red-600 border-r border-blue-900 flex items-center justify-center">{{ budget.ticket?.branch?.unit || 'N/D' }}</div>
                        <div class="w-1/4 bg-[#5b9bd5] text-white px-2 py-1.5 font-bold border-r border-blue-900 flex items-center">Nombre</div>
                        <div class="w-1/4 px-2 py-1.5 text-center font-bold text-red-600 flex items-center justify-center truncate">{{ budget.ticket?.branch?.branch_name || 'N/D' }}</div>
                    </div>
                    
                    <div class="flex flex-1">
                        <div class="w-1/4 bg-[#5b9bd5] text-white px-2 py-1.5 font-bold border-r border-blue-900 flex items-center">Región</div>
                        <div class="w-1/4 px-2 py-1.5 text-center font-bold text-red-600 border-r border-blue-900 flex items-center justify-center uppercase">{{ budget.ticket?.branch?.region || 'N/D' }}</div>
                        <div class="w-1/4 bg-[#5b9bd5] text-white px-2 py-1.5 font-bold border-r border-blue-900 flex items-center">País</div>
                        <div class="w-1/4 px-2 py-1.5 text-center font-bold text-red-600 flex items-center justify-center uppercase">{{ budget.ticket?.branch?.country || 'N/D' }}</div>
                    </div>
                </div>
            </div>

            <!-- Provider & General Data -->
            <div class="flex border-2 border-blue-900 border-b text-[11px] mt-2 bg-blue-50">
                <div class="w-[15%] bg-[#5b9bd5] text-white px-2 py-1 font-bold border-r border-blue-900">Proveedor / Cliente</div>
                <div class="w-[35%] px-2 py-1 text-center font-bold border-r border-blue-900">{{ budget.ticket?.customer?.name || 'N/D' }}</div>
                <div class="w-[10%] bg-[#5b9bd5] text-white px-2 py-1 font-bold border-r border-blue-900 text-center">RFC</div>
                <div class="w-[20%] px-2 py-1 text-center font-bold border-r border-blue-900">{{ budget.ticket?.customer?.rfc || 'N/D' }}</div>
                <div class="w-[10%] bg-[#5b9bd5] text-white px-2 py-1 font-bold border-r border-blue-900 text-center">No. Reporte</div>
                <div class="w-[10%] px-2 py-1 text-center font-bold">{{ budget.ticket?.folio || budget.ticket?.id }}</div>
            </div>

            <div class="flex border-2 border-t-0 border-blue-900 text-[11px] bg-blue-50 mb-4">
                <div class="w-[15%] bg-[#5b9bd5] text-white px-2 py-1 font-bold border-r border-blue-900">Servicio</div>
                <div class="w-[35%] px-2 py-1 text-center font-bold border-r border-blue-900 uppercase">{{ budget.ticket?.service_type || 'N/D' }}</div>
                <div class="w-[10%] bg-[#5b9bd5] text-white px-2 py-1 font-bold border-r border-blue-900 text-center flex items-center justify-center">Fecha inicio</div>
                <div class="w-[20%] px-2 py-1 text-center font-bold border-r border-blue-900 flex items-center justify-center">{{ formatDate(budget.ticket?.scheduled_start) }}</div>
                <div class="w-[10%] bg-[#5b9bd5] text-white px-2 py-1 font-bold border-r border-blue-900 text-center flex items-center justify-center">Fecha fin</div>
                <div class="w-[10%] px-2 py-1 text-center font-bold flex items-center justify-center">{{ formatDate(budget.ticket?.scheduled_end) }}</div>
            </div>

            <!-- Items Table -->
            <table class="w-full text-[11px] border-collapse border-2 border-blue-900">
                <thead>
                    <tr class="bg-[#4b82c3] text-white text-center">
                        <th class="border border-blue-900 py-2 w-16">PARTIDA<br>CLAVE</th>
                        <th class="border border-blue-900 py-2">DESCRIPCIÓN</th>
                        <th class="border border-blue-900 py-2 w-20">UNIDAD</th>
                        <th class="border border-blue-900 py-2 w-20">CANTIDAD</th>
                        <th class="border border-blue-900 py-2 w-24">P.U.</th>
                        <th class="border border-blue-900 py-2 w-28">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in catalog?.items" :key="item.id" class="text-center group">
                        <td class="border border-blue-900 py-1.5 px-1">{{ index + 1 }}</td>
                        <td class="border border-blue-900 py-1.5 px-2 text-left leading-tight break-words">{{ item.description }}</td>
                        <td class="border border-blue-900 py-1.5 px-1">{{ item.unit }}</td>
                        <td class="border border-blue-900 py-1.5 px-1">{{ Number(item.quantity).toFixed(2) }}</td>
                        <td class="border border-blue-900 py-1.5 px-2 text-right">
                            <div class="flex justify-between w-full"><span>$</span><span>{{ formatCurrency(item.unit_price) }}</span></div>
                        </td>
                        <td class="border border-blue-900 py-1.5 px-2 text-right bg-blue-50">
                            <div class="flex justify-between w-full"><span>$</span><span>{{ formatCurrency(item.total) }}</span></div>
                        </td>
                    </tr>
                    <!-- Fill empty rows if items are too few to maintain table structure -->
                    <tr v-if="!catalog?.items?.length">
                        <td colspan="6" class="border border-blue-900 py-4 text-center text-gray-400">Sin partidas registradas en esta versión.</td>
                    </tr>
                </tbody>
                <tfoot v-if="catalog">
                    <tr>
                        <td colspan="4" class="border-t-2 border-blue-900 border-b-0 border-l-0"></td>
                        <td class="border border-blue-900 py-1.5 px-2 font-bold text-white bg-[#284a7e] text-center">SUB-TOTAL</td>
                        <td class="border border-blue-900 py-1.5 px-2 font-bold text-right bg-[#284a7e] text-white">
                            <div class="flex justify-between w-full"><span>$</span><span>{{ formatCurrency(catalog.subtotal) }}</span></div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="border-none"></td>
                        <td class="border border-blue-900 py-1.5 px-2 font-bold text-white bg-[#284a7e] text-center">IVA</td>
                        <td class="border border-blue-900 py-1.5 px-2 font-bold text-right bg-[#284a7e] text-white">
                            <div class="flex justify-between w-full"><span>$</span><span>{{ formatCurrency(catalog.iva) }}</span></div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="border-none"></td>
                        <td class="border border-blue-900 py-1.5 px-2 font-bold text-white bg-[#284a7e] text-center">TOTAL</td>
                        <td class="border border-blue-900 py-1.5 px-2 font-bold text-right bg-[#284a7e] text-white">
                            <div class="flex justify-between w-full"><span>$</span><span>{{ formatCurrency(catalog.total) }}</span></div>
                        </td>
                    </tr>
                </tfoot>
            </table>
            <div class="mt-2 text-[10px] text-right font-bold text-blue-900 uppercase">
                MONEDA: {{ budget.currency }}
            </div>
        </div>
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