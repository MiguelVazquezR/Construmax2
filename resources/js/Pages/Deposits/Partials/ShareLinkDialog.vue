<script setup>
import { ref, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { CopyDocument } from '@element-plus/icons-vue'
import axios from 'axios'

const props = defineProps({
  modelValue: Boolean,
  target: Object,
})

const emit = defineEmits(['update:modelValue'])

const dialogVisible = ref(props.modelValue)
watch(() => props.modelValue, (v) => { dialogVisible.value = v })
watch(dialogVisible, (v) => emit('update:modelValue', v))

const generatedUrl = ref('')
const loading = ref(false)

const title = computed(() => {
  if (props.target?.type === 'deposit') {
    return `Compartir depósito #${props.target.deposit.id}`
  }
  return `Compartir depósitos del ${props.target?.date}`
})

async function generateLink() {
  loading.value = true
  try {
    if (props.target?.type === 'deposit') {
      const { data } = await axios.get(route('deposits.public-link', props.target.deposit.id))
      generatedUrl.value = data.url
    } else {
      const { data } = await axios.get(route('deposits.day-link', props.target.date))
      generatedUrl.value = data.url
    }
  } catch {
    ElMessage.error('No se pudo generar el enlace.')
  } finally {
    loading.value = false
  }
}

async function copyToClipboard() {
  try {
    await navigator.clipboard.writeText(generatedUrl.value)
    ElMessage.success('Enlace copiado al portapapeles.')
  } catch {
    ElMessage.error('No se pudo copiar el enlace.')
  }
}

// Auto-generate on open
watch(() => props.target, () => {
  if (props.target) generateLink()
}, { immediate: true })
</script>

<template>
  <el-dialog v-model="dialogVisible" :title="title" width="520px" destroy-on-close>
    <div v-loading="loading">
      <p class="text-sm text-gray-500 mb-3">
        Comparte este enlace con la persona encargada de realizar el depósito. No requiere iniciar sesión.
      </p>
      <el-input
        v-model="generatedUrl"
        readonly
        class="mb-3"
      >
        <template #append>
          <el-button :icon="CopyDocument" @click="copyToClipboard">Copiar</el-button>
        </template>
      </el-input>
    </div>
  </el-dialog>
</template>
