{{--
    The audit log, read only.

    There is no edit and no delete control anywhere on this page, by design. A
    trail an Admin can change is not a trail, and the absence of the buttons is
    the feature rather than an omission.
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

        <form method="GET" action="{{ route('staff.audit.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label for="q" class="block text-sm font-semibold text-navy-800">Search</label>
                <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Description or name"
                    class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
            </div>
            <div>
                <label for="action" class="block text-sm font-semibold text-navy-800">Action</label>
                <select id="action" name="action" class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                    <option value="">Every action</option>
                    @foreach ($actions as $key => $label)
                        <option value="{{ $key }}" @selected($action === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="actor" class="block text-sm font-semibold text-navy-800">Account</label>
                <select id="actor" name="actor" class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                    <option value="">Anyone</option>
                    @foreach ($actors as $actor)
                        <option value="{{ $actor->id }}" @selected((string) $actorId === (string) $actor->id)>{{ $actor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label for="from" class="block text-sm font-semibold text-navy-800">From</label>
                    <input id="from" name="from" type="date" value="{{ $from }}"
                        class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                </div>
                <div>
                    <label for="to" class="block text-sm font-semibold text-navy-800">To</label>
                    <input id="to" name="to" type="date" value="{{ $to }}"
                        class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                </div>
            </div>

            <div class="flex items-end gap-2 lg:col-span-5">
                <x-btn type="submit" variant="navy" size="md" icon="search">Filter</x-btn>
                @if ($search !== '' || $action !== '' || $actorId || $from !== '' || $to !== '')
                    <x-btn :href="route('staff.audit.index')" variant="ghost" size="md">Clear</x-btn>
                @endif
            </div>
        </form>

        <x-data-table :columns="[['label' => 'When'], ['label' => 'Who'], ['label' => 'Action'], ['label' => 'Detail']]"
            caption="Audit log" empty-title="Nothing logged"
            empty-description="No entry matches the current filter.">
            @foreach ($entries as $entry)
                <tr>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-500">
                        {{ $entry->created_at?->format('j M Y') }}
                        <span class="block text-xs">{{ $entry->created_at?->format('g:i a') }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-navy-900">
                        {{ $entry->user?->name ?? 'Someone removed' }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="rounded-full bg-navy-50 px-2.5 py-1 text-xs font-medium text-navy-800">
                            {{ $entry->actionLabel() }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        {{ $entry->description }}
                        @if ($entry->context)
                            <details class="mt-1">
                                <summary class="cursor-pointer text-xs text-navy-600">Context</summary>
                                <pre class="mt-1 overflow-x-auto rounded-lg bg-navy-50 p-2 text-xs text-navy-800">{{ json_encode($entry->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div>{{ $entries->links() }}</div>
    </div>
</x-app-layout>
