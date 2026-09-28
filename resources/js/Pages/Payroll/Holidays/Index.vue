<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessageBox } from 'element-plus';
import { Calendar, Delete, InfoFilled, MagicStick, Money, Plus } from '@element-plus/icons-vue';
import { useFlashMessages } from '@/Composables/useFlashMessages';

const props = defineProps({
    holidays: Array,
    years: Array,
});

useFlashMessages();

const currentYear = new Date().getFullYear();
const selectedYear = ref(props.years?.length ? props.years[0] : currentYear);

const yearOptions = computed(() => {
    const years = new Set([...(props.years || []), currentYear, currentYear + 1]);
    return Array.from(years).sort((a, b) => b - a);
});

const filteredHolidays = computed(() =>
    (props.holidays || []).filter((holiday) => Number(holiday.year) === Number(selectedYear.value))
);

const dialogVisible = ref(false);
const syncing = ref(false);

const form = useForm({
    date: null,
    name: '',
    is_mandatory: true,
    apply_extra_pay: true,
    notes: '',
});

const openDialog = () => {
    form.reset();
    form.clearErrors();
    dialogVisible.value = true;
};

const save = () => {
    form.post(route('payroll.holidays.store'), {
        onSuccess: () => {
            dialogVisible.value = false;
        },
    });
};

const destroy = (holiday) => {
    ElMessageBox.confirm(
        `¿Eliminar "${holiday.name}" del calendario?`,
        'Eliminar día festivo',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        form.delete(route('payroll.holidays.destroy', holiday.id));
    }).catch(() => {});
};

const syncYear = () => {
    syncing.value = true;

    form.post(route('payroll.holidays.sync', { year: selectedYear.value }), {
        onFinish: () => {
            syncing.value = false;
        },
    });
};

// Format without timezone shifts: the API sends YYYY-MM-DD.
const parseDate = (value) => {
    const raw = String(value).substring(0, 10);
    const [year, month, day] = raw.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const formatDate = (value) => {
    const date = parseDate(value);
    return date.toLocaleDateString('es-MX', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
};

// Live summary of what will be saved, shown inside the dialog.
const formSummary = computed(() => {
    const name = form.name?.trim() || 'el nuevo día';
    const date = form.date ? formatDate(form.date) : 'la fecha seleccionada';
    const kind = form.is_mandatory ? 'descanso obligatorio' : 'día de la empresa';
    const pay = form.apply_extra_pay ? 'con pago extra si se labora' : 'sin pago extra';

    return `Se registrará «${name}» el ${date} como ${kind} y ${pay}.`;
});
</script>

<template>
    <AppLayout title="Días festivos">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">Días festivos</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Días de descanso obligatorio según la Ley Federal del Trabajo, más los días de la empresa.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <el-select v-model="selectedYear" style="width: 130px">
                        <el-option v-for="year in yearOptions" :key="year" :label="String(year)" :value="year" />
                    </el-select>
                    <el-button :icon="MagicStick" :loading="syncing" @click="syncYear">
                        Generar año oficial
                    </el-button>
                    <el-button type="primary" color="#f26c17" :icon="Plus" @click="openDialog">
                        Agregar día festivo
                    </el-button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">
            <el-alert
                type="info"
                :closable="false"
                show-icon
                title="Pago por jornada festiva"
                description="Si un colaborador trabaja un día de descanso obligatorio, además del salario del día recibe el pago extra configurado (por defecto, un salario doble adicional). Los días generados automáticamente siguen las reglas del artículo 74 de la LFT."
            />

            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-table :data="filteredHolidays" style="width: 100%" stripe>
                    <el-table-column label="Fecha" min-width="230">
                        <template #default="scope">
                            <span class="capitalize font-medium text-gray-800 dark:text-gray-200">
                                {{ formatDate(scope.row.date) }}
                            </span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Nombre" min-width="240">
                        <template #default="scope">
                            <span class="text-sm">{{ scope.row.name }}</span>
                            <div v-if="scope.row.notes" class="text-xs text-gray-500">{{ scope.row.notes }}</div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Origen" width="110" align="center">
                        <template #default="scope">
                            <el-tag :type="scope.row.source === 'lft' ? 'primary' : 'info'" size="small" effect="plain">
                                {{ scope.row.source === 'lft' ? 'LFT' : 'Manual' }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Descanso obligatorio" width="160" align="center">
                        <template #default="scope">
                            <el-tag :type="scope.row.is_mandatory ? 'success' : 'info'" size="small" effect="plain">
                                {{ scope.row.is_mandatory ? 'Sí' : 'No' }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Pago extra si se labora" width="180" align="center">
                        <template #default="scope">
                            <el-tag :type="scope.row.apply_extra_pay ? 'warning' : 'info'" size="small" effect="plain">
                                {{ scope.row.apply_extra_pay ? 'Aplica' : 'No aplica' }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="" width="80" align="right">
                        <template #default="scope">
                            <el-button :icon="Delete" circle plain size="small" type="danger" @click="destroy(scope.row)" />
                        </template>
                    </el-table-column>
                </el-table>

                <div v-if="filteredHolidays.length === 0" class="text-center py-12 border-t border-dashed border-gray-200 dark:border-gray-700">
                    <el-empty :image-size="100" description="Sin días festivos para el año seleccionado">
                        <template #default>
                            <el-button :icon="MagicStick" @click="syncYear">Generar año oficial</el-button>
                        </template>
                    </el-empty>
                </div>
            </div>
        </div>

        <!-- Add dialog -->
        <el-dialog v-model="dialogVisible" title="Agregar día festivo" width="560px" top="8vh">
            <div class="flex items-start gap-2 rounded-lg bg-[#fdf0e7] dark:bg-[#3a2a1d] px-3 py-2 mb-5">
                <el-icon class="mt-0.5 shrink-0 text-[#f26c17]"><InfoFilled /></el-icon>
                <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                    Los días festivos son días de descanso pagados. Si el colaborador trabaja uno, además del
                    sueldo del día recibe el pago extra configurado.
                </p>
            </div>

            <el-form :model="form" label-position="top" size="default">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Fecha" required :error="form.errors.date">
                        <el-date-picker v-model="form.date" type="date" value-format="YYYY-MM-DD" placeholder="Seleccionar fecha" class="w-full" />
                    </el-form-item>

                    <el-form-item label="Nombre" required :error="form.errors.name">
                        <el-input v-model="form.name" placeholder="Ej. Día de la empresa" maxlength="150" />
                    </el-form-item>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-1">
                    <div
                        class="flex items-start justify-between gap-3 rounded-xl border px-4 py-3 transition-colors"
                        :class="form.is_mandatory
                            ? 'border-[#f26c17]/40 bg-[#fdf0e7]/60 dark:bg-[#3a2a1d]/40'
                            : 'border-gray-100 dark:border-[#2b2b2e]'"
                    >
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-blue-50 dark:bg-blue-500/10 text-blue-500 flex items-center justify-center shrink-0">
                                <el-icon :size="16"><Calendar /></el-icon>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-100">Descanso obligatorio</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Día de descanso oficial (LFT) o marcado por la empresa.</p>
                            </div>
                        </div>
                        <el-switch v-model="form.is_mandatory" style="--el-switch-on-color: #f26c17;" />
                    </div>

                    <div
                        class="flex items-start justify-between gap-3 rounded-xl border px-4 py-3 transition-colors"
                        :class="form.apply_extra_pay
                            ? 'border-[#f26c17]/40 bg-[#fdf0e7]/60 dark:bg-[#3a2a1d]/40'
                            : 'border-gray-100 dark:border-[#2b2b2e]'"
                    >
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                                <el-icon :size="16"><Money /></el-icon>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-100">Pago extra si se labora</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Paga el adicional configurado cuando alguien trabaja ese día.</p>
                            </div>
                        </div>
                        <el-switch v-model="form.apply_extra_pay" style="--el-switch-on-color: #f26c17;" />
                    </div>
                </div>

                <p v-if="form.errors.is_mandatory" class="text-xs text-red-500 mb-2">{{ form.errors.is_mandatory }}</p>
                <p v-if="form.errors.apply_extra_pay" class="text-xs text-red-500 mb-2">{{ form.errors.apply_extra_pay }}</p>

                <el-form-item label="Notas" :error="form.errors.notes">
                    <el-input v-model="form.notes" maxlength="255" placeholder="Comentarios opcionales, por ejemplo el motivo del día" />
                </el-form-item>
            </el-form>

            <!-- Live summary of the new holiday -->
            <div
                v-if="form.date || form.name"
                class="flex items-start gap-2 rounded-lg bg-gray-50 dark:bg-[#252529] px-3 py-2 text-xs text-gray-500 dark:text-gray-400"
            >
                <el-icon class="mt-0.5 shrink-0"><Calendar /></el-icon>
                <p>{{ formSummary }}</p>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="dialogVisible = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="form.processing" @click="save">
                        Guardar día festivo
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </AppLayout>
</template>
