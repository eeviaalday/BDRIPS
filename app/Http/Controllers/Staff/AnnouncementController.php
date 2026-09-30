<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Announcement management: create, edit, publish, pin.
 *
 * "Publish" is not a separate table or a boolean here - it is whether
 * published_at is set, which is the same thing the public page already reads.
 * A separate flag would be a second source of truth about whether a notice is
 * public, and the public page would be reading one while the screen showed the
 * other.
 *
 * Pinning is likewise just pinned_at. The public announcements page lifts the
 * newest pinned notice out of the feed, so pinning is a presentation choice
 * about one notice rather than a mode of the whole page.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $state = (string) $request->query('state', 'all');

        $query = Announcement::query()
            ->with('createdBy')
            // Grouped, because an ungrouped orWhere would bind to the state
            // filter instead of the search: "drafts" plus a term would then mean
            // "drafts, or anything matching the term".
            ->when($search !== '', fn ($notices) => $notices->where(
                fn ($inner) => $inner
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%")
            ))
            ->when($state === 'published', fn ($notices) => $notices->published())
            ->when($state === 'drafts', fn ($notices) => $notices->whereNull('published_at'))
            ->when($state === 'scheduled', fn ($notices) => $notices
                ->whereNotNull('published_at')
                ->where('published_at', '>', now()))
            ->latest('created_at');

        return view('staff.announcements.index', [
            'announcements' => $query->paginate(15)->withQueryString(),
            'search' => $search,
            'state' => $state,
            'publishedCount' => Announcement::query()->published()->count(),
            'draftCount' => Announcement::query()->whereNull('published_at')->count(),
        ]);
    }

    public function create(): View
    {
        return view('staff.announcements.form', [
            'announcement' => new Announcement,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $announcement = Announcement::create($this->validated($request, null));

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'announcement.created',
            subject: $announcement,
            description: sprintf('Announcement "%s" was created', $announcement->title),
        );

        return redirect()
            ->route('staff.announcements.index')
            ->with('success', $announcement->isPublished()
                ? 'Announcement published.'
                : 'Announcement saved as a draft.');
    }

    public function edit(Announcement $announcement): View
    {
        return view('staff.announcements.form', [
            'announcement' => $announcement,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $attributes = $this->validated($request, $announcement);

        // A blank date field means "leave it alone", not "unpublish". The form
        // always posts the field, so without this a typo in the title of a live
        // notice would silently take it off the public page. Withdrawing a notice
        // is the explicit publish toggle's job.
        if (blank($attributes['published_at'] ?? null)) {
            unset($attributes['published_at']);
        }

        $announcement->update($attributes);

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'announcement.updated',
            subject: $announcement,
            description: sprintf('Announcement "%s" was edited', $announcement->title),
        );

        return redirect()
            ->route('staff.announcements.index')
            ->with('success', 'Announcement saved.');
    }

    /**
     * Publish now, or unpublish by clearing the date.
     *
     * One action rather than publish/unpublish, because the state is the field.
     */
    public function publish(Request $request, Announcement $announcement): RedirectResponse
    {
        $publishing = ! $announcement->isPublished();

        $announcement->update([
            'published_at' => $publishing ? now() : null,
        ]);

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: $publishing ? 'announcement.published' : 'announcement.unpublished',
            subject: $announcement,
            description: sprintf(
                'Announcement "%s" was %s',
                $announcement->title,
                $publishing ? 'published' : 'unpublished'
            ),
        );

        return back()->with('success', $publishing ? 'Published.' : 'Unpublished - it is a draft again.');
    }

    /**
     * Schedule for a future date, or unpin.
     */
    public function pin(Request $request, Announcement $announcement): RedirectResponse
    {
        $validated = $request->validate([
            'published_at' => ['required', 'date', 'after:now'],
        ], [
            'published_at.after' => 'A scheduled notice has to be in the future.',
        ]);

        $announcement->update([
            'published_at' => $validated['published_at'],
            'pinned_at' => $announcement->pinned_at ?? now(),
        ]);

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'announcement.scheduled',
            subject: $announcement,
            description: sprintf(
                'Announcement "%s" was scheduled for %s',
                $announcement->title,
                $validated['published_at']
            ),
        );

        return back()->with('success', 'Scheduled and pinned.');
    }

    public function unpin(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_if($announcement->isPinned() === false, 409, 'That announcement is not pinned.');

        $announcement->update(['pinned_at' => null]);

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'announcement.unpinned',
            subject: $announcement,
            description: sprintf('Announcement "%s" was unpinned', $announcement->title),
        );

        return back()->with('success', 'Unpinned.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        $title = $announcement->title;

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'announcement.deleted',
            subject: $announcement,
            description: sprintf('Announcement "%s" was deleted', $title),
        );

        // The log keeps the title, so deleting a notice does not erase the fact
        // that it was once published.
        $announcement->delete();

        return redirect()
            ->route('staff.announcements.index')
            ->with('success', sprintf('"%s" was deleted.', $title));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Announcement $announcement): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'category' => ['nullable', 'string', Rule::in(array_keys($this->categoryOptions()))],
            'location' => ['nullable', 'string', 'max:160'],
            'published_at' => ['nullable', 'date'],
        ], [], [
            'published_at' => 'publication date',
        ]) + ['updated_at' => now()];
    }

    /**
     * @return array<string, string>
     */
    private function categoryOptions(): array
    {
        return collect(config('bdrpis.announcement_categories', []))
            ->map(fn (array $category): string => (string) $category['label'])
            ->all();
    }
}
