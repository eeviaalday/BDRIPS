{{--
    User accounts.

    Verification is not editable here, and the column is not a control: it is
    set from the resident's own registration evidence, and an Admin who could flip
    it would make the verification queue decorative. It is shown for residents
    only: "Pending" against a Secretary's name is not a statement about anything,
    it is a column that has run out of applicable rows and says nothing.

    The whole row opens the account, so there is no "Open" column - a row that is
    already clickable offering a button that does the same thing is two controls
    for one destination. What is left in the Actions column is the action the row's
    own link cannot do: lifting a restriction, which is a POST.
--}}
<x-app-layout :user="auth()->user()" title="User Accounts">
    <div class="mx-auto w-full max-w-6xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'User Accounts']]" />

        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-navy-900">User Accounts</h1>
                <p class="mt-1 text-sm text-gray-600">Roles, restrictions, and who is in the system.</p>
            </div>
        </header>

        {{--
            The role cards are filters, not figures.

            Each links to the same list narrowed to one role, and the card for the
            role currently being filtered is marked with aria-current plus a
            visible ring - so the reader can see which filter is on without
            reading the table. "All roles" is the first card and is the default
            state: it links to the unfiltered list, and with no ?role in the query
            string no role predicate is applied, so nothing is hidden by default.

            The numbers are the whole-system counts from AccountController and
            deliberately do NOT change when a filter is applied. A card that
            recounted the filtered list would show zero for the three roles you
            are not looking at, which reads as "there are no Kagawad" rather than
            "you are looking at Secretaries".
        --}}
        <nav aria-label="Filter accounts by role">
            <ul class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5 [&>*]:min-w-0">
                @php $activeRole = in_array($role, $roles, true) ? $role : ''; @endphp

                @php
                    $roleCards = array_merge(
                        ['' => 'All roles'],
                        array_combine(
                            $roles,
                            array_map(fn (string $r): string => \App\Models\User::roleLabel($r), $roles)
                        )
                    );
                @endphp

                @foreach ($roleCards as $cardValue => $cardLabel)
                    @php
                        $isActive = $activeRole === $cardValue;
                        // All roles is the default, so it is the active card when
                        // no role filter is in the query string.
                        $isAll = $cardValue === '';
                    @endphp
                    <li class="min-w-0">
                        <a href="{{ route('staff.accounts.index', $isAll ? [] : ['role' => $cardValue]) }}"
                            @if ($isActive) aria-current="true" @endif
                            @class([
                                'block rounded-xl border p-5 shadow-card transition duration-200',
                                'border-navy-800 bg-navy text-white shadow-md' => $isActive,
                                'border-navy-100 bg-white hover:border-navy-200 hover:shadow-md' => ! $isActive,
                            ])>
                            <div class="flex items-center justify-between gap-3">
                                <p @class([
                                    'text-3xl font-extrabold tabular-nums tracking-tight',
                                    'text-white' => $isActive,
                                    'text-navy-900' => ! $isActive,
                                ])>
                                    @if ($isAll)
                                        {{ array_sum($counts) }}
                                    @else
                                        {{ $counts[$cardValue] ?? 0 }}
                                    @endif
                                </p>
                                @if ($isActive)
                                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/15"
                                        aria-hidden="true">
                                        <x-icon name="check" class="h-4 w-4" />
                                    </span>
                                @else
                                    <span aria-hidden="true"
                                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-navy-100">
                                        <x-icon name="users" class="h-5 w-5 text-navy-800" />
                                    </span>
                                @endif
                            </div>
                            <p @class([
                                'mt-3 text-sm font-semibold',
                                'text-white' => $isActive,
                                'text-navy-700' => ! $isActive,
                            ])>{{ $cardLabel }}</p>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <x-staff.filter-bar :action="route('staff.accounts.index')"
            :has-filters="$search !== '' || $role !== ''"
            :clear-href="route('staff.accounts.index')">
            <x-staff.filter-field name="q" label="Search" :value="$search" placeholder="Name or email" />
            <x-staff.filter-select name="role" label="Role" :options="$roles"
                :value="$role" all-label="All roles"
                :map-label="fn (string $value): string => \App\Models\User::roleLabel($value)" />
        </x-staff.filter-bar>

        <x-staff.table
            :columns="[
                ['label' => 'Account'],
                ['label' => 'Role'],
                ['label' => 'Verification'],
                ['label' => 'Status'],
                ['label' => 'Actions', 'align' => 'right'],
            ]"
            caption="User accounts"
            empty-title="No accounts match these filters"
            empty-description="Try a different name, email, or role."
            empty-icon="users"
            empty-action-label="Clear filters"
            :empty-action-href="route('staff.accounts.index')">
            @foreach ($users as $account)
                <tr @class(['opacity-70' => $account->isRestricted()])
                    x-on:click="openRow($event, @js(route('staff.accounts.show', $account)))">
                    <th scope="row">
                        {{-- Email for every role, deliberately. It is the one
                             identifier all four roles share; showing a household
                             number for residents instead would hide the address a
                             staff member most often needs to match against a
                             paper list. The household number is on the detail page. --}}
                        <x-staff.person-cell :name="$account->name" :secondary="$account->email"
                            :href="route('staff.accounts.show', $account)" chevron />
                    </th>
                    <td class="text-sm text-slate-600">{{ \App\Models\User::roleLabel($account->role) }}</td>
                    <td>
                        @if ($account->isResident())
                            <x-staff.status-badge :status="$account->is_verified ? 'verified' : 'pending'" size="sm" />
                        @else
                            {{-- An em dash, not a grey badge. "Pending" beside a
                                 Secretary is not a state that applies, and a badge
                                 would imply it is one that does. --}}
                            <span class="text-slate-400" aria-label="Not applicable to this role">&mdash;</span>
                        @endif
                    </td>
                    <td>
                        <x-staff.status-badge :status="$account->isRestricted() ? 'restricted' : 'active'" size="sm" />
                    </td>
                    <td>
                        <div class="flex flex-wrap items-center justify-end gap-1.5">
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
        </x-staff.table>

        <x-staff.pagination :paginator="$users" />
    </div>
</x-app-layout>
