<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    /**
     * Guest layout: brand/context panel (desktop) + form card.
     *
     * Attributes arrive through the constructor rather than through plain public
     * properties. A class component does NOT auto-populate public properties
     * from the tag's attributes, so a bare `public ?string $maxWidth` silently
     * stayed null and every page fell back to the default width. Constructor
     * injection is what actually binds `:title`, `:maxWidth`, `:background`,
     * `:overlay`, `:full` and `:clip`.
     *
     * @param  string|null  $maxWidth  Card width class for the split layout's
     *                                 card (the password pages). Defaults to a
     *                                 single-column card (sm:max-w-md).
     * @param  string|null  $background  Optional full-bleed photograph path,
     *                                   relative to public/. When set, the image
     *                                   renders behind both panels under a
     *                                   translucent wash and both panels turn
     *                                   transparent.
     * @param  string  $overlay  Translucent colour wash over the photograph. Kept
     *                           light by default so the photograph stays legible.
     * @param  bool  $full  The full-screen merged card: a translucent blue
     *                      branding column flush against a white form column,
     *                      edge to edge with no margin and no rounded corner.
     *                      Used by /register. In this mode :clip decides what
     *                      happens when the form is taller than the viewport -
     *                      it hides the page's scrollbars, which is only safe
     *                      for a form that fits, since a clipped form has
     *                      fields the resident cannot scroll to.
     */
    public function __construct(
        public ?string $title = null,
        public ?string $maxWidth = null,
        public ?string $background = null,
        public string $overlay = 'bg-blue-900/40',
        public bool $full = false,
        public bool $clip = true,
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.guest');
    }
}
