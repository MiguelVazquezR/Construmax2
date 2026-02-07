import { usePage } from '@inertiajs/vue3';

export function usePermissions() {
    /**
     * Verifica si el usuario actual tiene un permiso específico.
     * @param {String} name Nombre del permiso (ej. 'users.create')
     * @returns {Boolean}
     */
    const can = (name) => {
        const permissions = usePage().props.auth.permissions || [];
        // Si es Super Admin (ID 1 o Rol), generalmente tiene acceso total, 
        // pero validaremos contra la lista plana que envía el backend.
        return permissions.includes(name);
    };

    /**
     * Verifica si el usuario tiene un rol específico.
     * @param {String} name Nombre del rol (ej. 'Super Admin')
     * @returns {Boolean}
     */
    const hasRole = (name) => {
        const roles = usePage().props.auth.roles || [];
        return roles.includes(name);
    };

    return { can, hasRole };
}