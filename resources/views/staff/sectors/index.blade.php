{{--
    Sectors and their Kagawad.

    WAS SEVENTEEN STACKED FORM CARDS. Each sector rendered a rename form, a
    Kagawad form and a delete control, all visible at once, so the page was a wall
    of inputs and an Admin looking for one sector scrolled past sixteen others. Now
    one row per sector, with the editing controls behind a per-row Manage
    disclosure - so the list is scannable and only the row being worked on is
    expanded.

    The assignment select only offers a Kagawad who has no sector. That is the
    unique index on sectors.kagawad_user_id expressed in the UI, so the screen
    cannot produce a save the database will refuse.

    Deletable is decided from two counts the controller already fetched, never from
    $sector->isEmpty(): that method asks the database two questions per sector and
    the view called it once per row, which is how this page came to cost 71
    queries with 17 sectors. The rule and its wording are unchanged - the server
    still refuses, with the same message, and the button is only a courtesy.
--}}
<x-app-layout :user="auth()->user()" title="Sectors &amp; Kagawad">
    <div class="mx-auto w-full max-w-6xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'Sectors & Kagawad']]" />

        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-navy-900">Sectors &amp; Kagawad</h1>
                <p class="mt-1 text-sm text-gray-600">
                    A sector is what a relief checklist and a resident's address are scoped by.
                </p>
            </div>

            <x-btn type="button" variant="navy" size="md" icon="grid"
                x-data="{}"
                x-on:click="$dispatch('open-modal', 'add-sector')">
                Add a sector
            </x-btn>
        </header>

        @if (session('success'))
            <x-alert variant="success" icon="check-circle">{{ session('success') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert variant="warning" icon="x-circle">{{ session('error') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="danger" icon="x-circle">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        {{-- Header figures. Unassigned is the one that needs acting on, so it is
             the one that is also the filter chip below. --}}
        <x-staff.stat-row>
            <x-staff.stat-card label="Total sectors" :value="$totals['total']" tone="navy" icon="grid" />
            <x-staff.stat-card label="With a Kagawad" :value="$totals['assigned']" tone="green" icon="check-circle" />
            <x-staff.stat-card label="Unassigned" :value="$totals['unassigned']"
                :tone="$totals['unassigned'] > 0 ? 'gold' : 'green'" icon="help-circle" />
        </x-staff.stat-row>

        <div class="flex flex-wrap items-center gap-2">
            @if ($unassignedOnly)
                <a href="{{ route('staff.sectors.index') }}"
                    class="inline-flex min-h-[36px] items-center gap-1.5 rounded-full border border-navy-800 bg-navy px-3.5 py-1.5 text-xs font-semibold text-white">
                    Unassigned only
                    <x-icon name="x-circle" class="h-3.5 w-3.5" />
                </a>
            @elseif ($totals['unassigned'] > 0)
                <a href="{{ route('staff.sectors.index', ['unassigned' => 1]) }}"
                    class="inline-flex min-h-[36px] items-center gap-1.5 rounded-full border border-gold-300 bg-gold-50 px-3.5 py-1.5 text-xs font-semibold text-gold-800 transition hover:bg-gold-100">
                    <x-icon name="help-circle" class="h-3.5 w-3.5" />
                    Unassigned only &middot; {{ $totals['unassigned'] }}
                </a>
            @endif
        </div>

        <x-staff.table :columns="[
                ['label' => 'Sector'],
                ['label' => 'Households', 'align' => 'right'],
                ['label' => 'Kagawad'],
                ['label' => 'Actions', 'align' => 'right'],
            ]"
            grouped
            caption="Sectors"
            empty-title="{{ $unassignedOnly ? 'Every sector has a Kagawad' : 'No sectors yet' }}"
            empty-description="{{ $unassignedOnly ? 'Nothing to assign.' : 'Add one to start scoping relief and addresses.' }}"
            empty-icon="grid"
            empty-action-label="{{ $unassignedOnly ? 'Show all sectors' : 'Add a sector' }}"
            empty-action-href="{{ $unassignedOnly ? route('staff.sectors.index') : '' }}">
            @foreach ($sectors as $sector)
                @php
                    // Both counts came with the row. No question is asked of the
                    // database here, which is what keeps this page at a fixed
                    // number of queries however many sectors there are.
                    $households = (int) $sector->residents_count;
                    $relief = (int) $sector->distributions_count;
                    $deletable = $households === 0 && $relief === 0;
                    $manageId = 'manage-sector-'.$sector->id;
                @endphp

                {{--
                    One <tbody> per sector, carrying the Alpine scope.

                    A <tbody> cannot wrap a <tr>, so the detail row below is a
                    SIBLING of this one. With the scope on the <tr>, its x-show had
                    nothing to resolve to and every row threw "managing is not
                    defined" - the expression-outside-its-scope failure, which is
                    invisible until a browser is pointed at it. Putting the scope on
                    the <tbody> is both valid HTML and the only place that legally
                    contains both rows.
                --}}
                <tbody x-data="{ managing: false }">
                <tr class="align-top">
                    <th scope="row">
                        <div class="min-w-0">
                            <p class="font-semibold text-navy-900">{{ $sector->sector_name }}</p>
                            @if ($sector->purok_name)
                                <p class="mt-0.5 text-xs text-slate-500">{{ $sector->purok_name }}</p>
                            @endif
                        </div>
                    </th>
                    <td class="text-right tabular-nums text-slate-600">
                        {{ $households }}
                        @if ($relief > 0)
                            <span class="block text-2xs text-slate-400" title="Relief entries on this sector">
                                {{ $relief }} relief
                            </span>
                        @endif
                    </td>
                    <td>
                        @if ($sector->kagawad)
                            <x-staff.person-cell :name="$sector->kagawad->name" />
                        @else
                            {{-- Amber, and it says Unassigned in words: a colour on its
                                 own is not a state, and this is the state that needs
                                 acting on. --}}
                            <x-staff.status-badge status="pending" label="Unassigned" size="sm" />
                        @endif
                    </td>
                    <td>
                        <div class="flex justify-end">
                            <button type="button"
                                x-on:click="managing = ! managing"
                                x-bind:aria-expanded="managing"
                                aria-controls="{{ $manageId }}"
                                class="inline-flex min-h-[36px] items-center justify-center gap-2 rounded-lg border border-navy-200 bg-white px-3 py-1.5 text-xs font-semibold text-navy-800 transition hover:bg-navy-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400">
                                <span x-text="managing ? 'Close' : 'Manage'"></span>
                                <x-icon name="edit" class="h-3.5 w-3.5" x-show="! managing" />
                                <x-icon name="x-circle" class="h-3.5 w-3.5" x-show="managing" />
                            </button>
                        </div>
                    </td>
                </tr>

                {{-- The editing row. A sibling <tr> rather than a nested table, so
                     the columns still line up with the header above it. --}}
                <tr id="{{ $manageId }}" x-show="managing" x-cloak>
                    <td colspan="4" class="!py-0">
                        <div class="-mx-4 space-y-5 border-t border-navy-50 bg-navy-50/40 px-4 py-4 sm:-mx-4">
                            <div class="grid gap-5 lg:grid-cols-2">
                                {{-- Rename + purok. --}}
                                <form method="POST" action="{{ route('staff.sectors.update', $sector) }}"
                                    class="space-y-3">
                                    @csrf
                                    @method('PUT')
                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-navy-600">
                                        Name and purok
                                    </h3>
                                    <div>
                                        <label for="name-{{ $sector->id }}"
                                            class="block text-sm font-semibold text-navy-800">Sector name</label>
                                        <input id="name-{{ $sector->id }}" name="sector_name" type="text" required
                                            maxlength="255" value="{{ $sector->sector_name }}"
                                            class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                                    </div>
                                    <div>
                                        <label for="purok-{{ $sector->id }}"
                                            class="block text-sm font-semibold text-navy-800">Purok</label>
                                        <input id="purok-{{ $sector->id }}" name="purok_name" type="text"
                                            maxlength="255" value="{{ $sector->purok_name }}"
                                            class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200"
                                            placeholder="Purok">
                                    </div>
                                    <x-btn type="submit" variant="secondary" size="sm" icon="check">Save name</x-btn>
                                </form>

                                {{-- Assign a Kagawad. --}}
                                <form method="POST" action="{{ route('staff.sectors.kagawad', $sector) }}"
                                    class="space-y-3">
                                    @csrf
                                    @method('PUT')
                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-navy-600">
                                        Kagawad
                                    </h3>
                                    <div>
                                        <label for="kagawad-{{ $sector->id }}"
                                            class="block text-sm font-semibold text-navy-800">Responsible for</label>
                                        <select id="kagawad-{{ $sector->id }}" name="kagawad_user_id"
                                            class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                                            <option value="">Nobody assigned</option>
                                            @if ($sector->kagawad)
                                                <option value="{{ $sector->kagawad->id }}" selected>
                                                    {{ $sector->kagawad->name }} (current)
                                                </option>
                                            @endif
                                            @foreach ($unassignedKagawad as $candidate)
                                                <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                                            @endforeach
                                        </select>
                                        <p class="mt-1.5 text-xs text-slate-500">
                                            A Kagawad can hold one sector, so only Kagawad with none are listed.
                                        </p>
                                    </div>
                                    <x-btn type="submit" variant="navy" size="sm" icon="check">Assign</x-btn>
                                </form>
                            </div>

                            {{-- Delete, with the rule stated. --}}
                            <div class="border-t border-navy-100 pt-4">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-navy-600">Delete</h3>

                                @if ($deletable)
                                    <p class="mt-1 text-xs text-slate-500">
                                        Nothing is filed under this sector, so nothing will be left pointing at a
                                        sector that is not there.
                                    </p>
                                    <x-btn type="button" variant="danger" size="sm" icon="x-circle"
                                        x-on:click="$dispatch('open-modal', 'delete-sector-{{ $sector->id }}')">
                                        Delete sector
                                    </x-btn>

                                    <x-confirm-dialog name="delete-sector-{{ $sector->id }}"
                                        title="Delete this sector?"
                                        message="Nothing is filed under it, so nothing will be left pointing at a sector that is not there."
                                        confirm-label="Delete" tone="danger" method="DELETE"
                                        :form="route('staff.sectors.destroy', $sector)" />
                                @else
                                    {{-- Disabled, and the reason is on the button rather
                                         than in a tooltip that only exists for a mouse:
                                         a title attribute is not reachable by keyboard
                                         and not announced by a screen reader. --}}
                                    <span class="inline-flex min-h-[36px] cursor-not-allowed items-center gap-2 rounded-lg border border-navy-200 bg-navy-50 px-3 py-1.5 text-xs font-semibold text-slate-400"
                                        title="Cannot delete while it has households or relief entries"
                                        aria-describedby="cannot-delete-{{ $sector->id }}">
                                        <x-icon name="lock" class="h-3.5 w-3.5" />
                                        Cannot delete
                                    </span>
                                    <p id="cannot-delete-{{ $sector->id }}" class="mt-1 text-xs text-slate-500">
                                        Cannot delete while it has households or relief entries.
                                        @if ($households > 0)
                                            {{ $households }} household{{ $households === 1 ? '' : 's' }} to move.
                                        @endif
                                        @if ($relief > 0)
                                            {{ $relief }} relief entr{{ $relief === 1 ? 'y' : 'ies' }} to move.
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
                </tbody>
            @endforeach
        </x-staff.table>

        {{-- Add a sector, in the header button's modal. --}}
        <x-confirm-dialog name="add-sector"
            title="Add a sector"
            message="A sector is what relief checklists and resident addresses are scoped by, so it needs a name before anything can be filed under it."
            confirm-label="Add sector" tone="primary" :form="route('staff.sectors.store')">
            <div>
                <label for="new-sector-name" class="block text-sm font-semibold text-navy-800">Sector name</label>
                <input id="new-sector-name" name="sector_name" type="text" required maxlength="255"
                    value="{{ old('sector_name') }}"
                    class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
            </div>
            <div class="mt-3">
                <label for="new-sector-purok" class="block text-sm font-semibold text-navy-800">
                    Purok (optional)
                </label>
                <input id="new-sector-purok" name="purok_name" type="text" maxlength="255"
                    value="{{ old('purok_name') }}"
                    class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
            </div>
        </x-confirm-dialog>

        {{-- The known empty-state path, made visible: a Kagawad with no sector
             sees nothing on their dashboard and their relief list. --}}
        <x-card tone="white" padding="p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="text-base font-bold text-navy-900">Kagawad without a sector</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        These accounts see an empty dashboard and an empty relief list. Assign one to a
                        sector above and their checklist appears.
                    </p>
                </div>
                <x-staff.status-badge
                    :status="$unassignedKagawad->isEmpty() ? 'active' : 'pending'"
                    :label="$unassignedKagawad->count().' waiting'"
                    size="sm" />
            </div>

            @if ($unassignedKagawad->isEmpty())
                <p class="mt-3 text-sm text-slate-500">Every Kagawad has a sector.</p>
            @else
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($unassignedKagawad as $candidate)
                        <li>
                            <x-staff.person-cell :name="$candidate->name" :secondary="$candidate->email" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-app-layout>
