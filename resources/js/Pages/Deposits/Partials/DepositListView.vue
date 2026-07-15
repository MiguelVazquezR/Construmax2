<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Edit, Delete, Check, Share } from '@element-plus/icons-vue'

const props = defineProps({
  deposits: Object,
  filters: Object,
  can: Object,
})

const emit = defineEmits(['edit', 'approve', 'delete', 'share', 'create'])

function statusColor(status) {
  return { pending: 'warning', approved: 'success', completed: 'info' }[status] || 'info'
}

function statusLabel(status) {
  return { pending: 'Pendiente', approved: 'Aprobado', completed: 'Completado' }[status] || status
}

function shiftLabel(shift) {
  return shift === 'matutino' ? 'Matutino' : 'Vespertino'
}

function updateFilter(key, value) {
  const params = { ...props.filters }
  if (value) {
    params[key] = value
  } else {
    delete params[key]
  }
  router.get(route('deposits.index'), params, { preserveState: true, replace: true })
}
</script>

<template>
  <div class="p-4">
    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-4">
      <el-input
        :model-value="filters.search ?? ''"
        placeholder="Buscar por nombre de técnico"
        class="w-60"
        clearable
        @input="updateFilter('search', $event)"
      />
      <el-select
        :model-value="filters.status ?? ''"
        placeholder="Todos los estados"
        class="w-40"
        clearable
        @change="updateFilter('status', $event)"
      >
        <el-option label="Pendiente" value="pending" />
        <el-option label="Aprobado" value="approved" />
        <el-option label="Completado" value="completed" />
      </el-select>
      <el-select
        :model-value="filters.shift ?? ''"
        placeholder="Todos los turnos"
        class="w-40"
        clearable
        @change="updateFilter('shift', $event)"
      >
        <el-option label="Matutino" value="matutino" />
        <el-option label="Vespertino" value="vespertino" />
      </el-select>
      <el-button v-if="can.create" type="primary" @click="$emit('create')">
        Nuevo depósito
      </el-button>
    </div>

    <!-- Table -->
    <el-table :data="deposits.data" stripe class="w-full">
      <el-table-column label="Técnico" min-width="160">
        <template #default="{ row }">
          <div class="flex items-center gap-2">
            <span class="font-medium">{{ row.technician?.user?.name ?? 'N/A' }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="Ticket" min-width="140">
        <template #default="{ row }">
          {{ row.ticket?.folio ?? 'N/A' }}
        </template>
      </el-table-column>
      <el-table-column label="Tipo" min-width="110">
        <template #default="{ row }">
          {{ row.deposit_type?.name ?? 'N/A' }}
        </template>
      </el-table-column>
      <el-table-column label="Monto" width="120" align="right">
        <template #default="{ row }">
          <span class="font-mono">${{ Number(row.amount).toFixed(2) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="Turno" width="100">
        <template #default="{ row }">
          {{ shiftLabel(row.shift) }}
        </template>
      </el-table-column>
      <el-table-column label="Fecha" width="120">
        <template #default="{ row }">
          {{ row.scheduled_date }}
        </template>
      </el-table-column>
      <el-table-column label="Estado" width="110">
        <template #default="{ row }">
          <el-tag :type="statusColor(row.status)" size="small">
            {{ statusLabel(row.status) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Aprobado por" min-width="140">
        <template #default="{ row }">
          {{ row.approved_by?.name ?? '—' }}
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="200" fixed="right">
        <template #default="{ row }">
          <div class="flex gap-1">
            <el-button
              v-if="row.status === 'pending' && can.approve"
              type="success"
              size="small"
              :icon="Check"
              @click="$emit('approve', row)"
            >
              Aprobar
            </el-button>
            <el-button
              v-if="row.status !== 'completed' && can.edit"
              size="small"
              :icon="Edit"
              @click="$emit('edit', row)"
            />
            <el-button
              v-if="row.status === 'approved' || row.status === 'completed'"
              size="small"
              :icon="Share"
              @click="$emit('share', row)"
            />
            <el-button
              v-if="row.status !== 'completed' && can.delete"
              type="danger"
              size="small"
              :icon="Delete"
              @click="$emit('delete', row)"
            />
          </div>
        </template>
      </el-table-column>
    </el-table>

    <!-- Pagination -->
    <div class="mt-4 flex justify-center" v-if="deposits.links">
      <el-pagination
        :current-page="deposits.current_page"
        :total="deposits.total"
        :page-size="deposits.per_page"
        layout="prev, pager, next"
        background
        @current-change="(page) => router.get(route('deposits.index'), { ...filters, page }, { preserveState: true })"
      />
    </div>
  </div>
</template>
