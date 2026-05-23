<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Back } from '@element-plus/icons-vue';
import BudgetForm from '@/Pages/Budgets/Partials/BudgetForm.vue';

defineProps({
    budget: Object,
    tickets: Array,
    users: Array,
});
</script>

<template>
    <AppLayout :title="`Editar presupuesto #${budget.id}`">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">
                    Editar presupuesto #{{ budget.id }}
                </h2>
                <Link :href="route('budgets.index')">
                    <el-button :icon="Back" circle />
                </Link>
            </div>
        </template>

        <div class="max-w-6xl mx-auto py-6 sm:px-6 lg:px-8">
            <BudgetForm
                mode="edit"
                :tickets="tickets"
                :users="users"
                :budget="budget"
            />
        </div>
    </AppLayout>
</template>
