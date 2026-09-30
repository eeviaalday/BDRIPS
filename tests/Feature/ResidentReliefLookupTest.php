<?php

namespace Tests\Feature;

use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The household lookup on the resident's Relief Transparency page.
 *
 * The form on this page used to hand the view an empty collection no matter
 * what was typed, so a resident who entered their reference got a page that
 * looked broken. It also accepted an unbounded string and revealed the name of
 * whoever collected a relative's relief, neither of which a household lookup
 * needs.
 */
class ResidentReliefLookupTest extends TestCase
{
    use RefreshDatabase;

    private function resident(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        return $user->fresh();
    }

    #[Test]
    public function a_household_number_typed_into_the_portal_form_actually_returns_records(): void
    {
        $row = ReliefDistribution::factory()->create([
            'household_number' => 'HH-4242',
            'status' => ReliefDistribution::STATUS_RECEIVED,
        ]);

        $content = $this->actingAs($this->resident())
            ->get(route('resident.relief', ['household_number' => 'HH-4242']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('HH-4242', $content);
        $this->assertStringContainsString($row->batch?->title ?? 'Relief assistance', $content);
        $this->assertStringContainsString('Received', $content);
    }

    #[Test]
    public function the_lookup_does_not_leave_the_portal(): void
    {
        // It posted to the public transparency route, which rendered a
        // different page with a second form on it.
        $content = $this->actingAs($this->resident())
            ->get(route('resident.relief'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('action="'.route('resident.relief').'"', $content);
        $this->assertStringNotContainsString('action="'.route('transparency.search').'"', $content);
    }

    #[Test]
    public function an_unknown_household_number_says_so_rather_than_showing_a_blank(): void
    {
        $content = $this->actingAs($this->resident())
            ->get(route('resident.relief', ['household_number' => 'HH-9999']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('No relief record is filed against', $content);
    }

    #[Test]
    public function a_matched_household_shows_no_name_even_on_the_public_board(): void
    {
        // Both lookups accept an arbitrary reference, so both have to withhold
        // the name of whoever signed. Short and sequential household numbers
        // make that reference a cheap way to read the relief register.
        ReliefDistribution::factory()->create([
            'household_number' => 'HH-5151',
            'status' => ReliefDistribution::STATUS_RECEIVED,
            'received_by_name' => 'Rosita Del Mundo',
        ]);

        $user = $this->resident();

        $portal = $this->actingAs($user)
            ->get(route('resident.relief', ['household_number' => 'HH-5151']))
            ->assertOk()
            ->getContent();

        $board = $this->actingAs($user)
            ->get(route('transparency.search', ['household_number' => 'HH-5151']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Rosita Del Mundo', $portal);
        $this->assertStringNotContainsString('Rosita Del Mundo', $board);
    }

    #[Test]
    public function a_resident_still_sees_who_signed_for_their_own_household(): void
    {
        // The resident's own record is their own information, and it is the one
        // case where knowing who collected the relief is actually useful - a
        // spouse who collected it, or a neighbour they had to ask. Withholding
        // it everywhere would be over-correcting.
        $user = $this->resident();

        ReliefDistribution::factory()->create([
            'household_number' => $user->residentProfile->household_number,
            'status' => ReliefDistribution::STATUS_RECEIVED,
            'received_by_name' => 'Nenita Del Mundo',
        ]);

        $content = $this->actingAs($user)
            ->get(route('resident.relief'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Nenita Del Mundo', $content);
    }

    #[Test]
    public function a_malformed_household_number_is_refused_with_a_message(): void
    {
        $response = $this->actingAs($this->resident())
            ->from(route('resident.relief'))
            ->get(route('resident.relief', ['household_number' => '<script>alert(1)</script>']));

        $response->assertRedirect(route('resident.relief'));
        $response->assertSessionHasErrors('household_number');
    }

    #[Test]
    public function an_over_long_household_number_is_refused(): void
    {
        $response = $this->actingAs($this->resident())
            ->from(route('resident.relief'))
            ->get(route('resident.relief', ['household_number' => str_repeat('A', 51)]));

        $response->assertRedirect(route('resident.relief'));
        $response->assertSessionHasErrors('household_number');
    }

    #[Test]
    public function arriving_with_no_reference_at_all_is_not_an_error(): void
    {
        // The normal case: the resident opened the page, not submitted a form.
        $this->actingAs($this->resident())
            ->get(route('resident.relief'))
            ->assertOk()
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function the_lookup_reports_while_it_is_working(): void
    {
        // A GET that returns the same page looks exactly like a dead button if
        // the wait is long, so the form says it is searching and blocks a second
        // submit. Both are Alpine-bound, so with scripting off the form still
        // submits - that is asserted by the tests above.
        $content = $this->actingAs($this->resident())
            ->get(route('resident.relief'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('x-on:submit="searching = true"', $content);
        $this->assertStringContainsString('Searching', $content);
    }

    #[Test]
    public function the_public_board_and_the_portal_agree_on_a_household(): void
    {
        ReliefDistribution::factory()->create([
            'household_number' => 'HH-6060',
            'status' => ReliefDistribution::STATUS_PENDING,
        ]);

        $user = $this->resident();

        $board = $this->actingAs($user)
            ->get(route('transparency.search', ['household_number' => 'HH-6060']))
            ->assertOk()
            ->getContent();

        $portal = $this->actingAs($user)
            ->get(route('resident.relief', ['household_number' => 'HH-6060']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('HH-6060', $board);
        $this->assertStringContainsString('HH-6060', $portal);
    }

    #[Test]
    public function the_lookup_is_rate_limited_so_a_household_range_cannot_be_walked(): void
    {
        // Household numbers are short and sequential, so without a ceiling this
        // endpoint is a way to read the whole relief register one guess at a
        // time. The resident's own dashboard is a normal page view, so this is
        // checked on the lookup routes only.
        $user = $this->resident();

        $throttled = 0;

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $response = $this->actingAs($user)
                ->get(route('transparency.search', ['household_number' => 'HH-'.str_pad((string) $attempt, 4, '0', STR_PAD_LEFT)]));

            if ($response->status() === 429) {
                $throttled = $attempt;
                break;
            }
        }

        $this->assertNotSame(0, $throttled, 'The household lookup was never rate limited.');
    }
}
