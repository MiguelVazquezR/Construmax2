<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { Plus, Delete, InfoFilled } from '@element-plus/icons-vue';

const props = defineProps({
    modelValue: Boolean,
    templateToEdit: {
        type: Object,
        default: () => null
    }
});

const emit = defineEmits(['update:modelValue', 'saved']);

const formRef = ref();

const form = useForm({
    name: '',
    description: '',
    items: [{ name: '', description: '' }]
});

// Reiniciar o llenar el formulario cuando se abre el modal
watch(() => props.modelValue, (isOpen) => {
    if (isOpen) {
        if (props.templateToEdit) {
            form.name = props.templateToEdit.name;
            form.description = props.templateToEdit.description;
            form.items = props.templateToEdit.items && props.templateToEdit.items.length > 0
                ? JSON.parse(JSON.stringify(props.templateToEdit.items))
                : [{ name: '', description: '' }];
        } else {
            form.reset();
            form.items = [{ name: '', description: '' }];
        }
        form.clearErrors();
    }
});

const close = () => {
    emit('update:modelValue', false);
};

const addItem = () => {
    form.items.push({ name: '', description: '' });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    } else {
        ElMessage.warning('La plantilla debe tener al menos una tarea asignada.');
    }
};

const submit = () => {
    if (!formRef.value) return;

    formRef.value.validate((valid) => {
        if (valid) {
            if (props.templateToEdit) {
                form.put(route('task-templates.update', props.templateToEdit.id), {
                    onSuccess: () => {
                        ElMessage.success('Plantilla actualizada correctamente');
                        emit('saved');
                        close();
                    }
                });
            } else {
                form.post(route('task-templates.store'), {
                    onSuccess: () => {
                        ElMessage.success('Plantilla de tareas creada correctamente');
                        emit('saved');
                        close();
                    }
                });
            }
        } else {
            ElMessage.error('Por favor revisa los campos requeridos marcados en rojo.');
        }
    });
};
</script>

<template>
    <el-dialog
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
        :title="templateToEdit ? 'Editar Plantilla de Tareas' : 'Nueva Plantilla de Tareas'"
        width="650px"
        destroy-on-close
        class="rounded-xl"
        top="5vh"
    >
        <!-- Texto Informativo / Alerta -->
        <div class="mb-5">
            <el-alert
                type="info"
                show-icon
                :closable="false"
                class="!bg-blue-50 dark:!bg-blue-900/20 !text-blue-700 dark:!text-blue-400 border border-blue-100 dark:border-blue-800/50"
            >
                <template #title>
                    <span class="font-bold">¿Para qué sirve esta plantilla?</span>
                </template>
                <p class="mt-1 text-sm leading-relaxed">
                    Automatiza el registro de tareas en tus tickets. Las actividades que definas aquí se agendarán de forma automática para el o los técnicos que selecciones al momento de generar el ticket.
                </p>
            </el-alert>
        </div>

        <el-form :model="form" ref="formRef" label-position="top" @submit.prevent="submit">
            
            <!-- Datos de la Plantilla -->
            <div class="grid grid-cols-1 gap-4 mb-2">
                <el-form-item
                    label="Nombre de la plantilla"
                    prop="name"
                    :rules="[{ required: true, message: 'El nombre es obligatorio', trigger: 'blur' }]"
                >
                    <el-input v-model="form.name" placeholder="Ej. Mantenimiento Preventivo Mensual" />
                </el-form-item>

                <el-form-item label="Descripción (Opcional)" prop="description">
                    <el-input v-model="form.description" type="textarea" rows="2" placeholder="Breve propósito de esta plantilla..." />
                </el-form-item>
            </div>

            <!-- Lista de Tareas Dinámica -->
            <div class="flex justify-between items-center mb-4 mt-6 border-b border-gray-100 dark:border-gray-700 pb-2">
                <h4 class="font-semibold text-gray-800 dark:text-gray-200">Tareas incluidas en la plantilla</h4>
                <el-button type="primary" link size="small" @click="addItem" :icon="Plus">
                    Añadir otra tarea
                </el-button>
            </div>

            <div class="space-y-4 max-h-[40vh] overflow-y-auto pr-2 custom-scrollbar">
                <div
                    v-for="(item, index) in form.items"
                    :key="index"
                    class="p-4 bg-gray-50 dark:bg-[#1e1e20] border border-gray-200 dark:border-gray-700 rounded-lg relative transition-colors hover:border-gray-300 dark:hover:border-gray-600"
                >
                    <el-button
                        v-if="form.items.length > 1"
                        type="danger"
                        link
                        :icon="Delete"
                        class="absolute top-3 right-3"
                        @click="removeItem(index)"
                        title="Eliminar tarea"
                    />
                    
                    <div class="pr-8">
                        <el-form-item
                            :label="`Tarea #${index + 1}`"
                            :prop="`items.${index}.name`"
                            :rules="[{ required: true, message: 'El nombre de la tarea es requerido', trigger: 'blur' }]"
                            class="mb-3"
                        >
                            <el-input v-model="item.name" placeholder="Ej. Levantamiento" />
                        </el-form-item>

                        <el-form-item
                            :prop="`items.${index}.description`"
                            class="mb-0"
                        >
                            <el-input v-model="item.description" type="textarea" rows="2" placeholder="Instrucciones específicas (Opcional)" />
                        </el-form-item>
                    </div>
                </div>
            </div>
        </el-form>

        <template #footer>
            <div class="flex justify-end gap-3 pt-2">
                <el-button @click="close">Cancelar</el-button>
                <el-button type="primary" color="#f26c17" :loading="form.processing" class="!font-bold" @click="submit">
                    {{ templateToEdit ? 'Actualizar Plantilla' : 'Guardar Plantilla' }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #d1d5db;
    border-radius: 20px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #4b5563;
}
</style>