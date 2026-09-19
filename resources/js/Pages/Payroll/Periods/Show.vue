<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Back, Download, Lock, Unlock, View, Edit, Delete, Plus, Picture, Location, Clock, Document } from '@element-plus/icons-vue';
import { useFlashMessages } from '@/Composables/useFlashMessages';
import { usePermissions } from '@/Composables/usePermissions';
import axios from 'axios';

const props = defineProps({
    period: Object,
    rows: Array,
    stats: Object,
    adjustments: Array,
    typeLabels: Object,
});

useFlashMessages();

const { can } = usePermissions();
const canManage = computed(() => can('payroll.periods.manage'));
const canClose = computed(() => can('payroll.periods.close'));

const statusLabels = {
    present: 'Asistió',
    absent: 'Falta',
    rest_day: 'Día de descanso',
    no_schedule: 'Sin horario',
    holiday: 'Día festivo',
    incident: 'Incidencia',
};

const statusTagType = (status) => ({
    present: 'success',
    absent: 'danger',
    rest_day: 'info',
    no_schedule: 'info',
    holiday: 'warning',
    incident: 'warning',
}[status] || 'info');

// --- Formatting ---

const parseDate = (value) => {
    const raw = String(value).substring(0, 10);
    const [year, month, day] = raw.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const formatDate = (value) => {
    if (!value) return '—';
    return parseDate(value).toLocaleDateString('es-MX', { weekday: 'short', day: '2-digit', month: 'short' });
};

const money = (value) => `$${Number(value || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const hours = (minutes) => `${Math.floor(Number(minutes || 0) / 60)}:${String(Math.round(Number(minutes || 0) % 60)).padStart(2, '0')}`;

const mapsLink = (day) => {
    const punch = (day.punches || []).find((item) => item.latitude && item.longitude);
    return punch ? `https://www.google.com/maps?q=${punch.latitude},${punch.longitude}` : null;
};

const captureFor = (day) => (day.punches || []).find((item) => item.capture_url)?.capture_url || null;

// --- Drawer ---

const drawerVisible = ref(false);
const drawerLoading = ref(false);
const selectedRow = ref(null);
const detailDays = ref([]);
const weeklySchedule = ref([]);
const detailTab = ref('days');

const openDetail = async (row) => {
    selectedRow.value = row;
    drawerVisible.value = true;
    detailTab.value = 'days';
    await loadDetail();
};

const loadDetail = async () => {
    if (!selectedRow.value) return;

    drawerLoading.value = true;

    try {
        const { data } = await axios.get(route('payroll.periods.days', [props.period.id, selectedRow.value.user_id]));
        detailDays.value = data.days;
        weeklySchedule.value = data.weekly_schedule;
    } catch {
        ElMessage.error('No se pudo cargar el detalle del colaborador.');
    } finally {
        drawerLoading.value = false;
    }
};

const refreshAll = () => {
    router.reload({ only: ['rows', 'stats', 'adjustments'], onSuccess: () => loadDetail() });
};

// --- Punch editing ---

const punchDialog = ref(false);
const editingPunch = ref(null);
const punchForm = useForm({ punched_at: null, type: null, edit_reason: '' });

const openPunchDialog = (punch) => {
    editingPunch.value = punch;
    punchForm.clearErrors();
    punchForm.punched_at = punch.punched_at;
    punchForm.type = punch.type;
    punchForm.edit_reason = '';
    punchDialog.value = true;
};

const savePunch = () => {
    punchForm.put(route('payroll.attendance-logs.update', editingPunch.value.id), {
        onSuccess: () => {
            punchDialog.value = false;
            refreshAll();
        },
    });
};

const deletePunch = (punch) => {
    ElMessageBox.confirm('¿Eliminar este marcaje?', 'Eliminar marcaje', {
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
        type: 'warning',
    }).then(() => {
        punchForm.delete(route('payroll.attendance-logs.destroy', punch.id), {
            onSuccess: () => refreshAll(),
        });
    }).catch(() => {});
};

const addPunchDialog = ref(false);
const addPunchForm = useForm({ user_id: null, type: 'check_in', punched_at: null, edit_reason: '' });

const openAddPunchDialog = () => {
    addPunchForm.clearErrors();
    addPunchForm.user_id = selectedRow.value.user_id;
    addPunchForm.type = 'check_in';
    addPunchForm.punched_at = null;
    addPunchForm.edit_reason = '';
    addPunchDialog.value = true;
};

const saveNewPunch = () => {
    addPunchForm.post(route('payroll.attendance-logs.store'), {
        onSuccess: () => {
            addPunchDialog.value = false;
            refreshAll();
        },
    });
};

// --- Day overrides ---

const toggleLate = (day) => {
    punchForm
        .transform(() => ({
            date: day.date,
            late_ignored: day.late_ignored,
            notes: day.notes,
        }))
        .put(route('payroll.periods.override', [props.period.id, selectedRow.value.user_id]), {
            preserveScroll: true,
            onSuccess: () => loadDetail(),
        });
};

// --- Capture / location dialogs ---

const captureDialog = ref(false);
const captureUrl = ref(null);

const viewCapture = (day) => {
    captureUrl.value = captureFor(day);
    captureDialog.value = true;
};

// --- Incidents ---

const incidentDialog = ref(false);
const incidentForm = useForm({ user_id: null, type: 'absence_justified', start_date: null, end_date: null, notes: '' });

const openIncidentDialog = (day = null) => {
    incidentForm.clearErrors();
    incidentForm.user_id = selectedRow.value.user_id;
    incidentForm.type = 'absence_justified';
    incidentForm.start_date = day?.date || null;
    incidentForm.end_date = day?.date || null;
    incidentForm.notes = '';
    incidentDialog.value = true;
};

const saveIncident = () => {
    incidentForm.post(route('payroll.incidents.store'), {
        onSuccess: () => {
            incidentDialog.value = false;
            refreshAll();
        },
    });
};

// --- Adjustments ---

const adjustmentsDialog = ref(false);
const adjustmentForm = useForm({ user_id: null, type: 'earning', concept: '', amount: null, notes: '' });

const userAdjustments = computed(() =>
    (props.adjustments || []).filter((adjustment) => adjustment.user_id === selectedRow.value?.user_id)
);

const openAdjustmentsDialog = (row) => {
    selectedRow.value = row;
    adjustmentForm.clearErrors();
    adjustmentForm.user_id = row.user_id;
    adjustmentForm.type = 'earning';
    adjustmentForm.concept = '';
    adjustmentForm.amount = null;
    adjustmentForm.notes = '';
    adjustmentsDialog.value = true;
};

const saveAdjustment = () => {
    adjustmentForm.post(route('payroll.periods.adjustments.store', props.period.id), {
        onSuccess: () => {
            adjustmentForm.reset('concept', 'amount', 'notes');
            router.reload({ only: ['adjustments', 'rows', 'stats'] });
        },
    });
};

const deleteAdjustment = (adjustment) => {
    adjustmentForm.delete(route('payroll.adjustments.destroy', adjustment.id), {
        onSuccess: () => router.reload({ only: ['adjustments', 'rows', 'stats'] }),
    });
};

// --- Close / reopen / export ---

const closePeriod = () => {
    ElMessageBox.confirm(
        'Al cerrar el periodo se generan los recibos y se registra el gasto de nómina. ¿Continuar?',
        'Cerrar periodo',
        { confirmButtonText: 'Cerrar periodo', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        punchForm.transform((data) => data).post(route('payroll.periods.close', props.period.id), {
            onSuccess: () => router.reload(),
        });
    }).catch(() => {});
};

const reopenPeriod = () => {
    ElMessageBox.confirm(
        'Se eliminarán los recibos y el gasto del periodo para poder editar la pre-nómina. ¿Continuar?',
        'Reabrir periodo',
        { confirmButtonText: 'Reabrir', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        punchForm.transform((data) => data).post(route('payroll.periods.reopen', props.period.id), {
            onSuccess: () => router.reload(),
        });
    }).catch(() => {});
};

const exportPeriod = () => {
    window.location.href = route('payroll.periods.export', props.period.id);
};

const openPayslips = () => {
    window.open(route('payroll.periods.payslips.print', props.period.id), '_blank');
};
</script>

<template>
    <AppLayout :title="`Nómina ${formatDate(period.start_date)}`">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link :href="route('payroll.periods.index')">
                        <el-button :icon="Back" circle plain />
                    </Link>
                    <div>
                        <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">
                            Nómina {{ formatDate(period.start_date) }} — {{ formatDate(period.end_date) }}
                        </h2>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ typeLabels[period.type] }} ·
                            <el-tag :type="period.status === 'open' ? 'warning' : 'success'" size="small" effect="plain" class="ml-1">
                                {{ period.status === 'open' ? 'Abierto (pre-nómina en tiempo real)' : 'Cerrado' }}
                            </el-tag>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <el-button :icon="Document" @click="openPayslips">Recibos</el-button>
                    <el-button :icon="Download" @click="exportPeriod">Exportar</el-button>
                    <el-button v-if="canClose && period.status === 'open'" type="primary" color="#f26c17" :icon="Lock" @click="closePeriod">
                        Cerrar periodo
                    </el-button>
                    <el-button v-if="canClose && period.status === 'closed'" type="warning" plain :icon="Unlock" @click="reopenPeriod">
                        Reabrir periodo
                    </el-button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-4">
                <div class="bg-white dark:bg-[#1e1e20] rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Colaboradores</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ stats.employees }}</p>
                </div>
                <div class="bg-white dark:bg-[#1e1e20] rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Percepciones</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ money(stats.total_gross) }}</p>
                </div>
                <div class="bg-white dark:bg-[#1e1e20] rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Deducciones</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ money(stats.total_deductions) }}</p>
                </div>
                <div class="bg-white dark:bg-[#1e1e20] rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Neto</p>
                    <p class="text-xl font-bold text-emerald-600">{{ money(stats.total_net) }}</p>
                </div>
                <div class="bg-white dark:bg-[#1e1e20] rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Horas extra</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ stats.overtime_hours }}</p>
                </div>
                <div class="bg-white dark:bg-[#1e1e20] rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Retardos (min)</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ stats.late_minutes }}</p>
                </div>
                <div class="bg-white dark:bg-[#1e1e20] rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-4">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold">Días no pagados</p>
                    <p class="text-xl font-bold text-red-500">{{ stats.unpaid_days }}</p>
                </div>
            </div>

            <!-- Employees table -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-table :data="rows" style="width: 100%" stripe>
                    <el-table-column label="Colaborador" min-width="200">
                        <template #default="scope">
                            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ scope.row.name }}</span>
                            <div class="text-xs text-gray-500">
                                {{ scope.row.employee_number || 'Sin número' }}
                                <template v-if="scope.row.department"> · {{ scope.row.department }}</template>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Días pagados" width="115" align="center">
                        <template #default="scope">{{ scope.row.days_paid }}</template>
                    </el-table-column>

                    <el-table-column label="No pagados" width="110" align="center">
                        <template #default="scope">
                            <span :class="{ 'text-red-500 font-semibold': scope.row.unpaid_days > 0 }">{{ scope.row.unpaid_days }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Retardos" width="100" align="center">
                        <template #default="scope">{{ scope.row.late_minutes }} min</template>
                    </el-table-column>

                    <el-table-column label="Extra" width="100" align="center">
                        <template #default="scope">{{ hours(scope.row.overtime_minutes) }}</template>
                    </el-table-column>

                    <el-table-column label="Vacaciones" width="110" align="center">
                        <template #default="scope">{{ scope.row.vacation_days }}</template>
                    </el-table-column>

                    <el-table-column label="Incapacidad" width="110" align="center">
                        <template #default="scope">{{ scope.row.incapacity_days }}</template>
                    </el-table-column>

                    <el-table-column label="Ajustes" width="130" align="right">
                        <template #default="scope">
                            <span class="text-xs">
                                +{{ money(scope.row.adjustments_earnings) }} / -{{ money(scope.row.adjustments_deductions) }}
                            </span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Neto" width="130" align="right">
                        <template #default="scope">
                            <span class="font-semibold">{{ money(scope.row.total_net) }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="" width="190" align="right">
                        <template #default="scope">
                            <el-button size="small" :icon="View" @click="openDetail(scope.row)">Detalle</el-button>
                            <el-button v-if="canManage && period.status === 'open'" size="small" plain class="!ml-2" @click="openAdjustmentsDialog(scope.row)">
                                Ajustes
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-if="rows.length === 0" class="text-center py-12">
                    <el-empty description="Sin colaboradores sujetos a nómina" :image-size="100" />
                </div>
            </div>
        </div>

        <!-- Employee detail drawer -->
        <el-drawer v-model="drawerVisible" :title="selectedRow ? `Detalle de ${selectedRow.name}` : 'Detalle'" size="62%">
            <div v-loading="drawerLoading" class="space-y-4">
                <div class="flex items-center justify-between">
                    <el-radio-group v-model="detailTab" size="small">
                        <el-radio-button value="days">Días y marcajes</el-radio-button>
                        <el-radio-button value="schedule">Horario semanal</el-radio-button>
                    </el-radio-group>

                    <div v-if="canManage && period.status === 'open'" class="flex gap-2">
                        <el-button size="small" :icon="Plus" @click="openAddPunchDialog">Agregar marcaje</el-button>
                        <el-button size="small" :icon="Plus" plain @click="openIncidentDialog()">Agregar incidencia</el-button>
                    </div>
                </div>

                <template v-if="detailTab === 'days'">
                    <el-table :data="detailDays" style="width: 100%" size="small" stripe>
                        <el-table-column label="Fecha" width="110">
                            <template #default="scope">{{ formatDate(scope.row.date) }}</template>
                        </el-table-column>

                        <el-table-column label="Estatus" width="130">
                            <template #default="scope">
                                <el-tag :type="statusTagType(scope.row.status)" size="small" effect="plain">
                                    {{ scope.row.status_label || statusLabels[scope.row.status] || scope.row.status }}
                                </el-tag>
                            </template>
                        </el-table-column>

                        <el-table-column label="Entrada" width="70" align="center">
                            <template #default="scope">{{ scope.row.first_in || '—' }}</template>
                        </el-table-column>

                        <el-table-column label="Comida" width="110" align="center">
                            <template #default="scope">
                                {{ scope.row.lunch_start || '—' }} – {{ scope.row.lunch_end || '—' }}
                            </template>
                        </el-table-column>

                        <el-table-column label="Salida" width="70" align="center">
                            <template #default="scope">{{ scope.row.last_out || '—' }}</template>
                        </el-table-column>

                        <el-table-column label="Trabajado" width="90" align="center">
                            <template #default="scope">{{ hours(scope.row.worked_minutes) }}</template>
                        </el-table-column>

                        <el-table-column label="Retardo" width="110" align="center">
                            <template #default="scope">
                                <span v-if="scope.row.late_minutes > 0" :class="{ 'text-red-500': !scope.row.late_ignored }">
                                    {{ scope.row.late_minutes }} min
                                    <el-tag v-if="scope.row.late_ignored" size="small" type="success" effect="plain" class="ml-1">Ignorado</el-tag>
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </template>
                        </el-table-column>

                        <el-table-column label="Extra" width="70" align="center">
                            <template #default="scope">{{ scope.row.overtime_minutes }} min</template>
                        </el-table-column>

                        <el-table-column label="Evidencia" width="110" align="center">
                            <template #default="scope">
                                <el-button v-if="captureFor(scope.row)" size="small" text :icon="Picture" @click="viewCapture(scope.row)">Foto</el-button>
                                <a v-if="mapsLink(scope.row)" :href="mapsLink(scope.row)" target="_blank" class="ml-2 text-primary">
                                    <el-icon><Location /></el-icon>
                                </a>
                            </template>
                        </el-table-column>

                        <el-table-column label="" width="90" align="right">
                            <template #default="scope">
                                <el-button
                                    v-if="canManage && period.status === 'open'"
                                    size="small"
                                    text
                                    :icon="Edit"
                                    @click="scope.row.punches?.[0] ? openPunchDialog(scope.row.punches[0]) : null"
                                />
                            </template>
                        </el-table-column>
                    </el-table>

                    <!-- Punches of the selected day are shown inline below -->
                    <div v-for="day in detailDays" :key="day.date" class="mt-2">
                        <div v-if="day.punches && day.punches.length" class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                            <span class="font-semibold text-gray-600 dark:text-gray-300">{{ formatDate(day.date) }}:</span>
                            <span v-for="punch in day.punches" :key="punch.id" class="flex items-center gap-1 bg-gray-50 dark:bg-[#252529] border border-gray-100 dark:border-[#2b2b2e] rounded px-2 py-1">
                                <el-icon><Clock /></el-icon>
                                {{ punch.type_label }} {{ punch.time }}
                                <template v-if="punch.device"> · {{ punch.device }}</template>
                                <template v-if="punch.edited"> · editado</template>
                                <template v-if="punch.identifier_method === 'pin'"> · PIN</template>
                                <template v-if="punch.identifier_method === 'face'"> · rostro</template>
                                <template v-if="canManage && period.status === 'open'">
                                    <el-button size="small" text :icon="Edit" @click="openPunchDialog(punch)" />
                                    <el-button size="small" text type="danger" :icon="Delete" @click="deletePunch(punch)" />
                                </template>
                            </span>
                        </div>
                        <div v-if="canManage && period.status === 'open' && day.late_minutes > 0" class="text-xs mt-1">
                            <el-checkbox :model-value="day.late_ignored" @change="(value) => { day.late_ignored = value; toggleLate(day); }">
                                Ignorar retardo del {{ formatDate(day.date) }}
                            </el-checkbox>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <el-table :data="weeklySchedule" style="width: 100%" size="small" stripe>
                        <el-table-column label="Día" width="120">
                            <template #default="scope">
                                <span :class="{ 'font-semibold': scope.row.workday }">{{ scope.row.label }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="Turno" min-width="160">
                            <template #default="scope">{{ scope.row.shift || 'Sin turno asignado' }}</template>
                        </el-table-column>
                        <el-table-column label="Horario" width="140" align="center">
                            <template #default="scope">
                                <template v-if="scope.row.flexible">{{ hours(scope.row.expected_minutes) }} h flexibles</template>
                                <template v-else-if="scope.row.start">{{ scope.row.start }} – {{ scope.row.end }}</template>
                                <template v-else>—</template>
                            </template>
                        </el-table-column>
                        <el-table-column label="Laborable" width="100" align="center">
                            <template #default="scope">
                                <el-tag :type="scope.row.workday ? 'success' : 'info'" size="small" effect="plain">
                                    {{ scope.row.workday ? 'Sí' : 'Descanso' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </template>
            </div>
        </el-drawer>

        <!-- Punch edit dialog -->
        <el-dialog v-model="punchDialog" title="Editar marcaje" width="460px" top="12vh">
            <el-form :model="punchForm" label-position="top" size="default">
                <el-form-item label="Fecha y hora" required :error="punchForm.errors.punched_at">
                    <el-date-picker v-model="punchForm.punched_at" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" format="DD/MM/YYYY HH:mm" class="w-full" />
                </el-form-item>
                <el-form-item label="Tipo de marcaje" :error="punchForm.errors.type">
                    <el-select v-model="punchForm.type" class="w-full">
                        <el-option label="Entrada" value="check_in" />
                        <el-option label="Inicio de comida" value="lunch_start" />
                        <el-option label="Fin de comida" value="lunch_end" />
                        <el-option label="Permiso / salida" value="break_start" />
                        <el-option label="Regreso de permiso" value="break_end" />
                        <el-option label="Salida" value="check_out" />
                    </el-select>
                </el-form-item>
                <el-form-item label="Motivo del cambio" required :error="punchForm.errors.edit_reason">
                    <el-input v-model="punchForm.edit_reason" maxlength="255" placeholder="Quedará registrado en la auditoría" />
                </el-form-item>
            </el-form>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="punchDialog = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="punchForm.processing" @click="savePunch">Guardar</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Add punch dialog -->
        <el-dialog v-model="addPunchDialog" title="Agregar marcaje manual" width="460px" top="12vh">
            <el-form :model="addPunchForm" label-position="top" size="default">
                <el-form-item label="Tipo de marcaje" required :error="addPunchForm.errors.type">
                    <el-select v-model="addPunchForm.type" class="w-full">
                        <el-option label="Entrada" value="check_in" />
                        <el-option label="Inicio de comida" value="lunch_start" />
                        <el-option label="Fin de comida" value="lunch_end" />
                        <el-option label="Permiso / salida" value="break_start" />
                        <el-option label="Regreso de permiso" value="break_end" />
                        <el-option label="Salida" value="check_out" />
                    </el-select>
                </el-form-item>
                <el-form-item label="Fecha y hora" required :error="addPunchForm.errors.punched_at">
                    <el-date-picker v-model="addPunchForm.punched_at" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" format="DD/MM/YYYY HH:mm" class="w-full" />
                </el-form-item>
                <el-form-item label="Motivo" required :error="addPunchForm.errors.edit_reason">
                    <el-input v-model="addPunchForm.edit_reason" maxlength="255" placeholder="Quedará registrado en la auditoría" />
                </el-form-item>
            </el-form>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="addPunchDialog = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="addPunchForm.processing" @click="saveNewPunch">Registrar</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Incident dialog -->
        <el-dialog v-model="incidentDialog" title="Agregar incidencia" width="520px" top="10vh">
            <el-form :model="incidentForm" label-position="top" size="default">
                <el-form-item label="Tipo de incidencia" required :error="incidentForm.errors.type">
                    <el-select v-model="incidentForm.type" class="w-full">
                        <el-option label="Falta justificada" value="absence_justified" />
                        <el-option label="Falta injustificada" value="absence_unjustified" />
                        <el-option label="Incapacidad médica" value="medical_leave" />
                        <el-option label="Permiso con goce de sueldo" value="permission_paid" />
                        <el-option label="Permiso sin goce de sueldo" value="permission_unpaid" />
                        <el-option label="Vacaciones" value="vacation" />
                        <el-option label="Otro" value="other" />
                    </el-select>
                </el-form-item>
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Desde" required :error="incidentForm.errors.start_date">
                        <el-date-picker v-model="incidentForm.start_date" type="date" value-format="YYYY-MM-DD" class="w-full" />
                    </el-form-item>
                    <el-form-item label="Hasta" :error="incidentForm.errors.end_date">
                        <el-date-picker v-model="incidentForm.end_date" type="date" value-format="YYYY-MM-DD" class="w-full" />
                    </el-form-item>
                </div>
                <el-form-item label="Notas" :error="incidentForm.errors.notes">
                    <el-input v-model="incidentForm.notes" maxlength="255" />
                </el-form-item>
            </el-form>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="incidentDialog = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="incidentForm.processing" @click="saveIncident">Guardar</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Adjustments dialog -->
        <el-dialog v-model="adjustmentsDialog" title="Ajustes manuales" width="560px" top="10vh">
            <div class="space-y-4">
                <el-table :data="userAdjustments" size="small" style="width: 100%">
                    <el-table-column label="Tipo" width="110">
                        <template #default="scope">
                            <el-tag :type="scope.row.type === 'earning' ? 'success' : 'danger'" size="small" effect="plain">
                                {{ scope.row.type === 'earning' ? 'Percepción' : 'Deducción' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="Concepto" min-width="140">
                        <template #default="scope">{{ scope.row.concept }}</template>
                    </el-table-column>
                    <el-table-column label="Monto" width="110" align="right">
                        <template #default="scope">{{ money(scope.row.amount) }}</template>
                    </el-table-column>
                    <el-table-column label="" width="60" align="right">
                        <template #default="scope">
                            <el-button size="small" text type="danger" :icon="Delete" @click="deleteAdjustment(scope.row)" />
                        </template>
                    </el-table-column>
                </el-table>

                <el-form :model="adjustmentForm" label-position="top" size="default">
                    <div class="grid grid-cols-3 gap-4">
                        <el-form-item label="Tipo" :error="adjustmentForm.errors.type">
                            <el-select v-model="adjustmentForm.type" class="w-full">
                                <el-option label="Percepción" value="earning" />
                                <el-option label="Deducción" value="deduction" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="Concepto" required :error="adjustmentForm.errors.concept" class="col-span-2">
                            <el-input v-model="adjustmentForm.concept" maxlength="120" />
                        </el-form-item>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <el-form-item label="Monto" required :error="adjustmentForm.errors.amount">
                            <el-input-number v-model="adjustmentForm.amount" :min="0.01" :precision="2" :controls="false" style="width: 100%" />
                        </el-form-item>
                        <el-form-item label="Notas" :error="adjustmentForm.errors.notes" class="col-span-2">
                            <el-input v-model="adjustmentForm.notes" maxlength="255" />
                        </el-form-item>
                    </div>
                </el-form>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="adjustmentsDialog = false">Cerrar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="adjustmentForm.processing" @click="saveAdjustment">
                        Agregar ajuste
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Capture dialog -->
        <el-dialog v-model="captureDialog" title="Evidencia del marcaje" width="520px" top="8vh">
            <img v-if="captureUrl" :src="captureUrl" alt="Captura del marcaje" class="w-full rounded-lg" />
        </el-dialog>
    </AppLayout>
</template>
