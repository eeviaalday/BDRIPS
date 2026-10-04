# PROMPT_LOG.md

## 1. Project overview

BDRPIS - Barangay Digital Records and Portal Information System: resident services, document
requests, relief distribution and resident verification for a single barangay. Laravel 13 on
PHP 8.5 with MySQL, Tailwind CSS 3, Alpine.js 3 and Blade throughout; Vite 8 builds the two
entry points, PHPUnit 12 tests. No SPA framework, no component library, no paid services.

**The five role areas** - the public/guest area plus four roles:

| Area | Who | What it covers |
|---|---|---|
| Public / guest | anyone | Home, services, about, announcements, contact; sign in and register |
| Resident | verified resident | Own household only: document requests, relief history, own profile |
| Staff desk | Secretary | Resident verification, residents, document requests, relief, announcements |
| Kagawad | assigned Kagawad | Own sector only: relief checklist and a read-only resident list |
| Captain | Admin | Everything a Secretary has, plus User Accounts, Sectors & Kagawad, Settings, Audit Logs |

This log exists for academic project documentation and AIAS Level 3 compliance
(auditability of AI-assisted development), as its own header records.

---

## 2. How AI was used

**The workflow, as the owner records it:** a senior-web-developer assistant and Claude wrote
the prompts; OpenCode agents executed them in this repository. The archive confirms the
execution side - its conventions describe it as "every prompt sent to OpenCode".

**The role assigned to the agents**, quoted verbatim from the prompt that opened the staff
work, entry #015:

> You are a senior Laravel/Blade/Tailwind engineer working on BDRPIS (Laravel 13, PHP 8.5,
> Blade + Tailwind 3 + Vite 8, MySQL, Breeze). Branch: staff-ui.

**The scope rules that governed the staff work.** These were written by the owner into the
prompts from #015 onward and codified in `.ai/rules/staff-polish.md`:

1. **No changes to the public/guest or resident area** during the staff polish - not views,
   components, controllers, routes or tests. Not "just a class", not "just copy".
2. **Staff-only component variants.** A file used by both staff and resident/public is never
   edited in place; a staff variant is created and the original left byte-for-byte unchanged.
3. **No new dependencies.** No JS framework, no composer or npm package. Blade, Tailwind and
   Alpine only.
4. **No behaviour change** to authorization, validation rules or business logic unless the
   finding is marked a bug and the owner has approved that specific item.
5. **Tests are part of the finish line** - the full suite plus Pint, and a rebuild whenever a
   Tailwind class changed.
6. **Browser verification is required.** Markup assertions are not evidence a control works.
7. **A diff guard must print nothing** for the resident/public and shared-component paths.

Later prompts added: never read or write `.env` (#033, #034), leave the development database
untouched and use `bdrpis_testing` instead (#025), and keep port 8000 alone (#034).

---

## 3. Timeline

| Date | Phase | What was built | Commits |
|---|---|---|---|
| 2026-09-28 | Repository created *(reconstructed from git history)* | One-line README placeholder. | `055b871` |
| 2026-09-28 to 09-30 | Prompt log started | Entries #001-#014 written: public homepage, hero, navigation, login. Two days before the import, so this work sits inside it rather than in separate commits. | *(no commits)* |
| 2026-09-30 | Application imported *(reconstructed from git history)* | The whole Laravel application - 364 files, 49,280 lines - plus agent tooling: `.agents/skills/`, `CLAUDE.md`, `AGENTS.md`, `opencode.json`, `.mcp.json`, `boost.json`, and the first `PROMPT_LOG.md`. | `3bfabed` |
| 2026-09-30 | Resident work backed up *(reconstructed from git history)* | 26 files across resident controllers, views and tests. | `43925d5` |
| 2026-09-30 | App cut back to a viewer-only shell *(reconstructed from git history)* | 141 files changed, **15,001 deletions**: staff, document-request, relief and profile controllers removed, leaving a base to rebuild on. | `77286b6` |
| 2026-10-01 | Database switched to MariaDB *(reconstructed from git history)* | `.env.example`, `phpunit.xml`, `tests/TestCase.php` - the separation of the test database begins here. | `6da7767` |
| 2026-10-01 | Phases 0-6: the five role areas *(reconstructed from git history)* | Foundation; role-based landing, per-role navigation, My Account; the resident area; the secretary and admin desk; the Kagawad area; the admin area; the permission matrix sweep. **No prompt entry was recorded for any of these seven commits.** | `af37e55`, `3ffa95b`, `81ef4f1`, `6fee11c`, `6c780a1`, `5139493`, `effde1d` |
| 2026-10-01 | Resident and public UI passes *(reconstructed from git history)* | 18 commits: resident heroes, tables, relief and request screens; the public tracking page retired; registration validated step by step; the two fee columns dropped; the New Request button wired. | `a300085` ... `485a279` |
| 2026-10-01 to 10-02 | Staff-area audit and polish (#015-#026) | Read-only audit, the staff component set, staff and admin screens, dialogs, Kagawad sector residents, sidebar state, badges, filters, Font Awesome. | `1f945b8` ... `8de64c5` |
| 2026-10-02 | Favicon and Services links (#027) | Favicon on every page; Services links send a guest to sign in. | `c14064a` |
| 2026-10-02 to 10-03 | Phone-usable tables (#030) | Staff tables become cards below `md`. | *(within `8de64c5`-era work)* |
| 2026-10-03 | Resident record, correction and archive (#028, #029) | Staff-only resident detail and edit screen; archive and restore instead of delete; a login error banner. | `18aaca0`, `1555e05` |
| 2026-10-03 | Unclaimed documents (#031) | Unclaimed workflow: 14 days, three strikes in 180 days, a 90-day pause, a 30-day slot hold, and an excuse. | `bf623e1` |
| 2026-10-03 | Digital copies and email delivery (#032) | AES-256 encrypted on-demand PDF copies for ready requests; office visibility per download. | `7cf54e1`, `7efe75f` |
| 2026-10-04 | Email verification (#033) | Wording, sender, 24-hour expiry, failure handling, 53 tests. | `f3e772d` |
| 2026-10-04 | Seeded residents removed (#034) | The seeder creates no residents; README updated; development data cleaned. | `cd57a8f` |

Entries #025-#034 carry no date in the log; their dates above are taken from the commits
they match, or written **not recorded** where no commit could be tied to them.

---

## 4. Prompt entries

Format for every entry: Goal, Prompt summary, Outcome, Problems found and fixes, Decision
made by the owner, Tests after.

### #001 - 2026-09-28, documentation

**Goal:** Create an append-only log of every AI prompt sent on this project, with rules for maintaining it.
**Prompt:** Asked for a root-level `PROMPT_LOG.md` recording every prompt sent to OpenCode, with a fixed entry template, a category list, file-level traceability and test results.
**Outcome:** `PROMPT_LOG.md` created with a header, Log Conventions and entry #001 as its first. No application behaviour changed.
**Problems and fixes:** none recorded.
**Owner decision:** Prompts issued before the log existed were deliberately not reconstructed, because their wording could not be verified.
**Tests after:** not recorded - documentation only, no suite run.

### #002 - 2026-09-28, public homepage images

**Goal:** Replace SVG placeholders with the real photographs in `public/images/` across the public pages.
**Prompt:** Asked for every image reference in Blade and CSS audited, the Zone 1 logo kept as the brand mark, layout checked at 1440/768/390px, and no backend, route or policy changes.
**Outcome:** Eight photographs render across the homepage; layout stable at all three breakpoints; alt text rewritten to describe the actual images.
**Problems and fixes:**
- The new test class lacked `RefreshDatabase`, so three tests failed on a missing table.
- A "no `<img>`" assertion matched the shared header/footer logo and was narrowed to the `<main>` region.
- The hero scrim left light cover exactly where copy sits on small screens; a responsive scrim replaced the flat opacity.
**Owner decision:** `php artisan pint` does not exist in this application, so `vendor/bin/pint --test` was used instead.
**Tests after:** 84 tests, 84 passed, 226 assertions.

### #003 - 2026-09-28, hero and header restyle

**Goal:** Restyle the hero and public header to a supplied mock-up - wave divider, lighter overlay, new copy, a floating callout, reorganised navigation.
**Prompt:** Specified a curved white wave, a lighter gradient, exact heading and CTA text and colours, a floating "Our Barangay" card, and a six-link header with a search bar.
**Outcome:** The hero matches the mock-up with a seam-free wave and brighter photograph, and no horizontal overflow at any breakpoint.
**Problems and fixes:**
- A pure-white wave left a hairline seam against the surface-toned section below; it was filled with `text-surface` instead.
- Flat `opacity-70` left white text on bright sky below `lg`; a responsive scrim replaced it.
- `x-btn` merges variant and passed background utilities, so two conflicting utilities resolved by stylesheet order; plain anchors were used.
**Owner decision:** "Map" was resolved to the Contact anchor, the header search bar shipped presentational and disabled, and the Tagalog line used a clean system italic - all confirmed with the user before coding.
**Tests after:** 89 tests, 89 passed, 250 assertions.

### #004 - 2026-09-28, hero proportions

**Goal:** Make the hero read at 100% zoom as it had at 80% - tighter padding and type, copy nearer the margin, a wider callout card, a taller wave.
**Prompt:** Asked for hero padding `py-20`/`py-24` reduced to `py-10`/`py-12`, headings down one step, a 16:9 callout card and a wave of `h-16 md:h-24 lg:h-32`, with links and copy intact.
**Outcome:** Desktop padding fell 128px to 56px, the callout became a 384px 16:9 card, and the wave reached 128px at `lg`.
**Problems and fixes:**
- The reshaped 384px card collided with the copy column between 1024-1279px, so the callout breakpoint moved from `lg` to `xl`.
- `2xl:max-w-[90rem]` was reported missing by a fixed-string search; Tailwind escapes the leading digit of the selector.
**Owner decision:** Widening was limited to `2xl` so the hero stays aligned with every section below it up to 1535px.
**Tests after:** 89 tests, 89 passed, 250 assertions.

### #005 - 2026-09-28, header cleanup

**Goal:** Remove the unused search field, align the brand with the hero text, distribute the nav evenly, drop the three hero counters, restyle the slogan.
**Prompt:** Asked for the search input deleted, a flush-left brand, evenly spread nav links, the stat counters and divider removed, and the slogan white, centred and tilted.
**Outcome:** The header is three clean blocks with the brand on the hero's text axis at every breakpoint; the slogan is solid white, centred and tilted three degrees.
**Problems and fixes:**
- A test asserted the removed search field existed, so it was inverted rather than deleted.
- The slogan sits where the hero overlay is weakest, so a targeted drop-shadow replaced the generic one.
- `text-shadow-*` is not generated by this Tailwind build; only `drop-shadow-*` is.
**Owner decision:** The #003 decision to ship the search bar disabled was reversed at the user's choosing.
**Tests after:** 90 tests, 90 passed, 252 assertions.

### #006 - 2026-09-28, nav and vertical rhythm

**Goal:** Undo two over-corrections - stretched nav links and over-tightened hero padding.
**Prompt:** Asked for nav links grouped rather than stretched, hero padding restored to the `py-16`/`md:py-20`/`lg:py-24` ramp, and the callout left alone.
**Outcome:** Six links sit in one tight `gap-4` cluster stepping to `gap-6` at 1280px, hero padding is restored, and the callout is byte-for-byte unchanged.
**Problems and fixes:**
- `justify-evenly` from the previous pass stretched the nav edge to edge and was removed.
- `px-2` link padding, added only to make the stretched layout fit, clipped hover backgrounds; it was restored to `px-3`.
**Owner decision:** Responsive `gap-4 xl:gap-6` was chosen over the flat `gap-6` or `gap-8` on offer, because `gap-8` overflows at 1024px.
**Tests after:** 90 tests, 90 passed, 252 assertions.

### #007 - 2026-09-28, hero height

**Goal:** Add a final increment of vertical breathing room to the hero, changing nothing else.
**Prompt:** Asked for more hero padding or a minimum height, keeping nav spacing, card proportions and the wave divider exactly as they were.
**Outcome:** The hero is 32px taller at desktop and one padding step taller elsewhere; wave clearance improved from 56px to 72px.
**Problems and fixes:**
- A `min-h` floor would also have needed flex centring, because uncentred surplus space collects below the copy and pushes it away from the card.
**Owner decision:** Padding was chosen over `min-h`: `lg:py-28` gives 554px against the 560px a floor would impose - within 6px - in one utility change.
**Tests after:** 90 tests, 90 passed, 252 assertions.

### #008 - 2026-09-28, full-viewport hero

**Goal:** Make the hero fill almost the whole first desktop viewport, so only the eyebrow beneath it stays visible.
**Prompt:** Asked for a near-full-height hero and the wave pushed down so the section below starts under the fold, with existing anchoring kept.
**Outcome:** At 100% zoom on any window 1024px or wider, only the eyebrow is visible below the hero; a short window falls back to content height without clipping.
**Problems and fixes:**
- The prompt's `min-h-[calc(100vh-80px)]` would have pushed the entire section below the fold, contradicting its own stated outcome.
- A `min-h` with a top-aligned copy collects every surplus pixel below the text, so desktop centring was added.
- This entry was first written to the wrong position in the log, between #006 and #007, and was moved to its chronological place.
**Owner decision:** Neither the prompt's `min-h` nor `py-26 lg:py-34` was used as written; the 194px offset was derived from measured component geometry instead.
**Tests after:** 90 tests, 90 passed, 252 assertions.

### #009 - 2026-09-28, About section redesign

**Goal:** Rebuild the homepage About band as a navy banner over a two-column body with copy and a statistics card.
**Prompt:** Asked for a navy "About Our Barangay" banner, a two-column desktop layout, History/Vision/Mission copy and a card showing population, households, families and location, stacking on mobile.
**Outcome:** The band is a navy banner over a two-column body that collapses below 1024px, with all four figures rendered from config.
**Problems and fixes:**
- The prompt's "Welcome to Barangay Linasin" signboard image does not exist, so the existing multi-purpose hall photograph was used.
- The Data Privacy Act notice was an absolute overlay on the image card; it was moved to a static block below the mission statement and kept.
**Owner decision:** The user chose the hall photograph over the non-existent signboard, and chose to redesign the existing homepage section rather than build the standalone page the banner-and-breadcrumb wording implied.
**Tests after:** 96 tests, 96 passed, 287 assertions.

### #010 - 2026-09-28, About grid realignment

**Goal:** Realign About into three elements - narrow photograph, wide copy, compact statistics card - and drop the breadcrumb.
**Prompt:** Asked for the breadcrumb removed entirely and a horizontal three-element row with a small image, majority-width text and a compact stats card, stacking on mobile.
**Outcome:** From 1280px the row is a 288px photograph, 576px copy and 288px card with no wrapping; below 1024px all three stack.
**Problems and fixes:**
- The photograph had been nested inside the copy column, pushing "Our History" under the image; the body was rebuilt as three sibling elements.
- An existing test asserted the deleted breadcrumb, so it was inverted rather than removed.
**Owner decision:** none recorded.
**Tests after:** 96 tests, 96 passed, 285 assertions.

### #011 - 2026-09-28, login page

**Goal:** Rebuild the login page as a full-bleed photograph under a translucent blue wash, branding left and a white sign-in card right.
**Prompt:** Asked for a `bg-cover` background under a semi-transparent blue overlay, a left brand column, and a right Sign In card with labelled fields, a visibility toggle and a register link, leaving auth logic untouched.
**Outcome:** The page is a full-bleed landscape with a working Alpine password toggle; all auth behaviour stayed intact, proven by five unchanged suites.
**Problems and fixes:**
- `GuestLayout` declared `$maxWidth` and `$title` with no constructor, and a class component cannot pick attributes up from the tag, so both were silently ignored on every guest page.
- A stale compiled layout masked the constructor fix until `view:clear` ran.
- One new assertion failed because the button label sits after a newline; it was rewritten as a whitespace-tolerant regex.
**Owner decision:** The "Or continue with" divider and the Google button were dropped on the user's instruction - there is no Socialite and no OAuth route - and a test now asserts neither can reappear.
**Tests after:** 104 tests, 104 passed, 322 assertions.

### #012 - 2026-09-28, standalone public pages

**Goal:** Replace one scrolling homepage with real pages, each with its own route, controller method, template and banner, plus active-tab highlighting.
**Prompt:** Asked for nav links to point at `/`, `/services`, `/about`, `/announcements` and `/contact` with `request()->routeIs()` highlighting, the homepage reduced to hero plus featured services and announcements, and no model, query or authorization changes.
**Outcome:** Five route-backed destinations, with the current page marked by the gold pill and `aria-current`; the homepage fell to 227 lines and no configured content was destroyed.
**Problems and fixes:**
- Three navigation entries hard-coded `active => false`, so those tabs could never light up.
- Four failures were all in new test code: attribute order, an HTML-escaped ampersand, a footer accessibility skip link, and an image still used by the hero.
- Two throwaway probes reported misleading results, one of them from a missing `RefreshDatabase`.
**Owner decision:** "How It Works" and "Community Activities" were relocated to their new pages rather than deleted, on the user's instruction.
**Tests after:** 129 tests, 129 passed, 465 assertions.

### #013 - 2026-09-28, Quick Services grid

**Goal:** Replace the homepage document catalogue with a six-card Quick Services grid that navigates, moving the catalogue to the page named after it.
**Prompt:** Asked for six named cards with coloured circular badges, given descriptions and arrow links on a six-column ladder, with no backend or route changes.
**Outcome:** Six cards render on the specified ladder, each a single fully-clickable link; document types now live only on `/services`.
**Problems and fixes:**
- Tailwind scanned no PHP config, so three badge colours compiled to nothing and three icon circles would have shipped with no background; adding `./config/*.php` fixed the whole class of problem.
- The feature test passed on those badges because it asserted class strings in the HTML, so it was strengthened to assert the utilities exist in the emitted CSS, then proven load-bearing by removing the glob again.
**Owner decision:** Barangay Officials points at `/directory` rather than the `/about` the prompt offered, because that is the page carrying the contact details the card promises.
**Tests after:** 148 tests, 148 passed, 578 assertions.

### #014 - 2026-09-28, Quick Services spacing

**Goal:** Close the gap between the hero wave and the Quick Services heading, and drop the redundant sixth card.
**Prompt:** Asked for reduced top spacing or a negative margin, the "More Services" card removed, and the grid rebalanced for five cards, with no backend changes.
**Outcome:** Band padding fell to 32/40/48px with the gap below it unchanged, the grid holds five cards, and the hero min-height was corrected from `100vh-194px` to `100vh-162px`.
**Problems and fixes:**
- `x-shell` hard-coded its inner padding with no override, so an optional `top` prop was added and top/bottom padding split into separate `pt-*`/`pb-*` utilities.
- The hero fold constant is derived from the band padding just changed; leaving it at 194px would have pushed the heading above the fold on every desktop window.
**Owner decision:** The negative-margin option was rejected and the reason recorded in the template, because the hero's `isolate` stacking context would slide the band behind the wave instead of closing the gap.
**Tests after:** 150 tests, 150 passed, 578 assertions.

### #015 - 2026-10-01, read-only staff audit

**Goal:** Audit the staff, Kagawad and Admin areas without editing views, routes, controllers, models, tests or CSS, and report the mismatch list.
**Prompt:** Asked for a permission matrix from `ROLES.md`, the resident design language, a list of shared components, a per-page inventory, and verification of twelve listed findings - with outputs limited to the audit doc, the rules file and the log.
**Outcome:** Thirteen findings reported, two of them real bugs carrying wrong data or a dead control; 801 tests pass and no stylesheet changed, so no build was needed.
**Problems and fixes:**
- Announcements Pin/Unpin is `@disabled($announcement->isPinned())`, so it is disabled exactly when unpinning is needed, and no Pin control exists anywhere.
- User Accounts role counts read 0 for every role because an arrow function captured `$counts` by value; proved side by side in tinker.
- Every staff page rendered the title `BDRPIS - BDRPIS`, because the layout never declared a `$title` property.
- Two reported findings did not reproduce and are recorded as such rather than confirmed.
**Owner decision:** none recorded.
**Tests after:** 801 tests pass.

### #016 - 2026-10-02, staff component set

**Goal:** Replace ad-hoc tables, filters, badges and empty states across staff pages with one small consistent component set.
**Prompt:** Asked for nine named components under `resources/views/components/staff/*`, a unified pagination footer, clickable rows and a component test file, with shared resident and public components never edited.
**Outcome:** Nine staff components built; 8 tables, 6 stat-card markups, 12 badge call sites, 6 filter forms and 8 paginator footers migrated.
**Problems and fixes:**
- Both the audit and the bug report were wrong about the offset cells: the centred element was `th[scope="row"]`, which the browser's own stylesheet centres regardless of any inherited alignment.
- Filter selects had no width and shrank to whatever was left; one had zero spare width, so a `min-width` is now computed in `ch` units.
- A Blade comment placed inside an `x-data` attribute closed it early, putting a JavaScript syntax error on every table: 14 console errors, invisible to 878 passing tests.
**Owner decision:** `public/build` was un-ignored and committed because the acceptance criteria required it, reversing a deliberate convention; the entry flags the resulting stale-asset churn.
**Tests after:** 878 tests pass (was 825).

### #017 - 2026-10-02, staff dashboard

**Goal:** Turn the staff dashboard from four numbers and a stale notice into a working Secretary screen with an Admin-only strip.
**Prompt:** Asked for the "still being built" notice deleted, a greeting, stat row, needs-attention grid, ready-for-pickup list, relief progress, quick actions and an Admin strip, at most one query per widget.
**Outcome:** The dashboard was rebuilt and verified in a real browser on both development data and a genuinely empty database.
**Problems and fixes:**
- `batchProgress()` assigned instead of accumulating, so the second row overwrote the first.
- "Oldest waiting 3 days" always read 0, because `Carbon::diffInDays` is signed and the arguments were the wrong way round.
- A stat-card assertion passed for the wrong reason, since the component renders value before label; it was replaced with a DOM parse verified against a fixture.
- `submitted_at` does not exist on `document_requests`, caught with `Schema::hasColumn`.
**Owner decision:** `ROLES.md` could not be obeyed because it did not exist, so route middleware was used instead and the Admin strip gated on `role === User::ROLE_ADMIN`.
**Tests after:** 914 pass (was 878).

### #018 - 2026-10-02, Kagawad area

**Goal:** Make the Kagawad area clean and phone-friendly for field use, without widening Kagawad permissions.
**Prompt:** Asked for the dashboard, relief list and checklist rewritten, the stale Captain copy removed, a minimal sidebar with a "Still to collect" badge, and 44px tap targets at 375px.
**Outcome:** Three screens rewritten with an unassigned-sector component and a collected badge; verified across 21 page, width and role combinations in Chrome.
**Problems and fixes:**
- Three separate Alpine syntax errors - a statement in `x-init`, PHP concatenation in JS, a `//` comment in `x-data` - each fixed with an IIFE.
- `x-staff.table` emitted two `class` attributes, so a caller's class was silently dropped.
- "Mark collected" was a submit button, making an irreversible record a single unconfirmed click; it now reveals an inline Confirm with Cancel.
- The whole phone navigation did not exist below 768px, leaving a Kagawad unable to reach any page.
**Owner decision:** No Undo was built, because `ROLES.md` did not exist and there is no undo endpoint; the phone navigation was built for every staff role, not Kagawad only.
**Tests after:** 947 pass (was 914).

### #019 - 2026-10-02, admin pages

**Goal:** Make the five Admin-only pages consistent with the new components and with each other.
**Prompt:** Asked for the zero role counts fixed with a failing-first test and clickable cards, 17 stacked sector form-cards replaced by a table, Settings grouped with a dirty save bar, Audit Logs filters reworked and a System Status summary added.
**Outcome:** All five pages rebuilt and verified in Chrome at 1440px with zero console errors.
**Problems and fixes:**
- The sectors page issued 71 queries for 17 sectors through a check in the view loop; it is now two subselects on one query.
- A per-row disclosure used `x-show` on a sibling row, out of Alpine scope: 17 page errors while the page still looked right.
- Three silent Alpine traps in the Settings dirty bar, including a double quote inside `x-data` that truncated the literal and `$el` meaning the button rather than the component root.
**Owner decision:** The instruction to report if `ROLES.md` allowed a Secretary into an admin page could not be carried out, because `ROLES.md` did not exist.
**Tests after:** 990 pass (was 947).

### #020 - 2026-10-02, admin close-out

**Goal:** Close out the previous admin work with six code fixes and five browser checks reported pass or fail.
**Prompt:** Asked for the admin strip linked to all five pages, seconds shown on Last checked, two tests renamed and two added, plus Playwright checks with console error counts.
**Outcome:** Nine browser checks, eight pass and one partial, all with zero console errors; the modal focus item was reported rather than worked around.
**Problems and fixes:**
- Three modal triggers dispatched `open-modal` with no Alpine scope, so clicks did nothing with zero console errors; each now carries `x-data="{}"`.
- A status-grid test asserted five headings existed somewhere, true of a layout that does not exist, so it was renamed and now asserts DOM membership.
- A test named for five admin pages asserted only three.
**Owner decision:** Modal focus was left unfixed, because the fix means editing a shared component or building a staff variant - reported as the owner's design decision.
**Tests after:** 992 pass (was 990).

### #021 - 2026-10-02, dialogs and audit label

**Goal:** Give staff dialogs focus management through a staff-only variant, and label the missing `request.submitted` audit action.
**Prompt:** Asked for a staff confirm-dialog forwarding `focusable` with shared components left byte-for-byte unchanged, all staff uses switched, and the one approved model line added.
**Outcome:** `x-staff.confirm-dialog` added and all eight staff uses switched; focus verified in Chrome for three dialogs with zero console errors.
**Problems and fixes:**
- Three more inert triggers - close a batch, reject a request, reject a registration - dead with no console error, each now carrying `x-data="{}"`.
- `x-modal` never returned focus after Escape, so the wrapper remembers the active element and restores it.
- Both new tests were mutation-checked and fail when the fix is reverted.
**Owner decision:** The prompt carried an explicit approved single exception to the do-not-edit rule on `app/Models`, limited to one line in `AuditLog::actions()`.
**Tests after:** 1007 pass (was 992).

### #022 - 2026-10-02, Kagawad sector residents

**Goal:** Give Kagawads a read-only list of their own sector's residents with relief status, plus a batch selector, a filter and search.
**Prompt:** Asked for `GET /kagawad/residents` inside the existing `role:kagawad` group with sector scoping applied first, and a copy fix to the unassigned-sector wording.
**Outcome:** Another sector's household is absent from the page source rather than merely unlinked; read-only structurally, with 71 of 71 browser checks passing at three widths.
**Problems and fixes:**
- "Still to receive" counted people who had already collected, because the `whereExists` had no status predicate.
- Every row showed the first checklist row's status, because two `addSelect` subqueries were uncorrelated; all page-level assertions still passed and a screenshot caught it.
**Owner decision:** The Captain wording was restored, reversing the previous entry, on the `ROLES.md` decision that the Captain is the Admin; editing the shared `RoleNavigation` was approved and the now-stale rule line was flagged for the owner to amend.
**Tests after:** 1036 pass (was 1033), 1 skipped by design.

### #023 - 2026-10-02, staff verification close-out

**Goal:** Verify the staff polish against a resident and public regression guard, fix the audit's open defects, and finish the documentation.
**Prompt:** Asked for a regression guard over the staff diff, four specific defects fixed, the permissions matrix closed out, ID-photo safety verified, and the README and docs replaced.
**Outcome:** 400 of 400 browser checks with zero console errors; four defects fixed; an independent roles matrix test added over 56 role-guarded routes.
**Problems and fixes:**
- The dead title block in the layout had to be removed in the same change, or all 22 pages would carry a second `h1`.
- No sidebar item marked the current page on any detail or form page, because the pattern was only ever set in the resident bar.
- The Admin demotion message understated the rule, which refuses every demotion from `admin`.
- Three of six initial browser failures were the AI's own assertions being wrong, not the application.
**Owner decision:** One resident/public guard hit - the owner-approved `AuditLog` line - was reported as such, and four deferred findings were left alone because three of them change markup the owner may want to shape differently.
**Tests after:** 1176 pass (was 1036), 7623 assertions, 1 skipped by owner decision.

### #024 - 2026-10-02, sidebar, status removal, badges

**Goal:** Fix the scrolling-away sidebar, remove System Status completely, and add count badges to sidebar destinations.
**Prompt:** Asked for a fixed aside, the status route, controller, view and nav entries deleted, and badges computed in one place, noting that `APP_DEBUG` then has no screen reporting it.
**Outcome:** The aside is fixed and scrolls internally; System Status fully removed; badge counts centralised in `NavigationBadges`.
**Problems and fixes:**
- A flex child defaults to `min-height: auto`, which pushed the user block off screen instead of scrolling; `min-h-0` was needed on the nav.
- Admin links render in the second nav loop, so the first version badged only the main loop and the Sectors badge silently never appeared.
- The badge memo was keyed by user id alone and leaked across requests inside one PHP process; it is now scoped to the `Request`.
**Owner decision:** The brief asked for badges "in the mobile drawer"; there is no drawer, and the phone bar was kept rather than inventing one to satisfy the wording.
**Tests after:** 1175 pass (1174 plus 1 skipped by owner decision), 7603 assertions.

### #025 - date not recorded, staff grouping and test database

**Goal:** Fix the Admin sidebar grouping, explain the 17 sector rows in the test database, and close the todo list.
**Prompt:** Asked, staff area only, for Admin headings of Workspace, Administration and My Account, the source of the 17 sectors traced with the workarounds removed, the development database untouched, and the Admin checked in a browser at 1280.
**Outcome:** The 17 sectors proved to be the schema, not litter - a migration inserts the puroks - so the test database already starts empty of anything a test creates.
**Problems and fixes:**
- `availableSidebarGroupsFor()` folded every non-main group into one bucket, discarding the Captain's admin group; three buckets with map-driven headings replaced it.
- A stray `</div>` closed the nav early, pushing the user block out of the viewport at 1280x500; a test now pins the shell.
- A test helper that truncated the counted tables was hiding the real baseline, so it was removed.
- The AI read a tag-balance warning as a false positive when it was a real unbalanced tag.
**Owner decision:** The only correct change was to the tests, not the schema, and `bdrpis` was only ever read.
**Tests after:** 1201 pass (1200 plus 1 skipped by owner decision), 7707 assertions.

### #026 - date not recorded, filters, Font Awesome, Actions column

**Goal:** Make every filter default to All, self-host Font Awesome for staff only, add a staff-only icon action, and replace clickable rows with an Actions column.
**Prompt:** Asked for those four staff-area changes finished with tests, a build, the diff guards and the log entry, with no public or resident changes.
**Outcome:** Font Awesome shipped as its own Vite entry, public and resident pages load no icon font, and 26 browser checks passed.
**Problems and fixes:**
- Table alignment had been reported fixed twice and was not: the real causes were `align => 'right'` on a column, `text-right` on numeric cells and `justify-end` on actions.
- The Role filter always showed "Resident", because the loop reused the value variable, so every option was compared with the last one.
- Four filters could not narrow at all, because `is_int($key)` cannot tell a slug list from an id-keyed map; `array_is_list()` decides the shape now.
- A `$dispatch` call dropped its Blade braces, so `$announcement->id` reached the browser as text and threw a page error with no failing test.
**Owner decision:** Only `solid.css` shipped, on the reasoning that one weight is one font file.
**Tests after:** 1273 pass (1272 plus 1 skipped), 11189 assertions.

### #027 - date not recorded, favicon and Services links

**Goal:** Put a favicon on every page and make two Services page links actionable for a guest - and only those two changes.
**Prompt:** Asked for those two changes under an owner-approved exception to the scope rules, with no other page changing shape.
**Outcome:** Five documents each gained one line; the favicon and apple-touch-icon are byte-identical to the Zone 1 logo; on Services only the `:href` expression changed.
**Problems and fixes:**
- The login and register pages are standalone documents with their own `<head>`, so a layout-only include would never reach them; all five were edited.
- `public/favicon.ico` is 0 bytes, which would have hidden a broken icon, so the test asserts the PNG is really served.
- A shell heredoc wrote literal backspace bytes into a test, making its own predicate match nothing.
- The suite passed unchanged at 1273, which meant the behaviour was entirely untested; a new test closes that gap.
**Owner decision:** The owner explicitly opened the public and resident areas for this task and nothing else, and a scope audit confirmed compliance.
**Tests after:** 1302 pass (1301 plus 1 skipped), 11307 assertions.

### #028 - date not recorded, resident record and correction

**Goal:** Build a staff-only resident detail and correction screen for the Secretary and Admin, with an audit entry per correction.
**Prompt:** Asked for a five-card record page, an Edit action on every directory row and a self-auditing correction form, with the resident and public areas read-only except the wizard's existing validation rules.
**Outcome:** The record page, the Edit action and the correction form shipped; the build changed by a removal only, with zero added Tailwind definitions.
**Problems and fixes:**
- Two of the brief's premises failed: `users` and `resident_profiles` have no phone column at all, and the wizard uploads an ID photograph rather than capturing the number.
- `household_number` is indexed but not unique, so two profiles could collide on the next relief batch; a unique check was added.
- Watching only the profile's `updated_at` left every name and date of birth overwriteable, because the form writes five columns on `users`.
- Two concurrency tests would have passed while doing nothing, because MySQL `DATETIME` has no sub-second part.
**Owner decision:** The national ID was therefore not editable, by the brief's own conditional rule, and no phone column was added because `User.php` was off limits.
**Tests after:** 1349 pass (1348 plus 1 skipped), 11567 assertions.

### #029 - date not recorded, archive and restore

**Goal:** Give the office a way to close a resident account that is not a delete, with everything restorable.
**Prompt:** Asked for archive rather than delete, so the profile, household number, document requests, relief rows and audit history all survive and can be restored, and the household number stays spoken for.
**Outcome:** Four archive columns on `users`, sign-in refused and open sessions killed; verified in Playwright at 375px and 1280px with zero problems.
**Problems and fixes:**
- Archived households were still editable by URL; `edit` and `update` now refuse with 409 as well as removing the control.
- An archived resident with the correct password was shown nothing, because the login page rendered no error block; one was added at 9.16:1 contrast.
- A test compared Eloquent models through session serialisation, so it failed without saying anything true; the payload is now plain scalars.
- A known-gap test written earlier to be inverted was inverted once the owner approved the guest-view change.
**Owner decision:** Editing a guest view was outside scope, so it was put to the owner and approved as an exception; `session('status')` flashes remain unrendered and are not claimed as fixed.
**Tests after:** 1,410 tests, 1,409 passed, 1 skipped, 12,065 assertions.

### #030 - date not recorded, phone-usable tables

**Goal:** Make every staff, Kagawad and Admin list table usable on a phone, measured rather than eyeballed.
**Prompt:** Asked for no sideways page scrolling, every column still named and every control still reachable, checked by measurement.
**Outcome:** `x-staff.table` demanded 736px at every width; on a 375px phone the page reached 775px and the Actions column sat at x=792. Nine pages were affected.
**Problems and fixes:**
- The first measurement was wrong: `scrollTo` reported 296px of overflow that `body { overflow-x: hidden }` makes impossible for a person to perceive, so the harness now sends a real wheel gesture.
- `contain`, `transform` and `overflow-x: clip` were each tried and are all wrong, because a fixed modal rendered from inside a table cell would be trapped in a 462px column.
- A `colspan` cell holding an entire edit form was being laid out as a grid, which would have squeezed a rename form into the label column.
**Owner decision:** The shared `data-table` component was left untouched so no resident, guest or public page changed shape, and in-container scrolling was kept where a real table is genuinely wider.
**Tests after:** 1,440 tests, 1,439 passed, 1 skipped, 12,383 assertions.

### #031 - date not recorded, unclaimed documents

**Goal:** Stop ready-for-pickup documents that nobody collects from tying up a request slot forever.
**Prompt:** Asked for an unclaimed workflow: 14 days ready before it can be marked unclaimed, three unexcused strikes in 180 days pausing new requests 90 days from the newest, a 30-day slot hold, and an excuse that lifts a suspension once strikes drop below three - with the figures in config, read at runtime.
**Outcome:** A config file, migration, derived policy, suspension refusal in the form request, staff and resident view changes and 42 new tests; browser-verified at both widths.
**Problems and fixes:**
- Counting strikes on `status = unclaimed` silently erases the record when the resident finally turns up, so strikes key on `unclaimed_at` as an event.
- A resident who collected late had no excuse button, though the route would have accepted the POST.
- A fixture-cleanup `DELETE` matched `BRGY-2026-2325` and destroyed two development rows; one was rebuilt from known details and the other was not, because inventing a plausible row is worse than an empty table.
**Owner decision:** No reminder email was built, because no mail exists in this codebase; the dialog says so outright.
**Tests after:** 1,485 tests, 1,484 passed, 1 skipped, 12,580 assertions.

### #032 - date not recorded, encrypted digital copies

**Goal:** Let a resident who cannot reach the hall download their own document, without handing out a copy anyone else can open.
**Prompt:** Asked for a password built from the resident's own facts and nothing stored, valid only while the request is ready, AES-256 encryption including metadata with a fresh random owner password per download, and office visibility through one deliveries row and one audit entry per download.
**Outcome:** One dependency, one migration, two services and a deliveries table, with 67 new tests; encryption verified by inspecting bytes and by opening generated PDFs with the right password and being refused otherwise.
**Problems and fixes:**
- A fresh `composer install` produced a library that threw `unable to read file: helvetica.json` on first use, so fourteen font metrics were converted once and committed.
- An unclosed `<font>` tag silently clipped the signature block while the PDF still rendered and opened; it was found by extracting and bisecting the PDF's text.
- The staff queue had a pre-existing N+1 on the household profile, taking twenty rows from 29 queries down to 14.
**Owner decision:** Counting a download as collection ships off by default; when enabled, the claim is written only from Ready and the audit entry names the channel.
**Tests after:** 1,553 tests, 1,552 passed, 1 skipped, 12,776 assertions.

### #033 - date not recorded, email verification

**Goal:** Make the verification email a letter from the barangay, extend the link to a day, and stop a wrong App Password destroying a registration.
**Prompt:** Asked for new wording and sender, a 24-hour link, a failure path that does not roll back a committed registration, a verify page truthful on both paths, and a Resend that survives broken mail settings.
**Outcome:** Seven files plus a 53-test file; the journey walked twice in Chromium against the log mailer, and the database was checked afterwards rather than inferred from the redirect.
**Problems and fixes:**
- `auth.verification.expire` was absent, so Laravel's sixty minutes applied.
- The `Registered` event was dispatched inside the transaction closure, so one wrong App Password rolled back a succeeded registration and showed the resident a 500.
- The page said "clicking on the link we just emailed to you" on the failure path, where no link had been sent.
- The first mail-failure test used a mailer name that was never declared, so every test passed while none tested what it claimed.
**Owner decision:** A failed send redirects to the confirmation page, where the message and the Resend button both sit, rather than bending flash lifetime; no sender address was invented.
**Tests after:** 1,607 tests, 1,606 passed, 1 skipped, 12,919 assertions.

### #034 - date not recorded, seeded residents removed

**Goal:** Remove the seeded residents and everything that exists only because of one, then prove the empty state in a browser and in the database.
**Prompt:** Asked for resident seed data and its dependents removed, non-seeded residents left alone, and proof from a fresh seed plus real browser passes.
**Outcome:** The resident seeder, its call and the two resident logins in the console output are gone; a fresh seed gives residents 0, profiles 0 and requests 0, with sectors 17, settings 11 and announcements 3 intact, and no test file changed.
**Problems and fixes:**
- The row at the seeded id was not the account its email claimed: it was the seeded resident renamed through the app's own edit screen, which an audit row records, so ids came from the audit and a dump was taken first.
- The suite's `RefreshDatabase` had emptied the test database after seeding, so logins silently failed and pages redirected to sign-in; the first browser pass misread that as a missing asset build. Foreign keys then handled the ordering: profiles and requests cascade, `audit_logs.user_id` is `SET NULL`, so audit rows outlived their author with `user_name` intact.
**Owner decision:** The audit page still shows "Someone removed" because reading the stored name needs a staff-view edit that was out of scope, and 4,533 unowned test files in private storage were counted rather than deleted.
**Tests after:** 1607 tests, 1606 passed, 1 skipped, 12919 assertions - the same figure as before the change.

---

## 5. Lessons learned

1. **Passing tests do not prove the UI works.** A Blade comment inside an `x-data` attribute put a JavaScript syntax error on every staff table: 14 console errors and 878 green tests (#016). Three modal triggers dispatched an event outside any Alpine scope and were inert with no console error at all (#020, #021).
2. **Dead triggers disguise themselves as styling.** `x-on:click` outside `x-data` does nothing silently; "Mark collected" hid an irreversible record behind one unconfirmed click (#018); the New Request button was never wired and the lock dialog was inert rather than invisible (commits `485a279`, `a279f21`).
3. **Measurement beats inspection, but the first measurement can be wrong in both directions.** Offset cells were recorded as "not reproduced" by two audits and were really `th[scope="row"]` (#016); a table's misalignment was reported fixed twice and was not (#026); a phantom 296px of overflow came from `scrollTo` against `overflow-x: hidden` (#030).
4. **When the tool and the reasoning disagree, work out which is lying.** A tag-balance warning dismissed as a false positive was a real unbalanced tag (#025, #026), and two browser failures were the audit script's fault rather than the app's (#025, #026).
5. **A test can pass for the wrong reason.** The badge test asserted class strings in HTML while the utilities never reached the stylesheet (#013); the dashboard stat-card assertion read the next card's number (#017); a session assertion compared serialised models (#029); the mail-failure test used an undeclared mailer (#033). Each is now mutation-checked or parses the DOM.
6. **Read the schema before designing the guard.** Guarding a column that does not exist guards nothing (#028); two column defects were fixed at the wrong layer (#026); two concurrency tests would have passed while proving nothing (#028).
7. **Config files must be in the Tailwind content globs.** Class names held in `config/bdrpis.php` compiled to nothing, and three icon badges would have shipped with no background colour (#013).
8. **Migrations missed on the development database are real damage.** Badge fixtures replaced the demo sectors, residents, requests and batches, so the development database had to be re-seeded (#024), and an over-broad fixture `DELETE` destroyed two live rows (#031).
9. **Treat the shared-file boundary as a design constraint.** Every staff need was met with a staff variant rather than an edit in place (#016, #021) - at the cost of duplicating a component that is already tested.
10. **Keep policy numbers in one place.** Business figures belong in config and `ROLES.md`, never inside a test that would then pass against whatever the config happens to say (#031).

---

## 6. Known limitations and deferred items

**Open in the application**

- The audit log page shows "Someone removed" instead of the stored `user_name`, because the view reads the relation; fixing it needs a staff-view edit that was out of scope (#034).
- Deferred and still reproducible: F4, F5/M1/M2, F10 and F11 (#023). F10 is Settings as one ungrouped form; F11 is a "Verified" column that means nothing for some roles (#025, #026).
- `sidebarFor()` returns an empty array for a role outside the four constants - unreachable today (#023).
- With System Status removed, nothing in the running application reports whether `APP_DEBUG` is on; the entry says this belongs in a deploy checklist (#024).
- Modal focus is not returned when a dialog is dismissed by clicking the backdrop (#021).
- `ROLES.md` decision 1 says the Captain gets everything a Secretary has, but "the Captain cannot open a Kagawad's area" is not yet true anywhere (#022).
- `sectors.kagawad_user_id` is `UNIQUE`, so a Kagawad can hold at most one sector and two end-to-end tests are skipped by design (#022).
- The Kagawad checklist filters client-side; at several hundred rows per sector it needs a server-side filter (#018).
- Six staff views render `session('success')` alongside the layout, showing the flash twice (#018).
- Directory and record pages scroll horizontally at 375px because the shared staff table sets `min-w-[46rem]`; reported, and off limits to those tasks (#028, #029). The 375px resident queue has the same pre-existing cause (#031).
- `session('status')` flashes - password reset, e-mail verification - are still not rendered on the login page (#029).
- A resident with no birthdate on file, or a last name with no letter, gets no digital copy and is sent to the hall (#032).

**Documentation, assets and dead ends**

- Five guest pages still render the title `BDRPIS - BDRPIS` because none passes a `:title` (#011).
- Removing the homepage sections dropped five anchors that inbound links may still use: `#how-it-works`, `#community`, `#officials`, `#transparency`, `#contact` (#012).
- The desktop header omits Officials, Relief Transparency and Track a Request, and is 80px wider than the page content at 1536px and above (#003, #004, #005).
- "Verify a Resident" sends a guest to the login page; a genuine public lookup needs an owner decision because it has privacy implications (#013).
- The About page's demographic figures are unverified static values in `config/bdrpis.php` (#009).
- `x-icon` still falls back to the document glyph for an unknown name without raising an error (#013).
- `public/build` is now tracked, so each rebuild that changes CSS leaves a stale hashed asset beside the old one (#016).
- `layouts/guest.blade.php` is unused by any current route, so its favicon line is unverified by any rendered page (#027).
- `login.jpg` is 1797KB for an above-the-fold background that every failed login re-requests (#011).

**Data deliberately left in place**

- 4,533 files (8.4 MB) of test leftovers in `storage/app/private/id-photos/`: 4224 `demo-*.png`, 304 `sweep-*.png` and 5 random-named files. Counted, not deleted (#034).
- One development row destroyed by fixture cleanup was never reconstructed, because inventing a plausible row would be worse than an empty table (#031).
- One test remains skipped by owner decision throughout #022 to #026.

---

## 7. Human oversight

The owner was not a passive recipient of output. These are the recorded moments of review,
correction, rejection or approval.

- **The scope rules came from the owner.** The hard scope rules in prompt #015 - no public or
  resident edits, staff variants for shared files, no new dependencies, no behavior change
  without approval - were written by the owner and codified in `.ai/rules/staff-polish.md`.
- **The Captain question was settled by the owner, not the code.** There is no `captain` role.
  `ROLES.md` decision 1 states the Captain *is* the Admin, which is why #022 restored the
  Captain wording that #018 had removed.
- **The archive design was the owner's.** #029 asked for archive rather than delete, with the
  household number staying spoken for; when the AI raised that the schema made that risky, the
  brief's own intent settled it.
- **A premise in the brief was allowed to fail the audit.** #028 asked for a contact-number
  field and made the national ID editable "unless the audit shows the wizard collects it as
  plain data". Neither premise held, so the national ID was not made editable.
- **The 90-day block figures are the owner's.** #031's brief set three strikes in 180 days
  pausing new requests for 90 days from the newest, with an excuse lifting it. The AI's
  judgement was to keep those numbers out of the test file entirely.
- **Work was declined, not just approved.** #023 left four deferred findings unfixed because
  three of them change markup the owner may want to shape differently, and left F5/M1/M2 for
  one decision rather than three patches.
- **The owner reversed an AI decision.** The search bar shipped disabled in #003; the user
  chose to remove it entirely in #005.
- **The owner overrode an explicit requirement.** #011's prompt required a Google sign-in
  button; on the user's instruction it was dropped, and a test now asserts it cannot reappear.
- **The owner granted named exceptions, each narrow.** `RoleNavigation` for staff entries
  (#022), one line in `AuditLog::actions()` (#021, #023), the guest login error block (#029),
  the favicon and Services links (#027), the wizard's existing validation rules (#028), and
  four files in #029 - each recorded as an approved exception rather than done quietly.
- **The owner unblocked a six-prompt blocker.** `ROLES.md` did not exist for prompts #015
  through #022, so the permission matrix could not be produced; the owner supplied it as the
  confirmed source of truth at #023.
- **Development data was restored by hand.** #024 re-seeded the development database after
  badge fixtures destroyed the demo sectors, residents, requests and batches.
- **A destructive mistake was reported, not hidden.** #031 records that a fixture-cleanup
  `DELETE` matched `BRGY-2026-2325` and destroyed two development rows; one was rebuilt from
  known details and the other deliberately not.