{{--
    The residents directory.

    A staff member may look across households here, which is the job. The scoping
    that stops one resident reading a neighbour does not apply, and pretending
    otherwise would make the directory useless.
--}}
<x-app-layout :user="auth()->user()" title="Residents">
    <div class="mx-auto w-full max-w-6xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'Residents']]" />

        <header>
            <h1 class="text-xl font-bold text-navy-900">Residents</h1>
            <p class="mt-1 text-sm text-gray-600">Every registered resident, by household number.</p>
        </header>

        <x-staff.filter-bar :action="route('staff.residents.index')"
            :has-filters="$search !== '' || $sectorId !== null || $record !== 'active'"
            :clear-href="route('staff.residents.index')">
            <x-staff.filter-field name="q" label="Search" :value="$search"
                placeholder="Name, email, address, household number" />
            <x-staff.filter-select name="sector_id" label="Sector"
                :options="$sectors->pluck('sector_name', 'id')->all()" :value="$sectorId" all-label="All sectors" />
            <x-staff.filter-select name="record" label="Record" :options="$recordOptions" :value="$record" />
        </x-staff.filter-bar>

        {{-- The deliberate exception to "filters default to All".
             Archived households are hidden here on purpose: this is the working
             directory, and a list of people who have left is not work. So the
             hiding has to be visible rather than silent - a Secretary who cannot
             find a household must be able to tell the difference between "this
             record does not exist" and "this record is archived", and must be
             given the one click that shows them.

             $hiddenCount ignores the search and sector filters, so the hint does
             not flicker in and out as the reader types: it answers "are there
             archived records in this barangay at all". --}}
        @if ($hiddenCount > 0)
            <p class="text-sm text-gray-600">
                {{ $hiddenCount }} archived {{ \Illuminate\Support\Str::plural('household', $hiddenCount) }}
                {{ $hiddenCount === 1 ? 'is' : 'are' }} hidden from this list.
                <a href="{{ route('staff.residents.index', ['record' => 'all']) }}"
                    class="font-semibold text-navy-700 underline underline-offset-2 hover:text-navy-900">Archived records are hidden. Show them</a>
            </p>
        @endif

        {{-- The empty state distinguishes "nothing is registered" from "your filter
             matched nothing", because only one of those is the reader's problem to
             fix, and the way out is offered either way it can be offered. --}}
        <x-staff.table
            :columns="[
                ['label' => 'Resident'],
                ['label' => 'Household'],
                ['label' => 'Sector'],
                ['label' => 'Verification'],
                ['label' => 'Registered'],
                ['label' => 'Actions'],
            ]"
            caption="Resident directory"
            :empty-title="$search !== '' || $sectorId !== null ? 'No residents match these filters' : 'No residents yet'"
            :empty-description="$search !== '' || $sectorId !== null
                ? 'Try a different search, or clear the filters to see everyone.'
                : 'Residents appear here as soon as they finish registering.'"
            empty-icon="users"
            :empty-action-label="$search !== '' || $sectorId !== null ? 'Clear filters' : null"
            :empty-action-href="$search !== '' || $sectorId !== null ? route('staff.residents.index') : null">
            @foreach ($profiles as $profile)
                @php $archived = (bool) $profile->user?->isArchived(); @endphp
                <tr @class(['opacity-70' => $archived])>
                    <th scope="row">
                        <x-staff.person-cell :name="$profile->user?->name" :secondary="$profile->user?->email" />
                    </th>
                    <td class="font-mono text-xs text-navy-800">{{ $profile->household_number }}</td>
                    <td class="text-sm text-slate-600">{{ $profile->sector?->sector_name ?? '—' }}</td>
                    <td>
                        <div class="flex flex-wrap items-center gap-1">
                            <x-staff.status-badge :status="$profile->verification_status" size="sm" />
                            {{-- The account state beside the verification state, because
                                 they are different facts and a reader needs both: an archived
                                 household is usually still "Verified", which says nothing at
                                 all about why it is greyed out three columns along. --}}
                            @if ($archived)
                                <x-staff.status-badge status="archived" size="sm" />
                            @endif
                        </div>
                    </td>
                    <td class="text-sm text-slate-600">{{ $profile->user?->created_at?->format('j M Y') ?? '—' }}</td>
                    <td>
                        <div class="flex items-center gap-1">
                        {{-- Two or three controls, all icon-only. Each carries the
                             resident's name in its accessible name, so a screen reader
                             announces "View Juan Dela Cruz" and "Archive Juan Dela Cruz"
                             rather than two indistinguishable buttons.

                             An archived household gets View and Restore and NOT Edit. An
                             edit form on an archived record is a form for changing a
                             household that is not on any working list, and the change
                             would be invisible everywhere the household appears. --}}
                        <x-staff.icon-action icon="eye" label="View {{ $profile->user?->name }}"
                            :href="route('staff.residents.show', $profile)" />

                        @if ($archived)
                            <x-staff.confirm-dialog :name="'restore-'.$profile->id"
                                :title="'Restore '.$profile->user?->name.'?'"
                                message="This household goes back on the working lists, out of the archived view, and back into new relief batches. Their verification status and sector are kept as they are."
                                confirm-label="Restore" tone="primary"
                                :form="route('staff.residents.restore', $profile)" />
                            <x-staff.icon-action icon="box-open" label="Restore {{ $profile->user?->name }}"
                                type="button" x-data
                                x-on:click="$dispatch('open-modal', 'restore-{{ $profile->id }}')" />
                        @else
                            <x-staff.icon-action icon="pen" label="Edit {{ $profile->user?->name }}"
                                :href="route('staff.residents.edit', $profile)" />

                            {{-- tone="danger" is deliberately NOT used. That tone is for
                                 actions that cannot be undone - Delete, Close - and this one
                                 is reversible from the same row it was hidden from. Red on a
                                 reversible action teaches staff that red means gone. --}}
                            <x-staff.confirm-dialog :name="'archive-'.$profile->id"
                                :title="'Archive '.$profile->user?->name.'?'"
                                message="They will no longer be able to sign in or request documents, and will be left out of new relief batches. Their history is kept and you can restore them."
                                confirm-label="Archive" tone="primary"
                                :form="route('staff.residents.archive', $profile)">
                                <div class="space-y-3">
                                    <div>
                                        <label for="archive_reason_{{ $profile->id }}"
                                            class="block text-sm font-semibold text-navy-800">Reason</label>
                                        <select id="archive_reason_{{ $profile->id }}" name="archive_reason" required
                                            class="mt-1 w-full rounded-lg border-navy-300 text-sm shadow-sm focus:border-navy-500 focus:ring-navy-500">
                                            <option value="">Choose a reason</option>
                                            @foreach (\App\Models\User::archiveReasons() as $reason)
                                                <option value="{{ $reason }}">{{ $reason }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="archive_note_{{ $profile->id }}"
                                            class="block text-sm font-semibold text-navy-800">Note (optional)</label>
                                        <textarea id="archive_note_{{ $profile->id }}" name="archive_note" rows="2"
                                            maxlength="1000"
                                            placeholder="Anything that does not fit the reason above"
                                            class="mt-1 w-full rounded-lg border-navy-300 text-sm shadow-sm focus:border-navy-500 focus:ring-navy-500"></textarea>
                                    </div>
                                    @error('archive_reason')
                                        <p class="text-sm font-medium text-red-700">{{ $message }}</p>
                                    @enderror
                                </div>
                            </x-staff.confirm-dialog>
                            <x-staff.icon-action icon="box-archive" label="Archive {{ $profile->user?->name }}"
                                type="button" x-data
                                x-on:click="$dispatch('open-modal', 'archive-{{ $profile->id }}')" />
                        @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-staff.table>

        <x-staff.pagination :paginator="$profiles" />
    </div>
</x-app-layout>