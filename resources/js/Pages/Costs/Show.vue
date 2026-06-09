<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';

const props = defineProps({
    budget: Object,
});

const currentVersion = ref(null);

const conceptsTotal = computed(() => {
    if (!props.budget.concepts || !props.budget.concepts.length) return 0;
    return props.budget.concepts.reduce((sum, c) => sum + Number(c.amount || 0), 0);
});

const formatCurrency = (value, currency = 'MXN') => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: currency,
    }).format(value || 0);
};

const form = useForm({
    items: [],
    subtotal: 0,
    iva: 0,
    total: 0,
    include_iva: true,
});

onMounted(() => {
    if (props.budget.latest_catalog && props.budget.latest_catalog.items) {
        currentVersion.value = props.budget.latest_catalog.version;
        form.items = props.budget.latest_catalog.items.map(item => ({
            description: item.description,
            unit: item.unit,
            quantity: Number(item.quantity),
            unit_price: Number(item.unit_price),
            total: Number(item.total),
        }));
    } else {
        addItemRow();
    }
    calculateTotals();
});

const addItemRow = () => {
    form.items.push({
        description: '',
        unit: 'PZA',
        quantity: 1,
        unit_price: 0,
        total: 0,
    });
};

const removeItemRow = (index) => {
    form.items.splice(index, 1);
    calculateTotals();
};

const calculateRowTotal = (row) => {
    row.total = Number((row.quantity * row.unit_price).toFixed(2));
    calculateTotals();
};

const calculateTotals = () => {
    form.subtotal = form.items.reduce((sum, item) => sum + Number(item.total || 0), 0);
    form.iva = form.include_iva ? Number((form.subtotal * 0.16).toFixed(2)) : 0;
    form.total = Number((form.subtotal + form.iva).toFixed(2));
};

const submitCatalog = () => {
    if (form.items.length === 0) {
        ElMessage.warning('Debes agregar al menos un concepto al catálogo.');
        return;
    }

    const invalidItems = form.items.some(item => !item.description || !item.unit);
    if (invalidItems) {
        ElMessage.warning('Completa la descripción y unidad de todos los conceptos.');
        return;
    }

    const executeSubmit = () => {
        form.post(route('costs.store-catalog', props.budget.id), {
            preserveScroll: true,
            onSuccess: () => {
                // Props already have the updated catalog from the server,
                // so just sync currentVersion to the actual latest version
                currentVersion.value = props.budget.latest_catalog?.version;
                ElMessage.success('Catálogo actualizado correctamente.');
            },
        });
    };

    if (props.budget.latest_catalog) {
        ElMessageBox.confirm(
            'Se creará una nueva versión del catálogo y se mantendrá el historial anterior. ¿Continuar?',
            'Guardar Catálogo',
            { confirmButtonText: 'Sí, guardar', cancelButtonText: 'Cancelar', type: 'info' }
        ).then(executeSubmit).catch(() => { });
    } else {
        executeSubmit();
    }
};

const viewCatalogVersion = (versionId) => {
    const catalog = props.budget.catalogs.find(c => c.id === versionId);
    if (catalog) {
        currentVersion.value = catalog.version;
        form.items = catalog.items.map(item => ({
            description: item.description,
            unit: item.unit,
            quantity: Number(item.quantity),
            unit_price: Number(item.unit_price),
            total: Number(item.total),
        }));
        form.include_iva = Number(catalog.iva) > 0;
        calculateTotals();
        ElMessage.info(`Mostrando la versión ${catalog.version}`);
    }
};

const openPrintView = () => {
    if (!currentVersion.value) return;
    const url = route('costs.print', { budget: props.budget.id, version: currentVersion.value });
    window.open(url, '_blank');
};
</script>

<template>
    <AppLayout :title="`Catálogo de Costos - ${budget.ticket.folio}`">
        <div class="space-y-6">

            <!-- Header -->
            <div
                class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-4">
                    <Link :href="route('costs.index')">
                        <el-button circle icon="Back" />
                    </Link>
                    <div>
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white leading-tight">
                            {{ budget.ticket.name }}
                        </h2>
                        <div class="flex items-center gap-2 text-sm text-gray-500">
                            <span>{{ budget.ticket.folio }}</span>
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                            <span>{{ budget.ticket.customer.name }}</span>
                            <el-dropdown v-if="budget.catalogs.length > 0" trigger="click"
                                @command="viewCatalogVersion">
                                <span
                                    class="cursor-pointer text-sm text-green-600 hover:text-green-700 transition flex items-center gap-1 font-medium bg-green-50 px-2 py-0.5 rounded border border-green-200">
                                    Versión mostrada: {{ currentVersion }} <el-icon><ArrowDown /></el-icon>
                                </span>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item v-for="cat in budget.catalogs" :key="cat.id"
                                            :command="cat.id">
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

            <!-- Context Info: Concepts and Images -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Conceptos del Presupuesto -->
                <div
                    class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <h3 class="text-md font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <el-icon>
                            <Money />
                        </el-icon> Conceptos base (Presupuesto)
                    </h3>
                    <el-table :data="budget.concepts" size="small" border class="w-full">
                        <el-table-column prop="concept" label="Concepto comercial" min-width="200" />
                        <el-table-column label="Monto referencial" width="140" align="right">
                            <template #default="scope">
                                {{ formatCurrency(scope.row.amount, budget.currency) }}
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="mt-3 text-right text-sm text-gray-600 dark:text-gray-400">
                        Total cotizado: <span class="font-bold">{{ formatCurrency(conceptsTotal, budget.currency)
                        }}</span>
                    </div>
                </div>

                <!-- Imágenes de Levantamiento -->
                <div
                    class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <h3 class="text-md font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <el-icon>
                            <Picture />
                        </el-icon> Imágenes de levantamiento
                    </h3>

                    <div v-if="budget.survey_images.length > 0" class="flex flex-wrap gap-2">
                        <el-image v-for="img in budget.survey_images" :key="img.id" :src="img.url"
                            :preview-src-list="budget.survey_images.map(i => i.url)"
                            :initial-index="budget.survey_images.findIndex(i => i.id === img.id)" fit="cover"
                            class="w-24 h-24 rounded-md border border-gray-200 dark:border-gray-700 cursor-pointer" />
                    </div>
                    <div v-else
                        class="text-center p-6 text-gray-400 bg-gray-50 dark:bg-[#252529] rounded-md border border-dashed border-gray-300 dark:border-gray-700">
                        No hay imágenes registradas.
                    </div>
                </div>
            </div>

            <!-- Builder del Catálogo -->
            <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <div class="p-4 border-b border-gray-100 dark:border-[#2b2b2e] flex justify-between items-center">
                    <h3 class="text-md font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <el-icon>
                            <List />
                        </el-icon> Desglose de Catálogo (Costos)
                    </h3>
                    <div class="flex items-center gap-2">
                        <el-button type="primary" plain size="small" icon="Plus" @click="addItemRow">
                            Agregar fila
                        </el-button>
                        <!-- <el-button type="info" plain size="small" icon="Printer" @click="openPrintView" :disabled="!currentVersion"> -->
                        <el-button type="info" plain size="small" icon="Printer" @click="$inertia.visit(route('costs.print', $props.budget.id))" :disabled="!currentVersion">
                            Imprimir catálogo
                        </el-button>
                        <el-button type="primary" color="#f26c17" size="small" icon="Check" @click="submitCatalog"
                            :loading="form.processing">
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
                                <td class="px-2 py-2">
                                    <el-input v-model="item.description" type="textarea" :rows="2"
                                        placeholder="Descripción detallada" />
                                </td>
                                <td class="px-2 py-2">
                                    <el-input v-model="item.unit" placeholder="Ej. PZA, ML, M2" />
                                </td>
                                <td class="px-2 py-2">
                                    <el-input-number v-model="item.quantity" :min="0.01" :step="1" :controls="false"
                                        class="!w-full text-right" @change="calculateRowTotal(item)" />
                                </td>
                                <td class="px-2 py-2">
                                    <el-input-number v-model="item.unit_price" :min="0" :step="0.01" :controls="false"
                                        class="!w-full text-right" @change="calculateRowTotal(item)" />
                                </td>
                                <td
                                    class="px-4 py-2 text-right font-mono font-bold text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-[#252529]">
                                    {{ formatCurrency(item.total, budget.currency) }}
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <el-button type="danger" plain circle icon="Delete" size="small"
                                        @click="removeItemRow(index)" />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3"></td>
                                <td class="px-4 py-3 text-right font-bold text-gray-600 dark:text-gray-400">Subtotal:
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-mono font-bold text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-[#252529]">
                                    {{ formatCurrency(form.subtotal, budget.currency) }}
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td class="px-4 py-3 text-right font-bold text-gray-600 dark:text-gray-400">
                                    <el-checkbox v-model="form.include_iva" @change="calculateTotals" label="IVA (16%):"
                                        class="!mr-0" />
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-mono font-bold text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-[#252529]">
                                    {{ formatCurrency(form.iva, budget.currency) }}
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td class="px-4 py-3 text-right font-bold text-gray-800 dark:text-white">TOTAL:</td>
                                <td
                                    class="px-4 py-3 text-right font-mono font-bold text-[#f26c17] bg-gray-50 dark:bg-[#252529] text-lg">
                                    {{ formatCurrency(form.total, budget.currency) }}
                                </td>
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
:deep(.el-input-number .el-input__inner) {
    text-align: right;
}
</style>