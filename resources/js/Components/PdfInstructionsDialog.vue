<script setup>
import { ElDialog, ElButton } from 'element-plus';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'print']);

const handlePrint = () => {
    emit('update:modelValue', false);
    // Small delay so the dialog closes before print dialog opens
    setTimeout(() => {
        emit('print');
    }, 400);
};
</script>

<template>
    <el-dialog
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
        title="Cómo guardar como PDF"
        width="500px"
        class="print:hidden"
    >
        <div class="space-y-3 text-sm text-gray-700">
            <p>Se abrirá la ventana de impresión del navegador. Sigue estos pasos:</p>
            <ol class="list-decimal pl-5 space-y-2">
                <li>
                    En <strong>Destino</strong>, selecciona <strong>"Guardar como PDF"</strong>.
                    <img src="/images/tuto_1.jpg" alt="Instrucciones para guardar como PDF" class="mt-2 border rounded">
                </li>
                <li>Ajusta el diseño a <strong>Vertical</strong> si es necesario.</li>
                <li>Haz clic en <strong>"Guardar"</strong> y elige la ubicación en tu equipo.</li>
            </ol>
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 text-xs text-orange-800">
                <strong>Tip:</strong> Si no ves "Guardar como PDF" en la lista de destinos, haz clic en "Más destinos..." para buscarlo.
            </div>
        </div>
        <template #footer>
            <el-button @click="$emit('update:modelValue', false)">Entendido</el-button>
            <el-button type="primary" @click="handlePrint">Abrir ventana de impresión</el-button>
        </template>
    </el-dialog>
</template>
