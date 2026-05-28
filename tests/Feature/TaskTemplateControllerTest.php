<?php

namespace Tests\Feature;

use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_active' => true]);
    }

    // --- store ---

    public function test_store_creates_template_with_items(): void
    {
        $data = [
            'name' => 'Standard installation',
            'description' => 'A standard installation template',
            'items' => [
                ['name' => 'Site inspection', 'description' => 'Inspect the site'],
                ['name' => 'Installation', 'description' => 'Perform installation'],
            ],
        ];

        $this->actingAs($this->user)
            ->post(route('task-templates.store'), $data)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('task_templates', ['name' => 'Standard installation']);
        $this->assertDatabaseHas('task_template_items', ['name' => 'Site inspection']);
        $this->assertDatabaseCount('task_template_items', 2);
    }

    public function test_store_fails_without_items(): void
    {
        $this->actingAs($this->user)
            ->post(route('task-templates.store'), [
                'name' => 'Empty template',
                'items' => [],
            ])
            ->assertSessionHasErrors('items');
    }

    public function test_store_requires_name(): void
    {
        $this->actingAs($this->user)
            ->post(route('task-templates.store'), [])
            ->assertSessionHasErrors('name');
    }

    // --- update ---

    public function test_update_modifies_template(): void
    {
        $template = TaskTemplate::factory()->create(['name' => 'Old Template']);
        $template->items()->create(['name' => 'Old item', 'description' => 'Old desc']);

        $data = [
            'name' => 'Updated Template',
            'description' => 'Updated description',
            'items' => [
                ['name' => 'New item 1', 'description' => 'New desc 1'],
                ['name' => 'New item 2', 'description' => 'New desc 2'],
            ],
        ];

        $this->actingAs($this->user)
            ->put(route('task-templates.update', $template), $data)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('task_templates', [
            'id' => $template->id,
            'name' => 'Updated Template',
        ]);
        $this->assertDatabaseCount('task_template_items', 2);
    }

    // --- toggleStatus ---

    public function test_toggle_status_deactivates_and_reactivates_template(): void
    {
        $template = TaskTemplate::factory()->create(['is_active' => true]);

        $this->actingAs($this->user)
            ->put(route('task-templates.toggle-status', $template))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('task_templates', [
            'id' => $template->id,
            'is_active' => false,
        ]);

        $this->actingAs($this->user)
            ->put(route('task-templates.toggle-status', $template))
            ->assertRedirect();

        $this->assertDatabaseHas('task_templates', [
            'id' => $template->id,
            'is_active' => true,
        ]);
    }

    // --- destroy ---

    public function test_destroy_deletes_template(): void
    {
        $template = TaskTemplate::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('task-templates.destroy', $template))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('task_templates', ['id' => $template->id]);
    }
}
