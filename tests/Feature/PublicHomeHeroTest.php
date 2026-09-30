<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicHomeHeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_hero_renders_the_wave_divider_both_calls_to_action_and_the_floating_callout(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Welcome to Barangay Linasin', $content);
        $this->assertStringContainsString('Zone 1, Bangued, Abra', $content);
        $this->assertStringContainsString('Request a Document', $content);
        $this->assertStringContainsString('View Services', $content);
        $this->assertStringContainsString('Mas Maayos na Serbisyo', $content);
        $this->assertStringContainsString('Our Barangay', $content);
        $this->assertStringContainsString('View Details', $content);
        $this->assertStringContainsString('preserveAspectRatio="none"', $content);
    }

    public function test_hero_no_longer_renders_the_stat_counters(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Online services', $content);
        $this->assertStringNotContainsString('Request tracking', $content);
        $this->assertStringNotContainsString('Barangay portal', $content);
    }

    public function test_hero_primary_call_to_action_sends_guests_to_registration(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('register'), false);
    }

    public function test_hero_primary_call_to_action_sends_authenticated_residents_to_the_request_form(): void
    {
        $resident = User::factory()->resident()->create();

        $this->actingAs($resident)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('resident.requests.create'), false);
    }

    public function test_navigation_exposes_the_five_route_backed_primary_links(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        // Each primary destination is a standalone route now, not an in-page
        // "#section" anchor, so it can be linked to and bookmarked directly.
        foreach (['home', 'services.index', 'about', 'announcements.index', 'contact'] as $name) {
            $this->assertStringContainsString(route($name), $content);
        }

        $this->assertStringNotContainsString(route('home').'#services', $content);
        $this->assertStringNotContainsString(route('home').'#about', $content);
        $this->assertStringNotContainsString(route('home').'#contact', $content);
    }

    public function test_navigation_no_longer_renders_the_map_link(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/>\s*Map\s*</',
            $content,
            'The Map navigation link should have been removed from the header.',
        );
    }

    public function test_header_no_longer_renders_a_search_field(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="site-search"', $content);
        $this->assertStringNotContainsString('Search services, announcements', $content);
    }
}
