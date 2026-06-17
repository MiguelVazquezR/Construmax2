<script setup>
import { ref, reactive, computed } from 'vue';
import { ElMessage } from 'element-plus';
import axios from 'axios';

const props = defineProps({
    modelValue: Boolean,
    customers: {
        type: Array,
        default: () => []
    },
    selectedCustomerId: {
        type: [Number, String],
        default: null
    }
});

const emit = defineEmits(['update:modelValue', 'created']);

const formRef = ref();
const isSubmitting = ref(false);

const form = reactive({
    customer_id: props.selectedCustomerId || '',
    country: 'México',
    region: '',
    city: '',
    unit: '',
    branch_name: '',
});

const rules = reactive({
    customer_id: [
        { required: true, message: 'Selecciona un cliente', trigger: 'change' }
    ],
    region: [
        { required: true, message: 'El estado es obligatorio', trigger: 'blur' }
    ],
    city: [
        { required: true, message: 'La ciudad es obligatoria', trigger: 'blur' }
    ],
    unit: [
        { required: true, message: 'La unidad/sucursal es obligatoria', trigger: 'blur' }
    ],
    branch_name: [
        { required: true, message: 'El nombre de sucursal es obligatorio', trigger: 'blur' }
    ],
});

const close = () => {
    emit('update:modelValue', false);
    resetForm();
};

const resetForm = () => {
    form.customer_id = props.selectedCustomerId || '';
    form.country = 'México';
    form.region = '';
    form.city = '';
    form.unit = '';
    form.branch_name = '';
    if (formRef.value) formRef.value.clearValidate();
};

const submit = () => {
    if (!formRef.value) return;

    formRef.value.validate(async (valid) => {
        if (valid) {
            isSubmitting.value = true;
            try {
                const response = await axios.post(route('customers.quick-branch'), form);
                ElMessage.success('Sucursal registrada correctamente.');
                emit('created', response.data.branch);
                close();
            } catch (error) {
                const msg = error.response?.data?.message || 'Error al registrar la sucursal.';
                ElMessage.error(msg);
            } finally {
                isSubmitting.value = false;
            }
        }
    });
};
</script>

<template>
    <el-dialog
        :model-value="modelValue"
        @update:model-value="close"
        title="Registro rápido de sucursal"
        width="450px"
        destroy-on-close
    >
        <p class="text-sm text-gray-500 mb-4">
            Añade una sucursal de forma rápida. Los datos faltantes se pueden actualizar posteriormente desde el módulo de clientes.
        </p>

        <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="submit">
            <el-form-item label="Cliente" prop="customer_id">
                <el-select v-model="form.customer_id" placeholder="Seleccionar cliente" class="w-full" filterable disabled>
                    <el-option
                        v-for="c in customers"
                        :key="c.id"
                        :label="c.name"
                        :value="c.id"
                    />
                </el-select>
            </el-form-item>

            <el-form-item label="País" prop="country">
                <el-input v-model="form.country" placeholder="Ej. México" />
            </el-form-item>

            <div class="grid grid-cols-2 gap-4">
                <el-form-item label="Estado" prop="region">
                    <el-input v-model="form.region" placeholder="Ej. Jalisco" />
                </el-form-item>

                <el-form-item label="Ciudad" prop="city">
                    <el-input v-model="form.city" placeholder="Ej. Guadalajara" />
                </el-form-item>
            </div>

            <el-form-item label="Unidad / tienda" prop="unit">
                <el-input v-model="form.unit" placeholder="Ej. Sucursal Centro" />
            </el-form-item>

            <el-form-item label="Nombre de sucursal" prop="branch_name">
                <el-input v-model="form.branch_name" placeholder="Ej. Tienda Guadalajara Centro" />
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="close">Cancelar</el-button>
            <el-button type="primary" color="#f26c17" @click="submit" :loading="isSubmitting" class="!font-bold">
                Guardar sucursal
            </el-button>
        </template>
    </el-dialog>
</template>
