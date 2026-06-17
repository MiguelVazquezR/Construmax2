<script setup>
import { ref, reactive } from 'vue';
import { ElMessage } from 'element-plus';
import axios from 'axios';

const props = defineProps({
    modelValue: Boolean
});

const emit = defineEmits(['update:modelValue', 'created']);

const formRef = ref();
const isSubmitting = ref(false);

const form = reactive({
    name: '',
    phone: '',
    is_internal: false, // NUEVO CAMPO
    level: 'Encargado',
});

const rules = reactive({
    name: [
        { required: true, message: 'El nombre es obligatorio', trigger: 'blur' },
        { min: 3, message: 'Ingresa el nombre completo', trigger: 'blur' }
    ],
    phone: [
        { required: true, message: 'El teléfono es obligatorio', trigger: 'blur' }
    ]
});

const close = () => {
    emit('update:modelValue', false);
    form.name = '';
    form.phone = '';
    form.is_internal = false;
    form.level = 'Encargado';
    if (formRef.value) formRef.value.clearValidate();
};

const submit = () => {
    if (!formRef.value) return;
    
    formRef.value.validate(async (valid) => {
        if (valid) {
            isSubmitting.value = true;
            try {
                const response = await axios.post(route('technicians.quick-store'), form);
                ElMessage.success('Técnico registrado correctamente.');
                emit('created', response.data.user);
                close();
            } catch (error) {
                ElMessage.error('Hubo un error al registrar el técnico.');
                console.error(error);
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
        title="Registro Rápido de Técnico"
        width="400px"
        destroy-on-close
    >
        <p class="text-sm text-gray-500 mb-4">
            Añade un proveedor de forma rápida. Los datos faltantes se pueden actualizar posteriormente desde el módulo de Técnicos.
        </p>

        <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="submit">
            <el-form-item label="Nombre completo" prop="name">
                <el-input v-model="form.name" placeholder="Ej. Juan Pérez López" />
            </el-form-item>
            
            <el-form-item label="Teléfono / Celular" prop="phone">
                <el-input v-model="form.phone" placeholder="Ej. 3312345678" />
            </el-form-item>

            <el-form-item label="Tipo de colaborador">
                <el-switch
                    v-model="form.is_internal"
                    active-text="Empleado interno"
                    inactive-text="Proveedor externo"
                    style="--el-switch-on-color: #f26c17;"
                />
            </el-form-item>

            <el-form-item label="Nivel / categoría">
                <el-select v-model="form.level" class="w-full">
                    <el-option label="Encargado" value="Encargado" />
                    <el-option label="Auxiliar / Ayudante" value="Auxiliar/Ayudante" />
                </el-select>
            </el-form-item>
        </el-form>
        
        <template #footer>
            <el-button @click="close">Cancelar</el-button>
            <el-button type="primary" color="#f26c17" @click="submit" :loading="isSubmitting" class="!font-bold">
                Guardar Técnico
            </el-button>
        </template>
    </el-dialog>
</template>