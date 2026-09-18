<script setup>
import { ref, watch } from 'vue';
import { Tickets, Wallet } from '@element-plus/icons-vue';

const props = defineProps({
    modelValue: Boolean,
});

const emit = defineEmits(['update:modelValue', 'select']);

const dialogVisible = ref(props.modelValue);
watch(() => props.modelValue, (value) => { dialogVisible.value = value; });
watch(dialogVisible, (value) => emit('update:modelValue', value));

const choose = (type) => {
    emit('select', type);
    dialogVisible.value = false;
};
</script>

<template>
    <el-dialog v-model="dialogVisible" title="Registrar gasto" width="560px" top="8vh" destroy-on-close>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Selecciona el tipo de gasto que vas a registrar.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
            <el-card shadow="hover" class="cursor-pointer" @click="choose('general')">
                <div class="flex flex-col items-center gap-2 text-center py-2">
                    <el-icon class="text-3xl text-primary"><Wallet /></el-icon>
                    <span class="font-semibold text-gray-800 dark:text-gray-200">Gasto general</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Gastos de operación de la empresa, sin ligarse a un presupuesto.
                    </span>
                </div>
            </el-card>

            <el-card shadow="hover" class="cursor-pointer" @click="choose('budget')">
                <div class="flex flex-col items-center gap-2 text-center py-2">
                    <el-icon class="text-3xl text-blue-500"><Tickets /></el-icon>
                    <span class="font-semibold text-gray-800 dark:text-gray-200">Gasto de presupuesto</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Paga conceptos del desglose, agrega gastos adicionales o comisiones.
                    </span>
                </div>
            </el-card>
        </div>
    </el-dialog>
</template>
