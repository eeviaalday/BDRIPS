{{--
    System status.

    No credential appears here. The database line shows the server version and
    the schema name, never the username or password, and the environment line
    reports whether a variable is set rather than what it holds - a status page
    that leaked a connection string would be a worse problem than the one it
    diagnoses.
--}}
<x-app-layout :user="auth()->user()" title="System Status">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'System Status']]" />

        <header>
            <h1 class="text-xl font-bold text-navy-900">System Status</h1>
            <p class="mt-1 text-sm text-gray-600">What an Admin needs before a resident reports a problem.</p>
        </header>

        <div class="space-y-4">
            @foreach ([
                ['Database', $database],
                ['Private storage', $storage],
                ['Migrations', $migrations],
                ['Environment', $environment],
            ] as [$name, $check])
                <x-card tone="white" padding="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-navy-900">{{ $name }}</h2>
                            <p class="mt-1 text-sm text-slate-600">{{ $check['detail'] }}</p>
                        </div>
                        <x-status-badge :status="$check['ok'] ? 'active' : 'rejected'" :label="$check['label']" />
                    </div>

                    @if (($check['issues'] ?? []) !== [])
                        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-amber-800">
                            @foreach ($check['issues'] as $issue)
                                <li>{{ $issue }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if (($check['pending'] ?? []) !== [])
                        <ul class="mt-3 list-disc space-y-1 pl-5 font-mono text-xs text-amber-800">
                            @foreach ($check['pending'] as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endforeach
        </div>

        <x-card tone="white" padding="p-6">
            <h2 class="mb-4 text-base font-bold text-navy-900">Configuration keys</h2>
            <p class="mb-4 text-sm text-slate-500">
                Set or not set. The values are not shown on purpose.
            </p>

            <ul class="grid gap-2 sm:grid-cols-2">
                @foreach ($environment['keys'] as $variable => $isSet)
                    <li class="flex items-center gap-2 text-sm">
                        <span @class([
                            'inline-flex h-5 w-5 items-center justify-center rounded-full text-xs',
                            'bg-emerald-100 text-emerald-700' => $isSet,
                            'bg-red-100 text-red-700' => ! $isSet,
                        ]) aria-hidden="true">{{ $isSet ? '✓' : '✗' }}</span>
                        <span class="font-mono text-navy-900">{{ $variable }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card tone="white" padding="p-6">
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
