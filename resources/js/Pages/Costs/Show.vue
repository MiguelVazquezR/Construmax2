<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { ChatDotSquare, WarningFilled } from '@element-plus/icons-vue';
import { useCostsHelpers } from '@/Composables/useCostsHelpers';
import MaterialsTable from '@/Components/Costs/MaterialsTable.vue';
import LaborTable from '@/Components/Costs/LaborTable.vue';
import EmpenoFacilTotals from '@/Components/Costs/EmpenoFacilTotals.vue';

const props = defineProps({ budget: Object, canCreateCatalog: Boolean, canApprove: Boolean });
const { formatCurrency, copyToClipboard } = useCostsHelpers();

const currentVersion = ref(null);
const editingReportNumber = ref(false);
const reportNumberValue = ref('');
const editingStartDate = ref(false);
const startDateValue = ref('');
const editingEndDate = ref(false);
const endDateValue = ref('');

const isEmpenoFacil = computed(() => props.budget?.ticket?.customer?.id === 2);

const conceptsTotal = computed(() => {
    if (!props.budget.concepts?.length) return 0;
    return props.budget.concepts.reduce((sum, c) => sum + Number(c.amount || 0), 0);
});

// --- Form ---
const form = useForm({
    items: [],
    subtotal: 0,
    iva: 0,
    total: 0,
    include_iva: true,
    non_installation_labor: 0,
    labor_utility: 0,
});

// Empeño Fácil computed
const materialsItems = computed(() => form.items.filter(i => i.type === 'material'));
const laborItems = computed(() => form.items.filter(i => i.type === 'labor'));
const materialsSubtotal = computed(() => materialsItems.value.reduce((s, i) => s + Number(i.total || 0), 0));
const laborSubtotal = computed(() => laborItems.value.reduce((s, i) => s + Number(i.total || 0), 0));
const combinedSubtotal = computed(() => materialsSubtotal.value + laborSubtotal.value);
const grandTotal = computed(() => {
    const base = combinedSubtotal.value + Number(form.non_installation_labor || 0) + Number(form.labor_utility || 0);
    if (isEmpenoFacil.value) return Number(base.toFixed(2));
    const iva = form.include_iva ? Number((base * 0.16).toFixed(2)) : 0;
    return Number((base + iva).toFixed(2));
});

// Auto-calculated defaults for Empeño Fácil
const userEditedNonInstallationLabor = ref(false);
const userEditedLaborUtility = ref(false);

const autoNonInstallationLabor = computed(() =>
    Number((materialsSubtotal.value * 0.12).toFixed(2))
);
const autoLaborUtility = computed(() =>
    Number((combinedSubtotal.value * 0.18).toFixed(2))
);

// --- Lifecycle ---
onMounted(() => {
    if (props.budget.latest_catalog?.items) {
        currentVersion.value = props.budget.latest_catalog.version;
        form.items = props.budget.latest_catalog.items.map(item => ({
            type: item.type || 'material',
            description: item.description,
            unit: item.unit || 'PZA',
            technician: item.technician || '',
            hours: Number(item.hours || 0),
            rate: Number(item.rate || 0),
            quantity: Number(item.quantity),
            unit_price: Number(item.unit_price),
            total: Number(item.total),
        }));
        form.non_installation_labor = Number(props.budget.latest_catalog.non_installation_labor || 0);
        form.labor_utility = Number(props.budget.latest_catalog.labor_utility || 0);
    } else {
        userEditedNonInstallationLabor.value = false;
        userEditedLaborUtility.value = false;
    }
    calculateTotals();
});

// --- Item management ---
function addItemRow(type = 'material') {
    const base = { type, description: '', unit: 'PZA', quantity: 1, unit_price: 0, total: 0 };
    if (type === 'labor') Object.assign(base, { technician: '', hours: 1, rate: 0 });
    form.items.push(base);
}

function removeItemRow(item) {
    form.items.splice(form.items.indexOf(item), 1);
    calculateTotals();
}

function calculateRowTotal(row) {
    row.total = row.type === 'labor'
        ? Number((Number(row.hours || 0) * Number(row.rate || 0)).toFixed(2))
        : Number((row.quantity * row.unit_price).toFixed(2));
    calculateTotals();
}

function calculateTotals() {
    if (isEmpenoFacil.value) {
        if (!userEditedNonInstallationLabor.value) {
            form.non_installation_labor = autoNonInstallationLabor.value;
        }
        if (!userEditedLaborUtility.value) {
            form.labor_utility = autoLaborUtility.value;
        }
        form.subtotal = combinedSubtotal.value;
        form.iva = 0;
        form.total = grandTotal.value;
    } else {
        form.subtotal = form.items.reduce((s, i) => s + Number(i.total || 0), 0);
        form.iva = form.include_iva ? Number((form.subtotal * 0.16).toFixed(2)) : 0;
        form.total = Number((form.subtotal + form.iva).toFixed(2));
    }
}

// --- Submit ---
function submitCatalog() {
    if (!form.items.length) return ElMessage.warning('Debes agregar al menos un concepto al catálogo.');
    if (form.items.some(i => !i.description || (i.type === 'material' && !i.unit)))
        return ElMessage.warning('Completa la descripción y unidad de todos los conceptos.');

    const doSubmit = () => form.post(route('costs.store-catalog', props.budget.id), {
        preserveScroll: true,
        onSuccess: () => {
            currentVersion.value = props.budget.latest_catalog?.version;
            ElMessage.success('Catálogo actualizado correctamente.');
        },
        onError: (errors) => {
            ElMessage.error('Ocurrió un error al guardar el catálogo.');
        },
        onFinish: () => {
            // Processing is automatically reset by useForm
        },
    });

    if (props.budget.latest_catalog) {
        ElMessageBox.confirm(
            'Se creará una nueva versión del catálogo y se mantendrá el historial anterior. ¿Continuar?',
            'Guardar Catálogo',
            { confirmButtonText: 'Sí, guardar', cancelButtonText: 'Cancelar', type: 'info' }
        ).then(doSubmit).catch(() => {});
    } else {
        Promise.resolve().then(doSubmit);
    }
}

function viewCatalogVersion(versionId) {
    const cat = props.budget.catalogs.find(c => c.id === versionId);
    if (!cat) return;
    currentVersion.value = cat.version;
    form.items = cat.items.map(item => ({
        type: item.type || 'material',
        description: item.description,
        unit: item.unit || 'PZA',
        technician: item.technician || '',
        hours: Number(item.hours || 0),
        rate: Number(item.rate || 0),
        quantity: Number(item.quantity),
        unit_price: Number(item.unit_price),
        total: Number(item.total),
    }));
    form.include_iva = Number(cat.iva) > 0;
    form.non_installation_labor = Number(cat.non_installation_labor || 0);
    form.labor_utility = Number(cat.labor_utility || 0);
    userEditedNonInstallationLabor.value = true;
    userEditedLaborUtility.value = true;
    calculateTotals();
    ElMessage.info(`Mostrando la versión ${cat.version}`);
}

function openUrl(url) { window.open(url, '_blank'); }

// --- Inline field editing ---
function startEditReportNumber() {
    reportNumberValue.value = props.budget.ticket?.report_number || '';
    editingReportNumber.value = true;
}

function saveTicketField(field, value, editingRef, successMsg) {
    router.put(route('tickets.update-field', props.budget.ticket.id), {
        field: field,
        value: value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            editingRef.value = false;
            ElMessage.success(successMsg);
        },
        onError: () => ElMessage.error('Error al actualizar.'),
    });
}

function saveReportNumber() {
    saveTicketField('report_number', reportNumberValue.value, editingReportNumber, 'Número de reporte actualizado.');
}

function startEditStartDate() {
    startDateValue.value = props.budget.ticket?.scheduled_start || '';
    editingStartDate.value = true;
}

function saveStartDate() {
    saveTicketField('scheduled_start', startDateValue.value, editingStartDate, 'Fecha de inicio actualizada.');
}

function startEditEndDate() {
    endDateValue.value = props.budget.ticket?.scheduled_end || '';
    editingEndDate.value = true;
}

function saveEndDate() {
    saveTicketField('scheduled_end', endDateValue.value, editingEndDate, 'Fecha de fin actualizada.');
}

function formatDateStr(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' });
}

function approveCatalog() {
    const catalogId = props.budget.latest_catalog?.id;
    if (!catalogId) return;

    ElMessageBox.confirm(
        '¿Estás seguro de aprobar este catálogo de costos? Una vez aprobado, el asesor recibirá una notificación.',
        'Aprobar catálogo',
        {
            confirmButtonText: 'Sí, aprobar',
            cancelButtonText: 'Cancelar',
            type: 'info',
        }
    ).then(() => {
        router.post(route('costs.approve-catalog', { budget: props.budget.id, catalog: catalogId }), {}, {
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success('Catálogo de costos aprobado correctamente.');
            },
            onError: () => {
                ElMessage.error('Error al aprobar el catálogo.');
            },
        });
    }).catch(() => {});
}

</script>

<template>
    <AppLayout :title="`Catálogo de Costos - ${budget.ticket.folio}`">
        <div class="space-y-6">

            <!-- Header -->
            <div class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-4">
                    <Link :href="route('costs.index')"><el-button circle icon="Back" /></Link>
                    <div>
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white leading-tight">{{ budget.ticket.name }}</h2>
                        <div class="flex items-center gap-2 text-sm text-gray-500">
                            <span>{{ budget.ticket.folio }}</span>
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                            <span>{{ budget.ticket.customer.name }}</span>
                            <!-- Approval status badge -->
                            <el-tag v-if="budget.latest_catalog" :type="budget.latest_catalog.is_approved ? 'success' : 'warning'" size="small" effect="dark">
                                {{ budget.latest_catalog.status_label }}
                            </el-tag>
                            <span v-if="budget.latest_catalog?.approved_by_name" class="text-xs text-gray-400">
                                por {{ budget.latest_catalog.approved_by_name }}
                            </span>
                            <el-dropdown v-if="budget.catalogs.length > 0" trigger="click" @command="viewCatalogVersion">
                                <span class="cursor-pointer text-sm text-green-600 hover:text-green-700 transition flex items-center gap-1 font-medium bg-green-50 px-2 py-0.5 rounded border border-green-200">
                                    Versión mostrada: {{ currentVersion }} <el-icon><ArrowDown /></el-icon>
                                </span>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item v-for="cat in budget.catalogs" :key="cat.id" :command="cat.id">
                                            Versión {{ cat.version }} ({{ formatCurrency(cat.total, budget.currency) }})
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                            <el-tag v-else type="info" size="small" effect="plain">Sin catálogo previo</el-tag>
                        </div>
                        <!-- Important note from ticket -->
                        <div v-if="budget.ticket.important_note" class="mt-3 p-3 rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/30">
                            <p class="text-xs font-bold text-amber-700 dark:text-amber-300 uppercase tracking-wide mb-1 flex items-center gap-1">
                                <el-icon :size="14"><ChatDotSquare /></el-icon>
                                Notas importantes
                            </p>
                            <p class="text-sm text-amber-800 dark:text-amber-200 leading-relaxed">
                                {{ budget.ticket.important_note }}
                            </p>
                        </div>
                        <!-- Approve button -->
                        <div v-if="budget.latest_catalog && !budget.latest_catalog.is_approved && canApprove" class="mt-2">
                            <el-button type="success" size="small" icon="Check" @click="approveCatalog">
                                Aprobar catálogo
                            </el-button>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-500 mt-1">
                            <span class="text-gray-400">No. Reporte:</span>
                            <template v-if="editingReportNumber">
                                <el-input v-model="reportNumberValue" size="small" class="!w-48" placeholder="Número de reporte" @keyup.enter="saveReportNumber" @keyup.escape="editingReportNumber = false" />
                                <el-button type="primary" size="small" circle icon="Check" @click="saveReportNumber" />
                                <el-button size="small" circle icon="Close" @click="editingReportNumber = false" />
                            </template>
                            <template v-else>
                                <span class="font-medium">{{ budget.ticket.report_number || '—' }}</span>
                                <el-button text size="small" icon="Edit" class="!text-gray-400 hover:!text-blue-500" @click="startEditReportNumber" title="Editar número de reporte" />
                            </template>
                            <span class="text-gray-300 dark:text-gray-600 mx-1">|</span>
                            <span class="text-gray-400">Inicio:</span>
                            <template v-if="editingStartDate">
                                <el-date-picker v-model="startDateValue" type="date" size="small" class="!w-40" placeholder="Fecha inicio" format="DD/MM/YYYY" value-format="YYYY-MM-DD" @keyup.escape="editingStartDate = false" />
                                <el-button type="primary" size="small" circle icon="Check" @click="saveStartDate" />
                                <el-button size="small" circle icon="Close" @click="editingStartDate = false" />
                            </template>
                            <template v-else>
                                <span class="font-medium">{{ formatDateStr(budget.ticket.scheduled_start) }}</span>
                                <el-button text size="small" icon="Edit" class="!text-gray-400 hover:!text-blue-500" @click="startEditStartDate" title="Editar fecha de inicio" />
                            </template>
                            <span class="text-gray-300 dark:text-gray-600 mx-1">|</span>
                            <span class="text-gray-400">Fin:</span>
                            <template v-if="editingEndDate">
                                <el-date-picker v-model="endDateValue" type="date" size="small" class="!w-40" placeholder="Fecha fin" format="DD/MM/YYYY" value-format="YYYY-MM-DD" @keyup.escape="editingEndDate = false" />
                                <el-button type="primary" size="small" circle icon="Check" @click="saveEndDate" />
                                <el-button size="small" circle icon="Close" @click="editingEndDate = false" />
                            </template>
                            <template v-else>
                                <span class="font-medium">{{ formatDateStr(budget.ticket.scheduled_end) }}</span>
                                <el-button text size="small" icon="Edit" class="!text-gray-400 hover:!text-blue-500" @click="startEditEndDate" title="Editar fecha de fin" />
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info & Team -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div v-if="budget.description" class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <h3 class="text-md font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <el-icon><Edit /></el-icon> Notas comerciales
                    </h3>
                    <div class="bg-gray-50 dark:bg-[#252529] rounded-lg p-4 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed min-h-[80px]">
                        {{ budget.description }}
                    </div>
                </div>

                <div v-if="budget.ticket.technicians?.length" class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <h3 class="text-md font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <el-icon><UserFilled /></el-icon> Técnicos asignados
                        <el-tag size="small" type="primary" effect="plain">{{ budget.ticket.technicians.length }}</el-tag>
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="tech in budget.ticket.technicians" :key="tech.id"
                            class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-[#252529] rounded-lg border border-gray-100 dark:border-[#3f3f46]">
                            <el-avatar :size="36" :src="tech.profile_photo_url" class="shrink-0 border border-gray-200 dark:border-gray-600">
                                {{ tech.name.charAt(0) }}
                            </el-avatar>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200 truncate">{{ tech.name }}</p>
                                <div class="flex items-center gap-1 mt-0.5 flex-wrap">
                                    <el-tag size="small" :type="tech.level === 'Encargado' ? 'primary' : 'warning'" effect="dark" class="!h-4 !text-[9px] !px-1">
                                        {{ tech.level === 'Encargado' ? 'Encargado' : 'Auxiliar' }}
                                    </el-tag>
                                    <el-tag v-if="tech.phone" size="small" type="info" effect="plain" class="!h-4 !text-[9px] !px-1">
                                        {{ tech.phone }}
                                    </el-tag>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Conceptos + Archivos -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <h3 class="text-md font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <el-icon><Money /></el-icon> Conceptos base (Presupuesto)
                    </h3>
                    <el-table :data="budget.concepts" size="small" border class="w-full">
                        <el-table-column prop="concept" label="Concepto comercial" min-width="200" />
                        <el-table-column label="Monto referencial" width="140" align="right">
                            <template #default="scope">{{ formatCurrency(scope.row.amount, budget.currency) }}</template>
                        </el-table-column>
                    </el-table>
                    <div class="mt-3 text-right text-sm text-gray-600 dark:text-gray-400">
                        Total cotizado: <span class="font-bold">{{ formatCurrency(conceptsTotal, budget.currency) }}</span>
                    </div>
                </div>

                <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <h3 class="text-md font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <el-icon><FolderOpened /></el-icon> Archivos del presupuesto
                    </h3>
                    <div v-if="budget.survey_images.length > 0">
                        <p class="text-xs text-gray-400 mb-3">Imágenes y documentos adjuntos al registrar el presupuesto.</p>
                        <div class="flex flex-wrap gap-2">
                            <el-image v-for="img in budget.survey_images" :key="img.id" :src="img.url"
                                :preview-src-list="budget.survey_images.map(i => i.url)"
                                :initial-index="budget.survey_images.findIndex(i => i.id === img.id)" fit="cover"
                                class="w-24 h-24 rounded-md border border-gray-200 dark:border-gray-700 cursor-pointer" />
                        </div>
                    </div>
                    <div v-else class="text-center p-6 text-gray-400 bg-gray-50 dark:bg-[#252529] rounded-md border border-dashed border-gray-300 dark:border-gray-700">
                        No hay archivos adjuntos en el presupuesto.
                    </div>
                </div>
            </div>

            <!-- Ticket files -->
            <div v-if="budget.ticket_media?.length" class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-4">
                <h3 class="text-md font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                    <el-icon><FolderOpened /></el-icon> Archivos subidos en el ticket
                    <el-tag size="small" type="info" effect="plain">{{ budget.ticket_media.length }} archivos</el-tag>
                </h3>
                <p class="text-xs text-gray-400 mb-3">Archivos generales subidos al ticket (planos, permisos, documentos).</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <div v-for="file in budget.ticket_media" :key="file.id"
                        class="relative group border border-gray-200 dark:border-[#3f3f46] rounded-lg overflow-hidden bg-white dark:bg-[#252529]">
                        <el-image v-if="file.mime_type?.startsWith('image/')" :src="file.url" fit="cover" class="w-full h-24 cursor-pointer"
                            :preview-src-list="budget.ticket_media.filter(f => f.mime_type?.startsWith('image/')).map(f => f.url)"
                            :initial-index="budget.ticket_media.filter(f => f.mime_type?.startsWith('image/')).findIndex(f => f.id === file.id)" hide-on-click-modal />
                        <div v-else class="w-full h-24 bg-gray-100 dark:bg-[#18181b] flex items-center justify-center">
                            <el-icon :size="32" class="text-gray-400"><Document /></el-icon>
                        </div>
                        <div class="p-1.5 flex items-center justify-between">
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate flex-1" :title="file.file_name">{{ file.file_name }}</p>
                            <a :href="file.url" target="_blank" download class="shrink-0 ml-1">
                                <el-button circle size="small" icon="Download" type="info" plain class="!w-5 !h-5" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Task evidence -->
            <div v-if="budget.task_evidence?.length" class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-4">
                <h3 class="text-md font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                    <el-icon><Camera /></el-icon> Evidencias de campo (técnicos)
                    <el-tag size="small" type="success" effect="plain">{{ budget.task_evidence.length }} archivos</el-tag>
                </h3>
                <p class="text-xs text-gray-400 mb-3">Fotos y videos registrados por los técnicos durante la ejecución de las tareas.</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <div v-for="ev in budget.task_evidence" :key="ev.id"
                        class="relative group border border-gray-200 dark:border-[#3f3f46] rounded-lg overflow-hidden bg-white dark:bg-[#252529]">
                        <el-image v-if="ev.mime_type?.startsWith('image/')" :src="ev.url" fit="cover" class="w-full h-24 cursor-pointer"
                            :preview-src-list="budget.task_evidence.filter(e => e.mime_type?.startsWith('image/')).map(e => e.url)"
                            :initial-index="budget.task_evidence.filter(e => e.mime_type?.startsWith('image/')).findIndex(e => e.id === ev.id)" hide-on-click-modal />
                        <div v-else-if="ev.mime_type?.startsWith('video/')" class="w-full h-24 bg-gray-900 flex items-center justify-center cursor-pointer" @click="openUrl(ev.url)">
                            <video class="w-full h-full object-cover" muted preload="metadata"><source :src="ev.url" /></video>
                            <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                                <el-icon class="text-white" :size="28"><VideoPlay /></el-icon>
                            </div>
                        </div>
                        <div class="p-1.5">
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate" :title="ev.file_name">{{ ev.file_name }}</p>
                            <p class="text-[9px] text-gray-400 dark:text-gray-500 truncate">Tarea: {{ ev.task_name }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- EMPEÑO FÁCIL: Catálogo especial                               -->
            <!-- ============================================================ -->
            <template v-if="isEmpenoFacil">
                <MaterialsTable
                    :items="materialsItems"
                    :currency="budget.currency"
                    :can-edit="canCreateCatalog"
                    @add="addItemRow('material')"
                    @remove="removeItemRow"
                    @calculate="calculateRowTotal"
                >
                    <template #subtotal>
                        <el-button text size="small" class="!font-mono !font-bold !text-gray-800 dark:!text-gray-200" @click="copyToClipboard(materialsSubtotal)">
                            {{ formatCurrency(materialsSubtotal, budget.currency) }}
                        </el-button>
                    </template>
                </MaterialsTable>

                <LaborTable
                    :items="laborItems"
                    :currency="budget.currency"
                    :can-edit="canCreateCatalog"
                    @add="addItemRow('labor')"
                    @remove="removeItemRow"
                    @calculate="calculateRowTotal"
                >
                    <template #subtotal>
                        <el-button text size="small" class="!font-mono !font-bold !text-gray-800 dark:!text-gray-200" @click="copyToClipboard(laborSubtotal)">
                            {{ formatCurrency(laborSubtotal, budget.currency) }}
                        </el-button>
                    </template>
                </LaborTable>

                <EmpenoFacilTotals
                    :combined-subtotal="combinedSubtotal"
                    :materials-subtotal="materialsSubtotal"
                    :non-installation-labor="form.non_installation_labor"
                    :labor-utility="form.labor_utility"
                    :iva="form.iva"
                    :total="form.total"
                    :include-iva="form.include_iva"
                    :loading="form.processing"
                    :can-edit="canCreateCatalog"
                    :currency="budget.currency"
                    @update:non-installation-labor="v => { form.non_installation_labor = v; userEditedNonInstallationLabor = true; calculateTotals(); }"
                    @update:labor-utility="v => { form.labor_utility = v; userEditedLaborUtility = true; calculateTotals(); }"
                    @save="submitCatalog"
                    @print="$inertia.visit(route('costs.print-empeno-facil', $props.budget.id))"
                />
            </template>

            <!-- ============================================================ -->
            <!-- DEFAULT: Catálogo estándar                                    -->
            <!-- ============================================================ -->
            <div v-else class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <div class="p-4 border-b border-gray-100 dark:border-[#2b2b2e] flex justify-between items-center">
                    <h3 class="text-md font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <el-icon><List /></el-icon> Desglose de Catálogo (Costos)
                    </h3>
                    <div class="flex items-center gap-2">
                        <el-button v-if="canCreateCatalog" type="primary" plain size="small" icon="Plus" @click="addItemRow('material')">Agregar fila</el-button>
                        <el-button type="info" plain size="small" icon="Printer"
                            @click="$inertia.visit(route('costs.print', $props.budget.id))" :disabled="!currentVersion">
                            Imprimir catálogo
                        </el-button>
                        <el-button v-if="canCreateCatalog" type="primary" color="#f26c17" size="small" icon="Check" @click="submitCatalog" :loading="form.processing">
                            Guardar versión
                        </el-button>
                    </div>
                </div>
                <div v-if="!canCreateCatalog" class="px-4 py-2 bg-amber-50 dark:bg-amber-900/20 border-b border-amber-100 dark:border-amber-800">
                    <p class="text-xs text-amber-700 dark:text-amber-300 flex items-center gap-1">
                        <el-icon><InfoFilled /></el-icon>
                        No tienes permiso para crear versiones de catálogo. Los campos están deshabilitados.
                    </p>
                </div>

                <div class="p-4 overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-[#252529]">
                            <tr>
                                <th class="px-4 py-3 min-w-[300px]">Descripción / Concepto</th>
                                <th class="px-4 py-3 w-32">Unidad</th>
                                <th class="px-4 py-3 w-32 text-right">Cantidad</th>
                                <th class="px-4 py-3 w-40 text-right">Precio Unitario</th>
                                <th class="px-4 py-3 w-40 text-right">Total</th>
                                <th class="px-4 py-3 w-16 text-center"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in form.items" :key="index" class="border-b dark:border-[#2b2b2e]">
                                <td class="px-2 py-2"><el-input v-model="item.description" type="textarea" :rows="2" placeholder="Descripción detallada" :disabled="!canCreateCatalog" /></td>
                                <td class="px-2 py-2"><el-input v-model="item.unit" placeholder="Ej. PZA, ML, M2" :disabled="!canCreateCatalog" /></td>
                                <td class="px-2 py-2"><el-input-number v-model="item.quantity" :min="0.01" :step="1" :controls="false" class="!w-full text-right" @change="calculateRowTotal(item)" :disabled="!canCreateCatalog" /></td>
                                <td class="px-2 py-2"><el-input-number v-model="item.unit_price" :min="0" :step="0.01" :controls="false" class="!w-full text-right" @change="calculateRowTotal(item)" :disabled="!canCreateCatalog" /></td>
                                <td class="px-4 py-2 text-right font-mono font-bold text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-[#252529]">
                                    {{ formatCurrency(item.total, budget.currency) }}
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <el-button v-if="canCreateCatalog" type="danger" plain circle icon="Delete" size="small" @click="removeItemRow(item)" />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3"></td>
                                <td class="px-4 py-3 text-right font-bold text-gray-600 dark:text-gray-400">Subtotal:</td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-[#252529]">{{ formatCurrency(form.subtotal, budget.currency) }}</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td class="px-4 py-3 text-right font-bold text-gray-600 dark:text-gray-400">
                                    <el-checkbox v-model="form.include_iva" @change="calculateTotals" label="IVA (16%):" class="!mr-0" :disabled="!canCreateCatalog" />
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-[#252529]">{{ formatCurrency(form.iva, budget.currency) }}</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td class="px-4 py-3 text-right font-bold text-gray-800 dark:text-white">TOTAL:</td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-[#f26c17] bg-gray-50 dark:bg-[#252529] text-lg">{{ formatCurrency(form.total, budget.currency) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.el-input-number .el-input__inner) { text-align: right; }
</style>
