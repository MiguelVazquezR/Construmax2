<script setup>
import { onBeforeUnmount } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Printer, Back } from '@element-plus/icons-vue';

const props = defineProps({
    period: Object,
    payslips: Array,
    isPreview: Boolean,
    appName: String,
});

// The receipts are a light-only document: while the page is open the dark
// theme is removed and the user preference is restored when leaving.
const hadDarkTheme = document.documentElement.classList.contains('dark');
document.documentElement.classList.remove('dark');

onBeforeUnmount(() => {
    if (hadDarkTheme) {
        document.documentElement.classList.add('dark');
    }
});

const money = (value) =>
    `$${Number(value || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const hours = (minutes) => {
    const total = Number(minutes || 0);
    return `${Math.floor(total / 60)}:${String(Math.round(total % 60)).padStart(2, '0')}`;
};

const days = (value) => Number(value || 0).toLocaleString('es-MX', { maximumFractionDigits: 2 });

const earningsOf = (payslip) => (payslip.lines || []).filter((line) => line.type === 'earning');
const deductionsOf = (payslip) => (payslip.lines || []).filter((line) => line.type === 'deduction');

const sumOf = (lines) => (lines || []).reduce((total, line) => total + Number(line.amount || 0), 0);

const earningsTotal = (payslip) => sumOf(earningsOf(payslip));
const deductionsTotal = (payslip) => sumOf(deductionsOf(payslip));

const print = () => window.print();

// The page usually lives in its own tab opened from the period screen: close
// it when possible, otherwise walk back or return to the period detail.
const goBack = () => {
    if (window.opener && ! window.opener.closed) {
        window.close();
        return;
    }

    if (window.history.length > 1) {
        window.history.back();
        return;
    }

    if (props.period?.id) {
        window.location.href = route('payroll.periods.show', props.period.id);
    }
};
</script>

<template>
    <Head title="Recibos de nómina" />

    <div class="payslips-page min-h-screen print:bg-white">
        <!-- Toolbar (hidden when printing) -->
        <div class="print:hidden sticky top-0 z-10 bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between gap-4">
            <div>
                <h1 class="font-semibold text-gray-800">Recibos de nómina</h1>
                <p class="text-sm text-gray-500">
                    Periodo {{ period.label }} · {{ payslips.length }} recibo(s)
                    <el-tag v-if="isPreview" type="warning" size="small" effect="plain" class="ml-2">
                        Pre-nómina (periodo abierto)
                    </el-tag>
                </p>
            </div>
            <div class="flex gap-2">
                <el-button :icon="Back" @click="goBack">Volver</el-button>
                <el-button type="primary" color="#f26c17" :icon="Printer" @click="print">
                    Imprimir / Guardar PDF
                </el-button>
            </div>
        </div>

        <!-- Receipts -->
        <div class="payslips-container">
            <div v-for="payslip in payslips" :key="payslip.id ?? payslip.user_id" class="payslip">
                <!-- Header -->
                <header class="slip-head">
                    <div>
                        <p class="company">{{ appName }}</p>
                        <p class="slip-title">
                            Recibo de nómina
                            <span v-if="isPreview" class="slip-preview">Pre-nómina</span>
                        </p>
                    </div>
                    <div class="slip-head-right">
                        <p class="folio">{{ payslip.id ? `Folio #${payslip.id}` : 'Sin folio (preliminar)' }}</p>
                        <p>Periodo: {{ period.label }}</p>
                    </div>
                </header>

                <!-- Collaborator -->
                <section class="slip-employee">
                    <div class="field">
                        <span class="field-label">Colaborador</span>
                        <span class="field-value">{{ payslip.user_name }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Número</span>
                        <span class="field-value">{{ payslip.employee_number || '—' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Puesto</span>
                        <span class="field-value">{{ payslip.position || '—' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Departamento</span>
                        <span class="field-value">{{ payslip.department || '—' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Sueldo diario</span>
                        <span class="field-value">{{ money(payslip.daily_salary) }}</span>
                    </div>
                </section>

                <!-- Concepts -->
                <section class="slip-concepts">
                    <div class="concept-block">
                        <p class="concepts-title">Percepciones</p>
                        <div v-for="line in earningsOf(payslip)" :key="line.concept" class="concept-row">
                            <span class="concept-name">
                                {{ line.concept }}<template v-if="line.quantity"> · {{ line.quantity }}</template>
                            </span>
                            <span class="concept-amount">{{ money(line.amount) }}</span>
                        </div>
                        <p v-if="earningsOf(payslip).length === 0" class="concept-row empty">Sin percepciones</p>
                        <div class="concept-subtotal">
                            <span>Subtotal</span>
                            <span>{{ money(earningsTotal(payslip)) }}</span>
                        </div>
                    </div>

                    <div class="concept-block">
                        <p class="concepts-title">Deducciones</p>
                        <div v-for="line in deductionsOf(payslip)" :key="line.concept" class="concept-row">
                            <span class="concept-name">{{ line.concept }}</span>
                            <span class="concept-amount">{{ money(line.amount) }}</span>
                        </div>
                        <p v-if="deductionsOf(payslip).length === 0" class="concept-row empty">Sin deducciones</p>
                        <div class="concept-subtotal">
                            <span>Subtotal</span>
                            <span>{{ money(deductionsTotal(payslip)) }}</span>
                        </div>
                    </div>
                </section>

                <!-- Attendance summary and totals -->
                <section class="slip-summary">
                    <div class="days-grid">
                        <div class="day-item">
                            <span class="day-label">Días pagados</span>
                            <span class="day-value">{{ days(payslip.days_paid) }}</span>
                        </div>
                        <div class="day-item">
                            <span class="day-label">Faltas</span>
                            <span class="day-value" :class="{ danger: payslip.unpaid_days > 0 }">{{ days(payslip.unpaid_days) }}</span>
                        </div>
                        <div class="day-item">
                            <span class="day-label">Retardos</span>
                            <span class="day-value">{{ payslip.late_minutes }} min</span>
                        </div>
                        <div class="day-item">
                            <span class="day-label">Tiempo extra</span>
                            <span class="day-value">{{ hours(payslip.overtime_minutes) }}</span>
                        </div>
                        <div class="day-item">
                            <span class="day-label">Vacaciones</span>
                            <span class="day-value">{{ days(payslip.vacation_days) }}</span>
                        </div>
                        <div class="day-item">
                            <span class="day-label">Incapacidad</span>
                            <span class="day-value">{{ days(payslip.incapacity_days) }}</span>
                        </div>
                    </div>

                    <div class="totals-box">
                        <div class="total-row">
                            <span>Total percepciones</span>
                            <span>{{ money(payslip.total_gross) }}</span>
                        </div>
                        <div class="total-row">
                            <span>Total deducciones</span>
                            <span>{{ payslip.total_deductions > 0 ? '-' : '' }}{{ money(payslip.total_deductions) }}</span>
                        </div>
                        <div class="total-net">
                            <span>Neto a pagar</span>
                            <strong>{{ money(payslip.total_net) }}</strong>
                        </div>
                    </div>
                </section>

                <!-- Footer -->
                <footer class="slip-foot">
                    <p v-if="isPreview" class="slip-note">
                        Documento preliminar de pre-nómina: los importes pueden cambiar hasta el cierre del periodo.
                    </p>
                    <div class="signature">
                        <div class="signature-line"></div>
                        <p class="signature-name">{{ payslip.user_name }}</p>
                        <p class="signature-caption">Recibí de conformidad</p>
                    </div>
                </footer>
            </div>

            <div v-if="payslips.length === 0" class="print:hidden text-center py-20 text-gray-500">
                {{ isPreview ? 'No hay colaboradores sujetos a nómina en este periodo.' : 'Este periodo no tiene recibos generados.' }}
            </div>
        </div>
    </div>
</template>

<style scoped>
.payslips-page {
    background: #f4f6f8;
    color-scheme: light;
}

.payslips-container {
    max-width: 210mm;
    margin: 0 auto;
    padding: 6mm 4mm;
}

/* The receipt grows with its content: no fixed height, nothing gets cut. */
.payslip {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 5mm 6mm;
    margin-bottom: 5mm;
    font-size: 10.5px;
    line-height: 1.4;
    color: #111827;
    break-inside: avoid;
    page-break-inside: avoid;
}

/* Header */

.slip-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 6mm;
    border-bottom: 1.5px solid #f26c17;
    padding-bottom: 2.5mm;
    margin-bottom: 3mm;
}

.company {
    font-weight: 800;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}

.slip-title {
    font-size: 10px;
    color: #6b7280;
    margin-top: 0.5mm;
}

.slip-preview {
    display: inline-block;
    margin-left: 2mm;
    padding: 0.2mm 1.5mm;
    border: 1px solid #f59e0b;
    border-radius: 2mm;
    color: #b45309;
    font-size: 8.5px;
    font-weight: 600;
}

.slip-head-right {
    text-align: right;
    color: #6b7280;
}

.folio {
    font-weight: 700;
    color: #111827;
}

/* Collaborator */

.slip-employee {
    display: grid;
    grid-template-columns: 2.2fr 1fr 1.4fr 1.6fr 1.2fr;
    gap: 4mm;
    padding-bottom: 3mm;
    margin-bottom: 3mm;
    border-bottom: 1px solid #e5e7eb;
}

.field {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.field-label {
    font-size: 8.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #9ca3af;
}

.field-value {
    font-weight: 600;
    word-break: break-word;
}

/* Concepts */

.slip-concepts {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8mm;
    margin-bottom: 3mm;
}

.concepts-title {
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6b7280;
    margin-bottom: 1.5mm;
}

.concept-row {
    display: flex;
    justify-content: space-between;
    gap: 4mm;
    padding: 0.4mm 0;
}

.concept-name {
    min-width: 0;
    word-break: break-word;
}

.concept-amount {
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.concept-row.empty {
    color: #9ca3af;
}

.concept-subtotal {
    display: flex;
    justify-content: space-between;
    gap: 4mm;
    margin-top: 1.5mm;
    padding-top: 1.5mm;
    border-top: 1px dashed #d1d5db;
    font-weight: 700;
}

/* Attendance summary and totals */

.slip-summary {
    display: grid;
    grid-template-columns: 1fr 64mm;
    gap: 6mm;
    align-items: start;
}

.days-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2mm 4mm;
}

.day-item {
    display: flex;
    flex-direction: column;
}

.day-label {
    font-size: 8.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #9ca3af;
}

.day-value {
    font-weight: 600;
}

.day-value.danger {
    color: #ef4444;
}

.totals-box {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 2.5mm 3mm;
}

.total-row {
    display: flex;
    justify-content: space-between;
    gap: 4mm;
    color: #4b5563;
}

.total-row span:last-child {
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.total-net {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 4mm;
    margin-top: 1.5mm;
    padding-top: 1.5mm;
    border-top: 1px solid #d1d5db;
    font-weight: 700;
}

.total-net strong {
    font-size: 14px;
    color: #f26c17;
    font-variant-numeric: tabular-nums;
}

/* Footer */

.slip-foot {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 6mm;
    margin-top: 4mm;
}

.slip-note {
    max-width: 80mm;
    font-size: 8.5px;
    color: #b45309;
}

.signature {
    margin-left: auto;
    text-align: center;
}

.signature-line {
    width: 55mm;
    height: 8mm;
    border-bottom: 1px solid #9ca3af;
}

.signature-name {
    font-weight: 600;
}

.signature-caption {
    font-size: 8.5px;
    color: #6b7280;
}

@media print {
    .payslips-page {
        background: #fff;
    }

    .payslips-container {
        max-width: none;
        padding: 0;
        margin: 0;
    }

    .payslip {
        border-radius: 0;
        margin: 0 0 4mm;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    .payslip:last-child {
        margin-bottom: 0;
    }
}

@page {
    size: A4;
    margin: 8mm;
}
</style>
