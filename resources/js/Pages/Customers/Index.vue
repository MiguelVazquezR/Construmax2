<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import { ElMessageBox, ElMessage } from 'element-plus';

const props = defineProps({
    customers: Object, // Paginado
    filters: Object,
});

const search = ref(props.filters.search || '');
const perPage = ref(parseInt(props.filters.perPage) || 10);

// Sincronización con el servidor
const handleSearch = debounce((val) => {
    router.get(route('customers.index'), { search: val, perPage: perPage.value }, {
        preserveState: true,
        replace: true,
    });
}, 300);

const handleSizeChange = (val) => {
    perPage.value = val;
    router.get(route('customers.index'), { search: search.value, perPage: val }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const handlePageChange = (val) => {
    router.get(route('customers.index'), { search: search.value, perPage: perPage.value, page: val }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Navegación al detalle
const handleRowClick = (row) => {
    router.visit(route('customers.show', row.id));
};

// Acciones
const deleteCustomer = (customer) => {
    ElMessageBox.confirm(
        `¿Estás seguro de eliminar al cliente "${customer.name}"? Esta acción borrará también sus contactos asociados.`,
        'Eliminar cliente',
        {
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            type: 'error',
        }
    )
    .then(() => {
        router.delete(route('customers.destroy', customer.id), {
             onSuccess: () => {
                ElMessage({
                    type: 'success',
                    message: 'Cliente eliminado correctamente',
                });
            }
        });
    })
    .catch(() => {});
};

watch(search, (val) => {
    handleSearch(val);
});
</script>

<template>
    <AppLayout title="Gestión de clientes">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                Clientes
            </h2>
        </template>

        <div class="space-y-4">
            <!-- Barra de Herramientas -->
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <div class="w-full sm:w-1/3">
                    <el-input
                        v-model="search"
                        placeholder="Buscar por nombre, razón social o RFC..."
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
                    <Link :href="route('customers.create')">
                        <el-button type="primary" color="#f26c17" icon="Plus">
                            Nuevo cliente
                        </el-button>
                    </Link>
                </div>
            </div>

            <!-- CONTENEDOR UNIFICADO (Tabla + Paginación) -->
            <div class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                
                <!-- VISTA DE ESCRITORIO (Tabla) -->
                <div class="hidden md:block">
                    <el-table 
                        :data="customers.data" 
                        style="width: 100%" 
                        stripe
                        @row-click="handleRowClick"
                        row-class-name="cursor-pointer hover:bg-gray-50 dark:hover:bg-[#27272a] transition-colors"
                    >
                        <el-table-column prop="id" label="ID" width="60" />
                        
                        <el-table-column label="Cliente" min-width="250">
                            <template #default="scope">
                                <div class="flex items-center gap-3">
                                    <div class="bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 p-2 rounded-lg">
                                        <el-icon :size="20"><OfficeBuilding /></el-icon>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 dark:text-gray-200 text-sm">{{ scope.row.name }}</span>
                                        <span class="text-xs text-gray-500">{{ scope.row.business_name }}</span>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>

                        <el-table-column prop="rfc" label="RFC" width="150">
                            <template #default="scope">
                                <span class="font-mono text-gray-600 dark:text-gray-400 text-xs bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                                    {{ scope.row.rfc }}
                                </span>
                            </template>
                        </el-table-column>

                        <el-table-column label="Condición de pago" min-width="160">
                            <template #default="scope">
                                <span class="text-sm text-gray-600 dark:text-gray-400">{{ scope.row.payment_condition }}</span>
                            </template>
                        </el-table-column>

                        <el-table-column label="Estado" width="100" align="center">
                            <template #default="scope">
                                <el-tag :type="scope.row.is_active ? 'success' : 'danger'" size="small" effect="dark">
                                    {{ scope.row.is_active ? 'Activo' : 'Inactivo' }}
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
                                                <Link :href="route('customers.show', scope.row.id)">
                                                    <el-dropdown-item icon="View">Ver detalles</el-dropdown-item>
                                                </Link>
                                                
                                                <Link :href="route('customers.edit', scope.row.id)">
                                                    <el-dropdown-item icon="Edit">Editar</el-dropdown-item>
                                                </Link>
                                                
                                                <el-dropdown-item divided icon="Delete" class="text-red-500" @click="deleteCustomer(scope.row)">
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
                    <div v-for="customer in customers.data" :key="customer.id" class="p-4 border-b border-gray-100 dark:border-[#2b2b2e] last:border-0 hover:bg-gray-50 dark:hover:bg-[#252529] transition-colors cursor-pointer" @click="handleRowClick(customer)">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center gap-3">
                                <div class="bg-orange-50 dark:bg-orange-900/20 text-orange-500 p-2 rounded-md">
                                    <el-icon :size="18"><OfficeBuilding /></el-icon>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800 dark:text-gray-200 text-sm">{{ customer.name }}</h3>
                                    <p class="text-xs text-gray-500">{{ customer.business_name }}</p>
                                </div>
                            </div>
                            <el-tag :type="customer.is_active ? 'success' : 'danger'" size="small" effect="dark">
                                {{ customer.is_active ? 'Activo' : 'Baja' }}
                            </el-tag>
                        </div>
                        
                        <div class="flex flex-col gap-1 text-sm text-gray-600 dark:text-gray-400 mb-4 pt-2 border-t border-gray-50 dark:border-gray-800">
                            <p class="flex justify-between">
                                <span class="text-gray-400 text-xs uppercase">RFC:</span> 
                                <span class="font-mono text-xs">{{ customer.rfc }}</span>
                            </p>
                            <p class="flex justify-between">
                                <span class="text-gray-400 text-xs uppercase">Pago:</span> 
                                <span>{{ customer.payment_condition }}</span>
                            </p>
                        </div>

                        <!-- Botones móviles -->
                        <div class="flex justify-end gap-2" @click.stop>
                            <Link :href="route('customers.edit', customer.id)">
                                <el-button size="small" icon="Edit" circle />
                            </Link>
                            <el-button size="small" type="danger" icon="Delete" circle @click="deleteCustomer(customer)" />
                        </div>
                    </div>
                </div>

                <!-- Footer Paginación -->
                <div class="flex flex-col sm:flex-row justify-between items-center p-4 bg-gray-50 dark:bg-[#252529] border-t border-gray-100 dark:border-[#2b2b2e]">
                    <div class="text-xs text-gray-500 mb-3 sm:mb-0">
                        Mostrando {{ customers.from }} a {{ customers.to }} de {{ customers.total }} registros
                    </div>
                    
                    <el-pagination
                        v-model:current-page="customers.current_page"
                        :page-size="customers.per_page"
                        :total="customers.total"
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