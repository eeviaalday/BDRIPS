<x-app-layout>
    <x-slot name="title">Household Checklist</x-slot>

    @php
        $received = $distribution->where('status', \App\Models\ReliefDistribution::STATUS_RECEIVED)->count();
        $pending = $distribution->where('status', \App\Models\ReliefDistribution::STATUS_PENDING)->count();
    @endphp

    <div class="space-y-5">
        <x-breadcrumb :items="[
            ['label' => 'My Sector', 'route' => 'kagawad.dashboard'],
            ['label' => $batch->title],
            ['label' => $sector->sector_name],
        ]" />

        <x-card tone="white" class="bg-navy-50/50">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-lg font-bold text-navy-900">{{ $batch->title }}</h1>
                    <p class="mt-1 text-sm text-navy-700">
                        {{ $sector->sector_name }}
                        @if ($sector->purok_name) &middot; {{ $sector->purok_name }} @endif
                        &middot; {{ $batch->distribution_date?->format('M d, Y') ?? 'Date to be confirmed' }}
                    </p>
                </div>
                <div class="flex gap-2 text-sm">
                    <span class="rounded-lg bg-emerald-50 px-3 py-1.5 font-semibold text-emerald-800">{{ $received }} received</span>
                    <span class="rounded-lg bg-amber-50 px-3 py-1.5 font-semibold text-amber-800">{{ $pending }} pending</span>
                </div>
            </div>
        </x-card>

        <x-filter-bar
            :action="route('kagawad.checklist', [$batch, $sector])"
            label="Household number or receiver"
            placeholder="e.g. HH-0001"
            :selects="[[
                'name' => 'status',
                'label' => 'Receipt status',
                'options' => [
                    '' => 'All households',
                    'pending' => 'Not yet received',
                    'received' => 'Received',
                ],
            ]]" />

        @if ($distribution->isEmpty())
            <x-card tone="white">
                @if ($search !== '' || ($status ?? '') !== '')
                    <x-empty-state icon="search" title="No matching households"
                        description="Nothing in this checklist matches. Try a different household number, or clear the filters.">
                        <a href="{{ route('kagawad.checklist', [$batch, $sector]) }}"
                            class="mt-5 inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-4 py-2 text-sm font-semibold text-navy-800 transition hover:bg-navy-50">
                            Clear filters
                        </a>
                    </x-empty-state>
                @else
                    <x-empty-state icon="inbox" title="No households in this batch"
                        description="This batch has no households mapped to your purok. Batches map verified profiles automatically, so an empty list usually means no resident in your sector is verified yet." />
                @endif
            </x-card>
        @else
            <x-card tone="white" padding="p-0">
                <x-data-table
                    class="rounded-none border-0"
                    :caption="'Household checklist for '.$sector->sector_name"
                    :columns="[
                        ['label' => 'Household #'],
                        ['label' => 'Received by'],
                        ['label' => 'Status'],
                        ['label' => 'Logged'],
                        ['label' => 'Action'],
                    ]">

                    @foreach ($distribution as $row)
                        <tr class="hover:bg-navy-50/40">
                            <td class="px-4 py-3 align-middle font-mono text-sm font-semibold text-navy-900">
                                {{ $row->household_number }}
                            </td>

                            <td class="px-4 py-3 align-middle text-navy-700">
                                @if ($row->received_by_name)
                                    {{ $row->received_by_name }}
                                @else
                                    <span class="text-navy-400">Not recorded</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 align-middle">
                                <x-status-badge
                                    :status="$row->status === \App\Models\ReliefDistribution::STATUS_RECEIVED ? 'completed' : 'submitted'"
                                    size="sm" />
                            </td>

                            <td class="px-4 py-3 align-middle whitespace-nowrap text-sm text-navy-600">
                                @if ($row->claimed_at)
                                    {{ $row->claimed_at->format('M d, Y') }}
                                    @if ($row->loggedBy)
                                        <span class="block text-xs text-navy-500">by {{ $row->loggedBy->name }}</span>
                                    @endif
                                @else
                                    <span class="text-navy-400">&mdash;</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 align-middle text-right">
                                @if ($row->status === \App\Models\ReliefDistribution::STATUS_PENDING)
                                    <button type="button"
                                        class="inline-flex items-center justify-center rounded-lg bg-navy px-3 py-2 text-xs font-semibold text-white transition hover:bg-navy-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400"
                                        x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'received-{{ $row->id }}' }))">
                                        Mark received
                                    </button>

                                    {{-- The confirmation carries the household number
                                         back into the form so the kagawad can record who
                                         actually collected. One form, not a copy. --}}
                                    <x-modal name="received-{{ $row->id }}" maxWidth="md">
                                        <form method="POST" action="{{ route('kagawad.distributions.update', $row) }}">
                                            @csrf

                                            <div class="bg-white px-6 pb-2 pt-6">
                                                <h2 class="text-lg font-bold text-navy-900">
                                                    Record collection for {{ $row->household_number }}
                                                </h2>
                                                <p class="mt-2 text-sm leading-relaxed text-navy-700">
                                                    Confirm goods were handed to this household. Leave
                                                    the name blank to record the registered
                                                    account holder.
                                                </p>

                                                <input type="hidden" name="status" value="received">

                                                <div class="mt-4">
                                                    <label for="received_by_name_{{ $row->id }}"
                                                        class="block text-sm font-semibold text-navy-900">
                                                        Received by <span class="font-normal text-navy-500">(optional)</span>
                                                    </label>
                                                    <input id="received_by_name_{{ $row->id }}"
                                                        name="received_by_name" type="text" maxlength="255"
                                                        placeholder="Name of the person collecting"
                                                        class="mt-1.5 block w-full rounded-lg border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 transition placeholder:text-navy-400 focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none" />
                                                    @error('received_by_name')
                                                        <p class="mt-1.5 text-xs font-medium text-red-700">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="flex flex-col-reverse gap-2 bg-navy-50/50 px-6 py-4 sm:flex-row sm:justify-end">
                                                <button type="button"
                                                    class="inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-4 py-2.5 text-sm font-semibold text-navy-800 transition hover:bg-navy-50"
                                                    x-on:click="show = false">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                    class="inline-flex items-center justify-center rounded-lg bg-navy px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
                                                    Confirm collection
                                                </button>
                                            </div>
                                        </form>
                                    </x-modal>
                                @else
                                    {{--
                                        A correction, not an undo. Reverting clears
                                        received_by_name but KEEPS claimed_at and
                                        logged_by_user_id in the controller, so the
                                        record that a claim was ever logged survives
                                        the correction - which is what makes this
                                        safe to offer at all.
                                    --}}
                                    <button type="button"
                                        class="inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-3 py-2 text-xs font-semibold text-navy-800 transition hover:bg-navy-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400"
                                        x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'revert-{{ $row->id }}' }))">
                                        Correct to not received
                                    </button>

                                    <x-modal name="revert-{{ $row->id }}" maxWidth="md">
                                        <form method="POST" action="{{ route('kagawad.distributions.update', $row) }}">
                                            @csrf

                                            <div class="bg-white px-6 pb-2 pt-6">
                                                <h2 class="text-lg font-bold text-navy-900">
                                                    Correct {{ $row->household_number }}?
                                                </h2>
                                                <p class="mt-2 text-sm leading-relaxed text-navy-700">
                                                    This sets the household back to not received and clears
                                                    the recorded name. The fact that a claim was
                                                    logged{{ $row->claimed_at ? ' on '.$row->claimed_at->format('M d, Y') : '' }}
                                                    {{ $row->loggedBy ? ' by '.$row->loggedBy->name : '' }}
                                                    is kept on the record.
                                                </p>

                                                <input type="hidden" name="status" value="pending">
                                            </div>

                                            <div class="flex flex-col-reverse gap-2 bg-navy-50/50 px-6 py-4 sm:flex-row sm:justify-end">
                                                <button type="button"
                                                    class="inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-4 py-2.5 text-sm font-semibold text-navy-800 transition hover:bg-navy-50"
                                                    x-on:click="show = false">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                    class="inline-flex items-center justify-center rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-700">
                                                    Confirm correction
                                                </button>
                                            </div>
                                        </form>
                                    </x-modal>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-data-table>
            </x-card>
        @endif
    </div>
</x-app-layout>
