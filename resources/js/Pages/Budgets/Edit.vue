<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';
import {
    Money,
    Loading,
    Delete,
    Plus,
    OfficeBuilding,
} from '@element-plus/icons-vue';
import axios from 'axios';

const props = defineProps({
    budget: Object,
    tickets: Array,
    users: Array,
});

const formRef = ref();
const loadingRate = ref(false);

const statuses = [
    'Borrador',
    'Presupuesto enviado', 'Facturado', 'Trabajo en proceso',
    'Trabajo terminado', 'Pagado', 'Perdido',
];

const form = useForm({
    ticket_id: props.budget.ticket_id,
    status: props.budget.status,
    description: props.budget.description,
    currency: props.budget.currency || 'MXN',
    exchange_rate: parseFloat(props.budget.exchange_rate) || 1,
    user_id: props.budget.user_id,
    concepts: props.budget.concepts.length > 0
        ? props.budget.concepts.map(c => ({ concept: c.concept, amount: parseFloat(c.amount) }))
        : [{ concept: '', amount: 0 }],
});

// --- TICKET SELECCIONADO ---

const selectedTicket = computed(() => {
    if (!form.ticket_id) return null;
    return props.tickets.find(t => t.id === form.ticket_id) || null;
});

const technicianNames = computed(() => {
    if (!selectedTicket.value?.technicians) return [];
    return selectedTicket.value.technicians.map(id => {
        const user = props.users.find(u => u.id === id);
        return user ? user.name : `ID #${id}`;
    });
});

// --- REACCIÓN AL CAMBIAR TICKET ---

const handleTicketChange = () => {
    const ticket = selectedTicket.value;
    if (!ticket) return;

    if (ticket.customer?.currency) {
        form.currency = ticket.customer.currency;
        if (ticket.customer.currency === 'USD') {
            fetchExchangeRate();
        } else {
            form.exchange_rate = 1;
        }
    }
};

// --- GESTIÓN DE MONEDA ---

watch(() => form.currency, async (newCurrency, oldCurrency) => {
    if (newCurrency === oldCurrency) return;
    if (newCurrency === 'MXN') {
        form.exchange_rate = 1;
    } else if (newCurrency === 'USD') {
        fetchExchangeRate();
    }
});

const fetchExchangeRate = async () => {
    loadingRate.value = true;
    try {
        const response = await axios.get('https://open.er-api.com/v6/latest/USD');
        const rate = response.data.rates.MXN;
        if (rate) {
            form.exchange_rate = rate;
            ElMessage.success(`Tipo de cambio actualizado: $${rate}`);
        }
    } catch (error) {
        console.error(error);
        ElMessage.warning('No se pudo obtener el tipo de cambio automáticamente.');
    } finally {
        loadingRate.value = false;
    }
};

// --- LÓGICA DE COSTOS ---

const totalCost = computed(() => {
    return form.concepts.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: form.currency }).format(value);
};

const addConcept = () => {
    form.concepts.push({ concept: '', amount: 0 });
};

const removeConcept = (index) => {
    if (form.concepts.length > 1) {
        form.concepts.splice(index, 1);
    } else {
        ElMessage.warning('Debe haber al menos un concepto de costo.');
    }
};

const rules = reactive({
    ticket_id: [{ required: true, message: 'Selecciona un ticket', trigger: 'change' }],
    status: [{ required: true, message: 'Requerido', trigger: 'change' }],
    user_id: [{ required: true, message: 'Requerido', trigger: 'change' }],
    currency: [{ required: true, message: 'Requerido', trigger: 'change' }],
    exchange_rate: [{ required: true, message: 'Requerido', trigger: 'blur' }],
});

const submit = () => {
    if (!formRef.value) return;

    formRef.value.validate((valid) => {
        if (valid) {
            const invalidCost = form.concepts.some(c => c.concept.trim() === '' || c.amount < 0);
            if (invalidCost) {
                ElMessage.error('Revisa los conceptos de costos.');
                return;
            }

            form.put(route('budgets.update', props.budget.id), {
                onSuccess: () => ElMessage.success('Presupuesto actualizado correctamente'),
            });
        } else {
            ElMessage.error('Completa los campos obligatorios.');
        }
    });
};
</script>

<template>
    <AppLayout :title="`Editar presupuesto #${budget.id}`">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-gray-800 dark:text-white leading-tight">
                    Editar presupuesto #{{ budget.id }}
                </h2>
                <Link :href="route('budgets.index')">
                    <el-button icon="Back" circle />
                </Link>
            </div>
        </template>

        <div class="max-w-6xl mx-auto py-6 sm:px-6 lg:px-8">
            <el-form
                ref="formRef"
                :model="form"
                :rules="rules"
                label-position="top"
                require-asterisk-position="right"
                size="large"
                @submit.prevent="submit"
            >
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- COLUMNA IZQUIERDA: Ticket info + Datos + Costos -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- Tarjeta: Selección de Ticket -->
                        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                                <el-icon class="text-primary"><OfficeBuilding /></el-icon> Ticket de origen
                            </h3>

                            <el-form-item label="Seleccionar ticket" prop="ticket_id">
                                <el-select
                                    v-model="form.ticket_id"
                                    placeholder="Elige un ticket..."
                                    class="w-full"
                                    filterable
                                    @change="handleTicketChange"
                                >
                                    <el-option
                                        v-for="ticket in tickets"
                                        :key="ticket.id"
                                        :label="`#${ticket.id} — ${ticket.name} (${ticket.customer?.name})`"
                                        :value="ticket.id"
                                    />
                                </el-select>
                            </el-form-item>

                            <!-- Datos del ticket seleccionado -->
                            <div v-if="selectedTicket" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 p-4 bg-gray-50 dark:bg-[#252529] rounded-lg border border-gray-200 dark:border-[#3f3f46]">
                                <div class="md:col-span-2">
                                    <span class="text-xs text-gray-400 uppercase tracking-wide">Proyecto</span>
                                    <p class="font-semibold text-gray-800 dark:text-white">{{ selectedTicket.name }}</p>
                                </div>

                                <div>
                                    <span class="text-xs text-gray-400 uppercase tracking-wide">Cliente</span>
                                    <p class="text-gray-700 dark:text-gray-300">{{ selectedTicket.customer?.name }}</p>
                                </div>

                                <div>
                                    <span class="text-xs text-gray-400 uppercase tracking-wide">Tipo de servicio</span>
                                    <p class="text-gray-700 dark:text-gray-300">{{ selectedTicket.service_type }}</p>
                                </div>

                                <div>
                                    <span class="text-xs text-gray-400 uppercase tracking-wide">Contacto</span>
                                    <p class="text-gray-700 dark:text-gray-300">{{ selectedTicket.contact?.name || '—' }}</p>
                                </div>

                                <div>
                                    <span class="text-xs text-gray-400 uppercase tracking-wide">Sucursal</span>
                                    <p class="text-gray-700 dark:text-gray-300">{{ selectedTicket.branch?.branch_name || '—' }}</p>
                                </div>

                                <div>
                                    <span class="text-xs text-gray-400 uppercase tracking-wide">Prioridad</span>
                                    <el-tag size="small" :type="selectedTicket.priority === 'Urgente' ? 'danger' : 'info'">
                                        {{ selectedTicket.priority }}
                                    </el-tag>
                                </div>

                                <div>
                                    <span class="text-xs text-gray-400 uppercase tracking-wide">Duración estimada</span>
                                    <p class="text-gray-700 dark:text-gray-300">{{ selectedTicket.duration || '—' }}</p>
                                </div>

                                <div class="md:col-span-2">
                                    <span class="text-xs text-gray-400 uppercase tracking-wide">Técnicos asignados</span>
                                    <p class="text-gray-700 dark:text-gray-300">
                                        <template v-if="technicianNames.length">
                                            <el-tag v-for="name in technicianNames" :key="name" size="small" class="mr-1 mb-1" round>{{ name }}</el-tag>
                                        </template>
                                        <span v-else class="text-gray-400">—</span>
                                    </p>
                                </div>
                            </div>

                            <div v-else class="text-center py-8 text-gray-400 text-sm">
                                <el-icon :size="32" class="mb-2 opacity-40"><OfficeBuilding /></el-icon>
                                <p>Selecciona un ticket para ver sus detalles</p>
                            </div>
                        </div>

                        <!-- Tarjeta: Datos del presupuesto -->
                        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                                <el-icon class="text-primary"><Money /></el-icon> Datos del presupuesto
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <el-form-item label="Estado actual" prop="status">
                                    <el-select v-model="form.status" class="w-full">
                                        <el-option v-for="item in statuses" :key="item" :label="item" :value="item" />
                                    </el-select>
                                </el-form-item>

                                <el-form-item label="Responsable interno" prop="user_id">
                                    <el-select v-model="form.user_id" placeholder="Asignar a..." class="w-full" filterable>
                                        <el-option
                                            v-for="user in users"
                                            :key="user.id"
                                            :label="user.name"
                                            :value="user.id"
                                        />
                                    </el-select>
                                </el-form-item>

                                <el-form-item label="Descripción / notas comerciales" class="md:col-span-2">
                                    <el-input
                                        v-model="form.description"
                                        type="textarea"
                                        :rows="3"
                                        placeholder="Alcances, condiciones comerciales, notas..."
                                    />
                                </el-form-item>
                            </div>
                        </div>

                        <!-- Tarjeta: Costos y Moneda -->
                        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                    <el-icon class="text-primary"><Money /></el-icon> Desglose de costos
                                </h3>

                                <div class="flex gap-2 items-center">
                                    <el-form-item prop="currency" class="!mb-0 w-24">
                                        <el-select v-model="form.currency" placeholder="Moneda">
                                            <el-option label="MXN" value="MXN" />
                                            <el-option label="USD" value="USD" />
                                        </el-select>
                                    </el-form-item>

                                    <el-form-item
                                        v-if="form.currency === 'USD'"
                                        prop="exchange_rate"
                                        class="!mb-0 w-40"
                                        :rules="[{ required: true, message: 'Requerido', trigger: 'blur' }]"
                                    >
                                        <el-input
                                            v-model="form.exchange_rate"
                                            placeholder="Tipo cambio"
                                            type="number"
                                            step="0.0001"
                                        >
                                            <template #prefix><span class="text-xs text-gray-400">TC:</span></template>
                                            <template #suffix>
                                                <el-icon v-if="loadingRate" class="is-loading"><Loading /></el-icon>
                                            </template>
                                        </el-input>
                                    </el-form-item>

                                    <el-button size="small" type="primary" plain :icon="Plus" @click="addConcept">
                                        Agregar
                                    </el-button>
                                </div>
                            </div>

                            <div class="bg-gray-50 dark:bg-[#252529] rounded-lg p-4 border border-gray-200 dark:border-[#3f3f46]">
                                <div v-for="(item, index) in form.concepts" :key="index" class="flex flex-col sm:flex-row gap-3 mb-3 last:mb-0 items-start sm:items-center">
                                    <div class="flex-1 w-full">
                                        <el-input v-model="item.concept" placeholder="Concepto" />
                                    </div>
                                    <div class="w-full sm:w-40">
                                        <el-input-number
                                            v-model="item.amount"
                                            :min="0"
                                            :precision="2"
                                            class="!w-full"
                                            placeholder="Monto"
                                            controls-position="right"
                                        >
                                            <template #prefix>$</template>
                                        </el-input-number>
                                    </div>
                                    <el-button
                                        type="danger"
                                        :icon="Delete"
                                        circle
                                        plain
                                        @click="removeConcept(index)"
                                        :disabled="form.concepts.length === 1"
                                    />
                                </div>
                            </div>

                            <div class="flex justify-end mt-4 items-center gap-4">
                                <span class="text-gray-500 text-sm font-medium uppercase">Total presupuesto:</span>
                                <span class="text-2xl font-bold text-gray-800 dark:text-white">
                                    {{ formatCurrency(totalCost) }} <span class="text-sm text-gray-400">{{ form.currency }}</span>
                                </span>
                            </div>

                            <div v-if="form.currency === 'USD'" class="text-right mt-1 text-xs text-gray-500">
                                Aprox: {{ new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(totalCost * form.exchange_rate) }} MXN
                            </div>
                        </div>

                    </div>

                    <!-- COLUMNA DERECHA: Resumen + Acción -->
                    <div class="lg:col-span-1 space-y-6">
                        <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6 sticky top-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                Resumen
                            </h3>

                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Ticket</span>
                                    <span class="font-medium text-gray-800 dark:text-white">{{ selectedTicket ? `#${selectedTicket.id}` : '—' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Proyecto</span>
                                    <span class="font-medium text-gray-800 dark:text-white truncate ml-2 max-w-[180px]">{{ selectedTicket?.name || '—' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Cliente</span>
                                    <span class="text-gray-800 dark:text-gray-300">{{ selectedTicket?.customer?.name || '—' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Conceptos</span>
                                    <span class="font-medium text-gray-800 dark:text-white">{{ form.concepts.length }}</span>
                                </div>
                                <el-divider class="!my-3" />
                                <div class="flex justify-between text-base">
                                    <span class="font-semibold text-gray-700 dark:text-gray-200">Total</span>
                                    <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(totalCost) }}</span>
                                </div>
                            </div>

                            <div class="mt-8 pt-4 border-t border-gray-100 dark:border-gray-700">
                                <el-button
                                    type="primary"
                                    native-type="submit"
                                    class="w-full !font-bold !h-12 !text-lg"
                                    color="#f26c17"
                                    :loading="form.processing"
                                >
                                    Actualizar presupuesto
                                </el-button>
                                <div class="text-center mt-3">
                                    <Link :href="route('budgets.index')" class="text-sm text-gray-500 hover:text-primary">
                                        Cancelar
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </el-form>
        </div>
    </AppLayout>
</template>