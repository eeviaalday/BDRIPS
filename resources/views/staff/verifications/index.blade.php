<x-app-layout>
    <x-slot name="title">Resident Verification Queue</x-slot>

    <div class="space-y-5">
        <x-breadcrumb :items="[
            ['label' => 'Staff', 'route' => 'staff.dashboard'],
            ['label' => 'Resident Verification'],
        ]" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-stat-card variant="badged" tone="orange" icon="clock" label="Awaiting Review"
                :value="$profiles->where('verification_status', \App\Models\ResidentProfile::VERIFICATION_PENDING)->count()"
                hint="of the page shown" />
            <x-stat-card variant="badged" tone="green" icon="check-circle" label="Verified"
                :value="$profiles->where('verification_status', \App\Models\ResidentProfile::VERIFICATION_VERIFIED)->count()"
                hint="of the page shown" />
            <x-stat-card variant="badged" tone="navy" icon="inbox" label="Matching"
                :value="$profiles->total()" hint="after filters" />
        </div>

        <x-card tone="white" class="bg-navy-50/50">
            <h2 class="text-sm font-bold text-navy-900">Before approving</h2>
            <p class="mt-1 text-sm leading-relaxed text-navy-700">
                Check the submitted document against the Barangay Master List: the name and
                household number must match, and the document must be legible and current.
                Approving a profile unlocks that resident's document requests and adds their
                household to any future relief batch.
            </p>
        </x-card>

        <x-filter-bar
            :action="route('staff.verifications.index')"
            label="Name, email or household number"
            placeholder="e.g. Dela Cruz or HH-0001"
            :selects="[[
                'name' => 'status',
                'label' => 'Verification status',
                'options' => [
                    '' => 'Pending and verified',
                    'pending' => 'Pending only',
                    'verified' => 'Verified only',
                    'rejected' => 'Rejected only',
                ],
            ]]" />

        @if ($profiles->isEmpty())
            <x-card tone="white">
                @if ($search !== '' || ($status ?? '') !== '')
                    <x-empty-state icon="search" title="No matching profiles"
                        description="Nothing in the queue matches. Try a different name, email or household number.">
                        <a href="{{ route('staff.verifications.index') }}"
                            class="mt-5 inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-4 py-2 text-sm font-semibold text-navy-800 transition hover:bg-navy-50">
                            Clear filters
                        </a>
                    </x-empty-state>
                @else
                    <x-empty-state icon="check-circle" title="Nothing to review"
                        description="There are no resident profiles waiting for verification. New registrations appear here as soon as they are submitted." />
                @endif
            </x-card>
        @else
            <x-card tone="white" padding="p-0">
                <x-data-table
                    class="rounded-none border-0"
                    caption="Resident profiles awaiting or holding verification"
                    :columns="[
                        ['label' => 'Resident'],
                        ['label' => 'Household #'],
                        ['label' => 'Sector'],
                        ['label' => 'Status'],
                        ['label' => 'Submitted'],
                        ['label' => 'Action'],
                    ]">

                    @foreach ($profiles as $profile)
                        <tr class="hover:bg-navy-50/40">
                            <td class="px-4 py-3 align-middle">
                                <span class="font-medium text-navy-900">{{ $profile->user?->name ?? 'Unknown resident' }}</span>
                                <span class="block text-xs text-navy-500">{{ $profile->user?->email }}</span>
                            </td>
                            <td class="px-4 py-3 align-middle font-mono text-sm text-navy-700">{{ $profile->household_number }}</td>
                            <td class="px-4 py-3 align-middle text-navy-700">{{ $profile->sector?->sector_name ?? '—' }}</td>
                            <td class="px-4 py-3 align-middle"><x-status-badge :status="$profile->verification_status" size="sm" /></td>
                            <td class="px-4 py-3 align-middle whitespace-nowrap text-navy-600">
                                {{ $profile->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-4 py-3 align-middle text-right">
                                <a href="{{ route('staff.verifications.show', $profile) }}"
                                    class="font-semibold text-navy-700 hover:text-navy-900">Review</a>
                            </td>
                        </tr>
                    @endforeach
                </x-data-table>

                @if ($profiles->hasPages())
                    <div class="border-t border-navy-100 px-5 py-4">
                        {{ $profiles->links() }}
                    </div>
                @endif
            </x-card>
        @endif
    </div>
</x-app-layout>
