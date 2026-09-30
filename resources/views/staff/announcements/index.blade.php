{{--
    Announcement management.

    "Published" is not a column here - it is whether published_at is set, which
    is the same thing the public page already reads. A separate flag would be a
    second source of truth about whether a notice is public, and the public page
    would be reading one while this screen showed the other.
--}}
<x-app-layout :user="auth()->user()" title="Announcements">
    <div class="mx-auto w-full max-w-6xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'Announcements']]" />

        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-navy-900">Announcements</h1>
                <p class="mt-1 text-sm text-gray-600">Notices for the public page. A draft is not shown to anybody.</p>
            </div>
            <x-btn :href="route('staff.announcements.create')" variant="navy" size="md" icon="megaphone">New announcement</x-btn>
        </header>

        <form method="GET" action="{{ route('staff.announcements.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[16rem] flex-1">
                <label for="q" class="block text-sm font-semibold text-navy-800">Search</label>
                <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Title or body"
                    class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
            </div>
            <div>
                <label for="state" class="block text-sm font-semibold text-navy-800">Show</label>
                <select id="state" name="state" class="mt-1.5 rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                    @foreach ([
                        'all' => 'Everything',
                        'published' => 'Published now',
                        'scheduled' => 'Scheduled',
                        'drafts' => 'Drafts',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected($state === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-btn type="submit" variant="navy" size="md" icon="search">Filter</x-btn>
        </form>

        @if (session('success'))
            <x-alert variant="success" icon="check-circle">{{ session('success') }}</x-alert>
        @endif

        <x-data-table :columns="[['label' => 'Announcement'], ['label' => 'State'], ['label' => 'Author'], ['label' => 'Actions']]"
            caption="Announcements" empty-title="Nothing here"
            empty-description="No announcement matches the current filter.">
            @foreach ($announcements as $announcement)
                <tr>
                    <th scope="row" class="px-4 py-3 text-sm font-semibold text-navy-900">
                        <a href="{{ route('staff.announcements.edit', $announcement) }}" class="hover:underline">{{ $announcement->title }}</a>
                        <span class="block text-xs font-normal text-slate-500">
                            {{ $announcement->isPinned() ? 'Pinned' : 'Not pinned' }}
                            @if ($announcement->location) &middot; {{ $announcement->location }} @endif
                        </span>
                    </th>
                    <td class="px-4 py-3 text-sm text-navy-800">
                        {{ $announcement->publicationStateLabel() }}
                        @if ($announcement->isPublished() && $announcement->published_at)
                            <span class="block text-xs text-slate-500">{{ $announcement->published_at->format('j M Y') }}</span>
                        @elseif ($announcement->isScheduled())
                            <span class="block text-xs text-slate-500">on {{ $announcement->published_at->format('j M Y, g:i a') }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $announcement->createdBy?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <form method="POST" action="{{ route('staff.announcements.publish', $announcement) }}">
                                @csrf
                                <button type="submit"
                                    class="rounded-lg border border-navy-200 px-2.5 py-1.5 text-xs font-semibold text-navy-800 transition hover:bg-navy-50">
                                    {{ $announcement->isPublished() ? 'Unpublish' : 'Publish' }}
                                </button>
                            </form>

                            <x-btn :href="route('staff.announcements.edit', $announcement)" variant="ghost" size="sm">Edit</x-btn>

                            <form method="POST" action="{{ route('staff.announcements.unpin', $announcement) }}">
                                @csrf
                                <button type="submit" @disabled($announcement->isPinned())
                                    class="rounded-lg border border-navy-200 px-2.5 py-1.5 text-xs font-semibold text-navy-800 transition hover:bg-navy-50 disabled:opacity-40">
                                    Unpin
                                </button>
                            </form>

                            <x-confirm-dialog :name="'delete-'.$announcement->id"
                                title="Delete this announcement?"
                                message="It disappears from the public page immediately. The record of who deleted it is kept in the audit log."
                                confirm-label="Delete" tone="danger" method="DELETE"
                                :form="route('staff.announcements.destroy', $announcement)" />
                            <button type="button" x-on:click="$dispatch('open-modal', 'delete-{{ $announcement->id }}')"
                                class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-50">
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div>{{ $announcements->links() }}</div>
    </div>
</x-app-layout>
