<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CustomerForm from './Partials/CustomerForm.vue';

const props = defineProps({
    customer: Object,
});
</script>

<template>
    <AppLayout :title="customer.type === 'prospect' ? 'Editar prospecto' : 'Editar cliente'">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                    {{ customer.type === 'prospect' ? 'Editar prospecto' : 'Editar cliente' }}: {{ customer.name }}
                </h2>
                <Link :href="route('customers.index')">
                    <el-button icon="Back" circle />
                </Link>
            </div>
        </template>

        <div class="max-w-5xl mx-auto py-6 sm:px-6 lg:px-8">
            <CustomerForm :is-edit="true" :customer="customer" />
        </div>
    </AppLayout>
</template>