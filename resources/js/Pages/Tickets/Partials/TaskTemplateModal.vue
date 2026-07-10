<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Delete, Search, Edit } from '@element-plus/icons-vue';
import { usePermissions } from '@/Composables/usePermissions';
import axios from 'axios';

const { can } = usePermissions();

const props = defineProps({
    modelValue: Boolean,
});

const emit = defineEmits(['update:modelValue', 'saved']);

const formRef = ref();
const templateSearch = ref('');
const allTemplates = ref([]);
const loadingTemplates = ref(false);
const showForm = ref(false); // Whether to show the create/edit form
const editingTemplate = ref(null); // Local ref to track which template is being edited

const form = useForm({
    name: '',
    description: '',
    items: [{ name: '', description: '' }],
});

const editingMode = computed(() => editingTemplate.value !== null);

const filteredTemplates = computed(() => {
    if (!templateSearch.value) return allTemplates.value;
    const search = templateSearch.value.toLowerCase();
    return allTemplates.value.filter(t =>
        t.name.toLowerCase().includes(search)
    );
});

const fetchTemplates = async () => {
    loadingTemplates.value = true;
    try {
        const { data } = await axios.get(route('task-templates.index'), {
            params: { search: templateSearch.value },
        });
        allTemplates.value = data;
    } catch (err) {
        ElMessage.error('Error al cargar las plantillas.');
    } finally {
        loadingTemplates.value = false;
    }
};

onMounted(() => {
    fetchTemplates();
});

const resetForm = () => {
    form.reset();
    form.items = [{ name: '', description: '' }];
    form.clearErrors();
};

// When modal opens, reset state
watch(() => props.modelValue, (isOpen) => {
    if (isOpen) {
        showForm.value = false;
        editingTemplate.value = null;
        resetForm();
        fetchTemplates();
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
            if (editingMode.value) {
                form.put(route('task-templates.update', editingTemplate.value.id), {
                    onSuccess: () => {
                        ElMessage.success('Plantilla actualizada correctamente.');
                        emit('saved');
                        fetchTemplates();
                        showForm.value = false;
                        editingTemplate.value = null;
                        resetForm();
                    },
                });
            } else {
                form.post(route('task-templates.store'), {
                    onSuccess: () => {
                        ElMessage.success('Plantilla de tareas creada correctamente.');
                        emit('saved');
                        fetchTemplates();
                        showForm.value = false;
                        resetForm();
                    },
                });
            }
        } else {
            ElMessage.error('Por favor revisa los campos requeridos marcados en rojo.');
        }
    });
};

const startEditTemplate = (template) => {
    form.name = template.name;
    form.description = template.description || '';
    form.items = template.items && template.items.length > 0
        ? JSON.parse(JSON.stringify(template.items))
        : [{ name: '', description: '' }];
    editingTemplate.value = template;
    showForm.value = true;
    form.clearErrors();
};

const startNewTemplate = () => {
    resetForm();
    editingTemplate.value = null;
    showForm.value = true;
};

const cancelEdit = () => {
    showForm.value = false;
    editingTemplate.value = null;
    resetForm();
};

const deleteTemplate = (template) => {
    ElMessageBox.confirm(
        `¿Estás seguro de eliminar la plantilla "${template.name}"? Esta acción no se puede deshacer.`,
        'Eliminar plantilla',
        {
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            type: 'error',
        }
    ).then(() => {
        router.delete(route('task-templates.destroy', template.id), {
            onSuccess: () => {
                ElMessage.success('Plantilla eliminada correctamente.');
                fetchTemplates();
                emit('saved');
            },
        });
    }).catch(() => {});
};
</script>

<template>
    <el-dialog
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
        :title="editingMode ? 'Editar plantilla de tareas' : 'Plantillas de tareas'"
        width="700px"
        destroy-on-close
        class="rounded-xl"
        top="3vh"
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

        <!-- LISTA DE PLANTILLAS EXISTENTES -->
        <div v-if="!showForm">
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-semibold text-gray-800 dark:text-gray-200">Plantillas existentes</h4>
                <el-button v-if="can('tickets.create-tasks-template')" type="primary" size="small" color="#f26c17" :icon="Plus" @click="startNewTemplate">
                    Nueva plantilla
                </el-button>
            </div>

            <el-input
                v-model="templateSearch"
                placeholder="Buscar plantilla por nombre..."
                :prefix-icon="Search"
                clearable
                class="mb-4"
                @input="fetchTemplates"
            />

            <div class="space-y-2 max-h-[40vh] overflow-y-auto custom-scrollbar">
                <div
                    v-for="tpl in filteredTemplates"
                    :key="tpl.id"
                    class="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#1e1e20] hover:border-gray-300 dark:hover:border-gray-600 transition-colors"
                >
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-800 dark:text-gray-200 text-sm truncate">{{ tpl.name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                            {{ tpl.items?.length || 0 }} tarea(s) · {{ tpl.description || 'Sin descripción' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-1 ml-3 shrink-0">
                        <el-button
                            v-if="can('tickets.edit-tasks-template')"
                            size="small"
                            type="primary"
                            plain
                            :icon="Edit"
                            @click="startEditTemplate(tpl)"
                        >
                            Editar
                        </el-button>
                        <el-button
                            v-if="can('tickets.delete-tasks-template')"
                            size="small"
                            type="danger"
                            plain
                            :icon="Delete"
                            @click="deleteTemplate(tpl)"
                        >
                            Eliminar
                        </el-button>
                    </div>
                </div>
                <el-empty v-if="filteredTemplates.length === 0 && !loadingTemplates" description="No se encontraron plantillas" :image-size="40" />
            </div>
        </div>

        <!-- FORMULARIO DE CREACIÓN / EDICIÓN -->
        <div v-else>
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-semibold text-gray-800 dark:text-gray-200">
                    {{ editingMode ? 'Editando: ' + editingTemplate.name : 'Nueva plantilla' }}
                </h4>
                <el-button size="small" @click="cancelEdit">
                    Volver a la lista
                </el-button>
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

                    <el-form-item label="Descripción (opcional)" prop="description">
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

                <div class="space-y-4 max-h-[30vh] overflow-y-auto pr-2 custom-scrollbar">
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
                                <el-input v-model="item.description" type="textarea" rows="2" placeholder="Instrucciones específicas (opcional)" />
                            </el-form-item>
                        </div>
                    </div>
                </div>
            </el-form>
        </div>

        <template #footer>
            <div class="flex justify-end gap-3 pt-2">
                <el-button @click="close">Cerrar</el-button>
                <el-button
                    v-if="showForm"
                    type="primary"
                    color="#f26c17"
                    :loading="form.processing"
                    class="!font-bold"
                    @click="submit"
                >
                    {{ editingMode ? 'Actualizar plantilla' : 'Guardar plantilla' }}
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