<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkAcceptanceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkAcceptanceReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_active' => true]);
        Permission::create(['name' => 'tickets.index', 'guard_name' => 'web', 'category' => 'Tickets', 'description' => 'View tickets']);
        $this->user->givePermissionTo('tickets.index');
    }

    // --- store (generate report) ---

    public function test_store_creates_work_acceptance_report(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->user)
            ->post(route('work-acceptance-reports.store'), [
                'ticket_id' => $ticket->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('work_acceptance_reports', [
            'ticket_id' => $ticket->id,
            'is_signed' => false,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_store_is_idempotent(): void
    {
        $ticket = Ticket::factory()->create();

        // First call creates the report
        $this->actingAs($this->user)
            ->post(route('work-acceptance-reports.store'), [
                'ticket_id' => $ticket->id,
            ]);

        // Second call returns the existing one (no duplicate)
        $this->actingAs($this->user)
            ->post(route('work-acceptance-reports.store'), [
                'ticket_id' => $ticket->id,
            ]);

        $this->assertEquals(1, WorkAcceptanceReport::where('ticket_id', $ticket->id)->count());
    }

    public function test_store_validates_ticket_exists(): void
    {
        $this->actingAs($this->user)
            ->post(route('work-acceptance-reports.store'), [
                'ticket_id' => 9999,
            ])
            ->assertSessionHasErrors('ticket_id');
    }

    // --- show (internal view) ---

    public function test_show_displays_report(): void
    {
        $ticket = Ticket::factory()->create();
        $report = WorkAcceptanceReport::factory()->create([
            'ticket_id' => $ticket->id,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('work-acceptance-reports.show', $report))
            ->assertInertia(fn ($page) => $page
                ->component('WorkAcceptanceReports/Show')
                ->has('report')
                ->has('technicianNames')
            );
    }

    // --- public show (replaced separate sign page) ---

    public function test_public_show_is_accessible_with_signed_url(): void
    {
        $ticket = Ticket::factory()->create();
        $report = WorkAcceptanceReport::factory()->create([
            'ticket_id' => $ticket->id,
            'is_signed' => false,
            'created_by' => $this->user->id,
        ]);

        $url = \Illuminate\Support\Facades\URL::signedRoute('work-acceptance-reports.public.show', [
            'report' => $report->id,
        ]);

        $this->get($url)
            ->assertInertia(fn ($page) => $page
                ->component('WorkAcceptanceReports/Show')
                ->has('report')
            );
    }

    public function test_store_signature_on_already_signed_report_returns_back(): void
    {
        $ticket = Ticket::factory()->create();
        $report = WorkAcceptanceReport::factory()->create([
            'ticket_id' => $ticket->id,
            'is_signed' => true,
            'signed_at' => now(),
            'signatory_name' => 'Test Signer',
            'signature_data' => 'data:image/png;base64,test',
            'created_by' => $this->user->id,
        ]);

        // Internal sign route
        $this->actingAs($this->user)
            ->post(route('work-acceptance-reports.sign', $report), [
                'signature_data' => 'data:image/png;base64,new',
                'signatory_name' => 'Second Signer',
            ])
            ->assertRedirect();

        $report->refresh();
        $this->assertEquals('Test Signer', $report->signatory_name);
    }

    // --- storeSignature ---

    public function test_store_signature_locks_report(): void
    {
        $ticket = Ticket::factory()->create();
        $report = WorkAcceptanceReport::factory()->create([
            'ticket_id' => $ticket->id,
            'is_signed' => false,
            'created_by' => $this->user->id,
        ]);

        $url = \Illuminate\Support\Facades\URL::signedRoute('work-acceptance-reports.public.store-signature', [
            'report' => $report->id,
        ]);

        $this->post($url, [
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'signatory_name' => 'Juan Pérez',
            'manager_name' => 'Gerente Sucursal',
            'client_comments' => 'Todo en orden.',
        ])->assertRedirect();

        $report->refresh();

        $this->assertTrue($report->is_signed);
        $this->assertNotNull($report->signed_at);
        $this->assertEquals('Juan Pérez', $report->signatory_name);
        $this->assertEquals('Todo en orden.', $report->client_comments);
        $this->assertNotEmpty($report->signature_data);
    }

    public function test_store_signature_prevents_double_signing(): void
    {
        $ticket = Ticket::factory()->create();
        $report = WorkAcceptanceReport::factory()->create([
            'ticket_id' => $ticket->id,
            'is_signed' => true,
            'signed_at' => now(),
            'signatory_name' => 'First Signer',
            'signature_data' => 'data:image/png;base64,original',
            'created_by' => $this->user->id,
        ]);

        $url = \Illuminate\Support\Facades\URL::signedRoute('work-acceptance-reports.public.store-signature', [
            'report' => $report->id,
        ]);

        $this->post($url, [
            'signature_data' => 'data:image/png;base64,new',
            'signatory_name' => 'Second Signer',
        ])->assertRedirect();

        $report->refresh();

        // Data should remain unchanged
        $this->assertEquals('First Signer', $report->signatory_name);
    }

    public function test_store_signature_validates_required_fields(): void
    {
        $ticket = Ticket::factory()->create();
        $report = WorkAcceptanceReport::factory()->create([
            'ticket_id' => $ticket->id,
            'is_signed' => false,
            'created_by' => $this->user->id,
        ]);

        $url = \Illuminate\Support\Facades\URL::signedRoute('work-acceptance-reports.public.store-signature', [
            'report' => $report->id,
        ]);

        $this->post($url, [
            'signature_data' => '',
            'signatory_name' => '',
        ])->assertSessionHasErrors(['signature_data', 'signatory_name']);
    }

    // --- model: lock mechanism ---

    public function test_lock_sets_is_signed_and_timestamp(): void
    {
        $report = WorkAcceptanceReport::factory()->create([
            'is_signed' => false,
            'signed_at' => null,
            'created_by' => $this->user->id,
        ]);

        $this->assertFalse($report->isSigned());

        $report->lock();

        $report->refresh();
        $this->assertTrue($report->is_signed);
        $this->assertNotNull($report->signed_at);
    }

    // --- relationship: ticket ---

    public function test_ticket_has_one_work_acceptance_report(): void
    {
        $ticket = Ticket::factory()->create();
        $report = WorkAcceptanceReport::factory()->create([
            'ticket_id' => $ticket->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(WorkAcceptanceReport::class, $ticket->workAcceptanceReport);
        $this->assertEquals($report->id, $ticket->workAcceptanceReport->id);
    }

    public function test_report_belongs_to_ticket(): void
    {
        $ticket = Ticket::factory()->create();
        $report = WorkAcceptanceReport::factory()->create([
            'ticket_id' => $ticket->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(Ticket::class, $report->ticket);
        $this->assertEquals($ticket->id, $report->ticket->id);
    }

    // --- auto-population from tasks ---

    public function test_create_report_action_populates_from_tasks(): void
    {
        $ticket = Ticket::factory()->create();

        // Create some tasks with data
        $ticket->tasks()->createMany([
            [
                'user_id' => $this->user->id,
                'name' => 'Instalación de cableado',
                'technician_notes' => 'Completado sin incidencias.',
                'status' => 'Completada',
                'start_date' => now()->subDays(2),
                'due_date' => now()->subDay(),
            ],
            [
                'user_id' => $this->user->id,
                'name' => 'Pruebas de conectividad',
                'technician_notes' => 'Todo funciona correctamente.',
                'status' => 'Completada',
                'start_date' => now()->subDay(),
                'due_date' => now(),
            ],
        ]);

        $action = app(\App\Actions\WorkAcceptanceReports\CreateWorkAcceptanceReportAction::class);
        $report = $action->execute($ticket);

        $this->assertNull($report->work_description, 'Work description should start blank so the technician fills it in.');
        $this->assertStringContainsString('Completado sin incidencias', $report->technician_comments);
        $this->assertStringContainsString('Todo funciona correctamente', $report->technician_comments);
        $this->assertNotNull($report->on_site_start);
        $this->assertNotNull($report->on_site_end);
        $this->assertNotNull($report->report_date);
    }
}
