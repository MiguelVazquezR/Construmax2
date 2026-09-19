<script setup>
import { reactive, ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Monitor, VideoCamera, Delete } from '@element-plus/icons-vue';
import axios from 'axios';

const props = defineProps({
    devices: Array,
    kioskUrl: String,
});

const TOKEN_KEY = 'attendance_device_token';

const currentDevice = ref(null);
const checkingCurrent = ref(true);
const dialogVisible = ref(false);
const saving = ref(false);

const form = reactive({
    name: '',
    location: '',
    notes: '',
});

const errors = reactive({
    name: '',
    location: '',
    notes: '',
});

const formatDateTime = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    return date.toLocaleString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const resolveCurrentDevice = async () => {
    checkingCurrent.value = true;
    const token = localStorage.getItem(TOKEN_KEY);

    if (!token) {
        currentDevice.value = null;
        checkingCurrent.value = false;
        return;
    }

    try {
        const { data } = await axios.post(route('attendance.kiosk.bootstrap'), {}, {
            headers: { 'X-Attendance-Device': token },
        });
        currentDevice.value = data.device;
    } catch {
        // Token revoked or invalid: forget it in this browser.
        localStorage.removeItem(TOKEN_KEY);
        currentDevice.value = null;
    } finally {
        checkingCurrent.value = false;
    }
};

const openRegisterDialog = () => {
    form.name = '';
    form.location = '';
    form.notes = '';
    Object.keys(errors).forEach((key) => (errors[key] = ''));
    dialogVisible.value = true;
};

const register = async () => {
    if (!form.name.trim()) {
        errors.name = 'El nombre del dispositivo es obligatorio.';
        return;
    }

    saving.value = true;
    Object.keys(errors).forEach((key) => (errors[key] = ''));

    try {
        const { data } = await axios.post(route('payroll.devices.store'), {
            name: form.name,
            location: form.location || null,
            notes: form.notes || null,
        });

        localStorage.setItem(TOKEN_KEY, data.token);
        currentDevice.value = data.device;
        dialogVisible.value = false;
        ElMessage.success('Dispositivo registrado correctamente en este navegador.');
        router.reload({ only: ['devices'] });
    } catch (error) {
        const serverErrors = error.response?.data?.errors || {};
        Object.keys(errors).forEach((key) => {
            errors[key] = serverErrors[key]?.[0] || '';
        });

        if (error.response?.status === 403) {
            ElMessage.error('No tienes permiso para gestionar dispositivos.');
        } else if (!Object.values(errors).some(Boolean)) {
            ElMessage.error('No se pudo registrar el dispositivo.');
        }
    } finally {
        saving.value = false;
    }
};

const revoke = (device) => {
    ElMessageBox.confirm(
        `¿Revocar el acceso del dispositivo "${device.name}"? El kiosco dejará de funcionar en él.`,
        'Revocar dispositivo',
        { confirmButtonText: 'Revocar', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(async () => {
        try {
            await axios.delete(route('payroll.devices.destroy', device.id));

            if (currentDevice.value?.id === device.id) {
                localStorage.removeItem(TOKEN_KEY);
                currentDevice.value = null;
            }

            ElMessage.success('Dispositivo revocado.');
            router.reload({ only: ['devices'] });
        } catch {
            ElMessage.error('No se pudo revocar el dispositivo.');
        }
    }).catch(() => {});
};

const openKiosk = () => {
    window.open(props.kioskUrl, '_blank');
};

onMounted(resolveCurrentDevice);
</script>

<template>
    <AppLayout title="Dispositivos de asistencia">
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">
                        Dispositivos de asistencia
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Tablets o pantallas autorizadas para abrir el kiosco de marcaje facial.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <el-button :icon="VideoCamera" @click="openKiosk">
                        Abrir kiosco
                    </el-button>
                    <el-button type="primary" color="#f26c17" :icon="Plus" @click="openRegisterDialog">
                        Registrar este dispositivo
                    </el-button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">

            <!-- Current browser device -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <el-icon :size="34" class="text-primary"><Monitor /></el-icon>
                        <div>
                            <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-1">Este navegador</p>
                            <p v-if="checkingCurrent" class="text-gray-500">Verificando dispositivo...</p>
                            <template v-else-if="currentDevice">
                                <p class="text-lg font-bold text-gray-800 dark:text-white">
                                    {{ currentDevice.name }}
                                    <el-tag type="success" size="small" effect="plain" class="ml-2">Autorizado</el-tag>
                                </p>
                                <p class="text-sm text-gray-500">{{ currentDevice.location || 'Sin ubicación registrada' }}</p>
                            </template>
                            <template v-else>
                                <p class="text-lg font-bold text-gray-800 dark:text-white">Dispositivo sin registrar</p>
                                <p class="text-sm text-gray-500">
                                    Regístralo para poder abrir el kiosco de asistencia en este navegador.
                                </p>
                            </template>
                        </div>
                    </div>
                    <el-button v-if="!checkingCurrent && !currentDevice" type="primary" color="#f26c17" :icon="Plus" @click="openRegisterDialog">
                        Registrar este dispositivo
                    </el-button>
                </div>
            </div>

            <!-- Devices table -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <el-table :data="devices" style="width: 100%" stripe>
                    <el-table-column label="Dispositivo" min-width="180">
                        <template #default="scope">
                            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ scope.row.name }}</span>
                            <div class="text-xs text-gray-500">{{ scope.row.location || 'Sin ubicación' }}</div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Registrado por" min-width="170">
                        <template #default="scope">
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                {{ scope.row.registered_by?.name || '—' }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ formatDateTime(scope.row.registered_at) }}
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Último uso" min-width="150">
                        <template #default="scope">
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                {{ formatDateTime(scope.row.last_seen_at) }}
                            </span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Estatus" width="120" align="center">
                        <template #default="scope">
                            <el-tag :type="scope.row.is_active ? 'success' : 'danger'" size="small" effect="plain">
                                {{ scope.row.is_active ? 'Activo' : 'Revocado' }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="" width="110" align="right">
                        <template #default="scope">
                            <el-button
                                v-if="scope.row.is_active"
                                type="danger"
                                plain
                                size="small"
                                :icon="Delete"
                                @click="revoke(scope.row)"
                            >
                                Revocar
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-if="devices.length === 0" class="text-center py-12 bg-gray-50 dark:bg-[#252529]/50 border-t border-dashed border-gray-300 dark:border-gray-700">
                    <el-empty description="Sin dispositivos registrados" :image-size="100">
                        <template #default>
                            <p class="text-sm text-gray-500">Registra la primera tablet o pantalla para el kiosco de asistencia.</p>
                        </template>
                    </el-empty>
                </div>
            </div>

            <el-alert
                type="info"
                :closable="false"
                show-icon
                title="Cómo funciona"
                description="Al registrar un dispositivo, se genera un token que se guarda únicamente en el navegador de ese dispositivo. En tablets compartidas se recomienda abrir el kiosco en pantalla completa y evitar borrar los datos del navegador."
            />
        </div>

        <!-- Register dialog -->
        <el-dialog v-model="dialogVisible" title="Registrar este dispositivo" width="480px" top="10vh">
            <el-form label-position="top" size="default" @submit.prevent="register">
                <el-form-item label="Nombre del dispositivo" required :error="errors.name">
                    <el-input v-model="form.name" placeholder="Ej. Tablet recepción, Pantalla planta norte" maxlength="100" />
                </el-form-item>
                <el-form-item label="Ubicación" :error="errors.location">
                    <el-input v-model="form.location" placeholder="Ej. Oficina, Planta" maxlength="120" />
                </el-form-item>
                <el-form-item label="Notas" :error="errors.notes">
                    <el-input v-model="form.notes" type="textarea" :rows="2" placeholder="Comentarios opcionales" maxlength="500" />
                </el-form-item>
            </el-form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="dialogVisible = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="saving" @click="register">
                        Registrar dispositivo
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </AppLayout>
</template>
