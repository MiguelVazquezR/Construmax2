<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

// Dismissal of an internal technician: asks for the termination date so the
// collaborator stops appearing in the payroll periods that start after it,
// exactly like the dismissal of a collaborator from Users.
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

const open = (technician) => {
    errors.value = {};
    target.value = technician;
    terminationDate.value = localToday();
    visible.value = true;
};

const submit = () => {
    if (! terminationDate.value) {
        errors.value = { termination_date: 'Indica la fecha de baja.' };
        return;
    }

    processing.value = true;

    router.delete(route('technicians.destroy', target.value.id), {
        data: { termination_date: terminationDate.value },
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Técnico dado de baja correctamente.');
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

defineExpose({ open });
</script>

<template>
    <el-dialog v-model="visible" title="Dar de baja al técnico" width="480px" top="15vh">
        <div class="space-y-3">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                ¿Dar de baja a <strong>{{ target?.user?.name }}</strong>? Se desactivará su acceso y, a partir de la
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
                El técnico seguirá apareciendo en el periodo de nómina que contiene esa fecha y ya no en los
                periodos posteriores. Tampoco podrá registrar asistencia después de esa fecha y podrá reactivarse
                desde el listado en cualquier momento.
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
