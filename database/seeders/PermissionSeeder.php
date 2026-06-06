<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Limpiar caché de permisos
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Definición de Permisos
        $permissions = [
            // --- MÓDULO: USUARIOS ---
            [
                'name' => 'users.index',
                'category' => 'Usuarios',
                'description' => 'Ver listado y detalles de usuarios'
            ],
            [
                'name' => 'users.create',
                'category' => 'Usuarios',
                'description' => 'Registrar nuevos usuarios y empleados'
            ],
            [
                'name' => 'users.edit',
                'category' => 'Usuarios',
                'description' => 'Editar información de usuarios existentes'
            ],
            [
                'name' => 'users.delete',
                'category' => 'Usuarios',
                'description' => 'Eliminar usuarios del sistema'
            ],
            [
                'name' => 'users.toggle-status',
                'category' => 'Usuarios',
                'description' => 'Activar o desactivar acceso a usuarios'
            ],
            // técnicos
            [
                'name' => 'technicians.index',
                'category' => 'Técnicos',
                'description' => 'Ver listado y detalles de técnicos'
            ],
            [
                'name' => 'technicians.create',
                'category' => 'Técnicos',
                'description' => 'Registrar nuevos técnicos'
            ],
            [
                'name' => 'technicians.edit',
                'category' => 'Técnicos',
                'description' => 'Editar información de técnicos existentes'
            ],
            [
                'name' => 'technicians.delete',
                'category' => 'Técnicos',
                'description' => 'Eliminar técnicos del sistema'
            ],

            // --- MÓDULO: ROLES Y PERMISOS ---
            [
                'name' => 'roles.index',
                'category' => 'Configuración',
                'description' => 'Ver roles y sus permisos asignados'
            ],
            [
                'name' => 'roles.create',
                'category' => 'Configuración',
                'description' => 'Crear nuevos roles'
            ],
            [
                'name' => 'roles.edit',
                'category' => 'Configuración',
                'description' => 'Editar roles y modificar sus permisos'
            ],
            [
                'name' => 'roles.delete',
                'category' => 'Configuración',
                'description' => 'Eliminar roles del sistema'
            ],
            // El permiso para gestionar "permisos base" (permissions.*) suele reservarse
            // exclusivamente para el desarrollador (ID 1) por código, pero lo agregamos por si acaso.
            [
                'name' => 'permissions.manage',
                'category' => 'Sistema',
                'description' => 'Gestionar la matriz de permisos (Solo Desarrollador)'
            ],

            // --- MÓDULO: CLIENTES ---
            [
                'name' => 'customers.index',
                'category' => 'Clientes',
                'description' => 'Ver listado y detalles de clientes'
            ],
            [
                'name' => 'customers.create',
                'category' => 'Clientes',
                'description' => 'Registrar nuevos clientes y contactos'
            ],
            [
                'name' => 'customers.edit',
                'category' => 'Clientes',
                'description' => 'Editar información comercial y fiscal de clientes'
            ],
            [
                'name' => 'customers.delete',
                'category' => 'Clientes',
                'description' => 'Eliminar clientes del sistema'
            ],

            // --- MÓDULO: PRESUPUESTOS (VENTAS) ---
            [
                'name' => 'budgets.index',
                'category' => 'Presupuestos',
                'description' => 'Ver listado, detalles y costos de presupuestos'
            ],
            [
                'name' => 'budgets.create',
                'category' => 'Presupuestos',
                'description' => 'Crear nuevos presupuestos'
            ],
            [
                'name' => 'budgets.edit',
                'category' => 'Presupuestos',
                'description' => 'Editar presupuestos, costos y estatus'
            ],
            [
                'name' => 'budgets.delete',
                'category' => 'Presupuestos',
                'description' => 'Eliminar presupuestos'
            ],
            // Permisos sensibles de Presupuestos
            [
                'name' => 'budgets.payments.manage',
                'category' => 'Presupuestos',
                'description' => 'Registrar y eliminar pagos de proyectos'
            ],
            [
                'name' => 'budgets.files.manage',
                'category' => 'Presupuestos',
                'description' => 'Subir y eliminar archivos adjuntos (planos, facturas)'
            ],

            // --- MÓDULO: TICKETS (OPERACIONES) ---
            [
                'name' => 'tickets.index',
                'category' => 'Tickets',
                'description' => 'Ver tablero de tickets y cronogramas'
            ],
            [
                'name' => 'tickets.create',
                'category' => 'Tickets',
                'description' => 'Generar nuevas órdenes de servicio (tickets)'
            ],
            [
                'name' => 'tickets.edit',
                'category' => 'Tickets',
                'description' => 'Editar asignaciones, fechas y estatus de tickets'
            ],
            [
                'name' => 'tickets.delete',
                'category' => 'Tickets',
                'description' => 'Eliminar tickets operativos'
            ],
            [
                'name' => 'tickets.tasks.create',
                'category' => 'Tickets',
                'description' => 'Crear tareas dentro de tickets'
            ],
            [
                'name' => 'tickets.tasks.edit',
                'category' => 'Tickets',
                'description' => 'Editar tareas dentro de tickets y adjuntar evidencias'
            ],
            [
                'name' => 'tickets.tasks.delete',
                'category' => 'Tickets',
                'description' => 'Eliminar tareas dentro de tickets'
            ],
            [
                'name' => 'tickets.tasks.toggle',
                'category' => 'Tickets',
                'description' => 'Marcar tareas como completadas o pendientes de cualquier usuario'
            ],

            // --- MÓDULO: ANALÍTICAS (DASHBOARDS) ---
            [
                'name' => 'tickets.analytics',
                'category' => 'Analíticas',
                'description' => 'Ver tablero de rendimiento operativo'
            ],

            // --- MÓDULO: FACTURACIÓN ---
            [
                'name' => 'invoices.index',
                'category' => 'Facturación',
                'description' => 'Permite ver la lista de presupuestos que requieren facturación o seguimiento con los pagos'
            ],
            [
                'name' => 'invoices.upload',
                'category' => 'Facturación',
                'description' => 'Permite marcar como factura emitida a un presupuesto y también subir la factura'
            ],

            // --- MÓDULO: COSTOS ---
            [
                'name' => 'costs.index',
                'category' => 'Costos',
                'description' => 'Ver listado de presupuestos y costos'
            ],

            // --- MÓDULO: NOTIFICACIONES ---
            [
                'name' => 'config.notifications',
                'category' => 'Configuración',
                'description' => 'Gestionar configuraciones de notificaciones (quién recibe qué)'
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                [
                    'category' => $permission['category'],
                    'description' => $permission['description'],
                    'guard_name' => 'web'
                ]
            );
        }

        // 3. Crear Rol Super Admin
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        // 4. Asignar TODOS los permisos al Super Admin
        $superAdminRole->syncPermissions(Permission::all());

        // 5. Asignar rol al usuario ID 1 (Tu usuario desarrollador/admin)
        $adminUser = User::find(1);
        if ($adminUser) {
            $adminUser->assignRole($superAdminRole);
            $this->command->info("Rol 'Super Admin' asignado al usuario ID: 1 ({$adminUser->name})");
        } else {
            $this->command->warn("No se encontró el usuario con ID 1. Recuerda asignarle el rol manualmente.");
        }

        $this->command->info('Permisos y roles generados correctamente.');
    }
}