@props([
    // The Font Awesome solid glyph, without the fa- prefix: icon="eye" draws
    // fa-solid fa-eye. Prefixing it here means a caller cannot end up with
    // "fa-eye fa-eye" or with a brand glyph in a staff table.
    'icon',

    // The action's name. This is the accessible name, the tooltip and the
    // screen-reader text, and it is required: an icon-only control with no name is
    // announced as "button" and nothing else.
    'label',

    // EITHER an href, which renders a link, OR nothing, which renders a button
    // meant to sit inside a form. Exactly one of the two, and which one it is
    // decides the element — a link that submits is a link that surprises, and a
    // button that navigates is a button that reloads the page and loses the POST.
    'href' => null,

    // Button-only. `type` is passed straight through, so submit is the explicit
    // choice at every call site rather than the browser's default.
    'type' => 'submit',
    'name' => null,
    'value' => null,

    // 'default' or 'danger'. Danger is for the one action in a row that cannot be
    // undone — Delete, Close — and it is red so the eye finds it before it clicks.
    'variant' => 'default',

    // A disabled control plus the reason it is disabled. Both halves are required
    // together: a bare `disabled` is a dead control with no explanation, and a
    // reason with no disabled attribute is a tooltip on something that still works.
    'disabled' => false,
    'disabledReason' => null,

    // Extra classes for the control, and for the glyph inside it.
    'class' => '',
    'iconClass' => '',

    // 'md' is the 36px staff default. 'lg' is 44px, for the Kagawad checklist,
    // which is used on a phone, one-handed, in a room where the phone is being
    // held rather than looked at.
    'size' => 'md',
])

@php
    /*
     * One action in a table row, as an icon with a name.
     *
     * WHY A COMPONENT AND NOT THE MARKUP AT EACH CALL SITE. An actions column is
     * the one place in a staff table where a control is icon-only, and every one
     * of its requirements is invisible in a screenshot: the aria-label, the
     * sr-only text, the tooltip, the focus ring, and the 36px hit area. Written
     * inline at nine call sites, one of them gets the hit area wrong and nobody
     * notices until someone tries to tap it.
     *
     * THE NAME IS CARRIED THREE WAYS, ON PURPOSE.
     *
     *   aria-label  what a screen reader announces for the control itself;
     *   sr-only     the visible-to-assistive-technology text in the button, so a
     *               control whose glyph is the only thing on screen is still
     *               described if the aria-label is ever dropped;
     *   title       the pointer tooltip, which is sighted-reader-only and is
     *               never the accessible name on its own.
     *
     * Any one of them alone is a defect. aria-label alone is invisible to anyone
     * reading the page, so sighted users get an unlabelled icon; title alone is
     * unreliable for assistive technology and vanishes on touch, where there is
     * no pointer to hover with.
     *
     * THE GLYPH IS aria-hidden. "eye" read aloud before "View announcement" is
     * noise, and the control already says what it does.
     *
     * THE HIT AREA. A 20px icon is not a target. The control is at least 36x36
     * (44x44 at size="lg") and the glyph is centred inside it, so the padding is
     * part of the button rather than a margin that a neighbouring cell can eat.
     */
    $isLink = filled($href);
    $isDanger = $variant === 'danger';

    /*
     * The enabled control. h-9 is exactly 36px and h-11 is 44px, which is why the
     * sizes are spelled out rather than approximated with padding on a smaller box.
     */
    $control = 'inline-flex items-center justify-center rounded-lg transition'
        .' '.($size === 'lg' ? 'h-11 w-11' : 'h-9 w-9')
        .' '.($isDanger
            ? 'text-red-600 hover:bg-red-50 hover:text-red-700'
            : 'text-navy-600 hover:bg-navy-50 hover:text-navy-800')
        .' focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-navy-400';

    /*
     * A disabled control keeps its place in the row and explains itself, so it is
     * rendered as a <span> rather than a disabled button.
     *
     * A disabled <button> is removed from the tab order and, in most browsers,
     * cannot be focused at all — which means the tooltip below is unreachable and
     * the reason is never read. A span with tabindex="0" can be focused, so a
     * keyboard user can reach the control, hear why it is unavailable, and move on.
     * aria-disabled is used rather than disabled for the same reason: it says the
     * control is unavailable while leaving it in the accessibility tree.
     */
    $disabledControl = 'inline-flex items-center justify-center rounded-lg transition cursor-not-allowed text-slate-400'
        .' '.($size === 'lg' ? 'h-11 w-11' : 'h-9 w-9')
        .' focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-slate-300';

    $accessibleName = $label.($disabled && $disabledReason ? ' — unavailable: '.$disabledReason : '');
@endphp

@if ($disabled)
    <span class="inline-flex items-center gap-1.5"
        title="{{ $disabledReason ?? $label }}"
        aria-disabled="true"
        tabindex="0">
        <span {{ $attributes->merge(['class' => $disabledControl.' '.$class]) }}
            aria-label="{{ $accessibleName }}">
            <i class="fa-solid fa-{{ $icon }} {{ $iconClass }}" aria-hidden="true"></i>
            <span class="sr-only">{{ $accessibleName }}</span>
        </span>

        {{-- The reason in text as well as in the title attribute, so it is not
             only available to someone who can hover, and not only on a device with
             a pointer. --}}
        @if ($disabledReason)
            <span class="sr-only">{{ $disabledReason }}</span>
        @endif
    </span>
@elseif ($isLink)
    <a href="{{ $href }}"
        title="{{ $label }}"
        aria-label="{{ $label }}"
        {{ $attributes->merge(['class' => $control.' '.$class]) }}>
        <i class="fa-solid fa-{{ $icon }} {{ $iconClass }}" aria-hidden="true"></i>
        <span class="sr-only">{{ $label }}</span>
    </a>
@else
    <button type="{{ $type }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($value !== null) value="{{ $value }}" @endif
        title="{{ $label }}"
        aria-label="{{ $label }}"
        {{ $attributes->merge(['class' => $control.' '.$class]) }}>
        <i class="fa-solid fa-{{ $icon }} {{ $iconClass }}" aria-hidden="true"></i>
        <span class="sr-only">{{ $label }}</span>
    </button>
@endif
