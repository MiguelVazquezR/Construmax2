<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';

const props = defineProps({
    budget: Object,
});

// --- UTILS ---
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('es-MX', {
        year: 'numeric', month: 'long', day: 'numeric'
    });
};

const getStatusColor = (status) => {
    const map = {
        'Borrador': 'info',
        'Presupuesto enviado': 'primary',
        'Facturado': 'warning',
        'Trabajo en proceso': 'primary',
        'Trabajo terminado': 'success',
        'Pagado': 'success',
        'Perdido': 'danger'
    };
    return map[status] || 'info';
};

// --- GESTIÓN DE PAGOS ---
const showPaymentModal = ref(false);
const paymentUploadRef = ref(null);

const paymentForm = useForm({
    amount: 0,
    payment_date: new Date().toISOString().split('T')[0],
    payment_method: 'Transferencia',
    reference: '',
    proof: null, // Archivo
});

const openPaymentModal = () => {
    paymentForm.reset();
    paymentForm.amount = props.budget.balance_due > 0 ? props.budget.balance_due : 0;
    if(paymentUploadRef.value) paymentUploadRef.value.clearFiles();
    showPaymentModal.value = true;
};

const handlePaymentProofChange = (file) => {
    paymentForm.proof = file.raw;
};

const submitPayment = () => {
    paymentForm.post(route('budgets.payments.store', props.budget.id), {
        onSuccess: () => {
            showPaymentModal.value = false;
            paymentForm.reset();
            ElMessage.success('Pago registrado correctamente');
        },
        onError: () => ElMessage.error('Error al registrar el pago'),
        forceFormData: true,
    });
};

const deletePayment = (paymentId) => {
    ElMessageBox.confirm('¿Eliminar este pago? Se recalculará el saldo.', 'Confirmar', {
        type: 'warning'
    }).then(() => {
        router.delete(route('budgets.payments.destroy', paymentId), {
            onSuccess: () => ElMessage.success('Pago eliminado')
        });
    }).catch(() => {});
};

// --- GESTIÓN DE ARCHIVOS ---
const fileUploadRef = ref(null);
const fileList = ref([]); // Para el componente upload visualmente

const uploadForm = useForm({
    files: [],
});

const handleFilesChange = (file, fileListArg) => {
    // Sincronizamos la lista visual con el formulario
    fileList.value = fileListArg;
    uploadForm.files = fileListArg.map(f => f.raw);
};

const submitFiles = () => {
    if (uploadForm.files.length === 0) return;

    uploadForm.post(route('budgets.files.store', props.budget.id), {
        onSuccess: () => {
            ElMessage.success('Archivos subidos correctamente');
            fileList.value = [];
            uploadForm.reset();
            if(fileUploadRef.value) fileUploadRef.value.clearFiles();
        },
        onError: () => ElMessage.error('Error al subir archivos'),
        forceFormData: true,
    });
};

const deleteFile = (mediaId) => {
    ElMessageBox.confirm('¿Eliminar este archivo permanentemente?', 'Confirmar', {
        type: 'warning'
    }).then(() => {
        router.delete(route('budgets.files.destroy', mediaId), {
            onSuccess: () => ElMessage.success('Archivo eliminado')
        });
    }).catch(() => {});
};

// --- PREVISUALIZACIÓN ---
const showPreviewModal = ref(false);
const previewUrl = ref('');
const previewType = ref(''); // 'image', 'pdf', 'other'
const previewTitle = ref('');

const openPreview = (file) => {
    previewUrl.value = file.original_url;
    previewTitle.value = file.file_name;
    
    const mime = file.mime_type;
    if (mime.startsWith('image/')) {
        previewType.value = 'image';
    } else if (mime === 'application/pdf') {
        previewType.value = 'pdf';
    } else {
        // Fallback: abrir en nueva pestaña si no es soportado en modal
        window.open(file.original_url, '_blank');
        return;
    }
    showPreviewModal.value = true;
};
</script>

<template>
    <AppLayout :title="`Proyecto: ${budget.name}`">
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-3">
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                        Detalle del proyecto
                    </h2>
                    <el-tag :type="getStatusColor(budget.status)" effect="dark">
                        {{ budget.status }}
                    </el-tag>
                </div>
                
                <div class="flex gap-2 w-full sm:w-auto">
                    <!-- BOTÓN VOLVER (Requerimiento 1) -->
                    <Link :href="route('budgets.index')" class="w-full sm:w-auto">
                        <el-button class="w-full sm:w-auto" icon="Back">Volver al listado</el-button>
                    </Link>
                    <Link :href="route('budgets.edit', budget.id)" class="w-full sm:w-auto">
                        <el-button type="primary" color="#f26c17" icon="Edit" class="w-full sm:w-auto">
                            Editar
                        </el-button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">
            
            <!-- RESUMEN SUPERIOR -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border-l-4 border-primary p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">{{ budget.name }}</h1>
                    <p class="text-sm text-gray-500 flex items-center gap-2">
                        <el-icon><Calendar /></el-icon> Registro: {{ formatDate(budget.created_at) }}
                        <span class="mx-1">•</span>
                        <el-icon><User /></el-icon> Responsable: {{ budget.responsible?.name }}
                    </p>
                </div>
                <div class="text-right w-full md:w-auto">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Monto Total</p>
                    <p class="text-3xl font-bold text-gray-800 dark:text-gray-100">{{ formatCurrency(budget.total_cost) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- COLUMNA IZQUIERDA (2/3): Detalles y Costos -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- INFORMACIÓN DETALLADA -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                        <div class="p-4 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#2b2b2e]">
                            <h3 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                <el-icon><Document /></el-icon> Descripción y alcance
                            </h3>
                        </div>
                        <div class="p-6">
                            <el-descriptions border :column="2">
                                <el-descriptions-item label="Tipo de servicio">{{ budget.service_type }}</el-descriptions-item>
                                <el-descriptions-item label="Prioridad">
                                    <el-tag size="small" :type="budget.priority === 'Urgente' ? 'danger' : 'info'">{{ budget.priority }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item label="Duración estimada">{{ budget.duration || 'No especificada' }}</el-descriptions-item>
                                <el-descriptions-item label="Sucursal / Sitio">{{ budget.branch }}</el-descriptions-item>
                            </el-descriptions>
                            
                            <div class="mt-4 p-4 bg-gray-50/50 dark:bg-[#252529]/50 rounded-md text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">
                                {{ budget.description || 'Sin descripción detallada.' }}
                            </div>
                        </div>
                    </div>

                    <!-- DESGLOSE DE COSTOS -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                        <div class="p-4 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#2b2b2e]">
                            <h3 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                <el-icon><Money /></el-icon> Conceptos del presupuesto
                            </h3>
                        </div>
                        <el-table :data="budget.concepts" stripe style="width: 100%">
                            <el-table-column prop="concept" label="Concepto" />
                            <el-table-column prop="amount" label="Monto" align="right" width="150">
                                <template #default="scope">
                                    {{ formatCurrency(scope.row.amount) }}
                                </template>
                            </el-table-column>
                        </el-table>
                        <div class="p-4 text-right bg-gray-50 dark:bg-[#252529] border-t border-gray-100 dark:border-[#2b2b2e]">
                            <span class="text-sm font-bold text-gray-600 mr-4">TOTAL:</span>
                            <span class="text-xl font-bold text-primary">{{ formatCurrency(budget.total_cost) }}</span>
                        </div>
                    </div>

                    <!-- ARCHIVOS ADJUNTOS (Requerimiento 2) -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                        <div class="p-4 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#2b2b2e] flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <h3 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                <el-icon><Folder /></el-icon> Documentos y archivos
                            </h3>
                            
                            <!-- Carga Múltiple -->
                            <div class="flex gap-2 items-center">
                                <el-upload
                                    ref="fileUploadRef"
                                    :show-file-list="false"
                                    :auto-upload="false"
                                    :on-change="handleFilesChange"
                                    multiple
                                    class="upload-demo"
                                    accept=".pdf,.jpg,.png,.doc,.docx,.xls,.xlsx"
                                >
                                    <template #trigger>
                                        <el-button type="primary" plain size="small" icon="FolderAdd">Seleccionar archivos</el-button>
                                    </template>
                                </el-upload>
                                <el-button 
                                    v-if="uploadForm.files.length > 0" 
                                    type="success" 
                                    size="small" 
                                    icon="Upload" 
                                    @click="submitFiles"
                                    :loading="uploadForm.processing"
                                >
                                    Subir ({{ uploadForm.files.length }})
                                </el-button>
                            </div>
                        </div>
                        
                        <div class="p-4">
                            <ul v-if="budget.media.length > 0" class="space-y-3">
                                <li v-for="file in budget.media" :key="file.id" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-[#252529] rounded-lg border border-gray-100 dark:border-[#3f3f46]">
                                    <div class="flex items-center gap-3 overflow-hidden cursor-pointer" @click="openPreview(file)">
                                        <div class="bg-blue-100 text-blue-600 p-2 rounded">
                                            <el-icon><Document /></el-icon>
                                        </div>
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate hover:text-primary transition-colors">
                                                {{ file.file_name }}
                                            </span>
                                            <span class="text-xs text-gray-400">{{ (file.size / 1024).toFixed(2) }} KB</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <!-- Botón Visualizar -->
                                        <el-button circle size="small" type="primary" plain icon="View" @click="openPreview(file)" />
                                        
                                        <a :href="file.original_url" target="_blank" download>
                                            <el-button circle size="small" icon="Download" />
                                        </a>
                                        <el-button circle size="small" type="danger" icon="Delete" plain @click="deleteFile(file.id)" />
                                    </div>
                                </li>
                            </ul>
                            <el-empty v-else description="No hay archivos adjuntos" :image-size="80" />
                        </div>
                    </div>

                </div>

                <!-- COLUMNA DERECHA (1/3): Cliente y Pagos -->
                <div class="lg:col-span-1 space-y-6">
                    
                    <!-- INFO CLIENTE -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                        <h3 class="font-bold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2">
                            <el-icon><OfficeBuilding /></el-icon> Cliente
                        </h3>
                        
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs text-gray-400 uppercase">Empresa</p>
                                <p class="font-bold text-gray-800 dark:text-white">{{ budget.customer?.name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase">Contacto</p>
                                <p class="text-sm text-gray-600 dark:text-gray-300">{{ budget.contact?.name }}</p>
                                <a :href="`mailto:${budget.contact?.email}`" class="text-xs text-primary hover:underline">{{ budget.contact?.email }}</a>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase">Sucursal</p>
                                <p class="text-sm text-gray-600 dark:text-gray-300">{{ budget.branch }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- FINANZAS Y PAGOS -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                        <h3 class="font-bold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2">
                            <el-icon><Wallet /></el-icon> Estado de cuenta
                        </h3>

                        <!-- Resumen Financiero -->
                        <div class="space-y-4 mb-6">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-500">Total Proyecto</span>
                                <span class="font-bold text-gray-800 dark:text-white">{{ formatCurrency(budget.total_cost) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-green-600">
                                <span class="text-sm">Pagado</span>
                                <span class="font-bold">- {{ formatCurrency(budget.total_paid) }}</span>
                            </div>
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-2 flex justify-between items-center">
                                <span class="text-sm font-bold text-gray-800 dark:text-white">Saldo Pendiente</span>
                                <span class="font-bold text-lg" :class="budget.balance_due > 0 ? 'text-red-500' : 'text-green-500'">
                                    {{ formatCurrency(budget.balance_due) }}
                                </span>
                            </div>
                            
                            <!-- Barra de progreso -->
                            <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700 mt-2">
                                <div 
                                    class="bg-green-500 h-2.5 rounded-full transition-all duration-500" 
                                    :style="{ width: Math.min((budget.total_paid / budget.total_cost) * 100, 100) + '%' }"
                                ></div>
                            </div>
                        </div>

                        <el-divider content-position="center">Pagos</el-divider>

                        <el-button type="success" class="w-full mb-4" @click="openPaymentModal" :disabled="budget.balance_due <= 0">
                            Registrar pago
                        </el-button>

                        <div class="space-y-3 max-h-80 overflow-y-auto">
                            <div v-for="payment in budget.payments" :key="payment.id" class="p-3 bg-gray-50 dark:bg-[#252529] rounded border border-gray-100 dark:border-[#3f3f46] text-sm group">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-bold text-gray-800 dark:text-gray-200">{{ formatCurrency(payment.amount) }}</p>
                                        <p class="text-xs text-gray-400">{{ formatDate(payment.payment_date) }} • {{ payment.payment_method }}</p>
                                    </div>
                                    <div class="flex gap-1">
                                        <!-- Ver Comprobante -->
                                        <el-tooltip v-if="payment.media && payment.media.length > 0" content="Ver comprobante" placement="top">
                                            <el-button 
                                                circle 
                                                size="small" 
                                                type="primary" 
                                                plain 
                                                icon="Picture" 
                                                @click="openPreview(payment.media[0])" 
                                            />
                                        </el-tooltip>

                                        <el-button 
                                            type="danger" 
                                            icon="Delete" 
                                            circle 
                                            size="small" 
                                            plain 
                                            class="opacity-0 group-hover:opacity-100 transition-opacity"
                                            @click="deletePayment(payment.id)" 
                                        />
                                    </div>
                                </div>
                                <p v-if="payment.reference" class="text-xs text-gray-500 mt-1 italic">Ref: {{ payment.reference }}</p>
                            </div>
                            <p v-if="budget.payments.length === 0" class="text-center text-xs text-gray-400">No hay pagos registrados.</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- MODAL NUEVO PAGO -->
        <el-dialog
            v-model="showPaymentModal"
            title="Registrar nuevo pago"
            width="450px"
        >
            <el-form :model="paymentForm" label-position="top">
                <el-form-item label="Monto">
                    <el-input-number 
                        v-model="paymentForm.amount" 
                        :min="0.01" 
                        :max="budget.balance_due" 
                        :precision="2"
                        class="!w-full"
                    />
                </el-form-item>
                
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Fecha">
                        <el-date-picker 
                            v-model="paymentForm.payment_date" 
                            type="date" 
                            class="!w-full"
                            format="DD/MM/YYYY"
                            value-format="YYYY-MM-DD"
                        />
                    </el-form-item>
                    <el-form-item label="Método">
                        <el-select v-model="paymentForm.payment_method" class="w-full">
                            <el-option label="Transferencia" value="Transferencia" />
                            <el-option label="Efectivo" value="Efectivo" />
                            <el-option label="Cheque" value="Cheque" />
                            <el-option label="Tarjeta" value="Tarjeta" />
                        </el-select>
                    </el-form-item>
                </div>

                <el-form-item label="Referencia / Nota">
                    <el-input v-model="paymentForm.reference" placeholder="Ej. Folio bancario" />
                </el-form-item>

                <!-- Campo Comprobante (Requerimiento 3) -->
                <el-form-item label="Comprobante (Opcional)">
                    <el-upload
                        ref="paymentUploadRef"
                        class="w-full"
                        :auto-upload="false"
                        :limit="1"
                        :on-change="handlePaymentProofChange"
                        accept="image/*,.pdf"
                    >
                        <template #trigger>
                            <el-button icon="Upload">Adjuntar archivo</el-button>
                        </template>
                    </el-upload>
                </el-form-item>
            </el-form>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="showPaymentModal = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" @click="submitPayment" :loading="paymentForm.processing">
                        Guardar Pago
                    </el-button>
                </span>
            </template>
        </el-dialog>

        <!-- MODAL DE PREVISUALIZACIÓN -->
        <el-dialog
            v-model="showPreviewModal"
            :title="previewTitle"
            width="80%"
            class="preview-modal"
            align-center
        >
            <div class="flex justify-center bg-gray-100 p-4 rounded min-h-[400px]">
                <img v-if="previewType === 'image'" :src="previewUrl" class="max-h-[70vh] object-contain" />
                
                <iframe 
                    v-else-if="previewType === 'pdf'" 
                    :src="previewUrl" 
                    class="w-full h-[70vh]" 
                    frameborder="0"
                ></iframe>
                
                <div v-else class="flex flex-col items-center justify-center text-gray-500">
                    <el-icon :size="48"><Document /></el-icon>
                    <p class="mt-4">Vista previa no disponible para este formato.</p>
                    <a :href="previewUrl" target="_blank" class="mt-2 text-primary hover:underline">
                        Descargar archivo
                    </a>
                </div>
            </div>
        </el-dialog>

    </AppLayout>
</template>