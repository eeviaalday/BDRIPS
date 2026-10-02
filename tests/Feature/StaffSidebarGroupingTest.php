<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RoleNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Which sidebar items sit under which heading, per role.
 *
 * M1/M2 in docs/STAFF_UI_AUDIT.md: `availableSidebarGroupsFor()` folded every
 * group that was not `main` into a single `account` bucket, discarding the
 * `group => 'admin'` that RoleNavigation declares on the Captain's four
 * destinations, and the view then drew one hard-coded heading for it. The visible
 * result was User Accounts, Sectors & Kagawad, Settings and Audit Logs sitting
 * under a heading that said "My Account".
 *
 * The fix has two halves and this file holds both shut, because either alone would
 * leave the same bug reachable:
 *
 *  - the buckets are separate, so the four land in `admin` rather than `account`;
 *  - the view draws a heading per bucket.
 *
 * Testing only the first would pass while the view still printed one heading, and
 * testing only the second would pass while the buckets were still merged. So these
 * assertions read the rendered DOM - the heading a reader sees, and the links
 * beneath it - rather than the array RoleNavigation returns.
 *
 * A heading is expected only where the role has more than one group, which is why
 * a Secretary and a Kagawad have NO heading over their workspace links: labelling
 * a lone group "Workspace" would add a heading that distinguishes nothing, and the
 * brief asked for their grouping to stay as it was.
 */
class StaffSidebarGroupingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * role => [the rendered groups, in order]
     *
     * Each group is [heading, items]; a null heading means those links are drawn
     * with no heading above them.
     *
     * @return array<string, array{0: string, 1: list<array{0: ?string, 1: list<string>}>}>
     */
    public static function groupingProvider(): array
    {
        return [
            'admin' => [
                User::ROLE_ADMIN,
                [
                    ['Workspace', ['Dashboard', 'Resident Verification', 'Residents', 'Document Requests', 'Relief Distribution', 'Announcements']],
                    ['Administration', ['User Accounts', 'Sectors & Kagawad', 'Settings', 'Audit Logs']],
                    ['My Account', ['My Account']],
                ],
            ],
            'secretary' => [
                User::ROLE_SECRETARY,
                [
                    [null, ['Dashboard', 'Resident Verification', 'Residents', 'Document Requests', 'Relief Distribution', 'Announcements']],
                    ['My Account', ['My Account']],
                ],
            ],
            'kagawad' => [
                User::ROLE_KAGAWAD,
                [
                    [null, ['Dashboard', 'Relief Distribution', 'My Sector Residents']],
                    ['My Account', ['My Account']],
                ],
            ],
        ];
    }

    /**
     * @param  list<array{0: ?string, 1: list<string>}>  $expected
     */
    #[Test]
    #[DataProvider('groupingProvider')]
    public function the_sidebar_groups_read_as_the_specification(string $role, array $expected): void
    {
        $html = $this->renderSidebar($role);

        $this->assertSame(
            $expected,
            $this->groupsIn($html),
            "The [{$role}] sidebar does not group its destinations as specified."
        );
    }

    /**
     * The Captain's four must never be under "My Account" again.
     *
     * Asserted on the specific pairing rather than only by the whole-shape
     * comparison above, because that is the exact regression: four admin
     * destinations under a heading about one's own account.
     */
    #[Test]
    public function no_admin_destination_sits_under_my_account(): void
    {
        $underAccount = $this->itemsUnder(
            $this->groupsIn($this->renderSidebar(User::ROLE_ADMIN)),
            'My Account',
        );

        foreach (['User Accounts', 'Sectors & Kagawad', 'Settings', 'Audit Logs'] as $label) {
            $this->assertNotContains(
                $label,
                $underAccount,
                "[{$label}] is under the My Account heading, which is the M1/M2 regression."
            );
        }
    }

    /**
     * My Account is one item under its own heading, for every role.
     *
     * The trap is a heading that names a section while listing the section
     * *before* it, which reads correctly as a list and is wrong as a layout.
     */
    #[Test]
    #[DataProvider('allRolesProvider')]
    public function the_my_account_group_holds_exactly_the_account_link(string $role): void
    {
        $items = $this->itemsUnder($this->groupsIn($this->renderSidebar($role)), 'My Account');

        $this->assertSame(
            ['My Account'],
            $items,
            "[{$role}] should show exactly the one My Account link under that heading."
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function allRolesProvider(): array
    {
        return [
            'admin' => [User::ROLE_ADMIN],
            'secretary' => [User::ROLE_SECRETARY],
            'kagawad' => [User::ROLE_KAGAWAD],
        ];
    }

    /**
     * The roles that must NOT be offered the Administration group.
     *
     * Split out from allRolesProvider because the Admin is the one role that is
     * supposed to have it: a test asserting its absence cannot be run against the
     * Admin without failing, and would push someone into deleting the very group
     * M1/M2 is about.
     *
     * @return array<string, array{0: string}>
     */
    public static function nonAdminRolesProvider(): array
    {
        return [
            'secretary' => [User::ROLE_SECRETARY],
            'kagawad' => [User::ROLE_KAGAWAD],
        ];
    }

    /**
     * A group is only drawn when it has something in it.
     *
     * Without this, an empty Administration div would still render a heading on a
     * Secretary's sidebar - an empty section that says "Administration" and offers
     * nothing, which is worse than no heading.
     *
     * Non-Admin roles only: the Admin is supposed to have this group.
     */
    #[Test]
    #[DataProvider('nonAdminRolesProvider')]
    public function no_heading_is_drawn_for_a_group_with_nothing_in_it(string $role): void
    {
        $this->assertSame(
            [],
            $this->itemsUnder($this->groupsIn($this->renderSidebar($role)), 'Administration'),
            "[{$role}] has no Administration destinations, so it must not draw that heading."
        );

        // And nothing was drawn empty: the word is nowhere in the sidebar markup.
        $this->assertStringNotContainsStringIgnoringCase(
            'Administration',
            $this->sidebarNav($this->renderSidebar($role)),
            "[{$role}] renders an Administration group with nothing in it."
        );
    }

    /**
     * The three groups are still three in RoleNavigation, before any rendering.
     *
     * The view could be fixed while the data stayed merged, in which case the
     * headings would appear but the destinations would be in the wrong bucket. This
     * is the half that is not visible in the DOM.
     */
    #[Test]
    public function the_admin_group_is_its_own_bucket_and_not_folded_into_account(): void
    {
        $groups = RoleNavigation::availableSidebarGroupsFor(User::ROLE_ADMIN);

        $this->assertSame(
            ['staff.accounts.index', 'staff.sectors.index', 'staff.settings.index', 'staff.audit.index'],
            array_column($groups['admin'], 'route'),
            'The Captain group must survive as its own bucket.'
        );

        $this->assertSame(
            ['account.edit'],
            array_column($groups['account'], 'route'),
            'The account bucket must hold only the account link.'
        );

        $this->assertCount(6, $groups['main'], 'The workspace bucket should hold the six shared destinations.');
    }

    /**
     * A Secretary and a Kagawad declare no admin group at all.
     */
    #[Test]
    #[DataProvider('nonAdminRolesProvider')]
    public function only_the_admin_has_an_administration_bucket(string $role): void
    {
        $groups = RoleNavigation::availableSidebarGroupsFor($role);

        $this->assertSame([], $groups['admin'], "[{$role}] should have no Administration destinations.");
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    /**
     * The rendered groups as an ordered list of [heading, items] pairs.
     *
     * Read with DOMXPath over the sidebar's own <nav>, so this is the structure a
     * reader sees. A heading of null is what an unlabelled group looks like in the
     * DOM.
     *
     * Returned as a list of pairs rather than heading => items on purpose. Two
     * unlabelled groups would collide on one key and the second would silently
     * replace the first, which is a helper that reports "one group" for a sidebar
     * that has two.
     *
     * @return list<array{0: ?string, 1: list<string>}>
     */
    private function groupsIn(string $html): array
    {
        $nav = $this->sidebarNav($html);

        if ($nav === '') {
            return [];
        }

        $out = [];

        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$nav);
        $xpath = new \DOMXPath($document);

        $out = [];

        foreach ($xpath->query('//nav[@aria-label="Portal"]/div') as $div) {
            $links = [];

            foreach ($xpath->query('.//a', $div) as $a) {
                $label = $this->labelOf($a);

                if ($label !== '') {
                    $links[] = $label;
                }
            }

            if ($links === []) {
                continue;
            }

            $heading = $xpath->query('./p', $div)->item(0);

            $out[] = [$heading === null ? null : $this->textOf($heading), $links];
        }

        return $out;
    }

    /**
     * The items under one heading, or [] when that heading is not on the page.
     *
     * A heading that is absent and a heading present with nothing under it are the
     * same thing as far as a reader is concerned, so both answer [] and both fail
     * the assertion that expects the group to exist.
     *
     * @param  list<array{0: ?string, 1: list<string>}>  $groups
     * @return list<string>
     */
    private function itemsUnder(array $groups, string $heading): array
    {
        foreach ($groups as [$groupHeading, $items]) {
            if ($groupHeading === $heading) {
                return $items;
            }
        }

        return [];
    }

    /**
     * A link's own label, with the count badge excluded.
     *
     * The badge lives inside the <a>, so reading the link's whole text makes
     * "Sectors & Kagawad 17" out of a link labelled "Sectors & Kagawad" showing 17
     * unassigned sectors. Asserting on the label span is what the brief describes;
     * asserting on the anchor text would bake a live count into every expectation
     * and then fail the day the fixture changes.
     */
    private function labelOf(\DOMElement $link): string
    {
        $xpath = new \DOMXPath($link->ownerDocument);

        $label = $xpath->query('./span[contains(@class,"truncate")]', $link)->item(0);

        return $label === null ? '' : $this->textOf($label);
    }

    /**
     * The desktop sidebar's nav markup, excluding the phone bottom bar.
     *
     * Both are `<nav aria-label="Portal">`, so the sidebar is the first.
     */
    private function sidebarNav(string $html): string
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new \DOMXPath($document);

        $nav = $xpath->query('//nav[@aria-label="Portal"]')->item(0);

        return $nav === null ? '' : (string) $document->saveHTML($nav);
    }

    /**
     * Visible text of a node, icons removed.
     */
    private function textOf(\DOMNode $node): string
    {
        $html = (string) $node->ownerDocument->saveHTML($node);
        $html = preg_replace('#<svg\b.*?</svg>#s', '', $html) ?? $html;
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
    }

    private function renderSidebar(string $role): string
    {
        $user = match ($role) {
            User::ROLE_ADMIN => User::factory()->admin()->create(['is_verified' => true]),
            User::ROLE_KAGAWAD => User::factory()->kagawad()->create(['is_verified' => true]),
            default => User::factory()->secretary()->create(['is_verified' => true]),
        };

        $home = $role === User::ROLE_KAGAWAD ? 'kagawad.dashboard' : 'staff.dashboard';

        return $this->actingAs($user)->get(route($home))->assertOk()->getContent();
    }
}
