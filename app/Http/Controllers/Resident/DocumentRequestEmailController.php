<?php

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\Controller;
use App\Mail\DocumentCopyMail;
use App\Models\DocumentDelivery;
use App\Models\DocumentRequest;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\DigitalCopyPassword;
use App\Services\DigitalCopyRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class DocumentRequestEmailController extends Controller
{
    public function __invoke(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        if (! $documentRequest->canBeDownloaded()) {
            return back()->with('error', sprintf(
                'Request %s is %s, so there is no digital copy to email yet.',
                $documentRequest->reference_number,
                $documentRequest->statusLabel()
            ));
        }

        $user = $request->user();
        if (! $user || ! $user->isResident()) {
            abort(403);
        }

        if ($documentRequest->user_id !== $user->id) {
            abort(404);
        }

        $password = DigitalCopyPassword::for($documentRequest->user);
        if ($password === null) {
            return back()->with('error', sprintf(
                'A password-protected copy of %s cannot be emailed because your birthdate or surname is not on file. Please ask at the Barangay Hall.',
                $documentRequest->reference_number
            ));
        }

        $keyPerRequest = sprintf('document_email:request:%d:user:%d:%s', $documentRequest->id, $user->id, now()->format('Y-m-d'));
        if (Cache::get($keyPerRequest, 0) >= 3 || RateLimiter::tooManyAttempts('document-email-request:'.$documentRequest->id.':'.$user->id, 3)) {
            abort(429, 'Too many email requests for this document today. Please try again later.');
        }

        $keyPerUser = sprintf('document_email:user:%d:%s', $user->id, now()->format('Y-m-d'));
        if (Cache::get($keyPerUser, 0) >= 10 || RateLimiter::tooManyAttempts('document-email-user:'.$user->id, 10)) {
            abort(429, 'Too many email requests today. Please try again later.');
        }

        $bytes = app(DigitalCopyRenderer::class)->render($documentRequest, $password);
        $filename = $this->filenameFor($documentRequest);

        try {
            Mail::to($documentRequest->user->email)->send(
                new DocumentCopyMail($documentRequest, $bytes, $filename)
            );
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send email. Please try again later.');
        }

        Cache::increment($keyPerRequest);
        Cache::increment($keyPerUser);
        RateLimiter::hit('document-email-request:'.$documentRequest->id.':'.$user->id);
        RateLimiter::hit('document-email-user:'.$user->id);

        DocumentDelivery::create([
            'document_request_id' => $documentRequest->getKey(),
            'user_id' => $user->getKey(),
            'channel' => 'email',
        ]);

        $logger = app(AuditLogger::class);
        $logger->log(
            actor: $user,
            action: 'request.pdf_emailed',
            subject: $documentRequest,
            description: sprintf('Digital copy of %s emailed to the resident', $documentRequest->reference_number),
            context: ['channel' => 'email'],
        );

        if (Setting::flag('digital_copy_counts_as_claimed')) {
            if ($documentRequest->canBeClaimed() && $documentRequest->status === DocumentRequest::STATUS_READY) {
                $documentRequest->update([
                    'status' => DocumentRequest::STATUS_CLAIMED,
                    'claimed_at' => now(),
                ]);
                $logger->log(
                    actor: $user,
                    action: 'request.claimed',
                    subject: $documentRequest,
                    description: sprintf('Request %s marked collected by email', $documentRequest->reference_number),
                    context: ['channel' => 'email'],
                );
            }
        }

        return back()->with('success', sprintf('A copy of %s has been emailed to your registered email address.', $documentRequest->reference_number));
    }

    private function filenameFor(DocumentRequest $documentRequest): string
    {
        return preg_replace(
            '/[^A-Za-z0-9._-]/',
            '-',
            sprintf('digital-copy-%s.pdf', $documentRequest->reference_number)
        ) ?? 'digital-copy.pdf';
    }
}
