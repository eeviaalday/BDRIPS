{{--
    One batch, with progress by sector.

    Progress is one grouped read rather than a count per sector, because this
    page shows every sector at once and N+1 counts here would be the slowest
    thing on the screen.
--}}
<x-app-layout :user="auth()->user()" :title="$batch->title">
    <div class="mx-auto w-full max-w-5xl space-y-6">
        <x-breadcrumb :items="[
            ['label' => 'Staff', 'route' => 'staff.dashboard'],
            ['label' => 'Relief Distribution', 'route' => 'staff.batches.index'],
            ['label' => $batch->title],
        ]" />

        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-navy-900">{{ $batch->title }}</h1>
                <p class="mt-1 text-sm text-gray-600">
                    {{ $batch->distribution_date?->format('j M Y') ?? 'No date set' }}
                    &middot; opened by {{ $batch->createdBy?->name ?? 'unknown' }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <x-status-badge :status="$batch->status" />
                @if ($batch->isActive())
                    <x-confirm-dialog name="close-batch"
                        title="Close this batch?"
                        message="The checklist stays readable and a Kagawad can still see the history. It just stops accepting new households."
                        confirm-label="Close the batch" tone="danger" method="POST"
                        :form="route('staff.batches.close', $batch)" />
                    <x-btn type="button" variant="danger" size="sm" icon="lock"
                        x-on:click="$dispatch('open-modal', 'close-batch')">Close batch</x-btn>
                @else
                    <form method="POST" action="{{ route('staff.batches.reopen', $batch) }}">
                        @csrf
                        <x-btn type="submit" variant="outline" size="sm" icon="check-circle">Reopen</x-btn>
                    </form>
                @endif
            </div>
        </header>

        @if (session('success'))
            <x-alert variant="success" icon="check-circle">{{ session('success') }}</x-alert>
        @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat-card label="Households on the list" :value="$progress['total']" tone="navy" icon="users" variant="badged" />
            <x-stat-card label="Collected" :value="$progress['received']" tone="green" icon="check-circle" variant="badged" />
            <x-stat-card label="Still to collect" :value="$progress['pending']" tone="gold" icon="clock" variant="badged" />
        </div>

        @if ($batch->items() !== [])
            <x-card tone="white" padding="p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Goods in this batch</h2>
                <ul class="flex flex-wrap gap-2">
                    @foreach ($batch->items() as $item)
                        <li class="rounded-full bg-navy-50 px-3 py-1 text-xs font-medium text-navy-800">{{ $item }}</li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <x-card tone="white" padding="p-6">
            <h2 class="mb-4 text-base font-bold text-navy-900">Progress by sector</h2>

            <x-data-table :columns="[['label' => 'Sector'], ['label' => 'Kagawad'], ['label' => 'Households'], ['label' => 'Collected'], ['label' => 'Remaining']]"
                caption="Progress by sector" empty-title="No households on this list"
                empty-description="Open the batch with at least one sector selected to add households.">
                @foreach ($sectors as $row)
                    <tr>
                        <th scope="row" class="px-4 py-3 text-sm font-semibold text-navy-900">{{ $row['sector']->sector_name }}</th>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $row['sector']->kagawad?->name ?? 'Unassigned' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $row['total'] }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $row['received'] }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $row['total'] - $row['received'] }}</td>
                    </tr>
                @endforeach
            </x-data-table>
        </x-card>
    </div>
</x-app-layout>
