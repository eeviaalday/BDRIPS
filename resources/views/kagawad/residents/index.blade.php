{{--
    A Kagawad's own sector residents, read-only.

    This page answers one question in the field: who in my sector still needs
    collecting. It is the only Kagawad page that shows people rather than
    households, and the only reason it is safe to build is that the sector scope is
    applied in the query (App\Support\KagawadSectorResidents) rather than here. A
    scope applied in this file would hide the rows while still shipping every
    household number in the page source, which is the thing the page must not do.

    WHAT IS DELIBERATELY NOT ON IT

    No national ID, no birthdate, no contact number, no address, no documents, no
    request history, and no link to any resident detail page. Those are not hidden
    with CSS or omitted from a template - they are never selected, so there is
    nothing here to leak. See the privacy note on the controller.

    No edit control either, and none is coming: the route is a single GET with no
    POST behind it, so read-only is a property of the route table.

    A resident who is not on the selected batch's checklist has no relief status.
    It is not "not yet received" - that phrase is a claim about the batch, and
    somebody absent from the checklist is not on it. The legend under the filter bar
    says so in one line, because a blank cell with no explanation reads as a bug.
--}}
<x-app-layout :user="auth()->user()" title="My Sector Residents">
    <div class="mx-auto w-full max-w-6xl space-y-6">
        <x-breadcrumb :items="[
            ['label' => 'Kagawad', 'route' => 'kagawad.dashboard'],
            ['label' => 'My Sector Residents'],
        ]" />

        @if ($sectorIds === [])
            {{--
                No sector. The same shared component the dashboard and the relief
                list use, so the sentence is written once. No query has run, so
                there is no list behind this - not an empty one, none at all.
            --}}
            <header>
                <h1 class="text-xl font-bold text-navy-900">My Sector Residents</h1>
            </header>

            <x-staff.unassigned-sector />
        @else
            <header class="space-y-3">
                <h1 class="text-xl font-bold text-navy-900">My Sector Residents</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Residents in your sector and whether they have received relief.
                </p>

                <ul class="flex flex-wrap gap-2" aria-label="Your sectors">
                    @foreach ($sectors as $sector)
                        <li>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border border-navy-200 bg-navy-50 px-3 py-1 text-xs font-semibold text-navy-800">
                                <x-icon name="pin" class="h-3.5 w-3.5 text-navy-500" />
                                {{ $sector->sector_name }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </header>

            {{--
                The two figures the dashboard card also shows, repeated here because
                this page is reached directly from the bottom bar as often as from
                the card. Both are scoped, and the second is scoped twice - a
                resident who is not verified is not on a distribution, so counting
                one as outstanding would put a number here this list cannot show.
            --}}
            <x-staff.stat-row>
                <x-staff.stat-card label="Residents in your sector" :value="$counts['residents']" tone="navy"
                    icon="users" />
                <x-staff.stat-card label="Still to receive" :value="$counts['outstanding']" tone="gold"
                    icon="clock" />
            </x-staff.stat-row>

            <x-staff.filter-bar :action="route('kagawad.residents.index')"
                :has-filters="$search !== '' || $sectorId !== null || $status !== ''"
                :clear-href="route('kagawad.residents.index')">
                <x-staff.filter-field name="q" label="Search" :value="$search"
                    placeholder="Name or household number" />

                {{-- Only offered when this Kagawad holds more than one sector: a
                     one-option filter is a control that cannot do anything. --}}
                @if ($sectorOptions !== [])
                    <x-staff.filter-select name="sector_id" label="Sector" :options="$sectorOptions"
                        :value="$sectorId" all-label="All my sectors" />
                @endif

                @if ($batches->isNotEmpty())
                    <x-staff.filter-select name="batch_id" label="Batch"
                        :options="$batches->mapWithKeys(fn ($b) => [$b->id => $b->title])->all()"
                        :value="$batch?->id" all-label="No batch selected" />
                @endif

                <x-staff.filter-select name="status" label="Relief status" :options="$statusFilters"
                    :value="$status" />
            </x-staff.filter-bar>

            <p class="text-2xs text-navy-500">
                Relief status is shown for
                @if ($batch !== null)
                    <span class="font-semibold text-navy-700">{{ $batch->title }}</span> only.
                    A resident who is not on that batch's checklist has no status.
                @else
                    the selected batch. Choose a batch to see who has received.
                @endif
            </p>

            @if ($profiles->isEmpty())
                {{-- "Nothing registered" and "your filter matched nothing" are two
                     different problems, and only one of them is the reader's to
                     fix, so the way out is offered either way it can be. --}}
                <x-staff.empty-state icon="users"
                    :title="$search !== '' || $sectorId !== null || $status !== ''
                        ? 'No residents match these filters'
                        : 'No residents in your sector yet'"
                    :description="$search !== '' || $sectorId !== null || $status !== ''
                        ? 'Try a different search, or clear the filters to see everyone in your sector.'
                        : 'Residents appear here as soon as they register and are assigned to your sector.'"
                    empty-icon="users"
                    :action-label="$search !== '' || $sectorId !== null || $status !== '' ? 'Clear filters' : null"
                    :action-href="$search !== '' || $sectorId !== null || $status !== '' ? route('kagawad.residents.index') : null" />
            @else
                {{-- TWO RENDERINGS OF THE SAME ROWS.

                     Below md this table would be a 46rem-wide grid inside
                     overflow-x-auto, so a phone gets a table the reader has to
                     scroll sideways - the opposite of usable for someone holding a
                     clipboard in the street. So below md the same rows are drawn as
                     stacked cards, each label sitting directly above its own value.

                     Both are always in the DOM and one is display:none. They cannot
                     disagree, because each value is written once per rendering and
                     both read the same two attributes off the same $profile. --}}

                {{-- Phone: one card per resident. --}}
                <ul class="space-y-3 md:hidden">
                    @foreach ($profiles as $profile)
                        <li class="rounded-xl border border-navy-100 bg-white p-4 shadow-card transition duration-200">
                            <x-staff.person-cell :name="$profile->user?->name"
                                :secondary="$profile->household_number" />

                            <dl class="mt-3 space-y-2">
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-2xs font-semibold uppercase tracking-wide text-navy-500">Sector</dt>
                                    <dd class="text-sm text-slate-600">{{ $profile->sector?->sector_name ?? '—' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-2xs font-semibold uppercase tracking-wide text-navy-500">Verification</dt>
                                    <dd>
                                        @if ($profile->isVerified())
                                            <x-staff.status-badge status="verified" size="sm" />
                                        @else
                                            <x-staff.status-badge status="neutral" label="Not verified" size="sm" />
                                        @endif
                                    </dd>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-2xs font-semibold uppercase tracking-wide text-navy-500">Relief</dt>
                                    <dd>
                                        @include('kagawad.residents.partials.relief-status', ['profile' => $profile])
                                    </dd>
                                </div>
                            </dl>
                        </li>
                    @endforeach
                </ul>

                {{-- Tablet and up: the real table. --}}
                <x-staff.table class="hidden md:block" :columns="[
                        ['label' => 'Resident'],
                        ['label' => 'Household'],
                        ['label' => 'Sector'],
                        ['label' => 'Relief status'],
                        ['label' => 'Verification'],
                    ]"
                    caption="Residents in your sector" empty-title="No residents in your sector yet"
                    empty-description="Residents appear here as soon as they register and are assigned to your sector."
                    empty-icon="users">
                    @foreach ($profiles as $profile)
                        {{-- No x-on:click and no href. A resident row is not a link on
                             this page, which is the other half of "read-only": there
                             is nowhere for the tap to go. --}}
                        <tr>
                            <th scope="row">
                                <x-staff.person-cell :name="$profile->user?->name" />
                            </th>
                            <td class="font-mono text-xs text-navy-800">{{ $profile->household_number }}</td>
                            <td class="text-sm text-slate-600">{{ $profile->sector?->sector_name ?? '—' }}</td>
                            <td>
                                @include('kagawad.residents.partials.relief-status', ['profile' => $profile])
                            </td>
                            <td>
                                @if ($profile->isVerified())
                                    <x-staff.status-badge status="verified" size="sm" />
                                @else
                                    <x-staff.status-badge status="neutral" label="Not verified" size="sm" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-staff.table>
            @endif

            <x-staff.pagination :paginator="$profiles" total-label="residents" :always="true" />
        @endif
    </div>
</x-app-layout>
