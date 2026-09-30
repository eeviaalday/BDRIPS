<x-app-layout>
    <x-slot name="title">Announcements</x-slot>

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">Manage community notices for the public news feed.</p>
            <a href="{{ route('staff.announcements.create') }}"
                class="rounded-lg bg-blue-700 hover:bg-blue-800 px-4 py-2 text-sm font-semibold text-white transition">
                + New Announcement
            </a>
        </div>

        @if ($announcements->isEmpty())
            <div class="rounded-xl bg-white border border-gray-200 p-10 text-center text-sm text-gray-500">
                No announcements yet.
            </div>
        @else
            <div class="rounded-xl bg-white border border-gray-200 divide-y divide-gray-100">
                @foreach ($announcements as $announcement)
                    <div class="p-5 flex items-start justify-between gap-4">
                        <div class="flex gap-4 min-w-0">
                            <img src="{{ $announcement->imageUrl() }}" alt=""
                                class="h-16 w-24 shrink-0 rounded-lg bg-gray-100 object-cover" />
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900">{{ $announcement->title }}</p>
                                <p class="text-sm text-gray-500 mt-1 line-clamp-2">{{ $announcement->body }}</p>
                                <p class="text-xs text-gray-400 mt-2 flex flex-wrap items-center gap-2">
                                    @if ($announcement->isPinned())
                                        <span class="font-semibold uppercase tracking-wide text-amber-700 bg-amber-50 rounded-full px-2 py-0.5">
                                            Pinned
                                        </span>
                                    @endif
                                    <span class="font-semibold uppercase tracking-wide text-blue-700">
                                        {{ $announcement->categoryLabel() }}
                                    </span>
                                    @if ($announcement->published_at)
                                        <span>Published {{ $announcement->published_at->format('M d, Y h:i A') }}</span>
                                    @else
                                        <span class="text-amber-600 font-medium">Not yet published</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('staff.announcements.edit', $announcement) }}"
                                class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium hover:bg-gray-50 transition">Edit</a>
                            <button type="button"
                                class="rounded-lg border border-red-200 text-red-700 px-3 py-1.5 text-sm font-medium hover:bg-red-50 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-400"
                                x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'delete-announcement-{{ $announcement->id }}' }))">
                                Delete
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
            {{ $announcements->links() }}
        @endif
    </div>

    {{-- Deleting a notice removes it from the public news feed immediately, so
         it goes through the shared dialog rather than a bare submit. --}}
    @foreach ($announcements as $announcement)
        <x-confirm-dialog name="delete-announcement-{{ $announcement->id }}"
            title="Delete this announcement?"
            message="{{ $announcement->title }} will be removed from the public news feed straight away. This cannot be undone from the application."
            confirm-label="Delete announcement" tone="danger"
            :form="route('staff.announcements.destroy', $announcement)" method="DELETE" />
    @endforeach
</x-app-layout>