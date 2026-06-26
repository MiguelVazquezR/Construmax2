<script setup>
import { useCostsHelpers } from '@/Composables/useCostsHelpers';

const props = defineProps({
    items: { type: Array, required: true },
    currency: { type: String, default: 'MXN' },
});

const emit = defineEmits(['add', 'remove', 'calculate']);

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
                <el-icon><User /></el-icon> Mano de obra
            </h3>
            <el-button type="warning" plain size="small" icon="Plus" @click="$emit('add')">
                Agregar mano de obra
            </el-button>
        </div>
        <div class="p-4 overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-[#252529]">
                    <tr>
                        <th class="px-4 py-3 w-16 text-center">MO</th>
                        <th class="px-4 py-3 min-w-[200px]">Descripción</th>
                        <th class="px-4 py-3 w-40">Técnico</th>
                        <th class="px-4 py-3 w-28 text-right">Horas</th>
                        <th class="px-4 py-3 w-32 text-right">Rate</th>
                        <th class="px-4 py-3 w-36 text-right">Total</th>
                        <th class="px-4 py-3 w-16 text-center"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="'l'+index" class="border-b dark:border-[#2b2b2e]">
                        <td class="px-4 py-2 text-center text-gray-500">{{ index + 1 }}</td>
                        <td class="px-2 py-2">
                            <el-input v-model="item.description" type="textarea" :rows="2"
                                placeholder="Descripción de la labor" />
                        </td>
                        <td class="px-2 py-2">
                            <el-input v-model="item.technician" placeholder="Núm. de técnicos" />
                        </td>
                        <td class="px-2 py-2">
                            <el-input-number v-model="item.hours" :min="0" :step="0.5" :controls="false"
                                class="!w-full text-right" @change="onRowTotal(item)" />
                        </td>
                        <td class="px-2 py-2">
                            <el-input-number v-model="item.rate" :min="0" :step="0.01" :controls="false"
                                class="!w-full text-right" @change="onRowTotal(item)" />
                        </td>
                        <td class="px-2 py-2 text-right font-mono font-bold text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-[#252529]">
                            <el-button text size="small" class="!font-mono !font-bold !text-gray-700 dark:!text-gray-300"
                                @click="copyToClipboard(item.total)">
                                {{ formatCurrency(item.total, currency) }}
                            </el-button>
                        </td>
                        <td class="px-2 py-2 text-center">
                            <el-button type="danger" plain circle icon="Delete" size="small"
                                @click="onRemove(item)" />
                        </td>
                    </tr>
                    <tr v-if="items.length === 0">
                        <td colspan="7" class="px-4 py-6 text-center text-gray-400">
                            No hay mano de obra. Haz clic en "Agregar mano de obra".
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="items.length > 0">
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-right font-bold text-gray-600 dark:text-gray-400">
                            Instalación laboral subtotal:
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
