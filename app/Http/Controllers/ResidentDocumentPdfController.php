<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Services\DocumentPdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A resident reading their OWN certificate.
 *
 * The existing staff PDF route is inside the role:admin,secretary group, so
 * linking a resident's "View PDF" at it produces a 403 for the one person
 * allowed to see the document. This is the resident's own half of the same
 * thing, and it is deliberately narrow:
 *
 *   - it is only ever reachable for a request whose user_id IS the signed-in
 *     resident, so it is not a reference-number oracle: another resident's row
 *     is refused exactly as an id that does not exist is.
 *   - it only serves a request that has actually been released. A submitted or
 *     rejected request has no certificate, and there is nothing to hand over
 *     before the Secretary has approved it.
 *
 * The rendering itself is DocumentPdfService, the same service the staff route
 * uses, so there is one certificate layout rather than two that drift.
 */
class ResidentDocumentPdfController extends Controller
{
    public function __invoke(Request $request, DocumentRequest $documentRequest, DocumentPdfService $pdfService): Response
    {
        abort_if($documentRequest->user_id !== $request->user()->id, 403);

        abort_if(
            ! in_array($documentRequest->status, [
                DocumentRequest::STATUS_READY,
                DocumentRequest::STATUS_COMPLETED,
            ], true),
            404,
            'This certificate has not been released yet.',
        );

        $documentRequest->load('user.residentProfile');

        $content = $pdfService->render($documentRequest);

        $response = new StreamedResponse(function () use ($content): void {
            echo $content;
        });

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'inline; filename="'.$pdfService->filename($documentRequest).'"');

        return $response;
    }
}
