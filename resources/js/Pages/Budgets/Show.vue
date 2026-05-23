<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { Document } from '@element-plus/icons-vue';
import BudgetDetailHeader from '@/Pages/Budgets/Partials/BudgetDetailHeader.vue';
import BudgetScopeCard from '@/Pages/Budgets/Partials/BudgetScopeCard.vue';
import BudgetConceptsTable from '@/Pages/Budgets/Partials/BudgetConceptsTable.vue';
import BudgetTechniciansSection from '@/Pages/Budgets/Partials/BudgetTechniciansSection.vue';
import BudgetFilesSection from '@/Pages/Budgets/Partials/BudgetFilesSection.vue';
import BudgetTicketCard from '@/Pages/Budgets/Partials/BudgetTicketCard.vue';
import BudgetClientCard from '@/Pages/Budgets/Partials/BudgetClientCard.vue';
import BudgetFinanceCard from '@/Pages/Budgets/Partials/BudgetFinanceCard.vue';

const { can } = usePermissions();

defineProps({
    budget: Object,
});

// --- PREVISUALIZACIÃ“N (compartida) ---
const showPreviewModal = ref(false);
const previewUrl = ref('');
const previewType = ref('');
const previewTitle = ref('');

const openPreview = (file) => {
    previewUrl.value = file.original_url;
    previewTitle.value = file.file_name;

    const mime = file.mime_type;
    if (mime.startsWith('image/')) {
        previewType.value = 'image';
    } else if (mime === 'application/pdf') {
        previewType.value = 'pdf';
    } else {
        window.open(file.original_url, '_blank');
        return;
    }
    showPreviewModal.value = true;
};

const getStatusColor = (status) => {
    const map = {
        'Borrador': 'info',
        'Cotización': 'warning',
        'Presupuesto enviado': 'primary',
        'Facturado': 'warning',
        'Facturación': 'danger',
        'Trabajo en proceso': 'primary',
        'Trabajo terminado': 'success',
        'Pagado': 'success',
        'Perdido': 'danger',
    };
    return map[status] || 'info';
};
</script>

<template>
    <AppLayout :title="`Presupuesto ${budget.ticket.folio}`">
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-3">
                    <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">
                        Detalle del proyecto
                    </h2>
                    <el-tag :type="getStatusColor(budget.status)" effect="dark">
                        {{ budget.status }}
                    </el-tag>
                </div>

                <div class="flex gap-2 w-full sm:w-auto">
                    <Link :href="route('budgets.index')" class="w-full sm:w-auto">
                        <el-button class="w-full sm:w-auto" icon="Back">Volver al listado</el-button>
                    </Link>
                    <Link v-if="can('budgets.edit')" :href="route('budgets.edit', budget.id)" class="w-full sm:w-auto">
                        <el-button type="primary" color="#f26c17" icon="Edit" class="w-full sm:w-auto">
                            Editar
                        </el-button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">

            <!-- RESUMEN SUPERIOR -->
            <BudgetDetailHeader :budget="budget" />

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- COLUMNA IZQUIERDA -->
                <div class="lg:col-span-2 space-y-6">
                    <BudgetScopeCard :budget="budget" />
                    <BudgetConceptsTable :budget="budget" />
                    <BudgetTechniciansSection :budget="budget" @preview="openPreview" />
                    <BudgetFilesSection :budget="budget" @preview="openPreview" />
                </div>

                <!-- COLUMNA DERECHA -->
                <div class="lg:col-span-1 space-y-6">
                    <BudgetTicketCard :budget="budget" />
                    <BudgetClientCard :budget="budget" />
                    <BudgetFinanceCard :budget="budget" @preview="openPreview" />
                </div>
            </div>
        </div>

        <!-- MODAL DE PREVISUALIZACIÃ“N -->
        <el-dialog
            v-model="showPreviewModal"
            :title="previewTitle"
            width="80%"
            align-center
        >
            <div class="flex justify-center bg-gray-100 p-4 rounded min-h-[400px]">
                <img v-if="previewType === 'image'" :src="previewUrl" class="max-h-[70vh] object-contain" />

                <iframe
                    v-else-if="previewType === 'pdf'"
                    :src="previewUrl"
                    class="w-full h-[70vh]"
                    frameborder="0"
                ></iframe>

                <div v-else class="flex flex-col items-center justify-center text-gray-500">
                    <el-icon :size="48"><Document /></el-icon>
                    <p class="mt-4">Vista previa no disponible para este formato.</p>
                    <a :href="previewUrl" target="_blank" class="mt-2 text-primary hover:underline">
                        Descargar archivo
                    </a>
                </div>
            </div>
        </el-dialog>
    </AppLayout>
</template>