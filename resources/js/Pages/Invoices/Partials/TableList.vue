<script setup>
import { ref } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    invoices: Object,
});

const formatCurrency = (value, currency = 'MXN') => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: currency,
    }).format(value || 0);
};

const getStatusColor = (status) => {
    const map = {
        'Facturación': 'danger',
        'Facturado': 'success',
    };
    return map[status] || 'info';
};

const handlePageChange = (val) => {
    router.visit(route('invoices.index', { ...route().params, page: val }), {
        preserveState: true,
        preserveScroll: true,
    });
};

// Lógica de modal y formulario
const isModalVisible = ref(false);
const activeBudget = ref(null);

const form = useForm({
    invoice_date: '',
    invoice_number: '',
    file: null,
});

const openUploadModal = (budget) => {
    activeBudget.value = budget;
    form.reset();
    form.clearErrors();
    isModalVisible.value = true;
};

const handleFileChange = (file) => {
    form.file = file.raw;
};

const submitInvoice = () => {
    form.post(route('invoices.upload', activeBudget.value.id), {
        onSuccess: () => {
            ElMessage.success('Factura registrada correctamente.');
            isModalVisible.value = false;
        },
    });
};
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
        
        <!-- Tabla -->
        <div class="hidden md:block">
            <el-table 
                :data="invoices.data" 
                style="width: 100%" 
                stripe
            >
                <el-table-column prop="id" label="Folio" width="80" align="center">
                    <template #default="scope">
                        <span class="font-mono text-xs text-gray-500">#{{ scope.row.id }}</span>
                    </template>
                </el-table-column>
                
                <el-table-column label="Proyecto" min-width="180">
                    <template #default="scope">
                        <div class="flex flex-col">
                            <span class="font-bold text-gray-800 dark:text-gray-200 text-sm">{{ scope.row.ticket_name }}</span>
                            <span class="font-mono text-xs text-gray-500">Folio Ticket: {{ scope.row.ticket_folio }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Cliente" min-width="180">
                    <template #default="scope">
                        <div class="flex items-center gap-2">
                            <el-icon class="text-gray-400"><OfficeBuilding /></el-icon>
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ scope.row.customer_name }}</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5 ml-6">
                            Crédito: {{ scope.row.payment_days }} días
                        </p>
                    </template>
                </el-table-column>

                <el-table-column label="Estado" width="120" align="center">
                    <template #default="scope">
                        <el-tag :type="getStatusColor(scope.row.status)" size="small" effect="light" class="w-full text-center">
                            {{ scope.row.status }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="Monto" width="140" align="right">
                    <template #default="scope">
                         <span class="font-mono text-sm font-semibold text-gray-700 dark:text-gray-300">
                             {{ formatCurrency(scope.row.total_cost, scope.row.currency) }}
                         </span>
                    </template>
                </el-table-column>

                <el-table-column label="Datos de factura" min-width="180">
                    <template #default="scope">
                        <div v-if="scope.row.status === 'Facturado'">
                            <p class="text-xs text-gray-600">
                                <span class="font-semibold">Folio:</span> {{ scope.row.invoice_number }}
                            </p>
                            <p class="text-xs text-gray-600">
                                <span class="font-semibold">Emisión:</span> {{ scope.row.invoice_date }}
                            </p>
                            <p class="text-xs" :class="new Date(scope.row.due_date) < new Date() ? 'text-red-500 font-semibold' : 'text-gray-500'">
                                <span class="font-semibold text-gray-600">Vencimiento:</span> {{ scope.row.due_date }}
                            </p>
                        </div>
                        <span v-else class="text-xs text-gray-400 italic">Pendiente de emisión</span>
                    </template>
                </el-table-column>

                <el-table-column label="Acciones" width="150" align="center" fixed="right">
                    <template #default="scope">
                        <div class="flex gap-2 justify-center items-center">
                            <el-button 
                                v-if="can('budgets.index')" 
                                type="primary" plain size="small" icon="Money" circle title="Ver Presupuesto"
                                @click="router.visit(route('budgets.show', scope.row.id))" 
                            />
                            <el-button 
                                v-if="can('tickets.index') && scope.row.ticket_id" 
                                type="warning" plain size="small" icon="Ticket" circle title="Ver Ticket"
                                @click="router.visit(route('tickets.show', scope.row.ticket_id))" 
                            />
                            <a v-if="scope.row.has_invoice_file" :href="scope.row.invoice_url" target="_blank" title="Ver archivo">
                                <el-button type="info" size="small" icon="View" circle />
                            </a>
                            <a v-if="scope.row.has_invoice_file" :href="scope.row.invoice_url" download title="Descargar factura">
                                <el-button type="info" size="small" icon="Download" circle />
                            </a>
                            <el-button
                                v-if="scope.row.status === 'Facturación' && can('invoices.upload')"
                                type="primary"
                                size="small"
                                @click="openUploadModal(scope.row)"
                            >
                                Subir
                            </el-button>
                        </div>
                    </template>
                </el-table-column>
            </el-table>
        </div>

        <!-- Paginación -->
        <div class="flex flex-col sm:flex-row justify-between items-center p-4 bg-gray-50 dark:bg-[#252529] border-t border-gray-100 dark:border-[#2b2b2e]">
            <div class="text-xs text-gray-500 mb-3 sm:mb-0">
                Mostrando {{ invoices.from }} a {{ invoices.to }} de {{ invoices.total }} registros
            </div>
            <el-pagination
                v-model:current-page="invoices.current_page"
                :page-size="invoices.per_page"
                :total="invoices.total"
                layout="prev, pager, next"
                background
                @current-change="handlePageChange"
                class="!p-0"
            />
        </div>

        <!-- Modal para subir factura -->
        <el-dialog
            v-model="isModalVisible"
            title="Subir factura"
            width="500px"
            destroy-on-close
        >
            <el-form :model="form" label-position="top" @submit.prevent="submitInvoice">
                <el-form-item label="Número de factura" :error="form.errors.invoice_number">
                    <el-input v-model="form.invoice_number" placeholder="Ej. A-1234" />
                </el-form-item>

                <el-form-item label="Fecha de emisión" :error="form.errors.invoice_date">
                    <el-date-picker
                        v-model="form.invoice_date"
                        type="date"
                        placeholder="Selecciona la fecha"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        class="!w-full"
                    />
                </el-form-item>

                <el-form-item label="Archivo (PDF o Imagen)" :error="form.errors.file">
                    <el-upload
                        class="w-full"
                        drag
                        :auto-upload="false"
                        :limit="1"
                        accept=".pdf,.jpg,.jpeg,.png"
                        @change="handleFileChange"
                    >
                        <el-icon class="el-icon--upload"><upload-filled /></el-icon>
                        <div class="el-upload__text">
                            Suelta el archivo aquí o <em>haz clic para subir</em>
                        </div>
                    </el-upload>
                </el-form-item>
            </el-form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="isModalVisible = false" :disabled="form.processing">
                        Cancelar
                    </el-button>
                    <el-button type="primary" @click="submitInvoice" :loading="form.processing">
                        Guardar factura
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>