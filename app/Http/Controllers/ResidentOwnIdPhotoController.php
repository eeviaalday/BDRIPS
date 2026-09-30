<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A resident looking at the ID document THEY submitted.
 *
 * The staff route for this same file is inside role:admin,secretary, so the
 * verification page used to point the resident at a URL that answered 403 for
 * the one person entitled to see it. This answers the same file for the owner
 * and for nobody else: the profile is resolved by its own id and then compared
 * to the signed-in user, so a resident who walks the id up and down the URL is
 * refused exactly as they would be by the staff route.
 *
 * It is a separate class rather than a second branch in ResidentIdPhotoController
 * because the two have genuinely different questions to ask - "is this staff?"
 * against "is this yours?" - and merging them is how one of the two ends up
 * skipping its check.
 *
 * There is no route parameter on purpose. The profile comes from the session,
 * so the URL cannot be edited to ask for somebody else's document; there is no
 * id in it to walk in the first place.
 */
class ResidentOwnIdPhotoController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $residentProfile = $request->user()->residentProfile;

        abort_if($residentProfile === null, 404, 'No resident record on file.');

        $path = $residentProfile->id_photo_path;

        abort_if(blank($path) || ! Storage::exists($path), 404, 'No ID document on file.');

        $response = new StreamedResponse(function () use ($path): void {
            echo Storage::get($path);
        });

        $response->headers->set('Content-Type', Storage::mimeType($path) ?? 'application/octet-stream');
        $response->headers->set('Content-Length', (string) Storage::size($path));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
