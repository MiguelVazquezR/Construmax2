<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Back } from '@element-plus/icons-vue';
import BudgetForm from '@/Pages/Budgets/Partials/BudgetForm.vue';

defineProps({
    tickets: Array,
    users: Array,
    technicians: Array,
    preselectedTicketId: { type: Number, default: null },
});
</script>

<template>
    <AppLayout title="Nuevo presupuesto">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">
                    Nuevo presupuesto
                </h2>
                <Link :href="route('budgets.index')">
                    <el-button :icon="Back" circle />
                </Link>
            </div>
        </template>

        <div class="max-w-6xl mx-auto py-6 sm:px-6 lg:px-8">
            <BudgetForm
                mode="create"
                :tickets="tickets"
                :users="users"
                :technicians="technicians"
                :preselected-ticket-id="preselectedTicketId"
            />
        </div>
    </AppLayout>
</template>