<script setup>
import { onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Printer, Back } from '@element-plus/icons-vue';

const props = defineProps({
    period: Object,
    payslips: Array,
    appName: String,
});

const money = (value) =>
    `$${Number(value || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const hours = (minutes) => {
    const total = Number(minutes || 0);
    return `${Math.floor(total / 60)}:${String(Math.round(total % 60)).padStart(2, '0')}`;
};

const earningsOf = (payslip) => (payslip.lines || []).filter((line) => line.type === 'earning');
const deductionsOf = (payslip) => (payslip.lines || []).filter((line) => line.type === 'deduction');

const print = () => window.print();

const goBack = () => window.history.back();

onMounted(() => {
    setTimeout(() => window.print(), 600);
});
</script>

<template>
    <Head title="Recibos de nómina" />

    <div class="min-h-screen bg-gray-100 print:bg-white">
        <!-- Toolbar (hidden when printing) -->
        <div class="print:hidden sticky top-0 z-10 bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
            <div>
                <h1 class="font-semibold text-gray-800">Recibos de nómina</h1>
                <p class="text-sm text-gray-500">Periodo {{ period.label }} · {{ payslips.length }} recibo(s) · 4 a 5 por hoja</p>
            </div>
            <div class="flex gap-2">
                <el-button :icon="Back" @click="goBack">Volver</el-button>
                <el-button type="primary" color="#f26c17" :icon="Printer" @click="print">
                    Imprimir / Guardar PDF
                </el-button>
            </div>
        </div>

        <!-- Sheets -->
        <div class="payslips-container max-w-[210mm] mx-auto py-6 print:py-0 print:max-w-none">
            <div v-for="payslip in payslips" :key="payslip.id" class="payslip">
                <!-- Receipt header -->
                <div class="slip-header">
                    <div>
                        <p class="company">{{ appName }}</p>
                        <p class="slip-title">Recibo de nómina</p>
                    </div>
                    <div class="text-right">
                        <p class="period">Periodo: {{ period.label }}</p>
                        <p class="folio">Recibo #{{ payslip.id }}</p>
                    </div>
                </div>

                <!-- Collaborator -->
                <div class="slip-meta">
                    <span><strong>Colaborador:</strong> {{ payslip.user_name }}</span>
                    <span><strong>Número:</strong> {{ payslip.employee_number || '—' }}</span>
                    <span><strong>Departamento:</strong> {{ payslip.department || '—' }}</span>
                    <span><strong>Sueldo diario:</strong> {{ money(payslip.daily_salary) }}</span>
                </div>

                <!-- Concepts -->
                <div class="slip-body">
                    <div class="concepts">
                        <p class="concepts-title">Percepciones</p>
                        <div v-for="line in earningsOf(payslip)" :key="line.concept" class="concept-row">
                            <span>{{ line.concept }}<template v-if="line.quantity"> ({{ line.quantity }})</template></span>
                            <span>{{ money(line.amount) }}</span>
                        </div>
                        <div v-if="earningsOf(payslip).length === 0" class="concept-row text-muted">
                            <span>Sin percepciones</span><span>—</span>
                        </div>
                    </div>

                    <div class="concepts">
                        <p class="concepts-title">Deducciones</p>
                        <div v-for="line in deductionsOf(payslip)" :key="line.concept" class="concept-row">
                            <span>{{ line.concept }}</span>
                            <span>{{ money(line.amount) }}</span>
                        </div>
                        <div v-if="deductionsOf(payslip).length === 0" class="concept-row text-muted">
                            <span>Sin deducciones</span><span>—</span>
                        </div>
                    </div>
                </div>

                <!-- Totals -->
                <div class="slip-totals">
                    <span class="totals-item">Días pagados: <strong>{{ payslip.days_paid }}</strong></span>
                    <span class="totals-item">No pagados: <strong>{{ payslip.unpaid_days }}</strong></span>
                    <span class="totals-item">Retardos: <strong>{{ payslip.late_minutes }} min</strong></span>
                    <span class="totals-item">Extra: <strong>{{ hours(payslip.overtime_minutes) }} h</strong></span>
                    <span class="totals-item">Vacaciones: <strong>{{ payslip.vacation_days }}</strong></span>
                    <span class="totals-item">Total percepciones: <strong>{{ money(payslip.total_gross) }}</strong></span>
                    <span class="totals-item">Deducciones: <strong>{{ money(payslip.total_deductions) }}</strong></span>
                    <span class="totals-item net">Neto a pagar: <strong>{{ money(payslip.total_net) }}</strong></span>
                </div>

                <div class="slip-signature">
                    <div class="signature-line">
                        <span>Recibí de conformidad</span>
                    </div>
                    <span class="signature-name">{{ payslip.user_name }}</span>
                </div>
            </div>

            <div v-if="payslips.length === 0" class="print:hidden text-center py-20 text-gray-500">
                Este periodo no tiene recibos generados.
            </div>
        </div>
    </div>
</template>

<style scoped>
.payslips-container {
    display: block;
}

.payslip {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 6mm 7mm;
    margin-bottom: 6mm;
    height: 52mm;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    font-size: 10px;
    line-height: 1.35;
    color: #111827;
}

.slip-header {
    display: flex;
    justify-content: space-between;
    border-bottom: 1px solid #d1d5db;
    padding-bottom: 2mm;
    margin-bottom: 2mm;
}

.company {
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.slip-title {
    font-size: 10px;
    color: #6b7280;
}

.period,
.folio {
    font-size: 10px;
}

.slip-meta {
    display: flex;
    gap: 5mm;
    flex-wrap: wrap;
    margin-bottom: 2mm;
}

.slip-body {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 5mm;
    flex: 1;
}

.concepts-title {
    font-weight: 700;
    text-transform: uppercase;
    font-size: 9px;
    color: #6b7280;
    margin-bottom: 1mm;
}

.concept-row {
    display: flex;
    justify-content: space-between;
    gap: 3mm;
}

.text-muted {
    color: #9ca3af;
}

.slip-totals {
    display: flex;
    flex-wrap: wrap;
    gap: 4mm;
    border-top: 1px solid #d1d5db;
    padding-top: 1.5mm;
    margin-top: 1.5mm;
}

.totals-item {
    white-space: nowrap;
}

.totals-item.net {
    margin-left: auto;
}

.slip-signature {
    display: flex;
    justify-content: flex-end;
    align-items: flex-end;
    gap: 4mm;
    margin-top: 2mm;
}

.signature-line {
    border-bottom: 1px solid #9ca3af;
    width: 45mm;
    text-align: center;
    font-size: 9px;
    color: #6b7280;
    padding-bottom: 0.5mm;
}

.signature-name {
    font-size: 9px;
    color: #6b7280;
}

@media print {
    .payslips-container {
        max-width: none;
        padding: 0;
        margin: 0;
    }

    .payslip {
        border: none;
        border-radius: 0;
        box-shadow: none;
        margin: 0;
        height: 54mm;
        page-break-inside: avoid;
    }
}

@page {
    size: A4;
    margin: 8mm;
}
</style>
