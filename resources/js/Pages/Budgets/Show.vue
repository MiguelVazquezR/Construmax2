<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    budget: Object,
});

// --- UTILS ---
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { 
        style: 'currency', 
        currency: props.budget.currency || 'MXN' 
    }).format(value || 0);
};

const formatMXN = (value) => {
    return new Intl.NumberFormat('es-MX', { 
        style: 'currency', 
        currency: 'MXN' 
    }).format(value || 0);
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

const getTicketStatusType = (status) => {
    const map = {
        'Programado': 'info',
        'En proceso': 'warning',
        'En espera': 'warning',
        'Revisión': 'primary',
        'Completado': 'success',
        'Cancelado': 'danger'
    };
    return map[status] || 'info';
};

// --- AUTOMATIZACIÓN DE TICKET ---
const generatingTicket = ref(false);

const generateTicket = () => {
    ElMessageBox.confirm(
        'Se generará un Ticket de servicio automáticamente copiando la información del presupuesto. La fecha de inicio será hoy y el término estimado en 2 semanas.',
        '¿Generar ticket automático?',
        {
            confirmButtonText: 'Sí, generar ticket',
            cancelButtonText: 'Cancelar',
            type: 'info',
            icon: 'Tools'
        }
    ).then(() => {
        generatingTicket.value = true;
        router.post(route('tickets.store-from-budget', props.budget.id), {}, {
            onSuccess: () => {
                ElMessage.success('Ticket generado correctamente');
                generatingTicket.value = false;
            },
            onError: () => {
                ElMessage.error('No se pudo generar el ticket');
                generatingTicket.value = false;
            }
        });
    }).catch(() => {});
};

// --- GESTIÓN DE PAGOS (CLIENTE) ---
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
const fileList = ref([]); 

const uploadForm = useForm({
    files: [],
});

const handleFilesChange = (file, fileListArg) => {
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
const previewType = ref('');
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
        window.open(file.original_url, '_blank');
        return;
    }
    showPreviewModal.value = true;
};

// --- GESTIÓN DE TÉCNICOS Y PAGOS ---
const showTechPaymentModal = ref(false);
const techPaymentUploadRef = ref(null);
const selectedTechnician = ref(null);

const techPaymentForm = useForm({
    user_id: null,
    amount: 0,
    payment_date: new Date().toISOString().split('T')[0],
    payment_method: 'Transferencia',
    reference: '',
    notes: '',
    proof: null,
});

// Calculamos los técnicos basados en las tareas asignadas + pagos existentes
const techniciansData = computed(() => {
    const techs = {};

    // 1. Procesar tareas del ticket
    if (props.budget.ticket && props.budget.ticket.tasks) {
        props.budget.ticket.tasks.forEach(task => {
            if (task.assignee) { // Solo si tiene técnico asignado
                const userId = task.user_id;
                if (!techs[userId]) {
                    techs[userId] = {
                        user: task.assignee,
                        total_tasks: 0,
                        completed_tasks: 0,
                        payments: [],
                        total_paid: 0
                    };
                }
                techs[userId].total_tasks++;
                if (task.status === 'Completada') {
                    techs[userId].completed_tasks++;
                }
            }
        });
    }

    // 2. Procesar pagos ya realizados (para que aparezcan aunque ya no tengan tareas)
    if (props.budget.technician_payments) {
        props.budget.technician_payments.forEach(payment => {
            const userId = payment.user_id;
            // Si el técnico no estaba en las tareas, lo inicializamos (puede haber pasado que le pagaron y luego le quitaron tareas)
            if (!techs[userId]) {
                techs[userId] = {
                    user: payment.technician, // Asumiendo que viene en la relación
                    total_tasks: 0,
                    completed_tasks: 0,
                    payments: [],
                    total_paid: 0
                };
            }
            techs[userId].payments.push(payment);
            techs[userId].total_paid += parseFloat(payment.amount);
        });
    }

    // 3. Convertir a array y calcular porcentaje
    return Object.values(techs).map(tech => ({
        ...tech,
        progress: tech.total_tasks > 0 ? Math.round((tech.completed_tasks / tech.total_tasks) * 100) : 0
    }));
});

const openTechPaymentModal = (tech) => {
    selectedTechnician.value = tech;
    techPaymentForm.reset();
    techPaymentForm.user_id = tech.user.id;
    if(techPaymentUploadRef.value) techPaymentUploadRef.value.clearFiles();
    showTechPaymentModal.value = true;
};

const handleTechProofChange = (file) => {
    techPaymentForm.proof = file.raw;
};

const submitTechPayment = () => {
    techPaymentForm.post(route('budgets.technician-payments.store', props.budget.id), {
        onSuccess: () => {
            showTechPaymentModal.value = false;
            techPaymentForm.reset();
            ElMessage.success('Pago a técnico registrado');
        },
        onError: () => ElMessage.error('Error al registrar pago'),
        forceFormData: true,
    });
};

const deleteTechPayment = (paymentId) => {
    ElMessageBox.confirm('¿Eliminar este registro de pago a técnico?', 'Confirmar', {
        type: 'warning'
    }).then(() => {
        router.delete(route('budgets.technician-payments.destroy', paymentId), {
            onSuccess: () => ElMessage.success('Pago eliminado')
        });
    }).catch(() => {});
};
</script>

<template>
    <AppLayout :title="`Presupuesto #${budget.id}`">
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-3">
                    <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">
                        Detalle del proyecto
                    </h2>
                    <el-tag :type="getStatusColor(budget.status)" effect="dark">
                        {{ budget.status }}
                    </el-tag>
                </div>
                
                <div class="flex gap-2 w-full sm:w-auto">
                    <Link :href="route('budgets.index')" class="w-full sm:w-auto">
                        <el-button class="w-full sm:w-auto" icon="Back">Volver al listado</el-button>
                    </Link>
                    <Link v-if="can('budgets.edit')" :href="route('budgets.edit', budget.id)" class="w-full sm:w-auto">
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
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">{{ budget.ticket?.name }}</h1>
                    <p class="text-sm text-gray-500 flex items-center gap-2">
                        <el-icon><Calendar /></el-icon> Registro: {{ formatDate(budget.created_at) }}
                        <span class="mx-1">•</span>
                        <el-icon><User /></el-icon> Responsable: {{ budget.responsible?.name }}
                    </p>
                </div>
                <div class="text-right w-full md:w-auto">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Monto Total</p>
                    <p class="text-3xl font-bold text-gray-800 dark:text-gray-100">
                        {{ formatCurrency(budget.total_cost) }} 
                        <span class="text-lg text-gray-400 font-normal">{{ budget.currency }}</span>
                    </p>
                    
                    <!-- Conversión visual si es USD -->
                    <p v-if="budget.currency === 'USD'" class="text-xs text-gray-500 mt-1">
                        ≈ {{ formatMXN(budget.total_cost * budget.exchange_rate) }} 
                        <span class="opacity-70">(TC: {{ budget.exchange_rate }})</span>
                    </p>
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
                                <el-descriptions-item label="Tipo de servicio">{{ budget.ticket?.service_type }}</el-descriptions-item>
                                <el-descriptions-item label="Prioridad">
                                    <el-tag size="small" :type="budget.ticket?.priority === 'Urgente' ? 'danger' : 'info'">{{ budget.ticket?.priority }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item label="Duración estimada">{{ budget.ticket?.duration || 'No especificada' }}</el-descriptions-item>
                                <el-descriptions-item label="Sucursal / Sitio">{{ budget.ticket?.branch?.branch_name }}</el-descriptions-item>
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

                    <!-- NUEVA SECCIÓN: TÉCNICOS Y PAGOS OPERATIVOS -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                        <div class="p-4 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#2b2b2e] flex flex-col sm:flex-row justify-between items-center gap-2">
                            <h3 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                <el-icon><Avatar /></el-icon> Gestión de Técnicos y Pagos
                            </h3>
                            <span class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded">Basado en tareas del Ticket #{{ budget.ticket?.id || 'N/A' }}</span>
                        </div>
                        
                        <div v-if="techniciansData.length > 0" class="p-4 space-y-6">
                            <div v-for="tech in techniciansData" :key="tech.user.id" class="border border-gray-100 dark:border-[#3f3f46] rounded-lg p-4 hover:shadow-sm transition-shadow">
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-3">
                                    <!-- Info Técnico -->
                                    <div class="flex items-center gap-3">
                                        <el-avatar :src="tech.user.profile_photo_url" :size="40">{{ tech.user.name.charAt(0) }}</el-avatar>
                                        <div>
                                            <p class="font-bold text-gray-800 dark:text-white">{{ tech.user.name }}</p>
                                            <p class="text-xs text-gray-500">{{ tech.completed_tasks }} / {{ tech.total_tasks }} tareas completadas</p>
                                        </div>
                                    </div>

                                    <!-- Barra Progreso -->
                                    <div class="w-full md:w-1/3 px-2">
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="text-gray-400">Progreso</span>
                                            <span class="font-bold text-gray-700 dark:text-gray-300">{{ tech.progress }}%</span>
                                        </div>
                                        <el-progress :percentage="tech.progress" :show-text="false" :status="tech.progress === 100 ? 'success' : ''" />
                                    </div>

                                    <!-- Acciones Pago -->
                                    <div class="flex items-center gap-3 w-full md:w-auto justify-between md:justify-end">
                                        <div class="text-right">
                                            <p class="text-xs text-gray-400">Total pagado</p>
                                            <p class="font-bold text-green-600">{{ formatCurrency(tech.total_paid) }}</p>
                                        </div>
                                        <el-button type="success" size="small" plain icon="Money" @click="openTechPaymentModal(tech)">
                                            Pagar
                                        </el-button>
                                    </div>
                                </div>

                                <!-- Lista Desplegable de Pagos (Si existen) -->
                                <div v-if="tech.payments.length > 0" class="mt-3 bg-gray-50 dark:bg-[#252529] rounded p-3 text-sm">
                                    <p class="text-xs font-bold text-gray-500 mb-2 uppercase">Historial de pagos</p>
                                    <ul class="space-y-2">
                                        <li v-for="pay in tech.payments" :key="pay.id" class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 last:border-0 pb-1 last:pb-0">
                                            <div class="flex gap-2 items-center">
                                                <span class="font-mono font-bold">{{ formatCurrency(pay.amount) }}</span>
                                                <span class="text-xs text-gray-400">({{ formatDate(pay.payment_date) }})</span>
                                                <el-tag v-if="pay.reference" size="small" type="info" class="scale-90">{{ pay.reference }}</el-tag>
                                            </div>
                                            <div class="flex gap-1">
                                                <el-tooltip content="Ver comprobante" placement="top" v-if="pay.media && pay.media.length > 0">
                                                    <el-button circle size="small" icon="Document" @click="openPreview(pay.media[0])" />
                                                </el-tooltip>
                                                <el-button circle size="small" type="danger" plain icon="Delete" @click="deleteTechPayment(pay.id)" />
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div v-else class="p-8 text-center text-gray-500">
                            <el-icon :size="40" class="mb-2 opacity-50"><User /></el-icon>
                            <p>No hay técnicos asignados a tareas en el ticket aún, o no se ha generado el ticket.</p>
                            <p class="text-xs mt-1">Genera el ticket y asigna tareas para gestionar pagos.</p>
                        </div>
                    </div>

                    <!-- ARCHIVOS ADJUNTOS -->
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

                    <!-- SECCIÓN NUEVA: TICKET / ORDEN DE SERVICIO -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6 relative overflow-hidden">
                        <div class="absolute top-0 right-0 p-4 opacity-5">
                            <el-icon :size="100"><Tools /></el-icon>
                        </div>
                        <h3 class="font-bold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2 relative z-10">
                            <el-icon><Tools /></el-icon> Orden de servicio
                        </h3>

                        <div v-if="budget.ticket" class="relative z-10">
                            <div class="flex justify-between items-start mb-3">
                                <span class="text-xs text-gray-500">Folio Ticket</span>
                                <span class="font-mono text-sm font-bold text-gray-800 dark:text-gray-200">#{{ budget.ticket.id }}</span>
                            </div>
                            
                            <div class="flex justify-between items-center mb-4">
                                <span class="text-xs text-gray-500">Estatus operativo</span>
                                <el-tag size="small" :type="getTicketStatusType(budget.ticket.status)">
                                    {{ budget.ticket.status }}
                                </el-tag>
                            </div>

                            <div class="mb-5">
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-gray-500">Progreso tareas</span>
                                    <span class="font-bold text-gray-700 dark:text-gray-300">{{ budget.ticket.progress || 0 }}%</span>
                                </div>
                                <el-progress 
                                    :percentage="budget.ticket.progress || 0" 
                                    :stroke-width="8" 
                                    :show-text="false" 
                                    :status="budget.ticket.status === 'Completado' ? 'success' : ''"
                                />
                            </div>

                            <div v-if="can('tickets.index')" class="mt-2">
                                <Link :href="route('tickets.show', budget.ticket.id)">
                                    <el-button type="primary" plain class="w-full" icon="Right">
                                        Ver seguimiento
                                    </el-button>
                                </Link>
                            </div>
                            <div v-else class="mt-2 p-2 bg-gray-50 dark:bg-[#252529] rounded text-xs text-center text-gray-500 border border-gray-100 dark:border-[#3f3f46]">
                                <el-icon class="mr-1"><Lock /></el-icon> Detalle restringido
                            </div>
                        </div>

                        <!-- LÓGICA DE CREACIÓN AUTOMÁTICA -->
                        <div v-else class="relative z-10">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                Este proyecto no tiene una orden de servicio (Ticket) activa. Se creará automáticamente con la información actual.
                            </p>
                            
                            <el-button 
                                type="primary" 
                                color="#f26c17" 
                                class="w-full" 
                                icon="Plus" 
                                @click="generateTicket"
                                :loading="generatingTicket"
                            >
                                Generar ticket automático
                            </el-button>
                        </div>
                    </div>
                    
                    <!-- INFO CLIENTE -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                        <h3 class="font-bold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2">
                            <el-icon><OfficeBuilding /></el-icon> Cliente
                        </h3>
                        
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs text-gray-400 uppercase">Empresa</p>
                                <p class="font-bold text-gray-800 dark:text-white">{{ budget.ticket?.customer?.name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase">Contacto</p>
                                <p class="text-sm text-gray-600 dark:text-gray-300">{{ budget.ticket?.contact?.name }}</p>
                                <a :href="`mailto:${budget.ticket?.contact?.email}`" class="text-xs text-primary hover:underline">{{ budget.ticket?.contact?.email }}</a>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase">Sucursal</p>
                                <p class="text-sm text-gray-600 dark:text-gray-300">{{ budget.ticket?.branch?.branch_name }}</p>
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
                            
                            <!-- INFO MONEDA -->
                            <div class="flex justify-between items-center pb-2 border-b border-gray-50 dark:border-gray-800">
                                <span class="text-xs text-gray-500">Moneda</span>
                                <div class="text-right">
                                    <span class="font-bold text-gray-800 dark:text-white text-sm block">{{ budget.currency }}</span>
                                    <span v-if="budget.currency === 'USD'" class="text-[10px] text-gray-400 block">
                                        TC: ${{ budget.exchange_rate }}
                                    </span>
                                </div>
                            </div>

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

        <!-- MODAL PAGO CLIENTE (Original) -->
        <el-dialog
            v-model="showPaymentModal"
            title="Registrar nuevo pago"
            width="450px"
        >
            <el-form :model="paymentForm" label-position="top">
                <el-form-item label="Monto">
                    <el-input-number 
                        v-model="paymentForm.amount" 
                        :min="0.00" 
                        :max="budget.balance_due" 
                        :precision="2"
                        class="!w-full"
                    >
                         <template #prefix>{{ budget.currency === 'USD' ? '$' : '$' }}</template>
                    </el-input-number>
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

                <!-- Campo Comprobante -->
                <el-form-item label="Comprobante (opcional)">
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
                        Guardar pago
                    </el-button>
                </span>
            </template>
        </el-dialog>

        <!-- MODAL PAGO A TÉCNICO (Nuevo) -->
        <el-dialog
            v-model="showTechPaymentModal"
            :title="`Pago a técnico: ${selectedTechnician?.user?.name}`"
            width="450px"
        >
            <el-form :model="techPaymentForm" label-position="top">
                <el-alert
                    title="Importante"
                    type="info"
                    description="El comprobante es obligatorio para los pagos a personal técnico."
                    show-icon
                    :closable="false"
                    class="mb-4"
                />

                <el-form-item label="Monto a Pagar">
                    <el-input-number 
                        v-model="techPaymentForm.amount" 
                        :min="0.01" 
                        :precision="2"
                        class="!w-full"
                    >
                         <template #prefix>$</template>
                    </el-input-number>
                </el-form-item>
                
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Fecha">
                        <el-date-picker 
                            v-model="techPaymentForm.payment_date" 
                            type="date" 
                            class="!w-full"
                            format="DD/MM/YYYY"
                            value-format="YYYY-MM-DD"
                        />
                    </el-form-item>
                    <el-form-item label="Método">
                        <el-select v-model="techPaymentForm.payment_method" class="w-full">
                            <el-option label="Transferencia" value="Transferencia" />
                            <el-option label="Efectivo" value="Efectivo" />
                            <el-option label="Nómina" value="Nómina" />
                        </el-select>
                    </el-form-item>
                </div>

                <el-form-item label="Referencia Bancaria">
                    <el-input v-model="techPaymentForm.reference" placeholder="Ej. SPEI-123456" />
                </el-form-item>
                
                <el-form-item label="Notas">
                    <el-input v-model="techPaymentForm.notes" type="textarea" placeholder="Concepto o detalles..." />
                </el-form-item>

                <!-- Comprobante OBLIGATORIO -->
                <el-form-item label="Comprobante de Pago (Obligatorio)" :error="techPaymentForm.errors.proof">
                    <el-upload
                        ref="techPaymentUploadRef"
                        class="w-full"
                        :auto-upload="false"
                        :limit="1"
                        :on-change="handleTechProofChange"
                        accept="image/*,.pdf"
                    >
                        <template #trigger>
                            <el-button type="primary" plain icon="Upload">Adjuntar Comprobante</el-button>
                        </template>
                        <template #tip>
                            <div class="el-upload__tip">
                                Archivos PDF o Imagen (Máx. 5MB)
                            </div>
                        </template>
                    </el-upload>
                </el-form-item>
            </el-form>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="showTechPaymentModal = false">Cancelar</el-button>
                    <el-button type="success" @click="submitTechPayment" :loading="techPaymentForm.processing">
                        Registrar Pago
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