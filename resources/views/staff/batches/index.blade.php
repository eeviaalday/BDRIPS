{{--
    The relief batches list.

    A batch is a delivery, so the batch title leads and the date it goes out on
    follows. "Opened by" is the second line under the title rather than a column of
    its own: it is provenance rather than something to sort or scan, and the four
    columns that carry the working figures fit a laptop better without it.
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

        <x-staff.table
            :columns="[
                ['label' => 'Batch'],
                ['label' => 'Distribution date'],
                ['label' => 'Households', 'align' => 'right'],
                ['label' => 'Status'],
            ]"
            caption="Relief batches"
            empty-title="No batches yet"
            empty-description="Open one to start a distribution."
            empty-icon="gift">
            @foreach ($batches as $batch)
                <tr x-on:click="openRow($event, @js(route('staff.batches.show', $batch)))">
                    <th scope="row">
                        <x-staff.person-cell :name="$batch->title"
                            :secondary="'opened by '.($batch->createdBy?->name ?? 'unknown')"
                            :href="route('staff.batches.show', $batch)" chevron />
                    </th>
                    <td class="text-sm text-slate-600">{{ $batch->distribution_date?->format('j M Y') ?? 'Not set' }}</td>
                    <td class="text-right text-sm tabular-nums text-slate-600">{{ $batch->distributions_count }}</td>
                    <td><x-staff.status-badge :status="$batch->status" size="sm" /></td>
                </tr>
            @endforeach
        </x-staff.table>

        <x-staff.pagination :paginator="$batches" />
    </div>
</x-app-layout>