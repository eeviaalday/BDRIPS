<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the icon set's silent fallback.
 *
 * resources/views/components/icon.blade.php renders $paths[$name] ?? $paths['document'].
 * An unknown name is therefore not an error: it quietly draws the document
 * glyph. A profile menu item asking for a "settings" icon that nobody had added
 * yet would have shipped looking like it offered a document, and nothing in the
 * suite or in the application log would have said so.
 *
 * These tests close that hole by resolving every literal icon name used in a
 * view against the keys the component actually defines.
 */
class IconNameResolvesTest extends TestCase
{
    /**
     * The keys declared in the icon component's $paths array.
     *
     * @return array<int, string>
     */
    private function declaredIcons(): array
    {
        $source = (string) file_get_contents(
            resource_path('views'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'icon.blade.php')
        );

        preg_match_all("/^\s*'([a-z0-9-]+)'\s*=>/m", $source, $matches);

        return $matches[1];
    }

    /**
     * @return \Iterator<int, \SplFileInfo>
     */
    private function bladeFiles(): \Iterator
    {
        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );
    }

    /**
     * Every icon name a view asks for by literal.
     *
     * Covers both spellings: name="x" on <x-icon>, and 'icon' => 'x' for the
     * views that build icon lists in a @php block and pass them to :name.
     *
     * @return array<string, string> icon name => the file that asked for it
     */
    private function usedIcons(): array
    {
        $used = [];

        foreach ($this->bladeFiles() as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            preg_match_all('/<x-icon\b[^>]*?\bname="([a-z0-9-]+)"/', $contents, $attributes);
            preg_match_all("/'icon'\s*=>\s*'([a-z0-9-]+)'/", $contents, $array);

            foreach ([...$attributes[1], ...$array[1]] as $name) {
                $used[$name] ??= $file->getPathname();
            }
        }

        return $used;
    }

    #[Test]
    public function the_icon_set_is_not_empty(): void
    {
        $this->assertNotEmpty(
            $this->declaredIcons(),
            'No icons were parsed out of the icon component - the test below would pass vacuously.',
        );
    }

    #[Test]
    public function the_scan_actually_finds_views_and_icons(): void
    {
        // Without this the two tests above would both pass on an empty scan.
        // RecursiveDirectoryIterator::getExtension() returns "php" for a file
        // called icon.blade.php, so an extension check written against
        // "blade.php" matches nothing and reports a clean bill of health.
        $used = $this->usedIcons();

        $this->assertGreaterThan(
            10,
            count($used),
            'The view scan found almost no icon references, so it cannot be trusted to find a bad one.',
        );

        // A canary in a file the scan must reach. "check-circle" is asked for by
        // exactly one view, resources/views/public/contact.blade.php, so this
        // fails if the walk stops short of the public pages rather than merely
        // finding the handful of icons near the root of the tree.
        $this->assertArrayHasKey(
            'check-circle',
            $used,
            'The scan did not reach the public contact page, which is the case this test exists for.',
        );

        $this->assertSame(
            realpath(resource_path('views/public/contact.blade.php')),
            realpath($used['check-circle']),
            'The canary resolved from an unexpected view, so the scan is not reporting where it looked.',
        );
    }

    #[Test]
    public function every_icon_a_view_asks_for_actually_exists(): void
    {
        $declared = array_flip($this->declaredIcons());

        $missing = [];

        foreach ($this->usedIcons() as $name => $file) {
            if (! isset($declared[$name])) {
                $missing[$name] = $file;
            }
        }

        $this->assertSame(
            [],
            $missing,
            "These icon names are not in the set, so they render as the document glyph:\n"
            .collect($missing)->map(fn (string $file, string $name) => "  {$name}  ({$file})")
                ->implode("\n"),
        );
    }

    #[Test]
    public function the_resident_profile_menu_icons_all_exist(): void
    {
        // Named explicitly because this is the one place where an icon list is
        // assembled in a @php block and handed to :name, which the scan above
        // has to infer rather than read.
        foreach ([
            'home' => 'Dashboard',
            'id-badge' => 'My Profile',
            'user' => 'Edit Profile',
            'settings' => 'Account Settings',
            'lock' => 'Change Password',
            'shield' => 'Verification Status',
        ] as $icon => $label) {
            $this->assertContains(
                $icon,
                $this->declaredIcons(),
                "The \"{$label}\" profile menu item asks for the \"{$icon}\" icon, which does not exist.",
            );
        }
    }
}
