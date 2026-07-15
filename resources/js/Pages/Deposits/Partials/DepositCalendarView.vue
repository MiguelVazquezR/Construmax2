<script setup>
import { ref, computed } from 'vue'
import { ElMessage } from 'element-plus'

const props = defineProps({
  events: Object,    // { '2026-07-14': [...deposits], ... }
  can: Object,
})

const emit = defineEmits(['create', 'edit', 'approve', 'share-day'])

const selectedDate = ref(new Date())

function getEventsForDate(date) {
  const key = formatDateKey(date)
  return props.events[key] || []
}

function formatDateKey(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function shiftColor(shift) {
  return shift === 'matutino' ? '#409EFF' : '#E6A23C'
}

function statusOpacity(status) {
  return status === 'pending' ? 1 : status === 'approved' ? 0.8 : 0.5
}

function handleDateClick(data) {
  emit('create-with-date', formatDateKey(data.date))
}

function handleEventClick(deposit) {
  if (deposit.status !== 'completed' && props.can.edit) {
    emit('edit', deposit)
  }
}
</script>

<template>
  <div class="p-4">
    <!-- Legend -->
    <div class="flex flex-wrap gap-4 mb-4 text-xs">
      <span class="flex items-center gap-1">
        <span class="w-3 h-3 rounded" style="background:#409EFF"></span> Matutino
      </span>
      <span class="flex items-center gap-1">
        <span class="w-3 h-3 rounded" style="background:#E6A23C"></span> Vespertino
      </span>
      <span class="flex items-center gap-1 text-gray-400">
        <span class="w-3 h-3 rounded border border-gray-300"></span> Completados
      </span>
      <el-button
        size="small"
        type="primary"
        plain
        @click="emit('share-day', formatDateKey(selectedDate))"
      >
        Compartir enlace del día
      </el-button>
    </div>

    <el-calendar v-model="selectedDate">
      <template #date-cell="{ data }">
        <div
          class="h-full w-full flex flex-col gap-1 overflow-hidden"
          @click="handleDateClick(data)"
        >
          <span
            class="text-sm font-bold"
            :class="data.isSelected ? 'text-primary' : 'text-gray-700 dark:text-gray-300'"
          >
            {{ data.date.getDate() }}
          </span>

          <div class="flex flex-col gap-0.5 overflow-y-auto pr-1">
            <div
              v-for="deposit in getEventsForDate(data.date)"
              :key="'d-' + deposit.id"
              class="text-[10px] px-1 py-0.5 rounded border truncate cursor-pointer hover:opacity-80 transition flex items-center gap-1"
              :style="{
                backgroundColor: shiftColor(deposit.shift) + '20',
                borderColor: shiftColor(deposit.shift),
                color: shiftColor(deposit.shift),
                opacity: statusOpacity(deposit.status),
              }"
              @click="handleEventClick(deposit)"
            >
              <span class="font-bold">${{ Number(deposit.amount).toFixed(0) }}</span>
              {{ deposit.technician?.user?.name?.split(' ')[0] ?? 'N/A' }}
            </div>
          </div>
        </div>
      </template>
    </el-calendar>
  </div>
</template>
