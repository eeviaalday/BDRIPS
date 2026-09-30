<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The login screen's fonts.
 *
 * This page is the one document in the portal that does not go through the Vite
 * bundle, and it used to pull Figtree from fonts.googleapis.com while every
 * other page self-hosted it. That made the login screen the only screen whose
 * typography depended on the network, which is exactly the sort of difference
 * that shows up on one machine and not another.
 *
 * These assertions are deliberately about the *absence* of the CDN link and the
 * presence of the local files. They will not catch a subtly wrong weight.
 */
class LoginFontDeliveryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_login_screen_does_not_reach_out_to_a_font_cdn(): void
    {
        $content = $this->get(route('login'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('fonts.googleapis.com', $content);
        $this->assertStringNotContainsString('fonts.gstatic.com', $content);
        $this->assertStringNotContainsString('fonts.googleapis', $content);
    }

    #[Test]
    public function the_login_screen_self_hosts_the_family_every_other_page_uses(): void
    {
        $content = $this->get(route('login'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("@font-face{font-family:'Figtree'", $content);

        // Every weight the page's own rules rely on has to be declared, or a
        // heading silently falls back to the system stack.
        foreach ([400, 500, 600, 700, 800] as $weight) {
            $this->assertStringContainsString(
                "font-weight:{$weight}",
                $content,
                "The login screen does not declare Figtree at weight {$weight}.",
            );
            $this->assertStringContainsString(
                "/fonts/figtree-latin-{$weight}.woff2",
                $content,
            );
        }
    }

    #[Test]
    public function the_local_font_files_really_exist(): void
    {
        // A self-hosted @font-face pointing at nothing is worse than a CDN: it
        // fails silently and the page looks wrong rather than obviously broken.
        foreach ([400, 500, 600, 700, 800] as $weight) {
            $this->assertFileExists(
                public_path("fonts/figtree-latin-{$weight}.woff2"),
            );
        }
    }

    #[Test]
    public function the_view_scan_really_walks_the_view_directory(): void
    {
        // getExtension() returns "php" for a file named login.blade.php, so an
        // extension check against "blade.php" matches nothing - and the scan
        // below would then pass on an empty list while claiming the codebase is
        // free of CDN links.
        $scanned = 0;
        $sawLogin = false;

        $views = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($views as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $scanned++;

            if ($file->getFilename() === 'login.blade.php') {
                $sawLogin = true;
            }
        }

        // A floor, not a count. The point is to fail if the walk silently
        // matches nothing, so the bound is set well below the number of views
        // the project actually has rather than pinned to it - it was lowered
        // when the resident and staff screens were removed, and pinning it to
        // the live total would make every future deletion a test failure.
        $this->assertGreaterThan(25, $scanned, 'The scan visited implausibly few Blade views.');
        $this->assertTrue($sawLogin, 'The scan never reached login.blade.php.');
    }

    #[Test]
    public function no_other_view_reaches_out_to_a_font_cdn(): void
    {
        $offenders = [];

        $views = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($views as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), 'fonts.googleapis.com')) {
                $offenders[] = $file->getFilename();
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These views still load a webfont from Google: '.implode(', ', $offenders),
        );
    }
}
