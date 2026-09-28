<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessageBox } from 'element-plus';
import { Plus, Calendar } from '@element-plus/icons-vue';
import { useFlashMessages } from '@/Composables/useFlashMessages';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    periods: Object,
    openPeriod: Object,
    nextPeriod: Object,
    openNextAvailable: Boolean,
    payrollEmployees: Number,
    typeLabels: Object,
});

useFlashMessages();

const { can } = usePermissions();
const canClose = computed(() => can('payroll.periods.close'));

// The open-next-period button is only offered after an early close: when the
// automatic rollover closes the week it opens the following one by itself.
const canOpenNext = computed(() => canClose.value && props.openNextAvailable);

const noOpenDescription = computed(() =>
    props.openNextAvailable
        ? 'Abre el siguiente periodo para continuar la pre-nómina. La nómina se cierra automáticamente los domingos a las 23:59 y el nuevo periodo se abre los lunes a las 00:00.'
        : 'El siguiente periodo se abrirá automáticamente el lunes a las 00:00. La nómina se cierra automáticamente los domingos a las 23:59.'
);

const form = useForm({});

// Long Spanish date for the confirmation dialog ("lunes 28 de septiembre").
const WEEKDAYS_ES = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
const MONTHS_ES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

const longDate = (value) => {
    const date = parseDate(value);
    return `${WEEKDAYS_ES[date.getDay()]} ${date.getDate()} de ${MONTHS_ES[date.getMonth()]}`;
};

// The dialog clarifies the exact dates that will be opened: the following
// Monday–Sunday week after the last period (never repeating existing dates).
const createPeriod = () => {
    if (!props.nextPeriod) return;

    const endYear = parseDate(props.nextPeriod.end_date).getFullYear();

    ElMessageBox.confirm(
        `Se abrirá el siguiente periodo: del ${longDate(props.nextPeriod.start_date)} al ${longDate(props.nextPeriod.end_date)} de ${endYear}.`,
        'Abrir siguiente periodo',
        { confirmButtonText: 'Abrir periodo', cancelButtonText: 'Cancelar', type: 'info' }
    ).then(() => {
        form.post(route('payroll.periods.store'));
    }).catch(() => {});
};

const changePage = (page) => {
    router.get(route('payroll.periods.index'), { page }, { preserveState: true, replace: true });
};

// Row click opens the payroll period detail
const handleRowClick = (row) => {
    router.visit(route('payroll.periods.show', row.id));
};

const parseDate = (value) => {
    const raw = String(value).substring(0, 10);
    const [year, month, day] = raw.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const formatDate = (value) => {
    if (!value) return '—';
    return parseDate(value).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
};

const money = (value) => `$${Number(value || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const statusTagType = (status) => (status === 'open' ? 'warning' : 'success');
const statusLabel = (status) => (status === 'open' ? 'Abierto' : 'Cerrado');
</script>

<template>
    <AppLayout title="Periodos de nómina">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">Periodos de nómina</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Periodos semanales de lunes a domingo, con cierre automático los domingos a las 23:59
                        y apertura los lunes a las 00:00. Haz clic en un periodo para ver su detalle.
                    </p>
                </div>
                <div class="flex items-center gap-2 ml-auto">
                    <el-tag v-if="openPeriod" type="warning" effect="plain" size="large">
                        Periodo abierto: {{ formatDate(openPeriod.start_date) }} — {{ formatDate(openPeriod.end_date) }}
                    </el-tag>
                    <el-button
                        v-if="canOpenNext"
                        type="primary"
                        color="#f26c17"
                        :icon="Plus"
                        :loading="form.processing"
                        @click="createPeriod"
                    >
                        Abrir siguiente periodo
                    </el-button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">
            <el-alert
                v-if="!openPeriod"
                type="info"
                :closable="false"
                show-icon
                title="Sin periodo abierto"
                :description="noOpenDescription"
            />

            <el-alert
                v-if="payrollEmployees === 0"
                type="warning"
                :closable="false"
                show-icon
                title="Aún no hay colaboradores sujetos a nómina"
                description="Marca 'Sujeto a nómina' en Usuarios → Nómina y asistencia, y captura su sueldo diario."
            />

            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-table
                    :data="periods.data"
                    style="width: 100%"
                    stripe
                    @row-click="handleRowClick"
                    row-class-name="cursor-pointer hover:bg-gray-50 dark:hover:bg-[#27272a] transition-colors"
                >
                    <el-table-column label="Periodo" min-width="200">
                        <template #default="scope">
                            <div class="flex items-center gap-2">
                                <el-icon class="text-gray-400"><Calendar /></el-icon>
                                <span class="font-semibold text-gray-800 dark:text-gray-200">
                                    {{ formatDate(scope.row.start_date) }} — {{ formatDate(scope.row.end_date) }}
                                </span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Tipo" width="120" align="center">
                        <template #default="scope">
                            {{ typeLabels[scope.row.type] }}
                        </template>
                    </el-table-column>

                    <el-table-column label="Estatus" width="110" align="center">
                        <template #default="scope">
                            <el-tag :type="statusTagType(scope.row.status)" size="small" effect="plain">
                                {{ statusLabel(scope.row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Recibos" width="90" align="center">
                        <template #default="scope">
                            <el-tag size="small" type="info" effect="plain">{{ scope.row.payslips_count }}</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Percepciones" width="130" align="right">
                        <template #default="scope">
                            {{ scope.row.total_gross ? money(scope.row.total_gross) : '—' }}
                        </template>
                    </el-table-column>

                    <el-table-column label="Deducciones" width="130" align="right">
                        <template #default="scope">
                            {{ scope.row.total_deductions ? money(scope.row.total_deductions) : '—' }}
                        </template>
                    </el-table-column>

                    <el-table-column label="Neto" width="140" align="right">
                        <template #default="scope">
                            <span class="font-semibold">{{ scope.row.total_net ? money(scope.row.total_net) : '—' }}</span>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="flex justify-end p-4">
                    <el-pagination
                        layout="prev, pager, next"
                        :total="periods.total"
                        :page-size="periods.per_page"
                        :current-page="periods.current_page"
                        @current-change="changePage"
                    />
                </div>

                <div v-if="periods.data.length === 0" class="text-center py-12">
                    <el-empty description="Sin periodos de nómina" :image-size="100" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.el-table .cursor-pointer) {
    cursor: pointer;
}
</style>
