<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessageBox } from 'element-plus';
import { Plus, Edit, Delete } from '@element-plus/icons-vue';
import { useFlashMessages } from '@/Composables/useFlashMessages';

const props = defineProps({
    shifts: Array,
    assignments: Array,
    users: Array,
    shiftTypes: Object,
    weekDays: Object,
    assignmentTypes: Object,
});

useFlashMessages();

const activeTab = ref('shifts');

const weekDayOptions = computed(() =>
    Object.entries(props.weekDays || {}).map(([value, label]) => ({ value: Number(value), label }))
);

const shiftTypeOptions = computed(() =>
    Object.entries(props.shiftTypes || {}).map(([value, label]) => ({ value, label }))
);

const assignmentTypeOptions = computed(() =>
    Object.entries(props.assignmentTypes || {}).map(([value, label]) => ({ value, label }))
);

const formatTime = (value) => (value ? String(value).substring(0, 5) : null);

// --- Shifts ---

const shiftDialog = ref(false);
const editingShift = ref(null);

const shiftForm = useForm({
    name: '',
    type: 'fixed',
    start_time: '09:00:00',
    end_time: '18:00:00',
    meal_minutes: 60,
    is_meal_paid: false,
    days: [1, 2, 3, 4, 5],
    required_daily_hours: 8,
    late_tolerance_minutes: null,
    is_active: true,
    description: '',
});

const openShiftDialog = (shift = null) => {
    editingShift.value = shift;
    shiftForm.clearErrors();

    if (shift) {
        shiftForm.name = shift.name;
        shiftForm.type = shift.type;
        shiftForm.start_time = shift.start_time;
        shiftForm.end_time = shift.end_time;
        shiftForm.meal_minutes = shift.meal_minutes;
        shiftForm.is_meal_paid = Boolean(shift.is_meal_paid);
        shiftForm.days = (shift.days || []).map(Number);
        shiftForm.required_daily_hours = shift.required_daily_hours ? Number(shift.required_daily_hours) : 8;
        shiftForm.late_tolerance_minutes = shift.late_tolerance_minutes;
        shiftForm.is_active = Boolean(shift.is_active);
        shiftForm.description = shift.description || '';
    } else {
        shiftForm.reset();
        shiftForm.days = [1, 2, 3, 4, 5];
    }

    shiftDialog.value = true;
};

const saveShift = () => {
    const options = {
        onSuccess: () => {
            shiftDialog.value = false;
        },
    };

    if (editingShift.value) {
        shiftForm.put(route('payroll.shifts.update', editingShift.value.id), options);
    } else {
        shiftForm.post(route('payroll.shifts.store'), options);
    }
};

const destroyShift = (shift) => {
    ElMessageBox.confirm(
        `¿Eliminar el turno "${shift.name}"?`,
        'Eliminar turno',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        shiftForm.delete(route('payroll.shifts.destroy', shift.id));
    }).catch(() => {});
};

// --- Assignments ---

const assignmentDialog = ref(false);
const targetType = ref('user');

const assignmentForm = useForm({
    user_id: null,
    department: '',
    type: 'fixed',
    shift_id: null,
    rotation: [],
    start_date: null,
    end_date: null,
    is_active: true,
    notes: '',
});

const openAssignmentDialog = () => {
    assignmentForm.reset();
    assignmentForm.clearErrors();
    targetType.value = 'user';
    assignmentDialog.value = true;
};

const saveAssignment = () => {
    if (targetType.value === 'user') {
        assignmentForm.department = '';
    } else {
        assignmentForm.user_id = null;
    }

    assignmentForm.post(route('payroll.shift-assignments.store'), {
        onSuccess: () => {
            assignmentDialog.value = false;
        },
    });
};

const destroyAssignment = (assignment) => {
    ElMessageBox.confirm(
        `¿Eliminar la asignación de "${assignment.target_label}"?`,
        'Eliminar asignación',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        assignmentForm.delete(route('payroll.shift-assignments.destroy', assignment.id));
    }).catch(() => {});
};
</script>

<template>
    <AppLayout title="Turnos y horarios">
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">Turnos y horarios</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Configura turnos fijos o flexibles y asígnalos a colaboradores o departamentos.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <el-button v-if="activeTab === 'shifts'" type="primary" color="#f26c17" :icon="Plus" @click="openShiftDialog()">
                        Nuevo turno
                    </el-button>
                    <el-button v-else type="primary" color="#f26c17" :icon="Plus" @click="openAssignmentDialog">
                        Nueva asignación
                    </el-button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-tabs v-model="activeTab" class="px-2 pt-2">
                    <!-- Shifts -->
                    <el-tab-pane name="shifts">
                        <template #label>
                            <span class="px-2">Turnos</span>
                        </template>

                        <el-table :data="shifts" style="width: 100%" stripe>
                            <el-table-column label="Turno" min-width="180">
                                <template #default="scope">
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ scope.row.name }}</span>
                                    <div class="text-xs text-gray-500">{{ scope.row.description || '—' }}</div>
                                </template>
                            </el-table-column>

                            <el-table-column label="Tipo" width="110" align="center">
                                <template #default="scope">
                                    <el-tag :type="scope.row.type === 'fixed' ? 'primary' : 'warning'" size="small" effect="plain">
                                        {{ shiftTypes[scope.row.type] }}
                                    </el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column label="Horario" min-width="150">
                                <template #default="scope">
                                    <template v-if="scope.row.type === 'fixed'">
                                        {{ formatTime(scope.row.start_time) }} – {{ formatTime(scope.row.end_time) }}
                                    </template>
                                    <template v-else>
                                        {{ Number(scope.row.required_daily_hours) }} h por día
                                    </template>
                                </template>
                            </el-table-column>

                            <el-table-column label="Días" min-width="200">
                                <template #default="scope">
                                    <div class="flex flex-wrap gap-1">
                                        <el-tag v-for="day in scope.row.days || []" :key="day" size="small" type="info" effect="plain">
                                            {{ weekDays[day] }}
                                        </el-tag>
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column label="Comida" width="130" align="center">
                                <template #default="scope">
                                    <span class="text-sm">{{ scope.row.meal_minutes }} min</span>
                                    <el-tag v-if="scope.row.is_meal_paid" size="small" type="success" effect="plain" class="ml-1">Pagada</el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column label="Tolerancia" width="110" align="center">
                                <template #default="scope">
                                    <span class="text-sm">
                                        {{ scope.row.late_tolerance_minutes !== null ? `${scope.row.late_tolerance_minutes} min` : 'Global' }}
                                    </span>
                                </template>
                            </el-table-column>

                            <el-table-column label="Asignaciones" width="120" align="center">
                                <template #default="scope">
                                    <el-tag size="small" type="info" effect="plain">{{ scope.row.assignments_count }}</el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column label="Estatus" width="110" align="center">
                                <template #default="scope">
                                    <el-tag :type="scope.row.is_active ? 'success' : 'danger'" size="small" effect="plain">
                                        {{ scope.row.is_active ? 'Activo' : 'Inactivo' }}
                                    </el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column label="" width="110" align="right">
                                <template #default="scope">
                                    <el-button :icon="Edit" circle plain size="small" @click="openShiftDialog(scope.row)" />
                                    <el-button :icon="Delete" circle plain size="small" type="danger" class="!ml-2" @click="destroyShift(scope.row)" />
                                </template>
                            </el-table-column>
                        </el-table>

                        <div v-if="shifts.length === 0" class="text-center py-12">
                            <el-empty description="Sin turnos registrados" :image-size="100">
                                <template #default>
                                    <p class="text-sm text-gray-500">Crea el primer turno para comenzar a asignar horarios.</p>
                                </template>
                            </el-empty>
                        </div>
                    </el-tab-pane>

                    <!-- Assignments -->
                    <el-tab-pane name="assignments">
                        <template #label>
                            <span class="px-2">Asignaciones</span>
                        </template>

                        <div class="px-4 pt-2 pb-4">
                            <el-alert
                                type="info"
                                :closable="false"
                                show-icon
                                title="Cómo se aplican las asignaciones"
                                description="La asignación individual tiene prioridad sobre la del departamento. En asignaciones rotativas, el turno cambia cada semana siguiendo el orden definido."
                            />
                        </div>

                        <el-table :data="assignments" style="width: 100%" stripe>
                            <el-table-column label="Asignado a" min-width="180">
                                <template #default="scope">
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ scope.row.target_label }}</span>
                                    <div class="text-xs text-gray-500">
                                        {{ scope.row.user_id ? 'Colaborador' : 'Departamento' }}
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column label="Tipo" width="110" align="center">
                                <template #default="scope">
                                    <el-tag :type="scope.row.type === 'fixed' ? 'primary' : 'warning'" size="small" effect="plain">
                                        {{ assignmentTypes[scope.row.type] }}
                                    </el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column label="Turno(s)" min-width="220">
                                <template #default="scope">
                                    <template v-if="scope.row.type === 'fixed'">
                                        {{ scope.row.shift_name || '—' }}
                                    </template>
                                    <template v-else>
                                        <div class="flex flex-wrap items-center gap-1">
                                            <template v-for="(name, index) in scope.row.rotation_labels" :key="index">
                                                <span v-if="index > 0" class="text-gray-400">→</span>
                                                <el-tag size="small" type="warning" effect="plain">{{ name }}</el-tag>
                                            </template>
                                        </div>
                                    </template>
                                </template>
                            </el-table-column>

                            <el-table-column label="Vigencia" min-width="200">
                                <template #default="scope">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ String(scope.row.start_date).substring(0, 10) }} —
                                        {{ scope.row.end_date ? String(scope.row.end_date).substring(0, 10) : 'Indefinida' }}
                                    </span>
                                </template>
                            </el-table-column>

                            <el-table-column label="Estatus" width="110" align="center">
                                <template #default="scope">
                                    <el-tag :type="scope.row.is_active ? 'success' : 'danger'" size="small" effect="plain">
                                        {{ scope.row.is_active ? 'Activa' : 'Inactiva' }}
                                    </el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column label="" width="80" align="right">
                                <template #default="scope">
                                    <el-button :icon="Delete" circle plain size="small" type="danger" @click="destroyAssignment(scope.row)" />
                                </template>
                            </el-table-column>
                        </el-table>

                        <div v-if="assignments.length === 0" class="text-center py-12">
                            <el-empty description="Sin asignaciones registradas" :image-size="100">
                                <template #default>
                                    <p class="text-sm text-gray-500">Asigna turnos a colaboradores o departamentos.</p>
                                </template>
                            </el-empty>
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </div>

        <!-- Shift dialog -->
        <el-dialog
            v-model="shiftDialog"
            :title="editingShift ? `Editar turno: ${editingShift.name}` : 'Nuevo turno'"
            width="640px"
            top="8vh"
        >
            <el-form :model="shiftForm" label-position="top" size="default">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Nombre" required :error="shiftForm.errors.name">
                        <el-input v-model="shiftForm.name" placeholder="Ej. Turno matutino" maxlength="120" />
                    </el-form-item>

                    <el-form-item label="Tipo de turno" required :error="shiftForm.errors.type">
                        <el-select v-model="shiftForm.type" class="w-full">
                            <el-option v-for="option in shiftTypeOptions" :key="option.value" :label="option.label" :value="option.value" />
                        </el-select>
                    </el-form-item>
                </div>

                <template v-if="shiftForm.type === 'fixed'">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <el-form-item label="Hora de entrada" required :error="shiftForm.errors.start_time">
                            <el-time-picker v-model="shiftForm.start_time" value-format="HH:mm:ss" format="HH:mm" placeholder="Entrada" class="w-full" />
                        </el-form-item>

                        <el-form-item label="Hora de salida" required :error="shiftForm.errors.end_time">
                            <el-time-picker v-model="shiftForm.end_time" value-format="HH:mm:ss" format="HH:mm" placeholder="Salida" class="w-full" />
                        </el-form-item>
                    </div>
                </template>

                <template v-else>
                    <el-form-item label="Horas diarias requeridas" required :error="shiftForm.errors.required_daily_hours">
                        <el-input-number v-model="shiftForm.required_daily_hours" :min="0.5" :max="24" :step="0.5" :precision="2" :controls="false" style="width: 100%" />
                    </el-form-item>
                </template>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Minutos de comida" :error="shiftForm.errors.meal_minutes">
                        <el-input-number v-model="shiftForm.meal_minutes" :min="0" :max="480" :controls="false" style="width: 100%" />
                    </el-form-item>

                    <el-form-item label="Comida pagada" :error="shiftForm.errors.is_meal_paid">
                        <el-switch v-model="shiftForm.is_meal_paid" />
                    </el-form-item>
                </div>

                <el-form-item label="Días de la semana" required :error="shiftForm.errors.days">
                    <el-checkbox-group v-model="shiftForm.days">
                        <el-checkbox v-for="day in weekDayOptions" :key="day.value" :value="day.value">
                            {{ day.label }}
                        </el-checkbox>
                    </el-checkbox-group>
                </el-form-item>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Tolerancia de retardo (min, opcional)" :error="shiftForm.errors.late_tolerance_minutes">
                        <el-input-number v-model="shiftForm.late_tolerance_minutes" :min="0" :max="120" :controls="false" placeholder="Usar la global" style="width: 100%" />
                    </el-form-item>

                    <el-form-item label="Turno activo" :error="shiftForm.errors.is_active">
                        <el-switch v-model="shiftForm.is_active" />
                    </el-form-item>
                </div>

                <el-form-item label="Descripción" :error="shiftForm.errors.description">
                    <el-input v-model="shiftForm.description" type="textarea" :rows="2" maxlength="255" placeholder="Notas opcionales" />
                </el-form-item>
            </el-form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="shiftDialog = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="shiftForm.processing" @click="saveShift">
                        {{ editingShift ? 'Guardar cambios' : 'Crear turno' }}
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Assignment dialog -->
        <el-dialog v-model="assignmentDialog" title="Nueva asignación de turno" width="640px" top="8vh">
            <el-form :model="assignmentForm" label-position="top" size="default">
                <el-form-item label="Asignar a" :error="assignmentForm.errors.user_id || assignmentForm.errors.department">
                    <el-radio-group v-model="targetType">
                        <el-radio-button value="user">Colaborador</el-radio-button>
                        <el-radio-button value="department">Departamento</el-radio-button>
                    </el-radio-group>
                </el-form-item>

                <el-form-item v-if="targetType === 'user'" label="Colaborador" required :error="assignmentForm.errors.user_id">
                    <el-select v-model="assignmentForm.user_id" filterable placeholder="Seleccionar colaborador" class="w-full">
                        <el-option v-for="user in users" :key="user.id" :label="user.name" :value="user.id" />
                    </el-select>
                    <p class="text-xs text-gray-400 mt-1">Solo aparecen colaboradores con asistencia habilitada.</p>
                </el-form-item>

                <el-form-item v-else label="Departamento" required :error="assignmentForm.errors.department">
                    <el-input v-model="assignmentForm.department" placeholder="Ej. Obras" maxlength="255" />
                </el-form-item>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Tipo de asignación" required :error="assignmentForm.errors.type">
                        <el-select v-model="assignmentForm.type" class="w-full">
                            <el-option v-for="option in assignmentTypeOptions" :key="option.value" :label="option.label" :value="option.value" />
                        </el-select>
                    </el-form-item>

                    <el-form-item v-if="assignmentForm.type === 'fixed'" label="Turno" required :error="assignmentForm.errors.shift_id">
                        <el-select v-model="assignmentForm.shift_id" placeholder="Seleccionar turno" class="w-full">
                            <el-option v-for="shift in shifts" :key="shift.id" :label="shift.name" :value="shift.id" />
                        </el-select>
                    </el-form-item>
                </div>

                <el-form-item v-if="assignmentForm.type === 'rotation'" label="Turnos de la rotación (en orden semanal)" required :error="assignmentForm.errors.rotation">
                    <el-select v-model="assignmentForm.rotation" multiple placeholder="Selecciona los turnos en el orden de rotación" class="w-full">
                        <el-option v-for="shift in shifts" :key="shift.id" :label="shift.name" :value="shift.id" />
                    </el-select>
                    <p class="text-xs text-gray-400 mt-1">El primer turno se aplica la primera semana y rota en el orden seleccionado.</p>
                </el-form-item>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Fecha de inicio" required :error="assignmentForm.errors.start_date">
                        <el-date-picker v-model="assignmentForm.start_date" type="date" value-format="YYYY-MM-DD" placeholder="Seleccionar fecha" class="w-full" />
                    </el-form-item>

                    <el-form-item label="Fecha final (opcional)" :error="assignmentForm.errors.end_date">
                        <el-date-picker v-model="assignmentForm.end_date" type="date" value-format="YYYY-MM-DD" placeholder="Indefinida" class="w-full" />
                    </el-form-item>
                </div>

                <el-form-item label="Notas" :error="assignmentForm.errors.notes">
                    <el-input v-model="assignmentForm.notes" maxlength="255" placeholder="Comentarios opcionales" />
                </el-form-item>
            </el-form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="assignmentDialog = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="assignmentForm.processing" @click="saveAssignment">
                        Guardar asignación
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </AppLayout>
</template>
