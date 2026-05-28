<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import { ElMessageBox, ElMessage } from 'element-plus';
import { 
    Search, 
    Plus, 
    OfficeBuilding, 
    MoreFilled, 
    View, 
    Edit, 
    Delete,
    Check,
    Close,
    User,
    Location
} from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    customers: Object, // Paginado
    filters: Object,
});

const search = ref(props.filters.search || '');
const region = ref(props.filters.region || '');
const contact = ref(props.filters.contact || '');
const perPage = ref(parseInt(props.filters.perPage) || 10);

// Sincronización con el servidor
const handleSearch = debounce(() => {
    router.get(route('customers.index'), { 
        search: search.value, 
        region: region.value,
        contact: contact.value,
        perPage: perPage.value 
    }, {
        preserveState: true,
        replace: true,
    });
}, 400);

const handleSizeChange = (val) => {
    perPage.value = val;
    handleSearch();
};

const handlePageChange = (val) => {
    router.get(route('customers.index'), { 
        search: search.value, 
        region: region.value,
        contact: contact.value,
        perPage: perPage.value, 
        page: val 
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Navegación al detalle
const handleRowClick = (row) => {
    router.visit(route('customers.show', row.id));
};

// Toggle Status
const toggleStatus = (customer) => {
    router.put(route('customers.toggle-status', customer.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success(`Cliente ${customer.is_active ? 'activado' : 'inactivado'} correctamente`);
        },
        onError: () => {
            customer.is_active = !customer.is_active;
            ElMessage.error('No se pudo actualizar el estatus');
        }
    });
};

// Acciones
const deleteCustomer = (customer) => {
    ElMessageBox.confirm(
        `¿Estás seguro de eliminar al cliente "${customer.name}"? Esta acción borrará también sus contactos y sucursales.`,
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

// Observamos los 3 filtros principales
watch([search, region, contact], () => {
    handleSearch();
});
</script>

<template>
    <AppLayout title="Gestión de clientes">
        <div class="space-y-4">
            <!-- Barra de Herramientas -->
            <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                
                <!-- Filtros -->
                <div class="w-full xl:flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
                    <el-input
                        v-model="search"
                        placeholder="Buscar por cliente o RFC..."
                        clearable
                        :prefix-icon="Search"
                    />
                    <el-input
                        v-model="contact"
                        placeholder="Filtrar por encargado..."
                        clearable
                        :prefix-icon="User"
                    />
                    <el-input
                        v-model="region"
                        placeholder="Filtrar por región o sucursal..."
                        clearable
                        :prefix-icon="Location"
                    />
                </div>

                <!-- Paginador y Botón Nuevo -->
                <div class="flex gap-2 w-full xl:w-auto justify-end xl:shrink-0">
                    <el-select v-model="perPage" placeholder="Mostrar" style="width: 110px" @change="handleSizeChange">
                        <el-option label="10 / pág" :value="10" />
                        <el-option label="20 / pág" :value="20" />
                        <el-option label="50 / pág" :value="50" />
                    </el-select>
                    <Link v-if="can('customers.create')" :href="route('customers.create')">
                        <el-button type="primary" color="#f26c17" :icon="Plus">
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
                        row-key="id"
                        row-class-name="cursor-pointer hover:bg-gray-50 dark:hover:bg-[#27272a] transition-colors"
                        @row-click="handleRowClick"
                    >
                        <!-- Columna Expandible para Sucursales y Contactos -->
                        <el-table-column type="expand">
                            <template #default="scope">
                                <div class="m-6 p-5 bg-gray-50/80 dark:bg-[#252529] border border-gray-200 dark:border-gray-800 rounded-xl cursor-default" @click.stop>
                                    <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2">
                                        <el-icon class="text-primary"><User /></el-icon> 
                                        Contactos y sucursales asignadas
                                    </h4>
                                    
                                    <div v-if="scope.row.contacts && scope.row.contacts.length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                                        <div 
                                            v-for="(contacto, idx) in scope.row.contacts" 
                                            :key="idx" 
                                            class="p-5 bg-white dark:bg-[#1e1e20] border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm"
                                        >
                                            <div class="flex justify-between items-start mb-4">
                                                <div>
                                                    <p class="font-bold text-sm text-gray-900 dark:text-white">{{ contacto.name }}</p>
                                                    <p class="text-xs font-medium text-primary">{{ contacto.position }}</p>
                                                </div>
                                                <div class="text-right text-xs text-gray-500 space-y-0.5">
                                                    <p>{{ contacto.phone }}</p>
                                                    <p>{{ contacto.email }}</p>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800 space-y-3">
                                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                                    Sucursales a cargo:
                                                </p>
                                                
                                                <div v-if="contacto.branches && contacto.branches.length > 0">
                                                    <div 
                                                        v-for="sucursal in contacto.branches" 
                                                        :key="sucursal.id" 
                                                        class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs bg-gray-50 dark:bg-[#2b2b2e] p-3 rounded-lg border border-gray-100 dark:border-gray-700 hover:bg-orange-50/50 dark:hover:bg-[#2a2a2d] transition-colors mb-2 last:mb-0"
                                                    >
                                                        <div class="flex items-center gap-3">
                                                            <div class="bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 p-1.5 rounded-md shrink-0">
                                                                <el-icon :size="16"><Location /></el-icon>
                                                            </div>
                                                            <div class="flex flex-col gap-0.5">
                                                                <span class="font-bold text-gray-800 dark:text-gray-200 text-sm">
                                                                    {{ sucursal.branch_name || 'Sin nombre definido' }}
                                                                </span>
                                                                <span class="text-gray-500 dark:text-gray-400">
                                                                    <span class="font-medium text-gray-600 dark:text-gray-300">Unidad:</span> {{ sucursal.unit }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="flex flex-col sm:items-end pl-10 sm:pl-0 text-gray-500 dark:text-gray-400">
                                                            <span class="font-medium">{{ sucursal.region }}</span>
                                                            <span>{{ sucursal.country }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div v-else class="text-xs text-gray-500 italic">
                                                    No hay sucursales asignadas a este contacto.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-sm text-gray-500 italic py-2">
                                        No hay contactos registrados para este cliente.
                                    </div>
                                </div>
                            </template>
                        </el-table-column>

                        <el-table-column prop="id" label="ID" width="60" />
                        
                        <el-table-column label="Cliente" min-width="220">
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

                        <el-table-column prop="rfc" label="RFC" width="140">
                            <template #default="scope">
                                <span class="font-mono text-gray-600 dark:text-gray-400 text-xs bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                                    {{ scope.row.rfc }}
                                </span>
                            </template>
                        </el-table-column>

                        <el-table-column label="Sucursales" min-width="120">
                            <template #default="scope">
                                <el-tag size="small" type="info" effect="light" class="mr-1">
                                    <span>
                                        {{ scope.row.branches?.length || 0 }} registradas
                                    </span>
                                </el-tag>
                            </template>
                        </el-table-column>

                        <el-table-column label="Estado" width="120" align="center">
                            <template #default="scope">
                                <div @click.stop>
                                    <el-switch
                                        v-if="can('customers.edit')"
                                        v-model="scope.row.is_active"
                                        inline-prompt
                                        :active-icon="Check"
                                        :inactive-icon="Close"
                                        active-text="Activo"
                                        inactive-text="Baja"
                                        style="--el-switch-on-color: #13ce66; --el-switch-off-color: #ff4949"
                                        @change="toggleStatus(scope.row)"
                                    />
                                    <el-tag v-else :type="scope.row.is_active ? 'success' : 'danger'" size="small" effect="dark">
                                        {{ scope.row.is_active ? 'Activo' : 'Inactivo' }}
                                    </el-tag>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Acciones -->
                        <el-table-column label="Acciones" width="90" align="center" fixed="right">
                            <template #default="scope">
                                <div @click.stop>
                                    <el-dropdown trigger="click">
                                        <span class="el-dropdown-link cursor-pointer p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full transition display-inline-block">
                                            <el-icon><MoreFilled /></el-icon>
                                        </span>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <Link :href="route('customers.show', scope.row.id)">
                                                    <el-dropdown-item :icon="View">Ver detalles</el-dropdown-item>
                                                </Link>
                                                
                                                <Link v-if="can('customers.edit')" :href="route('customers.edit', scope.row.id)">
                                                    <el-dropdown-item :icon="Edit">Editar</el-dropdown-item>
                                                </Link>
                                                
                                                <el-dropdown-item v-if="can('customers.delete')" divided :icon="Delete" class="text-red-500" @click="deleteCustomer(scope.row)">
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
                            
                            <div @click.stop>
                                 <el-switch
                                    v-if="can('customers.edit')"
                                    v-model="customer.is_active"
                                    size="small"
                                    style="--el-switch-on-color: #13ce66; --el-switch-off-color: #ff4949"
                                    @change="toggleStatus(customer)"
                                />
                                <el-tag v-else :type="customer.is_active ? 'success' : 'danger'" size="small" effect="dark">
                                    {{ customer.is_active ? 'Act' : 'Baja' }}
                                </el-tag>
                            </div>
                        </div>
                        
                        <div class="flex flex-col gap-1 text-sm text-gray-600 dark:text-gray-400 mb-3 pt-2 border-t border-gray-50 dark:border-gray-800">
                            <p class="flex justify-between">
                                <span class="text-gray-400 text-xs uppercase">RFC:</span> 
                                <span class="font-mono text-xs">{{ customer.rfc }}</span>
                            </p>
                            <p class="flex justify-between">
                                <span class="text-gray-400 text-xs uppercase">Sucursales:</span> 
                                <span class="text-xs">{{ customer.branches?.length || 0 }} registradas</span>
                            </p>
                        </div>

                        <!-- Botones móviles -->
                        <div class="flex justify-end gap-2" @click.stop>
                            <Link :href="route('customers.edit', customer.id)">
                                <el-button size="small" :icon="Edit" circle />
                            </Link>
                            <el-button size="small" type="danger" :icon="Delete" circle @click="deleteCustomer(customer)" />
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

:deep(.el-table__expanded-cell) {
    padding: 0 !important;
    background-color: transparent !important;
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