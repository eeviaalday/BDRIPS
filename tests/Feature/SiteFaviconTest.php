<?php

namespace Tests\Feature;

use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * One favicon, on every page, declared exactly once.
 *
 * There are five documents in this application that open a <head>: the public
 * layout (which also carries every resident page), the guest layout, the staff
 * layout (staff and Kagawad), and login and register, which are whole documents
 * of their own. All five include one shared partial, so the favicon cannot be
 * right on four pages and missing on the fifth.
 *
 * THE ASSERTION IS "EXACTLY ONE", NOT "AT LEAST ONE". A favicon is a case where
 * two declarations is a bug and not a redundancy: the browser picks one, the
 * other is dead markup, and which one it picks is not something a reader can
 * see. A test that only checked "a rel=icon is present" would pass on a page
 * declaring it three times.
 *
 * public/favicon.ico exists in this repository and is ZERO BYTES, which is why
 * this also asserts the PNG is really served rather than merely linked: a
 * <link> to a file that is not there renders a broken tab icon with no error
 * anywhere, and an empty .ico is exactly the kind of thing that hides it.
 */
class SiteFaviconTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One page per area, each as the role that can actually open it.
     *
     * Every area is here because every area renders a different document, and a
     * favicon test that only walks the public pages proves nothing about the
     * staff shell.
     *
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function pages(): array
    {
        return [
            'public home' => [null, 'home'],
            'guest login' => [null, 'login'],
            'guest register' => [null, 'register'],
            'resident' => ['resident', 'resident.requests.index'],
            'kagawad' => ['kagawad', 'kagawad.dashboard'],
            'secretary' => ['secretary', 'staff.dashboard'],
            'admin' => ['admin', 'staff.dashboard'],
        ];
    }

    #[Test]
    #[DataProvider('pages')]
    public function every_page_declares_exactly_one_icon(?string $role, string $route): void
    {
        $html = $this->area($role, $route);
        $icons = $this->iconLinks($html);

        $this->assertCount(
            1,
            $icons,
            "The [{$route}] page declares ".count($icons).' favicon links; exactly one is allowed.'
        );

        $this->assertStringContainsString(
            'favicon.png',
            $icons[0],
            "The [{$route}] page points its icon at something other than favicon.png: {$icons[0]}"
        );
    }

    /**
     * The link is a PNG, and it is cache-busted from the file's own mtime.
     */
    #[Test]
    #[DataProvider('pages')]
    public function the_icon_is_a_png_with_a_cache_busting_version(?string $role, string $route): void
    {
        $document = $this->document($this->area($role, $route));
        $link = $this->iconNodes($document)[0];

        $this->assertInstanceOf(DOMElement::class, $link);
        $this->assertSame('image/png', $link->getAttribute('type'));

        $this->assertMatchesRegularExpression(
            '/^'.preg_quote(asset('favicon.png'), '/').'\?v=\d+$/',
            $link->getAttribute('href'),
            'The icon URL is not cache-busted from the file mtime.'
        );
    }

    /**
     * The apple-touch-icon is declared once too.
     *
     * Only because the logo is square — see the dimensions assertion below.
     */
    #[Test]
    #[DataProvider('pages')]
    public function every_page_declares_exactly_one_apple_touch_icon(?string $role, string $route): void
    {
        $document = $this->document($this->area($role, $route));
        $apples = (new DOMXPath($document))->query('//head//link[@rel="apple-touch-icon"]');

        $this->assertSame(
            1,
            $apples->length,
            "The [{$route}] page declares {$apples->length} apple-touch-icons."
        );

        $apple = $apples->item(0);
        $this->assertInstanceOf(DOMElement::class, $apple);
        $this->assertStringContainsString('apple-touch-icon.png', $apple->getAttribute('href'));
    }

    /**
     * The file is really there, and really the logo.
     *
     * Asserted against public/images/Zone 1_logo.png, which stays exactly where
     * it was: it is not renamed, moved or deleted, and the sidebar, the top bar
     * and both auth pages still read it from there.
     */
    #[Test]
    public function the_icon_files_exist_and_are_the_logo_itself(): void
    {
        $this->assertFileExists(public_path('favicon.png'), 'public/favicon.png is missing.');

        $logo = public_path('images/Zone 1_logo.png');
        $this->assertFileExists($logo, 'The original logo has moved.');

        // Byte-identical, so nothing was cropped, rescaled or re-encoded.
        $this->assertSame(
            md5_file($logo),
            md5_file(public_path('favicon.png')),
            'public/favicon.png is not a byte-identical copy of the logo.'
        );

        // Square, which is the condition for reusing it as an apple-touch-icon
        // without distorting it.
        $size = getimagesize(public_path('favicon.png'));
        $this->assertNotFalse($size, 'favicon.png is not a readable image.');
        $this->assertSame($size[0], $size[1], 'The logo is not square, so it must not be reused uncropped.');

        $this->assertFileExists(public_path('apple-touch-icon.png'));
    }

    /**
     * Nothing else in the application declares an icon.
     *
     * A second declaration anywhere — a page component, a shared partial, a
     * Blade push — is how a page ends up with two icons, and it would not be
     * caught by any per-page assertion above if that page is not in the
     * provider. So the whole view tree is swept rather than the seven pages.
     */
    #[Test]
    public function no_other_view_declares_an_icon(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            // The shared partial is the one place allowed to declare them.
            // Compared by directory name rather than by a path suffix, because
            // getPathname() hands back whichever separator the platform uses and
            // a suffix written with '/' does not match on Windows — which would
            // make this test fail against itself instead of against a real
            // second icon.
            if (basename($file) === 'favicon.blade.php' && basename(dirname($file)) === 'partials') {
                continue;
            }

            $source = (string) file_get_contents($file);

            if (preg_match('/rel="(icon|shortcut icon|apple-touch-icon)"/', $source) === 1) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Another view declares a favicon; only partials/favicon.blade.php may: '.implode(', ', $offenders)
        );
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    /**
     * @return list<string>
     */
    private function bladeFiles(): array
    {
        $files = [];
        $directory = new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS);

        foreach (new \RecursiveIteratorIterator($directory) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * The href of every rel="icon" link in the head.
     *
     * Matched on rel's space-separated semantics — rel="icon", rel="shortcut
     * icon", rel="ICON" — because an equality test would not find a page that
     * declared rel="shortcut icon".
     *
     * @return list<string>
     */
    private function iconLinks(string $html): array
    {
        return array_map(
            fn (DOMElement $link): string => $link->getAttribute('href'),
            $this->iconNodes($this->document($html)),
        );
    }

    /**
     * @return list<DOMElement>
     */
    private function iconNodes(DOMDocument $document): array
    {
        $found = [];

        foreach ((new DOMXPath($document))->query('//head//link') as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            foreach (preg_split('/\s+/', strtolower(trim($link->getAttribute('rel')))) ?: [] as $token) {
                if ($token === 'icon' || $token === 'shortcut') {
                    $found[] = $link;
                    break;
                }
            }
        }

        return $found;
    }

    private function document(string $html): DOMDocument
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return $document;
    }

    /**
     * The rendered HTML of one page, as the role that can actually open it.
     *
     * One method rather than one per area, because the point of the provider is
     * that all seven go through the same path, and any per-area branching here
     * would be a place for them to stop agreeing.
     */
    private function area(?string $role, string $route): string
    {
        if ($role === null) {
            return $this->get(route($route))->assertOk()->getContent();
        }

        return $this->actingAs($this->userFor($role))->get(route($route))->assertOk()->getContent();
    }

    private function userFor(string $role): User
    {
        return match ($role) {
            'kagawad' => $this->kagawad(),
            'admin' => User::factory()->admin()->create(['is_verified' => true]),
            'secretary' => User::factory()->secretary()->create(['is_verified' => true]),
            default => $this->resident(),
        };
    }

    /**
     * A Kagawad holding a sector.
     *
     * An unassigned one is redirected off every Kagawad page, so the assertion
     * would never run against a real document.
     */
    private function kagawad(): User
    {
        $sector = Sector::first() ?? Sector::factory()->create();
        $kagawad = User::factory()->kagawad()->create(['is_verified' => true]);
        $sector->update(['kagawad_user_id' => $kagawad->id]);

        return $kagawad;
    }

    /**
     * A resident with the profile the portal expects.
     *
     * The profile is not optional: /resident/requests reads the household number
     * off it, and a resident account without one is not a state the application
     * can actually be in.
     */
    private function resident(): User
    {
        $resident = User::factory()->resident()->create(['is_verified' => true]);
        ResidentProfile::factory()->create(['user_id' => $resident->id]);

        return $resident;
    }
}
