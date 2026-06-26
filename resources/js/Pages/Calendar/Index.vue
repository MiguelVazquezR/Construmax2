<script setup>
import { ref, reactive, computed } from 'vue';
import { useForm, router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { CircleCheck, CircleClose, InfoFilled, Tools, Warning, Timer, OfficeBuilding, Location, UserFilled } from '@element-plus/icons-vue';

const props = defineProps({
    events: Array,
    tickets: Array,
    users: Array,
});

const currentUser = usePage().props.auth.user;

// --- GESTIÓN DEL MODAL (eventos) ---
const showModal = ref(false);
const isCreating = ref(false);
const selectedDate = ref(new Date());
const activeEvent = ref(null);

// --- GESTIÓN DEL MODAL (tickets) ---
const showTicketModal = ref(false);
const activeTicket = ref(null);

const form = useForm({
    id: null,
    title: '',
    type: 'Reunión',
    description: '',
    start_time: '',
    end_time: '',
    participants: [],
});

const types = ['Reunión', 'Tarea', 'Llamada', 'Recordatorio', 'Evento'];

// --- ACCIONES DE CALENDARIO ---

const getEventsForDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}`;

    return props.events.filter(e => {
        return e.start.substring(0, 10) === dateStr;
    });
};

// --- TICKETS EN CALENDARIO ---

const getTicketsForDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}`;

    return props.tickets.filter(t => {
        return dateStr >= t.start && dateStr <= t.end;
    });
};

const getTicketHealthClass = (ticket) => {
    if (ticket.health === 'Vencido') {
        return 'bg-red-500 text-white border-red-600';
    }
    if (ticket.health === 'Programado') {
        return 'bg-indigo-100 text-indigo-700 border-indigo-200';
    }
    return 'bg-emerald-100 text-emerald-700 border-emerald-200';
};

const getTicketHealthIcon = (ticket) => {
    if (ticket.health === 'Vencido') return Warning;
    if (ticket.health === 'Programado') return Timer;
    return CircleCheck;
};

const handleTicketClick = (ticket, e) => {
    e.stopPropagation();
    activeTicket.value = ticket;
    showTicketModal.value = true;
};

const formatDate = (dateStr) => {
    if (!dateStr) return '--';
    return new Date(dateStr + 'T12:00:00').toLocaleDateString('es-MX', {
        day: '2-digit', month: 'short', year: 'numeric'
    });
};

const handleDateClick = (data) => {
    isCreating.value = true;
    activeEvent.value = null;
    
    const start = new Date(data.date);
    start.setHours(9, 0, 0); 
    
    const end = new Date(data.date);
    end.setHours(10, 0, 0); 

    form.reset();
    form.start_time = formatDateToString(start);
    form.end_time = formatDateToString(end);
    
    showModal.value = true;
};

const formatDateToString = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
};

const handleEventClick = (event, e) => {
    e.stopPropagation();
    isCreating.value = false;
    activeEvent.value = event;
    
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

// --- TOGGLE COMPLETADO ---
const toggleComplete = () => {
    router.put(route('calendar.toggle-complete', activeEvent.value.id), {}, {
        onSuccess: () => {
            showModal.value = false;
            // No necesitamos ElMessage aquí si el controlador ya envía un flash message, 
            // pero si usas el componente de flash global, está bien.
        }
    });
};

// --- RESPUESTA ---
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
    // Si está completado, gris y tachado
    if (event.is_completed) return 'bg-gray-100 text-gray-400 border-gray-200 line-through decoration-gray-400';

    if (event.is_creator) return 'bg-blue-100 text-blue-700 border-blue-200';
    if (event.my_status === 'Aceptado') return 'bg-green-100 text-green-700 border-green-200';
    if (event.my_status === 'Rechazado') return 'bg-red-50 text-red-400 border-red-100 line-through opacity-60';
    return 'bg-orange-100 text-orange-700 border-orange-200'; 
};

const formatTime = (dateStr) => {
    if(!dateStr) return '';
    const date = new Date(dateStr.replace(/-/g, '/')); 
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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
                
                <div class="flex flex-wrap gap-4 mb-4 text-xs">
                    <span class="flex items-center gap-1 font-semibold text-gray-500 mr-4">Eventos:</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-blue-100 border border-blue-300 rounded"></span> Mis Eventos</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-orange-100 border border-orange-300 rounded"></span> Invitación Pendiente</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-green-100 border border-green-300 rounded"></span> Invitación Aceptada</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-gray-200 border border-gray-400 rounded"></span> Terminados</span>
                </div>
                <div class="flex flex-wrap gap-4 mb-4 text-xs border-t border-gray-100 dark:border-gray-700 pt-3">
                    <span class="flex items-center gap-1 font-semibold text-gray-500 mr-4">Tickets:</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-emerald-100 border border-emerald-300 rounded"></span> A tiempo</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-indigo-100 border border-indigo-300 rounded"></span> Programado</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-red-500 rounded"></span> Vencido</span>
                </div>

                <el-calendar v-model="selectedDate">
                    <template #date-cell="{ data }">
                        <div 
                            class="h-full w-full flex flex-col gap-1 overflow-hidden"
                            @click="handleDateClick(data)"
                        >
                            <span class="text-sm font-bold" :class="data.isSelected ? 'text-primary' : 'text-gray-700 dark:text-gray-300'">
                                {{ data.date.getDate() }}
                            </span>
                            
                            <div class="flex flex-col gap-1 overflow-y-auto custom-scrollbar pr-1">
                                <!-- Tickets -->
                                <div 
                                    v-for="ticket in getTicketsForDate(data.date)" 
                                    :key="'t-' + ticket.id"
                                    class="text-[9px] px-1 py-0.5 rounded border truncate cursor-pointer hover:opacity-80 transition flex items-center gap-1 font-medium"
                                    :class="getTicketHealthClass(ticket)"
                                    @click="(e) => handleTicketClick(ticket, e)"
                                >
                                    <el-icon :size="10"><component :is="getTicketHealthIcon(ticket)" /></el-icon>
                                    {{ ticket.folio }} {{ ticket.name }}
                                </div>

                                <!-- Events -->
                                <div 
                                    v-for="event in getEventsForDate(data.date)" 
                                    :key="'e-' + event.id"
                                    class="text-[10px] px-1 py-0.5 rounded border truncate cursor-pointer hover:opacity-80 transition flex items-center gap-1"
                                    :class="getEventColorClass(event)"
                                    @click="(e) => handleEventClick(event, e)"
                                >
                                    <el-icon v-if="event.is_completed" size="10"><CircleCheck /></el-icon>
                                    <span class="font-bold">{{ formatTime(event.start) }}</span> {{ event.title }}
                                </div>
                            </div>
                        </div>
                    </template>
                </el-calendar>
            </div>
        </div>

        <el-dialog
            v-model="showModal"
            :title="isCreating ? 'Nuevo Evento / Tarea' : 'Detalles del Evento'"
            width="600px"
            destroy-on-close
        >
            <!-- MODO VISUALIZACIÓN / EDICIÓN PARA INVITADOS -->
            <div v-if="!isCreating && !activeEvent.is_creator" class="mb-6">
                <div class="bg-gray-50 dark:bg-[#252529] p-4 rounded-lg border border-gray-200 dark:border-[#3f3f46]">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white" :class="{'line-through text-gray-400': activeEvent.is_completed}">
                                {{ activeEvent.title }}
                            </h3>
                            <el-tag v-if="activeEvent.is_completed" type="success" size="small" effect="dark">Terminado</el-tag>
                        </div>
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
                            <span class="font-medium dark:text-gray-200">{{ activeEvent.start }}</span>
                        </div>
                        <div>
                            <span class="text-gray-400 block">Fin</span>
                            <span class="font-medium dark:text-gray-200">{{ activeEvent.end }}</span>
                        </div>
                    </div>

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

            <!-- MODO FORMULARIO (CREADOR) -->
            <div v-else>
                <!-- Botón de Completar (Solo visible si ya existe el evento) -->
                <div v-if="!isCreating && activeEvent" class="mb-4 flex justify-end">
                    <el-button 
                        :type="activeEvent.is_completed ? 'warning' : 'success'" 
                        :icon="activeEvent.is_completed ? CircleClose : CircleCheck"
                        plain
                        class="w-full"
                        @click="toggleComplete"
                    >
                        {{ activeEvent.is_completed ? 'Reactivar evento (Marcar pendiente)' : 'Marcar como terminado' }}
                    </el-button>
                </div>

                <el-form :model="form" label-position="top" :disabled="activeEvent?.is_completed">
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
                                value-format="YYYY-MM-DD HH:mm:ss"
                            />
                        </el-form-item>
                        <el-form-item label="Fin">
                            <el-date-picker 
                                v-model="form.end_time" 
                                type="datetime" 
                                class="!w-full"
                                format="DD/MM/YYYY HH:mm"
                                value-format="YYYY-MM-DD HH:mm:ss"
                            />
                        </el-form-item>
                    </div>

                    <el-form-item label="Descripción">
                        <el-input v-model="form.description" type="textarea" :rows="3" />
                    </el-form-item>

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
                    <div v-else></div> 

                    <div v-if="isCreating || activeEvent?.is_creator">
                        <el-button @click="showModal = false">Cancelar</el-button>
                        <el-button 
                            type="primary" 
                            color="#f26c17" 
                            @click="submitEvent" 
                            :loading="form.processing"
                            :disabled="activeEvent?.is_completed"
                        >
                            {{ isCreating ? 'Agendar' : 'Guardar Cambios' }}
                        </el-button>
                    </div>
                    <div v-else>
                        <el-button @click="showModal = false">Cerrar</el-button>
                    </div>
                </div>
            </template>
        </el-dialog>

        <!-- MODAL DE DETALLES DE TICKET -->
        <el-dialog
            v-model="showTicketModal"
            :title="`Ticket ${activeTicket?.folio}`"
            width="500px"
            destroy-on-close
        >
            <div v-if="activeTicket" class="space-y-4">
                <!-- Health badge -->
                <div class="flex items-center gap-2 mb-2">
                    <el-tag
                        :type="activeTicket.health === 'Vencido' ? 'danger' : activeTicket.health === 'Programado' ? 'info' : 'success'"
                        effect="dark"
                        size="small"
                    >
                        <el-icon class="mr-1"><component :is="getTicketHealthIcon(activeTicket)" /></el-icon>
                        {{ activeTicket.health }}
                    </el-tag>
                    <el-tag v-if="activeTicket.priority" size="small" :type="activeTicket.priority === 'Urgente' || activeTicket.priority === 'Alta' ? 'danger' : 'info'" effect="plain">
                        {{ activeTicket.priority }}
                    </el-tag>
                </div>

                <!-- Project name -->
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">{{ activeTicket.name }}</h3>

                <!-- Customer -->
                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <el-icon><OfficeBuilding /></el-icon>
                    <span>{{ activeTicket.customer || 'Sin cliente' }}</span>
                </div>

                <!-- Branch -->
                <div v-if="activeTicket.branch" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <el-icon><Location /></el-icon>
                    <span>{{ activeTicket.branch }}</span>
                </div>

                <!-- Seller -->
                <div v-if="activeTicket.seller" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <el-icon><UserFilled /></el-icon>
                    <span>Asesor: {{ activeTicket.seller }}</span>
                </div>

                <!-- Dates -->
                <div class="grid grid-cols-2 gap-4 bg-gray-50 dark:bg-[#252529] p-3 rounded-lg">
                    <div>
                        <span class="text-xs text-gray-400 block">Inicio</span>
                        <span class="text-sm font-medium dark:text-gray-200">{{ formatDate(activeTicket.start) }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block">Fin</span>
                        <span class="text-sm font-medium dark:text-gray-200">{{ formatDate(activeTicket.end) }}</span>
                    </div>
                </div>

                <!-- Progress -->
                <div>
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>Progreso</span>
                        <span>{{ activeTicket.progress }}%</span>
                    </div>
                    <el-progress
                        :percentage="activeTicket.progress"
                        :stroke-width="6"
                        :show-text="false"
                        :color="activeTicket.progress === 100 ? '#67c23a' : '#f26c17'"
                    />
                </div>
            </div>

            <template #footer>
                <el-button @click="showTicketModal = false">Cerrar</el-button>
                <Link :href="route('tickets.show', activeTicket?.id)">
                    <el-button type="primary" color="#f26c17" :icon="Tools">
                        Ver detalles del ticket
                    </el-button>
                </Link>
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