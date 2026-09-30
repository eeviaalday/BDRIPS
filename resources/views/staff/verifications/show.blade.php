<x-app-layout>
    <x-slot name="title">Review {{ $profile->user?->name ?? 'Resident' }}</x-slot>

    <div class="space-y-5">
        <x-breadcrumb :items="[
            ['label' => 'Staff', 'route' => 'staff.dashboard'],
            ['label' => 'Resident Verification', 'route' => 'staff.verifications.index'],
            ['label' => $profile->user?->name ?? 'Resident'],
        ]" />

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

            {{-- ============================================================
                 THE ID DOCUMENT
                 ============================================================
                 Served by ResidentIdPhotoController, which is role-gated to
                 admin and secretary, sets X-Content-Type-Options: nosniff and
                 Cache-Control: no-store, and streams from the PRIVATE disk.

                 There is deliberately no direct link to storage/app/private and
                 no public URL: an ID document is a personal record, and the only
                 way to it is through a route that authorises the read.
            --}}
            <x-card tone="white" class="lg:col-span-1">
                <h2 class="text-base font-bold text-navy-900">Submitted ID</h2>

                @if ($profile->id_photo_path)
                    <a href="{{ route('staff.resident-verifications.id-photo', $profile) }}" target="_blank" rel="noopener"
                        class="mt-3 block overflow-hidden rounded-xl border border-navy-200 transition hover:border-navy-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400">
                        <img src="{{ route('staff.resident-verifications.id-photo', $profile) }}"
                            alt="The valid ID submitted by {{ $profile->user?->name ?? 'this resident' }}"
                            class="max-h-96 w-full bg-navy-50 object-contain" />
                        <span class="flex items-center justify-center gap-1.5 bg-navy-50 px-3 py-2 text-xs font-semibold text-navy-700">
                            <x-icon name="search" class="h-3.5 w-3.5" />
                            Open full size
                        </span>
                    </a>
                @else
                    <x-empty-state icon="id-badge" title="No ID on file"
                        description="This account has no uploaded ID. It may predate the registration wizard - check the master list before approving."
                        class="mt-3" />
                @endif
            </x-card>

            <div class="space-y-5 lg:col-span-2">

                <x-card tone="white">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h1 class="text-lg font-bold text-navy-900">{{ $profile->user?->name ?? 'Unknown resident' }}</h1>
                            <p class="mt-0.5 text-sm text-navy-600">{{ $profile->user?->email }}</p>
                        </div>
                        <x-status-badge :status="$profile->verification_status" />
                    </div>

                    <dl class="mt-5 grid grid-cols-1 gap-4 border-t border-navy-100 pt-5 sm:grid-cols-2">
                        @foreach ([
                            'Date of birth' => $profile->user?->date_of_birth?->format('M d, Y'),
                            'Gender' => $profile->user?->gender,
                            'Civil status' => $profile->user?->civil_status,
                            'Nationality' => $profile->user?->nationality,
                            'Household number' => $profile->household_number,
                            'Address' => $profile->address,
                            'Sector / Purok' => $profile->sector?->sector_name,
                            'Email confirmed' => $profile->user?->email_verified_at
                                ? $profile->user->email_verified_at->format('M d, Y')
                                : 'Not yet confirmed',
                        ] as $label => $value)
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">{{ $label }}</dt>
                                <dd class="mt-1 text-sm text-navy-900">{{ $value ?? '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <p class="mt-4 rounded-lg bg-navy-50 px-3 py-2 text-xs leading-relaxed text-navy-700">
                        Email confirmation and resident verification are separate. Confirming an
                        email address proves the resident owns it; it says nothing about whether
                        the person is who they say they are.
                    </p>
                </x-card>

                {{-- ============================================================
                     THE DECISION
                     ============================================================
                     Both actions post to the existing
                     staff.verifications.update route, which is the only thing
                     that writes verification_status. The dialogs are a
                     confirmation, not the enforcement - the route's own role
                     middleware and the controller's action whitelist remain the
                     only thing standing between a crafted POST and a decision.

                     Rejection asks for a reason because the controller stores
                     one and the resident is shown it; a rejection with no
                     explanation tells the resident nothing they can act on.
                --}}
                <x-card tone="white" class="{{ $profile->verification_status === \App\Models\ResidentProfile::VERIFICATION_REJECTED ? 'border-red-200' : '' }}">
                    <h2 class="text-base font-bold text-navy-900">Decision</h2>

                    @if ($profile->verification_status === \App\Models\ResidentProfile::VERIFICATION_VERIFIED)
                        <div class="mt-3 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                            <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                            <p class="text-sm leading-relaxed text-emerald-900">
                                Verified{{ $profile->verified_at ? ' on '.$profile->verified_at->format('F d, Y') : '' }}.
                                This resident can request documents and their household is included
                                in relief batches.
                            </p>
                        </div>
                    @elseif ($profile->rejection_reason)
                        <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                            <p class="text-sm font-semibold text-red-900">Rejected</p>
                            <p class="mt-1 text-sm leading-relaxed text-red-800">{{ $profile->rejection_reason }}</p>
                        </div>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button"
                            class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
                            x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'verify-{{ $profile->id }}' }))">
                            <x-icon name="check" class="mr-1.5 h-4 w-4" />
                            Approve &amp; verify
                        </button>

                        <button type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-400"
                            x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'reject-{{ $profile->id }}' }))">
                            <x-icon name="x-circle" class="mr-1.5 h-4 w-4" />
                            Reject
                        </button>

                        <a href="{{ route('staff.verifications.index') }}"
                            class="inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-4 py-2.5 text-sm font-semibold text-navy-800 transition hover:bg-navy-50">
                            Back to queue
                        </a>
                    </div>
                </x-card>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-card tone="white" class="p-0">
                        <h2 class="border-b border-navy-100 px-5 py-3 text-sm font-bold text-navy-900">Recent requests</h2>
                        @if ($requests->isEmpty())
                            <p class="px-5 py-4 text-sm text-navy-500">None submitted.</p>
                        @else
                            <ul class="divide-y divide-navy-50">
                                @foreach ($requests as $request)
                                    <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                                        <span class="font-mono text-xs text-navy-700">{{ $request->reference_number }}</span>
                                        <x-status-badge :status="$request->status" size="sm" />
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-card>

                    <x-card tone="white" class="p-0">
                        <h2 class="border-b border-navy-100 px-5 py-3 text-sm font-bold text-navy-900">Relief history</h2>
                        @if ($distributions->isEmpty())
                            <p class="px-5 py-4 text-sm text-navy-500">None recorded for this household.</p>
                        @else
                            <ul class="divide-y divide-navy-50">
                                @foreach ($distributions as $distribution)
                                    <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                                        <span class="truncate text-navy-700">{{ $distribution->batch?->title ?? '—' }}</span>
                                        <span class="shrink-0 text-xs font-semibold text-navy-600">{{ ucfirst($distribution->status) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-card>
                </div>
            </div>
        </div>
    </div>

    {{-- Approve --}}
    <x-modal name="verify-{{ $profile->id }}" maxWidth="md">
        <form method="POST" action="{{ route('staff.verifications.update', $profile) }}">
            @csrf
            <input type="hidden" name="action" value="verified">

            <div class="bg-white px-6 pb-2 pt-6">
                <h2 class="text-lg font-bold text-navy-900">Verify {{ $profile->user?->name ?? 'this resident' }}?</h2>
                <p class="mt-2 text-sm leading-relaxed text-navy-700">
                    This approves the profile against the master list. It will let the resident
                    request documents, and adds household
                    <span class="font-mono font-semibold">{{ $profile->household_number }}</span>
                    to any relief batch created from now on.
                </p>
            </div>

            <div class="flex flex-col-reverse gap-2 bg-navy-50/50 px-6 py-4 sm:flex-row sm:justify-end">
                <button type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-4 py-2.5 text-sm font-semibold text-navy-800 transition hover:bg-navy-50"
                    x-on:click="show = false">Cancel</button>
                <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                    Confirm verification
                </button>
            </div>
        </form>
    </x-modal>

    {{-- Reject --}}
    <x-modal name="reject-{{ $profile->id }}" maxWidth="md">
        <form method="POST" action="{{ route('staff.verifications.update', $profile) }}">
            @csrf
            <input type="hidden" name="action" value="rejected">

            <div class="bg-white px-6 pb-2 pt-6">
                <h2 class="text-lg font-bold text-navy-900">Reject {{ $profile->user?->name ?? 'this resident' }}?</h2>
                <p class="mt-2 text-sm leading-relaxed text-navy-700">
                    The resident cannot request documents until this is resolved. They will see
                    the reason you give, so make it something they can act on.
                </p>

                <div class="mt-4">
                    <label for="rejection_reason" class="block text-sm font-semibold text-navy-900">
                        Reason <span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <textarea id="rejection_reason" name="rejection_reason" rows="3" maxlength="500" required
                        placeholder="e.g. The ID photograph is too blurry to read the number."
                        class="mt-1.5 block w-full rounded-lg border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 transition placeholder:text-navy-400 focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none"></textarea>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-2 bg-navy-50/50 px-6 py-4 sm:flex-row sm:justify-end">
                <button type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-4 py-2.5 text-sm font-semibold text-navy-800 transition hover:bg-navy-50"
                    x-on:click="show = false">Cancel</button>
                <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">
                    Confirm rejection
                </button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
