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
    public function __construct(public readonly ?User $user = null) {}

    public function render(): View
    {
        return view('layouts.app');
    }
}
