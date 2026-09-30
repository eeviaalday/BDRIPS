{{--
    Relief batches, and the form for opening one.

    Opening a batch seeds a checklist row for every verified household, so this
    screen is where a distribution becomes something a Kagawad can work through
    rather than a notice nobody acts on.
--}}
<x-app-layout :user="auth()->user()" title="Relief Distribution">
    <div class="mx-auto w-full max-w-6xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'Relief Distribution']]" />

        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-navy-900">Relief Distribution</h1>
                <p class="mt-1 text-sm text-gray-600">Open a batch, watch it by sector, close it when the goods are gone.</p>
            </div>
            <x-btn :href="route('staff.batches.create')" variant="navy" size="md" icon="gift">Open a batch</x-btn>
        </header>

        <x-data-table :columns="[['label' => 'Batch'], ['label' => 'Distribution date'], ['label' => 'Households'], ['label' => 'Status']]"
            caption="Relief batches" empty-title="No batches yet"
            empty-description="Open one to start a distribution.">
            @foreach ($batches as $batch)
                <tr>
                    <th scope="row" class="px-4 py-3 text-sm font-semibold text-navy-900">
                        <a href="{{ route('staff.batches.show', $batch) }}" class="hover:underline">{{ $batch->title }}</a>
                        <span class="block text-xs font-normal text-slate-500">opened by {{ $batch->createdBy?->name ?? 'unknown' }}</span>
                    </th>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $batch->distribution_date?->format('j M Y') ?? 'Not set' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $batch->distributions_count }}</td>
                    <td class="px-4 py-3"><x-status-badge :status="$batch->status" size="sm" /></td>
                </tr>
            @endforeach
        </x-data-table>

        <div>{{ $batches->links() }}</div>
    </div>
</x-app-layout>
