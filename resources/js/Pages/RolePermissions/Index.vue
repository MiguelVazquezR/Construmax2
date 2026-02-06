<script setup>
import { ref, reactive, computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';

const props = defineProps({
    roles: Array,
    allPermissions: Array,
    permissionsGrouped: Object, // { 'Usuarios': [p1, p2], 'Ventas': [p3] }
});

const page = usePage();
const isDeveloper = computed(() => page.props.auth.user.id === 1);
const activeTab = ref('roles');

// Helper para obtener categorías únicas de un rol
const getRoleCategories = (role) => {
    if (!role.permissions || role.permissions.length === 0) {
        return 'Sin permisos asignados';
    }
    // Extraer categorías únicas
    const categories = [...new Set(role.permissions.map(p => p.category))];
    return categories.join(', ');
};

// ==========================================
// LÓGICA DE ROLES
// ==========================================
const showRoleModal = ref(false);
const editingRole = ref(false);
const roleFormRef = ref(null);

const roleForm = useForm({
    id: null,
    name: '',
    permissions: [], // Array de IDs
});

const roleRules = reactive({
    name: [{ required: true, message: 'El nombre del rol es obligatorio', trigger: 'blur' }],
});

const openCreateRole = () => {
    editingRole.value = false;
    roleForm.reset();
    roleForm.permissions = [];
    showRoleModal.value = true;
};

const openEditRole = (role) => {
    editingRole.value = true;
    roleForm.id = role.id;
    roleForm.name = role.name;
    // Extraer IDs de permisos asignados
    roleForm.permissions = role.permissions.map(p => p.id);
    showRoleModal.value = true;
};

const submitRole = () => {
    if (!roleFormRef.value) return;
    
    roleFormRef.value.validate((valid) => {
        if (valid) {
            if (editingRole.value) {
                roleForm.put(route('config.roles.update', roleForm.id), {
                    onSuccess: () => { showRoleModal.value = false; ElMessage.success('Rol actualizado'); }
                });
            } else {
                roleForm.post(route('config.roles.store'), {
                    onSuccess: () => { showRoleModal.value = false; ElMessage.success('Rol creado'); }
                });
            }
        }
    });
};

const deleteRole = (role) => {
    ElMessageBox.confirm(
        `¿Eliminar el rol "${role.name}"? Esto afectará a los usuarios que lo tengan asignado.`,
        'Advertencia',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(() => {
        router.delete(route('config.roles.destroy', role.id), {
            onSuccess: () => ElMessage.success('Rol eliminado')
        });
    }).catch(() => {});
};

// ==========================================
// LÓGICA DE PERMISOS (Dev Only)
// ==========================================
const showPermissionModal = ref(false);
const editingPermission = ref(false);
const permFormRef = ref(null);

const permForm = useForm({
    id: null,
    name: '',       // users.create
    category: '',   // Usuarios
    description: '' // Permite crear nuevos usuarios
});

const permRules = reactive({
    name: [{ required: true, message: 'La clave única es obligatoria', trigger: 'blur' }],
    category: [{ required: true, message: 'La categoría es obligatoria', trigger: 'blur' }],
    description: [{ required: true, message: 'La descripción es obligatoria', trigger: 'blur' }],
});

const openCreatePermission = () => {
    editingPermission.value = false;
    permForm.reset();
    showPermissionModal.value = true;
};

const openEditPermission = (perm) => {
    editingPermission.value = true;
    permForm.id = perm.id;
    permForm.name = perm.name;
    permForm.category = perm.category;
    permForm.description = perm.description;
    showPermissionModal.value = true;
};

const submitPermission = () => {
    if (!permFormRef.value) return;

    permFormRef.value.validate((valid) => {
        if (valid) {
            if (editingPermission.value) {
                permForm.put(route('config.permissions.update', permForm.id), {
                    onSuccess: () => { showPermissionModal.value = false; ElMessage.success('Permiso actualizado'); }
                });
            } else {
                permForm.post(route('config.permissions.store'), {
                    onSuccess: () => { showPermissionModal.value = false; ElMessage.success('Permiso creado'); }
                });
            }
        }
    });
};

const deletePermission = (perm) => {
    ElMessageBox.confirm(
        `¿Eliminar permiso "${perm.name}"?`,
        'Solo desarrolladores',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'error' }
    ).then(() => {
        router.delete(route('config.permissions.destroy', perm.id), {
            onSuccess: () => ElMessage.success('Permiso eliminado')
        });
    }).catch(() => {});
};
</script>

<template>
    <AppLayout title="Configuración de roles y permisos">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                Roles y permisos
            </h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                    
                    <el-tabs v-model="activeTab" class="demo-tabs">
                        
                        <!-- TAB: ROLES -->
                        <el-tab-pane label="Roles de usuario" name="roles">
                            <div class="flex justify-between items-center mb-4">
                                <p class="text-sm text-gray-500">Administra los roles y sus accesos al sistema.</p>
                                <el-button type="primary" color="#f26c17" icon="Plus" @click="openCreateRole">
                                    Crear rol
                                </el-button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div v-for="role in roles" :key="role.id" class="border border-gray-200 dark:border-[#2b2b2e] rounded-lg p-4 hover:shadow-md transition-shadow bg-gray-50 dark:bg-[#252529] flex flex-col justify-between">
                                    <div>
                                        <div class="flex justify-between items-start mb-2">
                                            <h3 class="font-bold text-lg text-gray-800 dark:text-white">{{ role.name }}</h3>
                                            <el-tag size="small" effect="plain" type="info">{{ role.permissions.length }} permisos</el-tag>
                                        </div>
                                        
                                        <!-- Lista de Módulos (Categorías) -->
                                        <div class="mb-4">
                                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Acceso a módulos:</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 leading-snug line-clamp-3" :title="getRoleCategories(role)">
                                                {{ getRoleCategories(role) }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex justify-end gap-2 border-t border-gray-200 dark:border-[#3f3f46] pt-3 mt-auto">
                                        <el-button size="small" icon="Edit" @click="openEditRole(role)">Editar</el-button>
                                        <el-button size="small" type="danger" icon="Delete" plain @click="deleteRole(role)" />
                                    </div>
                                </div>
                            </div>
                        </el-tab-pane>

                        <!-- TAB: PERMISOS (Matriz del sistema) -->
                        <el-tab-pane label="Definición de permisos" name="permissions">
                            <div class="flex justify-between items-center mb-4">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm text-gray-500">Listado técnico de permisos del sistema.</p>
                                    <el-tag v-if="!isDeveloper" type="warning" size="small">Solo lectura</el-tag>
                                    <el-tag v-else type="success" size="small">Modo desarrollador</el-tag>
                                </div>
                                <el-button v-if="isDeveloper" type="primary" color="#f26c17" icon="Plus" @click="openCreatePermission">
                                    Nuevo permiso
                                </el-button>
                            </div>

                            <el-table :data="allPermissions" style="width: 100%" height="500" stripe>
                                <el-table-column prop="category" label="Categoría" width="150" sortable />
                                <el-table-column prop="name" label="Clave (users.create)" min-width="180" sortable>
                                    <template #default="scope">
                                        <code class="bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded text-xs">{{ scope.row.name }}</code>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="description" label="Descripción" min-width="250" />
                                <el-table-column v-if="isDeveloper" label="Acciones" width="120" align="right">
                                    <template #default="scope">
                                        <el-button circle size="small" icon="Edit" @click="openEditPermission(scope.row)" />
                                        <el-button circle size="small" type="danger" icon="Delete" @click="deletePermission(scope.row)" />
                                    </template>
                                </el-table-column>
                            </el-table>
                        </el-tab-pane>
                    </el-tabs>
                </div>

            </div>
        </div>

        <!-- MODAL: CREAR/EDITAR ROL -->
        <el-dialog
            v-model="showRoleModal"
            :title="editingRole ? 'Editar rol' : 'Nuevo rol'"
            width="600px"
            destroy-on-close
        >
            <el-form :model="roleForm" :rules="roleRules" ref="roleFormRef" label-position="top">
                <el-form-item label="Nombre del rol" prop="name">
                    <el-input v-model="roleForm.name" placeholder="Ej. Gerente de ventas" />
                </el-form-item>

                <div class="mt-6">
                    <label class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-2 block">Asignar permisos</label>
                    <el-scrollbar height="300px" class="border border-gray-200 dark:border-[#3f3f46] rounded-md p-4 bg-gray-50 dark:bg-[#252529]">
                        <div v-for="(perms, category) in permissionsGrouped" :key="category" class="mb-5 last:mb-0">
                            <h4 class="text-xs font-bold uppercase text-primary mb-2 tracking-wider">{{ category }}</h4>
                            <div class="grid grid-cols-1 gap-2">
                                <el-checkbox-group v-model="roleForm.permissions">
                                    <el-checkbox 
                                        v-for="perm in perms" 
                                        :key="perm.id" 
                                        :label="perm.id" 
                                        :value="perm.id"
                                    >
                                        <span class="text-gray-700 dark:text-gray-300">{{ perm.description }}</span>
                                        <span class="text-xs text-gray-400 ml-1">({{ perm.name }})</span>
                                    </el-checkbox>
                                </el-checkbox-group>
                            </div>
                        </div>
                        <div v-if="allPermissions.length === 0" class="text-center text-gray-400 py-4">
                            No hay permisos registrados en el sistema.
                        </div>
                    </el-scrollbar>
                </div>
            </el-form>
            <template #footer>
                <div class="dialog-footer">
                    <el-button @click="showRoleModal = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" @click="submitRole" :loading="roleForm.processing">
                        Guardar rol
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- MODAL: CREAR/EDITAR PERMISO (Solo Dev) -->
        <el-dialog
            v-model="showPermissionModal"
            :title="editingPermission ? 'Editar permiso (sistema)' : 'Nuevo permiso (sistema)'"
            width="500px"
        >
            <el-form :model="permForm" :rules="permRules" ref="permFormRef" label-position="top">
                <el-alert
                    title="Zona de desarrollador"
                    type="warning"
                    description="Modificar las claves únicas puede romper la lógica del código fuente. Ten cuidado."
                    show-icon
                    :closable="false"
                    class="mb-4"
                />
                
                <el-form-item label="Categoría (módulo)" prop="category">
                    <el-input v-model="permForm.category" placeholder="Ej. Usuarios" />
                </el-form-item>

                <el-form-item label="Clave única (código)" prop="name">
                    <el-input v-model="permForm.name" placeholder="Ej. users.create">
                        <template #prepend>
                            <el-icon><Key /></el-icon>
                        </template>
                    </el-input>
                    <span class="text-xs text-gray-400">Esta clave se usa en el código: @can('users.create')</span>
                </el-form-item>

                <el-form-item label="Descripción legible" prop="description">
                    <el-input v-model="permForm.description" type="textarea" placeholder="Ej. Permite registrar nuevos usuarios en el sistema" />
                </el-form-item>
            </el-form>
            <template #footer>
                <div class="dialog-footer">
                    <el-button @click="showPermissionModal = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" @click="submitPermission" :loading="permForm.processing">
                        Guardar definición
                    </el-button>
                </div>
            </template>
        </el-dialog>

    </AppLayout>
</template>

<style scoped>
:deep(.el-checkbox__label) {
    display: inline-flex;
    align-items: center;
}
</style>