<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    availableDays: { type: Number, default: 0 },
    pendingDays: { type: Number, default: 0 },
    minimumDays: { type: Number, default: 0 },
    currentSeason: { type: Number, default: 0 },
});

const emit = defineEmits(['saved']);

const visible = ref(false);
const range = ref(null);
const preview = ref(null);
const previewLoading = ref(false);

const form = useForm({
    start_date: '',
    end_date: '',
    reason: '',
});

const formatDays = (value) => {
    const number = Number(value ?? 0);

    if (! Number.isFinite(number)) return '0';

    return String(Math.round(number * 100) / 100);
};

const errorMessage = computed(() => Object.values(form.errors)[0] ?? null);

// The collaborator needs the configured minimum balance before requesting.
const insufficientBalance = computed(
    () => Number(props.availableDays) < Number(props.minimumDays)
);

const futureOnly = (date) => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return date.getTime() < today.getTime();
};

// Exact discount preview (working days of the schedule, holidays excluded).
const loadPreview = async () => {
    if (! range.value?.[0] || ! range.value?.[1]) {
        preview.value = null;
        return;
    }

    previewLoading.value = true;

    try {
        const { data } = await axios.get(route('payroll.my-attendance.vacation-preview'), {
            params: { start_date: range.value[0], end_date: range.value[1] },
        });
        preview.value = data;
    } catch {
        preview.value = null;
    } finally {
        previewLoading.value = false;
    }
};

watch(range, (value) => {
    form.start_date = value?.[0] ?? '';
    form.end_date = value?.[1] ?? '';
    preview.value = null;
    loadPreview();
});

const remainingDays = computed(() => {
    if (! preview.value) return 0;

    return Math.max(0, Number(preview.value.available_days) - Number(preview.value.days));
});

const open = () => {
    form.reset();
    form.clearErrors();
    range.value = null;
    preview.value = null;
    visible.value = true;
};

const submit = () => {
    form.post(route('payroll.vacations.requests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            visible.value = false;
            emit('saved');
        },
    });
};

defineExpose({ open });
</script>

<template>
    <el-dialog
        v-model="visible"
        title="Solicitar vacaciones"
        width="560px"
        :close-on-click-modal="false"
    >
        <div class="space-y-5">
            <!-- Balance at a glance -->
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529]/60 px-4 py-3 text-center">
                    <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Disponibles</p>
                    <p class="text-2xl font-bold text-emerald-600">{{ formatDays(availableDays) }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529]/60 px-4 py-3 text-center">
                    <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">En solicitud</p>
                    <p class="text-2xl font-bold text-amber-500">{{ formatDays(pendingDays) }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529]/60 px-4 py-3 text-center">
                    <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold">Temporada</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ currentSeason > 0 ? `Año ${currentSeason}` : '—' }}</p>
                </div>
            </div>

            <el-alert
                v-if="insufficientBalance"
                type="warning"
                :closable="false"
                show-icon
                title="Saldo insuficiente"
                :description="`Necesitas al menos ${formatDays(minimumDays)} día(s) disponibles para poder solicitar vacaciones.`"
            />

            <el-form :model="form" label-position="top" size="default">
                <el-form-item
                    label="Fechas de vacaciones"
                    required
                    :error="form.errors.start_date || form.errors.end_date"
                >
                    <el-date-picker
                        v-model="range"
                        type="daterange"
                        value-format="YYYY-MM-DD"
                        format="DD/MM/YYYY"
                        range-separator="a"
                        start-placeholder="Fecha de inicio"
                        end-placeholder="Fecha final"
                        :disabled-date="futureOnly"
                        :unlink-panels="true"
                        style="width: 100%"
                    />
                </el-form-item>

                <!-- Impact preview of the selected range -->
                <div
                    v-if="range && range[0] && range[1]"
                    class="rounded-xl border border-gray-100 dark:border-[#2b2b2e] bg-gray-50 dark:bg-[#252529]/60 px-4 py-3 -mt-2 mb-4"
                >
                    <p v-if="previewLoading" class="text-sm text-gray-500 dark:text-gray-400">
                        Calculando días laborables…
                    </p>
                    <template v-else-if="preview">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                Solicitarás
                                <span class="font-bold text-gray-800 dark:text-gray-100">{{ formatDays(preview.days) }} día(s) laborables</span>
                            </p>
                            <el-tag :type="preview.exceeds_balance ? 'danger' : 'success'" effect="light" round>
                                <template v-if="preview.exceeds_balance">
                                    Excede tu saldo de {{ formatDays(preview.available_days) }}
                                </template>
                                <template v-else>
                                    Te quedarán {{ formatDays(remainingDays) }} día(s)
                                </template>
                            </el-tag>
                        </div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                            No se cuentan los días de descanso ni los festivos de tu horario.
                        </p>
                    </template>
                </div>

                <el-form-item label="Motivo (opcional)" :error="form.errors.reason">
                    <el-input
                        v-model="form.reason"
                        type="textarea"
                        :rows="2"
                        maxlength="255"
                        show-word-limit
                        placeholder="Referencia u observaciones"
                    />
                </el-form-item>
            </el-form>

            <el-alert v-if="errorMessage" type="error" :closable="false" show-icon :title="errorMessage" />
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <el-button @click="visible = false">Cancelar</el-button>
                <el-button
                    type="primary"
                    color="#f26c17"
                    :loading="form.processing"
                    :disabled="insufficientBalance"
                    @click="submit"
                >
                    Enviar solicitud
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>
