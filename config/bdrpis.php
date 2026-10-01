<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BDRPIS Public Imagery
    |--------------------------------------------------------------------------
    |
    | Each key below points at a real photograph of Barangay Linasin held in
    | public/images/. The filenames still carry the original "-placeholder"
    | suffix from the design phase; that suffix is historical and the files
    | are genuine photographs, not artwork.
    |
    | 'image_fallbacks' points at the neutral SVG artwork that shipped with
    | the original design. The public homepage falls back to it whenever a
    | photograph is missing, so a public page never renders a broken image.
    |
    | SWAPPING A PHOTOGRAPH: replace the file in public/images/ using the
    | same filename, or point the key at a new path. No Blade template needs
    | to be edited.
    |
    | These are community-scene photographs only. No official photograph is
    | stored in this system; officials are rendered as neutral initials
    | avatars by the <x-official-card> component instead.
    |
    */

    'images' => [

        'hero' => 'images/barangay-hero-placeholder.jpg',

        'about' => 'images/barangay-hall-placeholder.jpg',

        // The footer banner's right-hand edge. The hero photograph is the
        // project's establishing landscape, and it is the widest asset held
        // here, so it crops well into the shallow panel the footer uses.
        'footer' => 'images/barangay-hero-placeholder.jpg',

        // Every inner page opens with this one photograph, so the services,
        // about, announcements and contact pages share a single banner
        // treatment. It is the barangay hall: the building residents are
        // directed to in every notice the portal publishes.
        'page_banner' => 'images/brgy-hall.png',

        'community' => [
            'cleanup' => 'images/community-cleanup-placeholder.jpg',
            'health' => 'images/community-health-placeholder.jpg',
            'relief' => 'images/community-relief-placeholder.jpg',
            'meeting' => 'images/community-meeting-placeholder.jpg',
            'youth' => 'images/community-youth-placeholder.jpg',
            'senior' => 'images/community-senior-placeholder.jpg',
        ],

    ],

    'image_fallbacks' => [

        'hero' => 'images/placeholders/barangay-hero-placeholder.svg',

        'about' => 'images/placeholders/barangay-hall-placeholder.svg',

        'footer' => 'images/placeholders/barangay-hero-placeholder.svg',

        'page_banner' => 'images/placeholders/barangay-hall-placeholder.svg',

        'community' => [
            'cleanup' => 'images/placeholders/community-cleanup-placeholder.svg',
            'health' => 'images/placeholders/community-health-placeholder.svg',
            'relief' => 'images/placeholders/community-relief-placeholder.svg',
            'meeting' => 'images/placeholders/community-meeting-placeholder.svg',
            'youth' => 'images/placeholders/community-youth-placeholder.svg',
            'senior' => 'images/placeholders/community-senior-placeholder.svg',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Announcement Categories
    |--------------------------------------------------------------------------
    |
    | The homepage announcement card shows a category tag and a featured
    | photograph. The category is stored on the record, so the tag is real
    | data rather than a label invented at render time.
    |
    | "image" is a key into the 'images' and 'image_fallbacks' maps above, not a
    | path, so a category resolves through the same existence check as every
    | other public photograph and never renders a broken image. "alt" describes
    | what that photograph actually shows, so a card carrying the category
    | image rather than an uploaded one stays accurately described.
    |
    | "general" is the key used for a post saved without a category, and its
    | photograph is a community scene, so an uncategorised notice still shows
    | something of the barangay rather than a building.
    |
    | A category is stored as its key, never its label, so the wording can be
    | corrected here without touching the records.
    |
    | "announcement_default_image" below is the backstop for the whole block: it
    | is a community scene, and deliberately NOT the barangay hall, because a
    | notice is not about the building it was published from.
    |
    */

    'announcement_default_image' => 'community.meeting',

    /*
    |--------------------------------------------------------------------------
    | Registration Wizard
    |--------------------------------------------------------------------------
    |
    | The closed lists the five-step signup wizard offers, and the field
    | boundaries each step owns. Both the Blade stepper and the RegisterRequest
    | read these, so a step and its validation can never disagree about which
    | field belongs where - a field added to a step here shows up in the form and
    | is validated without a second edit.
    |
    | "fields" lists the input names only. That is enough to reopen the right
    | step when the server rejects a submission, and to keep a step from
    | rendering a field the form does not actually post.
    |
    | Step 5 asks for an UPLOAD, not a typed number. "valid_id_photo" is a file,
    | so it is the one field on this form that cannot be redisplayed with old()
    | after a rejected submission - the browser will not let a page set a file
    | input's value - and the resident is asked to pick the file again.
    |
    | Nationality is deliberately not a closed list: "Others" swaps the select
    | for a free-text box, because nationality is not one of a fixed set of
    | values the way civil status is.
    |
    | The sector list is NOT here. Sectors are rows in a table that
    | resident_profiles holds a foreign key to, so the sector names are data
    | rather than configuration - see the migration that replaced the "Sector 1"
    | placeholders. The dropdowns order by id to reproduce the intended reading
    | order rather than sorting alphabetically.
    |
    | A resident's sector is the one and only residential area they are asked
    | for. There is no purok field alongside it: the sectors table carries an
    | optional purok_name for a sub-purok the staff screen can record, but that
    | is a staff-side annotation, is null for every row today, and is never
    | collected from a resident. The finer description of where in the sector
    | someone lives belongs in the free-text address.
    |
    */

    'registration' => [

        /*
         | How each field is checked in the browser before the wizard will move on
         | from the step it sits on.
         |
         | This is the same set of fields the steps above list, described rather
         | than repeated: "name" appears once in the step and once here, and the
         | two are read from the same config, so a field cannot be added to a
         | step and left out of the browser's idea of what that step requires.
         |
         | The types are deliberately coarse. A browser cannot know that a date is
         | before 1905 or that an address is really an address, and it is not
         | trying to: it answers one question, "is this field filled in with
         | something of the right shape", and RegisterRequest answers the rest
         | when the form is posted. Anything that decides whether a registration
         | is allowed is decided by the server.
         |
         | The two conditional rules are the ones a flat list cannot express.
         | nationality_other is collected only when the resident picks "Others",
         | and password_confirmation is a comparison rather than a value, so both
         | are keyed by the field they depend on.
         */
        'field_rules' => [
            'name' => ['type' => 'text', 'message' => 'Enter your full name.'],
            'date_of_birth' => ['type' => 'date', 'message' => 'Enter your date of birth.'],
            'gender' => ['type' => 'select', 'message' => 'Select your gender.'],
            'civil_status' => ['type' => 'select', 'message' => 'Select your civil status.'],
            'nationality' => ['type' => 'select', 'message' => 'Select your nationality.'],
            'nationality_other' => ['type' => 'text', 'message' => 'Enter your nationality.'],
            'address' => ['type' => 'text', 'message' => 'Enter your complete address.'],
            'sector_id' => ['type' => 'select', 'message' => 'Select your sector.'],
            'email' => ['type' => 'email', 'message' => 'Enter your email address.'],
            'household_number' => ['type' => 'text', 'message' => 'Enter your household reference number.'],
            'valid_id_photo' => ['type' => 'file', 'message' => 'Attach a photo or scan of your valid ID.'],
            'password' => ['type' => 'password', 'message' => 'Choose a password.'],
            'password_confirmation' => ['type' => 'password_confirmation', 'message' => 'Repeat your password.'],
        ],

        /*
         | The message for a field the browser has decided is empty but that has
         | no message of its own above, so a field added later cannot advance the
         | wizard with no explanation on screen.
         */
        'default_field_message' => 'This field is required.',

        'steps' => [

            ['key' => 'personal', 'label' => 'Personal Info', 'heading' => 'Personal Information',
                'fields' => ['name', 'date_of_birth', 'gender', 'civil_status', 'nationality', 'nationality_other']],
            ['key' => 'address', 'label' => 'Address', 'heading' => 'Where You Live',
                'fields' => ['address', 'sector_id']],
            ['key' => 'contact', 'label' => 'Contact', 'heading' => 'How We Reach You',
                'fields' => ['email']],
            ['key' => 'household', 'label' => 'Household', 'heading' => 'Household Details',
                'fields' => ['household_number']],
            ['key' => 'identity', 'label' => 'ID & Security', 'heading' => 'ID and Account Security',
                'fields' => ['valid_id_photo', 'password', 'password_confirmation']],
        ],

        'genders' => [
            'Male' => 'Male',
            'Female' => 'Female',
            'Other' => 'Other',
        ],

        'civil_statuses' => [
            'Single' => 'Single',
            'Married' => 'Married',
            'Widowed' => 'Widowed',
            'Separated' => 'Separated',
            'Annulled' => 'Annulled',
        ],

        // "Others" is the escape hatch; the value entered beside it is stored in
        // the same column, so a nationality outside this list still round-trips.
        'default_nationality' => 'Filipino',
        'other_nationality' => 'Others',

        // Oldest age the Secretary will accept at signup. 120 is a guard against
        // a mistyped year rather than a policy about age.
        'oldest_acceptable_birth_date' => '1905-01-01',

    ],

    'announcement_categories' => [

        'general' => [
            'label' => 'General',
            'image' => 'community.meeting',
            'alt' => 'Barangay volunteers working together during a community activity',
        ],

        'community' => [
            'label' => 'Community',
            'image' => 'community.cleanup',
            'alt' => 'Young volunteers collecting litter from an open lot in the barangay',
        ],

        'health' => [
            'label' => 'Health',
            'image' => 'community.health',
            'alt' => 'A barangay health worker checking a resident’s blood pressure at a health station',
        ],

        'relief' => [
            'label' => 'Relief',
            'image' => 'community.relief',
            'alt' => 'Barangay officials handing out relief goods to residents at a distribution site',
        ],

        'meeting' => [
            'label' => 'Meeting',
            'image' => 'community.meeting',
            'alt' => 'Volunteers sorting and repacking relief goods into resident kits',
        ],

        'youth' => [
            'label' => 'Youth',
            'image' => 'community.youth',
            'alt' => 'Young children sharing a meal during a community feeding programme',
        ],

        'senior' => [
            'label' => 'Senior',
            'image' => 'community.senior',
            'alt' => 'Senior residents receiving packed goods at a community distribution',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Documented E-Services
    |--------------------------------------------------------------------------
    |
    | These four services mirror the document types BDRPIS supports. Nothing
    | outside this list may be promoted, because the portal cannot process it.
    |
    | The list is what the public pages render; the processing side went with
    | the document request area and is being rebuilt. Each card currently links
    | to the registration page, and will point at the request form once that
    | area is back.
    |
    */

    'services' => [

        [
            'key' => 'barangay_clearance',
            'title' => 'Barangay Clearance',
            'description' => 'Certifies that a resident has no derogatory record in the barangay. Commonly required for employment, business, and other local transactions.',
            'icon' => 'clipboard',
            'tone' => 'navy',
        ],

        [
            'key' => 'certificate_of_indigency',
            'title' => 'Certificate of Indigency',
            'description' => 'Issued to residents in financial need as supporting proof for aid, scholarship, and medical assistance programs.',
            'icon' => 'heart-hands',
            'tone' => 'sky',
        ],

        [
            'key' => 'first_time_jobseeker_certificate',
            'title' => 'First-Time Jobseeker Certificate',
            'description' => 'Issued to first-time job applicants in support of the First Time Jobseekers Assistance Act (RA 11961).',
            'icon' => 'briefcase',
            'tone' => 'gold',
        ],

        [
            'key' => 'barangay_residency_certificate',
            'title' => 'Barangay Residency Certificate',
            'description' => 'Confirms that a person is a bona fide resident of the barangay, required for various government and private transactions.',
            'icon' => 'home',
            'tone' => 'navy',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Quick Services (homepage feature grid)
    |--------------------------------------------------------------------------
    |
    | The five high-level portal features shown on the homepage. These are
    | deliberately NOT the document catalogue: each card is a destination, and
    | the individual document types live under config('bdrpis.services') on the
    | /services page.
    |
    | "route" holds a route NAME rather than a URL, so the view resolves it with
    | route() and the link stays correct if the URI changes. "badge" holds the
    | Tailwind classes for the circular icon wrapper.
    |
    | A sixth "More Services" card used to point back at /services, which the
    | Document Requests card above already does, so it was redundant and was
    | removed rather than kept as a duplicate call to action.
    |
    | Note on "resident.verification.status": this is the only resident-facing
    | verification page in the application and it sits behind auth + role:resident,
    | so a guest who clicks "Verify a Resident" is sent to the login page. There
    | is deliberately no public resident lookup, because exposing one would let
    | anyone confirm whether a named person is a registered resident.
    |
    */

    'quick_services' => [

        [
            'title' => 'Document Requests',
            'description' => 'Apply for barangay clearance, certification and more.',
            'icon' => 'document',
            'badge' => 'bg-blue-50 text-blue-600',
            'route' => 'services.index',
        ],

        [
            'title' => 'Public Information',
            'description' => 'Get the latest announcements, programs and updates.',
            'icon' => 'megaphone',
            'badge' => 'bg-green-50 text-green-600',
            'route' => 'announcements.index',
        ],

        [
            'title' => 'Verify a Resident',
            'description' => 'Check resident information and status.',
            'icon' => 'id-badge',
            'badge' => 'bg-purple-50 text-purple-600',
            'route' => 'resident.verification.status',
        ],

        [
            'title' => 'Relief Distribution',
            'description' => 'View relief program updates and status.',
            'icon' => 'gift',
            'badge' => 'bg-orange-50 text-orange-600',
            'route' => 'transparency.index',
        ],

        [
            'title' => 'Barangay Officials',
            'description' => 'Know your barangay officials and contact details.',
            'icon' => 'users',
            'badge' => 'bg-teal-50 text-teal-600',
            // The officials are a section of the About page now, and the card
            // should land on that section rather than at the top of a long page.
            'route' => 'about',
            'fragment' => 'officials',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Barangay Officials & SK Council
    |--------------------------------------------------------------------------
    |
    | The two groups of officials shown on the about page, in the order they
    | hold office: the barangay's own officers, then the youth council.
    |
    | These are static display values, not records. There is no officials table
    | in this system, and no official photograph is held by it either: "photo"
    | points at a drawn placeholder avatar in public/images/avatar/, not at the
    | person named. Replace it with the official's own photograph - or drop the
    | key and the card falls back to the generated initials disc - before this
    | page is published or submitted for review. A name is corrected here, with
    | no schema or seeder change.
    |
    | "role" is shown on the card under the name, so a captain reads differently
    | from a kagawad and a chairperson from a member.
    |
    | "photo" is a path under public/, resolved with asset(), so the portal asks
    | no third party for a face and works the same with no network.
    |
    | GENDER: "gender" selects which pool the avatar comes from, so a card does
    | not present a woman's official as a man or the other way round. The pools
    | below are the whole of public/images/avatar/, split by how the artwork
    | reads; a person's "photo" is round-robin over their pool, which spreads the
    | few files available rather than showing the same face to everyone. The
    | assignment is fixed in config rather than drawn at random per request, so a
    | resident sees the same official in the same card on every page load.
    |
    | Each avatar has an AVIF and a JPEG of the same picture. The card offers the
    | AVIF first and falls back to the JPEG, because nothing older than Safari
    | 16.4 renders AVIF at all and an older phone would otherwise show an empty
    | panel.
    |
    */

    'officials' => [

        'avatars' => [
            'female' => [
                'images/avatar/avatar2.avif',
                'images/avatar/avatar3.avif',
            ],
            'male' => [
                'images/avatar/avatar1.avif',
                'images/avatar/avatar4.avif',
                'images/avatar/avatar5.avif',
            ],
        ],

        'barangay' => [
            'eyebrow' => 'Leadership',
            'title' => 'Barangay Officials',
            'description' => 'The officers and kagawads elected to serve Zone 1, and the sectors they cover.',
            'people' => [
                ['name' => 'Francis Carla A. Acosta', 'role' => 'Barangay Captain', 'gender' => 'female', 'photo' => 'images/avatar/avatar2.avif'],
                ['name' => 'Elizabeth Alcantara', 'role' => 'Barangay Secretary', 'gender' => 'female', 'photo' => 'images/avatar/avatar3.avif'],
                ['name' => 'Julieta Baro', 'role' => 'Barangay Kagawad', 'gender' => 'female', 'photo' => 'images/avatar/avatar2.avif'],
                ['name' => 'Ofelia Renden', 'role' => 'Barangay Kagawad', 'gender' => 'female', 'photo' => 'images/avatar/avatar3.avif'],
                ['name' => 'Freddie Barbosa', 'role' => 'Barangay Kagawad', 'gender' => 'male', 'photo' => 'images/avatar/avatar1.avif'],
                ['name' => 'Diosdado Tubaña', 'role' => 'Barangay Kagawad', 'gender' => 'male', 'photo' => 'images/avatar/avatar4.avif'],
                ['name' => 'Cornelio Del Castillo', 'role' => 'Barangay Kagawad', 'gender' => 'male', 'photo' => 'images/avatar/avatar5.avif'],
                ['name' => 'Sherly Guzman', 'role' => 'Barangay Kagawad', 'gender' => 'female', 'photo' => 'images/avatar/avatar2.avif'],
                ['name' => 'Bobbie Baro', 'role' => 'Barangay Kagawad', 'gender' => 'male', 'photo' => 'images/avatar/avatar1.avif'],
            ],
        ],

        'sk' => [
            'eyebrow' => 'Youth Council',
            'title' => 'Sangguniang Kabataan (SK) Council',
            'description' => 'The young councillors of the barangay youth assembly, serving alongside the barangay officials.',
            'people' => [
                ['name' => 'Althea Acosta', 'role' => 'SK Chairperson', 'gender' => 'female', 'photo' => 'images/avatar/avatar3.avif'],
                ['name' => 'Cielo Jane Besinal', 'role' => 'SK Secretary', 'gender' => 'female', 'photo' => 'images/avatar/avatar2.avif'],
                ['name' => 'Reynald John Barbosa', 'role' => 'SK Treasurer', 'gender' => 'male', 'photo' => 'images/avatar/avatar4.avif'],
                ['name' => 'John Nikko Acosta', 'role' => 'SK Member', 'gender' => 'male', 'photo' => 'images/avatar/avatar5.avif'],
                ['name' => 'Michelle Joy Millare', 'role' => 'SK Member', 'gender' => 'female', 'photo' => 'images/avatar/avatar3.avif'],
                ['name' => 'Nasser James Galinato', 'role' => 'SK Member', 'gender' => 'male', 'photo' => 'images/avatar/avatar1.avif'],
                ['name' => 'Edel Jansen Mendez', 'role' => 'SK Member', 'gender' => 'male', 'photo' => 'images/avatar/avatar4.avif'],
                ['name' => 'Mardave Go', 'role' => 'SK Member', 'gender' => 'male', 'photo' => 'images/avatar/avatar5.avif'],
                ['name' => 'Jhay-Ar Porte', 'role' => 'SK Member', 'gender' => 'male', 'photo' => 'images/avatar/avatar1.avif'],
                ['name' => 'Mayryll Baes', 'role' => 'SK Member', 'gender' => 'female', 'photo' => 'images/avatar/avatar2.avif'],
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Announcement Page Chrome
    |--------------------------------------------------------------------------
    |
    | The three things around the notice feed: the category pills in its filter
    | bar, the hotline rows and schedules in its sidebar.
    |
    | FILTERS: a pill is a bucket over the stored categories rather than a
    | category of its own, so adding a category to a notice does not force a
    | change here. "all" is rendered as the reset pill and matches everything.
    |
    | HOTLINES: only numbers the barangay has actually published are listed.
    | A placeholder number on a government page is worse than a short list -
    | somebody will dial it - so an office is added here once its number is
    | confirmed, and the row disappears entirely until it is. "setting" names the
    | settings-table key the number is read from, so it is published once and
    | shown here and in the footer alike.
    |
    | The second row below is the one to fill in with the Tanod and RHU lines.
    | It currently resolves to the same number as the first, and the page
    | collapses the two into one row rather than print a number twice under two
    | office names. The office and detail text are placeholders until the
    | barangay confirms them.
    |
    | SCHEDULES: verify each one against the barangay's own calendar before this
    | page is published, the same caution the demographic figures carry. "when"
    | is shown as written and "dot" picks the bullet colour.
    |
    */

    'announcement_filters' => [

        'all' => [
            'label' => 'All',
            'categories' => [],
            'icon' => null,
            'tone' => 'slate',
        ],

        'health' => [
            'label' => 'Health & Medical',
            'categories' => ['health'],
            'icon' => 'heart-hands',
            'tone' => 'rose',
        ],

        'advisory' => [
            'label' => 'Advisories',
            'categories' => ['general'],
            'icon' => 'megaphone',
            'tone' => 'amber',
        ],

        'events' => [
            'label' => 'Events & Clean-ups',
            'categories' => ['community', 'meeting', 'youth', 'senior'],
            'icon' => 'leaf',
            'tone' => 'emerald',
        ],

        'assistance' => [
            'label' => 'Assistance & Services',
            'categories' => ['relief'],
            'icon' => 'hand',
            'tone' => 'sky',
        ],

    ],

    'hotlines' => [

        [
            'office' => 'Barangay Hall Desk',
            'detail' => 'Zone 1 Main Office',
            'setting' => 'office_phone',
        ],

        // Replace with the confirmed Tanod / RHU line once the barangay has one.
        [
            'office' => 'Public Information Line',
            'detail' => 'Barangay Linasin, Zone 1',
            'setting' => 'emergency_hotline',
        ],

    ],

    'schedules' => [

        [
            'title' => 'Barangay Hall Office Hours',
            'when' => 'Mon - Fri • 8:00 AM - 5:00 PM',
            'dot' => 'emerald',
        ],

        [
            'title' => 'Waste Collection (Biodegradable)',
            'when' => 'Every Tue & Fri • 8:00 AM',
            'dot' => 'amber',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | About Section Copy
    |--------------------------------------------------------------------------
    |
    | Public-facing history, vision, and mission statements for the homepage
    | "About" band. Held here rather than in the view so the wording can be
    | corrected without touching markup.
    |
    */

    'about' => [

        'history' => [
            'title' => 'Our History',
            'body' => 'Barangay Linasin is one of the progressive barangays in the Municipality of Bangued, Abra. Known for its strong community spirit and active participation in local governance, the barangay continues to work towards a safer, healthier, and more sustainable environment for all its residents.',
        ],

        'vision' => [
            'title' => 'Our Vision',
            'body' => 'A peaceful, progressive, and resilient community of empowered citizens.',
        ],

        'mission' => [
            'title' => 'Our Mission',
            'body' => 'To deliver efficient, transparent, and responsive public service for the benefit of every resident of Barangay Linasin.',
        ],

        /*
         * The "Find Us" map on the same page.
         *
         * The canvas is a Leaflet map over OpenStreetMap tiles, and the marker
         * is a real geographic marker at these coordinates, not an element
         * positioned over the middle of a frame: a centred overlay drifts off
         * the barangay the moment anyone pans or zooms.
         *
         * Leaflet is used rather than the Google Maps JS API because that API
         * only renders with a key, and a key in the markup of a public page is a
         * key anyone can lift. OSM tiles need no account at all. Google's
         * directions link below the map is still used, because it is the better
         * turn-by-turn product for a phone.
         *
         * The place id supplied for this work is kept for reference but is not
         * used to build anything: it decodes to 17 bytes, where a Google place
         * id carries roughly 41, so a place_id/cid lookup would resolve to
         * nothing.
         */
        'map' => [
            'place_id' => 'ChIJD0670VRZjjMRqRdIzYupueA',
            'address' => 'Zone 1 Pob. (Nalasin), Bangued, Abra',
            'short_address' => 'Zone 1, Nalasin, Bangued, Abra',
            'lat' => 17.6039114,
            'lng' => 120.6207086,
            'zoom' => 16,
            'tile_url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            'tile_max_zoom' => 19,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Barangay Demographics
    |--------------------------------------------------------------------------
    |
    | Figures rendered in the "Barangay Linasin" statistics card beside the
    | About copy.
    |
    | IMPORTANT: these are static display values supplied for the design, not a
    | live census and not read from the database. Verify each figure against the
    | official barangay profile and PSA data before this page is published or
    | submitted for review, then correct it here. They are kept in config so a
    | correction is a one-line change with no markup or schema impact.
    |
    */

    'demographics' => [

        'name' => 'Barangay Linasin',

        'location' => 'Zone 1, Bangued, Abra',

        'stats' => [
            ['label' => 'Population', 'value' => '2,264', 'icon' => 'users'],
            ['label' => 'Total Households', 'value' => '641', 'icon' => 'home'],
            ['label' => 'Total Families', 'value' => '710', 'icon' => 'heart-hands'],
            ['label' => 'Location / Zone', 'value' => 'Zone 1', 'icon' => 'pin'],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | How It Works
    |--------------------------------------------------------------------------
    |
    | Reflects the real BDRPIS workflow. Note that documents are claimed at the
    | Barangay Hall; they are not delivered digitally.
    |
    */

    'steps' => [

        [
            'title' => 'Create an Account',
            'description' => 'Register online with your name, email address, and a secure password.',
        ],

        [
            'title' => 'Verify Your Email & Resident Profile',
            'description' => 'Confirm your email, then submit your resident profile and valid ID for verification by the Barangay Secretary.',
        ],

        [
            'title' => 'Submit a Document Request',
            'description' => 'Choose a supported document, state your purpose, and receive a reference number instantly.',
        ],

        [
            'title' => 'Track Your Request',
            'description' => 'Follow the status of each request from your Document Requests page whenever you are logged in.',
        ],

        [
            'title' => 'Claim Your Document',
            'description' => 'Once marked ready, claim your document at the Barangay Hall within the stated release period.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Community Activities
    |--------------------------------------------------------------------------
    |
    | The activity categories and descriptions below are generic programme
    | types a barangay community runs day to day. They are not database
    | records and do not claim that a specific dated event took place.
    |
    | 'alt' describes what the paired photograph actually shows, so the
    | rendered page stays accurate if an image is ever swapped. Keep each
    | description consistent with the file referenced by 'image'.
    |
    */

    'activities' => [

        [
            'title' => 'Clean-Up Drive',
            'description' => 'Community-led environmental activities that keep shared spaces and public areas clear and usable for everyone.',
            'image' => 'community.cleanup',
            'alt' => 'Young volunteers collecting litter from an open lot in the barangay',
        ],

        [
            'title' => 'Health & Vaccination Activity',
            'description' => 'Barangay health and vaccination activities conducted with the local health office for resident welfare.',
            'image' => 'community.health',
            'alt' => 'A barangay health worker checking a resident’s blood pressure at a health station',
        ],

        [
            'title' => 'Relief Distribution',
            'description' => 'Distribution of relief goods to qualified households, with per-household status published on the Transparency Board.',
            'image' => 'community.relief',
            'alt' => 'Barangay officials handing out relief goods to residents at a distribution site',
        ],

        [
            'title' => 'Community Meeting',
            'description' => 'General assemblies and sector meetings where residents raise concerns and hear local updates.',
            'image' => 'community.meeting',
            'alt' => 'Volunteers sorting and repacking relief goods into resident kits',
        ],

        [
            'title' => 'Youth Activity',
            'description' => 'Programmes and gatherings for young residents, supporting skills, sports, and civic involvement.',
            'image' => 'community.youth',
            'alt' => 'Young children sharing a meal during a community feeding programme',
        ],

        [
            'title' => 'Senior Citizen Activity',
            'description' => 'Programmes dedicated to senior residents, including health, welfare, and community participation.',
            'image' => 'community.senior',
            'alt' => 'Senior residents receiving packed goods at a community distribution',
        ],

    ],

];
