<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Edit, Delete, Star, StarFilled, Picture } from '@element-plus/icons-vue';

const props = defineProps({
    technician: Object,
});

const showForm = ref(false);
const editingAccount = ref(null);

const form = useForm({
    account_number: '',
    card_number: '',
    clabe: '',
    branch_number: '',
    qr_image: null,
});

const openNew = () => {
    form.reset();
    form.clearErrors();
    editingAccount.value = null;
    showForm.value = true;
};

const openEdit = (account) => {
    form.account_number = account.account_number || '';
    form.card_number = account.card_number || '';
    form.clabe = account.clabe || '';
    form.branch_number = account.branch_number || '';
    form.qr_image = null;
    form.clearErrors();
    editingAccount.value = account;
    showForm.value = true;
};

const cancelForm = () => {
    showForm.value = false;
    editingAccount.value = null;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    const url = editingAccount.value
        ? route('technicians.bank-accounts.update', [props.technician.id, editingAccount.value.id])
        : route('technicians.bank-accounts.store', props.technician.id);

    form.post(url, {
        forceFormData: !!form.qr_image,
        onSuccess: () => {
            ElMessage.success(editingAccount.value ? 'Cuenta actualizada.' : 'Cuenta agregada.');
            cancelForm();
        },
    });
};

const deleteAccount = (account) => {
    ElMessageBox.confirm(
        '¿Eliminar esta cuenta bancaria?',
        'Confirmar',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'error' }
    ).then(() => {
        router.delete(route('technicians.bank-accounts.destroy', [props.technician.id, account.id]), {
            onSuccess: () => ElMessage.success('Cuenta eliminada.'),
        });
    }).catch(() => {});
};

const setFavorite = (account) => {
    router.put(route('technicians.bank-accounts.favorite', [props.technician.id, account.id]), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => ElMessage.success('Cuenta favorita actualizada.'),
    });
};

const getQrUrl = (account) => {
    const qr = account.media?.find(m => m.collection_name === 'bank_qr');
    return qr ? qr.original_url : null;
};

const handleQrChange = (file) => {
    form.qr_image = file.raw;
};
</script>

<template>
    <div class="space-y-6 pb-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Cuentas bancarias</h3>
                <p class="text-sm text-gray-500">Administra las cuentas para pagos al técnico.</p>
            </div>
            <el-button type="primary" color="#f26c17" :icon="Plus" @click="openNew" v-if="!showForm">
                Agregar cuenta
            </el-button>
        </div>

        <!-- Form -->
        <div v-if="showForm" class="p-5 bg-gray-50 dark:bg-[#252529] rounded-lg border border-gray-200 dark:border-gray-700">
            <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-4">
                {{ editingAccount ? 'Editar cuenta' : 'Nueva cuenta bancaria' }}
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <el-form-item label="Número de cuenta">
                    <el-input v-model="form.account_number" placeholder="Ej. 1234567890" />
                </el-form-item>
                <el-form-item label="Número de tarjeta">
                    <el-input v-model="form.card_number" placeholder="Ej. 4111111111111111" />
                </el-form-item>
                <el-form-item label="CLABE interbancaria">
                    <el-input v-model="form.clabe" placeholder="Ej. 012180001234567890" />
                </el-form-item>
                <el-form-item label="Número de sucursal">
                    <el-input v-model="form.branch_number" placeholder="Ej. 1234" />
                </el-form-item>
                <el-form-item label="Imagen QR">
                    <el-upload
                        :auto-upload="false"
                        :show-file-list="false"
                        :on-change="handleQrChange"
                        accept="image/*"
                    >
                        <el-button type="primary" plain :icon="Picture">
                            {{ editingAccount && getQrUrl(editingAccount) ? 'Cambiar QR' : 'Seleccionar QR' }}
                        </el-button>
                    </el-upload>
                    <span v-if="form.qr_image" class="text-xs text-green-600 ml-2">{{ form.qr_image.name }}</span>
                    <span v-else-if="editingAccount && getQrUrl(editingAccount)" class="text-xs text-gray-400 ml-2">QR actual: disponible</span>
                </el-form-item>
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <el-button @click="cancelForm">Cancelar</el-button>
                <el-button type="primary" color="#f26c17" :loading="form.processing" @click="submit">
                    {{ editingAccount ? 'Actualizar' : 'Guardar' }}
                </el-button>
            </div>
        </div>

        <!-- Accounts list -->
        <div v-if="technician.bank_accounts?.length" class="space-y-3">
            <div
                v-for="account in technician.bank_accounts"
                :key="account.id"
                :class="[
                    'p-4 rounded-lg border transition-colors',
                    account.is_favorite
                        ? 'border-orange-300 dark:border-orange-700 bg-orange-50/50 dark:bg-orange-900/10'
                        : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-[#1e1e20]'
                ]"
            >
                <div class="flex items-start justify-between gap-4">
                    <!-- Info -->
                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div v-if="account.account_number">
                            <p class="text-[10px] text-gray-400 uppercase font-bold">Núm. de cuenta</p>
                            <p class="text-sm font-mono text-gray-700 dark:text-gray-300">{{ account.account_number }}</p>
                        </div>
                        <div v-if="account.card_number">
                            <p class="text-[10px] text-gray-400 uppercase font-bold">Núm. de tarjeta</p>
                            <p class="text-sm font-mono text-gray-700 dark:text-gray-300">{{ account.card_number }}</p>
                        </div>
                        <div v-if="account.clabe">
                            <p class="text-[10px] text-gray-400 uppercase font-bold">CLABE</p>
                            <p class="text-sm font-mono text-gray-700 dark:text-gray-300">{{ account.clabe }}</p>
                        </div>
                        <div v-if="account.branch_number">
                            <p class="text-[10px] text-gray-400 uppercase font-bold">Sucursal</p>
                            <p class="text-sm font-mono text-gray-700 dark:text-gray-300">{{ account.branch_number }}</p>
                        </div>
                    </div>

                    <!-- QR preview & Actions -->
                    <div class="flex items-center gap-2 shrink-0">
                        <el-popover
                            v-if="getQrUrl(account)"
                            placement="left"
                            :width="260"
                            trigger="click"
                        >
                            <template #reference>
                                <el-button size="small" :icon="Picture" circle />
                            </template>
                            <img :src="getQrUrl(account)" alt="QR" class="w-full rounded" />
                        </el-popover>
                        <el-button
                            size="small"
                            :type="account.is_favorite ? 'warning' : 'default'"
                            :icon="account.is_favorite ? StarFilled : Star"
                            circle
                            :title="account.is_favorite ? 'Cuenta favorita' : 'Marcar como favorita'"
                            @click="setFavorite(account)"
                        />
                        <el-button size="small" :icon="Edit" circle @click="openEdit(account)" />
                        <el-button size="small" type="danger" :icon="Delete" circle @click="deleteAccount(account)" />
                    </div>
                </div>
            </div>
        </div>

        <el-empty v-else-if="!showForm" description="Sin cuentas bancarias registradas" :image-size="60" />
    </div>
</template>
