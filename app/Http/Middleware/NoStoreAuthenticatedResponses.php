<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stop a signed-in page from surviving in the browser's back history.
 *
 * Logging out is only half of it. Without these headers the browser is free to
 * keep the rendered page and its disk cache, and a resident who logs out on a
 * shared computer and presses Back is shown their own account again - full
 * name, household number, document references - without the server ever being
 * asked whether the session is still valid.
 *
 * The three headers are the agreed trio rather than a Cache-Control preference
 * on its own, because no-store alone is still advisory to some intermediaries
 * and Pragma/Expires are what older proxies and some browsers actually read.
 *
 * This is a response-header fix only. It is not, and cannot be, the thing that
 * enforces anything: the route's own auth and role middleware is what refuses
 * the request. These headers remove the copy the browser was holding.
 */
class NoStoreAuthenticatedResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
