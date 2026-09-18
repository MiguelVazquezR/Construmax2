/**
 * Shared helpers to render the cost type of budget concepts consistently
 * across the app (Mano de obra / Materiales).
 *
 * Types come from budget_concepts.type: labor, material.
 */
export function useCostType() {
    const COST_TYPE_LABELS = {
        labor: 'Mano de obra',
        material: 'Materiales',
    };

    const COST_TYPE_TAG_TYPES = {
        labor: 'warning',
        material: 'success',
    };

    const costTypeLabel = (type) => COST_TYPE_LABELS[type] || null;

    const costTypeTagType = (type) => COST_TYPE_TAG_TYPES[type] || 'info';

    return {
        costTypeLabel,
        costTypeTagType,
    };
}
