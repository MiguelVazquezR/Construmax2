<script setup>
import { ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { debounce } from 'lodash';

const props = defineProps({
    users: Array,
    settings: Object,
    types: Object,
    roles: Array,
    filters: Object,
});

const selectedUser = ref(null);

const form = useForm({
    user_id: null,
    notification_types: [],
});

const search = ref(props.filters?.search || '');
const roleFilter = ref(props.filters?.role || '');

const fetchData = debounce(() => {
    router.get(route('config.notifications.index'), {
        search: search.value,
        role: roleFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 300);

watch([search, roleFilter], fetchData);

const getUserTypes = (userId) => {
    const result = [];
    if (props.settings) {
        Object.entries(props.settings).forEach(([type, settings]) => {
            if (settings.some(s => s.user_id === userId && s.is_active)) {
                result.push(type);
            }
        });
    }
    return result;
};

const openEdit = (user) => {
    selectedUser.value = user;
    form.user_id = user.id;
    form.notification_types = [...getUserTypes(user.id)];
};

const closeEdit = () => {
    selectedUser.value = null;
    form.reset();
};

const submit = () => {
    form.post(route('config.notifications.store'), {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Configuración de notificaciones guardada.');
            closeEdit();
        },
    });
};

const resetUser = (user, event) => {
    event.stopPropagation();

    ElMessageBox.confirm(
        `¿Eliminar todas las configuraciones de notificación de ${user.name}?`,
        'Restablecer notificaciones',
        {
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            type: 'warning',
        }
    ).then(() => {
        router.delete(route('config.notifications.delete-user', user.id), {
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success(`Configuraciones de ${user.name} eliminadas.`);
            },
        });
    }).catch(() => {});
};

const isUserConfigured = (userId) => {
    return getUserTypes(userId).length > 0;
};

const getUserRole = (user) => {
    return user.roles?.[0]?.name || '—';
};
</script>

<template>
    <AppLayout title="Configuración de notificaciones">
        <div class="space-y-6">

            <!-- Header -->
            <div class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Configuración de notificaciones</h2>
                <p class="text-sm text-gray-500 mt-1">Configura qué usuarios reciben cada tipo de notificación. Solo los usuarios con un correo electrónico válido recibirán notificaciones por email.</p>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-[#1e1e20] p-4 rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] flex flex-col sm:flex-row gap-3">
                <el-input v-model="search" placeholder="Buscar por nombre o email..." clearable prefix-icon="Search" class="sm:w-80" />
                <el-select v-model="roleFilter" placeholder="Filtrar por rol" clearable class="sm:w-56">
                    <el-option v-for="role in roles" :key="role.name" :label="role.name" :value="role.name" />
                </el-select>
            </div>

            <!-- Users grid -->
            <div v-if="users.length === 0" class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-8 text-center text-gray-400">
                No se encontraron usuarios con los filtros aplicados.
            </div>
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="user in users"
                    :key="user.id"
                    class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-4 hover:border-[#f26c17] dark:hover:border-[#f26c17] transition-colors cursor-pointer"
                    @click="openEdit(user)"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-800 dark:text-white text-sm truncate">{{ user.name }}</p>
                            <p class="text-xs text-gray-400 mt-0.5 truncate">
                                {{ user.email || 'Sin correo registrado' }}
                                <el-tag v-if="!user.email" type="danger" size="small" class="ml-1">Sin email</el-tag>
                            </p>
                            <p class="text-[10px] text-gray-400 mt-0.5">{{ getUserRole(user) }}</p>
                        </div>
                        <div class="flex items-center gap-2 ml-2 shrink-0">
                            <el-tag v-if="isUserConfigured(user.id)" type="success" size="small" effect="light">
                                {{ getUserTypes(user.id).length }}
                            </el-tag>
                            <el-tag v-else type="info" size="small" effect="plain">—</el-tag>
                            <el-button
                                v-if="isUserConfigured(user.id)"
                                text
                                size="small"
                                type="danger"
                                icon="Delete"
                                @click="resetUser(user, $event)"
                                title="Restablecer notificaciones de este usuario"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit modal -->
            <el-dialog
                :model-value="!!selectedUser"
                @update:model-value="closeEdit"
                :title="`Notificar a: ${selectedUser?.name}`"
                width="500px"
            >
                <div v-if="selectedUser" class="space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <strong>Email:</strong> {{ selectedUser.email || 'Sin registrar — no se enviarán notificaciones por correo.' }}
                    </p>
                    <el-alert
                        v-if="!selectedUser.email"
                        title="Este usuario no tiene correo registrado. No se pueden enviar notificaciones por email."
                        type="warning"
                        :closable="false"
                        show-icon
                    />

                    <div class="space-y-3">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Tipos de notificación:</p>
                        <el-checkbox-group v-model="form.notification_types" class="flex flex-col gap-2">
                            <el-checkbox
                                v-for="(label, type) in types"
                                :key="type"
                                :label="type"
                                :value="type"
                            >
                                {{ label }}
                            </el-checkbox>
                        </el-checkbox-group>
                    </div>
                </div>

                <template #footer>
                    <el-button @click="closeEdit">Cancelar</el-button>
                    <el-button type="primary" @click="submit" :loading="form.processing">
                        Guardar configuración
                    </el-button>
                </template>
            </el-dialog>

        </div>
    </AppLayout>
</template>
