<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

// Redirección inicial
Route::redirect('/', '/login');

// Proxy de mapas GeoJSON (evita CORS)
Route::get('/maps/{country}', function ($country) {
    $sources = [
        'usa' => [
            'https://echarts.apache.org/examples/data/asset/geo/USA.json',
        ],
        'mexico' => [
            'https://geo.datav.aliyun.com/areas_v3/bound/484_full.json',
            'https://raw.githubusercontent.com/echarts-maps/map-json/main/json/Mexico.json',
        ],
    ];

    if (!isset($sources[$country])) {
        abort(404);
    }

    $lastError = null;
    foreach ($sources[$country] as $url) {
        try {
            $response = Http::timeout(8)->get($url);
            if ($response->successful()) {
                return response($response->body(), 200)
                    ->header('Content-Type', 'application/json')
                    ->header('Access-Control-Allow-Origin', '*');
            }
            $lastError = "HTTP {$response->status()}";
        } catch (\Exception $e) {
            $lastError = $e->getMessage();
        }
    }

    abort(502, "No se pudo obtener el mapa: {$lastError}");
})->name('maps.proxy');

// Grupo Autenticado
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    
    // Dashboard Principal Inteligente
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // --- PROXY API TIPO DE CAMBIO (Solución CORS) ---
    // El backend hace la petición a la API externa para evitar bloqueos del navegador
    Route::get('/api/exchange-rate', function () {
        try {
            $response = Http::timeout(7)->get('https://open.er-api.com/v6/latest/USD');
            return $response->json();
        } catch (\Exception $e) {
            // Retornar null o un error controlado si falla
            return response()->json(['rates' => ['MXN' => null]], 500);
        }
    })->name('exchange.rate');
});

// Importar rutas modulares
require __DIR__ . '/web/users.php';
require __DIR__ . '/web/roles-permissions.php';
require __DIR__ . '/web/customers.php';
require __DIR__ . '/web/budgets.php';
require __DIR__ . '/web/crm.php';
require __DIR__ . '/web/tickets.php';
require __DIR__ . '/web/calendar.php';
require __DIR__ . '/web/technicians.php';
require __DIR__ . '/web/invoices.php';
require __DIR__ . '/web/costs.php';

// --- SOLUCIÓN PARA HOSTING SIN SYMLINK ---
// Esta ruta intercepta las peticiones a imágenes y documentos
// y sirve el archivo directamente desde la carpeta storage/app/public
Route::get('/storage/{extra}', function ($extra) {
    $path = storage_path('app/public/' . $extra);

    if (!file_exists($path)) {
        abort(404);
    }

    return response()->file($path);
})->where('extra', '.*');