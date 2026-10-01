{{--
    One account, and the three things that can be done to it.

    Three cards: who this is, what can be changed, and what has happened. Anything
    that cannot be undone sits behind a confirmation that states the consequence in
    plain language before the button, not in a generic "Are you sure?" - an Admin
    should be able to read what will happen and decide, not click through a habit.

    The role form and the restriction controls are hidden for your own account. The
    server refuses both (AccountController::updateRole and ::restrict), but a
    control you know will fail is worse than no control, and the comment on the
    screen is more useful than an error page.

    No authorization is changed here. The dialog is a courtesy step; the route
    middleware and the controller's own guards remain the only thing between a
    crafted POST and the action.
--}}
<x-app-layout :user="auth()->user()" :title="$account->name">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <x-breadcrumb :items="[
            ['label' => 'Staff', 'route' => 'staff.dashboard'],
            ['label' => 'User Accounts', 'route' => 'staff.accounts.index'],
            ['label' => $account->name],
        ]" />

        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-navy-900">{{ $account->name }}</h1>
                <p class="mt-1 text-sm text-gray-600">{{ $account->email }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-staff.status-badge :status="$account->isRestricted() ? 'restricted' : 'active'" />
                @if ($account->isResident())
                    <x-staff.status-badge :status="$account->is_verified ? 'verified' : 'pending'" />
                @endif
            </div>
        </header>

        @if (session('success'))
            <x-alert variant="success" icon="check-circle">{{ session('success') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert variant="warning" icon="x-circle">{{ session('error') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="danger" icon="x-circle">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        {{-- 1. Who this is. --}}
        <x-card tone="white" padding="p-6">
            <h2 class="mb-4 text-base font-bold text-navy-900">Profile</h2>
            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Role</dt>
                    <dd class="text-sm text-navy-900">{{ \App\Models\User::roleLabel($account->role) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Registered</dt>
                    <dd class="text-sm text-navy-900">{{ $account->created_at?->format('j M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email verified</dt>
                    <dd class="text-sm text-navy-900">{{ $account->email_verified_at?->format('j M Y') ?? 'Not yet' }}</dd>
                </div>
                @if ($account->isResident())
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Verification</dt>
                        <dd class="text-sm text-navy-900">
                            {{ $account->is_verified ? 'Verified' : 'Pending review' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Household</dt>
                        <dd class="text-sm text-navy-900">
                            {{ $account->residentProfile?->household_number ?? '—' }}
                            &middot; {{ $account->residentProfile?->sector?->sector_name ?? 'no sector' }}
                        </dd>
                    </div>
                @endif
                @if ($account->reliefKagawadSectors->isNotEmpty())
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sector</dt>
                        <dd class="text-sm text-navy-900">
                            {{ $account->reliefKagawadSectors->pluck('sector_name')->join(', ') }}
                        </dd>
                    </div>
                @endif
                @if ($account->isRestricted())
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Restricted until</dt>
                        <dd class="text-sm text-red-700">{{ $account->restricted_until->format('j M Y') }}</dd>
                    </div>
                @endif
            </dl>
        </x-card>

        {{-- 2. Role and restriction, together: both answer "what can this person do". --}}
        <x-card tone="white" padding="p-6">
            <h2 class="mb-1 text-base font-bold text-navy-900">Role &amp; access</h2>
            <p class="mb-5 text-sm text-slate-600">
                Both changes take effect the next time they load a page. Both are written to the
                audit log with your name on them.
            </p>

            @if ($account->is(auth()->user()))
                <x-alert variant="info" icon="lock">
                    This is your own account. You cannot change your own role or restrict yourself —
                    either would lock you out of the only screen that could undo it.
                </x-alert>
            @else
                <div class="space-y-6">
                    {{-- Role. The select lives inside the dialog, so the value that is
                         confirmed is the value that is submitted. --}}
                    <div>
                        <h3 class="text-sm font-semibold text-navy-800">Role</h3>
                        <p class="mt-0.5 text-sm text-slate-600">
                            Currently <strong class="font-semibold text-navy-900">{{ \App\Models\User::roleLabel($account->role) }}</strong>.
                        </p>
                        <x-btn type="button" variant="secondary" size="md" icon="user"
                            x-data="{}"
                            x-on:click="$dispatch('open-modal', 'change-role-{{ $account->id }}')">
                            Change role
                        </x-btn>

                        <x-staff.confirm-dialog name="change-role-{{ $account->id }}"
                            title="Change this account's role?"
                            :message="sprintf('You are about to change %s from %s to a different role. They will keep their password and their sign-out, and lose or gain access depending on the role you pick.', $account->name, \Illuminate\Support\Str::lower(\App\Models\User::roleLabel($account->role)))"
                            confirm-label="Change role" tone="primary" method="PUT"
                            :form="route('staff.accounts.role', $account)">
                            {{-- The consequence, in the words an Admin would use. --}}
                            <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                @if ($account->isAdmin())
                                    This is an Admin account. Changing it to a lower role could leave the
                                    barangay with nobody able to administer the system.
                                @else
                                    A lower role loses the Admin pages. A higher role gains every permission
                                    that role has, including anything added later.
                                @endif
                            </p>

                            <div class="mt-3">
                                <label for="role-{{ $account->id }}" class="block text-sm font-semibold text-navy-800">
                                    New role
                                </label>
                                <select id="role-{{ $account->id }}" name="role"
                                    class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                                    @foreach (\App\Models\User::roles() as $option)
                                        <option value="{{ $option }}" @selected($account->role === $option)>
                                            {{ \App\Models\User::roleLabel($option) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </x-staff.confirm-dialog>
                    </div>

                    <div class="border-t border-navy-100 pt-6">
                        <h3 class="text-sm font-semibold text-navy-800">Restriction</h3>

                        @if ($account->isRestricted())
                            <p class="mt-0.5 text-sm text-slate-600">
                                Restricted until
                                <strong class="font-semibold text-red-700">
                                    {{ $account->restricted_until->format('j M Y') }}
                                </strong>.
                            </p>
                            <form method="POST" action="{{ route('staff.accounts.unrestrict', $account) }}"
                                x-on:submit="if (! confirm('Let {{ \Illuminate\Support\Str::before($account->name, ' ') }} back in? They will be able to sign in and use the system again immediately.')) $event.preventDefault()">
                                @csrf
                                <x-btn type="submit" variant="secondary" size="md" icon="check-circle">
                                    Let them back in
                                </x-btn>
                            </form>
                        @else
                            <p class="mt-0.5 text-sm text-slate-600">
                                Not restricted. A restricted account keeps its own password and sign-out
                                and loses everything else, so a frozen account is never permanently stuck.
                            </p>
                            <x-btn type="button" variant="danger" size="md" icon="lock"
                                x-data="{}"
                                x-on:click="$dispatch('open-modal', 'restrict-{{ $account->id }}')">
                                Restrict this account
                            </x-btn>
                        @endif
                    </div>
                </div>

                {{-- Restriction. Declared once, outside the branch above, because the
                     dialog owns the form and its inputs must submit from inside it. --}}
                @unless ($account->isRestricted())
                    <x-staff.confirm-dialog name="restrict-{{ $account->id }}"
                        title="Restrict this account?"
                        :message="sprintf('%s will be signed out of every page except changing their password and signing out. Nothing is deleted.', \Illuminate\Support\Str::before($account->name, ' '))"
                        confirm-label="Restrict account" tone="danger"
                        :form="route('staff.accounts.restrict', $account)">
                        <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                            @if ($account->isAdmin())
                                This is an Admin. Restricting them removes the only accounts that can
                                undo this, so check there is another Admin first.
                            @else
                                They keep their password and their sign-out, so this is reversible from
                                this page at any time.
                            @endif
                        </p>

                        <div class="mt-3 max-w-xs">
                            <label for="restricted_until" class="block text-sm font-semibold text-navy-800">
                                Until
                            </label>
                            <input id="restricted_until" name="restricted_until" type="date"
                                min="{{ now()->addDay()->toDateString() }}"
                                class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                            <p class="mt-1.5 text-xs text-slate-500">Leave blank to restrict with no end date.</p>
                        </div>

                        <div class="mt-3">
                            <label for="reason" class="block text-sm font-semibold text-navy-800">Reason</label>
                            <textarea id="reason" name="reason" rows="3" maxlength="500" required
                                class="mt-1.5 w-full rounded-lg border border-navy-200 bg-navy-50/60 px-3.5 py-2.5 text-sm focus:border-navy-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-200"
                                placeholder="Kept in the audit log, so the record explains itself later."></textarea>
                        </div>
                    </x-staff.confirm-dialog>
                @endunless
            @endif
        </x-card>

        {{-- 3. What has happened. --}}
        <x-card tone="white" padding="p-6">
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-base font-bold text-navy-900">Recent activity</h2>
                <a href="{{ route('staff.audit.index') }}"
                    class="text-xs font-semibold text-sky-700 hover:text-sky-900">See the full audit log</a>
            </div>

            @if ($recentActivity->isEmpty())
                <p class="text-sm text-slate-500">Nothing logged for this account yet.</p>
            @else
                <ul class="divide-y divide-navy-50 text-sm">
                    @foreach ($recentActivity as $entry)
                        <li class="flex flex-wrap items-baseline justify-between gap-2 py-2.5">
                            <div class="min-w-0">
                                <span class="text-navy-900">{{ $entry->actionLabel() }}</span>
                                <span class="block text-xs text-slate-500">{{ $entry->description }}</span>
                            </div>
                            <span class="shrink-0 text-xs text-slate-500">
                                {{ $entry->created_at?->format('j M Y, g:i a') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-app-layout>
