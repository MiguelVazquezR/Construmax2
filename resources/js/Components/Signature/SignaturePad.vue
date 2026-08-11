<template>
  <div class="signature-pad-wrapper">
    <div
      class="signature-canvas-container border-2 border-dashed rounded-lg relative bg-white dark:bg-gray-800"
      :class="[
        disabled ? 'border-gray-200 dark:border-gray-600 cursor-not-allowed' : 'border-gray-300 dark:border-gray-500 cursor-crosshair',
        hasSignature ? 'border-green-400 dark:border-green-500' : ''
      ]"
      @mousedown="startDrawing"
      @mousemove="draw"
      @mouseup="stopDrawing"
      @mouseleave="stopDrawing"
      @touchstart.prevent="startDrawingTouch"
      @touchmove.prevent="drawTouch"
      @touchend="stopDrawing"
    >
      <canvas
        ref="canvasRef"
        class="w-full h-full block"
        :style="{ touchAction: 'none' }"
      ></canvas>

      <!-- Placeholder text when empty -->
      <div
        v-if="!hasSignature && !isDrawing"
        class="absolute inset-0 flex items-center justify-center pointer-events-none text-gray-400 dark:text-gray-500 text-sm select-none"
      >
        Firma aqui
      </div>
    </div>

    <div class="flex items-center justify-between mt-2">
      <span class="text-xs text-gray-400 dark:text-gray-500">
        {{ hasSignature ? 'Firma capturada' : 'Usa el mouse o el dedo para firmar' }}
      </span>
      <el-button
        v-if="hasSignature && !disabled"
        size="small"
        type="danger"
        text
        @click="clearCanvas"
      >
        Limpiar
      </el-button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  width: {
    type: Number,
    default: 600,
  },
  height: {
    type: Number,
    default: 200,
  },
});

const emit = defineEmits(['update:modelValue']);

const canvasRef = ref(null);
const isDrawing = ref(false);
const hasSignature = ref(false);

let ctx = null;

function initCanvas() {
  if (!canvasRef.value) return;
  const canvas = canvasRef.value;
  canvas.width = props.width;
  canvas.height = props.height;

  ctx = canvas.getContext('2d');
  ctx.strokeStyle = '#1a1a1a';
  ctx.lineWidth = 2;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';

  // If there's an existing value (base64), draw it
  if (props.modelValue) {
    loadSignature(props.modelValue);
  }
}

function loadSignature(dataUrl) {
  if (!canvasRef.value || !ctx) return;
  const img = new Image();
  img.onload = () => {
    ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height);
    ctx.drawImage(img, 0, 0);
    hasSignature.value = true;
  };
  img.src = dataUrl;
}

function getPosition(e) {
  const rect = canvasRef.value.getBoundingClientRect();
  const scaleX = canvasRef.value.width / rect.width;
  const scaleY = canvasRef.value.height / rect.height;
  return {
    x: (e.clientX - rect.left) * scaleX,
    y: (e.clientY - rect.top) * scaleY,
  };
}

function getTouchPosition(e) {
  const touch = e.touches[0];
  const rect = canvasRef.value.getBoundingClientRect();
  const scaleX = canvasRef.value.width / rect.width;
  const scaleY = canvasRef.value.height / rect.height;
  return {
    x: (touch.clientX - rect.left) * scaleX,
    y: (touch.clientY - rect.top) * scaleY,
  };
}

function startDrawing(e) {
  if (props.disabled) return;
  isDrawing.value = true;
  const pos = getPosition(e);
  ctx.beginPath();
  ctx.moveTo(pos.x, pos.y);
}

function draw(e) {
  if (!isDrawing.value || props.disabled) return;
  const pos = getPosition(e);
  ctx.lineTo(pos.x, pos.y);
  ctx.stroke();
  hasSignature.value = true;
}

function startDrawingTouch(e) {
  if (props.disabled) return;
  isDrawing.value = true;
  const pos = getTouchPosition(e);
  ctx.beginPath();
  ctx.moveTo(pos.x, pos.y);
}

function drawTouch(e) {
  if (!isDrawing.value || props.disabled) return;
  const pos = getTouchPosition(e);
  ctx.lineTo(pos.x, pos.y);
  ctx.stroke();
  hasSignature.value = true;
}

function stopDrawing() {
  if (isDrawing.value) {
    isDrawing.value = false;
    ctx.closePath();
    emitSignature();
  }
}

function clearCanvas() {
  if (!ctx || !canvasRef.value) return;
  ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height);
  hasSignature.value = false;
  emit('update:modelValue', '');
}

function emitSignature() {
  if (!canvasRef.value || !hasSignature.value) return;
  const dataUrl = canvasRef.value.toDataURL('image/png');
  emit('update:modelValue', dataUrl);
}

function handleResize() {
  // Re-init on resize to maintain quality
  const currentData = canvasRef.value?.toDataURL('image/png');
  initCanvas();
  if (currentData && hasSignature.value) {
    loadSignature(currentData);
  }
}

onMounted(() => {
  nextTick(() => initCanvas());
  window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
  window.removeEventListener('resize', handleResize);
});

watch(() => props.modelValue, (val) => {
  if (val && ctx && canvasRef.value) {
    loadSignature(val);
  } else if (!val && ctx) {
    clearCanvas();
  }
});
</script>
