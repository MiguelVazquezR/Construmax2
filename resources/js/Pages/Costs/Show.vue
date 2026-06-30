<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useCostsHelpers } from '@/Composables/useCostsHelpers';
import MaterialsTable from '@/Components/Costs/MaterialsTable.vue';
import LaborTable from '@/Components/Costs/LaborTable.vue';
import EmpenoFacilTotals from '@/Components/Costs/EmpenoFacilTotals.vue';

const props = defineProps({ budget: Object });
const { formatCurrency, copyToClipboard } = useCostsHelpers();

const currentVersion = ref(null);

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
        addItemRow('material');
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
    });

    if (props.budget.latest_catalog) {
        ElMessageBox.confirm(
            'Se creará una nueva versión del catálogo y se mantendrá el historial anterior. ¿Continuar?',
            'Guardar Catálogo',
            { confirmButtonText: 'Sí, guardar', cancelButtonText: 'Cancelar', type: 'info' }
        ).then(doSubmit).catch(() => {});
    } else {
        doSubmit();
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
    userEditedNonInstallationLabor = true;
    userEditedLaborUtility = true;
    calculateTotals();
    ElMessage.info(`Mostrando la versión ${cat.version}`);
}

function openUrl(url) { window.open(url, '_blank'); }
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
                        <el-button type="primary" plain size="small" icon="Plus" @click="addItemRow('material')">Agregar fila</el-button>
                        <el-button type="info" plain size="small" icon="Printer"
                            @click="$inertia.visit(route('costs.print', $props.budget.id))" :disabled="!currentVersion">
                            Imprimir catálogo
                        </el-button>
                        <el-button type="primary" color="#f26c17" size="small" icon="Check" @click="submitCatalog" :loading="form.processing">
                            Guardar versión
                        </el-button>
                    </div>
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
                                <td class="px-2 py-2"><el-input v-model="item.description" type="textarea" :rows="2" placeholder="Descripción detallada" /></td>
                                <td class="px-2 py-2"><el-input v-model="item.unit" placeholder="Ej. PZA, ML, M2" /></td>
                                <td class="px-2 py-2"><el-input-number v-model="item.quantity" :min="0.01" :step="1" :controls="false" class="!w-full text-right" @change="calculateRowTotal(item)" /></td>
                                <td class="px-2 py-2"><el-input-number v-model="item.unit_price" :min="0" :step="0.01" :controls="false" class="!w-full text-right" @change="calculateRowTotal(item)" /></td>
                                <td class="px-4 py-2 text-right font-mono font-bold text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-[#252529]">
                                    {{ formatCurrency(item.total, budget.currency) }}
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <el-button type="danger" plain circle icon="Delete" size="small" @click="removeItemRow(item)" />
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
                                    <el-checkbox v-model="form.include_iva" @change="calculateTotals" label="IVA (16%):" class="!mr-0" />
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
