<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerBranch;
use App\Models\CustomerContact;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private array $statusDistribution = [
        'Borrador'              => 25,
        'Levantamiento'         => 25,
        'Catálogo'              => 30,
        'Proceso de ejecución'  => 40,
        'Ejecutado'             => 30,
        'Facturado'             => 25,
        'Pagado'                => 25,
    ];

    private array $serviceTypes = [
        'Iluminación', 'Herrería', 'Acabados', 'Eléctrico', 'Aire acondicionado',
        'Sanitario', 'Anuncios', 'Pintura', 'Carpintería', 'Vidrio',
        'Aluminio', 'Protección civil STPS', 'Monta cargas', 'Control de plagas',
        'Impermeabilización', 'Servicios varios',
    ];

    private array $priorities = ['Baja', 'Media', 'Alta', 'Urgente'];

    private array $catalogDescriptions = [
        'Suministro e instalación de luminaria LED', 'Mantenimiento a equipo de aire acondicionado',
        'Pintura vinílica en muros interiores', 'Reparación de plafón', 'Impermeabilización de azotea',
        'Instalación de contactos eléctricos', 'Cambio de vidrio templado', 'Soldadura de estructura metálica',
        'Limpieza de ductos', 'Instalación de cámara CCTV', 'Mantenimiento de elevador',
        'Reparación de fuga sanitaria', 'Aplicación de sellador', 'Desmontaje de equipo obsoleto',
        'Suministro de material eléctrico', 'Fabricación de cancelería de aluminio',
        'Nivelación de piso', 'Instalación de piso cerámico', 'Pulido de mármol',
        'Mantenimiento preventivo general',
    ];

    private array $catalogUnits = ['pza', 'm²', 'lote', 'servicio', 'kg', 'm', 'hr', 'juego'];

    /** @var \Illuminate\Support\Collection */
    private $customers;

    /** @var \Illuminate\Support\Collection */
    private $branches;

    /** @var \Illuminate\Support\Collection */
    private $contacts;

    /** @var \Illuminate\Support\Collection */
    private $sellers;

    /** @var \Illuminate\Support\Collection */
    private $technicians;

    public function run(): void
    {
        // Admin user
        User::factory()->create([
            'name'     => 'Soporte DTW',
            'email'    => 'contacto@dtw.com.mx',
            'password' => bcrypt('321321321'),
        ]);

        // Permissions seeder
        $this->call([
            PermissionSeeder::class,
        ]);

        $this->seedCustomersWithBranchesAndContacts();
        $this->seedTechnicians();
        $this->seedSellers();
        $this->seedTickets();

        $this->command?->info('Seeder completado: 70 clientes, 100 técnicos, 200 tickets con presupuestos y catálogos.');
    }

    private function seedCustomersWithBranchesAndContacts(): void
    {
        $this->command?->info('Creando 70 clientes con sucursales y contactos...');

        $customers = [];
        $branches  = [];
        $contacts  = [];

        $mexicoStates = [
            'Ciudad de México', 'Nuevo León', 'Jalisco', 'Estado de México', 'Querétaro',
            'Guanajuato', 'Puebla', 'Yucatán', 'Baja California', 'Sonora',
            'Chihuahua', 'Tamaulipas', 'Veracruz', 'Sinaloa', 'Michoacán',
            'Oaxaca', 'Chiapas', 'Guerrero', 'San Luis Potosí', 'Coahuila',
            'Quintana Roo', 'Hidalgo', 'Tabasco', 'Zacatecas', 'Durango',
        ];

        $usStates = ['Texas', 'California', 'Florida', 'Arizona', 'New Mexico'];

        $companyNames = [
            'Grupo Hotelero del Norte', 'Constructora Alpina', 'Corporativo Omega',
            'Desarrollos Urbanos del Bajío', 'Hospitales del Sur', 'Tiendas Departamentales del Centro',
            'Operadora de Restaurantes SA', 'Inmobiliaria Las Américas', 'Grupo Industrial Monterrey',
            'Cadena Comercial del Pacífico', 'Farmacias del Ahorro Plus', 'Distribuidora de Alimentos Premier',
            'Corporativo Educativo Nacional', 'Constructora Vértice', 'Grupo Financiero del Sureste',
            'Operadora de Centros Comerciales', 'Hotelera del Caribe', 'Manufacturera del Norte',
            'Cadena de Gimnasios FitMax', 'Laboratorios Farmacéuticos Nova', 'Grupo Automotriz del Centro',
            'Desarrollos Residenciales del Valle', 'Empresa de Seguridad Privada Centinela', 'Transportes Rápidos del Norte',
            'Aerolínea Regional Vuela', 'Minería del Pacífico', 'Energía Renovable de México',
            'Alimentos Procesados del Bajío', 'Cadena de Cines Estrella', 'Constructora del Itsmo',
            'Corporativo de Telecomunicaciones', 'Grupo Editorial del Golfo', 'Agroindustrias del Norte',
            'Cadena de Lavanderías Limpio', 'Hospitales Metropolitanos', 'Desarrollos Turísticos del Pacífico',
            'Maquiladora Fronteriza Premier', 'Corporativo de Logística Integral', 'Operadora de Estacionamientos',
            'Inmobiliaria del Centro Histórico', 'Grupo Textil Mexicano', 'Cadena de Farmacias Popular',
            'Tecnologías de la Información MX', 'Constructora del Sureste', 'Operadora de Hoteles Boutique',
            'Distribuidora de Vinos y Licores', 'Corporativo de Servicios Funerarios', 'Grupo de Ingeniería Civil',
            'Empresa de Renta de Equipo Pesado', 'Consultoría Empresarial del Norte', 'Cadena de Tiendas de Conveniencia',
            'Manufactura de Plásticos del Centro', 'Grupo de Desarrollo Portuario', 'Empresa de Agua Purificada Crystal',
            'Cadena de Restaurantes Italiana', 'Grupo de Seguros del Bajío', 'Operadora Turística del Caribe',
            'Laboratorio de Análisis Clínicos', 'Constructora de Vivienda Social', 'Empresa de Renta de Maquinaria',
            'Corporativo de Estacionamientos', 'Grupo de Transporte de Carga', 'Recicladora del Bajío',
            'Comercializadora de Acero', 'Consultorios Dentales Dentimax', 'Cadena de Auto Lavados Express',
            'Agencia de Publicidad Creativa', 'Bodegas y Almacenes del Norte', 'Servicios de Jardinería Integral',
            'Empresa de Mensajería Rápida', 'Fundidora del Norte',
        ];

        $positions = [
            'Gerente General', 'Gerente de Operaciones', 'Gerente de Mantenimiento',
            'Jefe de Compras', 'Coordinador de Servicios', 'Director de Planta',
            'Supervisor de Instalaciones', 'Gerente Administrativo', 'Encargado de Proyectos',
            'Director Regional', 'Jefe de Seguridad', 'Coordinador de Logística',
        ];

        $paymentMethods = ['Transferencia', 'Cheque', 'Efectivo', 'Tarjeta', 'Débito automático'];
        $invoiceUsages = ['Gastos generales', 'Adquisición de activos', 'Pagos a terceros', 'Gastos de operación'];

        $now = now()->toDateTimeString();

        foreach ($companyNames as $i => $name) {
            $customerId = $i + 1;
            $customers[] = [
                'id'               => $customerId,
                'name'             => $name,
                'business_name'    => $name . ' SA de CV',
                'rfc'              => strtoupper(Str::random(3) . fake()->numerify('######') . Str::random(3)),
                'payment_condition' => fake()->randomElement(['Contado', 'Crédito 15 días', 'Crédito 30 días', 'Crédito 45 días', 'Crédito 60 días']),
                'payment_method'   => fake()->randomElement($paymentMethods),
                'invoice_usage'    => fake()->randomElement($invoiceUsages),
                'currency'         => fake()->randomElement(['MXN', 'USD']),
                'payment_days'     => fake()->randomElement([null, 15, 30, 45, 60]),
                'is_active'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];

            $numBranches = fake()->numberBetween(1, 4);
            for ($b = 0; $b < $numBranches; $b++) {
                $country = fake()->randomElement(['México', 'Estados Unidos']);
                $region  = $country === 'México'
                    ? fake()->randomElement($mexicoStates)
                    : fake()->randomElement($usStates);

                $branches[] = [
                    'customer_id' => $customerId,
                    'country'     => $country,
                    'region'      => $region,
                    'unit'        => fake()->randomElement([
                        'Centro Comercial', 'Planta', 'Oficinas Corporativas', 'Sucursal',
                        'Bodega', 'Piso', 'Nave Industrial', 'Hotel', 'Hospital', 'Tienda',
                    ]) . ' ' . fake()->word(),
                    'branch_name' => fake()->company() . ' ' . fake()->randomElement([
                        'Sucursal', 'Planta', 'Oficina', 'Unidad', 'Agencia',
                    ]) . ' ' . $region,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }

            $numContacts = fake()->numberBetween(1, 3);
            for ($c = 0; $c < $numContacts; $c++) {
                $firstName = fake()->firstName();
                $lastName  = fake()->lastName();
                $contacts[] = [
                    'customer_id' => $customerId,
                    'name'        => $firstName . ' ' . $lastName,
                    'email'       => strtolower($firstName . '.' . $lastName . '@' . fake()->safeEmailDomain()),
                    'phone'       => fake()->numerify('55########'),
                    'position'    => fake()->randomElement($positions),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        DB::table('customers')->insert($customers);
        DB::table('customer_branches')->insert($branches);
        DB::table('customer_contacts')->insert($contacts);

        $this->customers = Customer::all();
        $this->branches  = CustomerBranch::all();
        $this->contacts  = CustomerContact::all();

        $this->command?->info('  Clientes: ' . $this->customers->count() . ', Sucursales: ' . $this->branches->count() . ', Contactos: ' . $this->contacts->count());
    }

    private function seedTechnicians(): void
    {
        $this->command?->info('Creando 100 técnicos (50 internos, 50 externos)...');

        $specialties = Technician::SPECIALTIES;
        $now         = now()->toDateTimeString();

        $users = [];
        $techs = [];

        for ($i = 0; $i < 100; $i++) {
            $userId = $i + 100; // offset 100–199
            $isInternal = $i < 50;

            $users[] = [
                'id'                => $userId,
                'name'              => fake()->name(),
                'email'             => fake()->unique()->safeEmail(),
                'email_verified_at' => $now,
                'password'          => Hash::make('password'),
                'is_active'         => true,
                'remember_token'    => Str::random(10),
                'created_at'        => $now,
                'updated_at'        => $now,
            ];

            $techs[] = [
                'user_id'            => $userId,
                'phone'              => fake()->numerify('55########'),
                'secondary_phone'    => fake()->optional(0.3)->numerify('55########'),
                'is_internal'        => $isInternal,
                'state'              => fake()->state(),
                'city'               => fake()->city(),
                'colony'             => fake()->optional()->word(),
                'zip_code'           => fake()->numerify('#####'),
                'coverage_radius_km' => fake()->numberBetween(5, 100),
                'specialties'        => json_encode(fake()->randomElements($specialties, fake()->numberBetween(1, 4))),
                'legal_name'         => fake()->name(),
                'rfc'                => strtoupper(Str::random(4) . fake()->numerify('######') . Str::random(3)),
                'bank_name'          => fake()->randomElement(['BBVA', 'Banorte', 'Santander', 'Banamex', 'HSBC', 'Banregio']),
                'bank_account'       => fake()->numerify('############'),
                'clabe'              => fake()->numerify('##################'),
                'status'             => fake()->randomElement(['Activo', 'Activo', 'Activo', 'En revisión', 'Inactivo']),
                'rating_avg'         => fake()->randomFloat(2, 1, 5),
                'internal_notes'     => fake()->optional(0.3)->sentence(),
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        DB::table('users')->insert($users);
        DB::table('technicians')->insert($techs);

        $this->technicians = User::whereIn('id', range(100, 199))->get();

        $this->command?->info('  Técnicos creados: ' . $this->technicians->count());
    }

    private function seedSellers(): void
    {
        $this->command?->info('Creando 20 vendedores...');

        $now     = now()->toDateTimeString();
        $sellers = [];

        for ($i = 0; $i < 20; $i++) {
            $userId = $i + 200; // offset 200–219
            $sellers[] = [
                'id'                => $userId,
                'name'              => fake()->name(),
                'email'             => fake()->unique()->safeEmail(),
                'email_verified_at' => $now,
                'password'          => Hash::make('password'),
                'is_active'         => true,
                'remember_token'    => Str::random(10),
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }

        DB::table('users')->insert($sellers);
        $this->sellers = User::whereIn('id', range(200, 219))->get();

        $this->command?->info('  Vendedores creados: ' . $this->sellers->count());
    }

    private function seedTickets(): void
    {
        $this->command?->info('Creando 200 tickets con presupuestos, catálogos y pagos...');

        $now             = now()->toDateTimeString();
        $ticketRows      = [];
        $budgetRows      = [];
        $catalogRows     = [];
        $catalogItemRows = [];
        $paymentRows     = [];
        $taskRows        = [];
        $ticketIndex     = 0;

        $taskNames = [
            'Levantamiento físico en sitio', 'Elaboración de catálogo de conceptos',
            'Instalación de equipo', 'Pruebas de funcionamiento', 'Limpieza de área de trabajo',
            'Desmontaje de equipo existente', 'Cableado estructurado', 'Configuración de sistema',
            'Aplicación de impermeabilizante', 'Pintura de acabado', 'Soldadura de estructura',
            'Instalación de ductos', 'Conexión eléctrica', 'Pruebas de presión',
            'Entrega y capacitación al cliente', 'Reporte fotográfico', 'Medición de parámetros',
            'Ajuste y calibración', 'Retiro de escombro', 'Instalación de accesorios',
        ];

        // Flatten status distribution into ordered queue
        $statusQueue = [];
        foreach ($this->statusDistribution as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $statusQueue[] = $status;
            }
        }

        foreach ($statusQueue as $status) {
            $ticketIndex++;
            $ticketId = $ticketIndex;

            $customer = $this->customers->random();
            $branch   = $this->branches->where('customer_id', $customer->id)->random();
            $contact  = $this->contacts->where('customer_id', $customer->id)->random();
            $seller   = $this->sellers->random();

            $techIds = $this->technicians->random(fake()->numberBetween(1, 3))->pluck('id')->toArray();

            $scheduledStart = fake()->dateTimeBetween('-6 months', '+2 months');
            $scheduledEnd   = (clone $scheduledStart)->modify('+' . fake()->numberBetween(1, 30) . ' days');

            $ticketRows[] = [
                'id'                  => $ticketId,
                'customer_id'         => $customer->id,
                'customer_contact_id' => $contact->id,
                'customer_branch_id'  => $branch->id,
                'seller_id'           => $seller->id,
                'name'                => fake()->sentence(3),
                'service_type'        => fake()->randomElement($this->serviceTypes),
                'duration'            => fake()->randomElement(['1 semana', '2 semanas', '1 mes', '3 días', '2 meses', null]),
                'technicians'         => json_encode($techIds),
                'status'              => $status,
                'priority'            => fake()->randomElement($this->priorities),
                'scheduled_start'     => $scheduledStart->format('Y-m-d'),
                'scheduled_end'       => $scheduledEnd->format('Y-m-d'),
                'instructions'        => fake()->optional(0.6)->paragraph(),
                'created_at'          => $now,
                'updated_at'          => $now,
            ];

            // Budget: every ticket gets one
            $needsInvoice = in_array($status, ['Facturado', 'Pagado']);
            $budgetRows[] = [
                'ticket_id'      => $ticketId,
                'status'         => fake()->randomElement(['Aprobado', 'Aprobado', 'Aprobado', 'Enviado al cliente']),
                'description'    => fake()->optional()->paragraph(),
                'currency'       => 'MXN',
                'exchange_rate'  => 1.0000,
                'user_id'        => $seller->id,
                'invoice_date'   => $needsInvoice ? fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d') : null,
                'invoice_number' => $needsInvoice ? 'F-' . strtoupper(Str::random(3) . '-' . fake()->numerify('###') . '-' . Str::random(4)) : null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];

            // Catalog: only for statuses beyond Borrador/Levantamiento
            $needsCatalog = !in_array($status, ['Borrador', 'Levantamiento']);
            $catalogTotal = 0.0;

            if ($needsCatalog) {
                $numItems = fake()->numberBetween(3, 10);
                $subtotal = 0.0;

                for ($j = 0; $j < $numItems; $j++) {
                    $quantity  = fake()->randomFloat(2, 1, 50);
                    $unitPrice = fake()->randomFloat(2, 50, 15000);
                    $itemTotal = round($quantity * $unitPrice, 2);
                    $subtotal += $itemTotal;

                    $catalogItemRows[] = [
                        'budget_catalog_id' => $ticketId,
                        'description'       => fake()->randomElement($this->catalogDescriptions),
                        'unit'              => fake()->randomElement($this->catalogUnits),
                        'quantity'          => $quantity,
                        'unit_price'        => $unitPrice,
                        'total'             => $itemTotal,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }

                $catalogTotal = round($subtotal * 1.16, 2);

                $catalogRows[] = [
                    'id'         => $ticketId,
                    'budget_id'  => $ticketId,
                    'version'    => 1,
                    'subtotal'   => round($subtotal, 2),
                    'iva'        => round($subtotal * 0.16, 2),
                    'total'      => $catalogTotal,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Payments: only for 'Pagado' — must cover catalog total
            if ($status === 'Pagado' && $catalogTotal > 0) {
                $remaining = $catalogTotal;
                $payCount  = fake()->numberBetween(1, 3);

                for ($p = 0; $p < $payCount; $p++) {
                    $isLast = $p === $payCount - 1;
                    $amount = $isLast
                        ? round($remaining, 2)
                        : round(fake()->randomFloat(2, 1000, max(1000, $remaining * 0.6)), 2);
                    $remaining -= $amount;

                    $paymentRows[] = [
                        'budget_id'      => $ticketId,
                        'amount'         => max($amount, 0.01),
                        'payment_date'   => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
                        'reference'      => 'PAGO-' . strtoupper(Str::random(2) . fake()->numerify('###')),
                        'payment_method' => fake()->randomElement(['Transferencia', 'Cheque', 'Efectivo', 'Tarjeta']),
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
            }

            // Tasks: 2–5 per ticket
            $numTasks = fake()->numberBetween(2, 5);
            for ($t = 0; $t < $numTasks; $t++) {
                $assignedTech = fake()->randomElement($techIds);
                $taskStatus   = in_array($status, ['Ejecutado', 'Facturado', 'Pagado'])
                    ? 'Completada'
                    : fake()->randomElement(['Pendiente', 'En proceso', 'Completada']);

                $startDate = clone $scheduledStart;
                $dueDate   = (clone $startDate)->modify('+' . fake()->numberBetween(1, 15) . ' days');
                $completed = $taskStatus === 'Completada'
                    ? (clone $dueDate)->modify('-' . fake()->numberBetween(0, 2) . ' days')
                    : null;

                $taskRows[] = [
                    'ticket_id'    => $ticketId,
                    'user_id'      => $assignedTech,
                    'name'         => fake()->randomElement($taskNames),
                    'description'  => fake()->optional()->sentence(),
                    'status'       => $taskStatus,
                    'start_date'   => $startDate->format('Y-m-d H:i:s'),
                    'due_date'     => $dueDate->format('Y-m-d H:i:s'),
                    'completed_at' => $completed ? $completed->format('Y-m-d H:i:s') : null,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
        }

        // Batch insert for performance
        DB::table('tickets')->insert($ticketRows);
        DB::table('budgets')->insert($budgetRows);

        if (!empty($catalogRows)) {
            DB::table('budget_catalogs')->insert($catalogRows);
        }
        if (!empty($catalogItemRows)) {
            DB::table('budget_catalog_items')->insert($catalogItemRows);
        }
        if (!empty($paymentRows)) {
            DB::table('budget_payments')->insert($paymentRows);
        }
        if (!empty($taskRows)) {
            DB::table('ticket_tasks')->insert($taskRows);
        }

        $this->command?->info(sprintf(
            '  Tickets: %d | Budgets: %d | Catalogs: %d | Catalog items: %d | Payments: %d | Tasks: %d',
            count($ticketRows),
            count($budgetRows),
            count($catalogRows),
            count($catalogItemRows),
            count($paymentRows),
            count($taskRows)
        ));
    }
}
