<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\Setting;
use Com\Tecnick\Pdf\Encrypt\Encrypt;
use Com\Tecnick\Pdf\Tcpdf;
use RuntimeException;

/**
 * Builds the encrypted PDF a resident downloads in place of the paper document.
 *
 * WHAT IS PRODUCED
 *
 * One A4 page: the barangay's name, address and contact line across the top
 * beside the logo, the document's title and reference number, a body written per
 * document type, the resident's details, the stated purpose, a signature block
 * for the Punong Barangay, and the office's configured digital-copy notice in the
 * footer.
 *
 * WHY THE PAGE IS BUILT AS HTML
 *
 * tc-lib-pdf renders through its HTML engine rather than a stream of positioned
 * text calls, and this is written as one HTML block per region of the page -
 * letterhead and body in one cell anchored at the top, footer in a second cell
 * anchored near the bottom. Two consequences worth recording.
 *
 * The footer lands in the same place on every copy whatever the body above it did
 * or did not fill, because its position is not a function of what came before it.
 * A footer positioned relative to the flow would drift on a request with a long
 * stated purpose, and a certificate whose footer moved is a certificate nobody
 * trusts.
 *
 * And alignment is done with tables of fixed millimetre widths rather than with
 * text-align. The engine maps text-align onto its own alignment attribute, but
 * only for the element that carries it, so a centred paragraph followed by a
 * <br/> silently reverts to the left - which is exactly how a title reads as
 * centred above a reference number that has quietly slid to the margin. A table
 * cell with a fixed width is unambiguous, and it is how the signature block stays
 * on the right half of the page while the body flows to the full width.
 *
 * WHY THE FONTS ARE COMMITTED TO THE REPOSITORY
 *
 * tc-lib-pdf does not ship font metrics. It reads `helvetica.json` and its siblings
 * from a folder the maintainer generates from Adobe's AFM files with a make target,
 * and a fresh `composer install` on XAMPP otherwise produces a library that throws
 * "unable to read file: helvetica.json" the first time anybody asks for a
 * certificate. The fourteen core metrics are therefore converted once, committed
 * under resources/pdf-fonts, and pointed at with K_PATH_FONTS. Nothing is built at
 * deploy time and no shell is involved, which is what "works on XAMPP" has to mean
 * for a project deployed by copying files.
 *
 * Core fonts rather than an embedded TrueType face, because an embedded face would
 * have to be licensed and committed too. The fourteen core fonts are in every
 * conforming reader, they cover the Latin text these documents are written in, and
 * the output stays small.
 *
 * WHY ENCRYPTION IS SET ON THE CONSTRUCTOR
 *
 * tc-lib-pdf takes an Encrypt object as its sixth constructor argument and keys
 * every string and stream through it as the document is built. There is no
 * post-hoc setter, which is the right design: a library that encrypted on save
 * would leave a window in which a readable document existed in memory. Here there
 * is no point at which an unencrypted PDF exists.
 *
 * mode 3 is AES-256 - PDF writes it /V 5 /R 5 /Length 256 with the crypt filter
 * named /AESV3 - the strongest standard security handler, supported by Adobe
 * Reader, Chrome, macOS Preview and every other mainstream reader since 2013.
 * Mode 4 would add the revised R6 key derivation on top of the same 256-bit key;
 * it is left alone deliberately, because R6 is a PDF 2.0 feature that older readers
 * reject outright, and a certificate that will not open on the machine at the hall
 * counter is worse than one a version behind on key derivation.
 *
 * Metadata is encrypted with the document. A title and an author are not secrets,
 * but leaving them readable in a file whose whole point is that it needs a password
 * would be an odd half-measure, and the library makes encrypting them the default.
 *
 * THE OWNER PASSWORD IS GENERATED HERE AND NEVER LEAVES THIS METHOD
 *
 * It is not written down, not logged, not sent, and not recoverable. That is the
 * point of it: the resident's own password opens the document for reading, and
 * nothing the office holds can later strip the restrictions from a copy that has
 * circulated. The library would generate one itself from an empty string, but an
 * explicit random value is passed so that no call site in this project ever shows an
 * empty password argument, which reads like an oversight and invites somebody to
 * replace it with a constant later.
 *
 * @see DigitalCopyPassword for how the resident's password is derived.
 */
class DigitalCopyRenderer
{
    /**
     * The footer used when the office has set none.
     *
     * Mirrors the seeded default so a settings row that was never written still
     * produces a copy that says what it is.
     */
    public const DEFAULT_FOOTER = 'This is a digitally generated copy. It is valid only when printed with the official signature and dry seal of the Punong Barangay.';

    /**
     * The logo the letterhead falls back to, relative to public/.
     */
    private const FALLBACK_LOGO = 'images/Zone 1_logo.png';

    /**
     * Left and right page margin, and the width the page is composed at. A4 is
     * 210mm wide, so 18mm each side leaves the 174mm every table below is sized
     * against.
     */
    private const MARGIN = 18.0;

    private const CONTENT_WIDTH = 174.0;

    public function __construct(private readonly string $fontDirectory) {}

    /**
     * Render one request's copy and return the encrypted bytes.
     *
     * The caller derives and passes the password rather than this class doing it,
     * so the rule and the encryption are two things testable apart - and so the
     * password is a value somebody decided to pass in and can be seen in the
     * caller's code, rather than conjured in here.
     */
    public function render(DocumentRequest $documentRequest, #[\SensitiveParameter] string $password): string
    {
        $this->assertFontsArePresent();

        $this->defineFontPath();

        $request = $documentRequest->loadMissing(['user', 'user.residentProfile', 'documentType']);

        $pdf = new Tcpdf(
            unit: 'mm',
            isunicode: true,
            subsetfont: false,
            compress: true,
            mode: '',
            objEncrypt: $this->encryptor($password),
        );

        $pdf->setCreator((string) config('app.name'));
        $pdf->setAuthor($this->barangayName());
        $pdf->setTitle((string) ($request->documentType?->label ?? 'Barangay document'));
        // The reference number, which is what the office and the resident both use
        // to talk about this document.
        $pdf->setSubject($request->reference_number);

        // One font, inserted once. The HTML engine reaches for the bold, italic and
        // bold-italic faces of the same family itself when it meets <b> and <i>, so
        // the metrics committed to the repository are the whole of what is needed.
        $font = $pdf->font->insert($pdf->pon, 'helvetica', '', 11);

        $pdf->addPage([
            'format' => 'A4',
            'orientation' => 'P',
            // One page, always. A certificate that ran onto a second sheet would be
            // a certificate with content the office has not agreed to; a purpose
            // too long to fit is truncated by the region rather than paginated.
            'autobreak' => false,
            'margin' => [
                'PL' => self::MARGIN,
                'PR' => self::MARGIN,
                'PT' => self::MARGIN,
                'CT' => self::MARGIN,
                'CB' => self::MARGIN,
            ],
            'region' => [[
                'RX' => self::MARGIN,
                'RY' => self::MARGIN,
                'RW' => self::CONTENT_WIDTH,
                'RH' => 261.0,
            ]],
        ]);

        $pdf->page->addContent($font['out']);

        $pdf->addHTMLCell(
            html: $this->documentHtml($request),
            posx: self::MARGIN,
            posy: 16.0,
            width: self::CONTENT_WIDTH,
        );

        $pdf->addHTMLCell(
            html: $this->footerHtml($request),
            posx: self::MARGIN,
            posy: 264.0,
            width: self::CONTENT_WIDTH,
        );

        return $pdf->getOutPDFString();
    }

    /**
     * The whole page above the footer.
     *
     * Built as one string rather than a Blade view: this is a document template,
     * not a page, it is rendered per download with no request behind it, and a
     * template engine would add a second way for a resident's name to reach a file
     * without escaping.
     */
    private function documentHtml(DocumentRequest $request): string
    {
        return implode('', [
            $this->letterheadHtml(),
            $this->titleHtml($request),
            $this->bodyHtml($request),
            $this->detailsHtml($request),
            $this->signatureHtml(),
        ]);
    }

    /**
     * Logo and the office's details across the top.
     *
     * The logo is inlined as a base64 data URI rather than referenced as a file
     * path. tc-lib-pdf restricts which local paths its file helper will read, and
     * public/ is not among them; a data URI carries no path at all, so the
     * configured logo cannot become a way to have the renderer read a file
     * outside the web root, and the copy is produced identically on XAMPP where the
     * absolute path differs from any server.
     */
    private function letterheadHtml(): string
    {
        $logo = $this->logoDataUri();

        $name = e($this->barangayName());

        $address = $this->addressLine();
        $contact = $this->contactLine();

        $subLines = '';

        foreach (array_filter([$address, $contact]) as $line) {
            $subLines .= '<font size="8.5" color="#5a6473">'.e($line).'</font><br/>';
        }

        if ($logo === null) {
            // No logo configured and none in public/: the name spans the full width
            // rather than leaving an empty cell beside it.
            $cells = '<td width="'.self::CONTENT_WIDTH.'mm">';
        } else {
            // The logo cell is 62mm because the IMAGE is 58mm, plus room for the
            // cell's own padding. That is not a detail: an image wider than its cell
            // makes the row wider than the page, and every table laid out after it
            // is then measured against what is left - so the signature block at the
            // bottom of the document comes out with a fraction of its width and its
            // text is silently clipped mid-word. Give the image a cell that fits.
            $cells = '<td width="62mm"><img src="'.$logo.'" width="58" height="58" /></td>'
                .'<td width="112mm">';
        }

        return '<table border="0" cellpadding="0" cellspacing="0" width="100%"><tr>'
            .$cells
            .'<font size="14"><b>'.e(mb_strtoupper($name)).'</b></font><br/>'
            .$subLines
            .'</td></tr></table>'
            .'<p></p>';
    }

    private function titleHtml(DocumentRequest $request): string
    {
        return '<table border="0" cellpadding="0" cellspacing="0" width="100%"><tr>'
            .'<td width="'.self::CONTENT_WIDTH.'mm" align="center">'
            .'<font size="14" color="#0f234b"><b>'
            .e(mb_strtoupper((string) ($request->documentType?->label ?? 'Certification')))
            .'</b></font><br/>'
            .'<font size="9" color="#5a6473">Reference No. '
            .e($request->reference_number)
            .'</font>'
            .'</td></tr></table>'
            .'<p></p>';
    }

    /**
     * The certifying words, per document type.
     *
     * Written out per key rather than assembled from the type's description,
     * because a certificate is read as a statement of fact by whoever receives it
     * and a templated sentence built from database fields would be vaguer than the
     * document it stands in for.
     *
     * An unrecognised key - a type an Admin added through the Settings screen -
     * gets the generic certification below rather than a blank page: a copy that
     * says only what is certainly true is better than one that says nothing.
     */
    private function bodyHtml(DocumentRequest $request): string
    {
        $name = e((string) $request->user?->name);
        $barangay = e($this->barangayName());

        $opening = match ($request->documentType?->key) {
            'barangay_clearance' => sprintf(
                'This is to certify that %s is a bona fide resident of %s and that there is no '
                .'derogatory record or pending case filed against him/her in the said barangay.',
                $name,
                $barangay
            ),
            'certificate_of_indigency' => sprintf(
                'This is to certify that %s is a bona fide resident of %s and is currently in '
                .'financial need, as certified by the Barangay Council, for the purpose of availing '
                .'any aid, scholarship, medical assistance or other assistance program.',
                $name,
                $barangay
            ),
            'first_time_jobseeker_certificate' => sprintf(
                'This is to certify that %s is a bona fide resident of %s and is a first-time job '
                .'seeker, in support of the First Time Jobseekers Assistance Act (Republic Act No. 11961).',
                $name,
                $barangay
            ),
            'barangay_residency_certificate' => sprintf(
                'This is to certify that %s is a bona fide resident of %s and has been so residing '
                .'at the address stated below.',
                $name,
                $barangay
            ),
            default => sprintf(
                'This is to certify that %s is a bona fide resident of %s, as evidenced by the '
                .'request recorded with this office under the reference number above.',
                $name,
                $barangay
            ),
        };

        $html = '<font size="11" align="justify">'.$opening.'</font><p></p>';

        $purpose = trim((string) $request->purpose);

        if ($purpose !== '') {
            $html .= '<font size="11"><b>This certification is issued for the following purpose:</b></font><br/>'
                .'<font size="11" align="justify">'.e($purpose).'</font><p></p>';
        }

        return $html
            .'<font size="11" align="justify">This certification is issued upon the request of the '
            .'above-named resident and for legitimate purposes.</font><p></p>';
    }

    /**
     * The facts the certificate records.
     *
     * The address comes from the resident's verified profile rather than from their
     * request: the profile is what the office verified at the desk, and a request
     * carries no address of its own.
     */
    private function detailsHtml(DocumentRequest $request): string
    {
        $profile = $request->user?->residentProfile;

        $rows = [
            'Name' => (string) $request->user?->name,
            'Address' => (string) ($profile?->address ?: 'Not stated'),
            'Household No.' => (string) ($profile?->household_number ?: '-'),
            'Date of birth' => $request->user?->date_of_birth?->format('F j, Y') ?? '-',
            'Date issued' => $request->ready_at?->format('F j, Y') ?? now()->format('F j, Y'),
        ];

        $html = '<p></p>';

        foreach ($rows as $label => $value) {
            $html .= '<font size="10"><b>'.e($label).':</b></font>'
                .'<font size="10">&nbsp;&nbsp;'.e($value).'</font><br/>';
        }

        return $html.'<p></p>';
    }

    /**
     * Where the office signs.
     *
     * A ruled line to sign over, in the right half of the page, and the Captain's
     * name from the existing punong_barangay setting - the office already records
     * who signs documents under that key, and a second "captain name" setting would
     * be a second place for the two to disagree.
     *
     * The "not valid without the dry seal" line is not decoration. Without it a
     * printed copy carries a signature block and a seal-shaped space and reads as
     * though something were missing, which is accurate; with it, the copy says so
     * before anybody has to ask.
     */
    private function signatureHtml(): string
    {
        $captain = Setting::text('punong_barangay');

        $name = $captain === ''
            ? ''
            : e(mb_strtoupper($captain)).'<br/>';

        return '<p></p>'
            .'<table border="0" cellpadding="0" cellspacing="0" width="'.self::CONTENT_WIDTH.'mm"><tr>'
            .'<td width="96mm">&nbsp;</td>'
            .'<td width="78mm" align="center">'
            .'<font size="9">Not valid without the dry seal.</font><br/>'
            .'<font size="10">&nbsp;</font><br/>'
            .$name
            .'<font size="10">____________________________</font><br/>'
            .'<font size="10"><b>Punong Barangay</b></font>'
            .'</td></tr></table>';
    }

    /**
     * The digital-copy notice, from settings.
     *
     * Anchored to its own position rather than flowing after the body, so it sits
     * in the same place on every copy.
     */
    private function footerHtml(DocumentRequest $request): string
    {
        $footer = Setting::text('digital_copy_footer', self::DEFAULT_FOOTER);

        return '<table border="0" cellpadding="0" cellspacing="0" width="100%"><tr>'
            .'<td width="'.self::CONTENT_WIDTH.'mm" align="center">'
            .'<font size="8" color="#69707a">'.e($footer).'</font><br/>'
            .'<font size="8" color="#8c929b">'
            .e($request->reference_number)
            .' | '.e(now()->format('F j, Y g:i A'))
            .'</font>'
            .'</td></tr></table>';
    }

    /**
     * The logo as a data URI, or null when there is none to draw.
     *
     * The setting is a path under public/ and is read only when it resolves to a
     * file inside public/, so a value typed into the Settings form cannot point the
     * renderer at something outside the web root. That box is a plain text field
     * written by whoever holds the Admin role.
     */
    private function logoDataUri(): ?string
    {
        $configured = trim(Setting::text('barangay_logo_path'));

        // Both are tried, in order, rather than one or the other. A missing file for
        // a configured path falls through to the logo in public/ instead of
        // producing a letterhead-less copy: an Admin who mistypes the path would
        // otherwise find out from a resident, because nothing else changes and
        // nothing anywhere reports a missing image.
        foreach ($configured === '' ? [self::FALLBACK_LOGO] : [$configured, self::FALLBACK_LOGO] as $candidate) {
            $data = $this->imageAt($candidate);

            if ($data !== null) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Read one image out of public/ as a data URI, or null if it is not there.
     *
     * Two things are being kept out with the realpath() check: a configured path
     * that climbs out of the web root, and one that is not a file at all. Neither
     * is a likely keypress, but the setting is written by an Admin and the
     * renderer's job is to render a certificate, not to be a file browser.
     */
    private function imageAt(string $candidate): ?string
    {
        $root = realpath(public_path());
        $path = realpath(public_path(ltrim($candidate, '/')));

        if ($root === false || $path === false || ! is_file($path)) {
            return null;
        }

        if (! str_starts_with($path, $root)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => 'image/png',
        };

        $bytes = @file_get_contents($path);

        if ($bytes === false || $bytes === '') {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    /**
     * Point the library at the committed metrics, once per process.
     *
     * K_PATH_FONTS is the constant the font loader reads to find helvetica.json,
     * and it has to exist before the first font is inserted - hence here, inside
     * render(), rather than in the constructor or a service provider where its
     * ordering against the loader would be a thing to remember.
     */
    private function defineFontPath(): void
    {
        if (! defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', $this->fontDirectory);
        }
    }

    /**
     * The encryption applied to every copy.
     *
     * @see Encrypt for the mode table and the permission
     *                                                   names.
     */
    private function encryptor(#[\SensitiveParameter] string $userPassword): Encrypt
    {
        return new Encrypt(
            enabled: true,
            file_id: '',
            // 3 = AES-256: /V 5 /R 5 /Length 256, crypt filter /AESV3.
            mode: 3,
            // Printing, copying text out, annotating, page assembly and extraction
            // for accessibility all stay permitted - a certificate a person cannot
            // print is not a certificate - and what is refused is modification and
            // the change of encryption itself.
            permissions: ['modify'],
            user_pass: $userPassword,
            owner_pass: bin2hex(random_bytes(16)),
            encryptMetadata: true,
        );
    }

    /**
     * Fail with a sentence that says what to do, rather than letting the library
     * throw "unable to read file: helvetica.json" - which is true, and tells
     * whoever is deploying this nothing about the committed directory.
     */
    private function assertFontsArePresent(): void
    {
        if (! is_file($this->fontDirectory.DIRECTORY_SEPARATOR.'helvetica.json')) {
            throw new RuntimeException(sprintf(
                'PDF font metrics are missing from %s. They are committed to the repository; '
                .'re-run "composer install" if that directory is empty.',
                $this->fontDirectory
            ));
        }
    }

    private function barangayName(): string
    {
        return Setting::text('barangay_name', 'Barangay');
    }

    private function addressLine(): string
    {
        return Setting::text('barangay_address');
    }

    /**
     * Contact details for the letterhead, whichever of the three exist.
     *
     * Pipe-separated rather than typographic. The core fonts here are WinAnsi, and
     * a middot in the letterhead is a byte that survives in the file but not always
     * in text extraction - which is where a test looking for a reference number
     * would go looking for it.
     */
    private function contactLine(): string
    {
        $parts = array_filter([
            Setting::text('office_phone'),
            Setting::text('office_email'),
            Setting::text('office_facebook'),
        ]);

        return implode(' | ', $parts);
    }
}
