<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The old "My Documents" page.
 *
 * There is no separate documents table: a document in this system IS a request
 * that has reached ready_for_pickup or completed. So this page and the request
 * list were showing two slices of the same rows, and a resident holding a
 * ready-for-pickup certificate had to know which of the two to refresh.
 *
 * The resident side is one "My Documents" section now, so this URL sends them
 * there. It is a redirect rather than a second rendering on purpose: a second
 * copy of the list is a second thing to keep in step, and this one had already
 * drifted - it still carried markup that never belonged on a page.
 *
 * The route is kept, so any link, bookmark or QR code that points at it still
 * lands the resident on their documents.
 */
class ResidentDocumentController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('resident.requests.index');
    }
}
