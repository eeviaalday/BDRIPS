<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The five Admin-only pages: who may reach them, and what they now do.
 *
 * ROLES.md does not exist in this repository. Every permission assertion here is
 * therefore made against the route middleware, which is the thing that actually
 * refuses a request, and the route table is quoted in the test so a change to it
 * shows up as a failure rather than as a passing test that has quietly stopped
 * testing anything.
 *
 * The second half of the file is about behaviour the rebuild had to get right, and
 * most of it is about things a markup assertion cannot see: a query count, an
 * Alpine expression that is inert, an attribute that the browser throws away.
 */
class AdminOnlyPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The five pages, with the middleware that guards each.
     *
     * @return array<string, array{0: string}>
     */
    public static function adminPageProvider(): array
    {
        return [
            'user accounts' => ['staff.accounts.index'],
            'sectors and kagawad' => ['staff.sectors.index'],
            'settings' => ['staff.settings.index'],
            'audit logs' => ['staff.audit.index'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminRoleProvider(): array
    {
        return [
            'secretary' => [User::ROLE_SECRETARY],
            'kagawad' => [User::ROLE_KAGAWAD],
            'resident' => [User::ROLE_RESIDENT],
        ];
    }

    // ---------------------------------------------------------- who may reach

    #[Test]
    #[DataProvider('adminPageProvider')]
    public function an_admin_reaches_every_admin_page(string $route): void
    {
        $this->actingAs($this->admin())->get(route($route))->assertOk();
    }

    #[Test]
    #[DataProvider('nonAdminRoleProvider')]
    public function a_non_admin_is_refused_every_admin_page(string $role): void
    {
        $user = User::factory()->create(['role' => $role, 'is_verified' => true]);

        if ($role === User::ROLE_RESIDENT) {
            ResidentProfile::factory()->create([
                'user_id' => $user->id,
                'sector_id' => Sector::factory()->create()->id,
            ]);
        }

        foreach (self::adminPageProvider() as [$page]) {
            $this->actingAs($user)
                ->get(route($page))
                ->assertForbidden();
        }
    }

    #[Test]
    public function a_non_admin_is_refused_the_admin_writes_too(): void
    {
        $secretary = User::factory()->create(['role' => User::ROLE_SECRETARY, 'is_verified' => true]);
        $account = User::factory()->create(['role' => User::ROLE_KAGAWAD, 'is_verified' => true]);
        $sector = Sector::factory()->create();

        $this->actingAs($secretary)->put(route('staff.accounts.role', $account), ['role' => 'admin'])->assertForbidden();
        $this->actingAs($secretary)->post(route('staff.accounts.restrict', $account), [
            'reason' => 'a reason long enough to pass validation',
        ])->assertForbidden();
        $this->actingAs($secretary)->post(route('staff.sectors.store'), ['sector_name' => 'X'])->assertForbidden();
        $this->actingAs($secretary)->put(route('staff.sectors.kagawad', $sector), [
            'kagawad_user_id' => $account->id,
        ])->assertForbidden();
        $this->actingAs($secretary)->delete(route('staff.sectors.destroy', $sector))->assertForbidden();
        $this->actingAs($secretary)->put(route('staff.settings.update'), [
            'values' => ['barangay_name' => 'Hijacked'],
        ])->assertForbidden();
    }

    #[Test]
    public function the_middleware_on_these_routes_is_still_role_admin_alone(): void
    {
        // Quoted rather than assumed. Every assertion above depends on this, and a
        // test that reads the guard back out of the route table fails loudly if
        // someone widens the group instead of quietly passing.
        $routes = collect(app('router')->getRoutes())->filter(
            fn ($route) => str_starts_with($route->uri(), 'staff/')
                && in_array($route->getName(), [
                    'staff.accounts.index', 'staff.sectors.index', 'staff.settings.index',
                    'staff.audit.index',
                ], true)
        );

        // Four, not five: the fifth was the System Status page, which has been
        // removed. Asserted as a count so a route silently disappearing is a
        // failure here rather than a quiet reduction in coverage.
        $this->assertCount(4, $routes, 'One of the four admin pages has no route.');

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains('role:admin', $middleware, "[{$route->getName()}] is not role:admin.");
            $this->assertNotContains('role:admin,secretary', $middleware, "[{$route->getName()}] widened to secretary.");
        }
    }

    // ------------------------------------------------------------- accounts

    #[Test]
    public function the_role_cards_count_every_user_whatever_the_filter(): void
    {
        $this->accounts();

        foreach (['', '?role=secretary', '?q=a'] as $query) {
            $html = $this->actingAs($this->admin())
                ->get(route('staff.accounts.index').$query)
                ->assertOk()
                ->getContent();

            $this->assertSame(
                $this->roleCounts(),
                $this->cards($html),
                "The cards changed under [{$query}]. They must count the whole system, not the filtered list."
            );
        }
    }

    #[Test]
    public function the_role_cards_are_links_that_filter(): void
    {
        $this->accounts();
        $html = $this->actingAs($this->admin())->get(route('staff.accounts.index'))->getContent();

        foreach (array_keys($this->roleCounts()) as $role) {
            $this->assertStringContainsString(
                route('staff.accounts.index', ['role' => $role]),
                $html,
                "No card links to the {$role} filter."
            );
        }
    }

    #[Test]
    public function the_active_role_card_is_marked_and_the_others_are_not(): void
    {
        $this->accounts();

        $xpath = $this->dom($this->actingAs($this->admin())
            ->get(route('staff.accounts.index', ['role' => 'secretary']))
            ->getContent());

        $current = $xpath->query('//a[@aria-current="true"]');

        $this->assertGreaterThan(0, $current->length, 'No card is marked as the active filter.');
        $this->assertSame(1, $current->length, 'More than one card claims to be the active filter.');
        $active = $current->item(0);
        $this->assertInstanceOf(DOMElement::class, $active);
        $this->assertStringContainsString('?role=secretary', (string) $active->getAttribute('href'));
    }

    #[Test]
    public function the_default_filter_hides_nobody(): void
    {
        $this->accounts();

        $response = $this->actingAs($this->admin())->get(route('staff.accounts.index'));

        $response->assertOk();
        $this->assertSame(
            User::query()->count(),
            $response->viewData('users')->total(),
            'The unfiltered default is not showing every account.'
        );
        $this->assertStringContainsString('All roles', $response->getContent());
    }

    #[Test]
    public function verification_is_a_badge_for_residents_and_a_dash_for_staff(): void
    {
        $this->accounts();

        $xpath = $this->dom($this->actingAs($this->admin())
            ->get(route('staff.accounts.index'))
            ->getContent());

        foreach ($xpath->query('//tbody/tr') as $row) {
            $this->assertInstanceOf(DOMElement::class, $row);

            $cells = $row->getElementsByTagName('td');
            // The first cell is a <th scope=row>, so td[0] is Role and td[1] is
            // Verification, not td[2].
            $roleCell = trim($cells->item(0)?->textContent ?? '');
            $verificationCell = $cells->item(1);

            $this->assertInstanceOf(DOMElement::class, $verificationCell);

            if (str_contains($roleCell, 'Resident')) {
                $this->assertStringNotContainsString(
                    '—',
                    $verificationCell->textContent,
                    'A resident row shows a dash instead of a verification badge.'
                );
            } else {
                $this->assertStringContainsString(
                    '—',
                    $verificationCell->textContent,
                    "A {$roleCell} row shows a verification badge, which is not a state that applies to it."
                );
            }
        }
    }

    #[Test]
    public function the_account_detail_page_has_the_three_cards(): void
    {
        $account = $this->accounts();
        $html = $this->actingAs($this->admin())->get(route('staff.accounts.show', $account))->getContent();

        $this->assertStringContainsString('Profile', $html);
        $this->assertStringContainsString('Role &amp; access', $html);
        $this->assertStringContainsString('Recent activity', $html);
    }

    #[Test]
    public function the_destructive_actions_are_behind_a_confirmation(): void
    {
        $account = User::factory()->create(['role' => User::ROLE_KAGAWAD, 'is_verified' => true]);
        $html = $this->actingAs($this->admin())->get(route('staff.accounts.show', $account))->getContent();

        // Restrict and role change both open a dialog rather than posting.
        $this->assertStringContainsString('open-modal', $html);
        $this->assertStringContainsString('role="dialog"', $html, 'No confirmation dialog on the page.');

        // And each states its consequence in words, not just "Are you sure?".
        $this->assertMatchesRegularExpression(
            '/keeps its password|keep their password/i',
            $html,
            'The restriction dialog does not say what a restriction actually does.'
        );
    }

    #[Test]
    public function an_admin_still_cannot_lock_themselves_out(): void
    {
        // Unchanged behaviour, pinned because the detail page was rebuilt around
        // dialogs and it would be easy to expose a control that the server refuses.
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('staff.accounts.role', $admin), ['role' => User::ROLE_SECRETARY])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->post(route('staff.accounts.restrict', $admin), ['reason' => 'a reason long enough to pass'])
            ->assertStatus(422);

        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
    }

    // -------------------------------------------------------------- sectors

    #[Test]
    public function the_sectors_page_is_a_table_not_a_stack_of_forms(): void
    {
        // No sectors are added: a migration seeds the barangay's seventeen, which
        // is the number this screen has to be usable at.
        $this->assertSame(17, Sector::query()->count(), 'The migration no longer seeds 17 sectors.');

        $xpath = $this->dom($this->actingAs($this->admin())->get(route('staff.sectors.index'))->getContent());

        $this->assertSame(1, $xpath->query('//table')->length, 'The sectors list is not a table.');
        $this->assertSame(1, $xpath->query('//thead')->length);

        // One visible row per sector, plus its collapsed detail row.
        $this->assertSame(34, $xpath->query('//tbody/tr')->length);

        $headers = [];
        foreach ($xpath->query('//thead//th') as $th) {
            $headers[] = trim($th->textContent);
        }

        $this->assertSame(['Sector', 'Households', 'Kagawad', 'Actions'], $headers);
    }

    #[Test]
    public function editing_is_behind_a_per_row_manage_disclosure_that_is_keyboard_reachable(): void
    {
        $xpath = $this->dom($this->actingAs($this->admin())->get(route('staff.sectors.index'))->getContent());

        // Collected in PHP: an attribute name containing a colon cannot be written
        // in an XPath predicate, because XPath reads "x-on" as a namespace prefix.
        $buttons = [];

        foreach ($xpath->query('//button') as $button) {
            $this->assertInstanceOf(DOMElement::class, $button);

            if (str_contains((string) $button->getAttribute('x-on:click'), 'managing')) {
                $buttons[] = $button;
            }
        }

        $this->assertCount(17, $buttons, 'One Manage control per sector expected.');

        foreach ($buttons as $button) {
            $this->assertInstanceOf(DOMElement::class, $button);

            // A real <button>, so it is tabbable and answers Enter and Space.
            $this->assertSame('button', $button->tagName);
            // A binding expression in the source, not a rendered "false": Alpine
            // writes the real value at runtime.
            $this->assertSame('managing', $button->getAttribute('x-bind:aria-expanded'));
            $this->assertNotSame('', (string) $button->getAttribute('aria-controls'));
        }
    }

    #[Test]
    public function each_row_owns_its_own_scope(): void
    {
        $html = $this->actingAs($this->admin())->get(route('staff.sectors.index'))->getContent();

        $expected = Sector::query()->count();

        // The detail row is a SIBLING of the visible row, so the scope has to be on
        // a <tbody> that legally contains both. With it on the <tr>, every row threw
        // "managing is not defined" and the disclosure was dead.
        $this->assertSame($expected, substr_count($html, '<tbody x-data="{ managing: false }">'));
        $this->assertSame(0, substr_count($html, '<tr x-data='), 'A scope is on a <tr> again, where the sibling detail row cannot see it.');
    }

    #[Test]
    public function the_query_count_does_not_grow_with_the_number_of_sectors(): void
    {
        $queriesWith = function (int $n): int {
            $this->sectors($n);

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin())->get(route('staff.sectors.index'))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $small = $queriesWith(1);
        $this->refreshDatabase();
        $large = $queriesWith(17);

        $this->assertSame($small, $large, "Sectors page issued {$large} queries with 17 sectors and {$small} with 1.");
    }

    #[Test]
    public function delete_is_disabled_with_its_reason_still_in_the_markup(): void
    {
        $occupied = Sector::factory()->create(['sector_name' => 'Occupied']);
        ResidentProfile::factory()->create(['sector_id' => $occupied->id]);
        Sector::factory()->create(['sector_name' => 'Empty']);

        $html = $this->actingAs($this->admin())->get(route('staff.sectors.index'))->getContent();

        // The existing rule and the existing wording.
        $this->assertStringContainsString('Cannot delete while it has households or relief entries', $html);
        // A <span> styled as disabled, with the reason in the markup rather than
        // only in a title attribute a keyboard never reaches.
        $this->assertStringContainsString('cursor-not-allowed', $html);
        $this->assertStringContainsString('aria-describedby', $html);
    }

    #[Test]
    public function the_delete_rule_is_still_enforced_by_the_server(): void
    {
        $occupied = Sector::factory()->create(['sector_name' => 'Occupied']);
        ResidentProfile::factory()->create(['sector_id' => $occupied->id]);

        $this->actingAs($this->admin())
            ->delete(route('staff.sectors.destroy', $occupied))
            ->assertRedirect();

        $this->assertSame(1, Sector::where('sector_name', 'Occupied')->count());
    }

    #[Test]
    public function the_header_figures_and_the_unassigned_filter_are_present(): void
    {
        $kagawad = User::factory()->kagawad()->create();
        Sector::factory()->create(['kagawad_user_id' => $kagawad->id]);
        Sector::factory()->create(['kagawad_user_id' => null]);

        $html = $this->actingAs($this->admin())->get(route('staff.sectors.index'))->getContent();

        $this->assertStringContainsString('Total sectors', $html);
        $this->assertStringContainsString('With a Kagawad', $html);
        $this->assertStringContainsString('Unassigned', $html);
        $this->assertStringContainsString('Unassigned only', $html);
        $this->assertStringContainsString('Kagawad without a sector', $html);
    }

    #[Test]
    public function the_unassigned_filter_narrows_the_list(): void
    {
        $kagawad = User::factory()->kagawad()->create();
        Sector::factory()->create(['sector_name' => 'Assigned', 'kagawad_user_id' => $kagawad->id]);
        Sector::factory()->create(['sector_name' => 'Nobody here', 'kagawad_user_id' => null]);

        $html = $this->actingAs($this->admin())
            ->get(route('staff.sectors.index', ['unassigned' => 1]))
            ->getContent();

        $this->assertStringContainsString('Nobody here', $html);
        $this->assertStringNotContainsString('Assigned', $html);
    }

    // ------------------------------------------------------------- settings

    #[Test]
    public function the_settings_form_still_writes_the_same_keys(): void
    {
        Setting::query()->delete();
        foreach ([
            ['barangay_name', 'Barangay Linasin', 'header'],
            ['barangay_address', 'Zone 1, Bangued, Abra', 'header'],
            ['office_email', 'a@b.test', 'header'],
        ] as [$key, $value, $group]) {
            Setting::query()->create(['key' => $key, 'value' => $value, 'group' => $group]);
        }

        $this->actingAs($this->admin())->put(route('staff.settings.update'), [
            'values' => ['barangay_name' => 'Barangay Bayabas', 'office_email' => 'c@d.test'],
        ])->assertRedirect();

        $this->assertSame('Barangay Bayabas', Setting::get('barangay_name'));
        $this->assertSame('c@d.test', Setting::get('office_email'));
        // Untouched key keeps its value.
        $this->assertSame('Zone 1, Bangued, Abra', Setting::get('barangay_address'));
        // And the group column was not rewritten by the save.
        $this->assertSame(['header'], Setting::query()->distinct()->pluck('group')->all());
    }

    #[Test]
    public function the_settings_page_does_not_rename_or_drop_a_key(): void
    {
        $this->seedSettings();

        $expected = [
            'barangay_name', 'barangay_address', 'punong_barangay', 'secretary_name',
            'emergency_hotline', 'office_phone', 'office_email', 'office_facebook',
        ];

        $html = $this->actingAs($this->admin())->get(route('staff.settings.index'))->getContent();

        foreach ($expected as $key) {
            $this->assertStringContainsString('values['.$key.']', $html, "The [{$key}] field is gone.");
        }

        // The group column is untouched: the sections are a view over the keys.
        $this->assertSame(['header'], Setting::query()->distinct()->pluck('group')->all());
    }

    #[Test]
    public function the_settings_sections_are_in_the_required_order(): void
    {
        $this->seedSettings();

        $html = $this->actingAs($this->admin())->get(route('staff.settings.index'))->getContent();

        $identity = strpos($html, 'values[barangay_name]');
        $address = strpos($html, 'values[barangay_address]');
        $hotline = strpos($html, 'values[emergency_hotline]');
        $email = strpos($html, 'values[office_email]');
        $facebook = strpos($html, 'values[office_facebook]');

        // Barangay Name first, then Address.
        $this->assertLessThan($address, $identity, 'Barangay Name is not before Address.');
        // And the contact group after identity.
        $this->assertLessThan($hotline, $address);
        $this->assertLessThan($email, $hotline);
        $this->assertLessThan($facebook, $email);

        $this->assertStringContainsString('Barangay identity', $html);
        $this->assertStringContainsString('Contact', $html);
    }

    #[Test]
    public function the_settings_page_has_the_dirty_bar_and_the_read_only_preview(): void
    {
        $this->seedSettings();

        $html = $this->actingAs($this->admin())->get(route('staff.settings.index'))->getContent();

        $this->assertStringContainsString('You have unsaved changes', $html);
        $this->assertStringContainsString('Discard', $html);
        $this->assertStringContainsString('Preview on public header', $html);
        $this->assertStringContainsString('Read-only', $html);

        // The bar is hidden until the form is dirty, and is driven by a handler
        // assigning a plain property. A getter, or a method called from x-show,
        // does not re-run in this Alpine build - see the note in the view.
        $this->assertMatchesRegularExpression('/x-show="dirty"/', $html);
        $this->assertMatchesRegularExpression('/x-on:input="check\(\)"/', $html);
        $this->assertStringNotContainsString('get dirty', $html);

        /*
         * $root, not $el.
         *
         * Inside a method reached from x-on, `this` is the scope proxy and its $el
         * is the element the handler was bound to - the Discard button, which holds
         * no inputs. check() then compared an empty list and reported not dirty, and
         * discard() wrote nothing, so the bar hid while the field kept the edited
         * value. Measured: zero writes to the field's value while dirty went false.
         * $root is the component's own element and does not move with the handler.
         */
        $this->assertStringContainsString('this.$root', $html);
        $this->assertStringNotContainsString('this.$el', $html, 'A method bound with x-on must reach the form through $root, not $el.');
    }

    #[Test]
    public function the_settings_preview_shows_the_saved_values_not_the_inputs(): void
    {
        Setting::query()->delete();
        Setting::query()->create(['key' => 'barangay_name', 'value' => 'Published Name', 'group' => 'header']);
        Setting::query()->create(['key' => 'barangay_address', 'value' => 'Published Address', 'group' => 'header']);

        $html = $this->actingAs($this->admin())->get(route('staff.settings.index'))->getContent();

        $this->assertStringContainsString('Published Name', $html);
        $this->assertStringContainsString('Published Address', $html);
    }

    #[Test]
    public function the_date_filters_are_wide_enough_to_show_a_whole_dd_mm_yyyy_date(): void
    {
        // <input type="date"> is a fixed-format control: it never wraps, and it
        // ellipsises the moment its box is too narrow. Asserted on the class rather
        // than on a rendered width so it holds without a browser, and so a
        // regression to flex-1 - which is what let the "To" box grow to 875px and
        // take a whole row - cannot come back unnoticed.
        $xpath = $this->dom($this->actingAs($this->admin())->get(route('staff.audit.index'))->getContent());

        foreach (['from', 'to'] as $name) {
            $input = $xpath->query('//input[@name="'.$name.'"]')->item(0);
            $this->assertInstanceOf(DOMElement::class, $input, "The [{$name}] date filter is gone.");

            $wrapper = $input->parentNode;
            $this->assertInstanceOf(DOMElement::class, $wrapper);

            $classes = (string) $wrapper->getAttribute('class');
            $this->assertMatchesRegularExpression(
                '/min-w-\[9\.5rem\]/',
                $classes,
                "The [{$name}] filter has no min width, so it can be squeezed below a full date."
            );

            // And it must not be allowed to grow either, for the same reason in reverse.
            $this->assertStringNotContainsString('flex-1', $classes, "The [{$name}] filter can grow to fill a row.");
        }
    }

    // ---------------------------------------------------------------- audit

    #[Test]
    public function the_audit_footer_counts_entries(): void
    {
        AuditLog::factory()->count(3)->create();

        $html = $this->actingAs($this->admin())->get(route('staff.audit.index'))->getContent();

        $this->assertStringContainsString('entries', $html);
        $this->assertStringContainsString('Showing', $html);
    }

    #[Test]
    public function the_audit_action_badge_is_coloured_by_family_and_says_what_happened(): void
    {
        AuditLog::factory()->create(['action' => 'relief.received', 'description' => 'marked']);
        AuditLog::factory()->create(['action' => 'user.restricted', 'description' => 'locked']);

        $xpath = $this->dom($this->actingAs($this->admin())->get(route('staff.audit.index'))->getContent());

        $badges = $xpath->query('//tbody/tr/td[3]//span[contains(@class,"rounded-full") and not(@aria-hidden)]');

        $this->assertGreaterThanOrEqual(2, $badges->length);

        $texts = [];
        foreach ($badges as $badge) {
            $this->assertInstanceOf(DOMElement::class, $badge);
            $texts[] = trim($badge->textContent);

            // Colour is never the only cue: a dot and a word as well.
            $this->assertNotSame('', trim($badge->textContent));
            $this->assertGreaterThan(0, $xpath->query('.//span[@aria-hidden="true"]', $badge)->length, 'The badge has no dot, so colour is its only cue.');
        }

        // Membership, not position: the log is newest first, so which of the two
        // lands first depends on the order the factory happened to run in.
        $this->assertContains('Relief marked received', $texts, 'The relief action is not badged with its own wording.');
        // A restriction is red whatever family it belongs to.
        $this->assertContains('Account restricted', $texts);
    }

    #[Test]
    public function the_audit_context_is_shown_as_key_value_pairs_when_it_is_flat(): void
    {
        AuditLog::factory()->create([
            'action' => 'user.role_changed',
            'description' => 'role changed',
            'context' => ['from' => 'secretary', 'to' => 'resident'],
        ]);

        $html = $this->actingAs($this->admin())->get(route('staff.audit.index'))->getContent();

        $this->assertStringContainsString('From', $html);
        $this->assertStringContainsString('secretary', $html);
        $this->assertStringNotContainsString('&quot;from&quot;', $html, 'The context is still raw JSON.');
    }

    #[Test]
    public function a_nested_audit_context_keeps_the_json(): void
    {
        AuditLog::factory()->create([
            'action' => 'settings.updated',
            'description' => 'settings',
            'context' => ['nested' => ['a' => 1]],
        ]);

        $html = $this->actingAs($this->admin())->get(route('staff.audit.index'))->getContent();

        // Flattening a nested map would misreport it, so it stays JSON.
        $this->assertStringContainsString('&quot;nested&quot;', $html);
    }

    #[Test]
    public function the_audit_log_offers_no_way_to_change_an_entry(): void
    {
        $html = $this->actingAs($this->admin())->get(route('staff.audit.index'))->getContent();

        $this->assertStringNotContainsString('staff.audit.update', $html);
        $this->assertStringNotContainsString('staff.audit.destroy', $html);
        $this->assertStringNotContainsString('method="DELETE"', $html);
        $this->assertStringNotContainsString('_method" value="DELETE"', $html);
    }

    // -------------------------------------------------- F: the admin strip

    #[Test]
    public function the_admin_strip_links_to_all_four_admin_pages(): void
    {
        $this->sectors(2);
        AuditLog::factory()->count(2)->create();

        $html = $this->actingAs($this->admin())->get(route('staff.dashboard'))->getContent();

        $xpath = $this->dom($html);

        /*
         * All four, and the name says so.
         *
         * This test was called the_admin_strip_links_to_the_five_admin_pages while
         * asserting three, so Sectors & Kagawad and Settings were reachable only by
         * typing a URL or from the sidebar, and the suite was green over the gap.
         * It then said five while the strip had five cards, one of which linked to
         * the System Status page. That page has been removed, so the fifth card and
         * this assertion both went with it - the name is corrected rather than left
         * claiming a count the page no longer has.
         *
         * The routes are asserted by name, and the strip's own anchors are counted,
         * so a fifth card or a dropped link both fail rather than passing quietly.
         */
        foreach ([
            'staff.audit.index',
            'staff.accounts.index',
            'staff.sectors.index',
            'staff.settings.index',
        ] as $route) {
            $this->assertStringContainsString(route($route), $html, "The admin strip does not link to {$route}.");
        }

        $strip = $xpath->query('//section[@aria-labelledby="admin-strip"]//a');
        $this->assertSame(4, $strip->length, 'The admin strip does not have one card per admin page.');

        // The strip reports; it never posts. Every link is a GET destination.
        foreach ($strip as $link) {
            $this->assertInstanceOf(DOMElement::class, $link);
            $this->assertStringStartsWith(url('/staff/'), (string) $link->getAttribute('href'));
        }
    }

    #[Test]
    public function a_secretary_still_does_not_see_the_admin_strip(): void
    {
        $html = $this->actingAs(User::factory()->secretary()->create())
            ->get(route('staff.dashboard'))
            ->getContent();

        $this->assertStringNotContainsString('For admins', $html);

        // And no Admin-only destination leaks through the dashboard. /staff/audit is
        // the one the strip used to link besides the removed status page, so it is
        // the one worth naming.
        $this->assertStringNotContainsString(route('staff.audit.index'), $html);
    }

    // ------------------------------------------------ Alpine attribute traps

    #[Test]
    public function no_admin_page_has_a_double_quote_inside_an_alpine_attribute(): void
    {
        // The trap that cost the most time on the settings page: a double quote
        // inside a double-quoted attribute ENDS the attribute, so a JS comment
        // containing x-show="revision > 0" silently truncated the x-data object
        // and the save bar's method never existed.
        foreach ($this->adminPages() as [$url, $name]) {
            $xpath = $this->dom($this->actingAs($this->admin())->get($url)->getContent());

            foreach ($xpath->query('//*') as $element) {
                if (! $element instanceof DOMElement) {
                    continue;
                }

                foreach ($element->attributes as $attribute) {
                    if (! str_starts_with($attribute->name, 'x-')) {
                        continue;
                    }

                    $this->assertStringNotContainsString(
                        '*/',
                        $attribute->value,
                        "[{$name}] has an unterminated comment inside {$attribute->name}."
                    );
                    $this->assertStringNotContainsString(
                        '/*',
                        $attribute->value,
                        "[{$name}] has a comment inside {$attribute->name}, which Alpine cannot evaluate."
                    );
                }
            }
        }
    }

    #[Test]
    public function every_admin_page_renders_without_a_javascript_error(): void
    {
        // Not proof on its own - the browser run is - but a broken x-data object
        // is visible here as a missing piece of the page, which is a cheap guard
        // against reintroducing one.
        foreach ($this->adminPages() as [$url, $name]) {
            $html = $this->actingAs($this->admin())->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('{--', $html, "[{$name}] rendered a Blade comment.");
            // Blade's :attr shorthand is a COMPONENT feature. On a plain element it
            // is emitted verbatim and a browser drops it without a word.
            $this->assertStringNotContainsString(' :aria-', $html, "[{$name}] has a component-only attribute shorthand on a plain element.");
        }
    }

    // --------------------------------------------------------------- helpers

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function adminPages(): array
    {
        $pages = [];

        foreach (self::adminPageProvider() as $name => [$route]) {
            $pages[] = [route($route), $name];
        }

        $account = User::factory()->create(['role' => User::ROLE_KAGAWAD, 'is_verified' => true]);
        $pages[] = [route('staff.accounts.show', $account), 'account detail'];

        return $pages;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_verified' => true]);
    }

    /**
     * One user per role, so every card has something to count.
     */
    private function accounts(): User
    {
        $last = null;

        foreach (User::roles() as $index => $role) {
            $user = User::factory()->create([
                'role' => $role,
                'name' => 'Person '.$index,
                'is_verified' => true,
            ]);

            if ($role === User::ROLE_RESIDENT) {
                ResidentProfile::factory()->create([
                    'user_id' => $user->id,
                    'sector_id' => Sector::factory()->create()->id,
                ]);
            }

            $last = $user;
        }

        return $last;
    }

    /**
     * The eight seeded keys, which a seeder creates and RefreshDatabase does not.
     */
    private function seedSettings(): void
    {
        foreach ([
            'barangay_name' => 'Barangay Linasin',
            'barangay_address' => 'Zone 1, Bangued, Abra',
            'punong_barangay' => 'Hon. Punong Barangay',
            'secretary_name' => 'Barangay Secretary',
            'emergency_hotline' => '09171234567',
            'office_phone' => '09171234567',
            'office_email' => 'office@bdrpis.test',
            'office_facebook' => 'https://facebook.com/bdrpis',
        ] as $key => $value) {
            Setting::query()->create(['key' => $key, 'value' => $value, 'group' => 'header']);
        }
    }

    /**
     * @return array<string, int>
     */
    private function roleCounts(): array
    {
        $counts = [];

        foreach (User::roles() as $role) {
            $counts[$role] = User::query()->where('role', $role)->count();
        }

        return $counts;
    }

    /**
     * The role cards as role => count, read out of the rendered page.
     *
     * @return array<string, int>
     */
    private function cards(string $html): array
    {
        $xpath = $this->dom($html);
        $cards = [];

        foreach ($xpath->query('//nav[@aria-label="Filter accounts by role"]//a') as $anchor) {
            $this->assertInstanceOf(DOMElement::class, $anchor);

            $this->assertInstanceOf(DOMElement::class, $anchor);
            $value = $anchor->getElementsByTagName('p')->item(0)?->textContent;
            $label = trim($anchor->getElementsByTagName('p')->item(1)?->textContent ?? '');

            // "All roles" is the unfiltered card, not a fifth role.
            if ($label === '' || $value === null || strtolower($label) === 'all roles') {
                continue;
            }

            $cards[strtolower($label)] = (int) $value;
        }

        return $cards;
    }

    private function sectors(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Sector::factory()->create(['sector_name' => 'Sector '.$i]);
        }
    }

    private function dom(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
