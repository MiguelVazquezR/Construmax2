<script setup>
import { ref, watch, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const visible = ref(props.modelValue);
watch(() => props.modelValue, (v) => { visible.value = v; });
watch(visible, (v) => emit('update:modelValue', v));

const loading = ref(false);
const technicians = ref([]);

const fetchData = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(route('tickets.pending-tech-payments'));
        technicians.value = data;
    } catch {
        technicians.value = [];
    } finally {
        loading.value = false;
    }
};

watch(visible, (isOpen) => {
    if (isOpen) fetchData();
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(value || 0);
};

const getTechLabel = (tech) => {
    let label = tech.name;
    if (tech.is_internal !== null && tech.is_internal !== undefined) {
        label += tech.is_internal ? ' (Interno)' : ' (Externo)';
    }
    if (tech.state) {
        label += ` — ${tech.state}`;
    }
    return label;
};

// Group by user_id
const groupedTechnicians = computed(() => {
    const map = new Map();
    for (const tech of technicians.value) {
        if (!map.has(tech.user_id)) {
            map.set(tech.user_id, {
                user_id: tech.user_id,
                name: tech.name,
                state: tech.state,
                is_internal: tech.is_internal,
                tickets: [],
                total_pending: 0,
            });
        }
        const entry = map.get(tech.user_id);
        entry.tickets.push(tech);
        entry.total_pending += tech.pending_amount;
    }
    return Array.from(map.values());
});
</script>

<template>
    <el-dialog v-model="visible" title="Técnicos con pagos pendientes" width="700px" destroy-on-close>
        <div v-loading="loading" class="modal-body">
            <el-alert
                v-if="!loading && groupedTechnicians.length === 0"
                title="Sin pagos pendientes"
                type="success"
                description="No hay técnicos con conceptos pagables pendientes en este momento."
                show-icon
                :closable="false"
            />

            <div v-else class="space-y-4">
                <el-alert
                    type="info"
                    :closable="false"
                    show-icon
                    class="mb-4"
                >
                    <template #title>
                        Técnicos asignados a tickets que tienen conceptos registrados como pagables y aún no liquidados.
                    </template>
                    <template #default>
                        <small>NOTA: Tramita los depósitos en el módulo de Depósitos para poder registrar los pagos al técnico.</small>
                    </template>
                </el-alert>

                <div
                    v-for="tech in groupedTechnicians"
                    :key="tech.user_id"
                    class="border border-gray-200 dark:border-[#3f3f46] rounded-lg p-4 bg-white dark:bg-[#252529]"
                >
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <p class="font-bold text-gray-800 dark:text-gray-200">{{ getTechLabel(tech) }}</p>
                            <p class="text-xs text-gray-500">
                                {{ tech.tickets.length }} ticket{{ tech.tickets.length > 1 ? 's' : '' }} —
                                Total pendiente: <span class="font-bold text-orange-600">{{ formatCurrency(tech.total_pending) }}</span>
                            </p>
                        </div>
                    </div>

                    <el-table :data="tech.tickets" size="small" stripe class="w-full">
                        <el-table-column label="Folio" width="100">
                            <template #default="scope">
                                <Link
                                    :href="route('tickets.show', scope.row.ticket_id)"
                                    class="font-mono text-sm text-blue-600 hover:text-blue-800 hover:underline"
                                >
                                    {{ scope.row.ticket_folio }}
                                </Link>
                            </template>
                        </el-table-column>
                        <el-table-column label="Ticket" min-width="180">
                            <template #default="scope">
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ scope.row.ticket_name }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="Pendiente" width="130" align="right">
                            <template #default="scope">
                                <span class="font-mono text-sm font-semibold text-orange-600">
                                    {{ formatCurrency(scope.row.pending_amount) }}
                                </span>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </div>
        </div>
    </el-dialog>
</template>

<style scoped>
.modal-body {
    max-height: 60vh;
    overflow-y: auto;
}
</style>
