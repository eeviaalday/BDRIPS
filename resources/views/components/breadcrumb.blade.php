@props([
    // ['label' => 'Document Requests', 'route' => 'staff.requests.index'] or
    // ['label' => 'BRGY-2026-4831'] for the current page, which has no route.
    'items' => [],
])

@php
    /*
     * Breadcrumbs, as a nav landmark rather than a row of links. A screen reader
     * user can jump the list; a sighted user reads "you are here" at a glance.
     *
     * The final crumb is the current page and is marked aria-current, so it is
     * announced as such and is not a link - it would point at the page you are
     * already on. Truncated at the start with a leading ellipsis when the trail
     * is long, because a resident's request trail can bury the section name.
     */
    $trail = array_values($items);
    $overflow = count($trail) > 4 ? count($trail) - 3 : 0;
    $visible = $overflow > 0 ? array_slice($trail, -3) : $trail;
@endphp

@if ($trail !== [])
    <nav aria-label="Breadcrumb" {{ $attributes->merge(['class' => 'mb-4']) }}>
        <ol class="flex flex-wrap items-center gap-1.5 text-sm text-navy-500">
            @if ($overflow > 0)
                <li class="flex items-center gap-1.5">
                    <span aria-hidden="true">&hellip;</span>
                    <span class="sr-only">Earlier pages</span>
                </li>
            @endif

            @foreach ($visible as $index => $item)
                @php $isLast = $index === array_key_last($visible); @endphp

                <li class="flex items-center gap-1.5">
                    @if (! $isLast && ! empty($item['route']))
                        <a href="{{ route($item['route']) }}"
                            class="rounded transition hover:text-navy-900 hover:underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400">
                            {{ $item['label'] }}
                        </a>
                        <span class="text-navy-300" aria-hidden="true">/</span>
                    @else
                        {{-- aria-current, not just a bold span: this is what tells
                             assistive tech the trail has arrived. --}}
                        <span class="font-semibold text-navy-900" aria-current="page">{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
