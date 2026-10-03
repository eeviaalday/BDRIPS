<?php

namespace App\Mail;

use App\Models\DocumentRequest;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentCopyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly DocumentRequest $documentRequest,
        private readonly string $pdfContent,
        private readonly string $filename
    ) {}

    public function envelope(): Envelope
    {
        $barangay = Setting::text('barangay_name');

        return new Envelope(
            subject: sprintf('Document Copy - %s', $this->documentRequest->reference_number),
            from: new Address(
                config('mail.from.address') ?: 'no-reply@localhost',
                $barangay !== '' ? $barangay : config('mail.from.name')
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.document-copy-mail',
            with: [
                'referenceNumber' => $this->documentRequest->reference_number,
                'barangay' => Setting::text('barangay_name'),
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->filename)
                ->withMime('application/pdf'),
        ];
    }
}
