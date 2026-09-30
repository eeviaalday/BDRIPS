{{--
    A resident's own document requests.

    The slot count is shown from the same constant the server enforces, so the
    figure a resident reads is the figure the POST will check. A resident who is
    pending still sees everything they already have: a hold stops new requests,
    it does not hide the old ones.
--}}
<x-public-layout>
    <x-slot name="title">Document Requests</x-slot>

    <x-shell>
        <x-page-header :crumbs="['Document Requests']" title="My Document Requests"
            description="Requests you have submitted, and where each one stands.">
            {{-- The default slot, not a named one: x-page-header renders only the
                 default slot, so a named "actions" slot would be dropped. --}}
            <div class="flex flex-wrap items-center gap-2">
                <x-btn :href="route('resident.requests.create')" variant="navy" size="sm" icon="document">
                    New request
                </x-btn>
            </div>
        </x-page-header>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat-card label="Open requests" :value="$openCount" :hint="'of '.$limit.' allowed'" tone="navy" icon="document" variant="badged" />
            <x-stat-card label="Slots remaining" :value="$slotsRemaining" tone="gold" icon="check-circle" variant="badged" />
            <x-stat-card label="Total on file" :value="$requests->count()" tone="sky" icon="archive" variant="badged" />
        </div>

        @if (! $canCreate)
            <x-alert variant="warning" icon="lock">
                @if (! auth()->user()->residentProfile?->isVerified())
                    You can follow the requests below, but new requests stay locked until the
                    Barangay Secretary has verified your details.
                    <a href="{{ route('resident.verification') }}" class="font-semibold underline">Check your verification status</a>.
                @elseif (auth()->user()->isRestricted())
                    This account is temporarily restricted, so new requests cannot be submitted.
                @else
                    You have all {{ $limit }} requests open at once. Wait for one to be collected
                    or rejected before applying for another.
                @endif
            </x-alert>
        @endif

        @if ($requests->isEmpty())
            <x-empty-state icon="document" title="No requests yet"
                description="When you apply for a barangay document it will appear here with its reference number.">
                <x-btn :href="route('resident.requests.create')" variant="navy" size="sm" class="mt-5">Request a document</x-btn>
            </x-empty-state>
        @else
            <x-data-table
                :columns="[
                    ['label' => 'Reference'],
                    ['label' => 'Document'],
                    ['label' => 'Submitted'],
                    ['label' => 'Status'],
                ]"
                caption="Your document requests"
                empty-title="No requests yet">
                @foreach ($requests as $documentRequest)
                    <tr>
                        <th scope="row" class="px-4 py-3 font-mono text-xs font-semibold text-navy-900">
                            <a href="{{ route('resident.requests.show', $documentRequest) }}" class="hover:underline">
                                {{ $documentRequest->reference_number }}
                            </a>
                        </th>
                        <td class="px-4 py-3 text-sm text-navy-800">
                            {{ $documentRequest->documentType?->label ?? 'Retired document' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            {{ $documentRequest->created_at?->format('j M Y') }}
                        </td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$documentRequest->status" size="sm" />
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        @endif
    </x-shell>
</x-public-layout>
