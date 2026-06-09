<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class TutorialController extends Controller
{
    public function index()
    {
        $videos = [
            [
                'id' => 1,
                'title' => 'Módulo de inicio',
                'description' => 'Aprende a usar la pantalla de inicio como tu organizador diario. Conoce los accesos rápidos, revisa tu agenda del día, monitorea tus tickets asignados y mantén bajo control el estado de las operaciones, costos y facturación en tiempo real.',
                'duration' => '01:13',
                'filename' => 'inicio.mp4',
                'thumbnail' => 'inicio.jpg',
            ],
            [
                'id' => 2,
                'title' => 'Módulo de analíticas',
                'description' => 'Descubre cómo usar los filtros globales para evaluar el rendimiento operativo y comercial. Aprende a alternar divisas, explorar el mapa interactivo de servicios y auditar los pagos de tus técnicos externos y estados de facturación.',
                'duration' => '01:34',
                'filename' => 'analiticas.mp4',
                'thumbnail' => 'analiticas.jpg',
            ],
            [
                'id' => 3,
                'title' => 'Módulo de clientes',
                'description' => 'Domina el control de tu base comercial. Aprende a buscar clientes, dar de alta registros con datos fiscales, configurar sucursales geográficas y asignar contactos específicos con accesos directos a llamadas o WhatsApp.',
                'duration' => '01:48',
                'filename' => 'clientes.mp4',
                'thumbnail' => 'clientes.jpg',
            ],
            [
                'id' => 4,
                'title' => 'Módulo de tickets',
                'description' => 'Controla el ciclo de vida de una orden de servicio. Aprende a usar los filtros y la vista Kanban, crear tickets con plantillas automatizadas, compartir órdenes de trabajo con técnicos externos y recibir sus evidencias desde el campo.',
                'duration' => '08:08',
                'filename' => 'tickets.mp4',
                'thumbnail' => 'tickets.jpg',
            ],
            [
                'id' => 5,
                'title' => 'Módulo de presupuestos',
                'description' => 'Vincula las finanzas con la operación en obra. Aprende a estructurar conceptos de costo, gestionar la cotización en pesos o dólares con tipo de cambio automático, registrar abonos de clientes y controlar los pagos a contratistas.',
                'duration' => '08:07',
                'filename' => 'presupuestos.mp4',
                'thumbnail' => 'presupuestos.jpg',
            ],
            [
                'id' => 6,
                'title' => 'Módulo de costos',
                'description' => 'Estructura catálogos de costos detallados por partidas con cálculo de IVA automático. Aprende a generar e imprimir presupuestos formales y a utilizar el historial de versiones para comparar los cambios solicitados por el cliente.',
                'duration' => '06:31',
                'filename' => 'costos.mp4',
                'thumbnail' => 'costos.jpg',
            ],
            [
                'id' => 7,
                'title' => 'Módulo de facturación',
                'description' => 'Audita evidencias de campo y registra facturas en el sistema. Aprende cómo el ERP calcula automáticamente el vencimiento del crédito según el cliente y cómo activa alertas de cobro inmediato si se cumplen los plazos establecidos.',
                'duration' => '04:48',
                'filename' => 'facturacion.mp4',
                'thumbnail' => 'facturacion.jpg',
            ],
            [
                'id' => 8,
                'title' => 'Configuración de notificaciones',
                'description' => 'Un tutorial rápido para personalizar los flujos de comunicación. Aprende a asignar qué usuarios reciben alertas automáticas en su correo y campana digital según los eventos de costos, operaciones y vencimientos de facturas.',
                'duration' => '02:13',
                'filename' => 'notificaciones.mp4',
                'thumbnail' => 'notificaciones.jpg',
            ],
        ];

        return Inertia::render('Tutorials/Index', [
            'videos' => $videos,
            'can' => [
                'tutorials' => true,
            ],
        ]);
    }
}
