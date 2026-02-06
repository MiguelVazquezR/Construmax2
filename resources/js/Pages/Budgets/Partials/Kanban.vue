<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

const props = defineProps({
    budgets: Object,
});

// Estado local para manejo optimista del DnD
const localBudgets = ref([]);

// Sincronizar props con estado local al cargar o cambiar página
watch(() => props.budgets.data, (newVal) => {
    localBudgets.value = JSON.parse(JSON.stringify(newVal));
}, { immediate: true });

// Configuración de Columnas con colores fijos (Hex)
const columns = [
    { id: 'Borrador', label: 'Borrador', color: '#9ca3af' }, // Gray
    { id: 'Presupuesto enviado', label: 'Enviado', color: '#60a5fa' }, // Blue
    { id: 'Facturado', label: 'Facturado', color: '#facc15' }, // Yellow
    { id: 'Trabajo en proceso', label: 'En Proceso', color: '#fb923c' }, // Orange
    { id: 'Trabajo terminado', label: 'Terminado', color: '#4ade80' }, // Green
    { id: 'Pagado', label: 'Pagado', color: '#34d399' }, // Emerald
    { id: 'Perdido', label: 'Perdido', color: '#f87171' }, // Red
];

// Agrupar presupuestos reactivamente desde localBudgets
const groupedBudgets = computed(() => {
    const groups = {};
    columns.forEach(col => groups[col.id] = []);
    
    localBudgets.value.forEach(budget => {
        if (groups[budget.status]) {
            groups[budget.status].push(budget);
        } else {
            // Fallback
            if (!groups['Borrador']) groups['Borrador'] = [];
            groups['Borrador'].push(budget);
        }
    });
    return groups;
});

// --- LÓGICA DRAG AND DROP ---
const draggedItem = ref(null);

const onDragStart = (e, budget) => {
    draggedItem.value = budget;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', budget.id);
    // Para ocultar la "sombra" original si se quisiera customizar, pero default está bien
};

const onDragOver = (e) => {
    e.preventDefault(); // Necesario para permitir el drop
    e.dataTransfer.dropEffect = 'move';
};

const onDrop = (e, targetStatus) => {
    e.preventDefault();
    const budgetId = draggedItem.value?.id;
    
    if (budgetId && draggedItem.value.status !== targetStatus) {
        updateStatus(budgetId, targetStatus);
    }
    draggedItem.value = null;
};

const updateStatus = (budgetId, newStatus) => {
    // 1. Actualización Optimista (Frontend)
    const index = localBudgets.value.findIndex(b => b.id === budgetId);
    if (index !== -1) {
        localBudgets.value[index].status = newStatus;
    }

    // 2. Actualización Backend
    router.put(route('budgets.update-status', budgetId), {
        status: newStatus
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            // Revertir si falla (Recargar datos originales)
            router.reload({ only: ['budgets'] });
            ElMessage.error('Error al actualizar el estado.');
        }
    });
};

// --- UTILS ---
const handleCardClick = (id) => {
    router.visit(route('budgets.show', id));
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 0
    }).format(value || 0);
};

const handlePageChange = (val) => {
    router.visit(route('budgets.index', { ...route().params, page: val }), {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="h-full flex flex-col">
        <!-- Tablero con scroll horizontal estilizado -->
        <div class="flex-1 overflow-x-auto pb-4 custom-scrollbar-x">
            <div class="flex gap-4 min-w-[1600px] px-1">
                
                <div 
                    v-for="col in columns" 
                    :key="col.id" 
                    class="flex-1 min-w-[260px] bg-gray-100 dark:bg-[#18181b] rounded-xl p-3 flex flex-col h-[calc(100vh-240px)] border border-transparent transition-colors"
                    @dragover="onDragOver"
                    @drop="onDrop($event, col.id)"
                >
                    <!-- Cabecera -->
                    <div class="flex justify-between items-center mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <span 
                                class="w-3 h-3 rounded-full border-2"
                                :style="{ backgroundColor: col.color, borderColor: col.color }"
                            ></span>
                            <h3 class="font-bold text-gray-700 dark:text-gray-200 text-sm">{{ col.label }}</h3>
                        </div>
                        <span class="bg-white dark:bg-[#27272a] text-xs font-bold px-2 py-0.5 rounded-full text-gray-500 shadow-sm">
                            {{ groupedBudgets[col.id]?.length || 0 }}
                        </span>
                    </div>

                    <!-- Lista de Tarjetas -->
                    <div class="flex-1 overflow-y-auto space-y-3 custom-scrollbar-y pr-1">
                        <div 
                            v-for="budget in groupedBudgets[col.id]" 
                            :key="budget.id"
                            draggable="true"
                            @dragstart="onDragStart($event, budget)"
                            @click="handleCardClick(budget.id)"
                            class="bg-white dark:bg-[#1e1e20] p-3 rounded-lg shadow-sm border-l-4 hover:shadow-md hover:-translate-y-0.5 transition-all cursor-move group relative"
                            :style="{ borderLeftColor: col.color }"
                        >
                            <!-- ID y Prioridad -->
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-[10px] font-mono text-gray-400">#{{ budget.id }}</span>
                                <span 
                                    class="text-[10px] px-1.5 py-0.5 rounded font-medium"
                                    :class="budget.priority === 'Urgente' ? 'bg-red-100 text-red-600' : 'bg-gray-50 text-gray-500 dark:bg-[#27272a]'"
                                >
                                    {{ budget.priority }}
                                </span>
                            </div>
                            
                            <!-- Título -->
                            <h4 class="font-bold text-gray-800 dark:text-gray-200 text-sm leading-tight mb-1 group-hover:text-primary transition-colors">
                                {{ budget.name }}
                            </h4>
                            
                            <!-- Cliente -->
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-1">
                                <el-icon :size="12"><OfficeBuilding /></el-icon>
                                <span class="truncate">{{ budget.customer?.name }}</span>
                            </div>

                            <!-- Footer: Costo y Responsable -->
                            <div class="flex justify-between items-center border-t border-gray-100 dark:border-[#2b2b2e] pt-2 mt-2">
                                <span class="font-bold text-sm text-gray-700 dark:text-gray-300">
                                    {{ formatCurrency(budget.concepts_sum_amount) }}
                                </span>
                                
                                <div class="flex items-center gap-1" :title="`Responsable: ${budget.responsible?.name}`">
                                    <el-avatar :size="20" class="!text-[10px] bg-gray-200 text-gray-600">
                                        {{ budget.responsible?.name?.charAt(0) }}
                                    </el-avatar>
                                </div>
                            </div>
                        </div>

                        <!-- Estado vacío -->
                        <div v-if="!groupedBudgets[col.id]?.length" class="h-full flex flex-col items-center justify-center opacity-40 min-h-[100px] border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-lg">
                            <p class="text-xs text-gray-400">Arrastra aquí</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Paginación -->
        <div class="flex justify-center mt-4 pt-2 border-t border-gray-200 dark:border-gray-800">
             <el-pagination
                v-model:current-page="budgets.current_page"
                :page-size="budgets.per_page"
                :total="budgets.total"
                layout="prev, pager, next"
                background
                small
                @current-change="handlePageChange"
            />
        </div>
    </div>
</template>

<style scoped>
/* Scrollbar Horizontal */
.custom-scrollbar-x::-webkit-scrollbar {
    height: 8px;
}
.custom-scrollbar-x::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar-x::-webkit-scrollbar-thumb {
    background-color: #d1d5db;
    border-radius: 4px;
}
.custom-scrollbar-x::-webkit-scrollbar-thumb:hover {
    background-color: #9ca3af;
}

/* Scrollbar Vertical (Columnas) */
.custom-scrollbar-y::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar-y::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar-y::-webkit-scrollbar-thumb {
    background-color: #e5e7eb;
    border-radius: 4px;
}
:global(.dark) .custom-scrollbar-y::-webkit-scrollbar-thumb {
    background-color: #3f3f46;
}
:global(.dark) .custom-scrollbar-x::-webkit-scrollbar-thumb {
    background-color: #3f3f46;
}
</style>