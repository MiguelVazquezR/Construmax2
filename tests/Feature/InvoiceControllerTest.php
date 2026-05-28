<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_active' => true]);
        Permission::create(['name' => 'invoices.index', 'guard_name' => 'web', 'category' => 'Invoices', 'description' => 'Access invoices']);
        Permission::create(['name' => 'invoices.upload', 'guard_name' => 'web', 'category' => 'Invoices', 'description' => 'Upload invoices']);
        $this->user->givePermissionTo(['invoices.index', 'invoices.upload']);
    }

    // --- index ---

    public function test_index_renders_invoices_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('invoices.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Invoices/Index')
                ->has('invoices')
            );
    }

    public function test_index_forbidden_without_permission(): void
    {
        $noPermUser = User::factory()->create();

        $this->actingAs($noPermUser)
            ->get(route('invoices.index'))
            ->assertForbidden();
    }

    // --- upload ---

    public function test_upload_requires_file(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'Ejecutado']);
        $budget = Budget::factory()->create(['ticket_id' => $ticket->id]);

        $this->actingAs($this->user)
            ->post(route('invoices.upload', $budget))
            ->assertSessionHasErrors(['invoice_date', 'invoice_number', 'file']);
    }

    public function test_upload_attaches_invoice_file(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'Ejecutado']);
        $budget = Budget::factory()->create(['ticket_id' => $ticket->id]);

        $file = UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)
            ->post(route('invoices.upload', $budget), [
                'invoice_date' => now()->toDateString(),
                'invoice_number' => 'F-ABC-001',
                'file' => $file,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('budgets', [
            'id' => $budget->id,
            'invoice_number' => 'F-ABC-001',
        ]);
    }
}
