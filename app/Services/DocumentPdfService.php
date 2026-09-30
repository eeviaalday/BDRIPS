<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class DocumentPdfService
{
    public function render(DocumentRequest $documentRequest): string
    {
        $documentRequest->load('user.residentProfile.sector');

        return Pdf::loadView('pdf.document', [
            'request' => $documentRequest,
            'settings' => $this->settingsMap(),
            'qrDataUri' => $this->qrCode($documentRequest->reference_number),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'serif',
                'isRemoteEnabled' => false,
            ])
            ->output();
    }

    public function filename(DocumentRequest $documentRequest): string
    {
        return str_replace(' ', '-', $documentRequest->documentTypeLabel())
            .'-'.$documentRequest->reference_number.'.pdf';
    }

    private function qrCode(string $content): string
    {
        $options = new QROptions([
            'outputType' => QRGdImagePNG::class,
            'outputBase64' => true,
            'scale' => 8,
            'eccLevel' => 'L',
        ]);

        return (new QRCode($options))->render($content);
    }

    private function settingsMap(): array
    {
        return [
            'barangay_name' => Setting::get('barangay_name', 'Barangay Linasin, Zone 1'),
            'barangay_address' => Setting::get('barangay_address', 'Zone 1, Bangued, Abra'),
            'barangay_logo' => Setting::get('barangay_logo', ''),
            'punong_barangay' => Setting::get('punong_barangay', 'Hon. [Punong Barangay]'),
            'secretary_name' => Setting::get('secretary_name', 'Brgy. Secretary'),
            'clearance_fee' => Setting::get('clearance_fee', '100.00'),
            'indigency_fee' => Setting::get('indigency_fee', '0.00'),
            'jobseeker_fee' => Setting::get('jobseeker_fee', '50.00'),
            'residency_fee' => Setting::get('residency_fee', '100.00'),
        ];
    }
}
