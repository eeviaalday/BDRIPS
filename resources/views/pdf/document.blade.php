<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Times New Roman', serif;
            color: #1f2937;
            font-size: 12pt;
            margin: 0;
            padding: 40px;
        }
        .header {
            text-align: center;
            border-bottom: 3px double #111;
            padding-bottom: 14px;
        }
        .repub {
            font-size: 10pt;
            letter-spacing: 1px;
            margin: 0;
        }
        .prov {
            font-size: 11pt;
            font-weight: bold;
            margin: 2px 0;
        }
        .barangay {
            font-size: 16pt;
            font-weight: bold;
            margin: 2px 0;
        }
        .title {
            text-align: center;
            font-size: 20pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 34px 0 8px;
            text-decoration: underline;
            text-underline-offset: 4px;
        }
        .preamble {
            text-align: center;
            font-size: 11pt;
            margin: 6px 0 28px;
        }
        .content {
            font-size: 12pt;
            line-height: 1.9;
            text-align: justify;
            margin: 0 24px;
        }
        .content strong {
            text-decoration: underline;
        }
        .purpose-label {
            display: inline-block;
            margin-top: 12px;
            font-weight: bold;
        }
        .signature-area {
            margin-top: 64px;
            position: relative;
        }
        .qr { position: absolute; left: 0; top: 20px; }
        .sig {
            text-align: right;
            margin-right: 14px;
        }
        .sig-line {
            border-bottom: 1px solid #111;
            width: 280px;
        }
        .sig-name { font-weight: bold; margin: 2px 0 0; }
        .sig-role { font-size: 10pt; margin: 0; }
        .reference {
            margin-top: 40px;
            padding-top: 12px;
            border-top: 1px solid #d1d5db;
            font-size: 9pt;
            color: #4b5563;
        }
        .reference table { width: 100%; }
        .reference td { vertical-align: top; font-size: 9pt; }
    </style>
</head>
<body>
    <div class="header">
        <p class="repub">Republic of the Philippines</p>
        <p class="prov">{{ $settings['barangay_address'] }}</p>
        <p class="barangay">Barangay {{ $settings['barangay_name'] }}</p>
        <p class="repub">OFFICE OF THE PUNONG BARANGAY</p>
    </div>

    <div class="title">Official Certificate</div>

    <p class="preamble">This is to certify that the following resident has filed a request with this office:</p>

    <div class="content">
        That <strong>{{ $request->user->name }}</strong>, a bonafide resident of this barangay,
        of legal age, married/single, and the holder of Household Reference Number
        <strong>{{ $request->user->residentProfile?->household_number ?? 'N/A' }}</strong>,
        having verified ID number <strong>{{ $request->user->residentProfile?->national_or_voter_id ?? 'N/A' }}</strong>,
        and currently residing at <strong>{{ $request->user->residentProfile?->address ?? 'N/A' }}</strong>
        under Sector <strong>{{ $request->user->residentProfile?->sector?->sector_name ?? 'N/A' }}</strong>,
        is entitled to receive the following document:

        <p style="text-align:center; margin:22px 0; font-weight:bold; text-decoration:underline; font-size:13pt;">
            {{ $request->documentTypeLabel() }}
        </p>

        <table style="width:100%; border:1px solid #111; border-collapse:collapse;">
            <tr style="border:1px solid #111;">
                <td style="border:1px solid #111; padding:6px 10px; width:35%;"><strong>Reference Number</strong></td>
                <td style="border:1px solid #111; padding:6px 10px; font-family:monospace;">{{ $request->reference_number }}</td>
            </tr>
            <tr style="border:1px solid #111;">
                <td style="border:1px solid #111; padding:6px 10px;"><strong>Date Issued</strong></td>
                <td style="border:1px solid #111; padding:6px 10px;">{{ now()->format('F d, Y') }}</td>
            </tr>
            <tr style="border:1px solid #111;">
                <td style="border:1px solid #111; padding:6px 10px;"><strong>Status</strong></td>
                <td style="border:1px solid #111; padding:6px 10px;">Approved - Ready for Release</td>
            </tr>
        </table>

        <p class="purpose-label">Purpose &mdash; {{ $request->purpose }}</p>
    </div>

    <div class="signature-area">
        <img src="{{ $qrDataUri }}" class="qr" style="width:90px; height:90px;" alt="QR">
        <div class="sig">
            <div class="sig-line"></div>
            <p class="sig-name">{{ $settings['punong_barangay'] }}</p>
            <p class="sig-role">Punong Barangay &middot; {{ $settings['barangay_name'] }}</p>
        </div>
    </div>

    <div class="reference">
        <table>
            <tr>
                <td style="width:50%;">
                    Document Reference: <strong>{{ $request->reference_number }}</strong><br>
                    Issued by: {{ $settings['secretary_name'] }} (Barangay Secretary)
                </td>
                <td style="width:50%;">
                    Verify this document by scanning the QR code or visiting the barangay hall.<br>
                    Generated on {{ now()->format('F d, Y h:i A') }}
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding-top:8px; font-style:italic;">
                    This certificate is issued subject to the Barangay Clearance Ordinance and may be revoked upon
                    misrepresentation of the information provided.
                </td>
            </tr>
        </table>
    </div>
</body>
</html>