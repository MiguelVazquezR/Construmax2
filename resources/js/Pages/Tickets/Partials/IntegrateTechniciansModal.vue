<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    ticket: { type: Object, required: true },
    users: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const visible = ref(props.modelValue);
watch(() => props.modelValue, (v) => { visible.value = v; });
watch(visible, (v) => emit('update:modelValue', v));

// Local users (only those with technician relationship)
const localUsers = computed(() => props.users.filter(u => u.technician));

const isIntegrating = ref(false);

const integrateForm = reactive({
    technicians: [],
    assistant_technicians: [],
});

const integrateEncargados = computed(() => {
    return localUsers.value.filter(u => u.technician?.level === 'Encargado');
});

const integrateAssistants = computed(() => {
    return localUsers.value.filter(u => u.technician?.level === 'Auxiliar/Ayudante');
});

// Init form when opening
watch(visible, (isOpen) => {
    if (isOpen) {
        integrateForm.technicians = [...(props.ticket.technicians || []).map(Number)];
        integrateForm.assistant_technicians = [...(props.ticket.assistant_technicians || []).map(Number)];
    }
});

const getTechnicianLabel = (user) => {
    let label = user.name;
    if (user.technician) {
        label += user.technician.is_internal ? ' (Interno)' : ' (Externo)';
        if (user.technician.state) {
            label += ` — ${user.technician.state}`;
        }
    }
    return label;
};

const submitIntegrate = () => {
    isIntegrating.value = true;
    router.put(route('tickets.update-technicians', props.ticket.id), {
        technicians: integrateForm.technicians,
        assistant_technicians: integrateForm.assistant_technicians,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            visible.value = false;
            ElMessage.success('Técnicos integrados correctamente.');
            router.reload({ only: ['ticket'] });
            emit('saved');
        },
        onError: () => {
            ElMessage.error('Error al integrar técnicos.');
        },
        onFinish: () => {
            isIntegrating.value = false;
        },
    });
};
</script>

<template>
    <el-dialog
        v-model="visible"
        title="Gestión de técnicos al ticket"
        width="500px"
        destroy-on-close
    >
        <p class="text-sm text-gray-500 mb-4">
            Selecciona los técnicos que participarán en este ticket. Puedes agregar tanto encargados como auxiliares.
        </p>
        <el-form label-position="top">
            <el-form-item label="Técnicos encargados">
                <el-select
                    v-model="integrateForm.technicians"
                    multiple
                    placeholder="Seleccionar encargados..."
                    class="w-full"
                    filterable
                    collapse-tags
                    collapse-tags-tooltip
                >
                    <el-option
                        v-for="tech in integrateEncargados"
                        :key="tech.id"
                        :label="getTechnicianLabel(tech)"
                        :value="tech.id"
                    />
                </el-select>
            </el-form-item>

            <el-form-item label="Técnicos auxiliares / ayudantes">
                <el-select
                    v-model="integrateForm.assistant_technicians"
                    multiple
                    placeholder="Seleccionar auxiliares..."
                    class="w-full"
                    filterable
                    collapse-tags
                    collapse-tags-tooltip
                >
                    <el-option
                        v-for="tech in integrateAssistants"
                        :key="tech.id"
                        :label="getTechnicianLabel(tech)"
                        :value="tech.id"
                    />
                </el-select>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="visible = false">Cancelar</el-button>
            <el-button type="primary" color="#f26c17" @click="submitIntegrate" :loading="isIntegrating">
                Guardar cambios
            </el-button>
        </template>
    </el-dialog>
</template>
