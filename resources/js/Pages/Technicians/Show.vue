<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    User, 
    MapLocation, 
    Document, 
    Money, 
    Edit, 
    Back,
    Phone,
    Message,
    Location,
    Briefcase,
    Download,
    Trophy,
    DataLine,
    View
} from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    technician: Object,
    tickets: Array,
    kpis: Object
});

const activeTab = ref('profile');

// --- HELPERS ---

const getStatusType = (status) => {
    const map = {
        'Activo': 'success',
        'Inactivo': 'info',
        'En revisión': 'warning',
        'Vetado': 'danger'
    };
    return map[status] || 'info';
};

const getTicketStatusType = (status) => {
    const map = {
        'Programado': 'info',
        'En proceso': 'primary',
        'Completado': 'success',
        'Cancelado': 'danger'
    };
    return map[status] || 'warning';
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

// Obtener documento fiscal de la colección de medios
const getFiscalDocument = () => {
    if (!props.technician.media) return null;
    return props.technician.media.find(m => m.collection_name === 'fiscal_documents');
};

const fiscalDoc = getFiscalDocument();

const navigateToTicket = (ticketId) => {
    router.visit(route('tickets.show', ticketId));
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
                            Detalle del técnico
                        </h2>
                        <p class="text-sm text-gray-500 mt-1">Visión general del perfil y desempeño.</p>
                    </div>
                </div>
                <Link v-if="can('technicians.edit')" :href="route('technicians.edit', technician.id)">
                    <el-button type="primary" :icon="Edit" color="#f26c17">
                        Editar perfil
                    </el-button>
                </Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">
            
            <!-- TARJETA RESUMEN (HEADER) -->
            <div class="bg-white dark:bg-[#1e1e20] rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-6">
                <div class="flex flex-col md:flex-row gap-6 items-center md:items-start">
                    
                    <!-- Avatar y Estatus -->
                    <div class="flex flex-col items-center gap-3">
                        <el-avatar :size="100" :src="technician.user.profile_photo_url" class="border-4 border-gray-50 dark:border-[#2b2b2e] shadow-md">
                            {{ technician.user.name.charAt(0) }}
                        </el-avatar>
                        <el-tag :type="getStatusType(technician.status)" effect="dark" size="large" class="w-full text-center uppercase tracking-wide font-bold">
                            {{ technician.status }}
                        </el-tag>
                    </div>

                    <!-- Info Principal -->
                    <div class="flex-1 text-center md:text-left w-full">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                            {{ technician.user.name }}
                            <el-icon v-if="technician.is_internal" class="text-blue-500 ml-2 align-middle" size="20" title="Empleado Interno"><Briefcase /></el-icon>
                        </h1>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-2 gap-x-8 text-sm text-gray-600 dark:text-gray-400 mb-4">
                            <div class="flex items-center gap-2 justify-center md:justify-start">
                                <el-icon><Message /></el-icon> {{ technician.user.email }}
                            </div>
                            <div class="flex items-center gap-2 justify-center md:justify-start">
                                <el-icon><Phone /></el-icon> {{ technician.phone }}
                            </div>
                            <div class="flex items-center gap-2 justify-center md:justify-start sm:col-span-2">
                                <el-icon><Location /></el-icon> {{ technician.city }}, {{ technician.state }}
                            </div>
                        </div>

                        <!-- Especialidades (Tags) -->
                        <div class="flex flex-wrap gap-2 justify-center md:justify-start">
                            <el-tag 
                                v-for="(spec, index) in technician.specialties" 
                                :key="index"
                                size="small"
                                effect="light"
                                class="!border-none bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                            >
                                {{ spec }}
                            </el-tag>
                        </div>
                    </div>

                    <!-- KPIs Rápidos -->
                    <div class="flex gap-4 md:border-l md:border-gray-100 md:dark:border-gray-700 md:pl-6 w-full md:w-auto justify-center md:justify-end">
                        <div class="text-center">
                            <div class="text-xs text-gray-400 uppercase font-bold mb-1">Tickets</div>
                            <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ kpis.total_tickets }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-xs text-gray-400 uppercase font-bold mb-1">Cumplimiento</div>
                            <el-progress type="circle" :percentage="kpis.completion_rate" :width="50" :stroke-width="4" color="#67c23a">
                                <template #default="{ percentage }">
                                    <span class="text-xs font-bold">{{ percentage }}%</span>
                                </template>
                            </el-progress>
                        </div>
                        <div class="text-center">
                            <div class="text-xs text-gray-400 uppercase font-bold mb-1">Rating</div>
                            <div class="text-xl font-bold text-yellow-500 flex items-center justify-center gap-1 h-[50px]">
                                {{ technician.rating_avg }} <el-icon><Trophy /></el-icon>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO PRINCIPAL (TABS) -->
            <div class="bg-white dark:bg-[#1e1e20] rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-tabs v-model="activeTab" class="px-6 py-2 demo-tabs">
                    
                    <!-- TAB 1: PERFIL DETALLADO -->
                    <el-tab-pane label="Perfil 360°" name="profile">
                        <div class="py-4 grid grid-cols-1 lg:grid-cols-2 gap-8">
                            
                            <!-- Columna Izquierda -->
                            <div class="space-y-6">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                        <el-icon class="text-primary"><MapLocation /></el-icon> Zona operativa
                                    </h3>
                                    <el-descriptions :column="1" border>
                                        <el-descriptions-item label="Dirección base">
                                            {{ technician.colony ? `${technician.colony}, ` : '' }}
                                            {{ technician.zip_code ? `C.P. ${technician.zip_code}` : '' }}
                                        </el-descriptions-item>
                                        <el-descriptions-item label="Radio de cobertura">
                                            {{ technician.coverage_radius_km }} Kilómetros
                                        </el-descriptions-item>
                                        <el-descriptions-item label="Teléfono de emergencia">
                                            {{ technician.secondary_phone || 'No registrado' }}
                                        </el-descriptions-item>
                                    </el-descriptions>
                                </div>

                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                        <el-icon class="text-primary"><Document /></el-icon> Notas internas
                                    </h3>
                                    <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg text-sm text-gray-600 dark:text-gray-300 border border-yellow-100 dark:border-yellow-800/50">
                                        {{ technician.internal_notes || 'Sin observaciones registradas.' }}
                                    </div>
                                </div>
                            </div>

                            <!-- Columna Derecha -->
                            <div class="space-y-6">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                        <el-icon class="text-primary"><Money /></el-icon> Datos administrativos
                                    </h3>
                                    <el-descriptions :column="1" border>
                                        <el-descriptions-item label="Razón social">
                                            {{ technician.legal_name || '-' }}
                                        </el-descriptions-item>
                                        <el-descriptions-item label="RFC">
                                            <span class="font-mono">{{ technician.rfc || '-' }}</span>
                                        </el-descriptions-item>
                                        <el-descriptions-item label="Banco">
                                            {{ technician.bank_name || '-' }}
                                        </el-descriptions-item>
                                        <el-descriptions-item label="Cuenta / Tarjeta">
                                            {{ technician.bank_account || '-' }}
                                        </el-descriptions-item>
                                        <el-descriptions-item label="CLABE">
                                            <span class="font-mono tracking-wider">{{ technician.clabe || '-' }}</span>
                                        </el-descriptions-item>
                                    </el-descriptions>
                                </div>
                            </div>

                        </div>
                    </el-tab-pane>

                    <!-- TAB 2: HISTORIAL OPERATIVO -->
                    <el-tab-pane label="Historial operativo" name="history">
                        <div class="py-4">
                            <div v-if="tickets.length > 0">
                                <el-table :data="tickets" style="width: 100%" stripe @row-click="(row) => navigateToTicket(row.id)" row-class-name="cursor-pointer">
                                    <el-table-column prop="id" label="ID" width="80">
                                        <template #default="scope">
                                            <span class="font-bold text-gray-500">#{{ scope.row.id }}</span>
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="Cliente / Proyecto" min-width="250">
                                        <template #default="scope">
                                            <div>
                                                <div class="font-bold text-gray-800 dark:text-gray-200">
                                                    {{ scope.row.budget?.customer?.name || 'Cliente General' }}
                                                </div>
                                                <div class="text-xs text-gray-500 truncate">{{ scope.row.instructions || 'Sin descripción' }}</div>
                                            </div>
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="Fecha" width="150">
                                        <template #default="scope">
                                            <div class="text-sm">
                                                {{ formatDate(scope.row.scheduled_start) }}
                                            </div>
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="Estatus" width="120" align="center">
                                        <template #default="scope">
                                            <el-tag :type="getTicketStatusType(scope.row.status)" size="small" effect="plain">
                                                {{ scope.row.status }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column width="60" align="center">
                                        <template #default>
                                            <el-icon class="text-gray-400"><View /></el-icon>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </div>
                            <el-empty v-else description="No hay tickets asignados a este técnico aún." />
                        </div>
                    </el-tab-pane>

                    <!-- TAB 3: DOCUMENTOS -->
                    <el-tab-pane label="Documentos" name="documents">
                        <div class="py-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                <!-- Tarjeta de Constancia Fiscal -->
                                <div v-if="fiscalDoc" class="border border-gray-200 dark:border-[#3f3f46] rounded-lg p-4 flex items-center gap-4 bg-gray-50 dark:bg-[#252529]">
                                    <div class="bg-red-100 text-red-500 p-3 rounded-lg">
                                        <el-icon size="24"><Document /></el-icon>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-bold text-gray-800 dark:text-gray-200 truncate">Constancia Fiscal</div>
                                        <div class="text-xs text-gray-500">{{ (fiscalDoc.size / 1024).toFixed(1) }} KB</div>
                                    </div>
                                    <a :href="fiscalDoc.original_url" target="_blank" class="no-underline">
                                        <el-button type="primary" :icon="Download" circle plain />
                                    </a>
                                </div>

                                <div v-else class="col-span-3 text-center py-8 text-gray-400 border-2 border-dashed border-gray-200 dark:border-[#3f3f46] rounded-lg">
                                    <el-icon size="32" class="mb-2"><Document /></el-icon>
                                    <p>No se ha cargado la Constancia de Situación Fiscal.</p>
                                </div>
                            </div>
                        </div>
                    </el-tab-pane>

                </el-tabs>
            </div>

        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.el-tabs__item) {
    font-size: 16px;
    padding: 20px 0;
}
:deep(.el-descriptions__label) {
    font-weight: 600;
    color: #606266;
}
</style>