/**
 * Shared helpers for the "Por programar" ticket status.
 *
 * This status is reserved for tickets whose branch is located in one of the
 * allowed regions/states (Jalisco, Nuevo León). The comparison ignores case,
 * accents and surrounding whitespace, so "jalisco", "JALISCO" and " Jálisco "
 * all match.
 */
export function useSchedulingRegion() {
    const SCHEDULING_REGIONS = ['Jalisco', 'Nuevo León'];

    const schedulingRegionMessage = 'Solo los tickets de los estados de Jalisco y Nuevo León pueden estar en el estatus "Por programar". Verifica que la región/estado de la sucursal esté bien escrita.';

    const normalizeRegion = (region) =>
        (region || '')
            .toString()
            .trim()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');

    const isSchedulingRegion = (region) =>
        SCHEDULING_REGIONS.some((allowed) => normalizeRegion(region) === normalizeRegion(allowed));

    const isTicketInSchedulingRegion = (ticket) => isSchedulingRegion(ticket?.branch?.region);

    return {
        isSchedulingRegion,
        isTicketInSchedulingRegion,
        schedulingRegionMessage,
    };
}
