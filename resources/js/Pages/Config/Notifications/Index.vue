<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';

const props = defineProps({
    users: Array,
    settings: Object,
    types: Object,
});

const selectedUser = ref(null);

const form = useForm({
    user_id: null,
    notification_types: [],
});

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

const isUserConfigured = (userId) => {
    return getUserTypes(userId).length > 0;
};

const typeLabel = (type) => {
    const labels = {
        'ticket.needs-catalog': 'Ticket necesita catálogo de costos',
        'catalog.created': 'Catálogo de costos generado',
        'ticket.needs-invoice': 'Ticket listo para facturar',
        'invoice.overdue': 'Vencimiento de factura',
    };
    return labels[type] || type;
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

            <!-- Users grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="user in users"
                    :key="user.id"
                    class="bg-white dark:bg-[#1e1e20] rounded-lg shadow-sm border border-gray-100 dark:border-[#2b2b2e] p-4 hover:border-[#f26c17] dark:hover:border-[#f26c17] transition-colors cursor-pointer"
                    @click="openEdit(user)"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-800 dark:text-white text-sm">{{ user.name }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ user.email || 'Sin correo registrado' }}
                                <el-tag v-if="!user.email" type="danger" size="small" class="ml-1">Sin email</el-tag>
                            </p>
                        </div>
                        <el-tag v-if="isUserConfigured(user.id)" type="success" size="small" effect="light">
                            {{ getUserTypes(user.id).length }} tipos
                        </el-tag>
                        <el-tag v-else type="info" size="small" effect="plain">Sin configurar</el-tag>
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
