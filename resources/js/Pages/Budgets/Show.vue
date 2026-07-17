<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { Document, Printer, Tickets } from '@element-plus/icons-vue';
import BudgetDetailHeader from '@/Pages/Budgets/Partials/BudgetDetailHeader.vue';
import BudgetScopeCard from '@/Pages/Budgets/Partials/BudgetScopeCard.vue';
import BudgetConceptsTable from '@/Pages/Budgets/Partials/BudgetConceptsTable.vue';
import BudgetTechniciansSection from '@/Pages/Budgets/Partials/BudgetTechniciansSection.vue';
import BudgetFilesSection from '@/Pages/Budgets/Partials/BudgetFilesSection.vue';
import BudgetTicketCard from '@/Pages/Budgets/Partials/BudgetTicketCard.vue';
import BudgetClientCard from '@/Pages/Budgets/Partials/BudgetClientCard.vue';
import BudgetFinanceCard from '@/Pages/Budgets/Partials/BudgetFinanceCard.vue';

const { can } = usePermissions();

const props = defineProps({
    budget: Object,
});

const activeTab = ref('scope');

// --- PREVIEW (compartida) ---
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

const getTicketStatusColor = (status) => {
    const map = {
        'Borrador': 'info',
        'Catálogo': 'warning',
        'Proceso de ejecución': 'primary',
        'Ejecutado': 'success',
        'Facturación': 'danger',
        'Facturado': 'warning',
        'Pagado': 'success',
        'Completado': 'success',
        'Cancelado': 'danger',
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
                    <el-tag :type="getTicketStatusColor(budget.ticket?.status)" effect="dark" size="large">
                        {{ budget.ticket?.status || 'N/A' }}
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

            <!-- RESUMEN SUPERIOR (fuera de tabs) -->
            <BudgetDetailHeader :budget="budget" />

            <!-- PESTAÑAS -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] min-h-[500px]">
                <el-tabs v-model="activeTab" class="px-6 pt-2">

                    <!-- TAB 1: ALCANCE Y COSTOS -->
                    <el-tab-pane label="Alcance y costos" name="scope">
                        <div class="pb-4 space-y-6">
                            <!-- CATÁLOGO DE COSTOS -->
                            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                                <div class="p-4 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#2b2b2e]">
                                    <h3 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                        <el-icon><Tickets /></el-icon> Catálogo de costos
                                    </h3>
                                </div>
                                <div class="p-6">
                                    <!-- Tiene catálogo -->
                                    <div v-if="budget.latest_catalog" class="space-y-4">
                                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                            <div class="flex items-center gap-3">
                                                <el-tag type="success" size="large" effect="dark">Catálogo registrado</el-tag>
                                                <span class="text-sm text-gray-500">
                                                    Versión {{ budget.latest_catalog.version }}
                                                </span>
                                                <el-tag
                                                    :type="budget.latest_catalog.status === 'approved' ? 'success' : 'warning'"
                                                    size="small"
                                                    effect="plain"
                                                >
                                                    {{ budget.latest_catalog.status === 'approved' ? 'Aprobado' : 'Pendiente de aprobación' }}
                                                </el-tag>
                                            </div>
                                            <div class="flex gap-2">
                                                <Link :href="route('costs.show', budget.id)">
                                                    <el-button type="primary" plain size="default">
                                                        Ver detalle de costos
                                                    </el-button>
                                                </Link>
                                                <Link :href="route('costs.print', budget.id)" target="_blank">
                                                    <el-button type="success" plain size="default" :icon="Printer">
                                                        Imprimir plantilla
                                                    </el-button>
                                                </Link>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Sin catálogo -->
                                    <div v-else class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                        <div class="flex items-center gap-3">
                                            <el-tag type="info" size="large">Sin catálogo</el-tag>
                                            <span class="text-sm text-gray-500">
                                                Aún no se ha registrado el catálogo de costos para este presupuesto.
                                            </span>
                                        </div>
                                        <Link :href="route('costs.show', budget.id)">
                                            <el-button type="primary" size="default">
                                                Ir a costos
                                            </el-button>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                            <BudgetConceptsTable :budget="budget" />
                            <BudgetScopeCard :budget="budget" />
                        </div>
                    </el-tab-pane>

                    <!-- TAB 2: TÉCNICOS -->
                    <el-tab-pane v-if="can('technicians.index')" label="Técnicos" name="technicians">
                        <div class="pb-4">
                            <BudgetTechniciansSection :budget="budget" @preview="openPreview" />
                        </div>
                    </el-tab-pane>

                    <!-- TAB 3: DOCUMENTOS -->
                    <el-tab-pane v-if="can('budgets.files.manage')" label="Documentos" name="documents">
                        <div class="pb-4">
                            <BudgetFilesSection :budget="budget" @preview="openPreview" />
                        </div>
                    </el-tab-pane>

                    <!-- TAB 4: CLIENTE Y FINANZAS -->
                    <el-tab-pane v-if="can('budgets.payments.manage')" label="Cliente y finanzas" name="finance">
                        <div class="pb-4 space-y-6">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <BudgetTicketCard :budget="budget" />
                                <BudgetClientCard :budget="budget" />
                            </div>
                            <BudgetFinanceCard :budget="budget" @preview="openPreview" />
                        </div>
                    </el-tab-pane>

                </el-tabs>
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