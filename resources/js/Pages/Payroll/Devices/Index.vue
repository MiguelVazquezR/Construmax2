<script setup>
import { reactive, ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Monitor, VideoCamera, Delete, RefreshRight, CircleClose, CopyDocument, Edit, Key } from '@element-plus/icons-vue';
import axios from 'axios';

const props = defineProps({
    devices: Array,
    kioskUrl: String,
    registrationUrl: String,
    registrationPassword: {
        type: String,
        default: null,
    },
    registrationPasswordConfigured: Boolean,
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

// Update an existing device
const editDialogVisible = ref(false);
const editingDevice = ref(null);
const editSaving = ref(false);
const editForm = reactive({
    name: '',
    location: '',
    notes: '',
});
const editErrors = reactive({
    name: '',
    location: '',
    notes: '',
});

// Shared password of the public registration link
const passwordDialogVisible = ref(false);
const passwordSaving = ref(false);
const passwordForm = reactive({
    password: '',
});
const passwordError = ref('');

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
        `¿Revocar el acceso del dispositivo "${device.name}"? El kiosco dejará de funcionar en él, pero podrás reactivarlo después.`,
        'Revocar dispositivo',
        { confirmButtonText: 'Revocar', cancelButtonText: 'Cancelar', type: 'warning' }
    ).then(async () => {
        try {
            await axios.patch(route('payroll.devices.revoke', device.id));

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

const restore = (device) => {
    ElMessageBox.confirm(
        `¿Reactivar el acceso del dispositivo "${device.name}"? Su token volverá a funcionar en el kiosco.`,
        'Reactivar dispositivo',
        { confirmButtonText: 'Reactivar', cancelButtonText: 'Cancelar', type: 'info' }
    ).then(async () => {
        try {
            await axios.patch(route('payroll.devices.restore', device.id));
            ElMessage.success('Dispositivo reactivado.');
            router.reload({ only: ['devices'] });
        } catch {
            ElMessage.error('No se pudo reactivar el dispositivo.');
        }
    }).catch(() => {});
};

const remove = (device) => {
    ElMessageBox.confirm(
        `¿Eliminar definitivamente el dispositivo "${device.name}"? Esta acción no se puede deshacer.`,
        'Eliminar dispositivo',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'error' }
    ).then(async () => {
        try {
            await axios.delete(route('payroll.devices.destroy', device.id));

            if (currentDevice.value?.id === device.id) {
                localStorage.removeItem(TOKEN_KEY);
                currentDevice.value = null;
            }

            ElMessage.success('Dispositivo eliminado.');
            router.reload({ only: ['devices'] });
        } catch {
            ElMessage.error('No se pudo eliminar el dispositivo.');
        }
    }).catch(() => {});
};

const openKiosk = () => {
    window.open(props.kioskUrl, '_blank');
};

const copyRegistrationLink = async () => {
    try {
        await navigator.clipboard.writeText(props.registrationUrl);
        ElMessage.success('Link de registro copiado.');
    } catch {
        ElMessage.warning('No se pudo copiar el link. Cópialo manualmente.');
    }
};

const openEditDialog = (device) => {
    editingDevice.value = device;
    editForm.name = device.name;
    editForm.location = device.location || '';
    editForm.notes = device.notes || '';
    Object.keys(editErrors).forEach((key) => (editErrors[key] = ''));
    editDialogVisible.value = true;
};

const saveEdit = async () => {
    if (!editingDevice.value) return;

    Object.keys(editErrors).forEach((key) => (editErrors[key] = ''));

    if (!editForm.name.trim()) {
        editErrors.name = 'El nombre del dispositivo es obligatorio.';
        return;
    }

    editSaving.value = true;

    try {
        await axios.put(route('payroll.devices.update', editingDevice.value.id), {
            name: editForm.name,
            location: editForm.location || null,
            notes: editForm.notes || null,
        });

        editDialogVisible.value = false;
        ElMessage.success('Dispositivo actualizado.');
        router.reload({ only: ['devices'] });
    } catch (error) {
        const serverErrors = error.response?.data?.errors || {};
        Object.keys(editErrors).forEach((key) => {
            editErrors[key] = serverErrors[key]?.[0] || '';
        });

        if (!Object.values(editErrors).some(Boolean)) {
            ElMessage.error('No se pudo actualizar el dispositivo.');
        }
    } finally {
        editSaving.value = false;
    }
};

const openPasswordDialog = () => {
    // The password is plain text, so the current value can be shown and edited.
    passwordForm.password = props.registrationPassword || '';
    passwordError.value = '';
    passwordDialogVisible.value = true;
};

const savePassword = async () => {
    passwordError.value = '';

    if (!passwordForm.password) {
        passwordError.value = 'Escribe la nueva contraseña de registro.';
        return;
    }

    if (passwordForm.password.length < 3) {
        passwordError.value = 'La contraseña debe tener al menos 3 caracteres.';
        return;
    }

    passwordSaving.value = true;

    try {
        await axios.put(route('payroll.devices.registration-password'), {
            password: passwordForm.password,
        });

        passwordDialogVisible.value = false;
        ElMessage.success('Contraseña de registro actualizada.');
        router.reload({ only: ['registrationPassword', 'registrationPasswordConfigured'] });
    } catch (error) {
        passwordError.value = error.response?.data?.errors?.password?.[0]
            || 'No se pudo actualizar la contraseña de registro.';
    } finally {
        passwordSaving.value = false;
    }
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
                        Tablets o pantallas autorizadas para abrir el kiosco de registro facial.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <el-button :icon="CopyDocument" @click="copyRegistrationLink">
                        Copiar link
                    </el-button>
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

            <!-- Public registration link -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-xl border border-gray-100 dark:border-[#2b2b2e] p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-1">Registro sin iniciar sesión</p>
                        <p class="text-sm text-gray-500">
                            Comparte este link con el dispositivo que quieras autorizar. Solo pedirá los datos del
                            dispositivo y la contraseña de registro.
                        </p>
                        <p class="text-sm text-gray-500 mt-2">
                            Contraseña actual:
                            <span v-if="registrationPassword" class="font-mono font-semibold text-gray-800 dark:text-gray-100">
                                {{ registrationPassword }}
                            </span>
                            <span v-else class="text-gray-400">Sin configurar</span>
                        </p>
                    </div>
                    <el-tag :type="registrationPasswordConfigured ? 'success' : 'info'" size="small" effect="plain">
                        {{ registrationPasswordConfigured ? 'Contraseña configurada' : 'Sin contraseña configurada' }}
                    </el-tag>
                </div>

                <div class="flex flex-col sm:flex-row gap-2">
                    <el-input :model-value="registrationUrl" readonly class="flex-1">
                        <template #prepend>Link</template>
                    </el-input>
                    <el-button :icon="CopyDocument" @click="copyRegistrationLink">Copiar link</el-button>
                    <el-button :icon="Key" @click="openPasswordDialog">
                        {{ registrationPasswordConfigured ? 'Cambiar contraseña' : 'Configurar contraseña' }}
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
                                {{ scope.row.registered_by?.name || 'Registro con enlace' }}
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

                    <el-table-column label="Acciones" width="340" align="right">
                        <template #default="scope">
                            <div class="flex items-center justify-end gap-1">
                                <el-button
                                    type="primary"
                                    plain
                                    size="small"
                                    :icon="Edit"
                                    @click="openEditDialog(scope.row)"
                                >
                                    Actualizar
                                </el-button>
                                <el-button
                                    v-if="scope.row.is_active"
                                    type="warning"
                                    plain
                                    size="small"
                                    :icon="CircleClose"
                                    @click="revoke(scope.row)"
                                >
                                    Revocar
                                </el-button>
                                <el-button
                                    v-else
                                    type="success"
                                    plain
                                    size="small"
                                    :icon="RefreshRight"
                                    @click="restore(scope.row)"
                                >
                                    Reactivar
                                </el-button>
                                <el-button
                                    type="danger"
                                    plain
                                    size="small"
                                    :icon="Delete"
                                    @click="remove(scope.row)"
                                >
                                    Eliminar
                                </el-button>
                            </div>
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
                description="Al registrar un dispositivo, se genera un token que se guarda únicamente en el navegador de ese dispositivo. También puedes compartir el link de registro para autorizar un dispositivo sin iniciar sesión: solo pedirá sus datos y la contraseña de registro. En tablets compartidas se recomienda abrir el kiosco en pantalla completa y evitar borrar los datos del navegador."
            />
        </div>

        <!-- Register dialog -->
        <el-dialog v-model="dialogVisible" title="Registrar este dispositivo" width="480px" top="10vh">
            <el-form label-position="top" size="default" @submit.prevent="register">
                <el-form-item label="Nombre del dispositivo" required :error="errors.name">
                    <el-input
                        v-model="form.name"
                        name="device_name"
                        autocomplete="off"
                        placeholder="Ej. Tablet recepción, Pantalla planta norte"
                        maxlength="100"
                    />
                </el-form-item>
                <el-form-item label="Ubicación" :error="errors.location">
                    <el-input v-model="form.location" name="device_location" autocomplete="off" placeholder="Ej. Oficina, Planta" maxlength="120" />
                </el-form-item>
                <el-form-item label="Notas" :error="errors.notes">
                    <el-input
                        v-model="form.notes"
                        name="device_notes"
                        autocomplete="off"
                        type="textarea"
                        :rows="2"
                        placeholder="Comentarios opcionales"
                        maxlength="500"
                    />
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

        <!-- Edit dialog -->
        <el-dialog v-model="editDialogVisible" title="Actualizar dispositivo" width="480px" top="10vh">
            <el-form label-position="top" size="default" @submit.prevent="saveEdit">
                <el-form-item label="Nombre del dispositivo" required :error="editErrors.name">
                    <el-input v-model="editForm.name" name="device_name" autocomplete="off" maxlength="100" />
                </el-form-item>
                <el-form-item label="Ubicación" :error="editErrors.location">
                    <el-input v-model="editForm.location" name="device_location" autocomplete="off" placeholder="Ej. Oficina, Planta" maxlength="120" />
                </el-form-item>
                <el-form-item label="Notas" :error="editErrors.notes">
                    <el-input v-model="editForm.notes" name="device_notes" autocomplete="off" type="textarea" :rows="2" maxlength="500" />
                </el-form-item>
            </el-form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="editDialogVisible = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="editSaving" @click="saveEdit">
                        Guardar cambios
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Registration password dialog -->
        <el-dialog v-model="passwordDialogVisible" title="Contraseña de registro" width="460px" top="10vh">
            <p class="text-sm text-gray-500 mb-4">
                Esta contraseña se pide en el link público para autorizar nuevos dispositivos sin iniciar sesión.
            </p>

            <el-form label-position="top" size="default" @submit.prevent="savePassword">
                <el-form-item label="Nueva contraseña" required :error="passwordError">
                    <el-input
                        v-model="passwordForm.password"
                        name="device_registration_password"
                        autocomplete="off"
                        maxlength="100"
                        placeholder="Mínimo 3 caracteres"
                        @keyup.enter="savePassword"
                    />
                </el-form-item>
            </el-form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="passwordDialogVisible = false">Cancelar</el-button>
                    <el-button type="primary" color="#f26c17" :loading="passwordSaving" @click="savePassword">
                        Guardar contraseña
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </AppLayout>
</template>
