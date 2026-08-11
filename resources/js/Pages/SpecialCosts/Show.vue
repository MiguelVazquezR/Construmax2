<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { ChatDotSquare, WarningFilled } from '@element-plus/icons-vue';
import { useCostsHelpers } from '@/Composables/useCostsHelpers';

const props = defineProps({
    catalog: Object,
    budget: Object,
    ticket: Object,
    latest_catalog: Object,
    catalogs: Array,
    concepts: Array,
    task_evidence: Array,
    ticket_media: Array,
    canCreateCatalog: Boolean,
    canApprove: Boolean,
});

const { formatCurrency, copyToClipboard } = useCostsHelpers();

const currentVersion = ref(null);
const editingReportNumber = ref(false);
const reportNumberValue = ref('');

const form = useForm({
    items: [],
    subtotal: 0,
    iva: 0,
    total: 0,
    include_iva: true,
    non_installation_labor: 0,
    labor_utility: 0,
});

const conceptsTotal = computed(() => {
    if (!props.concepts?.length) return 0;
    return props.concepts.reduce((sum, c) => sum + Number(c.amount || 0), 0);
});

onMounted(() => {
    const source = props.latest_catalog || props.catalog;
    if (source?.items) {
        currentVersion.value = source.version;
        form.items = source.items.map(item => ({
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
        form.non_installation_labor = Number(source.non_installation_labor || 0);
        form.labor_utility = Number(source.labor_utility || 0);
    }
    calculateTotals();
});

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
    form.subtotal = form.items.reduce((s, i) => s + Number(i.total || 0), 0);
    form.iva = form.include_iva ? Number((form.subtotal * 0.16).toFixed(2)) : 0;
    form.total = Number((form.subtotal + form.iva).toFixed(2));
}

function submitCatalog() {
    if (!form.items.length) return ElMessage.warning('Debes agregar al menos un concepto.');
    if (form.items.some(i => !i.description || (i.type === 'material' && !i.unit)))
        return ElMessage.warning('Completa la descripcion y unidad de todos los conceptos.');

    const doSubmit = () => form.post(route('special-costs.store-catalog', props.catalog.id), {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Nueva version del catalogo creada. Pendiente de aprobacion.');
        },
        onError: () => {
            ElMessage.error('Ocurrio un error al crear la nueva version.');
        },
    });

    ElMessageBox.confirm(
        'Se creara una nueva version del catalogo y se mantendra el historial anterior. Continuar?',
        'Crear nueva version',
        { confirmButtonText: 'Si, crear version', cancelButtonText: 'Cancelar', type: 'info' }
    ).then(doSubmit).catch(() => {});
}

function approveCatalog() {
    const catalogId = props.latest_catalog?.id || props.catalog.id;

    ElMessageBox.confirm(
        'Estas seguro de aprobar este catalogo de costos especiales? Una vez aprobado, el asesor recibira una notificacion y el catalogo desaparecera de esta lista.',
        'Aprobar catalogo especial',
        {
            confirmButtonText: 'Si, aprobar',
            cancelButtonText: 'Cancelar',
            type: 'info',
        }
    ).then(() => {
        router.post(route('special-costs.approve-catalog', catalogId), {}, {
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success('Catalogo aprobado correctamente. Redirigiendo al listado...');
            },
            onError: () => {
                ElMessage.error('Error al aprobar el catalogo.');
            },
        });
    }).catch(() => {});
}

function viewCatalogVersion(versionId) {
    const cat = props.catalogs.find(c => c.id === versionId);
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
    calculateTotals();
    ElMessage.info('Mostrando la version ' + cat.version);
}

function startEditReportNumber() {
    reportNumberValue.value = props.ticket?.report_number || '';
    editingReportNumber.value = true;
}

function saveReportNumber() {
    router.put(route('tickets.update-field', props.ticket.id), {
        field: 'report_number',
        value: reportNumberValue.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            editingReportNumber.value = false;
            ElMessage.success('Numero de reporte actualizado.');
        },
        onError: () => ElMessage.error('Error al actualizar.'),
    });
}

function formatDateStr(dateStr) {
    if (!dateStr) return '---';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return '---';
    return date.toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' });
}

function openUrl(url) { window.open(url, '_blank'); }
</script>

<template>
    <AppLayout :title="'Costos especiales - ' + ticket.folio">
        <div class="space-y-6">

            <!-- Header -->
            <div class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <div class="flex items-center gap-4 mb-3">
                    <Link :href="route('special-costs.index')"><el-button circle icon="Back" /></Link>
                    <div class="flex-1">
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white leading-tight">{{ ticket.name }}</h2>
                        <div class="flex items-center gap-2 text-sm text-gray-500 flex-wrap">
                            <span>{{ ticket.folio }}</span>
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                            <span>{{ ticket.customer.name }}</span>
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                            <span>{{ ticket.branch.branch_name }}</span>
                            <el-tag :type="latest_catalog?.is_approved ? 'success' : 'warning'" size="small" effect="dark">
                                {{ latest_catalog?.status_label || 'Pendiente' }}
                            </el-tag>
                            <span v-if="latest_catalog?.approved_by_name" class="text-xs text-gray-400">
                                por {{ latest_catalog.approved_by_name }}
                            </span>
                            <el-dropdown v-if="catalogs.length > 0" trigger="click" @command="viewCatalogVersion">
                                <span class="cursor-pointer text-sm text-green-600 hover:text-green-700 transition flex items-center gap-1 font-medium bg-green-50 px-2 py-0.5 rounded border border-green-200">
                                    Version mostrada: {{ currentVersion }} <el-icon><ArrowDown /></el-icon>
                                </span>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item v-for="cat in catalogs" :key="cat.id" :command="cat.id">
                                            Version {{ cat.version }} ({{ formatCurrency(cat.total) }})
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-500 mt-1">
                            <span class="text-gray-400">No. Reporte:</span>
                            <template v-if="editingReportNumber">
                                <el-input v-model="reportNumberValue" size="small" class="!w-48" placeholder="Numero de reporte" @keyup.enter="saveReportNumber" @keyup.escape="editingReportNumber = false" />
                                <el-button type="primary" size="small" circle icon="Check" @click="saveReportNumber" />
                                <el-button size="small" circle icon="Close" @click="editingReportNumber = false" />
                            </template>
                            <template v-else>
                                <span class="font-medium">{{ ticket.report_number || '---' }}</span>
                                <el-button text size="small" icon="Edit" class="!text-gray-400 hover:!text-blue-500" @click="startEditReportNumber" title="Editar numero de reporte" />
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Transfer notes -->
                <div v-if="catalog.transfer_notes" class="mt-3 p-4 rounded-lg border border-orange-300 dark:border-orange-700 bg-orange-50 dark:bg-orange-900/30">
                    <p class="text-xs font-bold text-orange-700 dark:text-orange-300 uppercase tracking-wide mb-1 flex items-center gap-1">
                        <el-icon :size="14"><WarningFilled /></el-icon>
                        Notas de transferencia
                    </p>
                    <p class="text-sm text-orange-800 dark:text-orange-200 leading-relaxed whitespace-pre-wrap">
                        {{ catalog.transfer_notes }}
                    </p>
                </div>

                <!-- Action buttons -->
                <div v-if="latest_catalog && !latest_catalog.is_approved" class="mt-3 flex items-center gap-2">
                    <el-button v-if="canApprove" type="success" icon="Check" @click="approveCatalog">
                        Aprobar catalogo
                    </el-button>
                </div>
                <div v-if="latest_catalog?.is_approved" class="mt-3">
                    <el-tag type="success" size="large" effect="dark">Catalogo aprobado</el-tag>
                </div>
            </div>

            <!-- Important note from ticket -->
            <div v-if="ticket.important_note" class="bg-amber-50 dark:bg-amber-900/30 p-4 rounded-lg shadow-sm border border-amber-300 dark:border-amber-700">
                <p class="text-xs font-bold text-amber-700 dark:text-amber-300 uppercase tracking-wide mb-1 flex items-center gap-1">
                    <el-icon :size="14"><ChatDotSquare /></el-icon>
                    Notas importantes del ticket
                </p>
                <p class="text-sm text-amber-800 dark:text-amber-200 leading-relaxed">
                    {{ ticket.important_note }}
                </p>
            </div>

            <!-- Info & Team -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <h3 class="text-md font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <el-icon><OfficeBuilding /></el-icon> Cliente y sucursal
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Cliente:</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ ticket.customer.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">RFC:</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ ticket.customer.rfc }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Sucursal:</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ ticket.branch.branch_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Ciudad:</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ ticket.branch.city }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Region:</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ ticket.branch.region }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Contacto:</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ ticket.contact.name }}</span>
                        </div>
                        <div v-if="ticket.contact.phone" class="flex justify-between">
                            <span class="text-gray-500">Telefono:</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ ticket.contact.phone }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="ticket.technicians?.length" class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <h3 class="text-md font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <el-icon><UserFilled /></el-icon> Tecnicos asignados
                        <el-tag size="small" type="primary" effect="plain">{{ ticket.technicians.length }}</el-tag>
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="tech in ticket.technicians" :key="tech.id"
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

            <!-- Concepts -->
            <div v-if="concepts.length" class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-4">
                <h3 class="text-md font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                    <el-icon><Money /></el-icon> Conceptos base (Presupuesto)
                </h3>
                <el-table :data="concepts" size="small" border class="w-full">
                    <el-table-column prop="concept" label="Concepto comercial" min-width="200" />
                    <el-table-column label="Monto referencial" width="140" align="right">
                        <template #default="scope">{{ formatCurrency(scope.row.amount) }}</template>
                    </el-table-column>
                </el-table>
                <div class="mt-3 text-right text-sm text-gray-600 dark:text-gray-400">
                    Total cotizado: <span class="font-bold">{{ formatCurrency(conceptsTotal) }}</span>
                </div>
            </div>

            <!-- Catalog Editor -->
            <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <div class="p-4 border-b border-gray-100 dark:border-[#2b2b2e] flex justify-between items-center">
                    <h3 class="text-md font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <el-icon><List /></el-icon> Catalogo de costos especiales
                    </h3>
                    <div class="flex items-center gap-2">
                        <el-button v-if="canCreateCatalog && !latest_catalog?.is_approved" type="primary" plain size="small" icon="Plus" @click="addItemRow('material')">Agregar fila</el-button>
                        <el-button v-if="canCreateCatalog && !latest_catalog?.is_approved" type="primary" color="#f26c17" size="small" icon="Check" @click="submitCatalog" :loading="form.processing">
                            Guardar nueva version
                        </el-button>
                    </div>
                </div>

                <div v-if="!canCreateCatalog && !latest_catalog?.is_approved" class="px-4 py-2 bg-amber-50 dark:bg-amber-900/20 border-b border-amber-100 dark:border-amber-800">
                    <p class="text-xs text-amber-700 dark:text-amber-300 flex items-center gap-1">
                        <el-icon><InfoFilled /></el-icon>
                        No tienes permiso para crear nuevas versiones. El catalogo se muestra en modo lectura.
                    </p>
                </div>

                <div class="p-4 overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-[#252529]">
                            <tr>
                                <th class="px-4 py-3 min-w-[300px]">Descripcion / Concepto</th>
                                <th class="px-4 py-3 w-32">Unidad</th>
                                <th class="px-4 py-3 w-32 text-right">Cantidad</th>
                                <th class="px-4 py-3 w-40 text-right">Precio Unitario</th>
                                <th class="px-4 py-3 w-40 text-right">Total</th>
                                <th v-if="canCreateCatalog && !latest_catalog?.is_approved" class="px-4 py-3 w-16 text-center"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in form.items" :key="index" class="border-b dark:border-[#2b2b2e]">
                                <td class="px-2 py-2">
                                    <el-input v-if="canCreateCatalog && !latest_catalog?.is_approved" v-model="item.description" type="textarea" :rows="2" placeholder="Descripcion detallada" />
                                    <span v-else class="text-gray-700 dark:text-gray-300">{{ item.description }}</span>
                                </td>
                                <td class="px-2 py-2">
                                    <el-input v-if="canCreateCatalog && !latest_catalog?.is_approved" v-model="item.unit" placeholder="Ej. PZA, ML" />
                                    <span v-else class="text-gray-600 dark:text-gray-400">{{ item.unit }}</span>
                                </td>
                                <td class="px-2 py-2">
                                    <el-input-number v-if="canCreateCatalog && !latest_catalog?.is_approved" v-model="item.quantity" :min="0.01" :step="1" :controls="false" class="!w-full text-right" @change="calculateRowTotal(item)" />
                                    <span v-else class="text-right block text-gray-600 dark:text-gray-400">{{ item.quantity }}</span>
                                </td>
                                <td class="px-2 py-2">
                                    <el-input-number v-if="canCreateCatalog && !latest_catalog?.is_approved" v-model="item.unit_price" :min="0" :step="0.01" :controls="false" class="!w-full text-right" @change="calculateRowTotal(item)" />
                                    <span v-else class="text-right block text-gray-600 dark:text-gray-400">{{ formatCurrency(item.unit_price) }}</span>
                                </td>
                                <td class="px-4 py-2 text-right font-mono font-bold text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-[#252529]">
                                    {{ formatCurrency(item.total) }}
                                </td>
                                <td v-if="canCreateCatalog && !latest_catalog?.is_approved" class="px-2 py-2 text-center">
                                    <el-button type="danger" plain circle icon="Delete" size="small" @click="removeItemRow(item)" />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td :colspan="canCreateCatalog && !latest_catalog?.is_approved ? 3 : 3"></td>
                                <td class="px-4 py-3 text-right font-bold text-gray-600 dark:text-gray-400">Subtotal:</td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-[#252529]">{{ formatCurrency(form.subtotal) }}</td>
                                <td v-if="canCreateCatalog && !latest_catalog?.is_approved"></td>
                            </tr>
                            <tr>
                                <td :colspan="canCreateCatalog && !latest_catalog?.is_approved ? 3 : 3"></td>
                                <td class="px-4 py-3 text-right font-bold text-gray-600 dark:text-gray-400">
                                    <el-checkbox v-model="form.include_iva" @change="calculateTotals" label="IVA (16%):" class="!mr-0" :disabled="!canCreateCatalog || latest_catalog?.is_approved" />
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-[#252529]">{{ formatCurrency(form.iva) }}</td>
                                <td v-if="canCreateCatalog && !latest_catalog?.is_approved"></td>
                            </tr>
                            <tr>
                                <td :colspan="canCreateCatalog && !latest_catalog?.is_approved ? 3 : 3"></td>
                                <td class="px-4 py-3 text-right font-bold text-gray-800 dark:text-white">TOTAL:</td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-[#f26c17] bg-gray-50 dark:bg-[#252529] text-lg">{{ formatCurrency(form.total) }}</td>
                                <td v-if="canCreateCatalog && !latest_catalog?.is_approved"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Ticket files -->
            <div v-if="ticket_media?.length" class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-4">
                <h3 class="text-md font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                    <el-icon><FolderOpened /></el-icon> Archivos del ticket
                    <el-tag size="small" type="info" effect="plain">{{ ticket_media.length }} archivos</el-tag>
                </h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <div v-for="file in ticket_media" :key="file.id"
                        class="relative group border border-gray-200 dark:border-[#3f3f46] rounded-lg overflow-hidden bg-white dark:bg-[#252529]">
                        <el-image v-if="file.mime_type?.startsWith('image/')" :src="file.url" fit="cover" class="w-full h-24 cursor-pointer"
                            :preview-src-list="ticket_media.filter(f => f.mime_type?.startsWith('image/')).map(f => f.url)"
                            :initial-index="ticket_media.filter(f => f.mime_type?.startsWith('image/')).findIndex(f => f.id === file.id)" hide-on-click-modal />
                        <div v-else class="w-full h-24 bg-gray-100 dark:bg-[#18181b] flex items-center justify-center">
                            <el-icon :size="32" class="text-gray-400"><Document /></el-icon>
                        </div>
                        <div class="p-1.5">
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate" :title="file.file_name">{{ file.file_name }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Task evidence -->
            <div v-if="task_evidence?.length" class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-4">
                <h3 class="text-md font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                    <el-icon><Camera /></el-icon> Evidencias de campo (tecnicos)
                    <el-tag size="small" type="success" effect="plain">{{ task_evidence.length }} archivos</el-tag>
                </h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <div v-for="ev in task_evidence" :key="ev.id"
                        class="relative group border border-gray-200 dark:border-[#3f3f46] rounded-lg overflow-hidden bg-white dark:bg-[#252529]">
                        <el-image v-if="ev.mime_type?.startsWith('image/')" :src="ev.url" fit="cover" class="w-full h-24 cursor-pointer"
                            :preview-src-list="task_evidence.filter(e => e.mime_type?.startsWith('image/')).map(e => e.url)"
                            :initial-index="task_evidence.filter(e => e.mime_type?.startsWith('image/')).findIndex(e => e.id === ev.id)" hide-on-click-modal />
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

        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.el-input-number .el-input__inner) { text-align: right; }
</style>