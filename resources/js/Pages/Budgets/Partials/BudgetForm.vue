<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { useForm, Link, usePage } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { Money, Loading, Delete, Plus, OfficeBuilding, Camera, ZoomIn } from '@element-plus/icons-vue';
import axios from 'axios';

const props = defineProps({
    mode: { type: String, required: true }, // 'create' | 'edit'
    tickets: { type: Array, required: true },
    users: { type: Array, required: true },
    technicians: { type: Array, default: () => [] },
    budget: { type: Object, default: null },
    preselectedTicketId: { type: Number, default: null },
});

const emit = defineEmits(['submitted']);

const page = usePage();
const formRef = ref();
const loadingRate = ref(false);

const isEdit = props.mode === 'edit';

const initialTicketId = isEdit ? props.budget?.ticket_id : (props.preselectedTicketId || null);

const form = useForm({
    ticket_id: initialTicketId,
    description: isEdit ? props.budget?.description : '',
    currency: isEdit ? (props.budget?.currency || 'MXN') : 'MXN',
    exchange_rate: isEdit ? (parseFloat(props.budget?.exchange_rate) || 1) : 1,
    user_id: isEdit ? props.budget?.user_id : (page.props.auth.user.id || null),
    concepts: isEdit && props.budget?.concepts?.length > 0
        ? props.budget.concepts.map(c => ({ 
            concept: c.concept, 
            amount: parseFloat(c.amount), 
            paid_to_technician: c.paid_to_technician ?? false,
            payment_date: c.payment_date || null,
        }))
        : [{ concept: '', amount: 0, paid_to_technician: false, payment_date: null }],
    survey_images: [],
    support_files: [],
});

// Si es edit y el cliente del ticket actual usa USD, cargar TC al montar
if (isEdit && props.budget?.ticket?.customer?.currency === 'USD') {
    fetchExchangeRate();
}

// If creating with a preselected ticket, auto-set currency
if (!isEdit && props.preselectedTicketId) {
    const ticket = props.tickets.find(t => t.id === props.preselectedTicketId);
    if (ticket?.customer?.currency === 'USD') {
        form.currency = 'USD';
        fetchExchangeRate();
    }
}

// --- TICKET SELECCIONADO ---

const selectedTicket = computed(() => {
    if (!form.ticket_id) return null;
    return props.tickets.find(t => t.id === form.ticket_id) || null;
});

const technicianDetails = computed(() => {
    return selectedTicket.value?.technicians_data || [];
});

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
        const response = await axios.get(route('exchange.rate'));
        const rate = response.data.rates?.MXN;
        if (rate) {
            form.exchange_rate = rate;
            ElMessage.success(`Tipo de cambio actualizado: $${rate}`);
        } else {
            throw new Error('Tasa no encontrada');
        }
    } catch (error) {
        console.error(error);
        ElMessage.warning('No se pudo obtener el tipo de cambio automáticamente. Por favor ingrésalo manualmente.');
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
    form.concepts.push({ concept: '', amount: 0, paid_to_technician: false, payment_date: null });
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

            const hasFiles = form.survey_images.length > 0 || form.support_files.length > 0;

            if (isEdit) {
                form
                    .transform(data => ({
                        ...data,
                        _method: 'PUT',
                    }))
                    .post(route('budgets.update', props.budget.id), {
                        forceFormData: hasFiles || false,
                        onSuccess: () => {
                            ElMessage.success('Presupuesto actualizado correctamente');
                            emit('submitted');
                        },
                    });
            } else {
                form.post(route('budgets.store'), {
                    forceFormData: hasFiles || false,
                    onSuccess: () => {
                        ElMessage.success('Presupuesto registrado correctamente');
                        emit('submitted');
                    },
                });
            }
        } else {
            ElMessage.error('Completa los campos obligatorios.');
        }
    });
};

// --- IMÁGENES DE LEVANTAMIENTO ---

const surveyImageList = ref([]);
const surveyUploadRef = ref(null);

const handleSurveyImagesChange = (file, fileListArg) => {
    surveyImageList.value = fileListArg;
    form.survey_images = fileListArg.map(f => f.raw);
};

const handleSurveyImagesRemove = (file, fileListArg) => {
    surveyImageList.value = fileListArg;
    form.survey_images = fileListArg.map(f => f.raw);
};

const removeSurveyImage = (index) => {
    surveyImageList.value.splice(index, 1);
    form.survey_images = surveyImageList.value.map(f => f.raw);
};

const previewSurveyImage = (file) => {
    const url = file.url || URL.createObjectURL(file.raw);
    window.open(url, '_blank');
};

defineExpose({ form });
</script>

<template>
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

            <!-- COLUMNA IZQUIERDA: Ticket + Datos + Costos -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Tarjeta: Selección de Ticket -->
                <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                        <el-icon class="text-primary"><OfficeBuilding /></el-icon> Ticket de origen
                    </h3>

                    <el-form-item label="Seleccionar ticket" prop="ticket_id">
                        <el-select
                            v-model="form.ticket_id"
                            :placeholder="isEdit ? 'Elige un ticket...' : 'Elige un ticket sin presupuesto...'"
                            class="w-full"
                            filterable
                            @change="handleTicketChange"
                        >
                            <el-option
                                v-for="ticket in tickets"
                                :key="ticket.id"
                                :label="`${ticket.folio} — ${ticket.name} (${ticket.customer?.name})`"
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
                            <div v-if="technicianDetails.length" class="mt-1 space-y-2">
                                <div
                                    v-for="tech in technicianDetails"
                                    :key="tech.name"
                                    class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                >
                                    <span class="font-medium">{{ tech.name }}</span>
                                    <el-tag
                                        :type="tech.is_internal === true ? 'success' : 'warning'"
                                        size="small"
                                        effect="plain"
                                    >
                                        {{ tech.is_internal ? 'Interno' : 'Externo' }}
                                    </el-tag>
                                    <span v-if="tech.state" class="text-gray-400 text-xs">
                                        — {{ tech.state }}
                                    </span>
                                    <span v-if="tech.phone" class="text-gray-400 text-xs">
                                        {{ tech.phone }}
                                    </span>
                                </div>
                            </div>
                            <p v-else class="text-gray-400 text-sm mt-1">—</p>
                        </div>
                    </div>

                    <div v-else class="text-center py-8 text-gray-400 text-sm">
                        <el-icon :size="32" class="mb-2 opacity-40"><OfficeBuilding /></el-icon>
                        <p>Selecciona un ticket para ver sus detalles</p>
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
                                    placeholder="TC"
                                    type="number"
                                    step="0.000001"
                                >
                                    <template #prefix><span class="text-xs text-gray-400">Tipo cambio:</span></template>
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
                            <div class="w-full sm:w-36">
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
                            <div class="flex items-center gap-2 shrink-0">
                                <el-checkbox v-model="item.paid_to_technician" label="Pago a técnico" />
                                <el-date-picker
                                    v-if="item.paid_to_technician"
                                    v-model="item.payment_date"
                                    type="date"
                                    placeholder="Fecha pago"
                                    class="!w-36"
                                    format="DD/MM/YYYY"
                                    value-format="YYYY-MM-DD"
                                    size="small"
                                />
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

                <!-- Tarjeta: Datos del presupuesto -->
                <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                        <el-icon class="text-primary"><Money /></el-icon> Datos del presupuesto
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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

                <!-- Tarjeta: Archivos de apoyo -->
                <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <el-icon class="text-primary"><FolderOpened /></el-icon> Archivos de apoyo
                        </h3>
                        <span class="text-xs text-gray-400">Opcional</span>
                    </div>
                    <p class="text-sm text-gray-500 mb-4">
                        Sube planos, cotizaciones, órdenes de compra o cualquier documento relacionado con este presupuesto.
                    </p>
                    <el-upload
                        ref="supportUploadRef"
                        :auto-upload="false"
                        :show-file-list="false"
                        :on-change="(file) => { form.support_files.push(file.raw); }"
                        :on-remove="(file) => { form.support_files = form.support_files.filter(f => f.name !== file.name || f.size !== file.size); }"
                        multiple
                        class="w-full"
                    >
                        <el-button type="primary" plain icon="Upload">Seleccionar archivos</el-button>
                        <template #tip>
                            <div class="el-upload__tip">Archivos PDF, imágenes, documentos (Máx. 10MB c/u)</div>
                        </template>
                    </el-upload>
                    <div v-if="form.support_files.length > 0" class="mt-3 flex flex-wrap gap-2">
                        <el-tag
                            v-for="(f, i) in form.support_files"
                            :key="i"
                            closable
                            type="info"
                            effect="plain"
                            @close="form.support_files.splice(i, 1)"
                        >
                            {{ f.name }}
                        </el-tag>
                    </div>
                </div>

                <!-- Tarjeta: Imágenes de levantamiento -->
                <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <el-icon class="text-primary"><Camera /></el-icon> Imágenes de levantamiento
                        </h3>
                        <span class="text-xs text-gray-400">Opcional</span>
                    </div>

                    <p class="text-sm text-gray-500 mb-4">
                        Adjunta las fotos que tomó el técnico durante el levantamiento para que el área de costos pueda revisar los detalles del trabajo requerido.
                    </p>

                    <el-upload
                        ref="surveyUploadRef"
                        v-model:file-list="surveyImageList"
                        :auto-upload="false"
                        :on-change="handleSurveyImagesChange"
                        :on-remove="handleSurveyImagesRemove"
                        list-type="picture-card"
                        multiple
                        accept="image/*"
                    >
                        <template #default>
                            <el-icon><Plus /></el-icon>
                        </template>
                        <template #file="{ file }">
                            <div class="relative group">
                                <img
                                    :src="file.url"
                                    class="w-full h-full object-cover rounded"
                                    alt="Preview"
                                />
                                <div class="absolute inset-0 bg-black/50 rounded opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                    <el-button
                                        circle
                                        size="small"
                                        type="primary"
                                        :icon="ZoomIn"
                                        @click="previewSurveyImage(file)"
                                    />
                                    <el-button
                                        circle
                                        size="small"
                                        type="danger"
                                        :icon="Delete"
                                        @click="surveyUploadRef.handleRemove(file)"
                                    />
                                </div>
                            </div>
                        </template>
                    </el-upload>
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
                        <div class="flex justify-between">
                            <span class="text-gray-500">Imágenes</span>
                            <span class="font-medium text-gray-800 dark:text-white">{{ form.survey_images.length || 0 }}</span>
                        </div>
                        <el-divider class="!my-3" />
                        <div class="flex justify-between text-base">
                            <span class="font-semibold text-gray-700 dark:text-gray-200">Total</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(totalCost) }}</span>
                        </div>
                    </div>

                    <div class="mt-8 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <!-- Info message: only for new budgets -->
                        <el-alert
                            v-if="!isEdit"
                            class="mb-4"
                            title="Al guardar, el ticket pasará a estado Catálogo para que el área de costos genere el catálogo correspondiente."
                            type="info"
                            :closable="false"
                            show-icon
                        />
                        <el-button
                            type="primary"
                            native-type="submit"
                            class="w-full !font-bold !h-12 !text-lg"
                            color="#f26c17"
                            :loading="form.processing"
                        >
                            {{ isEdit ? 'Actualizar presupuesto' : 'Guardar presupuesto' }}
                        </el-button>
                        <div class="text-center mt-3">
                            <Link :href="route('budgets.index')" class="text-sm text-gray-500 hover:text-primary">
                                {{ isEdit ? 'Cancelar' : 'Cancelar operación' }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </el-form>
</template>
