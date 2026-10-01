{{--
    System status.

    No credential appears here. The database line shows the server version and
    the schema name, never the username or password, and the environment line
    reports whether a variable is set rather than what it holds - a status page
    that leaked a connection string would be a worse problem than the one it
    diagnoses.

    The banner at the top is the same verdict the staff dashboard's Admin strip
    shows, computed by the same SystemHealth::summary(), so the two cannot disagree
    about whether the system is well. One of them being green while the other is
    amber would mean one of them is lying.
--}}
<x-app-layout :user="auth()->user()" title="System Status">
    @php
        $summary = $health->summary();
        $checks = [
            'Database' => $database,
            'Private storage' => $storage,
            'Migrations' => $migrations,
            'Environment' => $environment,
        ];
    @endphp

    <div class="mx-auto w-full max-w-5xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'System Status']]" />

        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-navy-900">System Status</h1>
                <p class="mt-1 text-sm text-gray-600">What an Admin needs before a resident reports a problem.</p>
            </div>

            <div class="flex items-center gap-2">
                <p class="text-xs text-navy-500">
                    Last checked
                    {{-- To the second, so a Re-check visibly changes it. --}}
                    <span class="font-semibold tabular-nums text-navy-800">{{ $checkedAt->format('j M Y, g:i:s a') }}</span>
                </p>
                {{-- A plain reload. Every check here is a live read, so a reload is
                     a re-check; there is nothing cached to clear and no reason for
                     this to be anything but the button a reader expects. --}}
                <x-btn :href="route('staff.status.index')" variant="secondary" size="md" icon="clock"
                    class="whitespace-nowrap">
                    Re-check
                </x-btn>
            </div>
        </header>

        {{-- The verdict. Green only when every check passed; amber otherwise, with
             the count of what needs attention rather than a vague "issues". --}}
        <div @class([
                'flex flex-wrap items-center justify-between gap-3 rounded-xl border p-5 shadow-card',
                'border-emerald-200 bg-emerald-50' => $summary['ok'],
                'border-amber-300 bg-amber-50' => ! $summary['ok'],
            ])>
            <div class="flex min-w-0 items-center gap-3">
                <span @class([
                        'inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full',
                        'bg-emerald-100 text-emerald-700' => $summary['ok'],
                        'bg-amber-100 text-amber-800' => ! $summary['ok'],
                    ]) aria-hidden="true">
                    <x-icon :name="$summary['ok'] ? 'check-circle' : 'help-circle'" class="h-6 w-6" />
                </span>
                <div class="min-w-0">
                    <p @class([
                            'text-base font-bold',
                            'text-emerald-800' => $summary['ok'],
                            'text-amber-900' => ! $summary['ok'],
                        ])>{{ $summary['label'] }}</p>
                    <p class="mt-0.5 text-sm text-navy-700">{{ $summary['detail'] }}</p>
                </div>
            </div>

            <x-staff.status-badge :status="$summary['ok'] ? 'active' : 'pending'"
                :label="$summary['ok'] ? 'Healthy' : 'Needs attention'" size="sm" />
        </div>

        {{-- The checks. Two columns of compact cards: each check is three facts,
             and stacked full-width they pushed the configuration keys a screen and
             a half down a page whose whole job is a glance. --}}
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($checks as $name => $check)
                <section aria-labelledby="check-{{ \Illuminate\Support\Str::slug($name) }}"
                    @class([
                        'rounded-xl border bg-white p-5 shadow-card',
                        'border-navy-100' => $check['ok'],
                        'border-amber-300' => ! $check['ok'],
                    ])>
                    <div class="flex items-start justify-between gap-3">
                        <h2 id="check-{{ \Illuminate\Support\Str::slug($name) }}"
                            class="text-sm font-bold text-navy-900">{{ $name }}</h2>
                        <x-staff.status-badge :status="$check['ok'] ? 'active' : 'rejected'"
                            :label="$check['label']" size="sm" />
                    </div>

                    <p class="mt-1.5 text-sm leading-relaxed text-navy-700">{{ $check['detail'] }}</p>

                    @if (($check['issues'] ?? []) !== [])
                        <ul class="mt-3 space-y-1.5">
                            @foreach ($check['issues'] as $issue)
                                <li class="flex items-start gap-2 text-sm text-amber-900">
                                    {{-- A severity icon per issue, not a colour: "3 to
                                         review" is a count, and the reader has to be
                                         able to see which line is which without
                                         matching shades. --}}
                                    <x-icon name="x-circle" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                                    <span>{{ $issue }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if (($check['pending'] ?? []) !== [])
                        <ul class="mt-3 space-y-1 font-mono text-xs text-amber-900">
                            @foreach ($check['pending'] as $line)
                                <li class="flex items-start gap-2">
                                    <x-icon name="clock" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-600" />
                                    <span>{{ $line }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>

        {{-- Configuration keys. Chips, set or not set, and never a value. --}}
        <x-card tone="white" padding="p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="text-base font-bold text-navy-900">Configuration keys</h2>
                    <p class="mt-0.5 text-sm text-slate-500">
                        Whether each one is set. The values are not shown, and cannot be: a status
                        page that printed a password would be a worse problem than the one it
                        diagnoses.
                    </p>
                </div>
            </div>

            <ul class="mt-4 flex flex-wrap gap-2">
                @foreach ($environment['keys'] as $variable => $isSet)
                    <li>
                        <span @class([
                                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 font-mono text-xs font-medium',
                                'border-emerald-200 bg-emerald-50 text-emerald-800' => $isSet,
                                'border-red-200 bg-red-50 text-red-800' => ! $isSet,
                            ])>
                            <span @class([
                                    'inline-flex h-4 w-4 items-center justify-center rounded-full text-2xs font-bold',
                                    'bg-emerald-200 text-emerald-900' => $isSet,
                                    'bg-red-200 text-red-900' => ! $isSet,
                                ]) aria-hidden="true">{{ $isSet ? '✓' : '✗' }}</span>
                            {{ $variable }}
                            {{-- Spelled out as well as coloured: the tick and the cross
                                 are the same shape, so the word is the real signal. --}}
                            <span class="sr-only">{{ $isSet ? 'is set' : 'is not set' }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card tone="white" padding="p-5">
            <h2 class="mb-4 text-base font-bold text-navy-900">What the system is holding</h2>

            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                @foreach ($counts as $label => $total)
                    <div class="flex items-baseline justify-between border-b border-navy-50 pb-2">
                        <dt class="text-sm capitalize text-slate-600">{{ str_replace('_', ' ', $label) }}</dt>
                        <dd class="text-sm font-semibold tabular-nums text-navy-900">{{ number_format($total) }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-card>
    </div>
</x-app-layout>
