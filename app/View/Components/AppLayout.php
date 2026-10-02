<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The staff shell: the side navigation plus the top bar.
 *
 * A class component rather than a plain layout include, because it is what
 * gives a staff page the current user's name and role without every page having
 * to reach for auth() itself. The resident's top bar is a separate component,
 * not a variant of this one, because the two designs share a colour scheme and
 * nothing else.
 */
class AppLayout extends Component
{
    /**
     * The page title, for <head> only.
     *
     * It has to arrive through the constructor rather than being picked up from
     * the tag's attributes. A class component does NOT auto-populate public
     * properties from attributes, so a bare `public ?string $title` would sit at
     * null forever and every staff page would render the same document title. This
     * is the same trap GuestLayout documents, and the same fix.
     *
     * The page itself owns its <h1>. layouts/app renders this into <head> and
     * deliberately does not print a heading of its own, so a page that declares a
     * title in two places cannot end up with two <h1>s.
     */
    public function __construct(
        public readonly ?User $user = null,
        public ?string $title = null,
    ) {}

    public function render(): View
    {
        return view('layouts.app');
    }
}
