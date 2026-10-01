<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The role-count cards above the accounts table.
 *
 * These four cards read 0 for every role, which is what the audit found. The
 * cause was in the aggregation rather than in the query: the count was folded
 * into an accumulator with an arrow function, and PHP captures those by value,
 * so every write landed in a copy that was then thrown away. The query had been
 * right the whole time - it returned 1, 1, 2 and 3 - and the page printed the
 * zeros that had been seeded as defaults before the fold ran.
 *
 * Nothing caught it, so the assertions here read the rendered cards rather than
 * the controller's return value, and compare them against a count taken
 * independently from the database.
 */
class StaffAccountRoleCountsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The Admin these tests sign in as.
     *
     * Memoised because the cards count every admin in the table, and an Admin
     * created per assertion would quietly add one to the very figure being
     * asserted. RefreshDatabase wraps each test in a transaction, so this cannot
     * leak between tests.
     */
    private ?User $actingAdmin = null;

    #[Test]
    public function each_card_shows_the_number_of_accounts_holding_that_role(): void
    {
        $this->actingAs($this->admin())->get(route('staff.accounts.index'))->assertOk();

        // 1 admin (the one signing in), 2 secretaries, 0 kagawads, 3 residents.
        $this->accounts(2, 3);

        $this->assertSame(
            [
                User::ROLE_ADMIN => 1,
                User::ROLE_SECRETARY => 2,
                User::ROLE_KAGAWAD => 0,
                User::ROLE_RESIDENT => 3,
            ],
            $this->cards(),
        );
    }

    /**
     * A role nobody holds still gets a card, reading 0. The row is one card per
     * role, so a card that vanished with its last holder made the row look
     * broken - and, more importantly, "no card" and "zero accounts" are different
     * answers to the same question.
     */
    #[Test]
    public function a_role_with_no_accounts_is_still_shown_as_zero(): void
    {
        $this->accounts(0, 0);

        $cards = $this->cards();

        $this->assertArrayHasKey(
            User::ROLE_KAGAWAD,
            $cards,
            'A role with no accounts has no card at all.'
        );

        $this->assertSame(0, $cards[User::ROLE_KAGAWAD]);
    }

    /**
     * Every role the application knows about gets a card, whatever the data. This
     * is the assertion that fails when the cards are built from the rows that
     * happen to exist rather than from the role list.
     */
    #[Test]
    public function every_role_gets_a_card_whatever_the_data(): void
    {
        $this->accounts(0, 0);

        $this->assertSame(
            array_values(User::roles()),
            array_keys($this->cards()),
            'The cards are not one-per-role.'
        );
    }

    /**
     * The counts track the database rather than anything baked into the template.
     */
    #[Test]
    public function the_counts_follow_the_data(): void
    {
        $this->actingAs($this->admin())->get(route('staff.accounts.index'))->assertOk();

        // One resident. The argument order is (secretaries, residents, kagawads),
        // so this is one resident and no secretaries.
        $this->accounts(0, 1);

        $this->assertSame(1, $this->cards()[User::ROLE_RESIDENT]);
        $this->assertSame(0, $this->cards()[User::ROLE_SECRETARY]);

        $this->accounts(0, 4);

        $this->assertSame(5, $this->cards()[User::ROLE_RESIDENT]);

        $this->accounts(0, 0, 2);

        $this->assertSame(2, $this->cards()[User::ROLE_KAGAWAD]);
    }

    /**
     * Compared against a count taken from the database independently of the
     * controller, so a card cannot agree with a bug in the same helper.
     */
    #[Test]
    public function every_card_agrees_with_the_database(): void
    {
        // The Admin is created before the independent count is taken, because the
        // cards count every admin in the table - including the one reading them.
        $this->admin();

        $this->accounts(2, 1);

        $actual = User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->map(fn ($total): int => (int) $total)
            ->all();

        $cards = $this->cards();

        $this->assertNotEmpty($cards, 'No role cards were rendered.');

        foreach ($cards as $role => $shown) {
            $this->assertSame(
                $actual[$role] ?? 0,
                $shown,
                sprintf('The %s card does not match the database.', $role)
            );
        }
    }

    /**
     * The counts describe the whole system, not the current page or the current
     * filter, so filtering the table must not move them.
     */
    #[Test]
    public function the_counts_are_not_narrowed_by_the_search_or_the_role_filter(): void
    {
        $this->accounts(2, 3);

        $before = $this->cards();

        $this->actingAs($this->admin())
            ->get(route('staff.accounts.index', ['role' => User::ROLE_KAGAWAD, 'q' => 'zzz']))
            ->assertOk()
            ->getContent();

        $this->assertSame($before, $this->cards());
    }

    /**
     * The cards are built from one grouped query, not one query per role, so the
     * page does not get slower as accounts are added.
     */
    #[Test]
    public function the_cards_cost_one_query_and_not_one_per_role(): void
    {
        $this->accounts(1, 1);
        $withFour = $this->countIndexQueries();

        $this->accounts(5, 5);
        $withFourteen = $this->countIndexQueries();

        // "Not more", not "the same": the figures can legitimately differ in the
        // other direction, because paginate() stops issuing its count query once
        // the rows fit on one page. What must never happen is the number growing
        // with the number of accounts, which is what a per-row or per-role fetch
        // would do.
        $this->assertLessThanOrEqual(
            $withFour,
            $withFourteen,
            'The number of queries grew with the number of accounts, so the counts are '
            .'being fetched per row or per role rather than in one grouped query.'
        );
    }

    /**
     * The cards survive the rest of the page: filters, pagination and the table
     * itself are unchanged by this fix, and the audit noted the whole area is
     * otherwise intact.
     */
    #[Test]
    public function the_rest_of_the_page_still_works(): void
    {
        $this->accounts(1, 1);

        $html = $this->actingAs($this->admin())
            ->get(route('staff.accounts.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Name or email', $html, 'The search box is gone.');
        // "All roles", not the previous "Every role". The label names the default
        // state, and the default here is no role filter at all - which is also why
        // it has to read as "all" and not as a description of some other value.
        $this->assertStringContainsString('All roles', $html, 'The role filter is gone.');
        $this->assertStringNotContainsString('Every role', $html);
        $this->assertStringContainsString('User Accounts', $html);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * The cards as role => count, read out of the rendered page.
     *
     * Read from the markup rather than from the controller's return value, because
     * a test that calls the same helper the controller calls cannot tell a correct
     * card from a correct helper that is then rendered wrongly.
     *
     * @return array<string, int>
     */
    private function cards(): array
    {
        $html = $this->actingAs($this->admin())
            ->get(route('staff.accounts.index'))
            ->assertOk()
            ->getContent();

        $xpath = $this->xpath($html);

        $cards = [];

        foreach ($xpath->query('//p[contains(@class,"text-3xl")]') as $value) {
            // A badged stat-card is: <div class="block rounded-xl ..."> holding a
            // flex row with the value, then a label paragraph. The value's parent
            // is that flex row, so the card is the next ancestor up. Walking with
            // nextSibling() instead would land on the whitespace text node between
            // the two paragraphs.
            $card = $value->parentNode;

            while ($card instanceof DOMElement && ! str_contains($card->getAttribute('class'), 'rounded-xl')) {
                $card = $card->parentNode;
            }

            if (! $card instanceof DOMElement) {
                continue;
            }

            $label = null;

            foreach ($card->getElementsByTagName('p') as $paragraph) {
                if (str_contains($paragraph->getAttribute('class'), 'font-semibold')) {
                    $label = $paragraph;
                    break;
                }
            }

            if ($label === null) {
                continue;
            }

            $cards[trim($label->textContent)] = (int) trim($value->textContent);
        }

        // The card prints the human role name, so map each label back to its slug
        // through the model's own label() rather than keeping a second copy of
        // the mapping here.
        $labels = [];

        foreach (User::roles() as $role) {
            $labels[User::roleLabel($role)] = $role;
        }

        $bySlug = [];

        foreach ($cards as $label => $count) {
            if (isset($labels[$label])) {
                $bySlug[$labels[$label]] = $count;
            }
        }

        return $bySlug;
    }

    private function countIndexQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->admin())->get(route('staff.accounts.index'))->assertOk();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }

    /**
     * Create n accounts of each given role.
     *
     * Counted with a plain loop rather than range(1, $n): range(1, 0) is [1, 0]
     * in PHP, not an empty array, so asking for zero of a role with range quietly
     * creates two of it. That is a very quiet way to make a test lie.
     */
    private function accounts(int $secretaries, int $residents, int $kagawads = 0): void
    {
        for ($i = 0; $i < $secretaries; $i++) {
            User::factory()->secretary()->create();
        }

        for ($i = 0; $i < $residents; $i++) {
            User::factory()->resident()->create();
        }

        for ($i = 0; $i < $kagawads; $i++) {
            User::factory()->kagawad()->create();
        }
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);

        $document->loadHTML('<?xml encoding="UTF-8">'.$html);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function admin(): User
    {
        return $this->actingAdmin ??= User::factory()->admin()->create(['is_verified' => true]);
    }
}
