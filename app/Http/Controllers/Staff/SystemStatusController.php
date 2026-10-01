<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Support\SystemHealth;
use Illuminate\View\View;

/**
 * System status: the things that tell an Admin the system is unhealthy before a
 * resident does.
 *
 * Every check is allowed to fail without failing the page. A status screen that
 * 500s when the disk is full is useless precisely when it is needed, so each
 * check returns a pass or a message and the page always renders.
 *
 * The checks themselves live in SystemHealth, because the staff dashboard shows
 * an Admin the same verdict and two copies of "is the system healthy" would
 * eventually disagree.
 *
 * Nothing here returns a credential. The database check reports the server
 * version and the schema name, never the username or password, and the
 * environment check reports whether a key is set rather than what it holds.
 */
class SystemStatusController extends Controller
{
    public function __construct(private readonly SystemHealth $health) {}

    public function index(): View
    {
        return view('staff.status.index', [
            'database' => $this->health->database(),
            'storage' => $this->health->storage(),
            'migrations' => $this->health->migrations(),
            'environment' => $this->health->environment(),
            'counts' => $this->health->counts(),
            // The verdict, from the same SystemHealth the staff dashboard's Admin
            // strip uses. Both pages read summary() from the one object, so the
            // banner here and the strip there cannot report different states.
            'health' => $this->health,
            // Every check above is a live read taken while rendering this page, so
            // the time the page was served is the time the system was last checked -
            // and the Re-check button is a plain reload.
            //
            // In Asia/Manila, to the second. The seconds are the point: a Re-check
            // clicked twice inside one minute used to render an identical
            // timestamp, so there was no way to tell a fresh reading from the
            // page already on screen, and "Re-check" was a control that could not
            // show that it had done anything.
            'checkedAt' => now('Asia/Manila'),
        ]);
    }
}
