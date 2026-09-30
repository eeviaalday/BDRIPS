<x-app-layout>
    <x-slot name="title">{{ $batch->title }}</x-slot>

    <div class="space-y-6">
        <div class="rounded-xl bg-white border border-gray-200 p-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">{{ $batch->title }}</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $batch->distribution_date?->format('F d, Y') }} &middot; {{ $batch->distributions_count }} households mapped
                    </p>
                    @if ($batch->description)
                        <p class="text-sm text-gray-600 mt-2">{{ $batch->description }}</p>
                    @endif
                    @if ($batch->getItemsAttribute())
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($batch->getItemsAttribute() as $item)
                                <span class="rounded-full bg-green-50 border border-green-200 px-2.5 py-0.5 text-xs text-green-700">{{ $item }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
                <form method="POST" action="{{ route('staff.batches.status', $batch) }}" class="flex items-center gap-2">
                    @csrf
                    <select name="status" onchange="this.form.submit()"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="active" @selected($batch->status === 'active')>Active</option>
                        <option value="completed" @selected($batch->status === 'completed')>Completed</option>
                    </select>
                </form>
            </div>

            {{-- Overall progress --}}
            @php
                $total = $sectorStats->sum('total');
                $received = $sectorStats->sum('received');
                $percent = $total > 0 ? round($received / $total * 100) : 0;
            @endphp
            <div class="mt-6">
                <div class="flex justify-between text-sm text-gray-600 mb-1">
                    <span>{{ $received }} / {{ $total }} households served</span>
                    <span class="font-semibold">{{ $percent }}%</span>
                </div>
                <div class="h-3 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full rounded-full bg-blue-600 transition-all" style="width: {{ $percent }}%"></div>
                </div>
            </div>
        </div>

        {{-- Per-sector breakdown --}}
        <div class="rounded-xl bg-white border border-gray-200 p-6">
            <h2 class="font-bold text-gray-900 mb-4">Distribution by Sector</h2>

            @if ($sectorStats->isEmpty())
                <p class="text-sm text-gray-500">No households mapped yet for this batch.</p>
            @else
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($sectorStats as $stat)
                        <div class="rounded-lg border border-gray-200 p-4">
                            <p class="font-semibold text-gray-900">{{ $stat['sector']->sector_name }}</p>
                            <p class="text-xs text-gray-500">
                                {!! $stat['sector']->kagawad ? 'Kagawad: <strong>' . e($stat['sector']->kagawad->name) . '</strong>' : '<em>No Kagawad assigned</em>' !!}
                            </p>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-center text-sm">
                                <div class="rounded bg-gray-50 py-2"><p class="text-gray-500 text-xs">Total</p><p class="font-bold">{{ $stat['total'] }}</p></div>
                                <div class="rounded bg-green-50 py-2"><p class="text-green-700 text-xs">Received</p><p class="font-bold text-green-800">{{ $stat['received'] }}</p></div>
                                <div class="rounded bg-amber-50 py-2"><p class="text-amber-700 text-xs">Pending</p><p class="font-bold text-amber-800">{{ $stat['pending'] }}</p></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>