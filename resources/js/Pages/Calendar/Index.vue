<script setup>
import { ref, reactive, computed } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';

const props = defineProps({
    events: Array,
    users: Array,
});

const currentUser = usePage().props.auth.user;

// --- GESTIÓN DEL MODAL ---
const showModal = ref(false);
const isCreating = ref(false);
const selectedDate = ref(new Date());
const activeEvent = ref(null); // Evento seleccionado para ver/editar

const form = useForm({
    id: null,
    title: '',
    type: 'Reunión',
    description: '',
    start_time: '',
    end_time: '',
    participants: [], // IDs
});

const types = ['Reunión', 'Tarea', 'Llamada', 'Recordatorio', 'Evento'];

// --- ACCIONES DE CALENDARIO ---

const getEventsForDate = (date) => {
    const dateStr = date.toISOString().split('T')[0];
    return props.events.filter(e => {
        // Simple comparación de día (ignora hora para visualización en celda)
        return e.start.substring(0, 10) === dateStr;
    });
};

const handleDateClick = (data) => {
    // data: { date, type, isSelected, day }
    // Abrir modal para crear
    isCreating.value = true;
    activeEvent.value = null;
    
    // Configurar fechas por defecto (8 AM a 9 AM del día seleccionado)
    const start = new Date(data.date);
    start.setHours(9, 0, 0);
    const end = new Date(data.date);
    end.setHours(10, 0, 0);

    form.reset();
    form.start_time = start;
    form.end_time = end;
    showModal.value = true;
};

const handleEventClick = (event, e) => {
    e.stopPropagation(); // Evitar que dispare handleDateClick
    isCreating.value = false;
    activeEvent.value = event;
    
    // Cargar datos al formulario por si se edita
    form.id = event.id;
    form.title = event.title;
    form.type = event.type;
    form.description = event.description;
    form.start_time = event.start;
    form.end_time = event.end;
    form.participants = event.participants.map(p => p.id);

    showModal.value = true;
};

// --- CRUD ---

const submitEvent = () => {
    if (isCreating.value) {
        form.post(route('calendar.store'), {
            onSuccess: () => {
                showModal.value = false;
                ElMessage.success('Evento agendado');
            }
        });
    } else {
        // Editar (Solo si soy creador)
        form.put(route('calendar.update', form.id), {
            onSuccess: () => {
                showModal.value = false;
                ElMessage.success('Evento actualizado');
            }
        });
    }
};

const deleteEvent = () => {
    ElMessageBox.confirm('¿Eliminar este evento?', 'Confirmar', { type: 'warning' })
        .then(() => {
            router.delete(route('calendar.destroy', activeEvent.value.id), {
                onSuccess: () => {
                    showModal.value = false;
                    ElMessage.success('Evento eliminado');
                }
            });
        }).catch(() => {});
};

// --- RESPUESTA A INVITACIÓN ---
const responseForm = useForm({
    status: '',
    rejection_reason: '',
});

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
            showModal.value = false;
            ElMessage.success('Respuesta enviada');
        }
    });
};

// --- ESTILOS ---
const getEventColorClass = (event) => {
    if (event.is_creator) return 'bg-blue-100 text-blue-700 border-blue-200';
    if (event.my_status === 'Aceptado') return 'bg-green-100 text-green-700 border-green-200';
    if (event.my_status === 'Rechazado') return 'bg-red-50 text-red-400 border-red-100 line-through opacity-60';
    return 'bg-orange-100 text-orange-700 border-orange-200'; // Pendiente
};

const formatTime = (dateStr) => {
    return new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <AppLayout title="Calendario">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                Mi Calendario
            </h2>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg p-6">
                
                <!-- LEYENDA -->
                <div class="flex gap-4 mb-4 text-xs">
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-blue-100 border border-blue-300 rounded"></span> Mis Eventos</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-orange-100 border border-orange-300 rounded"></span> Invitación Pendiente</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-green-100 border border-green-300 rounded"></span> Invitación Aceptada</span>
                </div>

                <!-- CALENDARIO -->
                <el-calendar v-model="selectedDate">
                    <template #date-cell="{ data }">
                        <div 
                            class="h-full w-full flex flex-col gap-1 overflow-hidden"
                            @click="handleDateClick(data)"
                        >
                            <span class="text-sm font-bold" :class="data.isSelected ? 'text-primary' : 'text-gray-700 dark:text-gray-300'">
                                {{ data.date.getDate() }}
                            </span>
                            
                            <!-- Lista de eventos del día -->
                            <div class="flex flex-col gap-1 overflow-y-auto custom-scrollbar pr-1">
                                <div 
                                    v-for="event in getEventsForDate(data.date)" 
                                    :key="event.id"
                                    class="text-[10px] px-1 py-0.5 rounded border truncate cursor-pointer hover:opacity-80 transition"
                                    :class="getEventColorClass(event)"
                                    @click="(e) => handleEventClick(event, e)"
                                >
                                    <span class="font-bold">{{ formatTime(event.start) }}</span> {{ event.title }}
                                </div>
                            </div>
                        </div>
                    </template>
                </el-calendar>
            </div>
        </div>

        <!-- MODAL EVENTO -->
        <el-dialog
            v-model="showModal"
            :title="isCreating ? 'Nuevo Evento / Tarea' : 'Detalles del Evento'"
            width="600px"
            destroy-on-close
        >
            <div v-if="!isCreating && !activeEvent.is_creator" class="mb-6">
                <!-- VISTA INVITADO -->
                <div class="bg-gray-50 dark:bg-[#252529] p-4 rounded-lg border border-gray-200 dark:border-[#3f3f46]">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white">{{ activeEvent.title }}</h3>
                        <el-tag>{{ activeEvent.type }}</el-tag>
                    </div>
                    <p class="text-sm text-gray-500 mb-2">
                        Organizado por: <span class="font-bold">{{ activeEvent.creator.name }}</span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-4 whitespace-pre-line">
                        {{ activeEvent.description || 'Sin descripción' }}
                    </p>
                    
                    <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                        <div>
                            <span class="text-gray-400 block">Inicio</span>
                            <span class="font-medium dark:text-gray-200">{{ new Date(activeEvent.start).toLocaleString() }}</span>
                        </div>
                        <div>
                            <span class="text-gray-400 block">Fin</span>
                            <span class="font-medium dark:text-gray-200">{{ new Date(activeEvent.end).toLocaleString() }}</span>
                        </div>
                    </div>

                    <!-- Botones de Respuesta -->
                    <div v-if="activeEvent.my_status === 'Pendiente'" class="flex gap-2 justify-end border-t pt-4 dark:border-gray-700">
                        <el-button type="success" @click="submitResponse('Aceptado')">Aceptar Invitación</el-button>
                        <el-button type="danger" plain @click="submitResponse('Rechazado')">Rechazar</el-button>
                    </div>
                    <div v-else class="text-right">
                        <span class="text-sm">Tu respuesta: </span>
                        <el-tag :type="activeEvent.my_status === 'Aceptado' ? 'success' : 'danger'">{{ activeEvent.my_status }}</el-tag>
                    </div>
                </div>
            </div>

            <div v-else>
                <!-- VISTA CREADOR / EDITOR -->
                <el-form :model="form" label-position="top">
                    <el-form-item label="Motivo / Título">
                        <el-input v-model="form.title" placeholder="Ej. Reunión de proyecto" />
                    </el-form-item>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <el-form-item label="Tipo">
                            <el-select v-model="form.type" class="w-full">
                                <el-option v-for="t in types" :key="t" :label="t" :value="t" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="Participantes">
                            <el-select v-model="form.participants" multiple placeholder="Invitar usuarios" class="w-full">
                                <el-option v-for="u in users" :key="u.id" :label="u.name" :value="u.id" />
                            </el-select>
                        </el-form-item>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <el-form-item label="Inicio">
                            <el-date-picker 
                                v-model="form.start_time" 
                                type="datetime" 
                                class="!w-full" 
                                format="DD/MM/YYYY HH:mm"
                            />
                        </el-form-item>
                        <el-form-item label="Fin">
                            <el-date-picker 
                                v-model="form.end_time" 
                                type="datetime" 
                                class="!w-full"
                                format="DD/MM/YYYY HH:mm"
                            />
                        </el-form-item>
                    </div>

                    <el-form-item label="Descripción">
                        <el-input v-model="form.description" type="textarea" :rows="3" />
                    </el-form-item>

                    <!-- Lista de Estatus de Invitados (Solo en edición) -->
                    <div v-if="!isCreating && activeEvent && activeEvent.participants.length > 0" class="mt-4 p-3 bg-gray-50 rounded border dark:bg-[#18181b] dark:border-[#3f3f46]">
                        <p class="text-xs font-bold text-gray-500 uppercase mb-2">Estatus de invitados</p>
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
                    <el-button v-if="!isCreating && activeEvent?.is_creator" type="danger" link @click="deleteEvent">
                        Eliminar evento
                    </el-button>
                    <div v-else></div> <!-- Spacer -->

                    <div v-if="isCreating || activeEvent?.is_creator">
                        <el-button @click="showModal = false">Cancelar</el-button>
                        <el-button type="primary" color="#f26c17" @click="submitEvent" :loading="form.processing">
                            {{ isCreating ? 'Agendar' : 'Guardar Cambios' }}
                        </el-button>
                    </div>
                    <div v-else>
                        <el-button @click="showModal = false">Cerrar</el-button>
                    </div>
                </div>
            </template>
        </el-dialog>

    </AppLayout>
</template>

<style scoped>
/* Ajuste para que las celdas del calendario tengan altura y scroll si hay muchos eventos */
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