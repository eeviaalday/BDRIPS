<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\DocumentRequest;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The resident dashboard.
 *
 * The four stat cards are the part worth defending: they are counts, and a count
 * that quietly includes finished work is worse than no card at all, because a
 * resident reads "3" as three things to chase. So the arithmetic is asserted,
 * not just the presence of the labels.
 */
class ResidentDashboardTest extends TestCase
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

    public function test_the_dashboard_renders_for_a_verified_resident(): void
    {
        $this->actingAs($this->resident())
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->assertSee('Recent Requests')
            ->assertSee('Quick Links')
            ->assertSee('Upcoming Events');
    }

    public function test_the_active_requests_card_counts_only_work_still_moving(): void
    {
        $user = $this->resident();

        foreach ([
            DocumentRequest::STATUS_SUBMITTED,
            DocumentRequest::STATUS_UNDER_REVIEW,
            DocumentRequest::STATUS_READY,
        ] as $index => $status) {
            DocumentRequest::factory()->create(['user_id' => $user->id, 'status' => $status]);
        }

        // Finished or dead requests must not inflate the figure.
        foreach ([
            DocumentRequest::STATUS_COMPLETED,
            DocumentRequest::STATUS_REJECTED,
            DocumentRequest::STATUS_ABANDONED,
        ] as $status) {
            DocumentRequest::factory()->create(['user_id' => $user->id, 'status' => $status]);
        }

        $this->actingAs($user)
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->assertViewHas('activeCount', 3)
            ->assertViewHas('readyCount', 1);
    }

    public function test_the_counts_only_cover_the_signed_in_residents_own_requests(): void
    {
        $user = $this->resident();
        DocumentRequest::factory()->create(['user_id' => $user->id, 'status' => DocumentRequest::STATUS_SUBMITTED]);
        DocumentRequest::factory()->create(['user_id' => $user->id, 'status' => DocumentRequest::STATUS_SUBMITTED]);

        $other = User::factory()->resident()->create(['is_verified' => true]);
        DocumentRequest::factory()->count(3)->create([
            'user_id' => $other->id,
            'status' => DocumentRequest::STATUS_SUBMITTED,
        ]);

        $this->actingAs($user)
            ->get(route('resident.dashboard', absolute: false))
            ->assertViewHas('activeCount', 2);
    }

    public function test_the_relief_card_counts_what_the_household_received(): void
    {
        $user = $this->resident();

        ReliefDistribution::factory()->count(2)->create([
            'household_number' => $user->residentProfile->household_number,
            'status' => ReliefDistribution::STATUS_RECEIVED,
        ]);
        ReliefDistribution::factory()->create([
            'household_number' => $user->residentProfile->household_number,
            'status' => ReliefDistribution::STATUS_PENDING,
        ]);

        $this->actingAs($user)
            ->get(route('resident.dashboard', absolute: false))
            ->assertViewHas('reliefCount', 2);
    }

    public function test_the_announcement_card_counts_recent_published_notices_only(): void
    {
        Announcement::create(['title' => 'Recent', 'body' => 'x', 'published_at' => now()->subDays(2)]);
        Announcement::create(['title' => 'Older', 'body' => 'x', 'published_at' => now()->subDays(60)]);
        Announcement::create(['title' => 'Draft', 'body' => 'x', 'published_at' => null]);
        // Scheduled for the future: not visible to a resident yet.
        Announcement::create(['title' => 'Future', 'body' => 'x', 'published_at' => now()->addWeek()]);

        $this->actingAs($this->resident())
            ->get(route('resident.dashboard', absolute: false))
            ->assertViewHas('announcementCount', 1);
    }

    public function test_the_dashboard_shows_at_most_five_recent_requests(): void
    {
        $user = $this->resident();
        DocumentRequest::factory()->count(8)->create([
            'user_id' => $user->id,
            'status' => DocumentRequest::STATUS_SUBMITTED,
        ]);

        $this->actingAs($user)
            ->get(route('resident.dashboard', absolute: false))
            ->assertViewHas('requests', fn ($requests): bool => $requests->count() === 5);
    }

    public function test_the_recent_requests_table_labels_each_type_and_status(): void
    {
        $user = $this->resident();
        DocumentRequest::factory()->create([
            'user_id' => $user->id,
            'document_type' => DocumentRequest::TYPE_CLEARANCE,
            'status' => DocumentRequest::STATUS_UNDER_REVIEW,
        ]);

        $content = $this->actingAs($user)
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Document Type', $content);
        $this->assertStringContainsString('Reference No.', $content);
        $this->assertStringContainsString('Barangay Clearance', $content);
        $this->assertStringContainsString('Under Review', $content);
    }

    public function test_the_hero_card_carries_the_slogan_and_a_real_photo(): void
    {
        $content = $this->actingAs($this->resident())
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('A Stronger Barangay Builds a Brighter Future', $content);
        $this->assertMatchesRegularExpression('#<img[^>]+src="[^"]+\.(jpe?g|png|avif|svg)"#i', $content);
    }

    public function test_the_dashboard_tells_an_unverified_resident_why_it_is_limited(): void
    {
        $user = User::factory()->resident()->create(['is_verified' => false]);
        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => ResidentProfile::VERIFICATION_PENDING,
        ]);

        $content = $this->actingAs($user)
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Verification is pending', $content);
    }

    public function test_a_rejected_profile_shows_the_reason_rather_than_a_generic_banner(): void
    {
        $user = User::factory()->resident()->create(['is_verified' => false]);
        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => ResidentProfile::VERIFICATION_REJECTED,
            'rejection_reason' => 'The ID photograph was too blurry to read.',
        ]);

        $content = $this->actingAs($user)
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('The ID photograph was too blurry to read.', $content);
    }

    public function test_a_resident_with_no_profile_gets_an_explanation_rather_than_an_error(): void
    {
        $this->actingAs(User::factory()->resident()->create(['is_verified' => true]))
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->assertSee('Your resident profile is not set up yet');
    }

    public function test_the_quick_links_all_point_at_real_portal_routes(): void
    {
        $content = $this->actingAs($this->resident())
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        foreach (['Barangay Officials', 'Contact Information', 'Barangay Profile', 'FAQs'] as $label) {
            $this->assertStringContainsString($label, $content);
        }

        $this->assertStringContainsString(route('resident.directory', absolute: false), $content);
        $this->assertStringContainsString(route('resident.profile', absolute: false), $content);
    }

    public function test_past_events_are_dropped_from_the_upcoming_list(): void
    {
        config()->set('bdrpis.upcoming_events', [
            ['title' => 'Already happened', 'date' => now()->subMonth()->toDateString(), 'time' => '9:00 AM', 'where' => 'Hall'],
            ['title' => 'Still to come', 'date' => now()->addMonth()->toDateString(), 'time' => '9:00 AM', 'where' => 'Hall'],
        ]);

        $content = $this->actingAs($this->resident())
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Still to come', $content);
        $this->assertStringNotContainsString('Already happened', $content);
    }

    public function test_the_dashboard_says_so_when_there_is_no_event_scheduled(): void
    {
        config()->set('bdrpis.upcoming_events', []);

        $this->actingAs($this->resident())
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->assertSee('No events are scheduled at the moment');
    }

    public function test_a_resident_with_no_requests_is_offered_the_request_form(): void
    {
        $content = $this->actingAs($this->resident())
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('No document requests yet', $content);
        $this->assertStringContainsString(route('resident.requests.create', absolute: false), $content);
    }
}
