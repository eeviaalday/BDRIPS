<x-app-layout>
    <x-slot name="title">Document Review Queue</x-slot>

    <div class="space-y-4">
        @php
            $tabs = [
                'queued' => 'Queued',
                'ready_for_pickup' => 'Ready for Pickup',
                'completed' => 'Completed',
                'rejected' => 'Rejected',
                'abandoned' => 'Abandoned',
            ];
        @endphp

        <div class="flex flex-wrap gap-2">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('staff.requests.index', ['status' => $key]) }}"
                    class="rounded-full px-4 py-1.5 text-sm font-medium transition
                        {{ $status === $key ? 'bg-blue-700 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @if ($requests->isEmpty())
            <div class="rounded-xl bg-white border border-gray-200 p-10 text-center text-sm text-gray-500">
                No requests in this queue.
            </div>
        @else
            <div class="overflow-x-auto rounded-xl bg-white border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Reference</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Resident</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Document</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Purpose</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Submitted</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($requests as $request)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-mono font-semibold text-blue-700">{{ $request->reference_number }}</td>
                                <td class="px-4 py-3">
                                    {{ $request->user->name }}
                                    <span class="block text-xs text-gray-400">{{ $request->user->residentProfile?->household_number ?? 'No profile' }}</span>
                                </td>
                                <td class="px-4 py-3">{{ $request->documentTypeLabel() }}</td>
                                <td class="px-4 py-3 text-gray-500 max-w-[220px] truncate">{{ $request->purpose }}</td>
                                <td class="px-4 py-3"><x-status-badge :status="$request->status" /></td>
                                <td class="px-4 py-3 text-gray-500">{{ $request->created_at->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <a href="{{ route('staff.requests.show', $request) }}" class="text-blue-700 hover:text-blue-900 font-medium">Review</a>
                                    @if ($request->status === 'ready_for_pickup')
                                        <a href="{{ route('staff.requests.pdf', $request) }}" class="text-blue-700 hover:text-blue-900 font-medium">PDF</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $requests->links() }}
        @endif
    </div>
</x-app-layout>