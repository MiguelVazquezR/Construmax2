# Guía de desarrollo — Módulo "Depósitos"

> Proyecto: Construmax2 ERP (Laravel 12 + Inertia + Vue 3 + Element Plus)
> Este documento es la especificación funcional y técnica completa para implementar el módulo. Sigue el mismo patrón arquitectónico de los módulos `Budgets`, `Invoices` y `Costs` (Controller → Action → Service → Model, Form Requests para validación).

---

## 1. Objetivo y alcance del módulo

Programar y controlar los depósitos (pagos) a **técnicos externos** (`technicians.is_internal = false`). Los técnicos internos están en nómina y nunca deben aparecer en este módulo.

Flujo general:

1. Un usuario con permiso programa un depósito (técnico, cuenta bancaria, ticket, monto, turno, tipo).
2. Se notifica (campana) a los usuarios con permiso de aprobación.
3. Un solo usuario aprueba → el depósito queda `approved`. No existe "rechazar"; si nadie aprueba, queda `pending` indefinidamente hasta que se apruebe o se elimine.
4. Cualquier usuario puede generar un enlace público (por depósito o por día) y compartirlo por el medio que sea.
5. La persona encargada de depositar (sin necesidad de cuenta ni permisos) entra al enlace público, ve los depósitos aprobados del día/depósito con los datos bancarios completos, y marca como "realizado" capturando opcionalmente comisión y comprobante.
6. Al marcar como realizado, el sistema crea automáticamente el registro en `technician_payments` (hoy esto se hace manual desde Ticket/Budget; con Depósitos se automatiza).

---

## 2. Modelos y migraciones nuevas

### 2.1 `deposit_types` (catálogo gestionable)

Sigue el mismo patrón que `ServiceType` (CRUD tipo JSON API, con `is_active`).

```php
Schema::create('deposit_types', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**Seeder** (elementos por defecto): Anticipo, Finiquito, Visita, Extras, Pago único.

Modelo `app/Models/DepositType.php`:
```php
class DepositType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_active'];

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }
}
```

### 2.2 `deposits`

```php
Schema::create('deposits', function (Blueprint $table) {
    $table->id();

    $table->foreignId('technician_id')->constrained()->cascadeOnDelete();
    $table->foreignId('technician_bank_account_id')->constrained();
    $table->foreignId('ticket_id')->constrained();
    // budget_id se deriva del ticket al crear el registro (Ticket hasOne Budget).
    // Se guarda de forma denormalizada porque technician_payments requiere budget_id directo.
    $table->foreignId('budget_id')->constrained();
    $table->foreignId('deposit_type_id')->constrained();

    $table->decimal('amount', 10, 2);
    $table->enum('shift', ['matutino', 'vespertino']);
    $table->enum('status', ['pending', 'approved', 'completed'])->default('pending');

    $table->foreignId('created_by')->constrained('users');
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('approved_at')->nullable();

    $table->timestamp('completed_at')->nullable();
    $table->decimal('commission_amount', 10, 2)->nullable();

    // Se llena cuando se completa el depósito y se genera el pago automático
    $table->foreignId('technician_payment_id')->nullable()->constrained()->nullOnDelete();

    $table->text('notes')->nullable();

    $table->timestamps();
});
```

> Nota: usa `restrictOnDelete()` (o simplemente deja el comportamiento por defecto, que ya es restrict) en `ticket_id`, `budget_id`, `technician_bank_account_id` y `deposit_type_id`, para no permitir borrados accidentales que dejen depósitos huérfanos.

Modelo `app/Models/Deposit.php`:

```php
class Deposit extends Model
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'technician_id', 'technician_bank_account_id', 'ticket_id', 'budget_id',
        'deposit_type_id', 'amount', 'shift', 'status', 'created_by',
        'approved_by', 'approved_at', 'completed_at', 'commission_amount',
        'technician_payment_id', 'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    // Relaciones
    public function technician(): BelongsTo { return $this->belongsTo(Technician::class); }
    public function bankAccount(): BelongsTo { return $this->belongsTo(TechnicianBankAccount::class, 'technician_bank_account_id'); }
    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function budget(): BelongsTo { return $this->belongsTo(Budget::class); }
    public function depositType(): BelongsTo { return $this->belongsTo(DepositType::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function technicianPayment(): BelongsTo { return $this->belongsTo(TechnicianPayment::class); }

    // Media (comprobante)
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('voucher')->singleFile();
    }

    // Scopes
    public function scopePending($q) { return $q->where('status', 'pending'); }
    public function scopeApproved($q) { return $q->where('status', 'approved'); }
    public function scopeCompleted($q) { return $q->where('status', 'completed'); }
    public function scopeForDate($q, $date) { return $q->whereDate('created_at', $date); } // ver nota 4.4 sobre fecha "programada" vs fecha de creación
    public function scopeForShift($q, $shift) { return $q->where('shift', $shift); }

    public function scopeFilter($q, array $filters)
    {
        return $q
            ->when($filters['search'] ?? null, fn($q, $s) => $q->whereHas('technician.user', fn($q) => $q->where('name', 'like', "%{$s}%")))
            ->when($filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->when($filters['shift'] ?? null, fn($q, $s) => $q->where('shift', $s));
    }
}
```

> **Importante — fecha del depósito**: el diseño actual no definió explícitamente un campo "fecha programada de depósito" separado de `created_at`. Para que la vista de calendario y los enlaces "por día" funcionen bien, se recomienda **agregar un campo `scheduled_date` (date, not nullable, default hoy)** editable por el usuario al programar. Usa este campo (no `created_at`) para: agrupar en el calendario, filtrar el enlace público "por día", y como criterio de "depósitos del día" en la vista de aprobación. Ajusta `scopeForDate` para usar `scheduled_date` en vez de `created_at`.

### 2.3 Cálculo de "pendiente por técnico" (para el selector de tickets)

Confirmado: se replica la lógica ya usada en `BudgetTechniciansSection.vue` (pool compartido por presupuesto, sin monto individual por técnico):

```
pool_total(budget)      = SUM(budget_concepts.amount) WHERE budget_id = X AND paid_to_technician = true
pagado_por_tecnico       = SUM(technician_payments.amount) WHERE budget_id = X AND user_id = technician.user_id
pendiente_por_tecnico    = pool_total(budget) - pagado_por_tecnico
```

Un ticket aparece en el selector de "Ticket" del formulario de Depósito si:
- El técnico participó en él (`Ticket.technicians` o `Ticket.assistant_technicians` JSON contiene su id, igual que `Technician::scopeWhereInvolved()`), **y**
- Tiene `Budget` asociado, **y**
- `pendiente_por_tecnico > 0` para ese budget.

Implementar en `app/Services/Deposits/DepositService.php`:

```php
class DepositService
{
    public function pendingAmountForTechnician(Technician $technician, Budget $budget): float
    {
        $poolTotal = $budget->concepts()->where('paid_to_technician', true)->sum('amount');

        $alreadyPaid = TechnicianPayment::where('budget_id', $budget->id)
            ->where('user_id', $technician->user_id)
            ->sum('amount');

        return max(0, $poolTotal - $alreadyPaid);
    }

    public function pendingTicketsForTechnician(Technician $technician)
    {
        return Ticket::whereInvolved($technician->id)
            ->whereHas('budget')
            ->with('budget', 'customer')
            ->get()
            ->filter(fn ($ticket) => $this->pendingAmountForTechnician($technician, $ticket->budget) > 0)
            ->values();
    }

    public function defaultShift(): string
    {
        return now()->hour >= 15 ? 'vespertino' : 'matutino';
    }
}
```

> El monto que el usuario captura en el depósito **no se bloquea** si excede el pendiente calculado (puede haber ajustes manuales, redondeos, etc.), pero la UI debe mostrar una advertencia visual (ej. texto en rojo) si `amount > pendiente_por_tecnico`.

### 2.4 Estructuras ya existentes que se reutilizan (sin cambios)

- `technicians` (filtrar `is_internal = false`)
- `technician_bank_accounts` (usar `is_favorite` para preselección)
- `tickets`, `budgets`, `budget_concepts`
- `technician_payments` (destino del registro automático)

---

## 3. Permisos (Spatie)

Nuevos permisos, kebab-case, agregados a la categoría **"Depósitos"** en `RolePermissionController`:

| Permiso | Uso |
|---|---|
| `deposits.index` | Ver el módulo (lista y calendario) |
| `deposits.create` | Programar depósitos |
| `deposits.edit` | Editar depósitos existentes |
| `deposits.delete` | Eliminar depósitos |
| `deposits.approve` | Aprobar depósitos (y recibir la notificación) |
| `deposits.types.manage` | CRUD del catálogo de tipos de depósito |

No se requieren permisos para: generar/copiar enlaces públicos (cualquier usuario autenticado puede hacerlo), ni para marcar como realizado (se hace desde la vista pública sin autenticación).

---

## 4. Rutas

Nuevo archivo `routes/web/deposits.php`, importado desde `routes/web.php` igual que los demás módulos.

```php
use App\Http\Controllers\DepositController;
use App\Http\Controllers\DepositTypeController;
use App\Http\Controllers\PublicDepositController;

// --- Autenticado ---
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::prefix('deposits')->name('deposits.')->group(function () {
        Route::get('/', [DepositController::class, 'index'])->name('index');
        Route::post('/', [DepositController::class, 'store'])->name('store');
        Route::put('/{deposit}', [DepositController::class, 'update'])->name('update');
        Route::delete('/{deposit}', [DepositController::class, 'destroy'])->name('destroy');
        Route::post('/{deposit}/approve', [DepositController::class, 'approve'])->name('approve');

        // Enlaces públicos (permanentes, firmados)
        Route::get('/{deposit}/public-link', [DepositController::class, 'depositLink'])->name('public-link');
        Route::get('/public-link/day/{date}', [DepositController::class, 'dayLink'])->name('day-link');

        // Datos auxiliares para el formulario (JSON)
        Route::get('/technicians/{technician}/bank-accounts', [DepositController::class, 'technicianBankAccounts'])->name('technician-bank-accounts');
        Route::get('/technicians/{technician}/pending-tickets', [DepositController::class, 'pendingTickets'])->name('technician-pending-tickets');

        Route::prefix('types')->name('types.')->group(function () {
            Route::get('/', [DepositTypeController::class, 'index'])->name('index');
            Route::post('/', [DepositTypeController::class, 'store'])->name('store');
            Route::put('/{depositType}', [DepositTypeController::class, 'update'])->name('update');
            Route::delete('/{depositType}', [DepositTypeController::class, 'destroy'])->name('destroy');
        });
    });
});

// --- Público (sin auth, rutas firmadas, sin expiración) ---
Route::prefix('d')->name('public.deposits.')->group(function () {
    Route::get('/{deposit}', [PublicDepositController::class, 'show'])->name('show')->middleware('signed');
    Route::get('/day/{date}', [PublicDepositController::class, 'day'])->name('day')->middleware('signed');
    Route::post('/{deposit}/complete', [PublicDepositController::class, 'complete'])->name('complete')->middleware('signed');
});
```

Generación de enlaces **permanentes** (sin expiración), igual convención que las rutas públicas de técnicos ya existentes (`/t/job-order/...`):

```php
URL::signedRoute('public.deposits.show', ['deposit' => $deposit->id]);
URL::signedRoute('public.deposits.day', ['date' => $date]); // formato Y-m-d
```

> Importante: al usar `signedRoute` sin tercer parámetro de expiración, Laravel genera una firma **permanente** (sin `expires` en la query string). Esto cumple con "válido mientras el depósito no se elimine".

---

## 5. Controladores

### 5.1 `DepositController` (`app/Http/Controllers/`)

- `index()`: retorna Inertia con los depósitos (paginados/lista + eventos para calendario), tipos de depósito activos, y flag de si el usuario puede aprobar.
- `store(StoreDepositRequest $request)`: delega a `DepositService`/Action de creación; dispara notificación a usuarios con `deposits.approve`.
- `update(UpdateDepositRequest $request, Deposit $deposit)`: solo editable si `status != completed`.
- `destroy(Deposit $deposit)`: solo si `status != completed`.
- `approve(Deposit $deposit)`: usa `ApproveDepositAction`.
- `depositLink(Deposit $deposit)`: retorna JSON con la URL firmada.
- `dayLink(Request $request, string $date)`: retorna JSON con la URL firmada para ese día.
- `technicianBankAccounts(Technician $technician)`: JSON de cuentas del técnico, marcando la favorita.
- `pendingTickets(Technician $technician)`: JSON de `DepositService::pendingTicketsForTechnician()`.

### 5.2 `DepositTypeController`

Mismo patrón que `ServiceTypeController` (CRUD JSON, `is_active`), protegido por `deposits.types.manage`.

### 5.3 `PublicDepositController` (sin middleware de auth)

- `show(Deposit $deposit)`: vista Inertia pública. Si `status === 'approved'` o `'completed'` → muestra datos bancarios completos (número de cuenta, CLABE, tarjeta, sucursal) + info del técnico, ticket, monto, tipo, turno, quién aprobó. Si `status === 'pending'` → solo nombre del técnico + leyenda "Este depósito aún no ha sido autorizado. No se puede mostrar la información para realizarlo."
- `day(string $date)`: vista Inertia pública con **todos los depósitos del día** (`scheduled_date = $date`), agrupados/etiquetados por turno (Matutino / Vespertino), cada uno respetando la misma regla de visibilidad según `status`.
- `complete(CompleteDepositRequest $request, Deposit $deposit)`: usa `CompleteDepositAction`. Solo permitido si `status === 'approved'`.

> **Nota sobre filtrado por turno en la vista pública**: mencionaste que esperas indicación del cliente sobre los horarios reales de cada turno para "solo mostrar los del turno en cuestión". Por ahora, la vista `day()` debe mostrar **ambos turnos** (claramente etiquetados), y dejar un `TODO` en el controlador para agregar el filtro automático por hora real del día una vez el cliente confirme los horarios. No agregues configuración de horarios todavía (confirmado: el corte de las 3pm para el *default del formulario* queda fijo en código, sin pantalla de configuración).

---

## 6. Form Requests

`app/Http/Requests/Deposits/`

- **StoreDepositRequest**: valida `technician_id` (exists, `is_internal=false`), `technician_bank_account_id` (exists y pertenece al técnico), `ticket_id` (exists, técnico involucrado), `deposit_type_id` (exists, activo), `amount` (numeric, min:0.01), `shift` (in:matutino,vespertino), `scheduled_date` (date), `notes` (nullable, string).
- **UpdateDepositRequest**: mismas reglas, más regla de negocio en el controlador/Action de que `status != completed`.
- **CompleteDepositRequest**: `commission_amount` (nullable, numeric, min:0), `voucher` (nullable, file, mimes:jpg,jpeg,png,pdf, max:10240).

---

## 7. Services y Actions

### 7.1 `app/Services/Deposits/DepositService.php`
Ya descrito en 2.3: `pendingAmountForTechnician()`, `pendingTicketsForTechnician()`, `defaultShift()`.

### 7.2 `app/Actions/Deposits/`

**ApproveDepositAction**
```php
class ApproveDepositAction
{
    public function execute(Deposit $deposit, User $approver): Deposit
    {
        $deposit->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $deposit;
    }
}
```

**CompleteDepositAction**
```php
class CompleteDepositAction
{
    public function __construct(private ImageOptimizerService $imageOptimizer) {}

    public function execute(Deposit $deposit, array $data): Deposit
    {
        // 1. Crear el pago automático al técnico
        $payment = TechnicianPayment::create([
            'budget_id' => $deposit->budget_id,
            'user_id' => $deposit->technician->user_id,
            'amount' => $deposit->amount,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transferencia',
            'reference' => "Depósito #{$deposit->id}",
            'notes' => "Generado automáticamente desde el módulo de Depósitos.",
        ]);

        // 2. Guardar comisión y comprobante
        $deposit->update([
            'status' => 'completed',
            'completed_at' => now(),
            'commission_amount' => $data['commission_amount'] ?? null,
            'technician_payment_id' => $payment->id,
        ]);

        if (isset($data['voucher'])) {
            $file = $data['voucher'];
            $isImage = str_starts_with($file->getMimeType(), 'image/');

            $media = $deposit->addMedia($file)->toMediaCollection('voucher');

            if ($isImage) {
                $this->imageOptimizer->optimize($media->getPath()); // solo si es imagen, no aplica a PDF
            }
        }

        return $deposit->fresh();
    }
}
```

### 7.3 Notificación de aprobación pendiente

Nueva notificación `app/Notifications/DepositPendingApproval.php` (solo canal `database`, siguiendo el patrón de `TicketNeedsCatalog`/`TicketNeedsInvoice`):

```php
class DepositPendingApproval extends Notification
{
    public function __construct(public Deposit $deposit) {}

    public function via($notifiable): array { return ['database']; }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Nuevo depósito pendiente de aprobación',
            'message' => "Depósito para {$this->deposit->technician->user->name} por $" . number_format($this->deposit->amount, 2),
            'action_url' => route('deposits.index'), // o a un modal específico si se implementa deep-link
        ];
    }
}
```

Dispatch: extender `NotificationService`/`DispatchNotificationAction` (mismo patrón que ya usan Costs/Invoices) para obtener usuarios con permiso `deposits.approve` y notificarlos al crear un depósito:

```php
$approvers = User::permission('deposits.approve')->where('is_active', true)->get();
Notification::send($approvers, new DepositPendingApproval($deposit));
```

Agregar el nuevo tipo (`deposit-pending-approval`) a `notification_settings` para que cada usuario pueda activarlo/desactivarlo desde la pantalla `Config/Notifications` existente.

---

## 8. Frontend (Vue 3 + Inertia + Element Plus)

### 8.1 Estructura de páginas

```
resources/js/Pages/Deposits/
├── Index.vue                     (Tabs: "Lista" | "Calendario", usa ElTabs)
├── Partials/
│   ├── DepositForm.vue           (formulario crear/editar, dentro de ElDialog)
│   ├── DepositListView.vue       (ElTable con filtros: técnico, estatus, turno)
│   ├── DepositCalendarView.vue   (ElCalendar, celda personalizada con eventos por scheduled_date)
│   ├── DepositTypesManager.vue   (ElDialog con CRUD del catálogo, solo si deposits.types.manage)
│   └── ShareLinkDialog.vue       (elegir "por depósito" o "por día", botón copiar al portapapeles)

resources/js/Pages/Public/Deposits/
├── Show.vue                      (layout público, sin sidebar/menú)
├── Day.vue                       (lista del día agrupada por turno)
└── Partials/
    └── CompleteDepositModal.vue  (comisión opcional + comprobante opcional)
```

> Las páginas `Public/Deposits/*` deben usar un layout mínimo propio (sin `AppLayout`/menú lateral), ya que se acceden sin sesión — igual criterio que las vistas públicas de `ticket_tasks` (job-order, evidencia).

### 8.2 `DepositForm.vue` — flujo de captura

1. **Técnico**: `ElSelect` con búsqueda remota sobre `technicians` filtrando `is_internal = false` y `status` activo.
2. Al seleccionar técnico → fetch `deposits.technician-bank-accounts` → `ElSelect` de cuentas, preseleccionando la que tenga `is_favorite = true`. Mostrar en el selector: banco (agregar el nombre del banco a la migración si aún no existe — ver nota 8.3), últimos 4 dígitos de cuenta/tarjeta como referencia visual.
3. Al seleccionar técnico → fetch `deposits.technician-pending-tickets` → `ElSelect` de tickets, mostrando folio, cliente, monto pendiente calculado. Si no hay tickets con pendiente, mostrar mensaje claro ("Este técnico no tiene pagos pendientes registrados").
4. Al seleccionar ticket → mostrar tarjeta de resumen (folio, cliente, sucursal, servicio, monto pendiente del pool, ya pagado a este técnico).
5. **Monto**: input numérico; si excede el pendiente calculado, mostrar advertencia (no bloquear).
6. **Turno**: `ElRadioGroup` Matutino/Vespertino, con default calculado (`DepositService::defaultShift()`) enviado desde el backend al abrir el formulario (o calculado en el frontend con la hora local, pero preferible que el backend lo calcule para consistencia con el server-time).
7. **Tipo de depósito**: `ElSelect` con los `deposit_types` activos + botón "Gestionar tipos" (visible solo con `deposits.types.manage`) que abre `DepositTypesManager.vue`.
8. **Notas** (opcional).

### 8.3 Nota pendiente sobre `technician_bank_accounts`

Confirmaste que la migración no tiene el nombre del banco. Antes de implementar, agrega una migración adicional:

```php
Schema::table('technician_bank_accounts', function (Blueprint $table) {
    $table->string('bank_name')->nullable()->after('technician_id');
});
```

Esto es necesario porque el requerimiento pide "mostrar todos los datos bancarios" al aprobador y en el enlace público, y sin el banco la información queda incompleta.

### 8.4 `DepositCalendarView.vue`

Usa el mismo enfoque que el módulo `Calendar` actual (Element Plus `ElCalendar`, sin librería externa). Cada celda muestra los depósitos de esa `scheduled_date` como chips/tags (color por turno: ej. azul=Matutino, naranja=Vespertino; y un ícono/opacidad distinta si ya está `approved`/`completed`). Click en un depósito abre `DepositForm.vue` en modo edición (si `status != completed`); click en día vacío abre el formulario en modo creación con `scheduled_date` prellenada.

### 8.5 Vista pública `Day.vue`

Agrupar visualmente por turno (Matutino / Vespertino). Por cada depósito:
- Si `approved` o `completed`: nombre del técnico, tipo de depósito, monto, turno, quién aprobó, y **todos los datos bancarios** (banco, número de cuenta, CLABE, número de tarjeta, número de sucursal).
- Si `pending`: solo nombre del técnico + leyenda de "no autorizado".
- Si `approved` (no completado aún): botón "Marcar como realizado" → abre `CompleteDepositModal.vue`.
- Si `completed`: mostrar sello/badge de "Depositado" con fecha/hora, y si se registró comisión, mostrarla (esta vista es útil también para quien revisa, aunque el requerimiento explícito de análisis financiero de comisiones se consulta más bien desde un reporte interno, no público — considera si el comprobante/comisión deben ocultarse en la vista pública o solo mostrarse una vez completado a modo de confirmación).

### 8.6 `CompleteDepositModal.vue`

Formulario simple: `commission_amount` (opcional, numérico) y `voucher` (opcional, `ElUpload` con drag&drop, acepta imagen o PDF). Al confirmar, `POST` a la ruta firmada `public.deposits.complete`.

---

## 9. Resumen del flujo de estados

```
[pending] --(1 usuario con deposits.approve aprueba)--> [approved] --(marcar como realizado, vía enlace público)--> [completed]
   |                                                         |
   |-- editable/eliminable (deposits.edit / deposits.delete) -- editable/eliminable --
                                                              |
                                                    genera TechnicianPayment automático
```

No existe transición de rechazo: un depósito `pending` permanece así hasta que se apruebe o se elimine.

---

## 10. Checklist de implementación sugerido (orden)

1. Migraciones: `deposit_types`, `deposits`, alter `technician_bank_accounts` (agregar `bank_name`), alter `deposits` para `scheduled_date`.
2. Modelos: `DepositType`, `Deposit` (con relaciones, scopes, media collection).
3. Seeder de `deposit_types` (Anticipo, Finiquito, Visita, Extras, Pago único).
4. Permisos Spatie + categoría "Depósitos" en `RolePermissionController`.
5. `DepositService` (cálculo de pendientes, turno default).
6. Form Requests (`StoreDepositRequest`, `UpdateDepositRequest`, `CompleteDepositRequest`).
7. Actions (`ApproveDepositAction`, `CompleteDepositAction`).
8. Notificación `DepositPendingApproval` + integración con `NotificationService`/`DispatchNotificationAction` + entrada en `notification_settings`.
9. Controladores (`DepositController`, `DepositTypeController`, `PublicDepositController`).
10. Rutas (`routes/web/deposits.php` + import en `routes/web.php`).
11. Frontend autenticado: `Index.vue`, `DepositForm.vue`, `DepositListView.vue`, `DepositCalendarView.vue`, `DepositTypesManager.vue`, `ShareLinkDialog.vue`.
12. Frontend público: `Show.vue`, `Day.vue`, `CompleteDepositModal.vue` (+ layout público mínimo).
13. Pruebas manuales del flujo completo: crear → notificar → aprobar → compartir enlace → marcar realizado → verificar `technician_payments` generado.

---

## 11. Puntos abiertos / a decidir más adelante (no bloquean el desarrollo actual)

- Horarios reales de turno Matutino/Vespertino para filtrar automáticamente la vista pública por turno (pendiente de indicación del cliente). Por ahora se muestran ambos turnos etiquetados.
- Si en el futuro se requiere un monto presupuestado **individual** por técnico (no pool compartido), habrá que decidir entre agregar `technician_user_id` a `budget_concepts` o una tabla pivote — esto afectaría también al módulo de Budgets, no solo Depósitos.
