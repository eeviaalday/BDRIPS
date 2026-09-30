{{--
    A resident's own relief record.

    No search box, and there is not going to be one. Relief follows the
    household, and a household number is short and sequential enough to walk in
    order - so an open lookup would let a resident read a neighbour's record by
    trying the next one. The number shown is their own, and the rows are theirs.

    A pending resident is told relief is locked. An empty table would be a
    different, and wrong, answer.
--}}
<x-public-layout>
    <x-slot name="title">Relief Transparency</x-slot>

    <x-page-header :crumbs="['Relief Transparency']" title="My Relief Record"
        description="What your household has received from each barangay relief batch." />

    <x-shell>
        @if (! $isVerified)
            <x-alert variant="warning" icon="lock">
                Relief records unlock once the Barangay Secretary has verified your details.
                You can see which batches are currently going out below.
                <a href="{{ route('resident.verification') }}" class="font-semibold underline">Check your verification status</a>.
            </x-alert>
        @else
            <x-alert variant="info" icon="shield">
                These are the records for household
                <strong class="font-mono font-semibold">{{ $householdNumber }}</strong> only.
            </x-alert>
        @endif

        <x-card tone="white" padding="p-6">
            <h2 class="mb-4 text-base font-bold text-navy-900">Batches going out now</h2>

            @if ($activeBatches->isEmpty())
                <x-empty-state icon="gift" title="No batch is running"
                    description="When the barangay opens a relief batch it will be listed here." />
            @else
                <ul class="space-y-3">
                    @foreach ($activeBatches as $batch)
                        <li class="rounded-lg border border-navy-100 bg-navy-50/40 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-navy-900">{{ $batch->title }}</p>
                                    @if ($batch->description)
                                        <p class="mt-1 text-sm text-slate-600">{{ $batch->description }}</p>
                                    @endif
                                    @if ($batch->items() !== [])
                                        <p class="mt-1.5 text-xs text-slate-500">
                                            Includes: {{ implode(', ', $batch->items()) }}
                                        </p>
                                    @endif
                                </div>
                                <div class="shrink-0 text-right">
                                    <x-status-badge :status="$batch->status" size="sm" />
                                    @if ($batch->distribution_date)
                                        <p class="mt-1.5 text-xs text-slate-500">{{ $batch->distribution_date->format('j M Y') }}</p>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        @if ($isVerified)
            <x-card tone="white" padding="p-6">
                <h2 class="mb-4 text-base font-bold text-navy-900">What my household has received</h2>

                @if ($distributions->isEmpty())
                    <x-empty-state icon="archive" title="Nothing recorded yet"
                        description="Once a Kagawad marks your household as having collected, it will be listed here." />
                @else
                    <x-data-table :columns="[
                        ['label' => 'Batch'],
                        ['label' => 'Distributed'],
                        ['label' => 'Status'],
                        ['label' => 'Collected by'],
                    ]"
                        variant="resident"
                        caption="Relief collected by your household">
                        @foreach ($distributions as $distribution)
                            <tr>
                                <th scope="row" class="px-4 py-3 text-sm font-semibold text-navy-900">
                                    {{ $distribution->batch?->title ?? 'Removed batch' }}
                                </th>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    {{ $distribution->batch?->distribution_date?->format('j M Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-status-badge :status="$distribution->status" size="sm" soft />
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    {{ $distribution->received_by_name ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </x-data-table>
                @endif
            </x-card>
        @endif
    </x-shell>
</x-public-layout>
