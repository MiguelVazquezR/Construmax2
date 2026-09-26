<script setup>
import { ArrowDown, CirclePlus, Flag, Minus, Operation } from '@element-plus/icons-vue';

const emit = defineEmits(['select']);

const items = [
    { command: 'taken', label: 'Registrar días tomados', icon: Minus },
    { command: 'grant', label: 'Registrar días ganados', icon: CirclePlus },
    { command: 'adjustment', label: 'Hacer un ajuste manual', icon: Operation },
    { command: 'initial', label: 'Saldo inicial', icon: Flag, divided: true },
];
</script>

<template>
    <el-dropdown trigger="click" @command="(command) => emit('select', command)">
        <el-button size="small" type="primary" color="#f26c17">
            Ajustar saldo inicial <el-icon class="ml-1"><ArrowDown /></el-icon>
        </el-button>
        <template #dropdown>
            <el-dropdown-menu>
                <el-dropdown-item
                    v-for="item in items"
                    :key="item.command"
                    :command="item.command"
                    :icon="item.icon"
                    :divided="item.divided"
                >
                    {{ item.label }}
                </el-dropdown-item>
            </el-dropdown-menu>
        </template>
    </el-dropdown>
</template>
