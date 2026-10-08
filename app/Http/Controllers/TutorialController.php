<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tutorials\StoreTutorialRequest;
use App\Http\Requests\Tutorials\UpdateTutorialRequest;
use App\Models\Tutorial;
use App\Services\Tutorials\TutorialMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TutorialController extends Controller
{
    public function __construct(
        private readonly TutorialMediaService $mediaService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Tutorials/Index', [
            'videos' => Tutorial::query()
                ->ordered()
                ->get()
                ->map(fn (Tutorial $tutorial) => $tutorial->toVideoPayload())
                ->values(),
            'can' => [
                'create' => $request->user()->can('tutorials.create'),
                'edit' => $request->user()->can('tutorials.edit'),
                'delete' => $request->user()->can('tutorials.delete'),
            ],
        ]);
    }

    public function store(StoreTutorialRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Tutorial::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'duration' => $data['duration'] ?? null,
            'video_path' => $this->mediaService->storeVideo($request->file('video')),
            'thumbnail_path' => $request->hasFile('thumbnail')
                ? $this->mediaService->storeThumbnail($request->file('thumbnail'))
                : null,
            'sort_order' => ((int) Tutorial::query()->max('sort_order')) + 1,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Tutorial agregado correctamente.');
    }

    public function update(UpdateTutorialRequest $request, Tutorial $tutorial): RedirectResponse
    {
        $data = $request->validated();

        $tutorial->title = $data['title'];
        $tutorial->description = $data['description'] ?? null;
        $tutorial->duration = $data['duration'] ?? null;

        if ($request->hasFile('video')) {
            $this->mediaService->delete($tutorial->video_path);
            $tutorial->video_path = $this->mediaService->storeVideo($request->file('video'));
        }

        if ($request->hasFile('thumbnail')) {
            $this->mediaService->delete($tutorial->thumbnail_path);
            $tutorial->thumbnail_path = $this->mediaService->storeThumbnail($request->file('thumbnail'));
        } elseif ($request->boolean('remove_thumbnail')) {
            $this->mediaService->delete($tutorial->thumbnail_path);
            $tutorial->thumbnail_path = null;
        }

        $tutorial->save();

        return back()->with('success', 'Tutorial actualizado correctamente.');
    }

    public function destroy(Request $request, Tutorial $tutorial): RedirectResponse
    {
        abort_unless($request->user()->can('tutorials.delete'), 403);

        $this->mediaService->delete($tutorial->video_path);
        $this->mediaService->delete($tutorial->thumbnail_path);

        $tutorial->delete();

        return back()->with('success', 'Tutorial eliminado correctamente.');
    }
}
