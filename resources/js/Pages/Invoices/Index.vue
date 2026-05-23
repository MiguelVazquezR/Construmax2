<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import TableList from '@/Pages/Invoices/Partials/TableList.vue';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    invoices: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');

const statuses = [
    'Facturación', 
    'Facturado',
];

const fetchData = debounce(() => {
    router.get(route('invoices.index'), { 
        search: search.value, 
        status: statusFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 300);

watch([search, statusFilter], fetchData);
</script>

<template>
    <AppLayout title="Gestión de facturación">
        <div class="space-y-4">
            <!-- Barra de herramientas -->
            <div class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] flex flex-col lg:flex-row justify-between items-center gap-4">
                
                <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto flex-1">
                    <div class="w-full sm:w-80">
                        <el-input
                            v-model="search"
                            placeholder="Buscar por proyecto, cliente..."
                            clearable
                            prefix-icon="Search"
                        />
                    </div>

                    <div class="w-full sm:w-48">
                        <el-select v-model="statusFilter" placeholder="Filtrar estado" clearable class="w-full">
                            <el-option label="Todos los estados" value="all" />
                            <el-option v-for="st in statuses" :key="st" :label="st" :value="st" />
                        </el-select>
                    </div>
                </div>
            </div>

            <!-- Contenido principal -->
            <div>
                <el-alert
                    type="info"
                    :closable="false"
                    show-icon
                    class="mb-4"
                >
                    <template #title>
                        Gestiona los presupuestos pendientes de facturación, sube los comprobantes y controla los días de crédito de cada cliente.
                    </template>
                </el-alert>
                
                <TableList :invoices="invoices" />
            </div>
        </div>
    </AppLayout>
</template>