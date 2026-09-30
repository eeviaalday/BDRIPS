{{--
    User accounts.

    Verification is not editable here, and the column is not a control: it is
    set from the resident's own registration evidence, and an Admin who could
    flip it would make the verification queue decorative.
--}}
<x-app-layout :user="auth()->user()" title="User Accounts">
    <div class="mx-auto w-full max-w-6xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'User Accounts']]" />

        <header>
            <h1 class="text-xl font-bold text-navy-900">User Accounts</h1>
            <p class="mt-1 text-sm text-gray-600">Roles, restrictions, and who is in the system.</p>
        </header>

        <div class="grid gap-4 sm:grid-cols-4">
            @foreach ($counts as $role => $total)
                <x-stat-card :label="\App\Models\User::roleLabel($role)" :value="$total" tone="navy" icon="users" variant="badged" />
            @endforeach
        </div>

        <form method="GET" action="{{ route('staff.accounts.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[16rem] flex-1">
                <label for="q" class="block text-sm font-semibold text-navy-800">Search</label>
                <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Name or email"
                    class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
            </div>
            <div>
                <label for="role" class="block text-sm font-semibold text-navy-800">Role</label>
                <select id="role" name="role" class="mt-1.5 rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                    <option value="">Every role</option>
                    @foreach ($roles as $option)
                        <option value="{{ $option }}" @selected($role === $option)>{{ \App\Models\User::roleLabel($option) }}</option>
                    @endforeach
                </select>
            </div>
            <x-btn type="submit" variant="navy" size="md" icon="search">Filter</x-btn>
        </form>

        <x-data-table :columns="[
            ['label' => 'Account'],
            ['label' => 'Role'],
            ['label' => 'Verified'],
            ['label' => 'Status'],
            ['label' => 'Actions'],
        ]" caption="User accounts" empty-title="No accounts match"
            empty-description="Try a different name, email, or role.">
            @foreach ($users as $account)
                <tr @class(['opacity-70' => $account->isRestricted()])>
                    <th scope="row" class="px-4 py-3 text-sm font-semibold text-navy-900">
                        <a href="{{ route('staff.accounts.show', $account) }}" class="hover:underline">{{ $account->name }}</a>
                        <span class="block text-xs font-normal text-slate-500">{{ $account->email }}</span>
                    </th>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ \App\Models\User::roleLabel($account->role) }}</td>
                    <td class="px-4 py-3">
                        @if ($account->is_verified)
                            <x-status-badge status="active" size="sm" />
                        @else
                            <x-status-badge status="pending" size="sm" />
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm">
                        @if ($account->isRestricted())
                            <x-status-badge status="rejected" size="sm" />
                        @else
                            <x-status-badge status="active" size="sm" />
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <x-btn :href="route('staff.accounts.show', $account)" variant="ghost" size="sm">Open</x-btn>

                            @if ($account->isRestricted())
                                <form method="POST" action="{{ route('staff.accounts.unrestrict', $account) }}">
                                    @csrf
                                    <x-btn type="submit" variant="outline" size="sm" icon="check">Lift restriction</x-btn>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div>{{ $users->links() }}</div>
    </div>
</x-app-layout>
