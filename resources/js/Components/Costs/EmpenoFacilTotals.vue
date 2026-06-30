<script setup>
import { useCostsHelpers } from '@/Composables/useCostsHelpers';

const props = defineProps({
    combinedSubtotal: { type: Number, required: true },
    materialsSubtotal: { type: Number, default: 0 },
    nonInstallationLabor: { type: Number, default: 0 },
    laborUtility: { type: Number, default: 0 },
    iva: { type: Number, required: true },
    total: { type: Number, required: true },
    includeIva: { type: Boolean, required: true },
    currency: { type: String, default: 'MXN' },
});

const emit = defineEmits(['update:nonInstallationLabor', 'update:laborUtility', 'update:includeIva', 'save', 'print']);

const { formatCurrency, copyToClipboard } = useCostsHelpers();
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
        <div class="p-4 border-b border-gray-100 dark:border-[#2b2b2e] flex justify-between items-center">
            <h3 class="text-md font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <el-icon><Coin /></el-icon> Totales
            </h3>
            <div class="flex items-center gap-2">
                <el-button type="info" plain size="small" icon="Printer" @click="$emit('print')">
                    Imprimir catálogo
                </el-button>
                <el-button type="primary" color="#f26c17" size="small" icon="Check" @click="$emit('save')">
                    Guardar versión
                </el-button>
            </div>
        </div>
        <div class="p-4">
            <table class="w-full text-sm max-w-md ml-auto">
                <tbody>
                    <!-- Subtotal -->
                    <tr class="border-b dark:border-[#2b2b2e]">
                        <td class="px-4 py-3 text-right font-bold text-gray-600 dark:text-gray-400 w-48">
                            Subtotal:
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-bold text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-[#252529]">
                            <el-button text size="small" class="!font-mono !font-bold !text-gray-800 dark:!text-gray-200"
                                @click="copyToClipboard(combinedSubtotal)">
                                {{ formatCurrency(combinedSubtotal, currency) }}
                            </el-button>
                        </td>
                    </tr>
                    <!-- Non installation labor -->
                    <tr class="border-b dark:border-[#2b2b2e]">
                        <td class="px-4 py-3 text-right font-bold text-gray-600 dark:text-gray-400">
                            Non installation labor:
                            <el-tooltip placement="top" effect="dark" raw-content>
                                <template #content>
                                    <div class="text-xs leading-relaxed">
                                        12% del subtotal de materiales:<br />
                                        12% &times; {{ formatCurrency(materialsSubtotal, currency) }} = {{ formatCurrency(materialsSubtotal * 0.12, currency) }}
                                    </div>
                                </template>
                                <el-icon class="ml-1 text-gray-400 hover:text-blue-500 cursor-help align-middle"><InfoFilled /></el-icon>
                            </el-tooltip>
                        </td>
                        <td class="px-2 py-2">
                            <el-input-number
                                :model-value="props.nonInstallationLabor"
                                @update:model-value="$emit('update:nonInstallationLabor', $event)"
                                :min="0" :step="0.01" :controls="false"
                                class="!w-full text-right" />
                        </td>
                    </tr>
                    <!-- Utilidad de mano de obra -->
                    <tr class="border-b dark:border-[#2b2b2e]">
                        <td class="px-4 py-3 text-right font-bold text-gray-600 dark:text-gray-400">
                            Utilidad de mano de obra:
                            <el-tooltip placement="top" effect="dark" raw-content>
                                <template #content>
                                    <div class="text-xs leading-relaxed">
                                        18% del subtotal combinado (materiales + mano de obra):<br />
                                        18% &times; {{ formatCurrency(combinedSubtotal, currency) }} = {{ formatCurrency(combinedSubtotal * 0.18, currency) }}
                                    </div>
                                </template>
                                <el-icon class="ml-1 text-gray-400 hover:text-blue-500 cursor-help align-middle"><InfoFilled /></el-icon>
                            </el-tooltip>
                        </td>
                        <td class="px-2 py-2">
                            <el-input-number
                                :model-value="props.laborUtility"
                                @update:model-value="$emit('update:laborUtility', $event)"
                                :min="0" :step="0.01" :controls="false"
                                class="!w-full text-right" />
                        </td>
                    </tr>
                    <!-- IVA -->
                    <tr class="border-b dark:border-[#2b2b2e]">
                        <td class="px-4 py-3 text-right font-bold text-gray-600 dark:text-gray-400">
                            <el-checkbox
                                :model-value="includeIva"
                                @update:model-value="$emit('update:includeIva', $event)"
                                label="IVA (16%):" class="!mr-0" />
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-bold text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-[#252529]">
                            <el-button text size="small" class="!font-mono !font-bold !text-gray-800 dark:!text-gray-200"
                                @click="copyToClipboard(iva)">
                                {{ formatCurrency(iva, currency) }}
                            </el-button>
                        </td>
                    </tr>
                    <!-- Subtotal general -->
                    <tr>
                        <td class="px-4 py-3 text-right font-bold text-gray-800 dark:text-white text-base">
                            Subtotal general:
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-bold text-[#f26c17] bg-gray-50 dark:bg-[#252529] text-lg">
                            <el-button text size="small" class="!font-mono !font-bold !text-[#f26c17] !text-lg"
                                @click="copyToClipboard(total)">
                                {{ formatCurrency(total, currency) }}
                            </el-button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
