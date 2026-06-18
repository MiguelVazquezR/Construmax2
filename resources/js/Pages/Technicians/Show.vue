<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';
import { 
    Edit, 
    Back,
    Phone,
    Message,
    Location,
    Briefcase,
    Trophy,
    ArrowDown,
    ChatDotRound,
    Check,
    InfoFilled,
    CircleCheck,
    List,
    Money
} from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';

// Importar Componentes Parciales
import ProfileTab from './Partials/ProfileTab.vue';
import HistoryTab from './Partials/HistoryTab.vue';
import DocumentsTab from './Partials/DocumentsTab.vue';
import PaymentsTab from './Partials/PaymentsTab.vue'; // Nuevo componente

const { can } = usePermissions();

const props = defineProps({
    technician: Object,
    tickets: Array,
    payments: Array, // Recibimos los pagos
    kpis: Object
});

const activeTab = ref('profile');
const ratingPopoverVisible = ref(false);
const tempRating = ref(props.technician.rating_avg);

const ratingColors = {
    2: '#F56C6C',
    3.5: '#E6A23C',
    5: '#67C23A',
};

// Formateo de moneda
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { 
        style: 'currency', 
        currency: 'MXN' 
    }).format(value || 0);
};

// URL de Google Maps basada en la ubicación registrada
const googleMapsUrl = computed(() => {
    const query = `${props.technician.city}, ${props.technician.state}, Mexico`;
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
});

const updateRating = () => {
    router.put(route('technicians.update-rating', props.technician.id), {
        rating: tempRating.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            ratingPopoverVisible.value = false;
            ElMessage.success('Calificación actualizada');
        }
    });
};

const changeStatus = (newStatus) => {
    router.put(route('technicians.update-status', props.technician.id), {
        status: newStatus
    }, {
        preserveScroll: true,
        onSuccess: () => ElMessage.success(`Estatus cambiado a ${newStatus}`)
    });
};

const getStatusType = (status) => {
    const map = {
        'Activo': 'success',
        'Inactivo': 'info',
        'En revisión': 'warning',
        'Vetado': 'danger'
    };
    return map[status] || 'info';
};

const getWhatsappUrl = (phone) => {
    if (!phone) return '#';
    const number = phone.replace(/\D/g, '');
    return `https://wa.me/${number}`;
};
</script>

<template>
    <AppLayout :title="`Técnico: ${technician.user.name}`">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('technicians.index')">
                        <el-button :icon="Back" circle plain />
                    </Link>
                    <div>
                        <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">
                            Perfil del Técnico
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">Gestión integral y seguimiento operativo.</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link v-if="can('technicians.edit')" :href="route('technicians.edit', technician.id)">
                        <el-button type="primary" :icon="Edit" color="#f26c17">
                            Editar Perfil
                        </el-button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">
            
            <!-- HEADER DINÁMICO REORDENADO -->
            <div class="bg-white dark:bg-[#1e1e20] rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-6">
                <div class="flex flex-col lg:flex-row gap-8 items-start">
                    
                    <!-- LADO IZQUIERDO: Avatar y Cambio de Estatus -->
                    <div class="flex flex-col items-center gap-4 min-w-[150px]">
                        <div class="relative group">
                            <el-avatar :size="110" :src="technician.user.profile_photo_url" class="border-4 border-white dark:border-[#2b2b2e] shadow-lg">
                                {{ technician.user.name.charAt(0) }}
                            </el-avatar>
                        </div>
                        
                        <!-- SELECTOR DE ESTATUS INTERACTIVO -->
                        <el-dropdown v-if="can('technicians.edit')" trigger="click" @command="changeStatus">
                            <el-button :type="getStatusType(technician.status)" size="default" effect="dark" class="w-full !rounded-full uppercase tracking-tighter font-bold">
                                {{ technician.status }} <el-icon class="el-icon--right"><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="Activo" :disabled="technician.status === 'Activo'">Activo</el-dropdown-item>
                                    <el-dropdown-item command="En revisión" :disabled="technician.status === 'En revisión'">En revisión</el-dropdown-item>
                                    <el-dropdown-item command="Inactivo" :disabled="technician.status === 'Inactivo'">Inactivo</el-dropdown-item>
                                    <el-dropdown-item command="Vetado" class="text-red-500" :disabled="technician.status === 'Vetado'">Vetado</el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                        <el-tag v-else :type="getStatusType(technician.status)" effect="dark" class="w-full text-center rounded-full">{{ technician.status }}</el-tag>
                    </div>

                    <!-- CENTRO: Información de contacto -->
                    <div class="flex-1 space-y-4">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white">
                                    {{ technician.user.name }}
                                </h1>
                                <el-tag v-if="technician.is_internal" type="info" size="small" effect="plain" class="border-blue-200 text-blue-600">
                                    INTERNO
                                </el-tag>
                                <el-tag v-if="technician.level" size="small" effect="dark" :type="technician.level === 'Encargado' ? 'primary' : 'warning'">
                                    {{ technician.level }}
                                </el-tag>
                            </div>
                            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                                <!-- Email Interactivo -->
                                <a :href="`mailto:${technician.user.email}`" class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-primary transition-colors group">
                                    <el-icon><Message /></el-icon> 
                                    <span class="border-b border-transparent group-hover:border-primary">{{ technician.user.email }}</span>
                                </a>
                                
                                <!-- Teléfono con Dropdown -->
                                <el-dropdown trigger="click">
                                    <span class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400 cursor-pointer hover:text-primary transition-colors font-medium">
                                        <el-icon><Phone /></el-icon> {{ technician.phone }}
                                    </span>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <a :href="`tel:${technician.phone}`" class="no-underline text-inherit"><el-dropdown-item :icon="Phone">Llamar</el-dropdown-item></a>
                                            <a :href="getWhatsappUrl(technician.phone)" target="_blank" class="no-underline text-inherit"><el-dropdown-item :icon="ChatDotRound">WhatsApp</el-dropdown-item></a>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>

                                <!-- Ubicación con Google Maps -->
                                <a :href="googleMapsUrl" target="_blank" class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-blue-500 transition-colors group">
                                    <el-icon><Location /></el-icon> 
                                    <span class="border-b border-transparent group-hover:border-blue-500">{{ technician.city }}, {{ technician.state }}</span>
                                </a>
                            </div>
                        </div>

                        <!-- Especialidades -->
                        <div class="flex flex-wrap gap-2">
                            <el-tag v-for="(spec, index) in technician.specialties" :key="index" size="small" effect="light" class="!border-none !bg-gray-100 !text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                {{ spec }}
                            </el-tag>
                        </div>
                    </div>

                    <!-- LADO DERECHO: Indicadores / KPIs con Explicaciones -->
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:flex lg:flex-row gap-6 p-4 lg:p-0 bg-gray-50 dark:bg-transparent rounded-lg w-full lg:w-auto">
                        
                        <!-- Tickets -->
                        <div class="flex flex-col items-center">
                            <div class="flex items-center gap-1 px-2 text-[10px] text-gray-400 uppercase font-black mb-1">
                                Tickets 
                                <el-tooltip content="Total de servicios asignados a este técnico (como responsable o apoyo)." placement="top">
                                    <el-icon class="cursor-help"><InfoFilled /></el-icon>
                                </el-tooltip>
                            </div>
                            <div class="text-2xl font-black text-gray-800 dark:text-white flex items-center gap-1">
                                <el-icon class="!text-blue-500" size="22"><List /></el-icon> {{ kpis.total_tickets }}
                            </div>
                        </div>

                        <!-- Cumplimiento -->
                        <div class="flex flex-col items-center px-4 border-l border-gray-200 dark:border-gray-800">
                            <div class="flex items-center gap-1 px-2 text-[10px] text-gray-400 uppercase font-black mb-1">
                                Éxito
                                <el-tooltip content="Porcentaje de tickets finalizados satisfactoriamente del total asignado." placement="top">
                                    <el-icon class="cursor-help"><InfoFilled /></el-icon>
                                </el-tooltip>
                            </div>
                            <el-progress type="circle" :percentage="kpis.completion_rate" :width="45" :stroke-width="4" color="#67c23a">
                                <template #default="{ percentage }">
                                    <span class="text-[10px] font-bold">{{ percentage }}%</span>
                                </template>
                            </el-progress>
                        </div>

                        <!-- NUEVO: Ingresos (Total Pagado) -->
                        <div class="flex flex-col items-center px-4 border-l border-gray-200 dark:border-gray-800">
                            <div class="flex items-center gap-1 px-2 text-[10px] text-gray-400 uppercase font-black mb-1">
                                Pagado
                                <el-tooltip content="Monto total histórico pagado a este técnico por servicios realizados." placement="top">
                                    <el-icon class="cursor-help"><InfoFilled /></el-icon>
                                </el-tooltip>
                            </div>
                            <div class="text-lg lg:text-xl font-black text-green-600 flex items-center gap-1">
                                {{ formatCurrency(kpis.total_earnings) }}
                            </div>
                        </div>
                        
                        <!-- Rating -->
                        <div class="flex flex-col items-center border-l border-gray-200 dark:border-gray-800 pl-4">
                            <div class="flex items-center gap-1 px-2 text-[10px] text-gray-400 uppercase font-black mb-1">
                                Rating
                                <el-tooltip content="Calificación promedio basada en su historial operativo y comportamiento interno." placement="top">
                                    <el-icon class="cursor-help"><InfoFilled /></el-icon>
                                </el-tooltip>
                            </div>
                            
                            <el-popover v-model:visible="ratingPopoverVisible" placement="bottom" :width="200" trigger="click" :disabled="!can('technicians.edit')">
                                <template #reference>
                                    <div class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 rounded px-2 py-1 transition-colors group text-center">
                                        <div class="text-2xl font-black flex items-center gap-1" :style="{ color: technician.rating_avg >= 4 ? '#67c23a' : (technician.rating_avg >= 2.5 ? '#e6a23c' : '#f56c6c') }">
                                            {{ technician.rating_avg }} <el-icon class="text-yellow-500"><Trophy /></el-icon>
                                        </div>
                                    </div>
                                </template>
                                <div class="text-center space-y-3 p-2">
                                    <p class="text-xs font-bold uppercase text-gray-500">Ajustar Rating</p>
                                    <el-rate v-model="tempRating" allow-half :colors="ratingColors" />
                                    <div class="flex justify-end gap-2 pt-1">
                                        <el-button size="small" text @click="ratingPopoverVisible = false">Cerrar</el-button>
                                        <el-button size="small" type="primary" color="#f26c17" @click="updateRating" :icon="Check">Guardar</el-button>
                                    </div>
                                </div>
                            </el-popover>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO PESTAÑAS -->
            <div class="bg-white dark:bg-[#1e1e20] rounded-xl shadow-sm border px-4 border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-tabs v-model="activeTab">
                    <el-tab-pane label="Perfil 360°" name="profile">
                        <div class="px-6"> <ProfileTab :technician="technician" /> </div>
                    </el-tab-pane>

                    <el-tab-pane label="Historial operativo" name="history">
                        <div class="px-6">
                            <HistoryTab :tickets="tickets" :technician-id="technician.user_id" />
                        </div>
                    </el-tab-pane>

                    <!-- NUEVA PESTAÑA -->
                    <el-tab-pane label="Pagos y Finanzas" name="payments">
                        <div class="px-6">
                            <PaymentsTab :payments="payments" />
                        </div>
                    </el-tab-pane>

                    <el-tab-pane label="Documentos" name="documents">
                        <div class="px-6"> <DocumentsTab :technician="technician" /> </div>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.el-dropdown-link:focus {
    outline: none;
}
.cursor-help {
    cursor: help;
}
</style>