<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerBranch;
use App\Models\CustomerContact;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_active' => true]);
    }

    // --- index ---

    public function test_index_renders_tickets_page(): void
    {
        Ticket::factory()->count(3)->create();

        $this->actingAs($this->user)
            ->get(route('tickets.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Tickets/Index')
                ->has('tickets.data', 3)
            );
    }

    public function test_index_filters_by_customer(): void
    {
        $customer = Customer::factory()->create();
        Ticket::factory()->create(['customer_id' => $customer->id]);
        Ticket::factory()->create();

        $this->actingAs($this->user)
            ->get(route('tickets.index', ['customer' => $customer->id]))
            ->assertInertia(fn ($page) => $page
                ->has('tickets.data', 1)
            );
    }

    public function test_index_filters_by_folio(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->user)
            ->get(route('tickets.index', ['folio' => (string) $ticket->id]))
            ->assertInertia(fn ($page) => $page
                ->has('tickets.data', 1)
            );
    }

    // --- create ---

    public function test_create_renders_form(): void
    {
        $this->actingAs($this->user)
            ->get(route('tickets.create'))
            ->assertInertia(fn ($page) => $page
                ->component('Tickets/Create')
                ->has('users')
                ->has('customers')
                ->has('templates')
            );
    }

    // --- store ---

    public function test_store_creates_ticket(): void
    {
        $customer = Customer::factory()->create();
        $contact = CustomerContact::factory()->create(['customer_id' => $customer->id]);
        CustomerBranch::factory()->create(['customer_id' => $customer->id]);

        $data = [
            'customer_id' => $customer->id,
            'customer_contact_id' => $contact->id,
            'name' => 'Repair AC unit',
            'service_type' => 'Aire acondicionado',
            'priority' => 'Alta',
        ];

        $this->actingAs($this->user)
            ->post(route('tickets.store'), $data)
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'customer_id' => $customer->id,
            'name' => 'Repair AC unit',
            'status' => 'Borrador',
        ]);
    }

    public function test_store_fails_validation_without_required_fields(): void
    {
        $this->actingAs($this->user)
            ->post(route('tickets.store'), [])
            ->assertSessionHasErrors([
                'customer_id', 'customer_contact_id', 'name',
                'service_type', 'priority',
            ]);
    }

    // --- show ---

    public function test_show_displays_ticket(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->user)
            ->get(route('tickets.show', $ticket))
            ->assertInertia(fn ($page) => $page
                ->component('Tickets/Show')
                ->has('ticket')
            );
    }

    // --- updateStatus ---

    public function test_update_status_changes_ticket_status(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'Borrador']);

        $this->actingAs($this->user)
            ->put(route('tickets.update-status', $ticket), ['status' => 'Levantamiento'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => 'Levantamiento',
        ]);
    }

    public function test_update_status_allows_por_programar_for_jalisco_tickets(): void
    {
        $customer = Customer::factory()->create();
        $branch = CustomerBranch::factory()->create(['customer_id' => $customer->id, 'region' => 'jalisco']);
        $ticket = Ticket::factory()->create(['status' => 'Borrador', 'customer_branch_id' => $branch->id]);

        $this->actingAs($this->user)
            ->put(route('tickets.update-status', $ticket), ['status' => 'Por programar'])
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => 'Por programar',
        ]);
    }

    public function test_update_status_blocks_por_programar_for_non_jalisco_tickets(): void
    {
        $customer = Customer::factory()->create();
        $branch = CustomerBranch::factory()->create(['customer_id' => $customer->id, 'region' => 'Nuevo León']);
        $ticket = Ticket::factory()->create(['status' => 'Borrador', 'customer_branch_id' => $branch->id]);

        $this->actingAs($this->user)
            ->put(route('tickets.update-status', $ticket), ['status' => 'Por programar'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => 'Borrador',
        ]);
    }

    // --- edit ---

    public function test_edit_renders_form(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->user)
            ->get(route('tickets.edit', $ticket))
            ->assertInertia(fn ($page) => $page
                ->component('Tickets/Edit')
                ->has('ticket')
            );
    }

    // --- update ---

    public function test_update_modifies_ticket(): void
    {
        $ticket = Ticket::factory()->create(['name' => 'Old name']);
        $customer = Customer::factory()->create();
        $contact = CustomerContact::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($this->user)
            ->put(route('tickets.update', $ticket), [
                'customer_id' => $customer->id,
                'customer_contact_id' => $contact->id,
                'name' => 'Updated name',
                'service_type' => 'Pintura',
                'priority' => 'Media',
                'status' => 'Levantamiento',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'name' => 'Updated name',
        ]);
    }

    // --- destroy ---

    public function test_destroy_deletes_ticket(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('tickets.destroy', $ticket))
            ->assertRedirect(route('tickets.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
    }

    // --- export ---

    public function test_export_downloads_an_xlsx_report_with_the_applied_filters(): void
    {
        $matching = Ticket::factory()->create([
            'seller_id' => $this->user->id,
            'status' => 'Borrador',
            'priority' => 'Alta',
            'name' => 'Instalación de anuncio',
        ]);

        Ticket::factory()->create([
            'seller_id' => $this->user->id,
            'status' => 'Borrador',
            'priority' => 'Baja',
            'name' => 'Ticket fuera del filtro',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('tickets.export', ['priority' => 'Alta']));

        $response->assertOk();
        $response->assertDownload('reporte-tickets-' . now()->format('Y-m-d') . '.xlsx');
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );

        $sheet = $this->sheetXmlFrom($response);

        // Only the ticket matching the priority filter is exported.
        $this->assertStringContainsString('>' . $matching->folio . '</t>', $sheet);
        $this->assertStringNotContainsString('>Ticket fuera del filtro</t>', $sheet);
    }

    public function test_export_uses_default_active_statuses_when_no_status_filter_is_sent(): void
    {
        Ticket::factory()->withStatus('Borrador')->create(['seller_id' => $this->user->id]);
        Ticket::factory()->withStatus('Cancelado')->create(['seller_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('tickets.export'));

        $response->assertOk();

        $sheet = $this->sheetXmlFrom($response);

        $this->assertStringContainsString('>Borrador</t>', $sheet);
        $this->assertStringNotContainsString('>Cancelado</t>', $sheet);
    }

    public function test_export_includes_every_status_when_all_is_selected(): void
    {
        Ticket::factory()->withStatus('Cancelado')->create(['seller_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->get(route('tickets.export', ['status' => ['all']]));

        $response->assertOk();

        $sheet = $this->sheetXmlFrom($response);

        $this->assertStringContainsString('>Cancelado</t>', $sheet);
    }

    public function test_export_only_includes_the_acting_seller_tickets(): void
    {
        Ticket::factory()->create([
            'seller_id' => $this->user->id,
            'status' => 'Borrador',
            'name' => 'Ticket propio',
        ]);

        Ticket::factory()->create([
            'status' => 'Borrador',
            'name' => 'Ticket de otro asesor',
        ]);

        $response = $this->actingAs($this->user)->get(route('tickets.export'));

        $response->assertOk();

        $sheet = $this->sheetXmlFrom($response);

        $this->assertStringContainsString('>Ticket propio</t>', $sheet);
        $this->assertStringNotContainsString('>Ticket de otro asesor</t>', $sheet);
    }

    /**
     * Read the generated sheet XML from a download response.
     */
    private function sheetXmlFrom($response): string
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertNotFalse($sheet);

        return $sheet;
    }
}
