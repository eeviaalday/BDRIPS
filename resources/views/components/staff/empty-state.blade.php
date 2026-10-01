@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
    // Optional way out. A filter that returns nothing is a dead end unless it offers
    // the way back, so the list screens pass the unfiltered URL here.
    'actionLabel' => null,
    'actionHref' => null,
    // Matches the heading level of the page it sits in. A skipped level is worse for
    // a screen reader than a wrong-looking heading size.
    'level' => 'p',
    'class' => '',
])

{{--
    The resident empty state: dashed border, pale fill, centred.

    Carries one line of explanation and optionally one way out. Both are deliberate.
    "Nothing here" alone leaves a reader guessing whether they have done something
    wrong, whether the page is broken, or whether there is genuinely nothing yet -
    and a filtered list that returns nothing without saying so is the worst of the
    three, because there is something, just not what was asked for.
--}}
<div {{ $attributes->merge(['class' => trim('flex flex-col items-center rounded-xl border border-dashed border-navy-200 bg-navy-50/40 px-6 py-12 text-center '.$class)]) }}>
    <span aria-hidden="true"
        class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-white text-navy-400 shadow-card ring-1 ring-navy-100">
        <x-icon :name="$icon" class="h-7 w-7" />
    </span>

    <{{ $level }} class="mt-5 text-base font-bold text-navy-900">{{ $title }}</{{ $level }}>

    @if ($description)
        <p class="mt-2 max-w-md text-sm leading-relaxed text-navy-600/80">{{ $description }}</p>
    @endif

    @if ($actionLabel && $actionHref)
        <div class="mt-5">
            <x-btn :href="$actionHref" variant="outline" size="sm">{{ $actionLabel }}</x-btn>
        </div>
    @endif
</div>