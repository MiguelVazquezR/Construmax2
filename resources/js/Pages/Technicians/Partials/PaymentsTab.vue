<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Money, Document, View, Calendar, CreditCard } from '@element-plus/icons-vue';

const props = defineProps({
    payments: Array
});

// Estado para visualización de comprobantes
const showPreviewModal = ref(false);
const previewUrl = ref('');
const previewType = ref('');

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { 
        style: 'currency', 
        currency: 'MXN' 
    }).format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-MX', {
        year: 'numeric', month: 'short', day: 'numeric'
    });
};

const openPreview = (media) => {
    if (!media) return;
    
    previewUrl.value = media.original_url;
    const mime = media.mime_type;
    
    if (mime.startsWith('image/')) {
        previewType.value = 'image';
    } else if (mime === 'application/pdf') {
        previewType.value = 'pdf';
    } else {
        window.open(media.original_url, '_blank');
        return;
    }
    
    showPreviewModal.value = true;
};
</script>

<template>
    <div class="py-6">
        <div v-if="payments.length > 0">
            <!-- Resumen Rápido -->
            <div class="mb-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-gray-50 dark:bg-[#252529] p-4 rounded-lg border border-gray-100 dark:border-[#3f3f46]">
                    <p class="text-xs text-gray-500 uppercase">Total de Pagos</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-white">{{ payments.length }}</p>
                </div>
                <div class="bg-green-50 dark:bg-green-900/10 p-4 rounded-lg border border-green-100 dark:border-green-900/20">
                    <p class="text-xs text-green-600 dark:text-green-400 uppercase">Monto Total Recibido</p>
                    <p class="text-xl font-bold text-green-700 dark:text-green-400">
                        {{ formatCurrency(payments.reduce((acc, curr) => acc + parseFloat(curr.amount), 0)) }}
                    </p>
                </div>
            </div>

            <el-table :data="payments" stripe style="width: 100%">
                <el-table-column label="Fecha" width="140">
                    <template #default="scope">
                        <div class="flex items-center gap-2">
                            <el-icon class="text-gray-400"><Calendar /></el-icon>
                            <span>{{ formatDate(scope.row.payment_date) }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Proyecto / Presupuesto" min-width="220">
                    <template #default="scope">
                        <div v-if="scope.row.budget">
                            <Link :href="route('budgets.show', scope.row.budget.id)" class="font-bold text-blue-600 hover:underline">
                                {{ scope.row.budget.name }}
                            </Link>
                            <p class="text-xs text-gray-500">{{ scope.row.budget.customer?.name || 'Cliente desconocido' }}</p>
                        </div>
                        <span v-else class="text-gray-400 italic">Proyecto eliminado</span>
                    </template>
                </el-table-column>

                <el-table-column label="Monto" width="150" align="right">
                    <template #default="scope">
                        <span class="font-bold text-gray-800 dark:text-gray-200">
                            {{ formatCurrency(scope.row.amount) }}
                        </span>
                    </template>
                </el-table-column>

                <el-table-column label="Detalles" min-width="200">
                    <template #default="scope">
                        <div>
                            <div class="flex items-center gap-1 text-sm text-gray-700 dark:text-gray-300">
                                <el-icon><CreditCard /></el-icon>
                                <span>{{ scope.row.payment_method }}</span>
                                <el-tag v-if="scope.row.reference" size="small" type="info" class="ml-2">{{ scope.row.reference }}</el-tag>
                            </div>
                            <p v-if="scope.row.notes" class="text-xs text-gray-500 mt-1 italic">"{{ scope.row.notes }}"</p>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Comprobante" width="120" align="center">
                    <template #default="scope">
                        <el-button 
                            v-if="scope.row.media && scope.row.media.length > 0"
                            type="primary" 
                            plain 
                            size="small" 
                            circle 
                            :icon="Document"
                            @click="openPreview(scope.row.media[0])"
                        />
                        <span v-else class="text-xs text-gray-300">N/A</span>
                    </template>
                </el-table-column>
            </el-table>
        </div>
        
        <el-empty v-else description="No se han registrado pagos para este técnico aún." :image-size="100">
            <template #description>
                <p class="text-gray-500">Los pagos se registran desde el detalle de cada Proyecto (Presupuesto).</p>
            </template>
        </el-empty>

        <!-- Modal de Previsualización -->
        <el-dialog v-model="showPreviewModal" title="Comprobante de Pago" width="70%" align-center>
            <div class="flex justify-center bg-gray-100 dark:bg-gray-800 p-4 rounded min-h-[400px]">
                <img v-if="previewType === 'image'" :src="previewUrl" class="max-h-[70vh] object-contain" />
                <iframe v-else-if="previewType === 'pdf'" :src="previewUrl" class="w-full h-[70vh]" frameborder="0"></iframe>
            </div>
        </el-dialog>
    </div>
</template>