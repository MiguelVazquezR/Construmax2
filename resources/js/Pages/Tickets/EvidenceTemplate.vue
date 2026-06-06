<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

const showPdfInstructions = ref(false);

const handlePrint = () => {
    showPdfInstructions.value = true;
    // Pequeño delay para que el diálogo se muestre antes que el de impresión
    setTimeout(() => {
        window.print();
    }, 300);
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
    <div class="min-h-screen bg-white p-8 print:p-4">
        <Head title="Plantilla de evidencias" />

        <!-- Botón de impresión / PDF (oculto al imprimir) -->
        <div class="flex items-center justify-end gap-3 mb-6 print:hidden">
            <el-button type="primary" size="large" @click="handlePrint">
                Guardar como PDF / Imprimir
            </el-button>
        </div>

        <!-- Header con logo del cliente -->
        <div class="border-b-2 border-gray-200 pb-6 mb-8 print:mb-4">
            <div class="flex items-center gap-6">
                <div v-if="getLogoUrl()" class="w-20 h-20 rounded-lg overflow-hidden border border-gray-200 bg-white shrink-0">
                    <img :src="getLogoUrl()" alt="Logo" class="w-full h-full object-contain" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Recopilación de evidencias</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ ticket.name }}</p>
                    <p class="text-xs text-gray-400">
                        Cliente: {{ ticket.customer?.name }} | {{ ticket.branch?.branch_name }}
                        <template v-if="ticket.branch?.city"> - {{ ticket.branch.city }}</template>
                        <template v-if="ticket.branch?.region">, {{ ticket.branch.region }}</template>
                    </p>
                </div>
            </div>
        </div>

        <!-- Cuadrícula de evidencias -->
        <div v-if="allEvidences.length > 0">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 print:gap-3">
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
                        <p class="text-xs font-semibold text-gray-700 truncate">{{ item.task_name }}</p>
                        <p class="text-[10px] text-gray-400">{{ formatDate(item.date) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="text-center py-16">
            <p class="text-gray-400">No hay evidencias registradas para este ticket.</p>
        </div>

        <!-- Footer -->
        <div class="mt-12 pt-6 border-t border-gray-200 text-center text-xs text-gray-400 print:mt-4">
            Generado el {{ new Date().toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' }) }}
        </div>

        <!-- Diálogo de instrucciones para guardar como PDF -->
        <el-dialog v-model="showPdfInstructions" title="Cómo guardar como PDF" width="500px" class="print:hidden">
            <div class="space-y-3 text-sm text-gray-700">
                <p>Se abrirá la ventana de impresión del navegador. Sigue estos pasos:</p>
                <ol class="list-decimal pl-5 space-y-2">
                    <li>En <strong>Destino</strong>, selecciona <strong>"Guardar como PDF"</strong>.</li>
                    <li>Ajusta el diseño a <strong>Vertical</strong> si es necesario.</li>
                    <li>Haz clic en <strong>"Guardar"</strong> y elige la ubicación en tu equipo.</li>
                </ol>
                <div class="bg-blue-50 border border-blue-100 rounded-lg p-3 text-xs text-blue-700">
                    <strong>Tip:</strong> Si no ves "Guardar como PDF" en la lista de destinos, haz clic en "Más destinos..." para buscarlo.
                </div>
            </div>
            <template #footer>
                <el-button @click="showPdfInstructions = false">Entendido</el-button>
                <el-button type="primary" @click="window.print()">Abrir ventana de impresión</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<style>
@media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @page { margin: 1cm; }
}
</style>