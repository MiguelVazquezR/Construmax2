import { ElMessage } from 'element-plus';

export function useCostsHelpers() {
    const formatCurrency = (value, currency = 'MXN') => {
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: currency,
        }).format(value || 0);
    };

    const formatNumber = (value) => {
        return new Intl.NumberFormat('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value || 0);
    };

    const copyToClipboard = (value) => {
        const text = typeof value === 'number' ? formatNumber(value) : String(value);
        navigator.clipboard.writeText(text).then(() => {
            ElMessage({ message: 'Copied', type: 'success', duration: 800, showClose: false });
        }).catch(() => {
            ElMessage({ message: 'Copy failed', type: 'error', duration: 1000, showClose: false });
        });
    };

    return { formatCurrency, formatNumber, copyToClipboard };
}
