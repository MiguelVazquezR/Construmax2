<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Avatar } from '@element-plus/icons-vue';

const props = defineProps({
    budget: Object,
});

const emit = defineEmits(['preview']);

const showTechPaymentModal = ref(false);
const techPaymentUploadRef = ref(null);
const selectedTechnician = ref(null);

const techPaymentForm = useForm({
    user_id: null,
    amount: 0,
    payment_date: new Date().toISOString().split('T')[0],
    payment_method: 'Transferencia',
    reference: '',
    notes: '',
    proof: null,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: props.budget.currency || 'MXN',
    }).format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('es-MX', {
        year: 'numeric', month: 'long', day: 'numeric',
    });
};

const techniciansData = computed(() => {
    const techs = {};

    // 1. Procesar tareas del ticket (solo técnicos externos)
    if (props.budget.ticket?.tasks) {
        props.budget.ticket.tasks.forEach(task => {
            if (task.assignee) {
                // Saltar técnicos internos
                if (task.assignee.technician?.is_internal) return;

                const userId = task.user_id;
                if (!techs[userId]) {
                    techs[userId] = {
                        user: task.assignee,
                        total_tasks: 0,
                        completed_tasks: 0,
                        payments: [],
                        total_paid: 0,
                    };
                }
                techs[userId].total_tasks++;
                if (task.status === 'Completada') {
                    techs[userId].completed_tasks++;
                }
            }
        });
    }

    // 2. Procesar pagos a técnicos (solo externos)
    if (props.budget.technician_payments) {
        props.budget.technician_payments.forEach(payment => {
            const userId = payment.user_id;

            // Saltar técnicos internos (payment.technician es User, User.technician es Technician)
            if (payment.technician?.technician?.is_internal) return;

            if (!techs[userId]) {
                techs[userId] = {
                    user: payment.technician,
                    total_tasks: 0,
                    completed_tasks: 0,
                    payments: [],
                    total_paid: 0,
                };
            }
            techs[userId].payments.push(payment);
            techs[userId].total_paid += parseFloat(payment.amount);
        });
    }

    return Object.values(techs).map(tech => ({
        ...tech,
        progress: tech.total_tasks > 0 ? Math.round((tech.completed_tasks / tech.total_tasks) * 100) : 0,
    }));
});

const openTechPaymentModal = (tech) => {
    selectedTechnician.value = tech;
    techPaymentForm.reset();
    techPaymentForm.user_id = tech.user.id;
    if (techPaymentUploadRef.value) techPaymentUploadRef.value.clearFiles();
    showTechPaymentModal.value = true;
};

const handleTechProofChange = (file) => {
    techPaymentForm.proof = file.raw;
};

const submitTechPayment = () => {
    techPaymentForm.post(route('budgets.technician-payments.store', props.budget.id), {
        onSuccess: () => {
            showTechPaymentModal.value = false;
            techPaymentForm.reset();
            ElMessage.success('Pago a técnico registrado');
        },
        onError: () => ElMessage.error('Error al registrar pago'),
        forceFormData: true,
    });
};

const deleteTechPayment = (paymentId) => {
    ElMessageBox.confirm('¿Eliminar este registro de pago a técnico?', 'Confirmar', {
        type: 'warning',
    }).then(() => {
        router.delete(route('budgets.technician-payments.destroy', paymentId), {
            onSuccess: () => ElMessage.success('Pago eliminado'),
        });
    }).catch(() => {});
};

const openPreview = (file) => {
    emit('preview', file);
};
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
        <div class="p-4 bg-gray-50 dark:bg-[#252529] border-b border-gray-100 dark:border-[#2b2b2e] flex flex-col sm:flex-row justify-between items-center gap-2">
            <h3 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                <el-icon><Avatar /></el-icon> Gestión de técnicos y pagos
            </h3>
            <span class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded">Basado en tareas del Ticket {{ budget.ticket?.folio || 'N/A' }}</span>
        </div>

        <div v-if="techniciansData.length > 0" class="p-4 space-y-6">
            <div v-for="tech in techniciansData" :key="tech.user.id" class="border border-gray-100 dark:border-[#3f3f46] rounded-lg p-4 hover:shadow-sm transition-shadow">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-3">
                    <div class="flex items-center gap-3">
                        <el-avatar :src="tech.user.profile_photo_url" :size="40">{{ tech.user.name.charAt(0) }}</el-avatar>
                        <div>
                            <p class="font-bold text-gray-800 dark:text-white">{{ tech.user.name }}</p>
                            <p class="text-xs text-gray-500">{{ tech.completed_tasks }} / {{ tech.total_tasks }} tareas completadas</p>
                        </div>
                    </div>

                    <div class="w-full md:w-1/3 px-2">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-gray-400">Progreso</span>
                            <span class="font-bold text-gray-700 dark:text-gray-300">{{ tech.progress }}%</span>
                        </div>
                        <el-progress :percentage="tech.progress" :show-text="false" :status="tech.progress === 100 ? 'success' : ''" />
                    </div>

                    <div class="flex items-center gap-3 w-full md:w-auto justify-between md:justify-end">
                        <div class="text-right">
                            <p class="text-xs text-gray-400">Total pagado</p>
                            <p class="font-bold text-green-600">{{ formatCurrency(tech.total_paid) }}</p>
                        </div>
                        <el-button type="success" size="small" plain icon="Money" @click="openTechPaymentModal(tech)">
                            Pagar
                        </el-button>
                    </div>
                </div>

                <div v-if="tech.payments.length > 0" class="mt-3 bg-gray-50 dark:bg-[#252529] rounded p-3 text-sm">
                    <p class="text-xs font-bold text-gray-500 mb-2 uppercase">Historial de pagos</p>
                    <ul class="space-y-2">
                        <li v-for="pay in tech.payments" :key="pay.id" class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 last:border-0 pb-1 last:pb-0">
                            <div class="flex gap-2 items-center">
                                <span class="font-mono font-bold">{{ formatCurrency(pay.amount) }}</span>
                                <span class="text-xs text-gray-400">({{ formatDate(pay.payment_date) }})</span>
                                <el-tag v-if="pay.reference" size="small" type="info" class="scale-90">{{ pay.reference }}</el-tag>
                            </div>
                            <div class="flex gap-1">
                                <el-tooltip content="Ver comprobante" placement="top" v-if="pay.media && pay.media.length > 0">
                                    <el-button circle size="small" icon="Document" @click="openPreview(pay.media[0])" />
                                </el-tooltip>
                                <el-button circle size="small" type="danger" plain icon="Delete" @click="deleteTechPayment(pay.id)" />
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div v-else class="p-8 text-center text-gray-500">
            <el-icon :size="40" class="mb-2 opacity-50"><User /></el-icon>
            <p>Esta sección es para gestión de pagos de técnicos externos.</p>
            <p class="text-xs mt-1">Si no hay técnicos externos asignados, esta sección estará vacía.</p>
        </div>
    </div>

    <!-- Modal pago a técnico -->
    <el-dialog
        v-model="showTechPaymentModal"
        :title="`Pago a técnico: ${selectedTechnician?.user?.name}`"
        width="450px"
    >
        <el-form :model="techPaymentForm" label-position="top">
            <el-alert
                title="Importante"
                type="info"
                description="El comprobante es obligatorio para los pagos a personal técnico."
                show-icon
                :closable="false"
                class="mb-4"
            />

            <el-form-item label="Monto a pagar">
                <el-input-number v-model="techPaymentForm.amount" :min="0.01" :precision="2" class="!w-full">
                    <template #prefix>$</template>
                </el-input-number>
            </el-form-item>

            <div class="grid grid-cols-2 gap-4">
                <el-form-item label="Fecha">
                    <el-date-picker v-model="techPaymentForm.payment_date" type="date" class="!w-full" format="DD/MM/YYYY" value-format="YYYY-MM-DD" />
                </el-form-item>
                <el-form-item label="Método">
                    <el-select v-model="techPaymentForm.payment_method" class="w-full">
                        <el-option label="Transferencia" value="Transferencia" />
                        <el-option label="Efectivo" value="Efectivo" />
                        <el-option label="Nómina" value="Nómina" />
                    </el-select>
                </el-form-item>
            </div>

            <el-form-item label="Referencia bancaria">
                <el-input v-model="techPaymentForm.reference" placeholder="Ej. SPEI-123456" />
            </el-form-item>

            <el-form-item label="Notas">
                <el-input v-model="techPaymentForm.notes" type="textarea" placeholder="Concepto o detalles..." />
            </el-form-item>

            <el-form-item label="Comprobante de pago (Obligatorio)" :error="techPaymentForm.errors.proof">
                <el-upload
                    ref="techPaymentUploadRef"
                    class="w-full"
                    :auto-upload="false"
                    :limit="1"
                    :on-change="handleTechProofChange"
                    accept="image/*,.pdf"
                >
                    <template #trigger>
                        <el-button type="primary" plain icon="Upload">Adjuntar comprobante</el-button>
                    </template>
                    <template #tip>
                        <div class="el-upload__tip">Archivos PDF o Imagen (Máx. 5MB)</div>
                    </template>
                </el-upload>
            </el-form-item>
        </el-form>
        <template #footer>
            <span class="dialog-footer">
                <el-button @click="showTechPaymentModal = false">Cancelar</el-button>
                <el-button type="success" @click="submitTechPayment" :loading="techPaymentForm.processing">
                    Registrar pago
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>
