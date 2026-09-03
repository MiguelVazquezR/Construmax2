/**
 * Shared helpers to render a cost catalog status consistently across the
 * app (Costs, Budgets and Tickets views).
 *
 * Statuses come from BudgetCatalog: approved, pending_approval, pending_update.
 */
export function useCatalogStatus() {
    const CATALOG_STATUS_LABELS = {
        approved: 'Aprobado',
        pending_approval: 'Pendiente de aprobación',
        pending_update: 'Pendiente de actualización',
    };

    const CATALOG_STATUS_COMPACT_LABELS = {
        approved: 'Aprobado',
        pending_approval: 'Pendiente',
        pending_update: 'Pendiente de actualización',
    };

    const CATALOG_STATUS_TYPES = {
        approved: 'success',
        pending_approval: 'warning',
        pending_update: 'danger',
    };

    const isCatalogApproved = (status) => status === 'approved';

    const isCatalogPendingUpdate = (status) => status === 'pending_update';

    const catalogStatusLabel = (status) => CATALOG_STATUS_LABELS[status] || 'Pendiente de aprobación';

    const catalogStatusCompactLabel = (status) => CATALOG_STATUS_COMPACT_LABELS[status] || 'Pendiente';

    const catalogStatusType = (status) => CATALOG_STATUS_TYPES[status] || 'warning';

    const catalogUpdatedTooltip = 'El presupuesto fue actualizado. Guarda una nueva versión del catálogo para continuar con la aprobación.';

    return {
        isCatalogApproved,
        isCatalogPendingUpdate,
        catalogStatusLabel,
        catalogStatusCompactLabel,
        catalogStatusType,
        catalogUpdatedTooltip,
    };
}
