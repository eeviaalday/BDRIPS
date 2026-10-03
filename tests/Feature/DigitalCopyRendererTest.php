<?php

namespace Tests\Feature;

use App\Models\DocumentDelivery;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\Setting;
use App\Models\User;
use App\Services\DigitalCopyPassword;
use App\Services\DigitalCopyRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The PDF itself: that it is a real PDF, and that it is actually encrypted.
 *
 * WHY THESE TESTS READ RAW BYTES
 *
 * A test that renders a copy and asserts it starts with "%PDF" would pass for a
 * completely unencrypted document, because that header is plaintext in every PDF.
 * The only way to tell an encrypted copy from an unencrypted one without opening
 * it in a reader is to look for the encryption dictionary in the file - so that is
 * what is asserted here.
 *
 * The second thing worth asserting is that nothing readable survives. Because the
 * resident's own data is inside the encrypted part, the tests use strings that were
 * chosen to be unmistakable (the metadata title the renderer sets, and a document
 * label invented for the test) and then assert they cannot be found in the bytes.
 * That is the difference between a copy that is marked encrypted and a copy that
 * is.
 */
class DigitalCopyRendererTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // It is a PDF, and it is an encrypted one
    // ---------------------------------------------------------------------

    #[Test]
    public function the_copy_is_a_pdf_with_an_encryption_dictionary(): void
    {
        $bytes = $this->render();

        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringContainsString('/Encrypt', $bytes);
    }

    /**
     * The algorithm is pinned rather than read back from the library.
     *
     * These four figures are the ones agreed for the barangay, and each was chosen
     * for a reason a future edit would not guess: AES-256 (CFM /AESV3) so the copy
     * is not sitting there in a cipher a 2010 laptop can break, revision 5 rather
     * than 6 because R6 needs AES-256 *and* an algorithm a reader may not have, and
     * a 256-bit key. A silent change to any of them is a downgrade nobody chose.
     */
    #[Test]
    public function the_encryption_is_the_one_that_was_agreed(): void
    {
        $bytes = $this->render();

        $this->assertStringContainsString('/Filter /Standard', $bytes);
        $this->assertStringContainsString('/V 5', $bytes);
        $this->assertStringContainsString('/R 5', $bytes);
        $this->assertStringContainsString('/Length 256', $bytes);
        $this->assertStringContainsString('/CFM /AESV3', $bytes);
    }

    /**
     * The document's own data is inside the encrypted part.
     *
     * A type label invented for this test appears nowhere in the bytes. If the
     * content streams were left in the clear this fails, which is the point: the
     * label stands in for the resident's name, their birthdate and the reference
     * number.
     */
    #[Test]
    public function the_contents_are_encrypted_not_merely_marked(): void
    {
        $label = 'ZZQQ Encrypted Clearance ZZQQ';

        $bytes = $this->render(documentTypeLabel: $label);

        $this->assertStringNotContainsString($label, $bytes);
        $this->assertStringNotContainsString('This is to certify', $bytes);
    }

    /**
     * Metadata is encrypted too.
     *
     * Author, title and keywords are not the resident's data, but they say who made
     * the document and what it is, and they are plaintext in every PDF that does not
     * ask for them to be encrypted. tc-lib-pdf's default is to encrypt them, and this
     * asserts that default is still in force rather than assumed.
     */
    #[Test]
    public function the_metadata_is_encrypted(): void
    {
        $bytes = $this->render();

        // The title the renderer sets. Visible as a plain string in /Info whenever
        // encryptMetadata is off.
        $this->assertStringNotContainsString('Barangay Digital Copy', $bytes);
    }

    /**
     * Every copy is keyed differently, even for the same resident and request.
     *
     * Two identical copies would mean the same file was handed round twice, and a
     * copy that was withdrawn would still be openable by whoever had it. The owner
     * password is generated per render for exactly this reason, and these two
     * assertions are how that shows up in the bytes.
     */
    #[Test]
    public function two_copies_of_the_same_request_are_not_the_same_file(): void
    {
        $request = $this->readyRequest();

        $this->assertNotSame($this->render($request), $this->render($request));
    }

    // ---------------------------------------------------------------------
    // It renders at all
    // ---------------------------------------------------------------------

    #[Test]
    public function the_office_logo_is_embedded_as_an_image(): void
    {
        // The letterhead is a picture, and a renderer that quietly dropped it would
        // still produce a valid, still-encrypted, entirely unrecognisable document.
        $this->assertStringContainsString('/Subtype /Image', $this->render());
    }

    #[Test]
    public function a_logo_path_that_does_not_exist_still_produces_a_copy(): void
    {
        // An Admin can mistype the logo path. Losing the letterhead picture is an
        // annoyance; losing the resident's copy entirely would be worse, so the
        // renderer falls back to the logo in public/ and carries on.
        Setting::query()->updateOrCreate(['key' => 'barangay_logo_path'], ['value' => 'images/not-here.png']);

        $this->assertStringContainsString('/Subtype /Image', $this->render());
    }

    #[Test]
    public function a_name_with_markup_in_it_still_renders(): void
    {
        $resident = $this->resident(['name' => '<b>Dela</b> Cruz & Sons <script>alert(1)</script>']);

        // Not an assertion about what the PDF says - it is encrypted, so a test
        // could not read it anyway - but about the fact that resident-controlled
        // text goes through the HTML layer without taking the render down.
        $this->assertStringStartsWith('%PDF-', $this->render($this->readyRequest($resident)));
    }

    #[Test]
    public function an_unknown_document_type_still_renders_a_certificate(): void
    {
        // A type added through the Settings screen has no wording here. A copy that
        // says only what is certainly true is better than a blank page.
        $type = DocumentType::factory()->create(['key' => 'something_new', 'label' => 'Something New']);

        $request = DocumentRequest::factory()->ready()->forResident($this->resident())->create([
            'document_type_id' => $type->id,
        ]);

        $bytes = app(DigitalCopyRenderer::class)->render($request->fresh(['user', 'documentType']), 'anything');

        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertSame(0, DocumentDelivery::query()->count());
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function render(?DocumentRequest $request = null, ?string $documentTypeLabel = null): string
    {
        $request ??= $this->readyRequest();

        if ($documentTypeLabel !== null) {
            DocumentType::query()->whereKey($request->document_type_id)->update([
                'key' => 'barangay_clearance',
                'label' => $documentTypeLabel,
            ]);

            $request->refresh();
        }

        return app(DigitalCopyRenderer::class)->render(
            $request->fresh(['user', 'documentType']),
            DigitalCopyPassword::for($request->user)
        );
    }

    private function readyRequest(?User $resident = null): DocumentRequest
    {
        return DocumentRequest::factory()
            ->ready()
            ->forResident($resident ?? $this->resident())
            ->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function resident(array $overrides = []): User
    {
        return User::factory()->resident()->create($overrides + [
            'name' => 'Juan Dela Cruz Santos',
            'date_of_birth' => '1994-03-17',
        ]);
    }
}
