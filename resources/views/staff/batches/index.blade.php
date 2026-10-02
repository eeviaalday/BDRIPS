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

        {{--
            The Show filter.

            The controller has always read ?status=active|closed and always defaulted
            it to "everything", but this page had no control for it — so the list
            could be narrowed by a bookmark or a dashboard card with nothing on
            screen to say so, and no way back to everything. A filter the reader
            cannot see and cannot clear is worse than no filter, because the numbers
            on the page then quietly stop matching the truth.

            "Everything" is the empty parameter, so an unfiltered page is the
            default and a link to the bare route shows every batch.
        --}}
        <x-staff.filter-bar :action="route('staff.batches.index')"
            :has-filters="$search !== '' || $status !== ''"
            :clear-href="route('staff.batches.index')">
            <x-staff.filter-field name="q" label="Search" :value="$search"
                placeholder="Batch title or description" />
            <x-staff.filter-select name="status" label="Show" all-label="Everything"
                :value="$status" :options="[
                    'active' => 'Open batches',
                    'closed' => 'Closed batches',
                ]" />
        </x-staff.filter-bar>

        <x-staff.table
            :columns="[
                ['label' => 'Batch'],
                ['label' => 'Distribution date'],
                ['label' => 'Households'],
                ['label' => 'Status'],
                ['label' => 'Actions'],
            ]"
            caption="Relief batches"
            :empty-title="$search !== '' || $status !== '' ? 'No batches match these filters' : 'No batches yet'"
            :empty-description="$search !== '' || $status !== ''
                ? 'Try a different search, or clear the filters to see every batch.'
                : 'Open one to start a distribution.'"
            :empty-action-label="$search !== '' || $status !== '' ? 'Clear filters' : null"
            :empty-action-href="$search !== '' || $status !== '' ? route('staff.batches.index') : null"
            empty-icon="gift">
            @foreach ($batches as $batch)
                <tr>
                    <th scope="row">
                        <x-staff.person-cell :name="$batch->title"
                            :secondary="'opened by '.($batch->createdBy?->name ?? 'unknown')" />
                    </th>
                    <td class="text-sm text-slate-600">{{ $batch->distribution_date?->format('j M Y') ?? 'Not set' }}</td>
                    {{-- No text-right. Every cell in the staff area is left-aligned,
                         numbers included, so a column of figures reads off the same
                         edge as the labels above it. --}}
                    <td class="text-sm tabular-nums text-slate-600">{{ $batch->distributions_count }}</td>
                    <td><x-staff.status-badge :status="$batch->status" size="sm" /></td>
                    <td>
                        <div class="flex items-center gap-1">
                            <x-staff.icon-action icon="eye" label="View {{ $batch->title }}"
                                :href="route('staff.batches.show', $batch)" />

                            {{--
                                Close only on an open batch. Not a disabled control:
                                there is nothing to disable, because a closed batch has
                                no Close to offer, and the Reopen control lives on the
                                batch's own page. Showing a dead padlock with a reason
                                would be a control that cannot do anything.
                            --}}
                            @if ($batch->isActive())
                                {{--
                                    The dialog is a sibling of the trigger, not a child,
                                    because it owns its own <form>. The trigger is a
                                    type="button" carrying its own x-data, so clicking
                                    it dispatches the event rather than submitting
                                    anything — which is the trap the shared dialog
                                    documents, and the reason the trigger is not a
                                    submit button inside that form.
                                --}}
                                <x-staff.confirm-dialog name="close-batch-{{ $batch->id }}"
                                    title="Close this batch?"
                                    message="The checklist stays readable and a Kagawad can still see the history. It just stops accepting new households."
                                    confirm-label="Close the batch" tone="danger" method="POST"
                                    :form="route('staff.batches.close', $batch)" />
                                <x-staff.icon-action icon="lock" label="Close {{ $batch->title }}"
                                    type="button" variant="danger"
                                    x-data
                                    x-on:click="$dispatch('open-modal', 'close-batch-{{ $batch->id }}')" />
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-staff.table>

        <x-staff.pagination :paginator="$batches" />
    </div>
</x-app-layout>