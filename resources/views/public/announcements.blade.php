<x-public-layout>
    <x-slot name="title">Announcements</x-slot>

    <x-page-header :crumbs="['Announcements']" title="Community Announcements"
        description="Notices published by the barangay: clean-ups, health missions, assemblies, assistance and advisories. Pinned notices sit at the top until they are replaced." />

    @php
        /*
         * The public notice board.
         *
         * Everything on this page is driven by the announcements table. The
         * filter bar, the featured notice and the feed are the same records
         * read three ways, and the sidebar holds the two things a visitor
         * usually wants beside a notice: how to reach the barangay, and when it
         * is open.
         *
         * The pills are buckets over the stored categories rather than
         * categories themselves, and "all" is the reset - see
         * config/bdrpis.php. Tones below are the only colour decision here: the
         * chips are tinted, never solid, so the notice titles keep the
         * strongest contrast on the page.
         */
        $filterTones = [
            'slate' => 'bg-slate-100 text-slate-700',
            'rose' => 'bg-rose-50 text-rose-700',
            'amber' => 'bg-amber-50 text-amber-700',
            'emerald' => 'bg-emerald-50 text-emerald-700',
            'sky' => 'bg-sky-50 text-sky-700',
        ];

        $dotTones = [
            'amber' => 'bg-amber-500',
            'emerald' => 'bg-emerald-500',
            'sky' => 'bg-sky-500',
            'slate' => 'bg-slate-400',
        ];
    @endphp

    <x-shell>
        {{-- ============================================================
             CONTROL BAR
             ============================================================
             One form so a search, a pill and a sort all submit together, and
             a pill is a link rather than a control inside it: a link can be
             shared, bookmarked and worked by with the keyboard's usual
             expectations for a link. The sort drives the same form on change.
        --}}
        <form method="GET" action="{{ route('announcements.index') }}" role="search"
            class="mb-8 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="relative w-full min-w-0 sm:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <label for="announcement-search" class="sr-only">Search announcements</label>
                <input id="announcement-search" type="search" name="q" value="{{ $search }}"
                    placeholder="Search announcements, dates, or keywords"
                    class="w-full rounded-lg border-slate-200 py-2 pl-9 pr-3 text-sm shadow-sm placeholder:text-slate-400 focus:border-sky-500 focus:ring-sky-500" />
            </div>

            <div class="-mx-1 flex flex-1 flex-wrap items-center gap-2 px-1 sm:justify-center">
                @foreach (config('bdrpis.announcement_filters') as $key => $pill)
                    {{-- A pill is a link so it can be shared and bookmarked, and
                         it carries the rest of the query: a search survives a
                         change of category. Page is dropped, because page 3 of a
                         filter the reader just left is not page 3 of the new
                         one, and a short result set would open on an empty
                         page. --}}
                    <a href="{{ request()->fullUrlWithQuery([
                        'filter' => $key,
                        'q' => $search === '' ? null : $search,
                        'sort' => $sort === 'newest' ? null : $sort,
                        'page' => null,
                    ]) }}"
                        @if ($filter === $key) aria-current="true" @endif
                        @class([
                            'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition',
                            'bg-slate-900 text-white' => $filter === $key,
                            ($filterTones[$pill['tone']] ?? $filterTones['slate']) => $filter !== $key,
                        ])>
                        @if ($pill['icon'])
                            <x-icon :name="$pill['icon']" class="h-3.5 w-3.5" />
                        @endif
                        {{ $pill['label'] }}
                    </a>
                @endforeach
            </div>

            {{-- The chosen filter is not one of the form's own fields, so it is
                 carried here; without it, changing the sort would silently drop
                 the reader back onto the whole feed. --}}
            <input type="hidden" name="filter" value="{{ $filter }}" />

            <div class="shrink-0">
                <label for="announcement-sort" class="sr-only">Sort announcements</label>
                <select id="announcement-sort" name="sort" x-on:change="$el.form.submit()"
                    class="rounded-lg border-slate-200 py-2 pl-3 pr-8 text-sm font-medium shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    <option value="newest" @selected($sort === 'newest')>Newest First</option>
                    <option value="oldest" @selected($sort === 'oldest')>Oldest First</option>
                </select>
            </div>
        </form>

        {{-- ============================================================
             PINNED NOTICE
             ============================================================
             Only rendered when staff have pinned one. A pinned notice is lifted
             out of the feed rather than shown twice, so the count below stays
             the number of notices a reader has yet to see.
        --}}
        @if ($pinned)
            <section aria-labelledby="pinned-heading" class="mb-10">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 id="pinned-heading" class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        <span aria-hidden="true">📌</span>
                        Pinned Official Announcement
                    </h2>

                    <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-red-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-red-500" aria-hidden="true"></span>
                        Urgent Attention
                    </span>
                </div>

                <article x-data="{ open: false }" class="rounded-2xl border-2 border-amber-400 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-5 sm:flex-row">
                        {{-- Date badge. --}}
                        <div class="w-28 shrink-0 rounded-xl bg-slate-900 p-4 text-center text-white">
                            <p class="text-3xl font-extrabold leading-none">
                                {{ $pinned->published_at->format('d') }}
                            </p>
                            <p class="mt-1 text-xs font-semibold uppercase tracking-wider text-slate-300">
                                {{ $pinned->published_at->format('M Y') }}
                            </p>
                            <p class="mt-2 rounded-md bg-slate-800 py-1 text-xs font-medium">
                                {{ $pinned->published_at->format('g:i A') }}
                            </p>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="inline-flex items-center rounded-full bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-700">
                                    {{ $pinned->categoryLabel() }}
                                </span>

                                <span class="inline-flex items-center gap-1 text-xs text-slate-500">
                                    <x-icon name="clock" class="h-3.5 w-3.5" />
                                    {{ $pinned->readingMinutes() }} min read
                                </span>
                            </div>

                            <h3 class="mb-2 mt-3 text-xl font-bold text-slate-900">
                                {{ $pinned->title }}
                            </h3>

                            {{-- The notice is shown in full here rather than on a
                                 page of its own: the portal has no per-notice
                                 route, and a reader who has scrolled to a pinned
                                 notice is already reading it. The excerpt is what
                                 they see first, and the button reveals the rest. --}}
                            <div class="text-sm leading-relaxed text-slate-600">
                                <p>{{ $pinned->excerpt() }}</p>
                                <p x-show="open" x-cloak class="mt-2 whitespace-pre-line">
                                    {{ $pinned->body }}
                                </p>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500">
                                    @if (filled($pinned->location))
                                        <span class="inline-flex items-center gap-1.5">
                                            <x-icon name="pin" class="h-4 w-4" />
                                            {{ $pinned->location }}
                                        </span>
                                    @endif

                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon name="user" class="h-4 w-4" />
                                        {{ $pinned->authorLabel() }}
                                    </span>
                                </div>

                                <button type="button" @click="open = ! open"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-800 transition hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600 focus-visible:ring-offset-2">
                                    <span x-text="open ? 'Show Less' : 'Read Full Notice'">Read Full Notice</span>
                                    <x-icon name="arrow-right" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            </section>
        @endif

        {{-- ============================================================
             FEED + SIDEBAR
             ============================================================ --}}
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        Public Notice Feed ({{ $announcements->total() }})
                    </h2>

                    <p class="text-xs text-slate-500">
                        <span aria-hidden="true">📡</span>
                        Verified Official Updates
                    </p>
                </div>

                @if ($announcements->isEmpty())
                    <x-empty-state icon="megaphone" title="No announcements here yet"
                        description="When the barangay publishes a notice it will appear in this feed. Try clearing the search or the category filter." />
                @else
                    <div class="space-y-4">
                        @foreach ($announcements as $announcement)
                            <article id="notice-{{ $announcement->id }}" x-data="{ open: false }"
                                class="flex flex-col gap-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:flex-row">
                                <div class="w-20 shrink-0 rounded-xl bg-slate-100 p-3 text-center font-bold text-slate-800">
                                    <p class="text-lg font-extrabold leading-none">{{ $announcement->published_at->format('d') }}</p>
                                    <p class="mt-1 text-2xs font-semibold uppercase tracking-wider text-slate-500">
                                        {{ $announcement->published_at->format('M Y') }}
                                    </p>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                            {{ $announcement->categoryLabel() }}
                                        </span>

                                        <time datetime="{{ $announcement->published_at->toIso8601String() }}"
                                            class="text-xs text-slate-500">
                                            {{ $announcement->published_at->format('g:i A') }}
                                        </time>
                                    </div>

                                    <h3 class="mb-1 mt-2 text-base font-bold text-slate-900">
                                        {{ $announcement->title }}
                                    </h3>

                                    <div class="text-sm leading-relaxed text-slate-600">
                                        <p>{{ $announcement->excerpt(140) }}</p>
                                        <p x-show="open" x-cloak class="mt-2 whitespace-pre-line">
                                            {{ $announcement->body }}
                                        </p>
                                    </div>

                                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
                                        <span class="inline-flex items-center gap-1.5">
                                            <x-icon name="clock" class="h-3.5 w-3.5" />
                                            {{ $announcement->readingMinutes() }} min read
                                        </span>

                                        <button type="button" @click="open = ! open"
                                            class="inline-flex items-center gap-1 font-semibold text-sky-700 transition hover:text-navy-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600 focus-visible:ring-offset-2">
                                            <span x-text="open ? 'Show Less' : 'Read Details'">Read Details</span>
                                            <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    @if ($announcements->hasPages())
                        <nav aria-label="Announcements pagination" class="mt-8">
                            {{ $announcements->onEachSide(1)->links() }}
                        </nav>
                    @endif
                @endif
            </div>

            {{-- ============================================================
                 SIDEBAR
                 ============================================================ --}}
            <aside class="space-y-6 lg:col-span-1">
                {{-- Hotlines. Numbers come from the settings table, so a number
                     is published once and shown here and in the footer alike. --}}
                <section aria-labelledby="hotlines-heading"
                    class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="mb-4 flex items-center justify-between gap-2">
                        <h2 id="hotlines-heading" class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            Emergency Hotlines
                        </h2>
                        <x-icon name="phone" class="h-4 w-4 text-amber-500" />
                    </div>

                    @if (filled($hotlines))
                        <ul class="space-y-2">
                            @foreach ($hotlines as $hotline)
                                <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2.5">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-slate-800">{{ $hotline['office'] }}</span>
                                        <span class="block text-xs text-slate-500">{{ $hotline['detail'] }}</span>
                                    </span>

                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline['number']) }}"
                                        class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-900 transition hover:bg-amber-200">
                                        {{ $hotline['number'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-slate-500">
                            No hotline has been published yet. Reach the barangay through the
                            <a href="{{ route('contact') }}" class="font-semibold text-sky-700 underline underline-offset-2">contact page</a>.
                        </p>
                    @endif
                </section>

                {{-- Schedules. Static display values - verify them against the
                     barangay's own calendar before publishing. --}}
                <section aria-labelledby="schedules-heading"
                    class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="mb-4 flex items-center justify-between gap-2">
                        <h2 id="schedules-heading" class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            Public Schedules
                        </h2>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-2xs font-semibold text-slate-600">
                            Linasin Zone 1
                        </span>
                    </div>

                    <ul class="space-y-3">
                        @foreach ($schedules as $schedule)
                            <li class="flex gap-2.5">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $dotTones[$schedule['dot']] ?? $dotTones['slate'] }}"
                                    aria-hidden="true"></span>
                                <span>
                                    <span class="block text-sm font-semibold text-slate-800">{{ $schedule['title'] }}</span>
                                    <span class="block text-xs text-slate-500">{{ $schedule['when'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                {{-- The one action a resident of this portal came here to take. --}}
                <section aria-labelledby="documents-heading"
                    class="rounded-2xl bg-slate-900 p-6 text-white">
                    <span class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-amber-400">
                        <x-icon name="document" class="h-5 w-5" />
                    </span>

                    <h2 id="documents-heading" class="mb-2 text-lg font-bold text-white">
                        Need Official Documents?
                    </h2>

                    <p class="mb-4 text-xs leading-relaxed text-slate-300">
                        Skip the long queues. Registered residents of Barangay Linasin can request
                        document clearances directly online.
                    </p>

                    <x-btn :href="auth()->check() ? route('resident.requests.create') : route('register')"
                        variant="primary" size="md" class="w-full justify-center">
                        {{ auth()->check() ? 'Request a Document' : 'Create Resident Account' }}
                    </x-btn>
                </section>
            </aside>
        </div>
    </x-shell>
</x-public-layout>
