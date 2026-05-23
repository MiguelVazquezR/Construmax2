<script setup>
import { OfficeBuilding } from '@element-plus/icons-vue';

defineProps({
    budget: Object,
});
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
        <h3 class="font-bold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2">
            <el-icon><OfficeBuilding /></el-icon> Cliente
        </h3>

        <div class="space-y-3">
            <div>
                <p class="text-xs text-gray-400 uppercase">Empresa</p>
                <p class="font-bold text-gray-800 dark:text-white">{{ budget.ticket?.customer?.name }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase">Contacto</p>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ budget.ticket?.contact?.name }}</p>
                <a :href="`mailto:${budget.ticket?.contact?.email}`" class="text-xs text-primary hover:underline">{{ budget.ticket?.contact?.email }}</a>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase">Sucursal</p>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ budget.ticket?.branch?.branch_name }}</p>
            </div>
        </div>
    </div>
</template>
