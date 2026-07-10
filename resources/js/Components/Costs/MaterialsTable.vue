<script setup>
import { useCostsHelpers } from '@/Composables/useCostsHelpers';

const props = defineProps({
    items: { type: Array, required: true },
    currency: { type: String, default: 'MXN' },
    canEdit: { type: Boolean, default: true },
});

const emit = defineEmits(['update:item', 'remove', 'calculate']);

const { formatCurrency, copyToClipboard } = useCostsHelpers();

function onRowTotal(item) {
    emit('calculate', item);
}

function onRemove(item) {
    emit('remove', item);
}
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
        <div class="p-4 border-b border-gray-100 dark:border-[#2b2b2e] flex justify-between items-center">
            <h3 class="text-md font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <el-icon><Box /></el-icon> Materiales
            </h3>
            <el-button v-if="canEdit" type="primary" plain size="small" icon="Plus" @click="$emit('add')">
                Agregar material
            </el-button>
        </div>
        <div class="p-4 overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-[#252529]">
                    <tr>
                        <th class="px-4 py-3 w-16 text-center">Desgloce</th>
                        <th class="px-4 py-3 min-w-[250px]">Descripción</th>
                        <th class="px-4 py-3 w-28">Unidad</th>
                        <th class="px-4 py-3 w-28 text-right">Qty</th>
                        <th class="px-4 py-3 w-36 text-right">Precio</th>
                        <th class="px-4 py-3 w-36 text-right">Total</th>
                        <th class="px-4 py-3 w-16 text-center"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="'m'+index" class="border-b dark:border-[#2b2b2e]">
                        <td class="px-4 py-2 text-center text-gray-500">{{ index + 1 }}</td>
                        <td class="px-2 py-2">
                            <el-input v-model="item.description" type="textarea" :rows="2"
                                placeholder="Descripción detallada" :disabled="!canEdit" />
                        </td>
                        <td class="px-2 py-2">
                            <el-input v-model="item.unit" placeholder="Ej. PZA, ML, M2" :disabled="!canEdit" />
                        </td>
                        <td class="px-2 py-2">
                            <el-input-number v-model="item.quantity" :min="0.01" :step="1" :controls="false"
                                class="!w-full text-right" @change="onRowTotal(item)" :disabled="!canEdit" />
                        </td>
                        <td class="px-2 py-2">
                            <el-input-number v-model="item.unit_price" :min="0" :step="0.01" :controls="false"
                                class="!w-full text-right" @change="onRowTotal(item)" :disabled="!canEdit" />
                        </td>
                        <td class="px-2 py-2 text-right font-mono font-bold text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-[#252529]">
                            <el-button text size="small" class="!font-mono !font-bold !text-gray-700 dark:!text-gray-300"
                                @click="copyToClipboard(item.total)">
                                {{ formatCurrency(item.total, currency) }}
                            </el-button>
                        </td>
                        <td class="px-2 py-2 text-center">
                            <el-button v-if="canEdit" type="danger" plain circle icon="Delete" size="small"
                                @click="onRemove(item)" />
                        </td>
                    </tr>
                    <tr v-if="items.length === 0">
                        <td colspan="7" class="px-4 py-6 text-center text-gray-400">
                            {{ canEdit ? 'No hay materiales. Haz clic en "Agregar material".' : 'Sin materiales registrados.' }}
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="items.length > 0">
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-right font-bold text-gray-600 dark:text-gray-400">
                            Materiales subtotal:
                        </td>
                        <td class="px-2 py-3 text-right font-mono font-bold text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-[#252529]">
                            <slot name="subtotal" />
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
