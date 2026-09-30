<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The three public lookups: the transparency board, the household check, and
 * request tracking.
 *
 * All three are unauthenticated and all three take a short, guessable key - a
 * household number, a four-digit reference. So the tests here are mostly about
 * what a page must NOT say, because the failure that matters is a stranger
 * learning a neighbour's business rather than a page not rendering.
 */
class PublicLookupsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A document-type label that appears on no other page.
     *
     * The real service names all appear in the public footer, so asserting on
     * one of them would pass whether or not the lookup found anything.
     */
    private const SENTINEL = 'Zonal Certification Document';

    // ---------------------------------------------------------------------
    // The transparency board
    // ---------------------------------------------------------------------

    #[Test]
    public function the_board_is_public(): void
    {
        ReliefBatch::factory()->create(['title' => 'Typhoon Relief Drive']);

        $this->get(route('transparency.index'))
            ->assertOk()
            ->assertSee('Typhoon Relief Drive');
    }

    /**
     * A batch is a roll call. Publishing the household numbers would turn a
     * transparency page into a directory of who is poor enough to need help,
     * which is the opposite of what the page is for.
     */
    #[Test]
    public function the_board_never_shows_a_household_number(): void
    {
        $this->batchWithHouseholds(['SECRET-01', 'SECRET-02']);

        $content = $this->get(route('transparency.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('SECRET-01', $content);
        $this->assertStringNotContainsString('SECRET-02', $content);
    }

    #[Test]
    public function the_board_shows_aggregates_only(): void
    {
        $batch = $this->batchWithHouseholds(['HH-01', 'HH-02', 'HH-03']);

        $batch->distributions()->where('household_number', 'HH-01')->update([
            'status' => ReliefDistribution::STATUS_RECEIVED,
        ]);

        $content = $this->get(route('transparency.index'))->assertOk()->getContent();

        $this->assertStringContainsString('3', $content, 'Three households are on the list.');
        $this->assertStringContainsString('1', $content, 'One of them has collected.');
    }

    /**
     * A household-number lookup on a public page is a page that confirms which
     * household numbers are real, and they are short enough to enumerate. The
     * board therefore carries no lookup form and the route does not exist; a
     * resident reads their own record through resident.relief, which is scoped by
     * the route rather than by a parameter they supply.
     */
    #[Test]
    public function there_is_no_public_household_number_lookup(): void
    {
        $board = $this->get(route('transparency.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="household_number"', $board);
        $this->assertStringNotContainsString('transparency/search', $board);

        $this->assertFalse(
            collect(app('router')->getRoutes())->contains(fn ($route) => $route->uri() === 'transparency/search'),
            'A public household-number lookup must not exist.'
        );
    }

    #[Test]
    public function the_board_offers_a_signed_in_resident_their_own_record(): void
    {
        $profile = $this->profile('MINE-01', ResidentProfile::VERIFICATION_VERIFIED);

        $this->actingAs($profile->user)
            ->get(route('transparency.index'))
            ->assertOk()
            ->assertSee(route('resident.relief'));
    }

    #[Test]
    public function the_board_offers_a_guest_the_way_in(): void
    {
        $this->get(route('transparency.index'))
            ->assertOk()
            ->assertSee(route('login'));
    }

    // ---------------------------------------------------------------------
    // Request tracking
    // ---------------------------------------------------------------------

    /**
     * A reference is BRGY-YYYY-NNNN. Four random digits is a few thousand
     * possibilities, so tracking on the reference alone would let somebody walk
     * a stranger's request: their document type, their timestamps, and their
     * rejection reason - which is free text a Secretary wrote about them.
     */
    #[Test]
    public function a_reference_alone_does_not_reveal_a_request(): void
    {
        $documentRequest = $this->documentRequest();

        $content = $this->get(route('requests.track', [
            'reference' => $documentRequest->reference_number,
        ]))->assertOk()->getContent();

        $this->assertStringNotContainsString(self::SENTINEL, $content);
    }

    #[Test]
    public function the_right_household_number_with_the_reference_does_reveal_it(): void
    {
        $documentRequest = $this->documentRequest();

        $this->get(route('requests.track', [
            'reference' => $documentRequest->reference_number,
            'household' => 'HH-1001',
        ]))
            ->assertOk()
            ->assertSee(self::SENTINEL)
            ->assertSee($documentRequest->reference_number);
    }

    #[Test]
    public function the_wrong_household_number_is_refused(): void
    {
        $documentRequest = $this->documentRequest();

        $this->get(route('requests.track', [
            'reference' => $documentRequest->reference_number,
            'household' => 'HH-0000',
        ]))
            ->assertOk()
            ->assertDontSee(self::SENTINEL);
    }

    /**
     * Even to the person who filed it, the tracked result is short of the
     * record. They have proved they filed it; they have no need of the name and
     * address on it, and a public page should not be echoing those.
     */
    #[Test]
    public function a_tracked_request_shows_no_name_or_address(): void
    {
        $documentRequest = $this->documentRequest();
        $user = $documentRequest->user;

        $content = $this->get(route('requests.track', [
            'reference' => $documentRequest->reference_number,
            'household' => 'HH-1001',
        ]))->assertOk()->getContent();

        $this->assertStringNotContainsString($user->email, $content);
    }

    #[Test]
    public function a_rejection_reason_is_shown_so_the_resident_knows_what_to_do(): void
    {
        $documentRequest = $this->documentRequest(status: DocumentRequest::STATUS_REJECTED);
        $documentRequest->update(['rejection_reason' => 'Please state the receiving office instead.']);

        $this->get(route('requests.track', [
            'reference' => $documentRequest->reference_number,
            'household' => 'HH-1001',
        ]))
            ->assertOk()
            ->assertSee('Please state the receiving office instead.');
    }

    /**
     * A request that does not exist and one whose household number is wrong must
     * be indistinguishable, or this confirms a guessed reference.
     */
    #[Test]
    public function a_wrong_reference_and_a_wrong_household_read_the_same(): void
    {
        $this->documentRequest();

        $wrongReference = $this->get(route('requests.track', [
            'reference' => 'BRGY-2020-0000',
            'household' => 'HH-1001',
        ]))->assertOk()->getContent();

        $wrongHousehold = $this->get(route('requests.track', [
            'reference' => 'BRGY-2020-0000',
            'household' => 'HH-0000',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('could not find that request', $wrongReference);
        $this->assertStringContainsString('could not find that request', $wrongHousehold);
    }

    // ---------------------------------------------------------------------
    // Resident verification status
    // ---------------------------------------------------------------------

    #[Test]
    public function a_resident_can_read_their_own_verification_status(): void
    {
        $profile = $this->profile('HH-1001', ResidentProfile::VERIFICATION_PENDING);

        // The old address redirects to the tab rather than rendering a second
        // page showing the same thing.
        $this->actingAs($profile->user)
            ->get(route('resident.verification.status'))
            ->assertRedirect(route('account.edit', ['tab' => 'verification']));

        $this->actingAs($profile->user)
            ->get(route('account.edit', ['tab' => 'verification']))
            ->assertOk()
            ->assertSee('HH-1001');
    }

    #[Test]
    public function a_rejected_resident_is_told_what_to_change(): void
    {
        $profile = $this->profile('HH-1001', ResidentProfile::VERIFICATION_REJECTED);
        $profile->update(['rejection_reason' => 'The household number is not on the master list.']);

        $this->actingAs($profile->user)
            ->get(route('account.edit', ['tab' => 'verification']))
            ->assertOk()
            ->assertSee('The household number is not on the master list.')
            ->assertSee(route('resident.verification'));
    }

    /**
     * The status page is a profile tab, so it belongs to the resident area. A
     * stub with no role middleware is how the matrix in PermissionMatrixTest
     * found it.
     */
    #[Test]
    public function the_verification_status_page_is_resident_only(): void
    {
        $route = collect(app('router')->getRoutes())
            ->first(fn ($r) => $r->getName() === 'resident.verification.status');

        $this->assertNotNull($route);
        $this->assertTrue($route->gatherMiddleware() !== []);
    }

    #[Test]
    public function the_status_page_needs_no_household_number_from_the_query_string(): void
    {
        $mine = $this->profile('HH-1001', ResidentProfile::VERIFICATION_VERIFIED);
        $theirs = $this->profile('HH-2002', ResidentProfile::VERIFICATION_VERIFIED);

        $content = $this->actingAs($mine->user)
            ->get(route('account.edit', ['tab' => 'verification', 'household_number' => 'HH-2002']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('HH-2002', $content);
        $this->assertStringContainsString('HH-1001', $content);
    }

    // ---------------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------------

    /**
     * @param  list<string>  $households
     */
    private function batchWithHouseholds(array $households): ReliefBatch
    {
        $batch = ReliefBatch::factory()->create([
            'title' => 'Typhoon Relief Drive',
            'status' => ReliefBatch::STATUS_ACTIVE,
        ]);

        foreach ($households as $household) {
            $sector = Sector::factory()->create();

            ReliefDistribution::factory()->create([
                'relief_batch_id' => $batch->getKey(),
                'sector_id' => $sector->getKey(),
                'household_number' => $household,
            ]);
        }

        return $batch->fresh();
    }

    private function documentRequest(string $status = DocumentRequest::STATUS_SUBMITTED): DocumentRequest
    {
        $profile = $this->profile('HH-1001');

        return DocumentRequest::factory()->create([
            'user_id' => $profile->user_id,
            'document_type_id' => DocumentType::factory()->create(['label' => self::SENTINEL])->id,
            'status' => $status,
        ]);
    }

    private function profile(string $household, string $status = ResidentProfile::VERIFICATION_PENDING): ResidentProfile
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        return ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'sector_id' => Sector::factory()->create()->id,
            'household_number' => $household,
            'verification_status' => $status,
        ]);
    }
}
