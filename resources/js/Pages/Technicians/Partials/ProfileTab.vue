<script setup>
import { 
    MapLocation, 
    Document, 
    Money 
} from '@element-plus/icons-vue';

defineProps({
    technician: Object
});
</script>

<template>
    <div class="py-4 grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Columna Izquierda -->
        <div class="space-y-6">
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <el-icon class="text-primary"><MapLocation /></el-icon> Zona operativa
                </h3>
                <el-descriptions :column="1" border>
                    <el-descriptions-item label="Dirección base">
                        {{ technician.colony ? `${technician.colony}, ` : '' }}
                        {{ technician.zip_code ? `C.P. ${technician.zip_code}` : '' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="Radio de cobertura">
                        {{ technician.coverage_radius_km }} Kilómetros
                    </el-descriptions-item>
                    <el-descriptions-item label="Teléfono de emergencia">
                        {{ technician.secondary_phone || 'No registrado' }}
                    </el-descriptions-item>
                </el-descriptions>
            </div>

            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <el-icon class="text-primary"><Document /></el-icon> Notas internas
                </h3>
                <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg text-sm text-gray-600 dark:text-gray-300 border border-yellow-100 dark:border-yellow-800/50">
                    {{ technician.internal_notes || 'Sin observaciones registradas.' }}
                </div>
            </div>
        </div>

        <!-- Columna Derecha -->
        <div class="space-y-6">
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <el-icon class="text-primary"><Money /></el-icon> Datos administrativos
                </h3>
                <el-descriptions :column="1" border>
                    <el-descriptions-item label="Razón social">
                        {{ technician.legal_name || '-' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="RFC">
                        <span class="font-mono">{{ technician.rfc || '-' }}</span>
                    </el-descriptions-item>
                    <el-descriptions-item label="Banco">
                        {{ technician.bank_name || '-' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="Cuenta / Tarjeta">
                        {{ technician.bank_account || '-' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="CLABE">
                        <span class="font-mono tracking-wider">{{ technician.clabe || '-' }}</span>
                    </el-descriptions-item>
                </el-descriptions>
            </div>
        </div>
    </div>
</template>