<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash'; 

const props = defineProps({
    users: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');
const perPage = ref(parseInt(props.filters.perPage) || 10);

// Sincronización con el servidor (Búsqueda y Paginación)
const handleSearch = debounce((val) => {
    router.get(route('users.index'), { search: val, perPage: perPage.value }, {
        preserveState: true,
        replace: true,
    });
}, 300);

const handleSizeChange = (val) => {
    perPage.value = val;
    router.get(route('users.index'), { search: search.value, perPage: val }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const handlePageChange = (val) => {
    router.get(route('users.index'), { search: search.value, perPage: perPage.value, page: val }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Acciones
const toggleStatus = (user) => {
    router.put(route('users.toggle-status', user.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            // Notificación visual opcional con Element Plus
        }
    });
};

const deleteUser = (user) => {
    if(!confirm('¿Estás seguro de eliminar este usuario?')) return;
    router.delete(route('users.destroy', user.id));
};

watch(search, (val) => {
    handleSearch(val);
});
</script>

<template>
    <AppLayout title="Gestión de Usuarios">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                Usuarios
            </h2>
        </template>

        <div class="space-y-4">
            <!-- Barra de Herramientas -->
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <div class="w-full sm:w-1/3">
                    <el-input
                        v-model="search"
                        placeholder="Buscar por nombre o email..."
                        clearable
                        prefix-icon="Search"
                        class="w-full"
                    />
                </div>
                <div class="flex gap-2 w-full sm:w-auto justify-end">
                    <el-select v-model="perPage" placeholder="Mostrar" style="width: 110px" @change="handleSizeChange">
                        <el-option label="10 / pág" :value="10" />
                        <el-option label="20 / pág" :value="20" />
                        <el-option label="50 / pág" :value="50" />
                    </el-select>
                    <el-button type="primary" color="#f26c17" icon="Plus">
                        Nuevo Usuario
                    </el-button>
                </div>
            </div>

            <!-- VISTA DE ESCRITORIO (Tabla) -->
            <div class="hidden md:block bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm overflow-hidden border border-gray-100 dark:border-[#2b2b2e]">
                <el-table :data="users.data" style="width: 100%" stripe>
                    <el-table-column prop="id" label="ID" width="60" />
                    
                    <el-table-column label="Usuario" min-width="200">
                        <template #default="scope">
                            <div class="flex items-center gap-3">
                                <el-avatar :size="32" :src="scope.row.profile_photo_url" />
                                <div class="flex flex-col">
                                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ scope.row.name }}</span>
                                    <span class="text-xs text-gray-500">{{ scope.row.email }}</span>
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Departamento" min-width="150">
                        <template #default="scope">
                            {{ scope.row.employee ? scope.row.employee.department : 'N/A' }}
                        </template>
                    </el-table-column>

                    <el-table-column label="Estado" width="100" align="center">
                        <template #default="scope">
                            <el-tag :type="scope.row.is_active ? 'success' : 'danger'" size="small" effect="dark">
                                {{ scope.row.is_active ? 'Activo' : 'Baja' }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Acciones" width="100" align="center" fixed="right">
                        <template #default="scope">
                            <el-dropdown trigger="click">
                                <span class="el-dropdown-link cursor-pointer p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full transition">
                                    <el-icon><MoreFilled /></el-icon>
                                </span>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item icon="Edit">Editar</el-dropdown-item>
                                        <el-dropdown-item icon="SwitchButton" @click="toggleStatus(scope.row)">
                                            {{ scope.row.is_active ? 'Dar de Baja' : 'Reactivar' }}
                                        </el-dropdown-item>
                                        <el-dropdown-item divided icon="Delete" class="text-red-500" @click="deleteUser(scope.row)">
                                            Eliminar
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </template>
                    </el-table-column>
                </el-table>
            </div>

            <!-- VISTA MÓVIL (Tarjetas) -->
            <div class="md:hidden space-y-3">
                <div v-for="user in users.data" :key="user.id" class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex items-center gap-3">
                            <el-avatar :size="40" :src="user.profile_photo_url" />
                            <div>
                                <h3 class="font-bold text-gray-800 dark:text-gray-200">{{ user.name }}</h3>
                                <p class="text-xs text-gray-500">{{ user.email }}</p>
                            </div>
                        </div>
                        <el-tag :type="user.is_active ? 'success' : 'danger'" size="small" effect="dark">
                            {{ user.is_active ? 'Activo' : 'Baja' }}
                        </el-tag>
                    </div>
                    
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-4 border-t border-gray-100 dark:border-gray-800 pt-2">
                        <p><span class="font-semibold">Depto:</span> {{ user.employee ? user.employee.department : 'Sin asignar' }}</p>
                        <p><span class="font-semibold">Puesto:</span> {{ user.employee ? user.employee.position : 'Sin asignar' }}</p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <el-button size="small" icon="Edit" circle />
                        <el-button size="small" :type="user.is_active ? 'warning' : 'success'" icon="SwitchButton" circle @click="toggleStatus(user)" />
                        <el-button size="small" type="danger" icon="Delete" circle @click="deleteUser(user)" />
                    </div>
                </div>
            </div>

            <!-- Paginación Común -->
            <div class="flex justify-center mt-6 bg-white dark:bg-[#1e1e20] p-3 rounded-lg shadow-sm">
                <el-pagination
                    v-model:current-page="users.current_page"
                    :page-size="users.per_page"
                    :total="users.total"
                    layout="prev, pager, next"
                    background
                    @current-change="handlePageChange"
                />
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Ajustes finos para tabla y dropdowns */
.el-dropdown-link:focus {
    outline: none;
}
</style>