<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';

const visible = ref(false);
const target = ref(null);
const terminationDate = ref(null);
const processing = ref(false);
const errors = ref({});

const localToday = () => {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
};

const send = (user, payload = {}) => {
    processing.value = true;

    router.put(route('users.toggle-status', user.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success(
                user.is_active
                    ? 'Usuario dado de baja correctamente.'
                    : 'Usuario activado correctamente.'
            );
            visible.value = false;
        },
        onError: (validationErrors) => {
            errors.value = validationErrors;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

/**
 * Dismiss the user (asking for the termination date) or reactivate them.
 * Called from the list dropdown and from the user detail page.
 */
const toggle = (user) => {
    errors.value = {};

    if (! user.is_active) {
        ElMessageBox.confirm(
            `¿Reactivar al usuario ${user.name}? Se eliminará su fecha de baja para que vuelva a la nómina.`,
            'Reactivar usuario',
            { confirmButtonText: 'Reactivar', cancelButtonText: 'Cancelar', type: 'info' }
        ).then(() => send(user)).catch(() => {});

        return;
    }

    target.value = user;
    terminationDate.value = localToday();
    visible.value = true;
};

const submit = () => {
    if (! terminationDate.value) {
        errors.value = { termination_date: 'Indica la fecha de baja.' };
        return;
    }

    send(target.value, { termination_date: terminationDate.value });
};

defineExpose({ toggle });
</script>

<template>
    <el-dialog v-model="visible" title="Dar de baja al usuario" width="480px" top="15vh">
        <div class="space-y-3">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                ¿Dar de baja a <strong>{{ target?.name }}</strong>? Se desactivará su acceso y, a partir de la
                fecha indicada, dejará de aparecer en los periodos de nómina.
            </p>

            <el-form label-position="top" size="default">
                <el-form-item label="Fecha de baja" required :error="errors.termination_date">
                    <el-date-picker
                        v-model="terminationDate"
                        type="date"
                        value-format="YYYY-MM-DD"
                        format="DD/MM/YYYY"
                        placeholder="Seleccionar fecha"
                        class="w-full"
                    />
                </el-form-item>
            </el-form>

            <p class="text-xs text-gray-400 dark:text-gray-500">
                El colaborador seguirá apareciendo en el periodo de nómina que contiene esa fecha y ya no en los
                periodos posteriores. Tampoco podrá registrar asistencia después de esa fecha.
            </p>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <el-button @click="visible = false">Cancelar</el-button>
                <el-button type="primary" color="#f26c17" :loading="processing" @click="submit">
                    Dar de baja
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>
