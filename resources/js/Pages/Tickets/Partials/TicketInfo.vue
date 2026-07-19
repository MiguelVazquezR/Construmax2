<script setup>
import { useForm, router, Link } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';

const props = defineProps({
    ticket: Object,
});

const formatDate = (date) => {
    if(!date) return 'No definida';
    return new Date(date).toLocaleDateString('es-MX', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
};

const formatCurrency = (value, currency = 'MXN') => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: currency,
    }).format(value || 0);
};

const openEvidenceTemplate = () => {
    const url = route('tickets.evidence-template', props.ticket.id);
    window.open(url, '_blank');
};

const openCostsPrint = () => {
    const url = route('costs.print', props.ticket.budget.id);
    window.open(url, '_blank');
};

// --- EVIDENCIA GENERAL DEL TICKET ---
const handleGeneralUpload = (file) => {
    const form = useForm({ files: [file.raw] });
    form.post(route('tickets.evidence.store', props.ticket.id), {
        onSuccess: () => ElMessage.success('Archivo general subido'),
        onError: () => ElMessage.error('Error al subir archivo')
    });
};

const deleteEvidence = (mediaId) => {
    ElMessageBox.confirm('¿Eliminar archivo?', 'Confirmar', { type: 'warning' })
        .then(() => {
            router.delete(route('tickets.evidence.destroy', mediaId));
        }).catch(() => {});
};
</script>

<template>
    <div class="py-4 space-y-8">
        
        <!-- SECCIÓN 1: DETALLES OPERATIVOS -->
        <div>
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <el-icon><InfoFilled /></el-icon> Detalles Operativos
            </h3>
            
            <el-descriptions border :column="2" size="large">
                <el-descriptions-item label="Prioridad">
                    <el-tag :type="ticket.priority === 'Urgente' ? 'danger' : 'warning'">{{ ticket.priority }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="Estatus actual">{{ ticket.status }}</el-descriptions-item>
                
                <el-descriptions-item label="Inicio programado">{{ formatDate(ticket.scheduled_start) }}</el-descriptions-item>
                <el-descriptions-item label="Fin estimado">{{ formatDate(ticket.scheduled_end) }}</el-descriptions-item>
                
                <el-descriptions-item label="Instrucciones especiales" :span="2">
                    <div class="whitespace-pre-line text-gray-600 dark:text-gray-300">
                        {{ ticket.instructions || 'Sin instrucciones especiales.' }}
                    </div>
                </el-descriptions-item>
            </el-descriptions>
        </div>

        <!-- SECCIÓN 2: CONTEXTO DEL CLIENTE (Desde Presupuesto) -->
        <div>
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <el-icon><OfficeBuilding /></el-icon> Información del Cliente
            </h3>
            
            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-100 dark:border-blue-800">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-blue-500 uppercase font-bold">Cliente</p>
                        <p class="text-blue-900 dark:text-blue-100 font-medium">{{ ticket.customer?.name || 'Sin asignar' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-blue-500 uppercase font-bold">Contacto</p>
                        <p class="text-blue-900 dark:text-blue-100 font-medium">{{ ticket.contact?.name || 'Sin asignar' }}</p>
                        <p class="text-xs text-blue-700 dark:text-blue-300">{{ ticket.contact?.phone || '---' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-blue-500 uppercase font-bold">Sucursal / Ubicación</p>
                        <p class="text-blue-900 dark:text-blue-100 font-medium">
                            {{ ticket.branch ? `${ticket.branch.branch_name}${ticket.branch.city ? ' (' + ticket.branch.city : ''}${ticket.branch.region ? ', ' + ticket.branch.region : ''}${ticket.branch.city ? ')' : ''} - Unidad: ${ticket.branch.unit}` : 'Sin asignar' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-blue-500 uppercase font-bold">Tipo de Servicio</p>
                        <p class="text-blue-900 dark:text-blue-100 font-medium">{{ ticket.service_type || 'Sin especificar' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-blue-500 uppercase font-bold">No. Reporte / Ticket</p>
                        <p class="text-blue-900 dark:text-blue-100 font-medium">{{ ticket.report_number || 'Sin especificar' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-blue-500 uppercase font-bold">Vendedor / Asesor</p>
                        <p class="text-blue-900 dark:text-blue-100 font-medium">{{ ticket.seller?.name || 'Sin asignar' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECCIÓN 3: CATÁLOGO DE COSTOS Y PLANTILLA DE EVIDENCIAS -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                    <el-icon><FolderOpened /></el-icon> Catálogo de costos y evidencias
                </h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Catálogo de costos -->
                <div class="bg-gray-50 dark:bg-[#252529] rounded-lg p-4 border border-gray-200 dark:border-[#3f3f46]">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300">Último catálogo de costos</h4>
                        <div class="flex items-center gap-2">
                            <el-tag v-if="ticket.budget?.latest_catalog" type="success" size="small" effect="dark">
                                V{{ ticket.budget.latest_catalog.version }}
                            </el-tag>
                            <el-tag
                                v-if="ticket.budget?.latest_catalog"
                                :type="ticket.budget.latest_catalog.status === 'approved' ? 'success' : 'warning'"
                                size="small"
                                effect="plain"
                            >
                                {{ ticket.budget.latest_catalog.status === 'approved' ? 'Aprobado' : 'Pendiente de aprobación' }}
                            </el-tag>
                            <el-tag v-else type="info" size="small" effect="plain">Sin catálogo</el-tag>
                        </div>
                    </div>
                    <div v-if="ticket.budget?.latest_catalog" class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Subtotal:</span>
                            <span class="font-mono font-bold">{{ formatCurrency(ticket.budget.latest_catalog.subtotal, ticket.budget.currency) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">IVA:</span>
                            <span class="font-mono font-bold">{{ formatCurrency(ticket.budget.latest_catalog.iva, ticket.budget.currency) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-2">
                            <span class="text-gray-700 dark:text-gray-300 font-bold">Total:</span>
                            <span class="font-mono font-bold text-primary">{{ formatCurrency(ticket.budget.latest_catalog.total, ticket.budget.currency) }}</span>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <Link :href="route('costs.show', ticket.budget.id)">
                                <el-button type="primary" size="small" plain>Ver detalle completo</el-button>
                            </Link>
                            <el-button type="primary" size="small" @click="openCostsPrint">
                                Imprimir catálogo
                            </el-button>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400 italic">
                        No hay catálogo de costos registrado para este ticket.
                    </p>
                </div>

                <!-- Plantilla de evidencias -->
                <div class="bg-gray-50 dark:bg-[#252529] rounded-lg p-4 border border-gray-200 dark:border-[#3f3f46]">
                    <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">Recopilación de evidencias</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Genera una plantilla imprimible con todas las evidencias subidas por los técnicos, 
                        ordenadas cronológicamente, incluyendo el logo del cliente.
                    </p>
                    <el-button type="primary" @click="openEvidenceTemplate">
                        Ver plantilla de evidencias
                    </el-button>
                </div>
            </div>
        </div>

        <!-- SECCIÓN 4: ARCHIVOS GENERALES (Planos, Permisos, etc.) -->
        <div>
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                    <el-icon><FolderOpened /></el-icon> Archivos Generales del Ticket
                </h3>
                <el-upload
                    :show-file-list="false"
                    :auto-upload="false"
                    :on-change="handleGeneralUpload"
                    multiple
                >
                    <el-button type="primary" plain size="small" icon="Upload">Subir archivos</el-button>
                </el-upload>
            </div>

            <div v-if="ticket.media && ticket.media.length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div 
                    v-for="file in ticket.media" 
                    :key="file.id" 
                    class="border border-gray-200 dark:border-[#3f3f46] rounded-lg p-3 bg-white dark:bg-[#252529] relative group"
                >
                    <div class="flex items-center justify-center h-24 bg-gray-100 dark:bg-[#18181b] rounded mb-2 overflow-hidden">
                        <img v-if="file.mime_type.startsWith('image/')" :src="file.original_url" class="object-cover w-full h-full" />
                        <el-icon v-else :size="32" class="text-gray-400"><Document /></el-icon>
                    </div>
                    <p class="text-xs truncate text-gray-600 dark:text-gray-300" :title="file.file_name">{{ file.file_name }}</p>
                    
                    <div class="absolute top-1 right-1 hidden group-hover:flex gap-1">
                        <a :href="file.original_url" target="_blank" download>
                            <el-button circle size="small" icon="Download" type="info" />
                        </a>
                        <el-button circle size="small" type="danger" icon="Delete" @click="deleteEvidence(file.id)" />
                    </div>
                </div>
            </div>
            <p v-else class="text-gray-400 text-sm italic">No hay archivos generales adjuntos (planos, permisos, etc.).</p>
        </div>

    </div>
</template>