<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { debounce } from 'lodash';
import { ElMessage } from 'element-plus';
import { CircleCheck } from '@element-plus/icons-vue';
import BudgetConceptPaymentDialog from './BudgetConceptPaymentDialog.vue';
import BulkConceptPaymentDialog from './BulkConceptPaymentDialog.vue';

const props = defineProps({
    modelValue: Boolean,
    categories: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const dialogVisible = ref(props.modelValue);
watch(() => props.modelValue, (value) => { dialogVisible.value = value; });
watch(dialogVisible, (value) => emit('update:modelValue', value));

const statusOptions = [
    'Borrador',
    'Por programar',
    'Programado',
    'Levantamiento',
    'Catálogo',
    'Pendiente de aprobación',
    'Proceso de ejecución',
    'Ejecutado',
    'Finalizado',
    'Facturado',
    'Pagado',
    'Cancelado',
];

const term = ref('');
const statusFilter = ref(['Facturado']);
const dateRange = ref([]);

const budgets = ref([]);
const loading = ref(false);
const selectedConceptIds = ref([]);

const fetchBudgets = async () => {
    loading.value = true;

    try {
        const { data } = await axios.get(route('expenses.budgets.search'), {
            params: {
                q: term.value || undefined,
                statuses: statusFilter.value?.length ? statusFilter.value : undefined,
                from: dateRange.value?.[0] || undefined,
                to: dateRange.value?.[1] || undefined,
                // Only budgets with pending payments are listed.
                only_pending: 1,
            },
        });

        budgets.value = data;

        const available = new Set(
            data.flatMap((budget) => budget.concepts.filter((concept) => !concept.is_paid).map((concept) => concept.id))
        );

        selectedConceptIds.value = selectedConceptIds.value.filter((id) => available.has(id));
    } catch {
        budgets.value = [];
        selectedConceptIds.value = [];
    } finally {
        loading.value = false;
    }
};

const debouncedFetch = debounce(fetchBudgets, 300);

watch([term, statusFilter, dateRange], debouncedFetch, { deep: true });

const onOpened = () => {
    fetchBudgets();
};

// --- Selection of pending expenses ---
const pendingConcepts = computed(() => budgets.value.flatMap((budget) => budget.concepts
    .filter((concept) => !concept.is_paid)
    .map((concept) => ({
        ...concept,
        budget_id: budget.id,
        budget_folio: budget.folio,
        budget_name: budget.name,
        currency: budget.currency,
    }))));

const selectedConcepts = computed(() => pendingConcepts.value.filter(
    (concept) => selectedConceptIds.value.includes(concept.id)
));

const selectedTotal = computed(() => selectedConcepts.value.reduce(
    (sum, concept) => sum + Number(concept.amount || 0), 0
));

const allPendingSelected = computed(() => pendingConcepts.value.length > 0
    && pendingConcepts.value.every((concept) => selectedConceptIds.value.includes(concept.id)));

const toggleSelection = () => {
    selectedConceptIds.value = allPendingSelected.value
        ? []
        : pendingConcepts.value.map((concept) => concept.id);
};

const toggleConcept = (concept) => {
    const index = selectedConceptIds.value.indexOf(concept.id);

    if (index >= 0) {
        selectedConceptIds.value.splice(index, 1);
    } else {
        selectedConceptIds.value.push(concept.id);
    }
};

// --- Payment dialogs ---
const showBulkDialog = ref(false);

const openBulkDialog = () => {
    if (!selectedConceptIds.value.length) {
        ElMessage.warning('Selecciona al menos un gasto pendiente.');

        return;
    }

    showBulkDialog.value = true;
};

const payBudgetPending = (budget) => {
    selectedConceptIds.value = budget.concepts
        .filter((concept) => !concept.is_paid)
        .map((concept) => concept.id);

    showBulkDialog.value = true;
};

const showConceptDialog = ref(false);
const paymentBudget = ref(null);
const paymentConcept = ref(null);

const openConceptDialog = (budget, concept) => {
    paymentBudget.value = budget;
    paymentConcept.value = concept;
    showConceptDialog.value = true;
};

const onPaymentsSaved = () => {
    fetchBudgets();
};

const openPanel = (budget) => {
    router.visit(route('expenses.budgets.show', budget.id));
};

// --- Presentation helpers ---
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(value || 0);
};

const formatDate = (value) => {
    if (!value) return '—';

    const [year, month, day] = value.split('-');

    return new Date(year, month - 1, day).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const statusTagType = (status) => {
    const map = {
        'Borrador': 'info',
        'Por programar': 'info',
        'Programado': 'warning',
        'Levantamiento': 'warning',
        'Catálogo': 'primary',
        'Pendiente de aprobación': 'warning',
        'Proceso de ejecución': 'primary',
        'Ejecutado': 'success',
        'Finalizado': 'success',
        'Facturado': 'success',
        'Pagado': 'success',
        'Cancelado': 'danger',
    };

    return map[status] || 'info';
};
</script>

<template>
    <el-dialog
        v-model="dialogVisible"
        title="Gastos de presupuesto"
        width="min(1150px, 95vw)"
        top="4vh"
        destroy-on-close
        @opened="onOpened"
    >
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Solo se muestran los presupuestos con pagos pendientes. Filtra por estatus y por la fecha en que cambiaron a ese estatus, expande cada presupuesto para ver sus gastos y registra el pago de uno, de los pendientes de un presupuesto o de todos los seleccionados.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-4">
            <el-input v-model="term" placeholder="Buscar folio, proyecto o cliente" clearable prefix-icon="Search" />

            <el-select
                v-model="statusFilter"
                multiple
                collapse-tags
                collapse-tags-tooltip
                clearable
                placeholder="Estatus"
                class="w-full"
            >
                <el-option v-for="status in statusOptions" :key="status" :label="status" :value="status" />
            </el-select>

            <el-date-picker
                v-model="dateRange"
                type="daterange"
                value-format="YYYY-MM-DD"
                range-separator="a"
                start-placeholder="Desde"
                end-placeholder="Hasta"
                class="w-full"
            />
        </div>

        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2 mt-4 mb-2">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <span class="font-semibold">{{ budgets.length }}</span> presupuestos ·
                <span class="font-semibold">{{ pendingConcepts.length }}</span> gastos pendientes
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <el-button size="small" :disabled="!pendingConcepts.length" @click="toggleSelection">
                    {{ allPendingSelected ? 'Quitar selección' : 'Seleccionar todos los pendientes' }}
                </el-button>

                <span class="text-sm text-gray-600 dark:text-gray-300">
                    {{ selectedConceptIds.length }} seleccionados · {{ formatCurrency(selectedTotal) }}
                </span>

                <el-button type="primary" size="small" :disabled="!selectedConceptIds.length" @click="openBulkDialog">
                    Registrar pago de seleccionados
                </el-button>
            </div>
        </div>

        <el-table v-loading="loading" :data="budgets" stripe row-key="id" max-height="55vh">
            <el-table-column type="expand">
                <template #default="{ row }">
                    <div class="px-3 py-2">
                        <el-table :data="row.concepts" size="small" stripe>
                            <el-table-column width="50" align="center">
                                <template #default="{ row: concept }">
                                    <el-checkbox
                                        v-if="!concept.is_paid"
                                        :model-value="selectedConceptIds.includes(concept.id)"
                                        @change="toggleConcept(concept)"
                                    />
                                    <el-icon v-else class="text-green-600"><CircleCheck /></el-icon>
                                </template>
                            </el-table-column>

                            <el-table-column label="Gasto" prop="concept" min-width="200" show-overflow-tooltip />

                            <el-table-column label="Monto" width="140" align="right">
                                <template #default="{ row: concept }">
                                    <div class="flex flex-col items-end">
                                        <span class="font-mono text-sm">{{ formatCurrency(concept.amount) }}</span>
                                        <span v-if="concept.expense?.commission_amount > 0" class="text-xs text-amber-600">
                                            + {{ formatCurrency(concept.expense.commission_amount) }} comisión
                                        </span>
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column label="Estatus" width="150" align="center">
                                <template #default="{ row: concept }">
                                    <el-tag :type="concept.is_paid ? 'success' : 'warning'" size="small" effect="light">
                                        {{ concept.is_paid ? 'Pagado' : 'Pendiente de pago' }}
                                    </el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column label="Fecha de pago" width="130" align="center">
                                <template #default="{ row: concept }">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ formatDate(concept.expense?.expense_date || concept.payment_date) }}
                                    </span>
                                </template>
                            </el-table-column>

                            <el-table-column label="Referencia" width="140" show-overflow-tooltip>
                                <template #default="{ row: concept }">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ concept.expense?.reference || '—' }}</span>
                                </template>
                            </el-table-column>

                            <el-table-column label="Acciones" width="150" align="center">
                                <template #default="{ row: concept }">
                                    <el-button
                                        size="small"
                                        :type="concept.is_paid ? 'default' : 'primary'"
                                        plain
                                        @click="openConceptDialog(row, concept)"
                                    >
                                        {{ concept.is_paid ? 'Editar pago' : 'Registrar pago' }}
                                    </el-button>
                                </template>
                            </el-table-column>

                            <template #empty>
                                <span class="text-xs text-gray-400">Este presupuesto no tiene conceptos en el desglose.</span>
                            </template>
                        </el-table>
                    </div>
                </template>
            </el-table-column>

            <el-table-column label="Folio" width="90">
                <template #default="{ row }">
                    <span class="font-mono text-xs text-gray-600 dark:text-gray-400">{{ row.folio }}</span>
                </template>
            </el-table-column>

            <el-table-column label="Proyecto" min-width="180" show-overflow-tooltip>
                <template #default="{ row }">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ row.name }}</span>
                </template>
            </el-table-column>

            <el-table-column label="Cliente" min-width="150" show-overflow-tooltip>
                <template #default="{ row }">
                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ row.customer_name || '—' }}</span>
                </template>
            </el-table-column>

            <el-table-column label="Cambio de estatus" width="150" align="center">
                <template #default="{ row }">
                    <el-tooltip content="Fecha en que el ticket pasó al estatus actual" placement="top">
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ formatDate(row.status_date) }}</span>
                    </el-tooltip>
                </template>
            </el-table-column>

            <el-table-column label="Estatus" width="140" align="center">
                <template #default="{ row }">
                    <el-tag :type="statusTagType(row.status)" size="small" effect="light">{{ row.status }}</el-tag>
                </template>
            </el-table-column>

            <el-table-column label="Pendientes" width="160" align="center">
                <template #default="{ row }">
                    <el-tooltip v-if="row.has_pending" :content="formatCurrency(row.pending_total)" placement="top">
                        <el-tag type="warning" size="small" effect="light">
                            {{ row.pending_count }} gastos
                        </el-tag>
                    </el-tooltip>
                    <el-tag v-else type="success" size="small" effect="light">Sin pendientes</el-tag>
                </template>
            </el-table-column>

            <el-table-column label="Acciones" width="200" align="center" fixed="right">
                <template #default="{ row }">
                    <div class="flex flex-col items-center gap-1">
                        <el-button
                            v-if="row.has_pending"
                            size="small"
                            type="primary"
                            plain
                            class="!m-0"
                            @click="payBudgetPending(row)"
                        >
                            Pagar pendientes
                        </el-button>
                        <el-button size="small" link class="!m-0" @click="openPanel(row)">
                            Ver panel
                        </el-button>
                    </div>
                </template>
            </el-table-column>

            <template #empty>
                <el-empty :image-size="80" description="No hay presupuestos con los filtros seleccionados." />
            </template>
        </el-table>

        <template #footer>
            <el-button @click="dialogVisible = false">Cerrar</el-button>
        </template>
    </el-dialog>

    <BulkConceptPaymentDialog
        v-if="showBulkDialog"
        v-model="showBulkDialog"
        :concepts="selectedConcepts"
        @saved="onPaymentsSaved"
    />

    <BudgetConceptPaymentDialog
        v-if="showConceptDialog && paymentConcept"
        v-model="showConceptDialog"
        :budget="paymentBudget"
        :concept="paymentConcept"
        :categories="categories"
        @saved="onPaymentsSaved"
    />
</template>
