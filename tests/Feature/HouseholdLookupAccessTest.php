<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The two household lookups - /track and /transparency - sit behind a sign-in.
 *
 * Both read a record that belongs to one household: a document request looked
 * up by reference number, and a relief distribution history looked up by
 * household number. Both identifiers are short and sequential, so an open
 * lookup lets a visitor read another household's records simply by trying the
 * next number along. These tests cover the two halves of the boundary: that a
 * guest is sent to the login screen, and that a signed-in resident only ever
 * sees their own.
 */
class HouseholdLookupAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every route that reads a household record, so a route added to the
     * group later is covered here rather than shipped open.
     *
     * @return array<string, array{0: string}>
     */
    public static function protectedRouteProvider(): array
    {
        return [
            'request tracking' => ['requests.track'],
            'transparency board' => ['transparency.index'],
            'household lookup' => ['transparency.search'],
        ];
    }

    #[DataProvider('protectedRouteProvider')]
    public function test_a_guest_is_sent_to_the_login_screen(string $route): void
    {
        $this->get(route($route))
            ->assertRedirect(route('login'));
    }

    #[DataProvider('protectedRouteProvider')]
    public function test_a_guest_is_returned_to_the_page_they_asked_for_after_signing_in(string $route): void
    {
        // A bare redirect to /login loses the reason the visitor came. The
        // intended URL is what makes the login screen a detour rather than a
        // dead end.
        $this->get(route($route))->assertRedirect(route('login'));

        $this->assertSame(
            route($route),
            session()->pull('url.intended'),
            'The page a guest asked for was not held for after sign-in.',
        );
    }

    #[DataProvider('protectedRouteProvider')]
    public function test_a_signed_in_resident_gets_the_page(string $route): void
    {
        $this->actingAs(User::factory()->resident()->create())
            ->get(route($route))
            ->assertOk();
    }

    #[DataProvider('protectedRouteProvider')]
    public function test_the_page_is_also_open_to_signed_in_staff(string $route): void
    {
        // A kagawad or secretary helping a resident at the counter is a normal
        // reason to be on these pages, and the household record itself is not
        // staff-only information.
        $this->actingAs(User::factory()->secretary()->create())
            ->get(route($route))
            ->assertOk();
    }

    #[Test]
    public function test_a_resident_can_track_their_own_request(): void
    {
        $resident = $this->residentWithRequest();

        $this->actingAs($resident)
            ->get(route('requests.track', ['reference' => 'BRGY-2026-0001']))
            ->assertOk()
            ->assertSee('BRGY-2026-0001')
            ->assertSee('Employment requirement');
    }

    #[Test]
    public function test_a_resident_cannot_read_another_residents_request_by_guessing_its_reference(): void
    {
        $this->residentWithRequest();

        $stranger = User::factory()->resident()->create();

        $response = $this->actingAs($stranger)
            ->get(route('requests.track', ['reference' => 'BRGY-2026-0001']))
            ->assertOk();
        // Nothing that only a found record renders: not the stated purpose, and
        // not the status and next-action block. A "no request found" is also
        // what a made-up reference gets, so the page cannot be used to confirm
        // that a guess was real. The reference number itself is echoed back by
        // the search field, so it says nothing about whether a record was found.
        $response
            ->assertSee('No request of yours was found')
            ->assertDontSee('Employment requirement')
            ->assertDontSee('Your request is being reviewed')
            ->assertDontSee('Under Review');
    }

    #[Test]
    public function test_a_resident_cannot_walk_the_reference_numbers_in_order(): void
    {
        $this->residentWithRequest();

        $stranger = User::factory()->resident()->create();

        // Reference numbers are handed out in year order, so guessing the next
        // one is the obvious attack. Two neighbouring numbers, neither of which
        // belongs to the signed-in resident, both have to come back empty.
        foreach (['BRGY-2026-0002', 'BRGY-2025-9999'] as $guess) {
            $this->actingAs($stranger)
                ->get(route('requests.track', ['reference' => $guess]))
                ->assertOk()
                ->assertSee('No request of yours was found');
        }
    }

    #[Test]
    public function test_the_lookup_does_not_confirm_that_someone_elses_request_exists(): void
    {
        $this->residentWithRequest();

        $stranger = User::factory()->resident()->create();

        // A wrong reference and another resident's real reference have to be
        // indistinguishable, or the page leaks the existence of other requests.
        $wrong = $this->actingAs($stranger)
            ->get(route('requests.track', ['reference' => 'BRGY-2026-0001']))
            ->assertOk()
            ->getContent();

        $alsoWrong = $this->actingAs($stranger)
            ->get(route('requests.track', ['reference' => 'NOT-A-REFERENCE']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('No request of yours was found', $wrong);
        $this->assertStringContainsString('No request of yours was found', $alsoWrong);
    }

    /**
     * A verified resident holding one request, and the reference number it was
     * given. The request is written directly rather than through the submission
     * form: this suite is about who can read it, not about how it is created.
     */
    private function residentWithRequest(): User
    {
        $resident = User::factory()->resident()->create();

        DocumentRequest::create([
            'user_id' => $resident->id,
            'reference_number' => 'BRGY-2026-0001',
            'document_type' => DocumentRequest::TYPE_CLEARANCE,
            'purpose' => 'Employment requirement',
            'status' => DocumentRequest::STATUS_UNDER_REVIEW,
        ]);

        return $resident;
    }
}
