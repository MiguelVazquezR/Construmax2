<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CustomerForm from './Partials/CustomerForm.vue';
</script>

<template>
    <AppLayout title="Registrar nuevo cliente">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">
                    Nuevo cliente
                </h2>
                <Link :href="route('customers.index')">
                    <el-button icon="Back" circle />
                </Link>
            </div>
        </template>

        <div class="max-w-5xl mx-auto py-6 sm:px-6 lg:px-8">
            <CustomerForm :is-edit="false" />
        </div>
    </AppLayout>
</template>