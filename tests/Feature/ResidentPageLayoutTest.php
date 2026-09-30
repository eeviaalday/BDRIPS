<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The resident area's own presentation, as distinct from its behaviour.
 *
 * Two things are checked here that the rest of the suite does not.
 *
 * The hero. Every resident page opens with the same x-page-header the public
 * inner pages use, and it has to sit OUTSIDE x-shell. x-shell is the padded,
 * max-width container, so a hero nested inside it renders as an inset rounded
 * box rather than the full-bleed band. The assertion is on the ordering of the
 * markup, because that is what the bug was: a page can use the right component
 * and still look wrong if the component is in the wrong place.
 *
 * The shared account partial. Its header is suppressed for residents, who get a
 * hero carrying the title instead, and left alone for everyone else. The staff
 * assertions are here because the flag is the kind of change that is invisible
 * in the resident screenshot and quietly breaks the Secretary's page.
 */
class ResidentPageLayoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Name, query parameters, and the title the hero must show.
     *
     * The parameters are separate because route() takes a name and an array; a
     * name with a "?" in it is not a route name.
     *
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: string}>
     */
    public static function residentPages(): array
    {
        return [
            'document requests' => ['resident.requests.index', [], 'My Document Requests'],
            'new request' => ['resident.requests.create', [], 'Request a Document'],
            'relief record' => ['resident.relief', [], 'My Relief Record'],
            'verification' => ['resident.verification', [], 'My Verification'],
            'verification tab' => ['account.edit', ['tab' => 'verification'], 'Verification Status'],
        ];
    }

    // ---------------------------------------------------------------------
    // The hero
    // ---------------------------------------------------------------------

    /**
     * The hero band comes before the content container, never inside it.
     *
     * Checked as string position rather than by looking for a class, because the
     * class is present either way - the difference is whether the surrounding
     * padded container is already open when the hero starts.
     */
    #[Test]
    #[DataProvider('residentPages')]
    public function the_hero_sits_outside_the_content_container(string $routeName, array $parameters): void
    {
        $html = $this->residentPage($routeName, $parameters);

        $hero = strpos($html, 'min-h-[300px]');
        $shell = strpos($html, 'pt-14 sm:pt-16 lg:pt-20');

        $this->assertNotFalse($hero, "{$routeName} has no hero band.");
        $this->assertNotFalse($shell, "{$routeName} has no content shell.");
        $this->assertLessThan(
            $shell,
            $hero,
            "{$routeName} renders its hero inside the content container, so it is an inset box."
        );
    }

    #[Test]
    #[DataProvider('residentPages')]
    public function every_resident_page_shows_its_title_in_the_hero(string $routeName, array $parameters, string $title): void
    {
        $this->assertStringContainsString($title, $this->residentPage($routeName, $parameters));
    }

    /**
     * The same class string the public pages use, so the two cannot drift. If
     * this fails after an edit to a resident page, the hero has been hand-rolled
     * rather than taken from the shared component.
     */
    #[Test]
    public function the_resident_hero_uses_the_public_hero_classes(): void
    {
        $resident = $this->residentPage('resident.requests.index');
        $public = $this->get(route('contact'))->assertOk()->getContent();

        foreach ([
            'relative isolate flex min-h-[300px] max-h-[360px] items-center overflow-hidden bg-slate-900',
            'object-cover object-[center_72%]',
            'from-slate-950/80 via-slate-900/60 to-transparent',
        ] as $signature) {
            $this->assertStringContainsString($signature, $public, 'The public hero changed.');
            $this->assertStringContainsString($signature, $resident, 'The resident hero no longer matches the public one.');
        }
    }

    /**
     * Home, then the page. The public breadcrumb marks only the last crumb
     * current, so a crumb cannot be announced as a link back to the page being
     * read.
     */
    #[Test]
    #[DataProvider('residentPages')]
    public function the_breadcrumb_runs_home_then_the_page(string $routeName, array $parameters): void
    {
        $html = $this->residentPage($routeName, $parameters);

        $this->assertStringContainsString('aria-label="Breadcrumb"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString(route('home'), $html);
    }

    /**
     * No action inside the hero. A banner carries the page's name and a sentence;
     * a button in it is a control the reader has to find before they know what
     * the page is for.
     */
    #[Test]
    public function the_hero_carries_no_button(): void
    {
        $html = $this->residentPage('resident.requests.index');

        $heroStart = strpos($html, 'min-h-[300px]');
        $heroEnd = strpos($html, '</section>', $heroStart);
        $hero = substr($html, $heroStart, $heroEnd - $heroStart);

        $this->assertStringNotContainsString('<button', $hero, 'The hero has a button in it.');
        $this->assertStringNotContainsString('New request', $hero);
    }

    /**
     * The action lives in the content instead, as a gold button at the right of
     * the section heading.
     */
    #[Test]
    public function the_new_request_action_sits_in_the_my_documents_section(): void
    {
        $html = $this->residentPage('resident.requests.index');

        $this->assertStringContainsString('My Documents', $html);
        $this->assertStringContainsString('+ New Request', $html);
        $this->assertStringContainsString(route('resident.requests.create'), $html);
    }

    /**
     * One h1 per page. The hero and a second heading would both be an h1, and a
     * page with two of them has an ambiguous title for a screen reader.
     */
    #[Test]
    #[DataProvider('residentPages')]
    public function each_resident_page_has_exactly_one_h1(string $routeName, array $parameters): void
    {
        $this->assertSame(
            1,
            substr_count($this->residentPage($routeName, $parameters), '<h1'),
            "{$routeName} does not have exactly one h1."
        );
    }

    // ---------------------------------------------------------------------
    // The shared account partial
    // ---------------------------------------------------------------------

    /**
     * The staff page keeps the header it has always had, in the staff shell, with
     * no hero. This is the assertion that the hideHeader flag did not leak out of
     * the resident wrapper - the change is invisible in a resident screenshot and
     * would otherwise only be caught by somebody noticing a missing heading.
     */
    #[Test]
    public function the_staff_account_page_keeps_its_own_header_and_gains_no_hero(): void
    {
        $html = $this->actingAs(User::factory()->secretary()->create(['is_verified' => true]))
            ->get(route('account.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('My Account', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'The staff page must keep exactly one h1.');
        $this->assertStringNotContainsString(
            'min-h-[300px]',
            $html,
            'The staff account page must not gain a resident hero.'
        );
        $this->assertStringContainsString(
            'Your role and verification status are set by the',
            $html,
            'The staff account page lost its explanatory header.'
        );
    }

    #[Test]
    public function the_staff_password_page_also_keeps_its_header(): void
    {
        $this->actingAs(User::factory()->admin()->create(['is_verified' => true]))
            ->get(route('account.password'))
            ->assertOk()
            ->assertSee('My Account');
    }

    /**
     * The resident page gets the hero, and exactly one h1, because the shared
     * header is suppressed for them.
     *
     * Asserted on the shared header's own h1 classes rather than its sentence,
     * because the hero description deliberately carries the same wording - a text
     * match would pass whether or not the header were still rendering.
     */
    #[Test]
    public function the_resident_account_page_has_a_hero_and_one_h1(): void
    {
        $html = $this->residentPage('account.edit');

        $this->assertStringContainsString('min-h-[300px]', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringNotContainsString(
            'text-2xl font-extrabold tracking-tight text-navy-900',
            $html,
            'The resident page still renders the shared in-shell header under the hero.'
        );
    }

    #[Test]
    public function the_resident_password_page_says_it_is_the_password_page(): void
    {
        // The old address now redirects to the tab, so the tab is what carries it.
        $this->assertStringContainsString('Change Password', $this->residentPage('account.edit', ['tab' => 'password']));
        $this->assertSame(1, substr_count($this->residentPage('account.edit', ['tab' => 'password']), '<h1'));
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function residentPage(string $routeName, array $parameters = []): string
    {
        $user = User::factory()->resident()->create([
            'is_verified' => true,
            'name' => 'Ana Dela Cruz',
        ]);

        $sector = Sector::factory()->create();

        $profile = ResidentProfile::factory()->create([
            'user_id' => $user->getKey(),
            'sector_id' => $sector->getKey(),
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        // The request list and its detail page both need one to exist.
        DocumentRequest::factory()->submitted()->create(['user_id' => $user->getKey()]);

        return $this->actingAs($user)
            ->get(route($routeName, $parameters))
            ->assertOk()
            ->getContent();
    }
}
