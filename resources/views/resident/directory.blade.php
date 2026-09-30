<x-public-layout>
    <x-slot name="title">Public Information</x-slot>

    {{-- The resident-scoped copy of the directory. Same officials as the public
         page - config('bdrpis.officials') - so there is one list to correct,
         reached without leaving the portal. --}}
    <x-card tone="white">
        <h2 class="text-base font-bold text-navy-900">Barangay Officials</h2>
        <p class="mt-1 text-sm text-navy-600">
            The officers and SK councillors serving Zone 1, and the sectors they cover.
        </p>

        @foreach ([
            'barangay' => 'Barangay Officials',
            'sk' => 'Sangguniang Kabataan (SK) Council',
        ] as $key => $groupLabel)
            <h3 class="mt-6 text-xs font-bold uppercase tracking-wider text-navy-500">{{ $groupLabel }}</h3>

            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach (config('bdrpis.officials.'.$key.'.people') as $person)
                    <div class="flex items-center gap-3 rounded-xl border border-navy-100 bg-navy-50/40 px-3 py-3">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-navy text-xs font-bold text-white"
                            aria-hidden="true">
                            {{ collect(explode(' ', $person['name']))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-navy-900">{{ $person['name'] }}</p>
                            <p class="text-xs text-navy-500">{{ $person['role'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </x-card>

    <x-card tone="white" class="mt-6">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-base font-bold text-navy-900">Latest Announcements</h2>
            <span class="text-sm font-medium text-navy-500">{{ $notices->count() }} shown</span>
        </div>

        @if ($notices->isEmpty())
            <x-empty-state icon="megaphone" title="No announcements yet"
                description="Notices from the barangay will appear here as they are published." class="mt-4" />
        @else
            <ul class="mt-4 divide-y divide-navy-50">
                @foreach ($notices as $notice)
                    <li class="py-4">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($notice->isPinned())
                                <span class="inline-flex items-center gap-1 rounded-full bg-gold-50 px-2 py-0.5 text-2xs font-bold uppercase tracking-wide text-gold-800 border border-gold-300">
                                    Pinned
                                </span>
                            @endif
                            <span class="text-xs font-semibold uppercase tracking-wider text-navy-500">
                                {{ $notice->categoryLabel() }}
                            </span>
                            @if ($notice->location)
                                <span class="text-xs text-navy-400">&middot; {{ $notice->location }}</span>
                            @endif
                        </div>
                        <p class="mt-1 font-semibold text-navy-900">{{ $notice->title }}</p>
                        <p class="mt-1 text-sm text-navy-600">{{ $notice->excerpt() }}</p>
                        <p class="mt-1.5 text-xs text-navy-400">
                            {{ $notice->published_at->format('M d, Y') }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-public-layout>
