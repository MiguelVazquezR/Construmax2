<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { UploadFilled } from '@element-plus/icons-vue';

const props = defineProps({
    modelValue: Boolean,
    /** Expense row (with receipts) linked to a deposit. */
    expense: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'completed']);

const dialogVisible = ref(props.modelValue);
watch(() => props.modelValue, (value) => { dialogVisible.value = value; });
watch(dialogVisible, (value) => emit('update:modelValue', value));

const existingVoucher = computed(() => props.expense?.receipts?.[0] ?? null);

const form = useForm({
    commission_amount: props.expense?.commission_amount || null,
    voucher: null,
});

const onVoucherChange = (file) => {
    if (file.raw && file.raw.size > 10 * 1024 * 1024) {
        ElMessage.warning('El comprobante no debe exceder 10 MB.');
        return;
    }

    form.voucher = file.raw;
};

function submit() {
    if (!form.voucher && !existingVoucher.value) {
        ElMessage.warning('Adjunta el comprobante del depósito.');
        return;
    }

    form.post(route('expenses.complete-deposit', props.expense.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Depósito marcado como realizado. El pago al técnico se registró automáticamente.');
            emit('update:modelValue', false);
            emit('completed');
        },
    });
}
</script>

<template>
    <el-dialog
        v-model="dialogVisible"
        title="Marcar depósito como realizado"
        width="520px"
        top="8vh"
        destroy-on-close
        :close-on-click-modal="false"
    >
        <el-alert
            v-if="expense"
            type="info"
            :closable="false"
            show-icon
            class="mb-4"
            :title="expense.concept"
        >
            <template #default>
                Al confirmar, el depósito quedará marcado como realizado y el pago al técnico se registrará automáticamente.
            </template>
        </el-alert>

        <el-form :model="form" label-position="top">
            <el-form-item label="Comprobante" :error="form.errors.voucher">
                <div class="w-full">
                    <div v-if="existingVoucher" class="mb-2 text-sm">
                        <a
                            :href="existingVoucher.url"
                            target="_blank"
                            rel="noopener"
                            class="text-blue-600 hover:underline dark:text-blue-400"
                        >
                            {{ existingVoucher.name }}
                        </a>
                        <span class="ml-2 text-xs text-gray-400">Comprobante actual (puedes reemplazarlo).</span>
                    </div>

                    <el-upload
                        :auto-upload="false"
                        :limit="1"
                        accept=".jpg,.jpeg,.png,.pdf"
                        drag
                        :on-change="onVoucherChange"
                    >
                        <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
                        <div class="el-upload__text">
                            Arrastra un archivo aquí o <em>haz clic para subir</em>
                        </div>
                        <template #tip>
                            <div class="el-upload__tip">JPG, PNG o PDF. Máx. 10 MB.</div>
                        </template>
                    </el-upload>
                </div>
            </el-form-item>

            <el-form-item
                label="Comisión (opcional)"
                prop="commission_amount"
                :error="form.errors.commission_amount"
                class="expense-commission-item"
            >
                <el-input-number
                    v-model="form.commission_amount"
                    :min="0"
                    :precision="2"
                    :controls="false"
                    placeholder="0.00"
                    class="w-full"
                >
                    <template #prefix>
                        <span class="text-gray-500">$</span>
                    </template>
                </el-input-number>
                <p class="text-xs text-gray-400 mt-1">Comisión del canal de pago (p. ej. cobro en OXXO), si hubo.</p>
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="dialogVisible = false">Cancelar</el-button>
            <el-button type="primary" :loading="form.processing" @click="submit">
                Confirmar depósito
            </el-button>
        </template>
    </el-dialog>
</template>

<style scoped>
/* Commission input: same width as the rest of the inputs */
:deep(.expense-commission-item .el-input-number) {
    width: 100%;
}
</style>
