{{--
    The audit log, read only.

    There is no edit and no delete control anywhere on this page, by design. A
    trail an Admin can change is not a trail, and the absence of the buttons is
    the feature rather than an omission.

    Context is shown as key/value pairs rather than as raw JSON. A JSON blob in a
    table cell is technically the data and practically unreadable: an Admin
    checking what a role change recorded wants to see "from: secretary" and
    "to: resident", not {"from":"secretary","to":"resident"}. Anything that is not
    a flat map - a nested array, a list - still falls back to JSON, because
    flattening it would misreport it.
--}}
<x-app-layout :user="auth()->user()" title="Audit Logs">
    <div class="mx-auto w-full max-w-6xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'Audit Logs']]" />

        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-navy-900">Audit Logs</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Append-only. {{ number_format($total) }} entr{{ $total === 1 ? 'y' : 'ies' }} recorded.
                </p>
            </div>
        </header>

        {{--
            Five controls plus the button. The two date fields are wrapped in one
            flex pair so the bar wraps at a predictable place.

            Left as five independent children, the bar filled row one to within a
            pixel at 1440px and pushed the Filter button onto a row by itself -
            the one outcome this is meant to avoid, and measured rather than
            assumed. Grouping from/to means the wrap is either everything on one
            row, or the date pair and the button together on the second.

            The search is capped at max-w-48 for the same reason: uncapped it grew
            to 324px and ate the exact space the button needed.

            The dates are not shrunk to make the arithmetic work. A type="date"
            that ellipsises hides part of a date nobody can guess at from context,
            which is the defect Prompt 2's filter work was about.
        --}}
        <x-staff.filter-bar :action="route('staff.audit.index')"
            :has-filters="$search !== '' || $action !== '' || $actorId || $from !== '' || $to !== ''"
            :clear-href="route('staff.audit.index')"
            clear-label="Clear" button-class="lg:ml-auto" gap="gap-2">
            <x-staff.filter-field name="q" label="Search" :value="$search" max-width="max-w-48"
                placeholder="Description or name" />
            <x-staff.filter-select name="action" label="Action" :options="$actions" :value="$action"
                all-label="Every action" />
            <x-staff.filter-select name="actor" label="Account"
                :options="$actors->pluck('name', 'id')->all()" :value="$actorId" all-label="Anyone" />
            <div class="flex shrink-0 items-end gap-3">
                <x-staff.filter-date name="from" label="From" :value="$from" />
                <x-staff.filter-date name="to" label="To" :value="$to" />
            </div>
        </x-staff.filter-bar>

        <x-staff.table :columns="[['label' => 'When'], ['label' => 'Who'], ['label' => 'Action'], ['label' => 'Detail']]"
            caption="Audit log" empty-title="Nothing logged"
            empty-description="No entry matches the current filter."
            empty-icon="shield-check"
            empty-action-label="Clear filters" :empty-action-href="route('staff.audit.index')">
            @foreach ($entries as $entry)
                <tr>
                    <td class="whitespace-nowrap text-slate-500">
                        {{ $entry->created_at?->format('j M Y') }}
                        <span class="block text-xs">{{ $entry->created_at?->format('g:i a') }}</span>
                    </td>
                    <td class="text-navy-900">{{ $entry->user?->name ?? 'Someone removed' }}</td>
                    <td>
                        <x-staff.action-badge :action="$entry->action" :label="$entry->actionLabel()" size="sm" />
                    </td>
                    <td class="text-slate-600">
                        {{ $entry->description }}

                        @if ($entry->context)
                            @php
                                /*
                                 * Flat maps get a definition list. Anything nested or
                                 * a list keeps the JSON, because rendering
                                 * ['a' => ['b' => 1]] as "a: Array" would lose the
                                 * only part of it anyone wanted to read.
                                 */
                                $context = (array) $entry->context;
                                $isFlatMap = $context !== []
                                    && collect($context)->every(fn ($value): bool => is_scalar($value) || $value === null);
                            @endphp

                            <details class="group mt-1.5">
                                <summary
                                    class="inline-flex cursor-pointer list-none items-center gap-1 text-xs font-semibold text-navy-600 transition hover:text-navy-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400">
                                    <x-icon name="arrow-right"
                                        class="h-3 w-3 transition-transform group-open:rotate-90" />
                                    Context
                                </summary>

                                @if ($isFlatMap)
                                    <dl
                                        class="mt-1.5 space-y-1 rounded-lg border border-navy-100 bg-navy-50/60 px-3 py-2 text-xs">
                                        @foreach ($context as $key => $value)
                                            <div class="flex flex-wrap gap-x-2">
                                                <dt class="font-semibold text-navy-700">{{ \Illuminate\Support\Str::headline((string) $key) }}</dt>
                                                <dd class="min-w-0 break-words text-navy-800">
                                                    {{ $value === null ? '—' : (\Illuminate\Support\Str::limit((string) $value, 120)) }}
                                                </dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                @else
                                    <pre
                                        class="mt-1.5 overflow-x-auto rounded-lg border border-navy-100 bg-navy-50/60 p-3 font-mono text-xs text-navy-800">{{ json_encode($entry->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                            </details>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-staff.table>

        {{-- `always` because this list is nearly always filtered, and the line is
             what says whether the filter excluded anything. --}}
        <x-staff.pagination :paginator="$entries" total-label="entries" :always="true" />
    </div>
</x-app-layout>
