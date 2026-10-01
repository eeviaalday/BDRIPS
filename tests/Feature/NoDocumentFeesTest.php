<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Barangay documents are free, and this pins that in both directions.
 *
 * The money is gone from the schema and from what a resident is shown, and both
 * halves are asserted here rather than trusted. A fee can come back quietly: a
 * column re-added by a rollback, a label that picks a price up from somewhere
 * else, a summary that starts quoting one because a value happens to be sitting
 * in a row. None of those announce themselves.
 */
class NoDocumentFeesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The schema, which is the half that cannot be argued with.
     */
    #[Test]
    public function neither_the_request_nor_the_type_carries_a_fee_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('document_requests', 'fee_amount'),
            'document_requests still has a fee_amount column.'
        );

        $this->assertFalse(
            Schema::hasColumn('document_types', 'fee_amount'),
            'document_types still has a fee_amount column.'
        );
    }

    /**
     * And no money column has appeared under a different name.
     */
    #[Test]
    public function no_money_column_survives_on_either_table(): void
    {
        foreach (['document_requests', 'document_types'] as $table) {
            $money = array_values(array_filter(
                Schema::getColumnListing($table),
                fn (string $column): bool => (bool) preg_match('/fee|price|amount|rate|cost|paid/i', $column)
            ));

            $this->assertSame([], $money, "[{$table}] still has a money column: ".implode(', ', $money));
        }
    }

    /**
     * The Track dialog, which is where a resident reads a request back. It used
     * to print the fee as one of the facts of the request.
     */
    #[Test]
    public function the_track_dialog_never_mentions_a_fee(): void
    {
        $resident = $this->verifiedResident();
        $type = DocumentType::factory()->create();
        DocumentRequest::factory()->submitted()->create([
            'user_id' => $resident->id,
            'document_type_id' => $type->id,
        ]);

        $html = $this->actingAs($resident)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('role="dialog"', $html, 'The Track dialog is not on the page.');
        $this->assertDoesNotMatchRegularExpression(
            '/\bfees?\b/i',
            $html,
            'The Track dialog still talks about a fee.'
        );
    }

    /**
     * The chooser. The price used to be part of every option label, which meant
     * a resident was choosing between documents partly on cost.
     */
    #[Test]
    public function the_new_request_page_never_mentions_a_fee(): void
    {
        // A type has to exist, or the chooser renders no options and the page has
        // nothing that could carry a price. Without this the assertion below
        // passes on an empty <select> and proves nothing.
        DocumentType::factory()->create(['is_active' => true]);

        $html = $this->actingAs($this->verifiedResident())
            ->get(route('resident.requests.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="document_type_id"', $html);
        $this->assertStringContainsString('<option', $html, 'The chooser rendered no options to check.');
        $this->assertDoesNotMatchRegularExpression(
            '/\bfees?\b/i',
            $html,
            'The document chooser still quotes a fee.'
        );
    }

    /**
     * No currency figure reaches the reader.
     *
     * Asserted against the literal things the old code emitted rather than a
     * pattern over the whole page. A regex across a full HTML document is a
     * backtracking hazard for no gain here: the strings that used to print a
     * price were "PHP " and number_format(), and a fee word is covered above.
     */
    #[Test]
    public function no_resident_document_page_prints_a_currency_amount(): void
    {
        $resident = $this->verifiedResident();
        DocumentType::factory()->create(['is_active' => true]);

        foreach ([
            route('resident.requests.index'),
            route('resident.requests.create'),
        ] as $url) {
            $html = $this->actingAs($resident)->get($url)->assertOk()->getContent();

            foreach (['PHP ', 'number_format', "\u{20b1}"] as $marker) {
                $this->assertStringNotContainsString(
                    $marker,
                    $html,
                    "[{$url}] still emits [{$marker}], which is how a price used to be printed."
                );
            }
        }
    }

    private function verifiedResident(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'sector_id' => Sector::factory()->create()->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        return $user;
    }
}
