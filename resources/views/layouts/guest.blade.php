{{--
    Guest layout: brand/context panel (desktop) + form card.

    Optional :background renders a full-bleed photograph behind both panels with
    a translucent colour wash over it, and makes the two panels transparent so
    the photograph shows through. The brand panel's own content can be replaced
    through the "brand" slot.

    Both are opt-in: the password pages pass neither, and their output is
    unchanged. (/login is not here at all - it is a standalone document that
    carries its own styles.)
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'BDRPIS') }} &middot; BDRPIS</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="min-h-screen bg-navy font-sans text-navy-800 antialiased">
        @if ($full)
            {{-- ============================================================
                 FULL-SCREEN MERGED CARD (5/7, NO GAP)
                 ============================================================
                 One card edge to edge: a translucent blue branding column and a
                 white form column, no margin, no padding between them, no
                 rounded corner and no outer shadow. Shared by /login and
                 /register so the two sign-in screens are the same frame.

                 There is nothing here to fake the seam: the two columns are
                 adjacent grid cells, so there is no margin to remove and no
                 rounded corner to line up. The hairline between them is a
                 border on the blue side only, at 10% white, which reads as a
                 fold in the card rather than as a divider.

                 The white column does not carry a border of its own - two
                 adjacent borders render as a 2px rule on a shared edge.

                 The card is min-h-screen rather than h-screen, so the columns
                 are a full viewport tall. It grows past that when the content
                 needs it, which is what lets the left column's blue run the
                 full length of a scrolling page instead of stopping at the fold
                 while the right column carries on.
            --}}
            <div @class([
                'relative min-h-screen w-full bg-cover bg-center',
                'flex overflow-hidden' => $clip,
                'block overflow-x-hidden' => ! $clip,
            ])
                @if ($background)
                    style="background-image: url('{{ asset($background) }}')"
                @else
                    style="background-image: none"
                @endif>
                <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-slate-900/30"></div>

                <div class="relative z-10 grid min-h-screen w-full grid-cols-1 overflow-hidden md:grid-cols-12">
                    {{-- Branding column --}}
                    <div class="flex h-full flex-col items-center justify-center border-r border-white/10 bg-blue-950/70 p-8 text-center text-white backdrop-blur-md md:col-span-5 md:p-12">
                        {{ $brand ?? '' }}
                    </div>

                    {{-- Form column. No margin, no radius: it meets the blue
                         column flush. --}}
                    <div class="flex h-full w-full flex-col justify-center bg-white p-8 md:col-span-7 md:p-16">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        @else
            {{-- Split layout: brand/context panel (desktop) + centred form card. --}}
            <div class="relative isolate flex min-h-screen flex-col lg:flex-row">
            @if ($background)
                {{-- Full-bleed photograph plus a translucent wash. The wash is kept
                     deliberately light so the landscape stays legible; the brand
                     panel adds its own local scrim behind the white text. --}}
                <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-20 overflow-hidden">
                    <img src="{{ asset($background) }}" alt=""
                        class="h-full w-full object-cover object-center" />
                </div>
                <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 {{ $overlay }}"></div>
            @endif

            {{-- Brand panel --}}
            <header @class([
                'relative isolate hidden overflow-hidden lg:flex lg:w-[46%] lg:flex-col lg:p-12 xl:w-[48%]',
                'items-center justify-center text-center' => $background,
                'bg-navy-900 lg:justify-between' => ! $background,
            ])>
                @unless ($background)
                    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
                        <div class="absolute inset-0 bg-[radial-gradient(130%_110%_at_10%_0%,rgba(59,130,246,0.30),transparent_58%)]"></div>
                        <div class="absolute -right-24 top-1/4 h-72 w-72 rounded-full border-[28px] border-gold/15"></div>
                        <div class="absolute -bottom-20 -left-16 h-64 w-64 rounded-full bg-sky-500/10"></div>
                        <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-gold/70 to-transparent"></div>
                    </div>
                @endunless

                @if (isset($brand) && ! $brand->isEmpty())
                    {{-- Local scrim keeps the white branding legible over the wash. --}}
                    @if ($background)
                        <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 bg-navy-900/35"></div>
                    @endif

                    {{ $brand }}
                @else
                    <div class="flex items-center gap-4">
                        <img src="{{ asset('images/Zone 1_logo.png') }}" alt="" width="56" height="56"
                            class="h-14 w-14 rounded-full object-cover ring-2 ring-gold/60" />
                        <div>
                            <p class="text-lg font-extrabold text-white">Barangay Linasin</p>
                            <p class="text-xs font-medium tracking-wide text-navy-300">Zone 1, Bangued, Abra</p>
                        </div>
                    </div>

                    <div class="max-w-md">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-gold">Barangay Directory &amp; Document Request and Public Information System</p>
                        <h2 class="mt-4 text-3xl font-extrabold leading-tight text-white xl:text-4xl">
                            One portal for barangay documents, directories, and relief transparency.
                        </h2>
                        <ul class="mt-8 space-y-3.5 text-sm text-navy-200">
                            @foreach ([
                                ['icon' => 'document', 'text' => 'Request supported barangay documents online'],
                                ['icon' => 'search', 'text' => 'Track your request using its reference number'],
                                ['icon' => 'shield', 'text' => 'Your personal data is protected under R.A. 10173'],
                            ] as $point)
                                <li class="flex items-start gap-3">
                                    <span class="mt-px inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/10 text-gold">
                                        <x-icon :name="$point['icon']" class="h-4 w-4" />
                                    </span>
                                    <span class="leading-relaxed">{{ $point['text'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <p class="text-xs text-navy-400">Zone 1, Bangued, Abra &middot; Philippines</p>
                @endif
            </header>

            {{-- Form panel --}}
            <main @class([
                'flex flex-1 flex-col',
                'bg-transparent' => $background,
                'bg-surface' => ! $background,
            ])>
                {{-- Mobile brand bar --}}
                <div class="flex items-center justify-center gap-3 px-4 py-5 lg:hidden {{ $background ? 'bg-navy-900/60' : 'bg-navy' }}">
                    <img src="{{ asset('images/Zone 1_logo.png') }}" alt="" width="44" height="44"
                        class="h-11 w-11 rounded-full object-cover ring-2 ring-gold/60" />
                    <div>
                        <p class="text-sm font-extrabold text-white">Barangay Linasin</p>
                        <p class="text-2xs tracking-wide text-navy-300">Zone 1, Bangued, Abra</p>
                    </div>
                </div>

                <div class="flex flex-1 items-center justify-center px-4 py-10 sm:px-6 lg:px-12">
                    <div class="w-full {{ $maxWidth ?? 'sm:max-w-md' }}">
                        <div class="rounded-2xl border border-navy-100 bg-white p-6 shadow-card-hover sm:p-8">
                            {{ $slot }}
                        </div>
                    </div>
                </div>
            </main>
            </div>
        @endif
    </body>
</html>
