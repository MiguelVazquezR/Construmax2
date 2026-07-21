<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { ElMessage } from 'element-plus'
import { UploadFilled } from '@element-plus/icons-vue'

const props = defineProps({
  modelValue: Boolean,
  deposit: Object,
  completeUrl: String,
})

const emit = defineEmits(['update:modelValue', 'completed'])

const dialogVisible = ref(props.modelValue)
watch(() => props.modelValue, (v) => { dialogVisible.value = v })
watch(dialogVisible, (v) => emit('update:modelValue', v))

const form = useForm({
  commission_amount: '',
  voucher: null,
})

function submit() {
  form.post(props.completeUrl, {
    onSuccess: () => {
      ElMessage.success('Depósito marcado como realizado. El pago se registró automáticamente.')
      emit('completed')
    },
  })
}
</script>

<template>
  <el-dialog
    v-model="dialogVisible"
    title="Marcar depósito como realizado"
    width="95%"
    destroy-on-close
    :close-on-click-modal="false"
    class="complete-deposit-dialog"
  >
    <el-form :model="form" label-position="top">
      <el-form-item label="Comisión (opcional)">
        <el-input-number
          v-model="form.commission_amount"
          :min="0"
          :precision="2"
          :controls="false"
          class="w-full"
          placeholder="0.00"
        />
      </el-form-item>

      <el-form-item label="Comprobante (opcional)">
        <el-upload
          :auto-upload="false"
          :limit="1"
          accept=".jpg,.jpeg,.png,.pdf"
          drag
          @change="(file) => form.voucher = file.raw"
        >
          <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
          <div class="el-upload__text">
            Arrastra un archivo aquí o <em>haz clic para subir</em>
          </div>
          <template #tip>
            <div class="el-upload__tip">
              JPG, PNG o PDF. Máx. 10 MB.
            </div>
          </template>
        </el-upload>
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="dialogVisible = false">Cancelar</el-button>
      <el-button
        type="primary"
        :loading="form.processing"
        @click="submit"
      >
        Confirmar depósito
      </el-button>
    </template>
  </el-dialog>
</template>

<style scoped>
@media (max-width: 640px) {
  .complete-deposit-dialog :deep(.el-dialog) {
    width: 90% !important;
    max-width: 90vw;
    margin: 0 auto;
  }

  .complete-deposit-dialog :deep(.el-dialog__body) {
    padding: 1rem;
    overflow-x: hidden;
    word-break: break-word;
  }

  .complete-deposit-dialog :deep(.el-upload-dragger) {
    width: 100%;
    max-width: 100%;
    padding: 1rem;
  }

  .complete-deposit-dialog :deep(.el-upload__text) {
    font-size: 0.75rem;
  }
}
</style>
