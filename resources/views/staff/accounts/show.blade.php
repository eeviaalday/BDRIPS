{{--
    One account, and the three things that can be done to it.

    The role form and the restriction form are hidden for your own account. The
    server refuses both, but a control you know will fail is worse than no
    control, and the comment on the screen is more useful than an error page.
--}}
<x-app-layout :user="auth()->user()" :title="$account->name">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <x-breadcrumb :items="[
            ['label' => 'Staff', 'route' => 'staff.dashboard'],
            ['label' => 'User Accounts', 'route' => 'staff.accounts.index'],
            ['label' => $account->name],
        ]" />

        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-navy-900">{{ $account->name }}</h1>
                <p class="mt-1 text-sm text-gray-600">{{ $account->email }}</p>
            </div>
            <div class="flex items-center gap-2">
                <x-status-badge :status="$account->isRestricted() ? 'rejected' : 'active'" />
                <x-status-badge :status="$account->is_verified ? 'active' : 'pending'" />
            </div>
        </header>

        @if (session('success'))
            <x-alert variant="success" icon="check-circle">{{ session('success') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert variant="warning" icon="x-circle">{{ session('error') }}</x-alert>
        @endif

        <x-card tone="white" padding="p-6">
            <h2 class="mb-4 text-base font-bold text-navy-900">Account</h2>
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
                @if ($account->residentProfile)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Household</dt>
                        <dd class="text-sm text-navy-900">
                            {{ $account->residentProfile->household_number }}
                            &middot; {{ $account->residentProfile->sector?->sector_name ?? 'no sector' }}
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

        <x-card tone="white" padding="p-6">
            <h2 class="mb-1 text-base font-bold text-navy-900">Change role</h2>
            <p class="mb-4 text-sm text-slate-600">
                @if ($account->is(auth()->user()))
                    You cannot change your own role. Demoting yourself would lock you out of
                    the only screen that could put it back.
                @else
                    Takes effect the next time they load a page.
                @endif
            </p>

            @unless ($account->is(auth()->user()))
                <form method="POST" action="{{ route('staff.accounts.role', $account) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="role-{{ $account->id }}" class="block text-sm font-semibold text-navy-800">Role</label>
                        <select id="role-{{ $account->id }}" name="role" class="mt-1.5 rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                            @foreach (\App\Models\User::roles() as $option)
                                <option value="{{ $option }}" @selected($account->role === $option)>
                                    {{ \App\Models\User::roleLabel($option) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <x-btn type="submit" variant="navy" size="md" icon="check">Save role</x-btn>
                </form>
            @endunless
        </x-card>

        <x-card tone="white" padding="p-6">
            <h2 class="mb-1 text-base font-bold text-navy-900">
                {{ $account->isRestricted() ? 'Lift the restriction' : 'Restrict this account' }}
            </h2>
            <p class="mb-4 text-sm text-slate-600">
                A restricted account keeps its own password and sign-out, and loses everything else,
                so a frozen account is never permanently stuck.
            </p>

            @if ($account->is(auth()->user()))
                <p class="text-sm text-slate-500">You cannot restrict your own account.</p>
            @elseif ($account->isRestricted())
                <form method="POST" action="{{ route('staff.accounts.unrestrict', $account) }}">
                    @csrf
                    <x-btn type="submit" variant="navy" size="md" icon="check-circle">Let them back in</x-btn>
                </form>
            @else
                <form method="POST" action="{{ route('staff.accounts.restrict', $account) }}" class="space-y-4">
                    @csrf
                    <div class="max-w-xs">
                        <label for="restricted_until" class="block text-sm font-semibold text-navy-800">Until</label>
                        <input id="restricted_until" name="restricted_until" type="date"
                            min="{{ now()->addDay()->toDateString() }}"
                            class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                        <p class="mt-1.5 text-xs text-slate-500">Leave blank to restrict with no end date.</p>
                    </div>

                    <div>
                        <label for="reason" class="block text-sm font-semibold text-navy-800">Reason</label>
                        <textarea id="reason" name="reason" rows="3" maxlength="500" required
                            class="mt-1.5 w-full rounded-lg border border-navy-200 bg-navy-50/60 px-3.5 py-2.5 text-sm focus:border-navy-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-200"
                            placeholder="Kept in the audit log, so the record explains itself later."></textarea>
                    </div>

                    <x-btn type="submit" variant="danger" size="md" icon="lock">Restrict account</x-btn>
                </form>
            @endif
        </x-card>

        <x-card tone="white" padding="p-6">
            <h2 class="mb-4 text-base font-bold text-navy-900">Recent activity</h2>

            @if ($recentActivity->isEmpty())
                <p class="text-sm text-slate-500">Nothing logged for this account yet.</p>
            @else
                <ul class="divide-y divide-navy-50 text-sm">
                    @foreach ($recentActivity as $entry)
                        <li class="flex flex-wrap items-baseline justify-between gap-2 py-2.5">
                            <span class="text-navy-900">{{ $entry->actionLabel() }}</span>
                            <span class="text-xs text-slate-500">{{ $entry->created_at?->format('j M Y, g:i a') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-app-layout>
