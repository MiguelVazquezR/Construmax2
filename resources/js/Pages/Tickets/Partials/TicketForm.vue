<script setup>
import { computed } from 'vue';

const props = defineProps({
    form: {
        type: Object,
        required: true
    },
    users: {
        type: Array,
        required: true
    },
    isEdit: {
        type: Boolean,
        default: false
    }
});

const statuses = [
    'Programado', 
    'En proceso', 
    'En espera', 
    'Revisión', 
    'Completado', 
    'Cancelado'
];

// Lógica de agrupación de usuarios
const groupedUsers = computed(() => {
    return [
        {
            label: 'Usuarios / Supervisores',
            // Los empleados SÍ deben tener su acceso activo (is_active = true)
            options: props.users.filter(u => !u.technician && u.is_active)
        },
        {
            label: 'Técnicos Internos',
            // Para técnicos, ignoramos si su usuario de plataforma está inactivo
            options: props.users.filter(u => u.technician && u.technician.is_internal)
        },
        {
            label: 'Técnicos Externos',
            // Para técnicos, ignoramos si su usuario de plataforma está inactivo
            options: props.users.filter(u => u.technician && !u.technician.is_internal)
        }
    ].filter(group => group.options.length > 0); // Ocultar grupos vacíos
});
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- SELECCIÓN DE RESPONSABLE AGRUPADO -->
        <div class="md:col-span-2">
            <div class="flex justify-between items-center mb-1">
                <label class="text-sm font-bold text-gray-700 dark:text-gray-300">Supervisor / encargado de obra</label>
            </div>
            <p class="text-xs text-gray-500 mb-2">
                * Este usuario es el responsable general. Los técnicos o proveedores que ejecutarán tareas específicas se asignan en la vista de detalles del ticket.
            </p>
            <el-form-item prop="user_id" :error="form.errors.user_id">
                <el-select v-model="form.user_id" placeholder="Seleccionar encargado..." class="w-full" filterable clearable>
                    <el-option-group 
                        v-for="group in groupedUsers" 
                        :key="group.label" 
                        :label="group.label"
                    >
                        <el-option 
                            v-for="user in group.options" 
                            :key="user.id" 
                            :label="user.name" 
                            :value="user.id" 
                        />
                    </el-option-group>
                </el-select>
            </el-form-item>
        </div>

        <!-- ESTADO ACTUAL (SOLO VISIBLE EN EDICIÓN) -->
        <el-form-item v-if="isEdit" label="Estado actual" prop="status" :error="form.errors.status">
            <el-select v-model="form.status" class="w-full">
                <el-option v-for="item in statuses" :key="item" :label="item" :value="item" />
            </el-select>
        </el-form-item>

        <!-- PRIORIDAD -->
        <el-form-item label="Prioridad" prop="priority" :error="form.errors.priority" :class="{'md:col-span-2': !isEdit}">
            <el-select v-model="form.priority" class="w-full">
                <el-option label="Baja" value="Baja" />
                <el-option label="Media" value="Media" />
                <el-option label="Alta" value="Alta" />
                <el-option label="Urgente" value="Urgente" />
            </el-select>
        </el-form-item>

        <!-- FECHAS -->
        <el-form-item label="Inicio Programado" prop="scheduled_start" :error="form.errors.scheduled_start">
            <el-date-picker 
                v-model="form.scheduled_start" 
                type="date" 
                class="!w-full" 
                placeholder="Seleccionar fecha"
                format="DD/MM/YYYY"
                value-format="YYYY-MM-DD"
            />
        </el-form-item>

        <el-form-item label="Fin Estimado" prop="scheduled_end" :error="form.errors.scheduled_end">
            <el-date-picker 
                v-model="form.scheduled_end" 
                type="date" 
                class="!w-full" 
                placeholder="Seleccionar fecha"
                format="DD/MM/YYYY"
                value-format="YYYY-MM-DD"
            />
        </el-form-item>
    </div>

    <!-- INSTRUCCIONES ESPECIALES -->
    <el-form-item label="Instrucciones especiales" prop="instructions" :error="form.errors.instructions" class="mt-2">
        <el-input 
            v-model="form.instructions" 
            type="textarea" 
            :rows="isEdit ? 5 : 4" 
            placeholder="Detalles operativos, acceso, herramientas necesarias..." 
        />
    </el-form-item>
</template>