<script setup>
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessageBox } from 'element-plus';
import { Plus, Delete, Document, Search } from '@element-plus/icons-vue';
import { useFlashMessages } from '@/Composables/useFlashMessages';

const props = defineProps({
    incidents: Object,
    users: Array,
    types: Object,
    filters: Object,
});

useFlashMessages();

const typeOptions = computed(() =>
    Object.entries(props.types || {}).map(([value, label]) => ({ value, label }))
);

const filters = reactive({
    user_id: props.filters?.user_id || null,
    type: props.filters?.type || '',
    from: props.filters?.from || null,
    to: props.filters?.to || null,
});

const clean = (source) =>
    Object.fromEntries(Object.entries(source).filter(([, value]) => value !== null && value !== '' && value !== undefined));

const applyFilters = () => {
    router.get(route('payroll.incidents.index'), clean(filters), { preserveState: true, replace: true });
};

const resetFilters = () => {
    filters.user_id = null;
    filters.type = '';
    filters.from = null;
    filters.to = null;
    applyFilters();
};

const changePage = (page) => {
    router.get(route('payroll.incidents.index'), { ...clean(filters), page }, { preserveState: true, replace: true });
};

// --- Create ---

const dialogVisible = ref(false);
const saving = ref(false);

const form = useForm({
    user_id: null,
    type: 'absence_justified',
    start_date: null,
    end_date: null,
    is_paid: null,
    notes: '',
    support: null,
});

const openDialog = () => {
    form.reset();
    form.clearErrors();
    form.is_paid = null;
    dialogVisible.value = true;
};

const save = () => {
    saving.value = true;

    form.post(route('payroll.incidents.store'), {
        forceFormData: Boolean(form.support),
        onSuccess: () => {
            dialogVisible.value = false;
        },
        onFinish: () => {
            saving.value = false;
        },
    });
};

const destroy = (incident) => {
    ElMessageBox.confirm(
        `¿Eliminar la incidencia de "${incident.user?.name}" (${incident.type_label})?`,
        'Eliminar incidencia',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        form.delete(route('payroll.incidents.destroy', incident.id));
    }).catch(() => {});
};

// --- Formatting ---

const parseDate = (value) => {
    const raw = String(value).substring(0, 10);
    const [year, month, day] = raw.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const formatDate = (value) => {
    if (!value) return '—';
    return parseDate(value).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
};

const payLabel = (incident) => {
    if (incident.is_paid === null) return 'Según el tipo';
    return incident.is_paid ? 'Con goce' : 'Sin goce';
};

const payTagType = (incident) => {
    if (incident.is_paid === null) return 'info';
    return incident.is_paid ? 'success' : 'danger';
};
</script>

<template>
    <AppLayout title="Incidencias">
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">Incidencias</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Faltas, incapacidades, permisos y vacaciones registradas por el equipo.
                    </p>
                </div>
                <el-button type="primary" color="#f26c17" :icon="Plus" @click="openDialog">
                    Registrar incidencia
                </el-button>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">

            <!-- Filters -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <el-select v-model="filters.user_id" filterable clearable placeholder="Colaborador" @change="applyFilters">
                        <el-option v-for="user in users" :key="user.id" :label="user.name" :value="user.id" />
                    </el-select>

                    <el-select v-model="filters.type" clearable placeholder="Tipo de incidencia" @change="applyFilters">
                        <el-option v-for="option in typeOptions" :key="option.value" :label="option.label" :value="option.value" />
                    </el-select>

                    <el-date-picker v-model="filters.from" type="date" value-format="YYYY-MM-DD" placeholder="Desde" @change="applyFilters" />
                    <el-date-picker v-model="filters.to" type="date" value-format="YYYY-MM-DD" placeholder="Hasta" @change="applyFilters" />
                </div>
                <div class="flex justify-end mt-3">
                    <el-button :icon="Search" size="small" @click="resetFilters">Limpiar filtros</el-button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-table :data="incidents.data" style="width: 100%" stripe>
                    <el-table-column label="Colaborador" min-width="170">
                        <template #default="scope">
                            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ scope.row.user?.name }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Tipo" min-width="180">
                        <template #default="scope">
                            <el-tag size="small" type="warning" effect="plain">{{ scope.row.type_label }}</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Periodo" min-width="190">
                        <template #default="scope">
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                {{ formatDate(scope.row.start_date) }} — {{ formatDate(scope.row.end_date) }}
                            </span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Días" width="80" align="center">
                        <template #default="scope">
                            <span class="text-sm font-medium">{{ Number(scope.row.days || 0) }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Pago" width="130" align="center">
                        <template #default="scope">
                            <el-tag :type="payTagType(scope.row)" size="small" effect="plain">
                                {{ payLabel(scope.row) }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Comprobante" width="120" align="center">
                        <template #default="scope">
                            <a v-if="scope.row.support_url" :href="scope.row.support_url" target="_blank" class="text-primary">
                                <el-icon :size="18"><Document /></el-icon>
                            </a>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Notas" min-width="170">
                        <template #default="scope">
                            <span class="text-xs text-gray-500">{{ scope.row.notes || '—' }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="" width="70" align="right">
                        <template #default="scope">
                            <el-button :icon="Delete" circle plain size="small" type="danger" @click="destroy(scope.row)" />
                        </template>
                    </el-table-column>
                </el-table>

                <div class="flex justify-end p-4">
                    <el-pagination
                        layout="prev, pager, next"
                        :total="incidents.total"
                        :page-size="incidents.per_page"
                        :current-page="incidents.current_page"
                        @current-change="changePage"
                    />
                </div>

                <div v-if="incidents.data.length === 0" class="text-center py-12 border-t border-dashed border-gray-200 dark:border-gray-700">
                    <el-empty description="Sin incidencias registradas" :image-size="100" />
                </div>
            </div>
        </div>

        <!-- Create dialog -->
        <el-dialog v-model="dialogVisible" title="Registrar incidencia" width="560px" top="8vh">
            <el-form :model="form" label-position="top" size="default">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Colaborador" required :error="form.errors.user_id">
                        <el-select v-model="form.user_id" filterable placeholder="Seleccionar" class="w-full">
                            <el-option v-for="user in users" :key="user.id" :label="user.name" :value="user.id" />
                        </el-select>
                    </el-form-item>

                    <el-form-item label="Tipo de incidencia" required :error="form.errors.type">
                        <el-select v-model="form.type" class="w-full">
                            <el-option v-for="option in typeOptions" :key="option.value" :label="option.label" :value="option.value" />
                        </el-select>
                    </el-form-item>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Fecha de inicio" required :error="form.errors.start_date">
                        <el-date-picker v-model="form.start_date" type="date" value-format="YYYY-MM-DD" placeholder="Seleccionar" class="w-full" />
                    </el-form-item>

                    <el-form-item label="Fecha final" :error="form.errors.end_date">
                        <el-date-picker v-model="form.end_date" type="date" value-format="YYYY-MM-DD" placeholder="Mismo día si se omite" class="w-full" />
                    </el-form-item>
                </div>

                <el-form-item label="Efecto en el pago" :error="form.errors.is_paid">
                    <el-select v-model="form.is_paid" placeholder="Según el tipo de incidencia" clearable class="w-full">
                        <el-option label="Según el tipo de incidencia" :value="null" />
                        <el-option label="Con goce de sueldo" :value="true" />
                        <el-option label="Sin goce de sueldo" :value="false" />
                    </el-select>
                </el-form-item>

                <el-form-item label="Comprobante (opcional)" :error="form.errors.support">
                    <el-upload
                        action="#"
                        :auto-upload="false"
                        :limit="1"
                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                        :on-change="(file) => (form.support = file.raw)"
                        :on-remove="() => (form.support = null)"
                    >
                        <el-button>Seleccionar archivo</el-button>
                    </el-upload>
                </el-form-item>

                <el-form-item label="Notas" :error="form.errors.notes">
                    <el-input v-model="form.notes" maxlength="255" placeholder="Comentarios opcionales" />
                </el-form-item>
            </el-form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="dialogVisible = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="saving" @click="save">
                        Guardar incidencia
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </AppLayout>
</template>
