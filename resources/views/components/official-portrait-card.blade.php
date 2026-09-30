@props([
    'name',
    'position',
    'photo' => null,
    'class' => '',
])

@php
    /*
     * Official card for the carousels: an avatar panel above the name, rather
     * than the initials disc the directory listing uses.
     *
     * "photo" is a path under public/ (see the officials block in
     * config/bdrpis.php) and is resolved here with asset(). It is a drawn
     * placeholder avatar, not this person - replace it with the official's own
     * photograph before the page is published. With no photo at all the panel
     * falls back to the generated initials, so a card is never left empty and a
     * missing file degrades to something readable.
     *
     * Each avatar is shipped as an AVIF with a JPEG of the same picture. The
     * AVIF goes in a <source> and the JPEG in the <img>, because nothing older
     * than Safari 16.4 renders AVIF and would otherwise show an empty panel.
     *
     * The name is adjacent to the picture and the picture carries nothing the
     * text does not, so it is marked decorative: a screen reader should hear
     * the name once, not "portrait of a person, Francis Carla A. Acosta".
     */
    $initials = \Illuminate\Support\Str::of($name)
        ->replace(['Hon.', 'Hon', 'Sir', 'Ms', 'Mrs'], ' ')
        ->squish()
        ->explode(' ')
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->join('');

    // Guarded against a full URL being pasted into config: asset() would turn
    // one into "/https://...", which renders as a broken image.
    $source = str_starts_with((string) $photo, 'http') ? $photo : asset($photo);
    $fallback = preg_replace('/\.avif$/', '.jpg', (string) $photo);
    $fallback = str_starts_with((string) $fallback, 'http') ? $fallback : asset($fallback);
@endphp

<article {{ $attributes->merge(['class' => trim('w-full overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-card transition duration-200 hover:shadow-card-hover '.$class)]) }}>
    <div class="h-48 w-full overflow-hidden rounded-t-xl bg-slate-100 md:h-56">
        @if (filled($photo))
            <picture>
                <source srcset="{{ $source }}" type="image/avif" />
                <img src="{{ $fallback }}" alt="" class="h-full w-full object-cover object-center"
                    loading="lazy" decoding="async" width="640" height="740" />
            </picture>
        @else
            {{-- No avatar: the same neutral disc the directory listing uses, so
                 a card without a photograph still reads as an official. --}}
            <span class="flex h-full w-full items-center justify-center bg-navy-50 text-3xl font-extrabold text-navy-300">
                {{ $initials !== '' ? $initials : '?' }}
            </span>
        @endif
    </div>

    {{-- Pulled up under the portrait so the two read as one card. A positive
         top-4 offset would open a gap between the panels instead, so the
         overlap is what carries the join. --}}
    <div class="relative -mt-4 rounded-b-xl border-x border-b border-slate-200/80 bg-white px-5 pb-5 pt-6 text-center">
        <h3 class="text-lg font-bold leading-snug text-slate-900">{{ $name }}</h3>
        <p class="mt-1 text-sm font-medium text-slate-500">{{ $position }}</p>
    </div>
</article>
