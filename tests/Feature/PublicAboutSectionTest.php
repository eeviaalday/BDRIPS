<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAboutSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_renders_the_banner_heading_and_its_breadcrumb(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        $this->assertStringContainsString('About Our Barangay', $content);

        // Inner pages now open with a breadcrumb trail under the banner title,
        // so the about page carries one and the trail ends on itself.
        $this->assertStringContainsString('aria-label="Breadcrumb"', $content);
        $this->assertStringContainsString('About Us', $content);
        $this->assertStringContainsString('href="'.route('home').'"', $content);
        $this->assertStringContainsString('aria-current="page"', $content);
    }

    public function test_about_has_its_own_route_rather_than_a_homepage_anchor(): void
    {
        // The section became a standalone page, so the homepage must link to the
        // route and must no longer carry the "about" anchor it used to define.
        $home = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(route('about'), $home);
        $this->assertStringNotContainsString(route('home').'#about', $home);
        $this->assertStringNotContainsString('id="about"', $home);
    }

    public function test_about_page_renders_history_vision_and_mission(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        foreach (['history', 'vision', 'mission'] as $key) {
            $block = config('bdrpis.about.'.$key);

            $this->assertStringContainsString($block['title'], $content);
            $this->assertStringContainsString($block['body'], $content);
        }

        $this->assertStringContainsString('R.A. 10173', $content);
    }

    public function test_about_page_renders_every_configured_demographic_figure(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        $this->assertStringContainsString(config('bdrpis.demographics.name'), $content);
        $this->assertStringContainsString(config('bdrpis.demographics.location'), $content);

        $this->assertCount(4, config('bdrpis.demographics.stats'));

        foreach (config('bdrpis.demographics.stats') as $stat) {
            $this->assertStringContainsString($stat['label'], $content);
            $this->assertStringContainsString($stat['value'], $content);
        }
    }

    public function test_about_page_uses_the_barangay_hall_photograph_with_a_specific_alt(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        $this->assertFileExists(public_path('images/barangay-hall.png'), 'The square hall photograph is missing from public/.');

        // Served from public/ with an alt that says what it is, which is what
        // carries the meaning now that the image is decorative. Its sizing is
        // asserted separately, where the reason for it is explained.
        $this->assertMatchesRegularExpression(
            '/<img src="'.preg_quote(asset('images/barangay-hall.png'), '/').'"\s+alt="The multi-purpose hall of Barangay Linasin Zone 1, where barangay services are delivered"/s',
            $content,
            'The history photograph is not the square hall image.',
        );

        $this->assertStringNotContainsString(
            'class="aspect-video w-full object-cover object-center"',
            $content,
            'The history photograph is still in the old 16:9 frame.',
        );
    }

    public function test_the_about_page_opens_with_the_overview_header(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        // Named for what the grid holds. The banner above already titles the
        // page "About Our Barangay", so repeating that here read as a duplicate.
        $this->assertMatchesRegularExpression(
            '/<p class="[^"]*tracking-\[0\.18em\][^"]*">\s*Overview\s*<\/p>\s*<h2[^>]*>\s*Barangay Profile &amp; Background\s*<\/h2>\s*<p class="[^"]*">\s*Discover the history, vision, mission, and key demographics of Barangay Linasin, Zone 1\.\s*<\/p>/s',
            $content,
            'The about page has no OVERVIEW / Barangay Profile & Background header.',
        );

        $this->assertStringNotContainsString('&amp;amp;', $content, 'The ampersand is double-encoded.');

        // And it sits above the history grid rather than after it.
        preg_match('/Barangay Profile &amp; Background\s*<\/h2>/', $content, $heading, PREG_OFFSET_CAPTURE);
        preg_match('/where barangay services are delivered/', $content, $grid, PREG_OFFSET_CAPTURE);

        $this->assertNotEmpty($heading, 'The overview header is missing from the page.');
        $this->assertNotEmpty($grid, 'The history grid is missing from the page.');
        $this->assertLessThan($grid[0][1], $heading[0][1], 'The overview header renders below the history grid.');
    }

    public function test_the_photograph_card_is_exactly_as_tall_as_the_picture(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        // Height comes from the file, capped, and object-cover crops a wider
        // picture into the frame rather than squashing it.
        $this->assertMatchesRegularExpression(
            '/<img src="'.preg_quote(asset('images/barangay-hall.png'), '/').'"[^>]*class="h-auto w-full max-h-80 object-cover object-center md:max-h-96"/s',
            $content,
            'The photograph no longer sizes itself from the file with a cap.',
        );

        // The row must not stretch its items: that is what left empty space
        // under the picture inside its border, because the history column beside
        // it is far taller.
        $this->assertStringContainsString('grid items-start gap-8 lg:grid-cols-12', $content);
        $this->assertStringNotContainsString('class="mt-12 grid gap-8 lg:grid-cols-12"', $content);

        $this->assertStringNotContainsString('aspect-video', $content);
    }

    public function test_the_history_vision_and_mission_blocks_keep_a_clear_hierarchy(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        foreach (['history', 'vision', 'mission'] as $key) {
            $block = config('bdrpis.about.'.$key);

            $this->assertMatchesRegularExpression(
                '/<h2 class="mb-1\.5 text-lg font-bold text-slate-900 md:text-xl">\s*'.preg_quote($block['title'], '/').'\s*<\/h2>\s*<p class="mb-4 text-sm leading-relaxed text-slate-600 md:text-base">/s',
                $content,
                "[{$block['title']}] has lost its heading or body typography.",
            );
        }

        // The spacing between blocks is set in one place, and the privacy
        // notice sits outside it so its own margin is not overridden.
        $this->assertStringContainsString('class="space-y-5"', $content);
    }

    public function test_about_page_exposes_no_resident_data(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        // No path to an uploaded ID, and no household or profile reference. The
        // staff route that used to serve an ID photo on request is gone, so the
        // markup cannot point at it at all - which is the stronger of the two
        // assertions, and the reason it no longer needs a route name.
        $this->assertStringNotContainsString('id-photo', $content);
        $this->assertStringNotContainsString('id_photo_path', $content);
        $this->assertStringNotContainsString('id-photos/', $content);
    }

    public function test_about_page_renders_a_geographic_map_at_the_configured_coordinates(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        $this->assertStringContainsString('id="find-us"', $content);
        $this->assertStringContainsString('Find Us', $content);
        $this->assertStringContainsString('id="find-us-map"', $content);

        // The canvas carries its own coordinates rather than being pointed at a
        // geocoded query, so the marker registered below lands on the hall and
        // not on whatever the geocoder decided.
        $this->assertStringContainsString('data-lat="'.config('bdrpis.about.map.lat').'"', $content);
        $this->assertStringContainsString('data-lng="'.config('bdrpis.about.map.lng').'"', $content);
        $this->assertStringContainsString('data-zoom="'.config('bdrpis.about.map.zoom').'"', $content);
        $this->assertStringContainsString('data-tiles="'.config('bdrpis.about.map.tile_url').'"', $content);
        $this->assertStringContainsString('data-logo="'.asset('images/Zone 1_logo.png').'"', $content);

        // Leaflet rather than a Google iframe: the Maps JS API only renders
        // with a key, and a key in public markup is a key anyone can lift.
        $this->assertStringContainsString('leaflet@1.9.4/dist/leaflet.js', $content);
        $this->assertStringContainsString('leaflet@1.9.4/dist/leaflet.css', $content);
        $this->assertStringNotContainsString('<iframe', $content);

        $this->assertStringNotContainsString('ChIJD0670VRZjjMRqRdIzYupueA', $content);
    }

    public function test_the_map_marks_the_location_geographically_rather_than_overlaying_it(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        // A marker registered with Leaflet is projected from its coordinates, so
        // it follows every pan, drag and zoom. An element centred over the frame
        // with CSS would not, which is why the overlay markup is gone.
        $this->assertStringContainsString('L.marker(position', $content);
        $this->assertStringContainsString('const position = [parseFloat(canvas.dataset.lat), parseFloat(canvas.dataset.lng)];', $content);

        // Anchored on its own centre, and the pulsing ring is inside the icon's
        // box so it travels with the marker rather than with the page.
        $this->assertStringContainsString('L.divIcon({', $content);
        $this->assertStringContainsString('iconSize: [40, 40]', $content);
        $this->assertStringContainsString('iconAnchor: [20, 20]', $content);
        $this->assertStringContainsString("'<span class=\"absolute -inset-2 rounded-full bg-blue-500/50 animate-ping\"></span>'", $content);
        $this->assertStringContainsString("' + canvas.dataset.logo + '", $content);

        // No HTML overlay is left floating over the canvas.
        $this->assertStringNotContainsString(
            'absolute inset-0 z-10 flex items-center justify-center',
            $content,
            'A static overlay marker is still layered over the map.',
        );
    }

    public function test_the_map_ships_only_the_controls_it_needs(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        // Scroll-wheel zoom would swallow the scrolling of the page this map
        // sits on; drag, pinch and the buttons still work.
        $this->assertStringContainsString('scrollWheelZoom: false', $content);

        // Zoom moved off the bottom-right corner, which carries the attribution.
        $this->assertStringContainsString("L.control.zoom({ position: 'topright' })", $content);
        $this->assertStringContainsString('zoomControl: false', $content);

        // Required by the Open Database License: the tile source is credited on
        // the map itself.
        $this->assertStringContainsString('OpenStreetMap</a> contributors', $content);
    }

    public function test_the_map_still_works_without_javascript(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        // Leaflet comes from a CDN, so it can fail to load on a restricted
        // connection. The address and the directions link must survive that.
        $this->assertStringContainsString('<noscript>', $content);
        $this->assertStringContainsString('The interactive map needs JavaScript', $content);
        $this->assertStringContainsString('Get Directions', $content);
    }

    public function test_find_us_is_the_last_section_on_the_about_page(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        $findUs = strpos($content, 'id="find-us"');
        $footer = strpos($content, '<footer');

        $this->assertIsInt($findUs, 'The about page no longer renders a find-us section.');
        $this->assertIsInt($footer, 'The page has no footer.');

        // Nothing but the end of the main region may follow it.
        $between = substr($content, $findUs, $footer - $findUs);

        $this->assertStringNotContainsString('<section', $between, 'Another section follows Find Us.');
    }

    public function test_the_map_sits_in_a_dark_blue_panel_card(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        $this->assertStringContainsString(
            'overflow-hidden rounded-3xl bg-slate-900 p-4 shadow-xl',
            $content,
            'The map no longer sits in the deep blue panel card.',
        );
    }

    public function test_the_map_carries_the_top_left_badge(): void
    {
        $panel = $this->mapPanel($this->get(route('about'))->assertOk()->getContent());

        // The corner a map's own "view larger map" control would use, so the two
        // never overlap. pointer-events-none keeps the map draggable under it, and
        // the pin glyph is decorative: the bar below states the place in words.
        $this->assertStringContainsString(
            'pointer-events-none absolute left-4 top-4 z-[500] flex items-center gap-2 rounded-xl bg-slate-900/90 px-4 py-2 text-sm font-semibold text-white backdrop-blur-sm',
            $panel,
            'The top-left location badge is missing or has moved.',
        );
        $this->assertMatchesRegularExpression(
            '/bg-slate-900\/90[^>]*>\s*<span aria-hidden="true">\s*\S+\s*<\/span>\s*Zone 1 Barangay Hall/s',
            $panel,
        );
    }

    public function test_the_map_action_bar_offers_contact_email_and_directions(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        $this->assertStringContainsString(
            'flex flex-wrap items-center justify-between gap-3 rounded-b-2xl bg-slate-900 p-4 text-white',
            $content,
            'The map action bar is missing.',
        );

        // Left: where it is.
        $this->assertStringContainsString(config('bdrpis.about.map.short_address'), $content);

        // Right: the three things a visitor wants next to a map.
        $this->assertStringContainsString('Contact Us', $content);
        $this->assertStringContainsString('Email', $content);
        $this->assertStringContainsString('Get Directions', $content);
    }

    public function test_the_map_actions_open_safely_in_a_new_tab(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        $coordinates = config('bdrpis.about.map.lat').','.config('bdrpis.about.map.lng');

        $this->assertStringContainsString(
            e('https://www.google.com/maps/dir/?api=1&destination='.$coordinates),
            $content,
            'The directions link does not point at the configured coordinates.',
        );

        // Both links in the panel leave the site, so both have to be marked.
        $this->assertSame(
            2,
            preg_match_all('/href="https:\/\/[^"]+"[^>]*target="_blank"[^>]*rel="noopener noreferrer"/', $this->mapPanel($content)),
            'Every link that leaves the site in the map panel must open in a new tab with noopener.',
        );
    }

    public function test_the_email_button_copies_the_address_with_a_fallback(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        $email = 'floria123@yahoo.com';

        // The address comes from settings rather than being typed into the view,
        // so a change to the barangay's email is a one-row change.
        $this->assertStringContainsString("@click=\"copy('{$email}')\"", $content);

        // Modern API first, then the selection-and-execCommand path that Safari
        // on http and older browsers still need.
        $this->assertStringContainsString('navigator.clipboard?.writeText', $content);
        $this->assertStringContainsString("document.execCommand('copy')", $content);
        $this->assertStringContainsString('field.select()', $content);

        // The confirmation is shown and announced.
        $this->assertStringContainsString("copied ? 'Copied!' : 'Email'", $content);
        $this->assertStringContainsString('aria-live="polite"', $content);
    }

    public function test_the_map_canvas_is_labelled_and_responsive(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        // An unnamed region is announced as just "region" by a screen reader.
        $this->assertMatchesRegularExpression(
            '/<div\s+id="find-us-map"[^>]*role="application"[^>]*aria-label="Map of Barangay Linasin[^"]*"/s',
            $content,
            'The map canvas is missing its accessible name.',
        );

        $this->assertMatchesRegularExpression(
            '/<div\s+id="find-us-map"[^>]*class="[^"]*h-80[^"]*sm:h-\[26rem\][^"]*"/s',
            $content,
            'The map has no responsive height.',
        );
    }

    public function test_the_map_places_are_kept_in_config(): void
    {
        $map = config('bdrpis.about.map');

        $this->assertNotEmpty($map['place_id'], 'The barangay place id was dropped from config.');
        $this->assertStringStartsWith('ChIJ', $map['place_id']);
        $this->assertStringContainsString('Nalasin', $map['address']);
        $this->assertStringContainsString('Bangued', $map['address']);
        $this->assertStringContainsString('Nalasin', $map['short_address']);

        // Bangued sits near 17.6 N, 120.6 E; a transposed pair would put the
        // marker in the middle of the South China Sea.
        $this->assertEqualsWithDelta(17.6, $map['lat'], 0.5);
        $this->assertEqualsWithDelta(120.6, $map['lng'], 0.5);
        $this->assertGreaterThanOrEqual(1, $map['zoom']);
        $this->assertLessThanOrEqual(21, $map['zoom']);

        // Tiles have to be somewhere a browser can fetch without a key.
        $this->assertStringStartsWith('https://', $map['tile_url']);
        $this->assertStringContainsString('{z}', $map['tile_url']);
        $this->assertGreaterThanOrEqual($map['zoom'], $map['tile_max_zoom']);
    }

    public function test_the_about_page_embeds_both_official_councils_in_carousels(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();
        $section = $this->officialsSection($content);

        foreach (['barangay', 'sk'] as $group) {
            $council = config('bdrpis.officials.'.$group);

            $this->assertStringContainsString(e($council['title']), $section);
            $this->assertStringContainsString(e($council['description']), $section);

            foreach ($council['people'] as $person) {
                $this->assertStringContainsString(e($person['name']), $section, "[{$person['name']}] is missing from the carousel.");
                $this->assertStringContainsString(e($person['role']), $section);
            }
        }

        // Two carousels, not one long row: the barangay's own officers and the
        // youth council are different bodies and are looked for separately.
        $this->assertSame(
            2,
            substr_count($content, 'x-data="officialCarousel('),
            'Expected exactly two carousels on the about page.',
        );
    }

    public function test_the_official_councils_are_separated_with_their_own_headings(): void
    {
        $section = $this->officialsSection($this->get(route('about'))->assertOk()->getContent());

        $this->assertStringContainsString('Barangay Officials', $section);
        $this->assertStringContainsString('Sangguniang Kabataan (SK) Council', $section);

        // The captain and the chairperson are in different carousels: the SK
        // council's chair must not be listed among the barangay's officers.
        $barangay = config('bdrpis.officials.barangay');
        $sk = config('bdrpis.officials.sk');

        foreach ($sk['people'] as $person) {
            $this->assertNotContains(
                $person['name'],
                array_column($barangay['people'], 'name'),
                "[{$person['name']}] is listed in both councils.",
            );
        }
    }

    public function test_the_official_heading_renders_a_plain_ampersand(): void
    {
        // The bug this pins: a Blade component attribute carrying "&amp;" hands
        // the literal string "&amp;" to {{ }}, which escapes the ampersand again,
        // and the page displays "&amp;" on screen instead of "&".
        //
        // The heading that had the ampersand was the directory page's, and it
        // went with the page. The rule it guarded still applies to everything
        // else the About page renders, which is where the check lives now.
        $about = $this->get(route('about'))->assertOk()->getContent();

        $this->assertStringNotContainsString('&amp;amp;', $about);
    }

    public function test_the_officials_section_starts_with_the_carousels_and_no_superfluous_heading(): void
    {
        $section = $this->officialsSection($this->get(route('about'))->assertOk()->getContent());

        // The band used to open with "Leadership - Barangay Officials & Directory"
        // above two subsections whose own eyebrows and titles already said it.
        $this->assertStringNotContainsString('Barangay Officials &amp; Directory', $section);

        // What is left is the two councils, each with its own label.
        $this->assertMatchesRegularExpression(
            '/tracking-\[0\.18em\][^>]*>\s*Leadership\s*<\/p>\s*<h2[^>]*>\s*Barangay Officials\s*<\/h2>/',
            $section,
            'The barangay council heading is missing its Leadership label.',
        );

        $this->assertMatchesRegularExpression(
            '/tracking-\[0\.18em\][^>]*>\s*Youth Council\s*<\/p>\s*<h2[^>]*>\s*Sangguniang Kabataan \(SK\) Council\s*<\/h2>/',
            $section,
            'The youth council heading is missing its Youth Council label.',
        );
    }

    public function test_the_official_cards_use_a_portrait_panel_instead_of_a_letter_disc(): void
    {
        $section = $this->officialsSection($this->get(route('about'))->assertOk()->getContent());

        $people = count(config('bdrpis.officials.barangay.people')) + count(config('bdrpis.officials.sk.people'));

        // One portrait panel and one detail panel per official.
        $this->assertSame($people, substr_count($section, 'h-48 w-full overflow-hidden rounded-t-xl bg-slate-100 md:h-56'));
        $this->assertSame($people, substr_count($section, 'relative -mt-4 rounded-b-xl border-x border-b border-slate-200/80 bg-white'));

        // The initials disc is the directory listing's card, not this one.
        $this->assertStringNotContainsString('rounded-full text-3xl', $section);

        $this->assertMatchesRegularExpression(
            '/<div class="h-48 w-full overflow-hidden rounded-t-xl bg-slate-100 md:h-56">\s*<picture>\s*<source srcset="[^"]*images\/avatar\/[^"]+" type="image\/avif" \/>\s*<img src="[^"]*images\/avatar\/[^"]+\.jpg" alt="" class="h-full w-full object-cover object-center"/s',
            $section,
            'The portrait panel does not carry the expected local avatar.',
        );
    }

    public function test_every_official_avatar_is_a_local_file(): void
    {
        $people = [...config('bdrpis.officials.barangay.people'), ...config('bdrpis.officials.sk.people')];

        foreach ($people as $person) {
            $this->assertStringStartsWith('images/avatar/', $person['photo'], "[{$person['name']}] does not use a local avatar.");
            $this->assertFileExists(public_path($person['photo']), "[{$person['name']}] points at a missing avatar file.");

            // The JPEG twin the card falls back to for browsers without AVIF.
            $fallback = public_path(preg_replace('/\.avif$/', '.jpg', $person['photo']));

            $this->assertFileExists($fallback, "[{$person['name']}] has no JPEG fallback, so an older browser would show an empty panel.");
        }

        // Nothing in the officials data reaches out to a third party: a resident
        // loading this page should cause no request off the server for a face.
        foreach ($people as $person) {
            $this->assertStringNotContainsString('http', $person['photo'], "[{$person['name']}] points at a remote image.");
        }

        $this->assertStringNotContainsString('unsplash', json_encode(config('bdrpis.officials')));
    }

    public function test_each_official_gets_an_avatar_from_their_own_gender_pool(): void
    {
        // A card that presents a woman as a man, or the reverse, is the failure
        // this mapping exists to prevent, so the gender of every official is
        // pinned here rather than left to the config to drift.
        $expected = [
            'female' => [
                'Francis Carla A. Acosta', 'Elizabeth Alcantara', 'Julieta Baro', 'Ofelia Renden',
                'Sherly Guzman', 'Althea Acosta', 'Cielo Jane Besinal', 'Michelle Joy Millare',
                'Mayryll Baes',
            ],
            'male' => [
                'Freddie Barbosa', 'Diosdado Tubaña', 'Cornelio Del Castillo', 'Bobbie Baro',
                'Reynald John Barbosa', 'John Nikko Acosta', 'Nasser James Galinato',
                'Edel Jansen Mendez', 'Mardave Go', 'Jhay-Ar Porte',
            ],
        ];

        $people = [...config('bdrpis.officials.barangay.people'), ...config('bdrpis.officials.sk.people')];

        foreach ($people as $person) {
            $this->assertContains(
                $person['name'],
                $expected[$person['gender']] ?? [],
                "[{$person['name']}] is not in the {$person['gender']} list from the brief.",
            );

            // And the avatar is drawn from that gender's pool, so the two cannot
            // disagree with each other.
            $this->assertContains(
                $person['photo'],
                config('bdrpis.officials.avatars.'.$person['gender']),
                "[{$person['name']}] is illustrated by an avatar from the other pool.",
            );
        }

        foreach ($expected as $gender => $names) {
            $this->assertCount(count($names), array_filter($people, fn ($person) => $person['gender'] === $gender));
        }
    }

    public function test_the_avatar_pools_are_the_whole_directory_and_are_balanced(): void
    {
        $pool = array_merge(...array_values(config('bdrpis.officials.avatars')));

        $onDisk = array_map(
            'basename',
            glob(public_path('images/avatar/*.avif')),
        );

        $this->assertSame(
            [],
            array_values(array_diff($onDisk, array_map('basename', $pool))),
            'An avatar exists in public/images/avatar/ that no gender pool claims.',
        );

        $this->assertSame(
            [],
            array_values(array_diff(array_map('basename', $pool), $onDisk)),
            'A gender pool claims an avatar that is not on disk.',
        );

        // The pools are spread rather than collapsed onto one file, so a row of
        // officials is not nine or ten of the same face.
        $used = array_column([...config('bdrpis.officials.barangay.people'), ...config('bdrpis.officials.sk.people')], 'photo');

        foreach (config('bdrpis.officials.avatars') as $gender => $avatars) {
            $distinct = array_unique(array_intersect($avatars, $used));

            $this->assertGreaterThan(1, count($distinct), "Every {$gender} official is shown the same avatar.");
        }
    }

    public function test_an_official_without_a_photograph_falls_back_to_initials(): void
    {
        $card = $this->blade('<x-official-portrait-card name="Julieta Baro" position="Barangay Kagawad" />', []);

        $this->assertStringNotContainsString('<img', $card);
        $this->assertStringContainsString('JB', $card, 'The initials fallback is missing.');
    }

    public function test_the_about_page_no_longer_offers_the_directory_button(): void
    {
        // Scoped to the page body. The page that used to be handed off to is
        // gone: its content is here now, and the footer and the quick services
        // card point at this section rather than at an address of its own.
        $body = $this->mainRegion($this->get(route('about'))->assertOk()->getContent());

        $this->assertStringNotContainsString('View the full official directory', $body);
        $this->assertStringNotContainsString('/directory', $body);
    }

    /**
     * The page body, without the site header and footer.
     */
    private function mainRegion(string $content): string
    {
        preg_match('/<main id="main-content">(.*)<\/main>/s', $content, $matches);

        $this->assertNotEmpty($matches, 'The public layout did not render a main region.');

        return $matches[1];
    }

    public function test_each_carousel_has_arrows_and_one_dot_per_official(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        // Arrows: one pair per carousel, each disabled at its own end and
        // labelled for a screen reader rather than relying on the glyph.
        $this->assertSame(2, substr_count($content, 'aria-label="Show the previous official in'));
        $this->assertSame(2, substr_count($content, 'aria-label="Show the next official in'));
        $this->assertSame(4, substr_count($content, ':disabled="atStart"') + substr_count($content, ':disabled="atEnd"'));

        // Dots are generated from the labels in a template loop, so the count
        // follows the roster rather than being written out by hand.
        $this->assertSame(2, substr_count($content, '<template x-for="(label, index) in labels"'));
        $this->assertSame(2, substr_count($content, ':aria-current="index === active ? \'true\' : null"'));
    }

    public function test_the_carousels_slide_a_native_track_that_a_swipe_can_drive(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        // The track is a native scroll container with snap points, so a touch
        // swipe and the keyboard both work. The step between cards is measured
        // from the rendered slides in JavaScript, because the card width
        // changes across breakpoints.
        $this->assertSame(2, substr_count($content, 'snap-x snap-mandatory'));
        $this->assertSame(2, substr_count($content, 'overflow-x-auto'));
        $this->assertSame(2, substr_count($content, 'role="region"'));
        $this->assertSame(2, substr_count($content, 'x-ref="track" @scroll="sync()"'));
        $this->assertStringContainsString('const [first, second] = this.slides;', file_get_contents(resource_path('js/app.js')));
    }

    public function test_the_carousels_show_one_card_on_a_phone_and_three_to_four_on_a_desktop(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        $width = 'w-[78%] shrink-0 grow-0 snap-start sm:w-[calc(50%-0.5rem)] lg:w-[calc(33.333%-0.667rem)] xl:w-[calc(25%-0.75rem)]';

        // Once per slide, so the count is the size of the roster: 9 barangay
        // officials and 10 SK councillors.
        $this->assertSame(
            count(config('bdrpis.officials.barangay.people')) + count(config('bdrpis.officials.sk.people')),
            substr_count($content, $width),
            'The carousel no longer steps its card width across breakpoints.',
        );
    }

    public function test_every_official_renders_a_card_with_a_name_and_a_role(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        $this->assertStringContainsString('rounded-2xl border border-slate-200/80 bg-white shadow-card', $content);

        foreach (['barangay', 'sk'] as $group) {
            foreach (config('bdrpis.officials.'.$group)['people'] as $person) {
                $this->assertMatchesRegularExpression(
                    '/'.preg_quote(e($person['name']), '/').'<\/h3>\s*<p class="[^"]*">'.preg_quote(e($person['role']), '/').'/',
                    $content,
                    "[{$person['name']}] is missing, or its role is not shown under the name.",
                );
            }
        }
    }

    public function test_every_public_section_header_is_centred(): void
    {
        // The site's section headers all follow the "Community Activities"
        // pattern: eyebrow, title, description, centred. Anything still asking
        // for left alignment is a header that will look different from its
        // neighbours, so the rule is checked at the source rather than left to
        // each page's markup.
        //
        // The two bands on the homepage are the exception and are deliberate:
        // the announcements header shares its row with a "View all" button, and
        // both were specified left aligned.
        $homepage = resource_path('views/public/welcome.blade.php');
        $views = array_merge(
            glob(resource_path('views/public/*.blade.php')),
            glob(resource_path('views/transparency/*.blade.php')),
            [resource_path('views/components/official-carousel.blade.php')],
        );

        foreach ($views as $view) {
            if ($view === $homepage) {
                continue;
            }

            $source = (string) file_get_contents($view);

            $this->assertStringNotContainsString(
                'align="left"',
                $source,
                basename($view).' still asks for a left-aligned section header.');
        }

        // And in the rendered pages: the centred wrapper and the section
        // heading's own eyebrow are produced by the same component and nowhere
        // else, so counting one against the other proves every section header
        // that rendered took the centred path. (Dark feature panels - the relief
        // band on /contact, for one - carry their own eyebrow and their own
        // layout, and are not section headers. The same is true of the card
        // headers in /contact's three-panel layout: they head a panel, not a
        // band, so they are left aligned on purpose.)
        $total = 0;

        // /transparency and /directory are not in the sweep: neither is a page
        // any more.
        foreach (['about', 'services.index', 'contact', 'announcements.index'] as $route) {
            $content = $this->mainRegion($this->get(route($route))->assertOk()->getContent());

            $centred = substr_count($content, 'text-center mx-auto max-w-2xl');
            $eyebrows = substr_count($content, 'text-xs font-bold uppercase tracking-[0.18em] text-sky-700');

            $this->assertSame(
                $centred,
                $eyebrows,
                "Not every section header on [{$route}] is centred.",
            );

            $total += $centred;
        }

        // So the check above is not vacuous: the pages below carry section
        // headers, and they rendered. (/announcements has none - its banner
        // already titles the page and the body opens with a count. /contact has
        // none either now that its three panels carry their own card headers.)
        $this->assertGreaterThanOrEqual(8, $total, 'The public pages no longer render any centred section headers.');
    }

    /**
     * The officials section of the about page, so the activity gallery above it
     * cannot satisfy a roster assertion.
     */
    /**
     * The About page's officials section, boundaries included.
     *
     * Balances the section tags rather than stopping at the first closing one.
     * The executive roles, the public information line and the sector Kagawads
     * used to be a page of their own and are now sections inside this one, so
     * the first "</section>" after the opening is one of theirs, not the end of
     * the block - and a helper that stopped there would quietly hand every
     * assertion a truncated slice of the page.
     */
    private function officialsSection(string $content): string
    {
        $start = strpos($content, 'id="officials"');

        $this->assertIsInt($start, 'The about page no longer renders an officials section.');

        // Start at the opening <section> itself, not at the id inside it. The id
        // is in the tag's attribute list, so slicing from it would begin after
        // the opening tag had been counted and the balance would never close.
        $opening = strrpos(substr($content, 0, $start), '<section');

        $this->assertIsInt($opening, 'The officials section is not a section element.');

        $slice = substr($content, $opening);
        preg_match_all('/<section[ >]|<\/section>/', $slice, $tags, PREG_OFFSET_CAPTURE);

        $depth = 0;
        $end = null;

        foreach ($tags[0] as [$tag, $offset]) {
            $depth += $tag === '</section>' ? -1 : 1;

            if ($depth === 0) {
                $end = $offset + strlen($tag);
                break;
            }
        }

        $this->assertIsInt($end, 'The officials section is not closed.');

        return substr($slice, 0, $end);
    }

    /**
     * The map panel, from its opening tag to the end of the section, so a
     * footer link cannot satisfy a panel assertion.
     */
    private function mapPanel(string $content): string
    {
        $start = strpos($content, 'id="find-us"');
        $end = strpos($content, '</section>', $start === false ? 0 : $start);

        $this->assertIsInt($start, 'The about page no longer renders a find-us section.');
        $this->assertIsInt($end, 'The find-us section is not closed.');

        return substr($content, $start, $end - $start);
    }
}
