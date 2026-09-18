<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { debounce } from 'lodash';

const props = defineProps({
    modelValue: Boolean,
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

const statusFilter = ref('');
const budgetId = ref(null);
const options = ref([]);
const loading = ref(false);

const search = debounce(async (query) => {
    loading.value = true;

    try {
        const { data } = await axios.get(route('expenses.budgets.search'), {
            params: {
                q: query || undefined,
                status: statusFilter.value || undefined,
            },
        });

        options.value = data;
    } catch {
        options.value = [];
    } finally {
        loading.value = false;
    }
}, 300);

watch(statusFilter, () => {
    budgetId.value = null;
    search('');
    options.value = [];
});

const onOpened = () => {
    search('');
};

const openPanel = () => {
    if (!budgetId.value) return;

    router.visit(route('expenses.budgets.show', budgetId.value));
};

const budgetLabel = (budget) => {
    const label = [budget.folio, budget.name].filter(Boolean).join(' — ');

    return budget.customer_name ? `${label} (${budget.customer_name})` : label;
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
    <el-dialog v-model="dialogVisible" title="Seleccionar presupuesto" width="620px" top="8vh" destroy-on-close @opened="onOpened">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Elige el presupuesto al que registrarás el gasto. Puedes filtrar por estatus.
        </p>

        <div class="flex flex-col sm:flex-row gap-3 mt-4">
            <el-select v-model="statusFilter" placeholder="Estatus del presupuesto" clearable class="w-full sm:!w-56">
                <el-option v-for="status in statusOptions" :key="status" :label="status" :value="status" />
            </el-select>

            <el-select
                v-model="budgetId"
                placeholder="Busca por folio, proyecto o cliente"
                remote
                reserve-keyword
                filterable
                clearable
                :remote-method="search"
                :loading="loading"
                class="w-full"
            >
                <el-option
                    v-for="budget in options"
                    :key="budget.id"
                    :label="budgetLabel(budget)"
                    :value="budget.id"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="truncate">{{ budgetLabel(budget) }}</span>
                        <el-tag :type="statusTagType(budget.status)" size="small" effect="light">
                            {{ budget.status }}
                        </el-tag>
                    </div>
                </el-option>
            </el-select>
        </div>

        <template #footer>
            <el-button @click="dialogVisible = false">Cancelar</el-button>
            <el-button type="primary" :disabled="!budgetId" @click="openPanel">
                Gestionar gastos
            </el-button>
        </template>
    </el-dialog>
</template>
