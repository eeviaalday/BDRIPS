{{--
    Announcements.

    The only staff list whose rows carry actions rather than just a destination, so
    it is also the only one where the whole row is NOT clickable: a row that opened
    somewhere under Publish, Unpin or Delete would be a row that fires an action by
    accident. The title is the link instead, and the actions stay buttons.

    The filter is a GET form over the same state= query string it has always used.
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

        <x-staff.filter-bar :action="route('staff.announcements.index')"
            :has-filters="$search !== '' || $state !== 'all'"
            :clear-href="route('staff.announcements.index')">
            <x-staff.filter-field name="q" label="Search" :value="$search" placeholder="Title or body" />
            <x-staff.filter-select name="state" label="Show" :value="$state" :options="[
                'all' => 'Everything',
                'published' => 'Published now',
                'scheduled' => 'Scheduled',
                'drafts' => 'Drafts',
            ]" />
        </x-staff.filter-bar>

        @if (session('success'))
            <x-alert variant="success" icon="check-circle">{{ session('success') }}</x-alert>
        @endif

        <x-staff.table
            :columns="[
                ['label' => 'Announcement'],
                ['label' => 'State'],
                ['label' => 'Author'],
                ['label' => 'Actions', 'align' => 'right'],
            ]"
            caption="Announcements"
            :empty-title="$search !== '' || $state !== 'all' ? 'No announcements match these filters' : 'Nothing here yet'"
            :empty-description="$search !== '' || $state !== 'all'
                ? 'Try a different search, or clear the filters to see everything.'
                : 'Write the first notice for the public page.'"
            empty-icon="megaphone"
            :empty-action-label="$search !== '' || $state !== 'all' ? 'Clear filters' : null"
            :empty-action-href="$search !== '' || $state !== 'all' ? route('staff.announcements.index') : null">
            @foreach ($announcements as $announcement)
                <tr>
                    <th scope="row">
                        <x-staff.person-cell :name="$announcement->title"
                            :secondary="trim(($announcement->isPinned() ? 'Pinned' : 'Not pinned').($announcement->location ? ' · '.$announcement->location : ''))"
                            :href="route('staff.announcements.edit', $announcement)" />
                    </th>
                    <td>
                        <x-staff.status-badge
                            :status="$announcement->isPublished() ? 'published' : ($announcement->isScheduled() ? 'scheduled' : 'draft')"
                            size="sm" />
                        @if ($announcement->isPublished() && $announcement->published_at)
                            <span class="mt-1 block text-xs text-slate-500">{{ $announcement->published_at->format('j M Y') }}</span>
                        @elseif ($announcement->isScheduled())
                            <span class="mt-1 block text-xs text-slate-500">on {{ $announcement->published_at->format('j M Y, g:i a') }}</span>
                        @endif
                    </td>
                    <td class="text-sm text-slate-600">{{ $announcement->createdBy?->name ?? '—' }}</td>
                    <td>
                        <div class="flex flex-wrap items-center justify-end gap-1.5">
                            <form method="POST" action="{{ route('staff.announcements.publish', $announcement) }}">
                                @csrf
                                <x-btn type="submit" variant="outline" size="sm">
                                    {{ $announcement->isPublished() ? 'Unpublish' : 'Publish' }}
                                </x-btn>
                            </form>

                            {{-- Pin and Unpin are the same control in two states, so
                                 the label and the action are both read off the row's
                                 actual pinned state rather than being written once.

                                 The previous version rendered an Unpin form on every
                                 row, disabled it exactly when the notice was pinned,
                                 and posted it to the unpin route. So the one state in
                                 which unpinning was possible was the state in which
                                 the button was dead, and an unpinned notice offered an
                                 enabled Unpin against something that was not pinned.

                                 Unpin is a POST because it needs nothing but the row.
                                 Pin is a LINK, because staff.announcements.pin is
                                 "schedule and pin": it validates published_at as
                                 required and after:now, and the public page only
                                 reads pinned notices that are also live, so a pin
                                 without a date is refused by design. Posting from
                                 here with no date would fail validation every time,
                                 so the link goes to the edit form, which is where the
                                 Pin from field lives. --}}
                            @if ($announcement->isPinned())
                                <form method="POST" action="{{ route('staff.announcements.unpin', $announcement) }}">
                                    @csrf
                                    <x-btn type="submit" variant="outline" size="sm">Unpin</x-btn>
                                </form>
                            @else
                                <x-btn :href="route('staff.announcements.edit', ['announcement' => $announcement]).'#pin-and-publish'"
                                    variant="outline" size="sm">
                                    Pin
                                </x-btn>
                            @endif

                            <x-staff.confirm-dialog :name="'delete-'.$announcement->id"
                                title="Delete this announcement?"
                                message="It disappears from the public page immediately. The record of who deleted it is kept in the audit log."
                                confirm-label="Delete" tone="danger" method="DELETE"
                                :form="route('staff.announcements.destroy', $announcement)" />
                            <x-btn type="button" variant="danger" size="sm"
                                x-on:click="$dispatch('open-modal', 'delete-{{ $announcement->id }}')">
                                Delete
                            </x-btn>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-staff.table>

        <x-staff.pagination :paginator="$announcements" />
    </div>
</x-app-layout>