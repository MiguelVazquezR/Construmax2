# ERP — Project guidelines for deepseek

## Stack

- **Backend:** Laravel 12, PHP 8.3+
- **Frontend:** Vue 3 (Composition API + `<script setup>`), Inertia.js 2, Vite
- **UI library:** Element Plus (always use its components — never build from scratch what Element Plus already provides)
- **Auth & permissions:** Laravel Sanctum, Spatie Laravel Permission
- **Styling:** Tailwind CSS (utility classes only to complement Element Plus, never to replace it)


---

## General principles

- All code, comments, variable names, class names, methods, routes, and files must be written in **English**
- All user-facing text (labels, placeholders, messages, tooltips) must use **sentence case** — never title case or all caps
  - ✅ `"Save changes"`, `"This field is required"`, `"Service orders"`
  - ❌ `"Save Changes"`, `"This Field Is Required"`, `"Service Orders"`
- Follow **SOLID principles** strictly:
  - Single responsibility: one class, one purpose
  - Open/closed: extend behavior without modifying existing code
  - Liskov substitution: subtypes must be replaceable for their base types
  - Interface segregation: prefer small, focused interfaces
  - Dependency inversion: depend on abstractions, not concretions
- Prefer **explicit over implicit** code — clarity over cleverness
- No commented-out dead code — delete it


---

## Architecture

### Layer responsibilities

```
HTTP Request
  → Controller       (thin — delegates immediately)
  → Action           (single-use case orchestrator)
  → Service          (reusable business logic)
  → Model            (rich domain logic, scopes, relationships, mutators)
  → FormRequest      (all validation lives here)
```

### Controllers — keep them thin

- One responsibility per method: receive request, call action or service, return response
- No business logic, no queries, no calculations
- Always use Form Requests for validation
- Always use Inertia responses or JSON responses — never blade views

```php
// ✅ Correct
public function store(StoreServiceOrderRequest $request): RedirectResponse
{
    $this->createServiceOrderAction->execute($request->validated());

    return redirect()->route('service-orders.index');
}

// ❌ Wrong — business logic in controller
public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([...]);
    $order = ServiceOrder::create($validated);
    $order->notify();
    return redirect()->route('service-orders.index');
}
```

### Actions — single use cases

- One action per use case: `CreateServiceOrderAction`, `ApproveInvoiceAction`
- Located in `app/Actions/{Module}/`
- Must have a single public method: `execute(array $data): mixed`
- Can call multiple services, fire events, dispatch jobs

```php
namespace App\Actions\ServiceOrders;

class CreateServiceOrderAction
{
    public function __construct(
        private readonly ServiceOrderService $serviceOrderService,
        private readonly NotificationService $notificationService,
    ) {}

    public function execute(array $data): ServiceOrder
    {
        $order = $this->serviceOrderService->create($data);
        $this->notificationService->notifyAssignedTechnician($order);

        return $order;
    }
}
```

### Services — reusable business logic

- Located in `app/Services/{Module}/`
- Handle reusable operations shared across multiple actions
- May interact with models, external APIs, or other services
- Must be injected via constructor (dependency inversion)

```php
namespace App\Services\ServiceOrders;

class ServiceOrderService
{
    public function create(array $data): ServiceOrder
    {
        return ServiceOrder::create($data);
    }

    public function calculateTotal(ServiceOrder $order): float
    {
        return $order->items->sum(fn($item) => $item->quantity * $item->unit_price);
    }
}
```

### Models — rich and expressive

- Define all relationships, scopes, accessors, mutators, and casts here
- Use `$fillable` explicitly — never `$guarded = []`
- Define casts for dates, booleans, enums, and JSON fields
- Add query scopes for all common filters

```php
class ServiceOrder extends Model
{
    protected $fillable = [
        'customer_id', 'assigned_to', 'status', 'scheduled_at', 'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'status'       => ServiceOrderStatus::class,
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class);
    }

    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ServiceOrderStatus::Pending);
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }
}
```

---

## Form requests

- **All validation lives in Form Requests** — never `$request->validate()` inside controllers
- Located in `app/Http/Requests/{Module}/`
- Always implement `authorize()` using Spatie permissions or policies
- Use `prepareForValidation()` to normalize data before validation

```php
namespace App\Http\Requests\ServiceOrders;

class StoreServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create service-orders');
    }

    public function rules(): array
    {
        return [
            'customer_id'   => ['required', 'integer', 'exists:customers,id'],
            'scheduled_at'  => ['required', 'date', 'after:now'],
            'notes'         => ['nullable', 'string', 'max:1000'],
            'items'         => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Please select a customer.',
            'scheduled_at.after'   => 'The scheduled date must be in the future.',
            'items.required'       => 'Add at least one item to the order.',
        ];
    }
}
```

---

## Routes

- All route files in `routes/web/` use **kebab-case with hyphens** for URL segments
  - ✅ `service-orders.php`, `purchase-orders.php`, `work-orders.php`
  - ❌ `serviceOrders.php`, `service_orders.php`
- Use **named routes** always, following the same kebab-case pattern
- Group routes by module and apply middleware

```php
// routes/service-orders.php
Route::middleware(['auth', 'verified'])->prefix('service-orders')->name('service-orders.')->group(function () {
    Route::get('/', [ServiceOrderController::class, 'index'])->name('index');
    Route::get('/create', [ServiceOrderController::class, 'create'])->name('create');
    Route::post('/', [ServiceOrderController::class, 'store'])->name('store');
    Route::get('/{serviceOrder}', [ServiceOrderController::class, 'show'])->name('show');
    Route::get('/{serviceOrder}/edit', [ServiceOrderController::class, 'edit'])->name('edit');
    Route::put('/{serviceOrder}', [ServiceOrderController::class, 'update'])->name('update');
    Route::delete('/{serviceOrder}', [ServiceOrderController::class, 'destroy'])->name('destroy');
});
```

---

## Vue 3 frontend

### General rules

- Always use `<script setup>` with Composition API — never Options API
- Use `defineProps` and `defineEmits` with TypeScript-style type annotations
- Keep components focused — if a component exceeds ~200 lines, split it
- Use Element Plus components for all UI: forms, tables, dialogs, buttons, inputs, selects, date pickers, etc.
- Never build custom form inputs, modals, or tables when Element Plus already provides them

### Component naming

- Pages: `PascalCase` in `resources/js/Pages/{Module}/`
  - `resources/js/Pages/ServiceOrders/Index.vue`
  - `resources/js/Pages/ServiceOrders/Create.vue`
- Reusable components: `resources/js/Components/{Category}/`
  - `resources/js/Components/Forms/CustomerSelect.vue`
  - `resources/js/Components/Tables/StatusBadge.vue`

### Inertia patterns

- Use `useForm()` for all forms — never plain `axios.post()`
- Use `router.visit()` for programmatic navigation
- Pass only the data the page needs from the controller — avoid over-fetching

```vue
<script setup>
import { useForm } from '@inertiajs/vue3'
import { ElMessage } from 'element-plus'

const props = defineProps({
  customers: Array,
})

const form = useForm({
  customer_id: null,
  scheduled_at: '',
  notes: '',
  items: [],
})

function submit() {
  form.post(route('service-orders.store'), {
    onSuccess: () => ElMessage.success('Service order created successfully.'),
  })
}
</script>
```

### Element Plus usage

- Use `ElForm` + `ElFormItem` for all forms with proper `:model` and `:rules`
- Use `ElTable` + `ElTableColumn` for all data tables
- Use `ElDialog` for modals — never custom overlays
- Use `ElMessage` for toast notifications (success, error, warning, info)
- Use `ElMessageBox` for confirmation dialogs before destructive actions
- Always set `size="default"` on forms unless explicitly asked for another size

```vue
<!-- ✅ Correct: using Element Plus components -->
<el-form :model="form" label-position="top">
  <el-form-item label="Customer" prop="customer_id">
    <el-select v-model="form.customer_id" placeholder="Select a customer" class="w-full">
      <el-option
        v-for="customer in customers"
        :key="customer.id"
        :label="customer.name"
        :value="customer.id"
      />
    </el-select>
  </el-form-item>

  <el-form-item label="Scheduled date" prop="scheduled_at">
    <el-date-picker v-model="form.scheduled_at" type="datetime" class="w-full" />
  </el-form-item>

  <el-form-item>
    <el-button type="primary" :loading="form.processing" @click="submit">
      Save service order
    </el-button>
  </el-form-item>
</el-form>
```

---

## Permissions (Spatie)

- Define all permissions using kebab-case: `create service-orders`, `edit invoices`, `delete customers`
- Always check permissions in Form Requests (`authorize()`) and in Vue with a `can` prop passed from the controller
- Never hardcode role checks in controllers — use permissions, not roles

```php
// ✅ Check permission
$this->user()->can('create service-orders');

// ❌ Check role directly
$this->user()->hasRole('admin');
```

---

## File & folder structure

```
app/
├── Actions/
│   └── ServiceOrders/
│       ├── CreateServiceOrderAction.php
│       └── UpdateServiceOrderAction.php
├── Http/
│   ├── Controllers/
│   │   └── ServiceOrders/
│   │       └── ServiceOrderController.php
│   └── Requests/
│       └── ServiceOrders/
│           ├── StoreServiceOrderRequest.php
│           └── UpdateServiceOrderRequest.php
├── Models/
│   └── ServiceOrder.php
├── Services/
│   └── ServiceOrders/
│       └── ServiceOrderService.php

resources/js/
├── Pages/
│   └── ServiceOrders/
│       ├── Index.vue
│       ├── Create.vue
│       ├── Edit.vue
│       └── Show.vue
├── Components/
│   ├── Forms/
│   └── Tables/

routes/web/
├── service-orders.php
├── customers.php
└── invoices.php
```

---

## What to avoid

- ❌ Business logic inside controllers
- ❌ `$request->validate()` inside controllers — always use Form Requests
- ❌ `$guarded = []` in models — always define `$fillable`
- ❌ Raw queries when Eloquent scopes can do the job
- ❌ Options API in Vue — always use `<script setup>`
- ❌ Custom modal or form components when Element Plus provides them
- ❌ Title Case in user-facing text — always sentence case
- ❌ Checking roles directly — always check permissions
- ❌ Spanish variable names, method names, or comments — all code in English
