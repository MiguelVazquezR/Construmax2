/**
 * Shared helpers for the "Por programar" ticket status.
 *
 * This status is reserved for tickets whose branch is located in the
 * allowed region/state (Jalisco). The comparison ignores case, accents and
 * surrounding whitespace, so "jalisco", "JALISCO" and " Jálisco " all match.
 */
export function useSchedulingRegion() {
    const SCHEDULING_REGION = 'Jalisco';

    const schedulingRegionMessage = 'Solo los tickets del estado de Jalisco pueden estar en el estatus "Por programar". Verifica que la región/estado de la sucursal esté bien escrita.';

    const normalizeRegion = (region) =>
        (region || '')
            .toString()
            .trim()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');

    const isSchedulingRegion = (region) => normalizeRegion(region) === normalizeRegion(SCHEDULING_REGION);

    const isTicketInSchedulingRegion = (ticket) => isSchedulingRegion(ticket?.branch?.region);

    return {
        isSchedulingRegion,
        isTicketInSchedulingRegion,
        schedulingRegionMessage,
    };
}
