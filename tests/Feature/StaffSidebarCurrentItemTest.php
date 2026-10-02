<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The sidebar must say where you are, on detail and form pages as well as lists.
 *
 * `RoleNavigation::isCurrent()` has always claimed to honour a per-destination
 * `pattern` "so a detail page still lights its parent" - but the pattern key was
 * only ever set in the resident bar, never in the staff sidebar. On a staff list
 * page the exact route name matched and nobody noticed. On the nine staff screens
 * that are a detail or a form, nothing matched, and the sidebar rendered with no
 * current item at all: a reader who opened a request could not see which section
 * they were in.
 *
 * This is asserted per destination, because the failure was per destination - a
 * blanket "some item is current" would have passed before the fix on the list
 * pages, which is exactly what hid it.
 */
class StaffSidebarCurrentItemTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The page under test, and the sidebar label that must light on it.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function pageProvider(): array
    {
        return [
            // The list page itself.
            'verifications list' => ['staff.verifications.index', 'Resident Verification'],
            'verifications detail' => ['staff.verifications.show', 'Resident Verification'],
            'residents list' => ['staff.residents.index', 'Residents'],
            'residents detail' => ['staff.residents.show', 'Residents'],
            'requests list' => ['staff.requests.index', 'Document Requests'],
            'requests detail' => ['staff.requests.show', 'Document Requests'],
            'batches list' => ['staff.batches.index', 'Relief Distribution'],
            'batches form' => ['staff.batches.create', 'Relief Distribution'],
            'batches detail' => ['staff.batches.show', 'Relief Distribution'],
            'announcements list' => ['staff.announcements.index', 'Announcements'],
            'announcements form' => ['staff.announcements.create', 'Announcements'],
            'accounts list' => ['staff.accounts.index', 'User Accounts'],
            'accounts detail' => ['staff.accounts.show', 'User Accounts'],
            'kagawad relief list' => ['kagawad.relief', 'Relief Distribution'],
            'kagawad relief detail' => ['kagawad.relief.show', 'Relief Distribution'],
            'kagawad residents' => ['kagawad.residents.index', 'My Sector Residents'],
        ];
    }

    #[Test]
    #[DataProvider('pageProvider')]
    public function exactly_one_sidebar_item_is_marked_current(string $route, string $expected): void
    {
        $html = $this->actingAs($this->userFor($route))->get($this->urlFor($route))->assertOk()->getContent();

        // Counted inside <aside> only. The breadcrumb and the phone bottom bar each
        // mark the current page too, and all three are correct.
        $aside = $this->asideOf($html);

        $this->assertSame(
            1,
            substr_count($aside, 'aria-current="page"'),
            "Exactly one sidebar item should be current on [{$route}].",
        );

        $this->assertStringContainsString(
            $expected,
            $this->textAroundCurrentItem($aside),
            "The item lit on [{$route}] should be [{$expected}].",
        );
    }

    #[Test]
    public function the_phone_bottom_bar_marks_the_current_page_too(): void
    {
        $html = $this->actingAs($this->userFor('staff.requests.index'))
            ->get($this->urlFor('staff.requests.index'))
            ->assertOk()
            ->getContent();

        // The bar is built from the same list, so it must not fall out of step.
        $this->assertSame(
            1,
            substr_count($this->sectionOf($html, 'aria-label="Portal"'), 'aria-current="page"'),
            'The phone bottom bar should mark exactly one current destination.',
        );
    }

    #[Test]
    public function the_account_link_stays_current_across_both_tabs(): void
    {
        $admin = User::factory()->admin()->create(['is_verified' => true]);

        foreach (['account.edit', 'account.password'] as $route) {
            $html = $this->actingAs($admin)->get(route($route))->assertOk()->getContent();

            $this->assertStringContainsString(
                'My Account',
                $this->textAroundCurrentItem($this->asideOf($html)),
                "[{$route}] should still show My Account as current.",
            );
        }
    }

    // -----------------------------------------------------------------

    private function userFor(string $route): User
    {
        if (str_starts_with($route, 'kagawad.')) {
            $kagawad = User::factory()->kagawad()->create(['is_verified' => true]);
            $sector = Sector::factory()->create(['kagawad_user_id' => $kagawad->getKey()]);
            $batch = ReliefBatch::factory()->create();

            ReliefDistribution::factory()->create([
                'relief_batch_id' => $batch->getKey(),
                'sector_id' => $sector->getKey(),
            ]);

            return $kagawad;
        }

        return User::factory()->admin()->create(['is_verified' => true]);
    }

    private function urlFor(string $route): string
    {
        return match ($route) {
            'staff.verifications.show' => route($route, ResidentProfile::factory()->create()),
            'staff.residents.show' => route($route, ResidentProfile::factory()->create()),
            'staff.requests.show' => route($route, DocumentRequest::factory()->create()),
            'staff.batches.show' => route($route, ReliefBatch::factory()->create()),
            'staff.accounts.show' => route($route, User::factory()->create()),
            'kagawad.relief.show' => route($route, ReliefBatch::first() ?? ReliefBatch::factory()->create()),
            default => route($route),
        };
    }

    /**
     * The sidebar <aside> block, so the breadcrumb and the bottom bar are excluded.
     */
    private function asideOf(string $html): string
    {
        $start = strpos($html, '<aside');
        $end = $start === false ? false : strpos($html, '</aside>', $start);

        $this->assertNotFalse($start, 'No <aside> found - the staff sidebar is missing.');
        $this->assertNotFalse($end, 'The <aside> is not closed.');

        return substr($html, $start, $end - $start);
    }

    private function sectionOf(string $html, string $marker): string
    {
        $start = strpos($html, $marker);

        if ($start === false) {
            return '';
        }

        $end = strpos($html, '</nav>', $start);

        return $end === false ? '' : substr($html, $start, $end - $start);
    }

    /**
     * The visible label of the link carrying aria-current, so the assertion is
     * about what a reader sees rather than about markup order.
     */
    private function textAroundCurrentItem(string $markup): string
    {
        if (preg_match('#<a\b[^>]*aria-current="page"[^>]*>(.*?)</a>#s', $markup, $m) !== 1) {
            return '';
        }

        $label = preg_replace('/<svg\b.*?<\/svg>/s', '', $m[1]) ?? $m[1];

        return trim(preg_replace('/\s+/', ' ', strip_tags($label)) ?? '');
    }
}
