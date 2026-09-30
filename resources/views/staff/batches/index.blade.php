<x-app-layout>
    <x-slot name="title">Relief Batches</x-slot>

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">Create relief batches and monitor distribution across sectors.</p>
            <a href="{{ route('staff.batches.create') }}"
                class="rounded-lg bg-blue-700 hover:bg-blue-800 px-4 py-2 text-sm font-semibold text-white transition">
                + New Relief Batch
            </a>
        </div>

        @if ($batches->isEmpty())
            <div class="rounded-xl bg-white border border-gray-200 p-10 text-center text-sm text-gray-500">
                No relief batches yet. Create one to start mapping households.
            </div>
        @else
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($batches as $batch)
                    <a href="{{ route('staff.batches.show', $batch) }}" class="rounded-xl bg-white border border-gray-200 p-5 hover:shadow transition">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-semibold text-gray-900">{{ $batch->title }}</p>
                            <x-status-badge :status="$batch->status" />
                        </div>
                        <p class="text-sm text-gray-500 mt-1">{{ $batch->distribution_date?->format('M d, Y') }}</p>

                        @if ($batch->getItemsAttribute())
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($batch->getItemsAttribute() as $item)
                                    <span class="rounded-full bg-green-50 border border-green-200 px-2.5 py-0.5 text-xs text-green-700">{{ $item }}</span>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-lg bg-gray-50 p-3">
                                <p class="text-gray-500">Total</p>
                                <p class="font-bold text-gray-900">{{ $batch->distributions_count }}</p>
                            </div>
                            <div class="rounded-lg bg-green-50 p-3">
                                <p class="text-green-700">Received</p>
                                <p class="font-bold text-green-800">{{ $batch->received_count }}</p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
            {{ $batches->links() }}
        @endif
    </div>
</x-app-layout>