{{--
    Settings, one panel per group.

    Rendered from the rows rather than from a hardcoded list, so a key added by
    a later migration appears here without a code change. The field name is the
    key, because the public templates read these by key and this screen must not
    be able to invent one.
--}}
<x-app-layout :user="auth()->user()" title="Settings">
    <div class="mx-auto w-full max-w-3xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'Settings']]" />

        <header>
            <h1 class="text-xl font-bold text-navy-900">Settings</h1>
            <p class="mt-1 text-sm text-gray-600">
                These are the values the public pages read. Changing one changes every page that
                shows it, immediately.
            </p>
        </header>

        @if (session('success'))
            <x-alert variant="success" icon="check-circle">{{ session('success') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert variant="warning" icon="x-circle">{{ session('error') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="danger" icon="x-circle">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <form method="POST" action="{{ route('staff.settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            @foreach ($groups as $group)
                <x-card tone="white" padding="p-6">
                    <h2 class="mb-4 text-base font-bold text-navy-900">{{ $group['label'] }}</h2>

                    <div class="space-y-4">
                        @foreach ($group['settings'] as $setting)
                            <div>
                                <label for="setting-{{ $setting->key }}" class="block text-sm font-semibold text-navy-800">
                                    {{ \Illuminate\Support\Str::headline($setting->key) }}
                                </label>
                                @if ($setting->description)
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $setting->description }}</p>
                                @endif
                                <input id="setting-{{ $setting->key }}" name="values[{{ $setting->key }}]" type="text"
                                    maxlength="2000" value="{{ old('values.'.$setting->key, $setting->value) }}"
                                    class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endforeach

            <div class="flex justify-end">
                <x-btn type="submit" variant="navy" size="md" icon="check">Save settings</x-btn>
            </div>
        </form>
    </div>
</x-app-layout>
