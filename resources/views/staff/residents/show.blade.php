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

    /*
     * Whether this household's account is closed. Read once here because the
     * header, the banner, the history tables and both action buttons all have to
     * agree about it, and four separate isArchived() calls is four chances for one
     * of them to be wrong.
     */
    $archived = (bool) $profile->user?->isArchived();
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

        {{-- Why the archive did not happen, with the way out attached.
             The message names the requests and the links below are the requests, so
             the refusal and the rows that caused it cannot describe different sets:
             both come from the same one query in the controller. A refusal that
             said only "you cannot archive this yet" would leave a Secretary to guess
             which of six open requests was in the way. --}}
        @if (session('archive_blocked'))
            <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                <p class="text-sm font-semibold text-red-800">{{ session('error') }}</p>
                <ul class="mt-3 space-y-1">
                    @foreach (session('archive_blocked') as $blocked)
                        <li class="text-sm text-red-900">
                            <a href="{{ $blocked['url'] }}"
                                class="font-semibold underline underline-offset-2">{{ $blocked['reference_number'] }}</a>
                            &mdash; {{ $blocked['document'] }}
                            (<x-staff.status-badge :status="$blocked['status']" size="sm" class="align-middle" />)
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- The restore that did not happen, for the same reason and with the same
             shape: the message names the household holding the number, and the link
             goes to the record that has to be corrected first. --}}
        @if (session('restore_blocked'))
            <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                <p class="text-sm text-red-900">{{ session('error') }}</p>
                <a href="{{ session('restore_blocked')['url'] }}"
                    class="mt-2 inline-block text-sm font-semibold text-red-800 underline underline-offset-2">
                    Open {{ session('restore_blocked')['name'] }}'s record
                </a>
            </div>
        @endif

        {{-- The archived state, stated on the record itself. An archived household
             still has a detail page - the history is the reason it exists - so this
             page has to say up front why the household is not on any working list,
             before the numbers below are read as current. --}}
        @if ($archived)
            <div class="rounded-lg border border-gray-300 bg-gray-100 p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <x-staff.status-badge status="archived" />
                    <p class="text-sm font-semibold text-gray-800">
                        {{ $profile->user?->name }} is archived.
                    </p>
                </div>
                <p class="mt-2 text-sm text-gray-700">
                    {{ $profile->user?->archive_reason }}{{ $profile->user?->archive_note ? ' — '.$profile->user->archive_note : '' }}
                    @if ($profile->user?->archived_at)
                        <span class="text-gray-600">
                            Archived {{ $profile->user->archived_at->format('j M Y') }}
                            @if ($profile->user->archivedBy) by {{ $profile->user->archivedBy->name }} @endif.
                        </span>
                    @endif
                </p>
            </div>
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

                {{-- An archived household offers Restore and not Edit. Editing a
                     record that is off every working list produces a change nobody
                     sees and cannot find later, and the corrections to an archived
                     household's address can wait for the restore. --}}
                @if ($archived)
                    <x-staff.confirm-dialog :name="'restore-detail-'.$profile->id"
                        title="Restore this household?"
                        message="They go back on the working lists, out of the archived view, and back into new relief batches. Their verification status and sector are kept as they are."
                        confirm-label="Restore" tone="primary"
                        :form="route('staff.residents.restore', $profile)" />
                    <x-btn variant="primary" size="sm" icon="box-open" type="button"
                        x-data x-on:click="$dispatch('open-modal', 'restore-detail-{{ $profile->id }}')">Restore</x-btn>
                @else
                    <x-btn :href="route('staff.residents.edit', $profile)" variant="primary" size="sm" icon="edit">Edit</x-btn>

                    <x-staff.confirm-dialog :name="'archive-detail-'.$profile->id"
                        :title="'Archive '.$profile->user?->name.'?'"
                        message="They will no longer be able to sign in or request documents, and will be left out of new relief batches. Their history is kept and you can restore them."
                        confirm-label="Archive" tone="primary"
                        :form="route('staff.residents.archive', $profile)">
                        <div class="space-y-3">
                            <div>
                                <label for="archive_reason_detail"
                                    class="block text-sm font-semibold text-navy-800">Reason</label>
                                <select id="archive_reason_detail" name="archive_reason" required
                                    class="mt-1 w-full rounded-lg border-navy-300 text-sm shadow-sm focus:border-navy-500 focus:ring-navy-500">
                                    <option value="">Choose a reason</option>
                                    @foreach (\App\Models\User::archiveReasons() as $reason)
                                        <option value="{{ $reason }}">{{ $reason }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="archive_note_detail"
                                    class="block text-sm font-semibold text-navy-800">Note (optional)</label>
                                <textarea id="archive_note_detail" name="archive_note" rows="2" maxlength="1000"
                                    placeholder="Anything that does not fit the reason above"
                                    class="mt-1 w-full rounded-lg border-navy-300 text-sm shadow-sm focus:border-navy-500 focus:ring-navy-500"></textarea>
                            </div>
                            @error('archive_reason')
                                <p class="text-sm font-medium text-red-700">{{ $message }}</p>
                            @enderror
                        </div>
                    </x-staff.confirm-dialog>
                    <x-btn variant="outline" size="sm" icon="box-archive" type="button"
                        x-data x-on:click="$dispatch('open-modal', 'archive-detail-{{ $profile->id }}')">Archive</x-btn>
                @endif
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
                    {{-- The four archive columns, stated as fields. A restore clears
                         all four, so this block disappears on restore rather than
                         leaving four empty rows behind. --}}
                    @if ($archived)
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Archived on</dt>
                            <dd class="mt-1 text-sm text-navy-900">{{ $profile->user?->archived_at?->format('j M Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Archived by</dt>
                            <dd class="mt-1 text-sm text-navy-900">{{ $profile->user?->archivedBy?->name ?? 'Former staff member' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Archive reason</dt>
                            <dd class="mt-1 text-sm text-navy-900">{{ $profile->user?->archive_reason }}</dd>
                        </div>
                        @if (filled($profile->user?->archive_note))
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Archive note</dt>
                                <dd class="mt-1 text-sm text-navy-900">{{ $profile->user->archive_note }}</dd>
                            </div>
                        @endif
                    @endif
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
                        <td>
                            <div class="flex flex-wrap items-center gap-1">
                                <x-staff.status-badge :status="$documentRequest->status" size="sm" />
                                {{-- History is kept, and it is kept visible - an
                                     archived household's requests are the record of what
                                     the office did before it closed. What changes is that
                                     the reader is told these belong to a household that
                                     is gone, so a row still reading "Ready for pickup"
                                     is not mistaken for a packet still on the shelf. --}}
                                @if ($archived)
                                    <x-staff.status-badge status="archived" size="sm" />
                                @endif
                            </div>
                        </td>
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
                        <td>
                            <div class="flex flex-wrap items-center gap-1">
                                <x-staff.status-badge :status="$entry->status" size="sm" />
                                {{-- A "Received" row on an archived household is the
                                     single most important thing this table says: the
                                     household was served, and it is now gone. The row
                                     stays on every future batch view's history. --}}
                                @if ($archived)
                                    <x-staff.status-badge status="archived" size="sm" />
                                @endif
                            </div>
                        </td>
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