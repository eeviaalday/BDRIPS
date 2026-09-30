{{--
    A Kagawad's batches, with their own share of each.

    A Kagawad sees every batch, because a closed one is a record of a handout
    they worked - but the counts on it are only their own sectors. Showing the
    barangay-wide count here would tell them the size of a neighbouring purok's
    list, which is the one number the sector split is there to keep private.
--}}
<x-app-layout :user="auth()->user()" title="Relief Distribution">
    <div class="mx-auto w-full max-w-5xl space-y-6">
        <x-breadcrumb :items="[
            ['label' => 'Kagawad', 'route' => 'kagawad.dashboard'],
            ['label' => 'Relief Distribution'],
        ]" />

        <header>
            <h1 class="text-xl font-bold text-navy-900">Relief Distribution</h1>
            <p class="mt-1 text-sm text-gray-600">
                @if ($sectorIds !== [])
                    Your sectors:
                    <span class="font-semibold text-navy-800">
                        {{ $sectors->pluck('sector_name')->join(', ') }}
                    </span>
                @else
                    You have not been given a sector yet, so no checklist is showing.
                @endif
            </p>
        </header>

        @if (session('success'))
            <x-alert variant="success" icon="check-circle">{{ session('success') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert variant="warning" icon="x-circle">{{ session('error') }}</x-alert>
        @endif

        @if ($sectorIds === [])
            <x-card tone="soft" padding="p-8" class="text-center">
                <p class="text-sm leading-relaxed text-navy-700">
                    A Captain assigns a Kagawad to a sector from
                    <span class="font-semibold">Sectors &amp; Kagawad</span>. Until that is done there is
                    nothing to hand out from this account.
                </p>
            </x-card>
        @else
            <x-data-table :columns="[['label' => 'Batch'], ['label' => 'Date'], ['label' => 'Households'], ['label' => 'Collected'], ['label' => 'Remaining']]"
                caption="Relief batches" empty-title="No batches yet"
                empty-description="When the Secretary opens a batch that covers your sector, it appears here.">
                @foreach ($batches as $batch)
                    <tr @class([
                        'opacity-60' => ! $batch->isActive(),
                    ])>
                        <th scope="row" class="px-4 py-3 text-sm font-semibold text-navy-900">
                            <a href="{{ route('kagawad.relief.show', $batch) }}" class="hover:underline">{{ $batch->title }}</a>
                            @unless ($batch->isActive())
                                <x-status-badge status="inactive" size="sm" class="mt-1" />
                            @endunless
                        </th>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            {{ $batch->distribution_date?->format('j M Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm tabular-nums text-slate-600">{{ $batch->households_total }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-slate-600">{{ $batch->households_received }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-slate-600">
                            {{ $batch->households_total - $batch->households_received }}
                        </td>
                    </tr>
                @endforeach
            </x-data-table>

            <div>{{ $batches->links() }}</div>
        @endif
    </div>
</x-app-layout>
