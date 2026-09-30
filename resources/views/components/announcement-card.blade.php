@props([
    'announcement',
    'href' => null,
    'excerptLength' => 140,
    'class' => '',
])

@php
    /*
     * Trim the body to a whole word so cards never end mid-word
     * (e.g. "at the Baran…"). Falls back to a hard cut only for very
     * long unbroken strings such as pasted IDs.
     */
    $body = trim(preg_replace('/\s+/', ' ', strip_tags((string) $announcement->body)) ?? '');

    if (mb_strlen($body) <= $excerptLength) {
        $excerpt = $body;
    } else {
        $clipped = mb_substr($body, 0, $excerptLength);
        $lastSpace = mb_strrpos($clipped, ' ');
        $excerpt = rtrim($lastSpace !== false ? mb_substr($clipped, 0, $lastSpace) : $clipped, " ,.;:-").'…';
    }
@endphp

{{--
    One card in the homepage announcement grid.

    The card is a white panel with the photograph bleeding to the top edge, so
    the panel's own rounding and overflow clip it: the image is a plain
    rectangle and the card supplies the shape. The category tag sits above the
    title because that is the only way a reader can tell three notices apart at
    a glance; the date shares that row, pushed to the far end, and is the only
    quiet element in the card.

    The image is decorative next to the title, which already names the notice,
    but it is not aria-hidden: it carries real content, and a screen reader
    reaching it should hear what is actually shown rather than skip it.
--}}
<article {{ $attributes->merge(['class' => trim('group flex h-full flex-col overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm transition-shadow duration-200 hover:shadow-md '.$class)]) }}>
    <div class="h-48 w-full overflow-hidden bg-navy-50">
        <img src="{{ $announcement->imageUrl() }}" alt="{{ $announcement->imageAlt() }}"
            class="h-48 w-full object-cover" loading="lazy" decoding="async"
            width="640" height="384" />
    </div>

    <div class="flex flex-1 flex-col p-5">
        <div class="flex items-center justify-between gap-3">
            <span class="text-2xs font-bold uppercase tracking-[0.18em] text-blue-700">
                {{ $announcement->categoryLabel() }}
            </span>

            <time datetime="{{ $announcement->published_at->toDateString() }}"
                class="shrink-0 text-2xs font-semibold uppercase tracking-wider text-navy-500">
                {{ $announcement->published_at->format('M d, Y') }}
            </time>
        </div>

        <h3 class="mt-2.5 text-base font-bold uppercase leading-snug tracking-tight text-navy-900">
            {{ $announcement->title }}
        </h3>

        <p class="mt-2 flex-1 text-sm leading-relaxed text-navy-600/80">{{ $excerpt }}</p>

        @if ($href)
            <a href="{{ $href }}" class="mt-5 inline-flex items-center gap-1.5 text-sm font-bold text-sky-700 transition group-hover:text-navy-900">
                Read More
                <x-icon name="arrow-right" class="h-4 w-4 transition duration-200 group-hover:translate-x-0.5" />
            </a>
        @endif
    </div>
</article>
