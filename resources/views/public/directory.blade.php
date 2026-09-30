<x-public-layout>
    <x-slot name="title">Officials Directory</x-slot>

    {{-- Plain &, not &amp;: a Blade component attribute carrying an entity
         passes the literal "&amp;" through to {{ }}, which escapes the
         ampersand a second time and the heading renders "&amp;" on screen. --}}
    <x-page-header :crumbs="['Officials Directory']" title="Barangay Officials & Directory"
        description="Executive officials, the Barangay Secretary, the public information line, and the Sector Kagawads assigned to each sector." />

    <x-shell>
        {{-- ============================================================
             Executive officials
             ============================================================ --}}
        <section aria-labelledby="executive-heading">
            <h2 id="executive-heading" class="sr-only">Barangay Executive</h2>

            <x-section-heading eyebrow="Leadership" title="Barangay Executive"
                description="The Punong Barangay, the Barangay Secretary, and the public information line." />

            <div class="mt-8 grid gap-6 md:grid-cols-2">
                <x-official-card featured
                    :name="$officials['punong_barangay']"
                    position="Punong Barangay"
                    meta="Village head & chief executive of the barangay" />

                <x-official-card featured
                    :name="$officials['secretary']"
                    position="Barangay Secretary"
                    meta="Records, documents & resident verification" />
            </div>

            <div class="mt-6 flex flex-col gap-4 rounded-xl border border-red-100 bg-red-50 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-white text-red-600 shadow-card">
                        <x-icon name="phone" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-red-700">Emergency Hotline</p>
                        <p class="mt-0.5 text-sm text-red-700/80">Public information line for the barangay.</p>
                    </div>
                </div>

                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $officials['hotline']) }}"
                    class="text-xl font-extrabold text-red-800 underline underline-offset-4 sm:text-2xl">
                    {{ $officials['hotline'] }}
                </a>
            </div>
        </section>

        {{-- ============================================================
             Sector Kagawads
             ============================================================ --}}
        <section class="mt-16" aria-labelledby="kagawad-heading">
            <h2 id="kagawad-heading" class="sr-only">Sector Kagawads</h2>

            <x-section-heading eyebrow="Sectors" title="Sector Kagawads"
                description="Each sector is handled by a kagawad who assists residents of that purok." />

            @if ($sectors->isEmpty())
                <x-empty-state icon="users" title="Sector directory is being established"
                    description="Sector assignments have not been published yet. Please check back later." class="mt-8" />
            @else
                <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($sectors as $sector)
                        <x-card tone="white" padding="p-5" class="flex flex-col">
                            <div class="flex items-center gap-3.5">
                                @php
                                    $kagawadName = $sector->kagawad?->name ?? 'Vacant';
                                    $initials = \Illuminate\Support\Str::of($kagawadName)
                                        ->replace(['Hon.', 'Hon', 'Sir', 'Ms', 'Mrs'], ' ')
                                        ->squish()->explode(' ')->filter()->take(2)
                                        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('');
                                    $initials = $initials !== '' ? $initials : '?';
                                @endphp

                                <span @class([
                                    'inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-sm font-extrabold ring-4',
                                    'bg-navy-50 text-navy-700 ring-navy-100' => $sector->kagawad,
                                    'bg-sky-50 text-sky-700 ring-sky-100' => ! $sector->kagawad,
                                ])>
                                    {{ $initials }}
                                </span>

                                <div class="min-w-0">
                                    <p class="text-base font-bold leading-snug text-navy-900">{{ $kagawadName }}</p>
                                    <p class="text-sm font-semibold {{ $sector->kagawad ? 'text-sky-700' : 'text-navy-500' }}">
                                        {{ $sector->kagawad ? 'Sector Kagawad' : 'Unassigned' }}
                                    </p>
                                </div>
                            </div>

                            <dl class="mt-4 space-y-2 border-t border-navy-100 pt-4 text-sm">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-navy-500">Sector</dt>
                                    <dd class="text-right font-semibold text-navy-900">{{ $sector->sector_name }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-navy-500">Purok</dt>
                                    <dd class="text-right font-semibold text-navy-900">{{ $sector->purok_name ?? 'Not set' }}</dd>
                                </div>
                            </dl>
                        </x-card>
                    @endforeach
                </div>
            @endif
        </section>
    </x-shell>
</x-public-layout>
