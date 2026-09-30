<x-app-layout>
    <x-slot name="title">{{ $announcement ? 'Edit Announcement' : 'New Announcement' }}</x-slot>

    <div class="max-w-2xl mx-auto">
        <div class="rounded-xl bg-white border border-gray-200 p-6">
            <h2 class="text-lg font-bold text-gray-900">{{ $announcement ? 'Edit Announcement' : 'New Announcement' }}</h2>

            <form method="POST" enctype="multipart/form-data"
                action="{{ $announcement ? route('staff.announcements.update', $announcement) : route('staff.announcements.store') }}"
                class="mt-6 space-y-5">
                @csrf
                @if ($announcement)
                    @method('PUT')
                @endif

                <div>
                    <x-input-label for="title" value="Title" />
                    <x-text-input id="title" name="title" type="text" required class="mt-1 block w-full"
                        value="{{ old('title', $announcement?->title) }}" />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="body" value="Body" />
                    <textarea id="body" name="body" rows="6" required
                        class="mt-1 block w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm">{{ old('body', $announcement?->body) }}</textarea>
                    <x-input-error :messages="$errors->get('body')" class="mt-2" />
                </div>

                {{-- The category drives the public card's tag and, when no photograph
                     is uploaded, the picture it shows. --}}
                <div>
                    <x-input-label for="category" value="Category" />
                    <select id="category" name="category"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach ($categories as $key => $label)
                            <option value="{{ $key }}"
                                @selected(old('category', $announcement?->category ?? 'general') === $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('category')" class="mt-2" />
                </div>

                {{-- The public card names this beside the date when it is filled in,
                     so it is a place, not an address: "Plaza" rather than a
                     street number. --}}
                <div>
                    <x-input-label for="location" value="Location" />
                    <input id="location" name="location" type="text" maxlength="160"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        value="{{ old('location', $announcement?->location) }}"
                        placeholder="e.g. Barangay Plaza" />
                    <p class="mt-1 text-xs text-gray-500">
                        Optional. Where the notice happens, shown on the public card.
                    </p>
                    <x-input-error :messages="$errors->get('location')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="image" value="Featured image" />
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"
                        class="mt-1 block w-full text-sm text-gray-700" />
                    <p class="mt-1 text-xs text-gray-500">
                        Optional. JPG, PNG or WebP up to 4MB. Without an image the public
                        card shows the photograph for the chosen category.
                    </p>
                    @if ($announcement?->hasImage())
                        <img src="{{ $announcement->imageUrl() }}" alt="Current featured image"
                            class="mt-3 h-32 w-56 rounded-lg object-cover" />
                    @endif
                    <x-input-error :messages="$errors->get('image')" class="mt-2" />
                </div>

                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="publish_now" value="1"
                            @checked(old('publish_now', $announcement?->published_at !== null))
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Publish immediately
                    </label>

                    {{-- Pinning lifts the notice into its own block at the top of
                         the public page and out of the feed, so it is for
                         something residents must not scroll past. Only the most
                         recently pinned notice is shown there. --}}
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="pinned" value="1" @checked(old('pinned', $announcement?->isPinned()))
                            class="mt-0.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                        <span>
                            Pin to the top of the public page
                            <span class="block text-xs text-gray-500">
                                Replaces the current pinned notice. Untick to unpin.
                            </span>
                        </span>
                    </label>
                    <x-input-error :messages="$errors->get('pinned')" />
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('staff.announcements.index') }}" class="text-sm text-gray-500 hover:text-gray-700 font-medium">Cancel</a>
                    <button type="submit"
                        class="rounded-lg bg-blue-700 hover:bg-blue-800 px-5 py-2.5 text-sm font-semibold text-white transition">
                        {{ $announcement ? 'Update' : 'Save' }} Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>