<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import { ElMessageBox, ElMessage } from 'element-plus';
import { 
    Search, 
    Plus, 
    Tools, 
    MoreFilled, 
    View, 
    Edit, 
    Delete,
    Location,
    StarFilled
} from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    technicians: Object, // Paginado
    filters: Object,
});

const search = ref(props.filters.search || '');
const perPage = ref(parseInt(props.filters.perPage) || 10);

// Sincronización con el servidor
const handleSearch = debounce((val) => {
    router.get(route('technicians.index'), { search: val, perPage: perPage.value }, {
        preserveState: true,
        replace: true,
    });
}, 300);

const handleSizeChange = (val) => {
    perPage.value = val;
    router.get(route('technicians.index'), { search: search.value, perPage: val }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const handlePageChange = (val) => {
    router.get(route('technicians.index'), { search: search.value, perPage: perPage.value, page: val }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Navegación al detalle
const handleRowClick = (row) => {
    router.visit(route('technicians.show', row.id));
};

// Acciones
const deleteTechnician = (technician) => {
    ElMessageBox.confirm(
        `¿Estás seguro de eliminar al técnico "${technician.user.name}"? Esta acción borrará su usuario y todo su historial asociado permanentemente.`,
        'Eliminar técnico',
        {
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            type: 'error',
        }
    )
    .then(() => {
        router.delete(route('technicians.destroy', technician.id), {
             onSuccess: () => {
                ElMessage({
                    type: 'success',
                    message: 'Técnico eliminado correctamente',
                });
            }
        });
    })
    .catch(() => {});
};

// Helpers Visuales
const getStatusColor = (status) => {
    const map = {
        'Activo': 'success',
        'Inactivo': 'info',
        'En revisión': 'warning',
        'Vetado': 'danger'
    };
    return map[status] || 'info';
};

watch(search, (val) => {
    handleSearch(val);
});
</script>

<template>
    <AppLayout title="Gestión de técnicos">
        <div class="space-y-4">
            <!-- Barra de Herramientas -->
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <div class="w-full sm:w-1/3">
                    <el-input
                        v-model="search"
                        placeholder="Buscar por nombre, especialidad o ciudad..."
                        clearable
                        :prefix-icon="Search"
                        class="w-full"
                    />
                </div>
                <div class="flex gap-2 w-full sm:w-auto justify-end">
                    <el-select v-model="perPage" placeholder="Mostrar" style="width: 110px" @change="handleSizeChange">
                        <el-option label="10 / pág" :value="10" />
                        <el-option label="20 / pág" :value="20" />
                        <el-option label="50 / pág" :value="50" />
                    </el-select>
                    <Link v-if="can('technicians.create')" :href="route('technicians.create')">
                        <el-button type="primary" color="#f26c17" :icon="Plus">
                            Nuevo técnico
                        </el-button>
                    </Link>
                </div>
            </div>

            <!-- CONTENEDOR UNIFICADO (Tabla + Paginación) -->
            <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                
                <!-- VISTA DE ESCRITORIO (Tabla) -->
                <div class="hidden md:block">
                    <el-table 
                        :data="technicians.data" 
                        style="width: 100%" 
                        stripe
                        @row-click="handleRowClick"
                        row-class-name="cursor-pointer hover:bg-gray-50 dark:hover:bg-[#27272a] transition-colors"
                    >
                        <el-table-column prop="id" label="ID" width="60" />
                        
                        <!-- Perfil -->
                        <el-table-column label="Técnico" min-width="260">
                            <template #default="scope">
                                <div class="flex items-center gap-3">
                                    <el-avatar :size="36" :src="scope.row.user.profile_photo_url" class="border border-gray-200">
                                        {{ scope.row.user.name.charAt(0) }}
                                    </el-avatar>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 dark:text-gray-200 text-sm flex items-center gap-1">
                                            {{ scope.row.user.name }}
                                            <el-tag v-if="scope.row.is_internal" size="small" type="info" effect="plain" class="ml-1 scale-90">Interno</el-tag>
                                        </span>
                                        <span class="text-xs text-gray-500">{{ scope.row.user.email }}</span>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Ubicación -->
                        <el-table-column label="Ubicación" min-width="150">
                            <template #default="scope">
                                <div class="flex items-center text-gray-600 dark:text-gray-400 text-sm">
                                    <el-icon class="mr-1"><Location /></el-icon>
                                    {{ scope.row.city }}, {{ scope.row.state }}
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Especialidades -->
                        <el-table-column label="Especialidades" min-width="200">
                            <template #default="scope">
                                <div class="flex flex-wrap gap-1">
                                    <el-tag 
                                        v-for="(spec, index) in (scope.row.specialties || []).slice(0, 2)" 
                                        :key="index" 
                                        size="small" 
                                        effect="light"
                                        class="!border-none !bg-gray-100 !text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                    >
                                        {{ spec }}
                                    </el-tag>
                                    <el-tag v-if="(scope.row.specialties || []).length > 2" size="small" type="info" effect="plain">
                                        +{{ scope.row.specialties.length - 2 }}
                                    </el-tag>
                                    <span v-if="!scope.row.specialties?.length" class="text-xs text-gray-400 italic">Sin etiquetas</span>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Calificación -->
                        <el-table-column label="Rating" width="180">
                            <template #default="scope">
                                <el-rate
                                    v-model="scope.row.rating_avg"
                                    disabled
                                    show-score
                                    size="small"
                                    text-color="#ff9900"
                                    score-template="{value}"
                                />
                            </template>
                        </el-table-column>

                        <!-- Estatus -->
                        <el-table-column label="Estatus" width="120" align="center">
                            <template #default="scope">
                                <el-tag :type="getStatusColor(scope.row.status)" size="small" effect="dark" class="w-full text-center">
                                    {{ scope.row.status }}
                                </el-tag>
                            </template>
                        </el-table-column>

                        <!-- Acciones -->
                        <el-table-column label="Acciones" width="100" align="center" fixed="right">
                            <template #default="scope">
                                <div @click.stop>
                                    <el-dropdown trigger="click">
                                        <span class="el-dropdown-link cursor-pointer p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full transition display-inline-block">
                                            <el-icon><MoreFilled /></el-icon>
                                        </span>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <Link :href="route('technicians.show', scope.row.id)">
                                                    <el-dropdown-item :icon="View">Ver perfil</el-dropdown-item>
                                                </Link>
                                                
                                                <Link v-if="can('technicians.edit')" :href="route('technicians.edit', scope.row.id)">
                                                    <el-dropdown-item :icon="Edit">Editar</el-dropdown-item>
                                                </Link>
                                                
                                                <el-dropdown-item v-if="can('technicians.delete')" divided :icon="Delete" class="text-red-500" @click="deleteTechnician(scope.row)">
                                                    Eliminar
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </div>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>

                <!-- VISTA MÓVIL (Tarjetas) -->
                <div class="md:hidden">
                    <div v-for="tech in technicians.data" :key="tech.id" class="p-4 border-b border-gray-100 dark:border-[#2b2b2e] last:border-0 hover:bg-gray-50 dark:hover:bg-[#252529] transition-colors cursor-pointer" @click="handleRowClick(tech)">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center gap-3">
                                <el-avatar :size="40" :src="tech.user.profile_photo_url" class="border border-gray-200">
                                    {{ tech.user.name.charAt(0) }}
                                </el-avatar>
                                <div>
                                    <h3 class="font-bold text-gray-800 dark:text-gray-200 text-sm flex items-center gap-2">
                                        {{ tech.user.name }}
                                        <el-icon v-if="tech.rating_avg >= 4.5" class="text-yellow-500" size="14"><StarFilled /></el-icon>
                                    </h3>
                                    <p class="text-xs text-gray-500 flex items-center gap-1">
                                        <el-icon><Location /></el-icon> {{ tech.city }}
                                    </p>
                                </div>
                            </div>
                            <el-tag :type="getStatusColor(tech.status)" size="small" effect="dark">
                                {{ tech.status }}
                            </el-tag>
                        </div>
                        
                        <div class="flex flex-wrap gap-1 mb-3">
                            <el-tag 
                                v-for="(spec, idx) in (tech.specialties || []).slice(0, 3)" 
                                :key="idx" 
                                size="small" 
                                class="!border-none bg-gray-100 text-gray-600"
                            >
                                {{ spec }}
                            </el-tag>
                        </div>

                        <!-- Botones móviles -->
                        <div class="flex justify-end gap-2 pt-2 border-t border-gray-50 dark:border-gray-800" @click.stop>
                            <Link :href="route('technicians.show', tech.id)">
                                <el-button size="small" :icon="View" circle plain />
                            </Link>
                            <Link v-if="can('technicians.edit')" :href="route('technicians.edit', tech.id)">
                                <el-button size="small" :icon="Edit" circle />
                            </Link>
                            <el-button v-if="can('technicians.delete')" size="small" type="danger" :icon="Delete" circle @click="deleteTechnician(tech)" />
                        </div>
                    </div>
                </div>

                <!-- Footer Paginación -->
                <div class="flex flex-col sm:flex-row justify-between items-center p-4 bg-gray-50 dark:bg-[#252529] border-t border-gray-100 dark:border-[#2b2b2e]">
                    <div class="text-xs text-gray-500 mb-3 sm:mb-0">
                        Mostrando {{ technicians.from }} a {{ technicians.to }} de {{ technicians.total }} registros
                    </div>
                    
                    <el-pagination
                        v-model:current-page="technicians.current_page"
                        :page-size="technicians.per_page"
                        :total="technicians.total"
                        layout="prev, pager, next"
                        background
                        @current-change="handlePageChange"
                        class="!p-0"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.el-table .cursor-pointer) {
    cursor: pointer;
}

.el-dropdown-link:focus {
    outline: none;
}

:deep(.el-dropdown-menu__item a) {
    display: flex;
    align-items: center;
    width: 100%;
    color: inherit;
    text-decoration: none;
}
</style>