<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\User;
use App\Services\DocumentPdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentPdfController extends Controller
{
    public function __invoke(Request $request, DocumentRequest $documentRequest, DocumentPdfService $pdfService): Response
    {
        abort_if(! in_array($request->user()->role, [User::ROLE_ADMIN, User::ROLE_SECRETARY], true), 403);

        $documentRequest->load('user.residentProfile');

        if ($documentRequest->status === DocumentRequest::STATUS_SUBMITTED) {
            return back()->with('error', 'This request has not been approved yet.');
        }

        $content = $pdfService->render($documentRequest);

        $response = new StreamedResponse(function () use ($content): void {
            echo $content;
        });

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'inline; filename="'.$pdfService->filename($documentRequest).'"');

        return $response;
    }
}
