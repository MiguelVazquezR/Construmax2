<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, CircleCheck, CircleClose, InfoFilled } from '@element-plus/icons-vue';
import CalendarModeSelector from './Partials/CalendarModeSelector.vue';
import FieldWorkFormModal from './Partials/FieldWorkFormModal.vue';
import DayView from '@/Components/MyComponents/DayView.vue';
import WeekView from '@/Components/MyComponents/WeekView.vue';
import { usePermissions } from '@/Composables/usePermissions';

// ---------------------------------------------------------------------------
// Props (from CalendarController@index)
// ---------------------------------------------------------------------------

const props = defineProps({
    events: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    technicians: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
});

const { can } = usePermissions();
const currentUser = usePage().props.auth.user;

// ---------------------------------------------------------------------------
// Permissions
// ---------------------------------------------------------------------------

const canViewFieldWork = computed(() => can('tickets.calendar.view'));
const canCreateFieldWork = computed(() => can('tickets.calendar.create'));

// ---------------------------------------------------------------------------
// Calendar mode & view state
// ---------------------------------------------------------------------------

const calendarMode = ref('personal');     // 'personal' | 'field-work'
const viewMode = ref('month');            // 'day' | 'week' | 'month'
const currentDate = ref(new Date());

// ---------------------------------------------------------------------------
// Field work events (loaded via API)
// ---------------------------------------------------------------------------

const fieldWorkEvents = ref([]);
const loadingFieldWork = ref(false);

// ---------------------------------------------------------------------------
// Filters (field work calendar)
// ---------------------------------------------------------------------------

const filterTechnician = ref(null);
const filterBranch = ref(null);
const filterIsInternal = ref(null); // null = all, true = internal, false = external

async function fetchFieldWorkEvents() {
    loadingFieldWork.value = true;
    try {
        const params = new URLSearchParams();
        if (filterTechnician.value) params.set('technician_id', filterTechnician.value);
        if (filterBranch.value) params.set('branch_id', filterBranch.value);
        if (filterIsInternal.value !== null) params.set('is_internal', filterIsInternal.value ? '1' : '0');

        const url = '/field-work/events' + (params.toString() ? '?' + params.toString() : '');
        const resp = await fetch(url, {
            headers: { 'Accept': 'application/json' },
        });
        fieldWorkEvents.value = await resp.json();
    } catch {
        // Silently fail — user can retry
    } finally {
        loadingFieldWork.value = false;
    }
}

onMounted(fetchFieldWorkEvents);
watch(calendarMode, (mode) => {
    if (mode === 'field-work') fetchFieldWorkEvents();
});

// Re-fetch when filters change
watch([filterTechnician, filterBranch, filterIsInternal], () => {
    if (calendarMode.value === 'field-work') {
        fetchFieldWorkEvents();
    }
});

// ---------------------------------------------------------------------------
// Events for current mode (normalised for Day/Week/Month views)
// ---------------------------------------------------------------------------

const activeEvents = computed(() => {
    if (calendarMode.value === 'field-work') {
        return fieldWorkEvents.value.map((e) => ({
            ...e,
            _completed: ['Ejecutado', 'Facturado', 'Pagado'].includes(e.ticket_status),
        }));
    }
    return props.events.map((e) => ({
        id: e.id,
        title: e.title,
        start_time: e.start,
        end_time: e.end,
        is_all_day: false,
        description: e.description,
        color: null,
        _type: e.type,
        _is_completed: e.is_completed,
        _is_creator: e.is_creator,
        _my_status: e.my_status,
        _creator: e.creator,
        _participants: e.participants,
    }));
});

// ---------------------------------------------------------------------------
// Field Work modal state
// ---------------------------------------------------------------------------

const showFieldWorkModal = ref(false);
const editingSchedule = ref(null);
const prefilledDate = ref('');
const prefilledTime = ref('');

// ---------------------------------------------------------------------------
// Personal calendar event modal
// ---------------------------------------------------------------------------

const showPersonalModal = ref(false);
const isCreating = ref(false);
const activeEvent = ref(null);

const personalForm = useForm({
    id: null,
    title: '',
    type: 'Reunión',
    description: '',
    start_time: '',
    end_time: '',
    participants: [],
});

const types = ['Reunión', 'Tarea', 'Llamada', 'Recordatorio', 'Evento'];

// ---------------------------------------------------------------------------
// Day/Week view event handlers
// ---------------------------------------------------------------------------

function onDayWeekCreate(payload) {
    if (calendarMode.value === 'field-work') {
        if (!canCreateFieldWork.value) {
            ElMessage.warning('No tienes permiso para agendar trabajos en campo.');
            return;
        }
        prefilledDate.value = payload.date;
        prefilledTime.value = payload.time;
        editingSchedule.value = null;
        showFieldWorkModal.value = true;
    } else {
        isCreating.value = true;
        activeEvent.value = null;
        personalForm.reset();
        personalForm.start_time = `${payload.date} ${payload.time}:00`;
        const [h, m] = payload.time.split(':').map(Number);
        const endH = h + 1;
        personalForm.end_time = `${payload.date} ${String(endH).padStart(2, '0')}:${String(m).padStart(2, '0')}:00`;
        showPersonalModal.value = true;
    }
}

function onDayWeekEdit(event) {
    if (calendarMode.value === 'field-work') {
        editingSchedule.value = event;
        showFieldWorkModal.value = true;
    } else {
        isCreating.value = false;
        const found = props.events.find((e) => e.id === event.id);
        if (!found) return;
        activeEvent.value = found;
        personalForm.id = found.id;
        personalForm.title = found.title;
        personalForm.type = found.type;
        personalForm.description = found.description;
        personalForm.start_time = found.start;
        personalForm.end_time = found.end;
        personalForm.participants = (found.participants || []).map((p) => p.id);
        showPersonalModal.value = true;
    }
}

function onDayWeekDelete(event) {
    if (calendarMode.value === 'field-work') {
        // Guard: only the creator can delete
        const isOwner = currentUser?.id === event.user_id;
        if (!isOwner) {
            ElMessage.warning('Solo puedes eliminar tus propias agendas.');
            return;
        }

        ElMessageBox.confirm(
            '¿Eliminar esta agenda de trabajo en campo? Las fechas de las tareas se limpiarán.',
            'Confirmar eliminación',
            { type: 'warning', confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar' },
        ).then(() => {
            router.delete(route('field-work.destroy', event.id), {
                onSuccess: () => {
                    ElMessage.success('Agenda de trabajo en campo eliminada.');
                    fetchFieldWorkEvents();
                },
            });
        }).catch(() => {});
    }
}

function onDateChange(newDate) {
    currentDate.value = newDate;
}

function onFieldWorkSaved() {
    fetchFieldWorkEvents();
}

// ---------------------------------------------------------------------------
// Month view: event helpers
// ---------------------------------------------------------------------------

const selectedDate = ref(new Date());

const getEventsForDate = (date) => {
    const y = date.getFullYear();
    const mo = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    const dateStr = `${y}-${mo}-${d}`;

    return activeEvents.value.filter((e) => {
        const start = (e.start_time || e.start || '').substring(0, 10);
        const end = (e.end_time || e.end || start).substring(0, 10);
        return start <= dateStr && end >= dateStr;
    });
};

const handleDateClick = (data) => {
    if (calendarMode.value === 'field-work') {
        if (!canCreateFieldWork.value) return;
        const d = data.date;
        const dateStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        prefilledDate.value = dateStr;
        prefilledTime.value = '09:00';
        editingSchedule.value = null;
        showFieldWorkModal.value = true;
        return;
    }

    isCreating.value = true;
    activeEvent.value = null;
    const start = new Date(data.date);
    start.setHours(9, 0, 0);
    const end = new Date(data.date);
    end.setHours(10, 0, 0);
    personalForm.reset();
    personalForm.start_time = formatDateToString(start);
    personalForm.end_time = formatDateToString(end);
    showPersonalModal.value = true;
};

const formatDateToString = (date) => {
    const y = date.getFullYear();
    const mo = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    const h = String(date.getHours()).padStart(2, '0');
    const mi = String(date.getMinutes()).padStart(2, '0');
    return `${y}-${mo}-${d} ${h}:${mi}:00`;
};

const handleEventClick = (event, e) => {
    e.stopPropagation();

    if (calendarMode.value === 'field-work') {
        editingSchedule.value = event;
        showFieldWorkModal.value = true;
        return;
    }

    isCreating.value = false;
    activeEvent.value = event;
    personalForm.id = event.id;
    personalForm.title = event.title;
    personalForm.type = event.type;
    personalForm.description = event.description;
    personalForm.start_time = event.start;
    personalForm.end_time = event.end;
    personalForm.participants = (event.participants || []).map((p) => p.id);
    showPersonalModal.value = true;
};

// ---------------------------------------------------------------------------
// Personal CRUD
// ---------------------------------------------------------------------------

const submitPersonalEvent = () => {
    if (isCreating.value) {
        personalForm.post(route('calendar.store'), {
            onSuccess: () => {
                showPersonalModal.value = false;
                ElMessage.success('Evento creado correctamente.');
            },
        });
    } else {
        personalForm.put(route('calendar.update', personalForm.id), {
            onSuccess: () => {
                showPersonalModal.value = false;
                ElMessage.success('Evento actualizado correctamente.');
            },
        });
    }
};

const deletePersonalEvent = () => {
    ElMessageBox.confirm('¿Eliminar este evento?', 'Confirmar', { type: 'warning' })
        .then(() => {
            router.delete(route('calendar.destroy', activeEvent.value.id), {
                onSuccess: () => {
                    showPersonalModal.value = false;
                    ElMessage.success('Evento eliminado correctamente.');
                },
            });
        })
        .catch(() => {});
};

const toggleComplete = () => {
    router.put(route('calendar.toggle-complete', activeEvent.value.id), {}, {
        onSuccess: () => { showPersonalModal.value = false; },
    });
};

const responseForm = useForm({ status: '', rejection_reason: '' });

const submitResponse = (status) => {
    if (status === 'Rechazado') {
        ElMessageBox.prompt('Motivo del rechazo:', 'Rechazar invitación', {
            confirmButtonText: 'Rechazar',
            cancelButtonText: 'Cancelar',
        }).then(({ value }) => {
            responseForm.status = 'Rechazado';
            responseForm.rejection_reason = value || 'Sin motivo especificado';
            sendResponse();
        }).catch(() => {});
    } else {
        responseForm.status = 'Aceptado';
        responseForm.rejection_reason = null;
        sendResponse();
    }
};

const sendResponse = () => {
    responseForm.put(route('calendar.respond', activeEvent.value.id), {
        onSuccess: () => {
            showPersonalModal.value = false;
            ElMessage.success('Respuesta enviada.');
        },
    });
};

// ---------------------------------------------------------------------------
// Styling helpers
// ---------------------------------------------------------------------------

const getEventColorClass = (event) => {
    if (event.is_completed) return 'bg-gray-100 text-gray-400 border-gray-200 line-through decoration-gray-400';
    if (event.is_creator) return 'bg-blue-100 text-blue-700 border-blue-200';
    if (event.my_status === 'Aceptado') return 'bg-green-100 text-green-700 border-green-200';
    if (event.my_status === 'Rechazado') return 'bg-red-50 text-red-400 border-red-100 line-through opacity-60';
    return 'bg-orange-100 text-orange-700 border-orange-200';
};

const getFieldWorkEventStyle = (event) => {
    if (event._completed) {
        return {
            backgroundColor: '#9ca3af20',
            borderLeftColor: '#9ca3af',
            color: '#6b7280',
        };
    }
    return {
        backgroundColor: (event.color || '#409EFF') + '20',
        borderLeftColor: event.color || '#409EFF',
        color: event.color || '#409EFF',
    };
};

const formatTime = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr.replace(/-/g, '/'));
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

function openFieldWorkCreate() {
    editingSchedule.value = null;
    prefilledDate.value = '';
    prefilledTime.value = '';
    showFieldWorkModal.value = true;
}
</script>

<template>
    <AppLayout :title="calendarMode === 'field-work' ? 'Calendario de trabajo en campo' : 'Mi calendario'">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                    {{ calendarMode === 'field-work' ? 'Calendario de trabajo en campo' : 'Mi calendario' }}
                </h2>
                <el-button
                    v-if="calendarMode === 'field-work' && canCreateFieldWork"
                    type="primary"
                    color="#f26c17"
                    :icon="Plus"
                    @click="openFieldWorkCreate"
                >
                    Agendar trabajo en campo
                </el-button>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-1 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg p-4 sm:p-6">

                <!-- Mode selector + View toggle -->
                <CalendarModeSelector
                    v-model="calendarMode"
                    v-model:viewMode="viewMode"
                    :can-view-field-work="canViewFieldWork"
                />

                <!-- Field work filters -->
                <div
                    v-if="calendarMode === 'field-work'"
                    class="mb-4"
                >
                    <div class="grid grid-cols-3 gap-3">
                        <el-select
                            v-model="filterTechnician"
                            placeholder="Todos los técnicos"
                            clearable
                            size="default"
                        >
                            <el-option
                                v-for="t in technicians"
                                :key="t.id"
                                :label="t.name"
                                :value="t.id"
                            />
                        </el-select>

                        <el-select
                            v-model="filterBranch"
                            placeholder="Todas las sucursales"
                            clearable
                            filterable
                            size="default"
                        >
                            <el-option
                                v-for="b in branches"
                                :key="b.id"
                                :label="`${b.branch_name} — ${b.customer_name}`"
                                :value="b.id"
                            />
                        </el-select>

                        <el-select
                            v-model="filterIsInternal"
                            placeholder="Todos"
                            clearable
                            size="default"
                        >
                            <el-option label="Interno" :value="true" />
                            <el-option label="Externo" :value="false" />
                        </el-select>
                    </div>

                    <div class="flex items-center justify-between mt-2">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ loadingFieldWork ? 'Cargando...' : `mostrando ${activeEvents.length} evento${activeEvents.length !== 1 ? 's' : ''}` }}
                        </span>
                    </div>
                </div>

                <!-- Day View -->
                <div v-if="viewMode === 'day'" class="h-[calc(100vh-16.5rem)] min-h-[400px]">
                    <DayView
                        :events="activeEvents"
                        :current-date="currentDate"
                        @create="onDayWeekCreate"
                        @edit="onDayWeekEdit"
                        @delete="onDayWeekDelete"
                        @date-change="onDateChange"
                    />
                </div>

                <!-- Week View -->
                <div v-if="viewMode === 'week'" class="h-[calc(100vh-16.5rem)] min-h-[400px]">
                    <WeekView
                        :events="activeEvents"
                        :current-date="currentDate"
                        @create="onDayWeekCreate"
                        @edit="onDayWeekEdit"
                        @delete="onDayWeekDelete"
                        @date-change="onDateChange"
                    />
                </div>

                <!-- Month View -->
                <div v-if="viewMode === 'month'">
                    <div v-if="calendarMode === 'personal'" class="flex flex-wrap gap-4 mb-4 text-xs">
                        <span class="flex items-center gap-1 font-semibold text-gray-500 mr-4">Eventos:</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 bg-blue-100 border border-blue-300 rounded" /> Mis eventos</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 bg-orange-100 border border-orange-300 rounded" /> Invitación pendiente</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 bg-green-100 border border-green-300 rounded" /> Aceptada</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 bg-gray-200 border border-gray-400 rounded" /> Completados</span>
                    </div>

                    <el-calendar v-model="selectedDate">
                        <template #date-cell="{ data }">
                            <div
                                class="h-full w-full flex flex-col gap-1 overflow-hidden"
                                @click="handleDateClick(data)"
                            >
                                <span
                                    class="text-sm font-bold"
                                    :class="data.isSelected ? 'text-primary' : 'text-gray-700 dark:text-gray-300'"
                                >
                                    {{ data.date.getDate() }}
                                </span>
                                <div class="flex flex-col gap-1 overflow-y-auto custom-scrollbar pr-1">
                                    <template v-if="calendarMode === 'field-work'">
                                        <div
                                            v-for="ev in getEventsForDate(data.date)"
                                            :key="'fw-' + ev.id"
                                            class="text-[10px] px-1.5 py-0.5 rounded border-l-2 truncate cursor-pointer hover:opacity-80 transition"
                                            :style="getFieldWorkEventStyle(ev)"
                                            @click="(e) => handleEventClick(ev, e)"
                                        >
                                            <span class="font-bold">{{ formatTime(ev.start_time) }}</span> {{ ev.title }}
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div
                                            v-for="event in getEventsForDate(data.date)"
                                            :key="'pe-' + event.id"
                                            class="text-[10px] px-1 py-0.5 rounded border truncate cursor-pointer hover:opacity-80 transition flex items-center gap-1"
                                            :class="getEventColorClass(event)"
                                            @click="(e) => handleEventClick(event, e)"
                                        >
                                            <el-icon v-if="event.is_completed" size="10"><CircleCheck /></el-icon>
                                            <span class="font-bold">{{ formatTime(event.start) }}</span> {{ event.title }}
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </el-calendar>
                </div>

            </div>
        </div>

        <!-- Field Work Form Modal -->
        <FieldWorkFormModal
            v-model:visible="showFieldWorkModal"
            :schedule="editingSchedule"
            :prefilled-date="prefilledDate"
            :prefilled-time="prefilledTime"
            @saved="onFieldWorkSaved"
        />

        <!-- Personal Calendar Event Modal -->
        <el-dialog
            v-model="showPersonalModal"
            :title="isCreating ? 'Nuevo evento' : 'Detalles del evento'"
            width="600px"
            destroy-on-close
        >
            <div v-if="!isCreating && activeEvent && !activeEvent.is_creator" class="mb-6">
                <div class="bg-gray-50 dark:bg-[#252529] p-4 rounded-lg border border-gray-200 dark:border-[#3f3f46]">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white" :class="{ 'line-through text-gray-400': activeEvent.is_completed }">
                                {{ activeEvent.title }}
                            </h3>
                            <el-tag v-if="activeEvent.is_completed" type="success" size="small" effect="dark">Completado</el-tag>
                        </div>
                        <el-tag>{{ activeEvent.type }}</el-tag>
                    </div>
                    <p class="text-sm text-gray-500 mb-2">
                        Organizado por: <span class="font-bold">{{ activeEvent.creator?.name }}</span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-4 whitespace-pre-line">
                        {{ activeEvent.description || 'Sin descripción' }}
                    </p>
                    <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                        <div><span class="text-gray-400 block">Inicio</span><span class="font-medium dark:text-gray-200">{{ activeEvent.start }}</span></div>
                        <div><span class="text-gray-400 block">Fin</span><span class="font-medium dark:text-gray-200">{{ activeEvent.end }}</span></div>
                    </div>
                    <div v-if="activeEvent.my_status === 'Pendiente'" class="flex gap-2 justify-end border-t pt-4 dark:border-gray-700">
                        <el-button type="success" @click="submitResponse('Aceptado')">Aceptar</el-button>
                        <el-button type="danger" plain @click="submitResponse('Rechazado')">Rechazar</el-button>
                    </div>
                    <div v-else class="text-right">
                        <span class="text-sm">Tu respuesta: </span>
                        <el-tag :type="activeEvent.my_status === 'Aceptado' ? 'success' : 'danger'">{{ activeEvent.my_status }}</el-tag>
                    </div>
                </div>
            </div>

            <div v-else>
                <div v-if="!isCreating && activeEvent" class="mb-4 flex justify-end">
                    <el-button
                        :type="activeEvent.is_completed ? 'warning' : 'success'"
                        :icon="activeEvent.is_completed ? CircleClose : CircleCheck"
                        plain
                        class="w-full"
                        @click="toggleComplete"
                    >
                        {{ activeEvent.is_completed ? 'Reactivar (marcar pendiente)' : 'Marcar como completado' }}
                    </el-button>
                </div>

                <el-form :model="personalForm" label-position="top" :disabled="activeEvent?.is_completed">
                    <el-form-item label="Título">
                        <el-input v-model="personalForm.title" placeholder="Ej. Reunión de proyecto" />
                    </el-form-item>
                    <div class="grid grid-cols-2 gap-4">
                        <el-form-item label="Tipo">
                            <el-select v-model="personalForm.type" class="w-full">
                                <el-option v-for="t in types" :key="t" :label="t" :value="t" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="Participantes">
                            <el-select v-model="personalForm.participants" multiple placeholder="Invitar usuarios" class="w-full">
                                <el-option v-for="u in users" :key="u.id" :label="u.name" :value="u.id" />
                            </el-select>
                        </el-form-item>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <el-form-item label="Inicio">
                            <el-date-picker
                                v-model="personalForm.start_time"
                                type="datetime"
                                class="!w-full"
                                format="DD/MM/YYYY HH:mm"
                                value-format="YYYY-MM-DD HH:mm:ss"
                            />
                        </el-form-item>
                        <el-form-item label="Fin">
                            <el-date-picker
                                v-model="personalForm.end_time"
                                type="datetime"
                                class="!w-full"
                                format="DD/MM/YYYY HH:mm"
                                value-format="YYYY-MM-DD HH:mm:ss"
                            />
                        </el-form-item>
                    </div>
                    <el-form-item label="Descripción">
                        <el-input v-model="personalForm.description" type="textarea" :rows="3" />
                    </el-form-item>
                    <div
                        v-if="!isCreating && activeEvent && activeEvent.participants?.length > 0"
                        class="mt-4 p-3 bg-gray-50 rounded border dark:bg-[#18181b] dark:border-[#3f3f46]"
                    >
                        <p class="text-xs font-bold text-gray-500 uppercase mb-2">Estado de invitados</p>
                        <ul class="space-y-1">
                            <li v-for="p in activeEvent.participants" :key="p.id" class="text-sm flex justify-between">
                                <span class="dark:text-gray-300">{{ p.name }}</span>
                                <div class="flex items-center gap-2">
                                    <el-tag size="small" :type="p.pivot.status === 'Aceptado' ? 'success' : (p.pivot.status === 'Rechazado' ? 'danger' : 'warning')">
                                        {{ p.pivot.status }}
                                    </el-tag>
                                    <el-tooltip v-if="p.pivot.status === 'Rechazado'" :content="p.pivot.rejection_reason" placement="top">
                                        <el-icon class="text-gray-400 cursor-help"><InfoFilled /></el-icon>
                                    </el-tooltip>
                                </div>
                            </li>
                        </ul>
                    </div>
                </el-form>
            </div>

            <template #footer>
                <div class="dialog-footer flex justify-between">
                    <el-button v-if="!isCreating && activeEvent?.is_creator" type="danger" link @click="deletePersonalEvent">
                        Eliminar evento
                    </el-button>
                    <div v-else />
                    <div v-if="isCreating || activeEvent?.is_creator">
                        <el-button @click="showPersonalModal = false">Cancelar</el-button>
                        <el-button
                            type="primary"
                            color="#f26c17"
                            :loading="personalForm.processing"
                            :disabled="activeEvent?.is_completed"
                            @click="submitPersonalEvent"
                        >
                            {{ isCreating ? 'Agendar' : 'Guardar cambios' }}
                        </el-button>
                    </div>
                    <div v-else>
                        <el-button @click="showPersonalModal = false">Cerrar</el-button>
                    </div>
                </div>
            </template>
        </el-dialog>

    </AppLayout>
</template>

<style scoped>
:deep(.el-calendar-day) {
    height: 120px;
    padding: 4px;
}
:deep(.el-calendar-table .el-calendar-day:hover) {
    background-color: #f9fafb;
}
:global(.dark) :deep(.el-calendar-table .el-calendar-day:hover) {
    background-color: #27272a;
}
</style>