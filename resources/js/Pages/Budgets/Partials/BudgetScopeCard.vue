<script setup>
import { Document } from '@element-plus/icons-vue';

defineProps({
    budget: Object,
});
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
        <div class="p-4 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#2b2b2e]">
            <h3 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                <el-icon><Document /></el-icon> Descripción y alcance
            </h3>
        </div>
        <div class="p-6">
            <el-descriptions border :column="2">
                <el-descriptions-item label="Tipo de servicio">{{ budget.ticket?.service_type }}</el-descriptions-item>
                <el-descriptions-item label="Prioridad">
                    <el-tag size="small" :type="budget.ticket?.priority === 'Urgente' ? 'danger' : 'info'">{{ budget.ticket?.priority }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="Duración estimada">{{ budget.ticket?.duration || 'No especificada' }}</el-descriptions-item>
                <el-descriptions-item label="País">{{ budget.ticket?.branch?.country || '—' }}</el-descriptions-item>
                <el-descriptions-item label="Región">{{ budget.ticket?.branch?.region || '—' }}</el-descriptions-item>
                <el-descriptions-item label="Unidad">{{ budget.ticket?.branch?.unit || '—' }}</el-descriptions-item>
                <el-descriptions-item label="Sucursal" :span="2">{{ budget.ticket?.branch?.branch_name || '—' }}</el-descriptions-item>
            </el-descriptions>

            <div class="mt-4 p-4 bg-gray-50/50 dark:bg-[#252529]/50 rounded-md text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">
                {{ budget.description || 'Sin descripción detallada.' }}
            </div>
        </div>
    </div>
</template>
