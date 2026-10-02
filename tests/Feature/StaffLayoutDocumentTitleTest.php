<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The staff shell's document title, and the heading it does not print.
 *
 * F3 in docs/STAFF_UI_AUDIT.md: every staff page rendered "BDRPIS - BDRPIS"
 * because AppLayout never declared a $title property. In a class component, an
 * undeclared attribute stays in the attribute bag and never reaches the view, so
 * layouts/app's `{{ $title ?? 'BDRPIS' }}` always took the fallback.
 *
 * The fix has a trap on the other side, and this file exists to hold both halves
 * shut at once. layouts/app also had an `@isset($title) <h1>` block that was dead
 * code for exactly as long as $title was always null. Making $title arrive would
 * have switched that block on and given all twenty-two staff pages a second
 * <h1>, so the block was removed. Asserting the title alone would not notice that
 * regression; asserting the h1 count alone would not notice the title coming back.
 */
class StaffLayoutDocumentTitleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every staff and kagawad screen, with the title it is expected to carry.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function staffPageProvider(): array
    {
        return [
            'staff dashboard' => ['staff.dashboard', 'Dashboard', 'admin'],
            'verifications queue' => ['staff.verifications.index', 'Resident Verification', 'admin'],
            'residents directory' => ['staff.residents.index', 'Residents', 'admin'],
            'requests queue' => ['staff.requests.index', 'Document Requests', 'admin'],
            'batches list' => ['staff.batches.index', 'Relief Distribution', 'admin'],
            'announcements' => ['staff.announcements.index', 'Announcements', 'admin'],
            'accounts' => ['staff.accounts.index', 'User Accounts', 'admin'],
            'sectors' => ['staff.sectors.index', 'Sectors & Kagawad', 'admin'],
            'settings' => ['staff.settings.index', 'Settings', 'admin'],
            'audit' => ['staff.audit.index', 'Audit Logs', 'admin'],
            'status' => ['staff.status.index', 'System Status', 'admin'],
            'kagawad dashboard' => ['kagawad.dashboard', 'Dashboard', 'kagawad'],
            'kagawad relief' => ['kagawad.relief', 'Relief Distribution', 'kagawad'],
            'kagawad residents' => ['kagawad.residents.index', 'My Sector Residents', 'kagawad'],
        ];
    }

    #[Test]
    #[DataProvider('staffPageProvider')]
    public function a_staff_page_puts_its_own_name_in_the_document_title(string $route, string $expected, string $role): void
    {
        $html = $this->asRole($role)->get(route($route))->assertOk()->getContent();

        // Matched against the escaped form, because that is what the page has to
        // contain: "Sectors & Kagawad" must arrive as "Sectors &amp; Kagawad" or it
        // is a raw ampersand in the document head.
        $needle = htmlspecialchars($expected, ENT_QUOTES);

        $this->assertMatchesRegularExpression(
            '#<title>[^<]*'.preg_quote($needle, '#').'[^<]*</title>#',
            $html,
            "[{$route}] did not put [{$expected}] in its <title>.",
        );
    }

    #[Test]
    #[DataProvider('staffPageProvider')]
    public function no_staff_page_is_still_titled_bdrpis_bdrpis(string $route, string $expected, string $role): void
    {
        $html = $this->asRole($role)->get(route($route))->assertOk()->getContent();

        $this->assertStringNotContainsString(
            '<title>BDRPIS - BDRPIS</title>',
            $html,
            "[{$route}] is still titled 'BDRPIS - BDRPIS'.",
        );
    }

    /**
     * The other half of the same fix.
     *
     * Staff pages own their heading, so the layout must not add one. This is the
     * assertion that fails if someone restores the removed @isset($title) block
     * while fixing something else.
     */
    #[Test]
    #[DataProvider('staffPageProvider')]
    public function a_staff_page_has_exactly_one_h1_and_the_layout_adds_none(string $route, string $expected, string $role): void
    {
        $html = $this->asRole($role)->get(route($route))->assertOk()->getContent();

        // Some pages declare one h1 per branch, but only one branch renders.
        $this->assertSame(
            1,
            substr_count($html, '<h1'),
            "[{$route}] should render exactly one <h1> - the layout must not add a second.",
        );
    }

    #[Test]
    public function the_account_pages_title_themselves_too(): void
    {
        /*
         * /account is the trap called out in staff-polish.md: one route, two
         * layouts. The staff branch goes through AppLayout and is in scope here;
         * the resident branch belongs to the public layout and is not asserted.
         *
         * Both tabs carry the same document title because account/edit-staff is one
         * view that includes the shared details partial, and the title is declared
         * once above the include. That is deliberate and correct - both tabs are the
         * My Account screen - so this asserts the shared title rather than the
         * heading of whichever tab happens to be open. The tab-specific heading is
         * the partial's business, and the partial is do-not-edit.
         */
        foreach (['account.edit', 'account.password'] as $route) {
            $html = $this->asRole('admin')->get(route($route))->assertOk()->getContent();

            $this->assertMatchesRegularExpression(
                '#<title>[^<]*My Account[^<]*</title>#',
                $html,
                "[{$route}] did not title itself.",
            );
        }
    }

    private function asRole(string $role): static
    {
        $user = match ($role) {
            'kagawad' => User::factory()->kagawad()->create(['is_verified' => true]),
            'secretary' => User::factory()->secretary()->create(['is_verified' => true]),
            default => User::factory()->admin()->create(['is_verified' => true]),
        };

        return $this->actingAs($user);
    }
}
