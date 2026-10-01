{{--
    One resident's record, as staff see it: the details, the documents they have
    asked for, and a link to the verification screen.
--}}
<x-app-layout :user="auth()->user()" :title="$profile->user?->name">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <x-breadcrumb :items="[
            ['label' => 'Staff', 'route' => 'staff.dashboard'],
            ['label' => 'Residents', 'route' => 'staff.residents.index'],
            ['label' => $profile->user?->name ?? 'Resident'],
        ]" />

        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-navy-900">{{ $profile->user?->name }}</h1>
                <p class="mt-1 text-sm text-gray-600">{{ $profile->user?->email }}</p>
            </div>
            <x-staff.status-badge :status="$profile->verification_status" />
        </header>

        <x-card tone="white" padding="p-6">
            <h2 class="mb-4 text-base font-bold text-navy-900">Record</h2>
            <dl class="grid gap-4 sm:grid-cols-3">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Household</dt><dd class="mt-1 font-mono text-sm text-navy-900">{{ $profile->household_number }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sector</dt><dd class="mt-1 text-sm text-navy-900">{{ $profile->sector?->sector_name ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">National / voter ID</dt><dd class="mt-1 text-sm text-navy-900">{{ $profile->national_or_voter_id ?? 'Not supplied' }}</dd></div>
                <div class="sm:col-span-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Address</dt><dd class="mt-1 text-sm text-navy-900">{{ $profile->address }}</dd></div>
            </dl>

            <div class="mt-5 flex flex-wrap gap-2 border-t border-navy-100 pt-5">
                <x-btn :href="route('staff.verifications.show', $profile)" variant="outline" size="sm" icon="id-badge">Verification</x-btn>
                @if ($profile->id_photo_path)
                    <x-btn :href="route('staff.resident-verifications.id-photo', $profile)" variant="ghost" size="sm" icon="document">View ID</x-btn>
                @endif
            </div>
        </x-card>

        <x-card tone="white" padding="p-6">
            <h2 class="mb-4 text-base font-bold text-navy-900">Document requests</h2>

            <x-staff.table :columns="[['label' => 'Reference'], ['label' => 'Document'], ['label' => 'Status']]"
                    caption="Document requests by this resident"
                    empty-title="No requests"
                    empty-description="This resident has not asked for a document."
                    empty-icon="document">
                    @foreach ($requests as $documentRequest)
                        <tr x-on:click="openRow($event, @js(route('staff.requests.show', $documentRequest)))">
                            <th scope="row">
                                <x-staff.person-cell :name="$documentRequest->reference_number" mono
                                    :href="route('staff.requests.show', $documentRequest)" chevron />
                            </th>
                            <td class="text-sm text-navy-800">{{ $documentRequest->documentType?->label ?? 'Retired document' }}</td>
                            <td><x-staff.status-badge :status="$documentRequest->status" size="sm" /></td>
                        </tr>
                    @endforeach
                </x-staff.table>
        </x-card>
    </div>
</x-app-layout>
