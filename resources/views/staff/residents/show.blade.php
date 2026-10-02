{{--
    One resident's record, as staff see it.

    FIVE CARDS, IN THE ORDER A CLERK WORKS THROUGH THEM: who the person is, which
    household they belong to, how to reach them, what state the account is in, and
    what they have actually asked for and received. The order is the point - a
    verification queue is worked by reading down a page, and a single card of
    fifteen rows is a form to squint at rather than a record to read.

    The national ID is MASKED HERE and is not on the edit form. It is government
    identity evidence, and a record page is a screen that gets left open, gets
    shoulder-surfed, and gets printed. Staff who need the whole number open the
    uploaded document, which is where it was captured from in the first place, and
    every time they do it is recorded as resident.id_viewed.
--}}
@php
    /*
     * Read once here so the mask and the emptiness check below cannot disagree
     * about whether there is an ID at all.
     */
    $nationalId = $profile->national_or_voter_id;
@endphp
<x-app-layout :user="auth()->user()" :title="$profile->user?->name">
    <div class="mx-auto w-full max-w-6xl space-y-6">
        <x-breadcrumb :items="[
            ['label' => 'Staff', 'route' => 'staff.dashboard'],
            ['label' => 'Residents', 'route' => 'staff.residents.index'],
            ['label' => $profile->user?->name ?? 'Resident'],
        ]" />

        {{-- The layout renders success and error, but not info, and the layout is
             shared with every other staff page. A no-op save is not an error and
             must not be dressed as one, so the notice is rendered here. --}}
        @if (session('info'))
            <x-alert variant="info" icon="help-circle">{{ session('info') }}</x-alert>
        @endif

        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-navy-900">{{ $profile->user?->name }}</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Household {{ $profile->household_number }} &middot; {{ $profile->sector?->sector_name ?? 'No sector' }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-staff.status-badge :status="$profile->verification_status" />
                <x-btn :href="route('staff.residents.edit', $profile)" variant="primary" size="sm" icon="edit">Edit</x-btn>
            </div>
        </header>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- 1. Personal information. The name is here rather than in the
                 header alone because this is the record, and a record states its
                 subject rather than implying it. --}}
            <x-card tone="white" padding="p-6">
                <h2 class="mb-4 text-base font-bold text-navy-900">Personal information</h2>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Full name</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $profile->user?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date of birth</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $profile->user?->date_of_birth?->format('j M Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gender</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $profile->user?->gender ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Civil status</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $profile->user?->civil_status ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nationality</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $profile->user?->nationality ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">National / voter ID</dt>
                        <dd class="mt-1 font-mono text-sm text-navy-900">
                            @if (filled($nationalId))
                                {{-- All but the last four. Enough to tell two documents
                                     apart, not enough to be a credential on screen. --}}
                                @php($maskedId = strlen($nationalId) > 4
                                    ? str_repeat('•', min(strlen($nationalId) - 4, 8)).substr($nationalId, -4)
                                    : str_repeat('•', 4).$nationalId)
                                {{ $maskedId }}
                            @else
                                <span class="text-slate-500">Not supplied</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-card>

            {{-- 2. Household and sector. Household number is monospaced because it
                 is an identifier that gets read aloud and written onto paper forms,
                 and proportional figures make 1 and 7 hard to tell apart. --}}
            <x-card tone="white" padding="p-6">
                <h2 class="mb-4 text-base font-bold text-navy-900">Household and sector</h2>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Household number</dt>
                        <dd class="mt-1 font-mono text-sm text-navy-900">{{ $profile->household_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sector</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $profile->sector?->sector_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Purok</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $profile->sector?->purok_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Registered</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $profile->user?->created_at?->format('j M Y') ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Address</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $profile->address }}</dd>
                    </div>
                </dl>
            </x-card>

            {{-- 3. Contact. The email is the only contact address this system
                 holds: there is no phone column on either table, so the card says
                 so rather than showing an empty field a clerk would try to fill in
                 somewhere else. --}}
            <x-card tone="white" padding="p-6">
                <h2 class="mb-4 text-base font-bold text-navy-900">Contact</h2>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email address</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $profile->user?->email ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Phone number</dt>
                        <dd class="mt-1 text-sm text-slate-500">
                            Not recorded. This system holds no phone number for residents;
                            change it at the barangay office if one is needed.
                        </dd>
                    </div>
                </dl>
            </x-card>

            {{-- 4. Account and verification status. Separated from the personal
                 card because these are the two things a Secretary must never edit
                 while correcting a typo: an account credential and a verification
                 decision. --}}
            <x-card tone="white" padding="p-6">
                <h2 class="mb-4 text-base font-bold text-navy-900">Account and verification</h2>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Verification</dt>
                        <dd class="mt-1"><x-staff.status-badge :status="$profile->verification_status" size="sm" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Verified on</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $profile->verified_at?->format('j M Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email confirmed</dt>
                        <dd class="mt-1 text-sm text-navy-900">
                            {{ $profile->user?->hasVerifiedEmail() ? 'Yes' : 'No' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Account role</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ ucfirst($profile->user?->role ?? '—') }}</dd>
                    </div>
                    @if (filled($profile->rejection_reason))
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reason for rejection</dt>
                            <dd class="mt-1 text-sm text-navy-900">{{ $profile->rejection_reason }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-5 flex flex-wrap gap-2 border-t border-navy-100 pt-5">
                    <x-btn :href="route('staff.verifications.show', $profile)" variant="outline" size="sm" icon="id-badge">Verification</x-btn>
                    @if ($profile->id_photo_path)
                        <x-btn :href="route('staff.resident-verifications.id-photo', $profile)" variant="ghost" size="sm" icon="document">View ID</x-btn>
                    @endif
                </div>
            </x-card>
        </div>

        {{-- 5. Request and relief history, as a summary first and the detail
             beneath it. A summary because the answer to "has this household been
             served" is one glance, and a table because the answer to "when, and
             what" is not. --}}
        <x-card tone="white" padding="p-6">
            <h2 class="mb-4 text-base font-bold text-navy-900">Requests and relief history</h2>

            <dl class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-navy-100 bg-navy-50/40 px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Document requests</dt>
                    <dd class="mt-1 text-lg font-bold text-navy-900">{{ $requests->count() }}</dd>
                </div>
                <div class="rounded-lg border border-navy-100 bg-navy-50/40 px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Relief entries</dt>
                    <dd class="mt-1 text-lg font-bold text-navy-900">{{ $relief->count() }}</dd>
                </div>
                <div class="rounded-lg border border-navy-100 bg-navy-50/40 px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Received</dt>
                    <dd class="mt-1 text-lg font-bold text-navy-900">{{ $relief->where('status', 'received')->count() }}</dd>
                </div>
            </dl>

            <h3 class="mb-3 mt-6 text-sm font-bold text-navy-900">Document requests</h3>
            <x-staff.table :columns="[
                ['label' => 'Reference'],
                ['label' => 'Document'],
                ['label' => 'Status'],
                ['label' => 'Actions'],
            ]"
                caption="Document requests by this resident"
                empty-title="No requests"
                empty-description="This resident has not asked for a document."
                empty-icon="document">
                @foreach ($requests as $documentRequest)
                    <tr>
                        <th scope="row">
                            <x-staff.person-cell :name="$documentRequest->reference_number" mono />
                        </th>
                        <td class="text-sm text-navy-800">{{ $documentRequest->documentType?->label ?? 'Retired document' }}</td>
                        <td><x-staff.status-badge :status="$documentRequest->status" size="sm" /></td>
                        <td>
                            <div class="flex items-center gap-1">
                                <x-staff.icon-action icon="eye"
                                    label="Review request {{ $documentRequest->reference_number }}"
                                    :href="route('staff.requests.show', $documentRequest)" />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-staff.table>

            <h3 class="mb-3 mt-6 text-sm font-bold text-navy-900">Relief received</h3>
            <x-staff.table :columns="[
                ['label' => 'Batch'],
                ['label' => 'Distributed'],
                ['label' => 'Status'],
            ]"
                caption="Relief batches this household appears on"
                :empty-title="$relief->isEmpty() ? 'No relief recorded' : 'No relief history'"
                empty-description="This household does not appear on any relief batch checklist."
                empty-icon="gift">
                @foreach ($relief as $entry)
                    <tr>
                        <th scope="row">
                            <x-staff.person-cell :name="$entry->batch?->title ?? 'Retired batch'" />
                        </th>
                        <td class="text-sm text-navy-800">{{ $entry->batch?->distribution_date?->format('j M Y') ?? '—' }}</td>
                        <td><x-staff.status-badge :status="$entry->status" size="sm" /></td>
                    </tr>
                @endforeach
            </x-staff.table>

            {{-- Stated rather than left to be discovered. A household number is the
                 key every relief row is filed under, so correcting one orphans the
                 history recorded under the old one - and that history stays in the
                 database, visible to nobody from this screen. --}}
            @if ($relief->isNotEmpty())
                <p class="mt-4 text-xs text-slate-500">
                    Relief history is matched on the household number above. Correcting it will not move
                    the rows already recorded under the old number.
                </p>
            @endif
        </x-card>
    </div>
</x-app-layout>