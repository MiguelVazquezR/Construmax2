<?php

namespace Tests\Feature;

use App\Models\Tutorial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TutorialControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create([
            'name' => 'tutorials.create',
            'guard_name' => 'web',
            'category' => 'Tutoriales',
            'description' => 'Create tutorials',
        ]);
        Permission::create([
            'name' => 'tutorials.edit',
            'guard_name' => 'web',
            'category' => 'Tutoriales',
            'description' => 'Edit tutorials',
        ]);
        Permission::create([
            'name' => 'tutorials.delete',
            'guard_name' => 'web',
            'category' => 'Tutoriales',
            'description' => 'Delete tutorials',
        ]);

        $this->manager = User::factory()->create(['is_active' => true]);
        $this->manager->givePermissionTo(['tutorials.create', 'tutorials.edit', 'tutorials.delete']);

        $this->viewer = User::factory()->create(['is_active' => true]);
    }

    private function fakeStoredTutorial(array $overrides = []): Tutorial
    {
        Storage::disk('public')->put('tutorials/videos/original.mp4', 'video');
        Storage::disk('public')->put('tutorials/thumbnails/original.jpg', 'thumbnail');

        return Tutorial::create(array_merge([
            'title' => 'Tutorial original',
            'description' => 'Descripción original',
            'duration' => '01:00',
            'video_path' => '/storage/tutorials/videos/original.mp4',
            'thumbnail_path' => '/storage/tutorials/thumbnails/original.jpg',
            'sort_order' => 9,
        ], $overrides));
    }

    public function test_index_lists_the_tutorials_from_the_database(): void
    {
        $this->actingAs($this->viewer)
            ->get(route('tutorials.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tutorials/Index')
                ->has('videos', 8)
                ->where('videos.0.title', 'Módulo de inicio')
                ->where('videos.0.video_url', '/videos/inicio.mp4')
                // Legacy /videos/* files are not part of the repository, so they are not exposed.
                ->where('videos.0.video_available', false)
                ->where('videos.0.thumbnail_url', null)
                ->where('can.create', false)
                ->where('can.edit', false)
                ->where('can.delete', false)
            );
    }

    public function test_index_exposes_the_availability_of_the_stored_files(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('tutorials/videos/available.mp4', 'video');
        Storage::disk('public')->put('tutorials/thumbnails/available.jpg', 'thumbnail');

        Tutorial::create([
            'title' => 'Tutorial con archivos',
            'video_path' => '/storage/tutorials/videos/available.mp4',
            'thumbnail_path' => '/storage/tutorials/thumbnails/available.jpg',
            'sort_order' => 20,
        ]);

        $this->actingAs($this->viewer)
            ->get(route('tutorials.index'))
            ->assertInertia(fn ($page) => $page
                ->where('videos.0.video_available', false)
                ->where('videos.0.thumbnail_url', null)
                ->where('videos.8.title', 'Tutorial con archivos')
                ->where('videos.8.video_available', true)
                ->where('videos.8.thumbnail_url', '/storage/tutorials/thumbnails/available.jpg')
            );
    }

    public function test_managers_get_the_management_flags(): void
    {
        $this->actingAs($this->manager)
            ->get(route('tutorials.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.create', true)
                ->where('can.edit', true)
                ->where('can.delete', true)
            );
    }

    public function test_permissions_are_granular(): void
    {
        Storage::fake('public');
        $tutorial = $this->fakeStoredTutorial();

        $editor = User::factory()->create(['is_active' => true]);
        $editor->givePermissionTo('tutorials.edit');

        $this->actingAs($editor)
            ->get(route('tutorials.index'))
            ->assertInertia(fn ($page) => $page
                ->where('can.create', false)
                ->where('can.edit', true)
                ->where('can.delete', false)
            );

        $this->actingAs($editor)
            ->post(route('tutorials.store'), [
                'title' => 'No permitido',
                'video' => UploadedFile::fake()->create('tutorial.mp4', 1024, 'video/mp4'),
            ])
            ->assertForbidden();

        $this->actingAs($editor)
            ->delete(route('tutorials.destroy', $tutorial))
            ->assertForbidden();

        $this->actingAs($editor)
            ->put(route('tutorials.update', $tutorial), ['title' => 'Editado por el editor'])
            ->assertRedirect();

        $this->assertSame('Editado por el editor', $tutorial->refresh()->title);
    }

    public function test_store_creates_a_tutorial_with_video_and_thumbnail(): void
    {
        Storage::fake('public');

        $this->actingAs($this->manager)
            ->post(route('tutorials.store'), [
                'title' => 'Módulo de pruebas',
                'description' => 'Descripción de prueba',
                'duration' => '03:00',
                'video' => UploadedFile::fake()->create('tutorial.mp4', 2048, 'video/mp4'),
                'thumbnail' => UploadedFile::fake()->image('thumb.jpg', 640, 360),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $tutorial = Tutorial::query()->where('title', 'Módulo de pruebas')->first();

        $this->assertNotNull($tutorial);
        $this->assertSame(9, $tutorial->sort_order);
        $this->assertSame($this->manager->id, $tutorial->created_by);
        $this->assertStringStartsWith('/storage/tutorials/videos/', $tutorial->video_path);
        $this->assertStringStartsWith('/storage/tutorials/thumbnails/', $tutorial->thumbnail_path);
        Storage::disk('public')->assertExists(Str::after($tutorial->video_path, '/storage/'));
        Storage::disk('public')->assertExists(Str::after($tutorial->thumbnail_path, '/storage/'));
    }

    public function test_store_validates_required_fields(): void
    {
        Storage::fake('public');

        $this->actingAs($this->manager)
            ->post(route('tutorials.store'), [])
            ->assertSessionHasErrors(['title', 'video']);
    }

    public function test_store_is_forbidden_without_permission(): void
    {
        Storage::fake('public');

        $this->actingAs($this->viewer)
            ->post(route('tutorials.store'), [
                'title' => 'Módulo de pruebas',
                'video' => UploadedFile::fake()->create('tutorial.mp4', 1024, 'video/mp4'),
            ])
            ->assertForbidden();
    }

    public function test_update_changes_the_fields_and_replaces_the_video(): void
    {
        Storage::fake('public');
        $tutorial = $this->fakeStoredTutorial();

        $this->actingAs($this->manager)
            ->put(route('tutorials.update', $tutorial), [
                'title' => 'Título actualizado',
                'description' => 'Nueva descripción',
                'duration' => '05:05',
                'video' => UploadedFile::fake()->create('nuevo.mp4', 1024, 'video/mp4'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $tutorial->refresh();

        $this->assertSame('Título actualizado', $tutorial->title);
        $this->assertSame('Nueva descripción', $tutorial->description);
        $this->assertSame('05:05', $tutorial->duration);
        $this->assertNotSame('/storage/tutorials/videos/original.mp4', $tutorial->video_path);
        $this->assertStringStartsWith('/storage/tutorials/videos/', $tutorial->video_path);
        Storage::disk('public')->assertMissing('tutorials/videos/original.mp4');

        // The thumbnail is kept when no new file is sent.
        $this->assertSame('/storage/tutorials/thumbnails/original.jpg', $tutorial->thumbnail_path);
        Storage::disk('public')->assertExists('tutorials/thumbnails/original.jpg');
    }

    public function test_update_can_remove_the_thumbnail(): void
    {
        Storage::fake('public');
        $tutorial = $this->fakeStoredTutorial();

        $this->actingAs($this->manager)
            ->put(route('tutorials.update', $tutorial), [
                'title' => 'Tutorial original',
                'remove_thumbnail' => true,
            ])
            ->assertRedirect();

        $this->assertNull($tutorial->refresh()->thumbnail_path);
        Storage::disk('public')->assertMissing('tutorials/thumbnails/original.jpg');
    }

    public function test_update_is_forbidden_without_permission(): void
    {
        Storage::fake('public');
        $tutorial = $this->fakeStoredTutorial();

        $this->actingAs($this->viewer)
            ->put(route('tutorials.update', $tutorial), ['title' => 'Hackeado'])
            ->assertForbidden();

        $this->assertSame('Tutorial original', $tutorial->refresh()->title);
    }

    public function test_destroy_deletes_the_tutorial_and_its_files(): void
    {
        Storage::fake('public');
        $tutorial = $this->fakeStoredTutorial();

        $this->actingAs($this->manager)
            ->delete(route('tutorials.destroy', $tutorial))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('tutorials', ['id' => $tutorial->id]);
        Storage::disk('public')->assertMissing('tutorials/videos/original.mp4');
        Storage::disk('public')->assertMissing('tutorials/thumbnails/original.jpg');
    }

    public function test_destroy_is_forbidden_without_permission(): void
    {
        Storage::fake('public');
        $tutorial = $this->fakeStoredTutorial();

        $this->actingAs($this->viewer)
            ->delete(route('tutorials.destroy', $tutorial))
            ->assertForbidden();

        $this->assertDatabaseHas('tutorials', ['id' => $tutorial->id]);
    }
}
