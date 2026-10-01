{{--
    One resident verification, with everything a decision needs side by side.

    The ID is served through the authorised route rather than linked as a file,
    so the private disk path never appears in the page source.
--}}
<x-app-layout :user="auth()->user()" :title="$profile->user?->name">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <x-breadcrumb :items="[
            ['label' => 'Staff', 'route' => 'staff.dashboard'],
            ['label' => 'Resident Verification', 'route' => 'staff.verifications.index'],
            ['label' => $profile->user?->name ?? 'Resident'],
        ]" />

        <header>
            <h1 class="text-xl font-bold text-navy-900">{{ $profile->user?->name }}</h1>
            <p class="mt-1 text-sm text-gray-600">Check these details against the master list before deciding.</p>
        </header>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-card tone="white" padding="p-6">
                    <h2 class="mb-4 text-base font-bold text-navy-900">Submitted details</h2>
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Full name</dt><dd class="mt-1 text-sm text-navy-900">{{ $profile->user?->name }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt><dd class="mt-1 text-sm text-navy-900">{{ $profile->user?->email }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Household no.</dt><dd class="mt-1 font-mono text-sm text-navy-900">{{ $profile->household_number }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">National / voter ID</dt><dd class="mt-1 text-sm text-navy-900">{{ $profile->national_or_voter_id ?? 'Not supplied' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date of birth</dt><dd class="mt-1 text-sm text-navy-900">{{ $profile->user?->date_of_birth?->format('j M Y') ?? '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Civil status</dt><dd class="mt-1 text-sm text-navy-900">{{ $profile->user?->civil_status ?? '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Address</dt><dd class="mt-1 text-sm text-navy-900">{{ $profile->address }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sector</dt><dd class="mt-1 text-sm text-navy-900">{{ $profile->sector?->sector_name ?? '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt><dd class="mt-1"><x-staff.status-badge :status="$profile->verification_status" size="sm" /></dd></div>
                    </dl>
                </x-card>

                @if ($profile->isAwaitingDecision())
                    <x-card tone="white" padding="p-6">
                        <h2 class="mb-4 text-base font-bold text-navy-900">Decision</h2>

                        <form method="POST" action="{{ route('staff.verifications.approve', $profile) }}" class="mb-6">
                            @csrf
                            <x-btn type="submit" variant="navy" size="md" icon="check-circle">
                                Approve this registration
                            </x-btn>
                        </form>

                        {{-- The reason travels inside the dialog, not in a separate
                             form the trigger would have to reach. The component owns
                             the one <form> for the action precisely so an input
                             cannot end up posted under the wrong field name. --}}
                        <x-confirm-dialog name="reject-verification"
                            title="Reject this registration?"
                            message="The resident will be told the reason and can correct their details."
                            confirm-label="Reject, with this reason" tone="danger" method="POST"
                            :form="route('staff.verifications.reject', $profile)">
                            <input type="hidden" name="rejecting" value="1">

                            @error('rejection_reason')
                                <x-alert variant="danger" icon="x-circle" class="mb-3">{{ $message }}</x-alert>
                            @enderror

                            <label for="rejection_reason" class="block text-sm font-semibold text-navy-800">Reason for rejecting</label>
                            <textarea id="rejection_reason" name="rejection_reason" rows="3" maxlength="1000" required
                                class="mt-1.5 w-full rounded-lg border border-navy-200 bg-navy-50/60 px-3.5 py-2.5 text-sm focus:border-navy-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-200"
                                placeholder="What did the master list show, and what should the resident correct?"></textarea>
                            <p class="mt-1.5 text-xs text-slate-500">The resident reads this on their own page, so say what they should change.</p>
                        </x-confirm-dialog>

                        <x-btn type="button" variant="danger" size="md" icon="x-circle"
                            x-on:click="$dispatch('open-modal', 'reject-verification')">
                            Reject registration
                        </x-btn>
                    </x-card>
                @else
                    <x-alert variant="success" icon="check-circle">
                        This resident is already verified. There is nothing left to decide.
                    </x-alert>
                @endif
            </div>

            <div class="space-y-4">
                <x-card tone="white" padding="p-5">
                    <h2 class="mb-3 text-sm font-bold text-navy-900">ID document</h2>

                    @if ($profile->id_photo_path)
                        <img src="{{ route('staff.resident-verifications.id-photo', $profile) }}"
                            alt="ID document uploaded by {{ $profile->user?->name }}"
                            class="w-full rounded-lg border border-navy-200 bg-white">
                        <p class="mt-3 text-xs leading-relaxed text-slate-500">
                            Served through an authorised route from the private disk. Every view is written
                            to the audit log.
                        </p>
                    @else
                        <x-staff.empty-state icon="upload" title="No ID on file"
                            description="This resident registered without attaching one, so check their other details carefully." />
                    @endif
                </x-card>

                @if ($profile->rejection_reason)
                    <x-alert variant="danger" icon="x-circle">
                        <strong class="font-semibold">Previously rejected:</strong>
                        {{ $profile->rejection_reason }}
                    </x-alert>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
