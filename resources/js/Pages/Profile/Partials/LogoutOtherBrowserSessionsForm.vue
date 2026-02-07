<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

defineProps({
    sessions: Array,
});

const confirmingLogout = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmLogout = () => {
    confirmingLogout.value = true;
    setTimeout(() => passwordInput.value?.focus(), 250);
};

const logoutOtherBrowserSessions = () => {
    form.delete(route('other-browser-sessions.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            ElMessage.success('Se han cerrado las otras sesiones.');
        },
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingLogout.value = false;
    form.reset();
};
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
        
        <div class="p-6 border-b border-gray-100 dark:border-[#2b2b2e] bg-gray-50/50 dark:bg-[#252529]">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white flex items-center gap-2">
                <el-icon class="text-primary"><Monitor /></el-icon> Sesiones del Navegador
            </h3>
            <p class="text-sm text-gray-500 mt-1">
                Administra y cierra tus sesiones activas en otros navegadores y dispositivos.
            </p>
        </div>

        <div class="p-6">
            <div class="max-w-xl text-sm text-gray-600 dark:text-gray-400 mb-6">
                Si es necesario, puedes cerrar sesión en todas las demás sesiones de navegador en todos tus dispositivos. Algunas de tus sesiones recientes se enumeran a continuación; sin embargo, esta lista puede no ser exhaustiva.
            </div>

            <!-- Lista de Sesiones -->
            <div v-if="sessions.length > 0" class="space-y-4">
                <div v-for="(session, i) in sessions" :key="i" class="flex items-center p-3 rounded-lg border border-gray-100 dark:border-[#2b2b2e] hover:bg-gray-50 dark:hover:bg-[#252529] transition">
                    <div class="text-gray-500 dark:text-gray-400">
                        <el-icon v-if="session.agent.is_desktop" :size="32"><Monitor /></el-icon>
                        <el-icon v-else :size="32"><Iphone /></el-icon>
                    </div>

                    <div class="ml-4">
                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            {{ session.agent.platform ? session.agent.platform : 'Desconocido' }} - {{ session.agent.browser ? session.agent.browser : 'Desconocido' }}
                        </div>

                        <div class="text-xs text-gray-500">
                            {{ session.ip_address }},
                            <span v-if="session.is_current_device" class="text-green-500 font-bold">Este dispositivo</span>
                            <span v-else>Última actividad: {{ session.last_active }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex items-center">
                <el-button type="primary" color="#f26c17" @click="confirmLogout" class="!font-bold">
                    Cerrar otras sesiones
                </el-button>

                <transition name="fade">
                    <span v-if="form.recentlySuccessful" class="ml-3 text-sm text-green-600">
                        Hecho.
                    </span>
                </transition>
            </div>

            <!-- Modal de Confirmación -->
            <el-dialog
                v-model="confirmingLogout"
                title="Cerrar Otras Sesiones"
                width="500px"
                @close="closeModal"
            >
                <div class="text-gray-600 dark:text-gray-400 mb-4">
                    Por favor, ingresa tu contraseña para confirmar que deseas cerrar sesión en los demás dispositivos.
                </div>

                <el-input
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    placeholder="Contraseña"
                    show-password
                    size="large"
                    @keyup.enter="logoutOtherBrowserSessions"
                />
                
                <p v-if="form.errors.password" class="text-xs text-red-500 mt-2">{{ form.errors.password }}</p>

                <template #footer>
                    <span class="dialog-footer">
                        <el-button @click="closeModal">Cancelar</el-button>
                        <el-button 
                            type="primary" 
                            color="#f26c17" 
                            @click="logoutOtherBrowserSessions" 
                            :loading="form.processing"
                        >
                            Cerrar Sesiones
                        </el-button>
                    </span>
                </template>
            </el-dialog>
        </div>
    </div>
</template>