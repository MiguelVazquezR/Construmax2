<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    private static array $mexicanCompanies = [
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

    private static array $paymentConditions = ['Contado', 'Crédito 15 días', 'Crédito 30 días', 'Crédito 45 días', 'Crédito 60 días'];
    private static array $paymentMethods = ['Transferencia', 'Cheque', 'Efectivo', 'Tarjeta', 'Débito automático'];
    private static array $invoiceUsages = ['Gastos generales', 'Adquisición de activos', 'Pagos a terceros', 'Gastos de operación'];
    private static array $currencies = ['MXN', 'USD'];

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(self::$mexicanCompanies);

        return [
            'name'             => $name,
            'business_name'    => $name . ' SA de CV',
            'rfc'              => strtoupper(fake()->bothify('???######???')),
            'payment_condition' => fake()->randomElement(self::$paymentConditions),
            'payment_method'   => fake()->randomElement(self::$paymentMethods),
            'invoice_usage'    => fake()->randomElement(self::$invoiceUsages),
            'currency'         => fake()->randomElement(self::$currencies),
            'payment_days'     => fake()->randomElement([null, 15, 30, 45, 60]),
            'is_active'        => true,
        ];
    }
}
