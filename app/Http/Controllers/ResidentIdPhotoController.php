<?php

namespace App\Http\Controllers;

use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResidentIdPhotoController extends Controller
{
    public function show(Request $request, ResidentProfile $residentProfile): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $isStaff = in_array($user->role, [User::ROLE_ADMIN, User::ROLE_SECRETARY], true);

        if (! $isStaff) {
            abort(403, 'You do not have permission to view this ID photo.');
        }

        $photoPath = $residentProfile->id_photo_path;

        if (empty($photoPath)) {
            abort(404, 'ID photo not found.');
        }

        if (! Storage::exists($photoPath)) {
            abort(404, 'ID photo not found.');
        }

        $mimeType = Storage::mimeType($photoPath);
        $fileSize = Storage::size($photoPath);

        $response = new StreamedResponse(function () use ($photoPath): void {
            echo Storage::get($photoPath);
        });

        $response->headers->set('Content-Type', $mimeType ?? 'application/octet-stream');
        $response->headers->set('Content-Length', (string) $fileSize);
        $response->headers->set('Content-Disposition', 'inline; filename="resident-id-'.$residentProfile->id.'.jpg"');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
