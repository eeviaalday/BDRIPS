<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('staff.announcements.index', [
            'announcements' => Announcement::latest()->paginate(10),
        ]);
    }

    public function create(): View
    {
        return view('staff.announcements.form', [
            'announcement' => null,
            'categories' => Announcement::categoryOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        Announcement::create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'category' => $validated['category'] ?? Announcement::CATEGORY_GENERAL,
            'location' => $validated['location'] ?? null,
            'image_path' => $request->file('image')?->store('announcements', Announcement::IMAGE_DISK),
            'published_at' => ($validated['publish_now'] ?? false) ? now() : null,
            'pinned_at' => ($validated['pinned'] ?? false) ? now() : null,
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('staff.announcements.index')
            ->with('success', 'Announcement saved.');
    }

    public function edit(Announcement $announcement): View
    {
        return view('staff.announcements.form', [
            'announcement' => $announcement,
            'categories' => Announcement::categoryOptions(),
        ]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $previousImage = $announcement->image_path;
        $imagePath = $request->file('image')?->store('announcements', Announcement::IMAGE_DISK);

        $announcement->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'category' => $validated['category'] ?? Announcement::CATEGORY_GENERAL,
            'location' => $validated['location'] ?? null,
            // A notice keeps its photograph unless a new one is uploaded, so
            // editing the copy never silently drops the image.
            'image_path' => $imagePath ?? $announcement->image_path,
            'published_at' => ($validated['publish_now'] ?? false) ? now() : $announcement->published_at,
            // Un-ticking the box unpins, and pinning again restamps the notice
            // to the top of the pinned section.
            'pinned_at' => ($validated['pinned'] ?? false) ? now() : null,
        ]);

        if ($imagePath !== null) {
            $this->deleteImage($previousImage);
        }

        return redirect()->route('staff.announcements.index')
            ->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->deleteImage($announcement->image_path);

        $announcement->delete();

        return redirect()->route('staff.announcements.index')
            ->with('success', 'Announcement deleted.');
    }

    /**
     * Shared rules for both write paths.
     *
     * The category is validated against the configured keys rather than taken
     * as free text, so a card can always resolve a label and a photograph for
     * whatever was stored.
     *
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'category' => ['nullable', Rule::in(array_keys(Announcement::categoryOptions()))],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'location' => ['nullable', 'string', 'max:160'],
            'publish_now' => ['nullable', 'boolean'],
            'pinned' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Remove a stored photograph, ignoring the ones the record never owned.
     * A path is only ever the return value of a store() on the announcement
     * disk, so a blank or missing file is not an error worth reporting.
     */
    private function deleteImage(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        Storage::disk(Announcement::IMAGE_DISK)->delete($path);
    }
}
