{{--
    Correct a resident's own details.

    EIGHT FIELDS, AND THE ABSENCE OF THE REST IS THE POINT. Everything a Secretary
    might want to change while fixing a typo is here; the account fields (email,
    password), the identity fields (national ID number, ID photograph) and the
    verification state are not, because each of those is a decision rather than a
    correction and each has its own audited screen. A form that offered them would
    make a typo fix capable of looking like an approval.

    The national ID is displayed here MASKED and is not editable, for the reason on
    the record page: it is not collected by the registration wizard at all - the
    number is read off the uploaded document during review - so there is no wizard
    value to keep in step with, and typing it here would create a second source of
    truth for a value the verification screen owns.
--}}
<x-app-layout :user="auth()->user()" title="Correct resident details">
    <div class="mx-auto w-full max-w-3xl space-y-6">
        {{-- The breadcrumb component takes a route with no parameters, so the
             resident's own page cannot be a crumb here; their name is in the
             heading below instead. --}}
        <x-breadcrumb :items="[
            ['label' => 'Staff', 'route' => 'staff.dashboard'],
            ['label' => 'Residents', 'route' => 'staff.residents.index'],
            ['label' => 'Correct details'],
        ]" />

        <header>
            <h1 class="text-xl font-bold text-navy-900">Correct resident details</h1>
            <p class="mt-1 text-sm text-gray-600">
                {{ $profile->user?->name }} &middot; household {{ $profile->household_number }}
            </p>
        </header>

        @if ($errors->any())
            <x-alert variant="danger" icon="x-circle" title="These details were not saved">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        {{-- The concurrency token. If someone else saves this resident while this
             form is open, their save changes updated_at and this submission is
             refused rather than silently overwriting their correction. --}}
        <x-alert variant="info" icon="shield">
            Saving records your name against the change in the audit log. If someone else edits this
            resident while this form is open, your save is refused and nothing is overwritten.
        </x-alert>

        <form method="POST" action="{{ route('staff.residents.update', $profile) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <input type="hidden" name="profile_updated_at" value="{{ old('profile_updated_at', $profile->updated_at?->format('Y-m-d H:i:s')) }}">
            <input type="hidden" name="user_updated_at" value="{{ old('user_updated_at', $profile->user?->updated_at?->format('Y-m-d H:i:s')) }}">

            <x-card tone="white" padding="p-6">
                <h2 class="mb-1 text-base font-bold text-navy-900">Personal information</h2>
                <p class="mb-4 text-xs text-slate-500">
                    As collected by the registration wizard. These are the same fields and the same rules.
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field id="name" name="name" label="Full name" variant="soft" required
                        :value="old('name', $profile->user?->name)"
                        placeholder="Juan Dela Cruz" class="sm:col-span-2" />

                    <x-field id="date_of_birth" name="date_of_birth" label="Date of birth" type="date"
                        variant="soft" required
                        :value="old('date_of_birth', $profile->user?->date_of_birth?->format('Y-m-d'))" />

                    <x-select-field id="gender" name="gender" label="Gender" variant="soft" required
                        :options="config('bdrpis.registration.genders')"
                        :value="old('gender', $profile->user?->gender)" />

                    <x-select-field id="civil_status" name="civil_status" label="Civil status" variant="soft" required
                        :options="config('bdrpis.registration.civil_statuses')"
                        :value="old('civil_status', $profile->user?->civil_status)" />

                    {{--
                        Nationality is a closed list with an escape hatch. The column
                        holds whatever the resident typed beside "Others" rather than
                        the word "Others" itself, so a stored value that is neither of
                        the two list entries cannot be shown by a two-option select.
                        The stored value is therefore presented as "Others" with the
                        text box beside it carrying the real value - which is what the
                        wizard did when it was captured, so saving it back unchanged
                        is a no-op rather than a rewrite.
                    --}}
                    <div x-data="{ nationality: @js(old('nationality', $nationality)) }">
                        <x-select-field id="nationality" name="nationality" label="Nationality"
                            variant="soft" required
                            :options="[$defaultNationality => $defaultNationality, $otherNationality => $otherNationality]"
                            :value="old('nationality', $nationality)" x-model="nationality" />

                        <div x-show="nationality === @js($otherNationality)" x-cloak class="mt-4">
                            <x-field id="nationality_other" name="nationality_other" label="Nationality"
                                variant="soft"
                                :value="old('nationality_other', $nationalityOther)"
                                placeholder="e.g. American" />
                        </div>
                    </div>
                </div>
            </x-card>

            <x-card tone="white" padding="p-6">
                <h2 class="mb-1 text-base font-bold text-navy-900">Household and sector</h2>
                <p class="mb-4 text-xs text-slate-500">
                    Where this household is, which decides who can see it and which checklist it lands on.
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field id="household_number" name="household_number" label="Household reference number"
                        variant="soft" mono required
                        :value="old('household_number', $profile->household_number)"
                        placeholder="HH-0042" />

                    <x-select-field id="sector_id" name="sector_id" label="Sector" variant="soft" required
                        :options="$sectors->pluck('sector_name', 'id')->all()"
                        :value="old('sector_id', $profile->sector_id)" />

                    <x-field id="address" name="address" label="Complete address" icon="pin" variant="soft"
                        required class="sm:col-span-2"
                        :value="old('address', $profile->address)"
                        placeholder="House no. 12, Barangay Linasin" />
                </div>

                {{-- Always shown, not only once a value has been chosen. The
                     consequences below are the reason this form asks the Secretary
                     to confirm rather than assume: the sector change is immediate
                     and visible to a different Kagawad the moment it saves. --}}
                <div class="mt-5 space-y-3 border-t border-navy-100 pt-5">
                    <x-alert variant="warning" icon="shield" title="Changing the sector changes who can see this resident">
                        The Kagawad of the sector you choose will immediately be able to see this resident on
                        their own residents list, and the Kagawad of the current sector will stop seeing them.
                        Relief batches already opened keep this household on the checklist of its original
                        sector &mdash; only batches opened after this change use the new one.
                    </x-alert>

                    <x-alert variant="warning" icon="shield" title="Changing the household number does not move past relief records">
                        Relief history is recorded against the household number, not against the resident.
                        Correcting it leaves the rows already recorded under the old number in place, and this
                        resident will stop matching them on their own relief page.
                    </x-alert>
                </div>
            </x-card>

            <x-card tone="tinted" padding="p-5">
                <h2 class="text-sm font-bold text-navy-900">Not corrected here</h2>
                <dl class="mt-2 grid gap-2 text-xs text-slate-600 sm:grid-cols-2">
                    <div><dt class="font-semibold text-navy-800">Email address</dt>
                        <dd>{{ $profile->user?->email }} &mdash; changed in the account area.</dd></div>
                    <div><dt class="font-semibold text-navy-800">Password</dt>
                        <dd>Credential. Never recorded or shown here.</dd></div>
                    <div><dt class="font-semibold text-navy-800">National / voter ID</dt>
                        <dd>Read off the uploaded document at verification, not typed. Not editable.</dd></div>
                    <div><dt class="font-semibold text-navy-800">Verification status</dt>
                        <dd>{{ ucfirst(str_replace('_', ' ', $profile->verification_status)) }} &mdash;
                            the verification screen decides this.</dd></div>
                </dl>
            </x-card>

            <div class="flex flex-wrap justify-end gap-3">
                <x-btn :href="route('staff.residents.show', $profile)" variant="outline" size="md">Cancel</x-btn>
                <x-btn variant="primary" size="md" icon="check">Save changes</x-btn>
            </div>
        </form>
    </div>
</x-app-layout>