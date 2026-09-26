<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { WarningFilled } from '@element-plus/icons-vue';

const props = defineProps({
    userId: { type: Number, default: null },
    userName: { type: String, default: '' },
});

const emit = defineEmits(['saved']);

const visible = ref(false);
const editingPeriod = ref(null);

const form = useForm({
    year_number: null,
    start_date: null,
    end_date: null,
    entitled_days: 0,
    accrued_days: 0,
    taken_days: 0,
    premium_paid: false,
    premium_paid_at: null,
});

const today = () => new Date().toISOString().substring(0, 10);

const open = (period = null) => {
    editingPeriod.value = period;
    form.clearErrors();
    form.year_number = period ? period.year_number : null;
    form.start_date = period?.start_date ?? null;
    form.end_date = period?.end_date ?? null;
    form.entitled_days = period ? Number(period.entitled_days) : 0;
    form.accrued_days = period ? Number(period.accrued_days) : 0;
    form.taken_days = period ? Number(period.taken_days) : 0;
    form.premium_paid = Boolean(period?.premium_paid_at);
    form.premium_paid_at = period?.premium_paid_at ?? null;
    visible.value = true;
};

// Marking the premium as paid defaults the date to today; the payroll team
// can adjust it to the actual payment date.
watch(() => form.premium_paid, (paid) => {
    if (paid && ! form.premium_paid_at) {
        form.premium_paid_at = today();
    }
});

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            visible.value = false;
            emit('saved');
        },
    };

    if (editingPeriod.value) {
        form.put(route('payroll.vacations.periods.update', editingPeriod.value.id), options);
    } else {
        form.post(route('payroll.vacations.periods.store', { user: props.userId }), options);
    }
};

defineExpose({ open });
</script>

<template>
    <el-dialog
        v-model="visible"
        :title="editingPeriod ? 'Editar periodo vacacional' : 'Agregar periodo vacacional'"
        width="560px"
        top="8vh"
        :close-on-click-modal="false"
    >
        <div class="space-y-4">
            <div class="rounded-lg bg-gray-50 dark:bg-[#252529]/60 border border-gray-200 dark:border-gray-800 px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                <span class="font-semibold text-gray-800 dark:text-gray-100">{{ userName }}</span>
                · Los periodos se calculan solos con la fecha de ingreso; los que edites aquí conservan tus valores.
            </div>

            <el-form :model="form" label-position="top" size="default">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Número de año" required :error="form.errors.year_number">
                        <el-input-number
                            v-model="form.year_number"
                            :min="1"
                            :max="80"
                            :controls="false"
                            style="width: 100%"
                            placeholder="Ej. 4"
                        />
                    </el-form-item>

                    <el-form-item label="Prima vacacional" :error="form.errors.premium_paid">
                        <el-checkbox v-model="form.premium_paid">Prima vacacional pagada</el-checkbox>
                    </el-form-item>
                </div>

                <el-form-item v-if="form.premium_paid" label="Fecha de pago de la prima" :error="form.errors.premium_paid_at">
                    <el-date-picker
                        v-model="form.premium_paid_at"
                        type="date"
                        value-format="YYYY-MM-DD"
                        placeholder="Seleccionar fecha"
                        class="w-full"
                    />
                </el-form-item>

                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Rango del periodo (aniversario a aniversario)</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Inicio" required :error="form.errors.start_date">
                        <el-date-picker
                            v-model="form.start_date"
                            type="date"
                            value-format="YYYY-MM-DD"
                            placeholder="Seleccionar"
                            class="w-full"
                        />
                    </el-form-item>

                    <el-form-item label="Fin" required :error="form.errors.end_date">
                        <el-date-picker
                            v-model="form.end_date"
                            type="date"
                            value-format="YYYY-MM-DD"
                            placeholder="Seleccionar"
                            class="w-full"
                        />
                    </el-form-item>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <el-form-item label="Días otorgados" :error="form.errors.entitled_days">
                        <el-input-number
                            v-model="form.entitled_days"
                            :min="0"
                            :max="365"
                            :precision="2"
                            :controls="false"
                            style="width: 100%"
                        />
                    </el-form-item>

                    <el-form-item label="Días devengados" :error="form.errors.accrued_days">
                        <el-input-number
                            v-model="form.accrued_days"
                            :min="0"
                            :max="365"
                            :precision="2"
                            :controls="false"
                            style="width: 100%"
                        />
                    </el-form-item>

                    <el-form-item label="Días tomados" :error="form.errors.taken_days">
                        <el-input-number
                            v-model="form.taken_days"
                            :min="0"
                            :max="365"
                            :precision="2"
                            :controls="false"
                            style="width: 100%"
                        />
                    </el-form-item>
                </div>

                <div class="flex items-start gap-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40 px-3 py-2.5 text-xs text-amber-700 dark:text-amber-300">
                    <el-icon class="mt-0.5 shrink-0"><WarningFilled /></el-icon>
                    <p>Modificar «Días tomados» manualmente puede causar inconsistencias con el historial de transacciones. Los días que edites ya no se actualizarán solos.</p>
                </div>
            </el-form>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <el-button @click="visible = false">Cancelar</el-button>
                <el-button type="primary" color="#f26c17" :loading="form.processing" @click="submit">
                    Guardar
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>
