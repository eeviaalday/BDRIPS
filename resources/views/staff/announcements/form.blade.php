{{--
    The announcement editor, used for both creating and editing.

    One form rather than two, because they write the same columns and a pair of
    forms editing the same record is a pair of forms that can disagree. The
    publication date is optional: left blank it stays a draft, which is the
    default state and therefore the safe one.
--}}
<x-app-layout :user="auth()->user()" :title="$announcement->exists ? 'Edit announcement' : 'New announcement'">
    <div class="mx-auto w-full max-w-3xl space-y-6">
        <x-breadcrumb :items="[
            ['label' => 'Staff', 'route' => 'staff.dashboard'],
            ['label' => 'Announcements', 'route' => 'staff.announcements.index'],
            ['label' => $announcement->exists ? 'Edit' : 'New'],
        ]" />

        <header>
            <h1 class="text-xl font-bold text-navy-900">
                {{ $announcement->exists ? 'Edit announcement' : 'New announcement' }}
            </h1>
            <p class="mt-1 text-sm text-gray-600">
                Leave the publication date blank to keep it as a draft. The public page shows only
                notices whose date has passed.
            </p>
        </header>

        @if ($errors->any())
            <x-alert variant="danger" icon="x-circle">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <x-card tone="white" padding="p-6">
            <form method="POST"
                action="{{ $announcement->exists ? route('staff.announcements.update', $announcement) : route('staff.announcements.store') }}"
                class="space-y-5">
                @csrf
                @if ($announcement->exists)
                    @method('PUT')
                @endif

                <x-field id="title" name="title" label="Title" variant="soft" required
                    :value="old('title', $announcement->title)" />

                <div>
                    <label for="body" class="block text-sm font-semibold text-navy-800">Notice</label>
                    <textarea id="body" name="body" rows="10" maxlength="20000" required
                        class="mt-1.5 w-full rounded-lg border border-navy-200 bg-navy-50/60 px-3.5 py-2.5 text-sm focus:border-navy-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-200">{{ old('body', $announcement->body) }}</textarea>
                    <p class="mt-1.5 text-xs text-slate-500">
                        Line breaks are kept as written on the public page.
                    </p>
                </div>

                <x-select-field id="category" name="category" label="Category" variant="soft"
                    placeholder="No category"
                    :options="$categories"
                    :value="old('category', $announcement->category)" />

                <x-field id="location" name="location" label="Location" variant="soft"
                    :value="old('location', $announcement->location)"
                    hint="Optional. A court, a hall, a purok - only if the notice is about a place." />

                <x-field id="published_at" name="published_at" label="Publish on" type="datetime-local"
                    variant="soft"
                    :value="old('published_at', $announcement->published_at?->format('Y-m-d\TH:i'))"
                    hint="Blank keeps it a draft. A future date schedules it." />

                <div class="flex justify-end gap-3 border-t border-navy-100 pt-5">
                    <x-btn :href="route('staff.announcements.index')" variant="outline" size="md">Cancel</x-btn>
                    <x-btn type="submit" variant="navy" size="md" icon="megaphone">
                        {{ $announcement->exists ? 'Save changes' : 'Save announcement' }}
                    </x-btn>
                </div>
            </form>
        </x-card>

        @if ($announcement->exists)
            <x-card tone="white" padding="p-6">
                <h2 class="mb-4 text-base font-bold text-navy-900">Pin and publish</h2>

                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[14rem]">
                        <label for="pinned_at" class="block text-sm font-semibold text-navy-800">Pin from</label>
                        <input id="pinned_at" name="pinned_at" type="datetime-local" form="pin-form"
                            value="{{ $announcement->pinned_at?->format('Y-m-d\TH:i') }}"
                            class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                    </div>
                    <x-btn type="submit" form="pin-form" variant="outline" size="md" icon="pin">Schedule and pin</x-btn>

                    <form method="POST" action="{{ route('staff.announcements.publish', $announcement) }}">
                        @csrf
                        <x-btn type="submit" variant="ghost" size="md">
                            {{ $announcement->isPublished() ? 'Unpublish now' : 'Publish now' }}
                        </x-btn>
                    </form>
                </div>

                <form id="pin-form" method="POST" action="{{ route('staff.announcements.pin', $announcement) }}" class="hidden">
                    @csrf
                </form>

                <p class="mt-3 text-xs leading-relaxed text-slate-500">
                    Pinning lifts the newest pinned notice above the feed on the public announcements
                    page, and only one notice is pinned at a time.
                </p>
            </x-card>
        @endif
    </div>
</x-app-layout>
