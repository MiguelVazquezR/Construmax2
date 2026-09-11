<?php

namespace Tests\Feature\Notifications;

use App\Models\Budget;
use App\Models\BudgetCatalog;
use App\Models\User;
use App\Notifications\CatalogNeedsUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogNeedsUpdateNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'costs.receive-catalog-update-notifications', 'guard_name' => 'web', 'category' => 'Costos', 'description' => 'Recibir notificaciones cuando un presupuesto actualizado requiere un nuevo catálogo']);
        Permission::create(['name' => 'costs.create', 'guard_name' => 'web', 'category' => 'Costos', 'description' => 'Crear versiones de catálogo de costos']);
        Permission::create(['name' => 'costs.approve', 'guard_name' => 'web', 'category' => 'Costos', 'description' => 'Aprobar catálogos de costos']);
    }

    /**
     * Build a valid budget update payload.
     */
    private function updatePayload(Budget $budget, User $user, bool $sendToCosts = true): array
    {
        return [
            'ticket_id'     => $budget->ticket_id,
            'description'   => 'Presupuesto actualizado',
            'currency'      => 'MXN',
            'exchange_rate' => 1.0000,
            'user_id'       => $user->id,
            'concepts'      => [
                ['concept' => 'Material', 'amount' => 1500],
            ],
            'send_to_costs' => $sendToCosts,
        ];
    }

    /**
     * Create a budget (with its ticket) and a catalog in the given status.
     *
     * @return array{0: Budget, 1: BudgetCatalog}
     */
    private function makeBudgetWithCatalog(string $catalogStatus): array
    {
        $budget = Budget::factory()->create();
        $budget->ticket->update(['status' => 'Proceso de ejecución']);

        $catalog = BudgetCatalog::factory()->create([
            'budget_id' => $budget->id,
            'status'    => $catalogStatus,
        ]);

        return [$budget, $catalog];
    }

    public function test_users_with_the_notification_permission_receive_the_notification_when_a_new_catalog_is_needed(): void
    {
        Notification::fake();

        $notified = User::factory()->create(['is_active' => true]);
        $notified->givePermissionTo('costs.receive-catalog-update-notifications');

        $creator = User::factory()->create(['is_active' => true]);
        $creator->givePermissionTo('costs.create');

        $approver = User::factory()->create(['is_active' => true]);
        $approver->givePermissionTo('costs.approve');

        $outsider = User::factory()->create(['is_active' => true]);

        $inactiveNotified = User::factory()->create(['is_active' => false]);
        $inactiveNotified->givePermissionTo('costs.receive-catalog-update-notifications');

        [$budget, $catalog] = $this->makeBudgetWithCatalog(BudgetCatalog::STATUS_APPROVED);

        $this->actingAs($creator)
            ->put(route('budgets.update', $budget), $this->updatePayload($budget, $creator))
            ->assertRedirect(route('budgets.show', $budget->id));

        // The catalog is invalidated: a new version must be saved
        $this->assertDatabaseHas('budget_catalogs', [
            'id'     => $catalog->id,
            'status' => BudgetCatalog::STATUS_PENDING_UPDATE,
        ]);

        Notification::assertSentTo(
            $notified,
            CatalogNeedsUpdate::class,
            fn (CatalogNeedsUpdate $notification, array $channels) => in_array('database', $channels, true)
                && $notification->catalog->id === $catalog->id
        );
        Notification::assertNotSentTo($creator, CatalogNeedsUpdate::class);
        Notification::assertNotSentTo($approver, CatalogNeedsUpdate::class);
        Notification::assertNotSentTo($outsider, CatalogNeedsUpdate::class);
        Notification::assertNotSentTo($inactiveNotified, CatalogNeedsUpdate::class);
    }

    public function test_users_with_a_role_granting_the_notification_permission_receive_the_notification(): void
    {
        Notification::fake();

        $role = Role::create(['name' => 'Costos', 'guard_name' => 'web']);
        $role->givePermissionTo('costs.receive-catalog-update-notifications');

        $member = User::factory()->create(['is_active' => true]);
        $member->assignRole($role);

        [$budget] = $this->makeBudgetWithCatalog(BudgetCatalog::STATUS_APPROVED);

        $this->actingAs($member)
            ->put(route('budgets.update', $budget), $this->updatePayload($budget, $member))
            ->assertRedirect();

        Notification::assertSentTo($member, CatalogNeedsUpdate::class);
    }

    public function test_budget_update_without_send_to_costs_does_not_notify(): void
    {
        Notification::fake();

        $notified = User::factory()->create(['is_active' => true]);
        $notified->givePermissionTo('costs.receive-catalog-update-notifications');

        [$budget, $catalog] = $this->makeBudgetWithCatalog(BudgetCatalog::STATUS_APPROVED);

        $this->actingAs($notified)
            ->put(route('budgets.update', $budget), $this->updatePayload($budget, $notified, false))
            ->assertRedirect();

        $this->assertDatabaseHas('budget_catalogs', [
            'id'     => $catalog->id,
            'status' => BudgetCatalog::STATUS_APPROVED,
        ]);

        Notification::assertNothingSent();
    }

    public function test_already_pending_update_catalog_does_not_notify_again(): void
    {
        Notification::fake();

        $notified = User::factory()->create(['is_active' => true]);
        $notified->givePermissionTo('costs.receive-catalog-update-notifications');

        [$budget, $catalog] = $this->makeBudgetWithCatalog(BudgetCatalog::STATUS_PENDING_UPDATE);

        $this->actingAs($notified)
            ->put(route('budgets.update', $budget), $this->updatePayload($budget, $notified))
            ->assertRedirect();

        $this->assertDatabaseHas('budget_catalogs', [
            'id'     => $catalog->id,
            'status' => BudgetCatalog::STATUS_PENDING_UPDATE,
        ]);

        Notification::assertNothingSent();
    }
}
