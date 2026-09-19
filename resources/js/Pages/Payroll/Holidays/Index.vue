<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessageBox } from 'element-plus';
import { Plus, Delete, MagicStick } from '@element-plus/icons-vue';
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
        <el-dialog v-model="dialogVisible" title="Agregar día festivo" width="480px" top="10vh">
            <el-form :model="form" label-position="top" size="default">
                <el-form-item label="Fecha" required :error="form.errors.date">
                    <el-date-picker v-model="form.date" type="date" value-format="YYYY-MM-DD" placeholder="Seleccionar fecha" class="w-full" />
                </el-form-item>

                <el-form-item label="Nombre" required :error="form.errors.name">
                    <el-input v-model="form.name" placeholder="Ej. Día de la empresa" maxlength="150" />
                </el-form-item>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Descanso obligatorio" :error="form.errors.is_mandatory">
                        <el-switch v-model="form.is_mandatory" />
                    </el-form-item>

                    <el-form-item label="Pago extra si se labora" :error="form.errors.apply_extra_pay">
                        <el-switch v-model="form.apply_extra_pay" />
                    </el-form-item>
                </div>

                <el-form-item label="Notas" :error="form.errors.notes">
                    <el-input v-model="form.notes" maxlength="255" placeholder="Comentarios opcionales" />
                </el-form-item>
            </el-form>

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
