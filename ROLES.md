# ROLES.md

> **The permission model for this barangay. Owned and confirmed by the barangay.**
>
> The route tables were read out of the running application — the route table, the
> middleware on each route, the guards inside controllers and form requests, the
> data scoping in each query, and the sidebar each role is given. The
> [Owner decisions](#owner-decisions) below are the specification; the tables are
> the implementation; [Code vs ROLES.md](#code-vs-rolesmd) records the one place
> the code has not caught up yet.

---

## Owner decisions

These are the specification. Everything else in this file is either how the code
implements them or a note on how well it does.

### 1. Admin is the Barangay Captain

**Admin IS the Barangay Captain.** An Admin may do everything a Secretary can do,
plus the Admin-only pages: User Accounts, Sectors & Kagawad, Settings and Audit
Logs. *(System Status was a fifth and has been removed — see
[Removed](#removed-system-status).)*

The code matches this exactly. The staff routes are declared as
`role:admin,secretary` for the shared operational pages and `role:admin` for the
five Captain pages, and `RoleNavigation` gives the Admin the Secretary's list plus
those five. There is no capability an Admin lacks that a Secretary has.

This also settles a question the code itself could not answer: the old interface
copy referred to "a Captain" assigning a Kagawad. There is no `captain` role in
`User::roles()` — **the Captain is the Admin**, and Sectors & Kagawad is
Admin-only because assigning a Kagawad is the Captain's job.

### 2. A Secretary owns announcements outright

**A Secretary may create, edit, publish, pin and DELETE announcements.** All five
are intended, not oversights.

All five are in the `role:admin,secretary` group, so this is already the case:
`staff.announcements.create`, `.edit`, `.update`, `.publish`, `.pin`, `.unpin`
and `.destroy`. An Admin can do all of them too, by decision 1.

### 3. A Kagawad sees only their own sector, read-only

**A Kagawad sees only their assigned sector(s).** Within that, a Kagawad may
**view the residents of their own sector(s), together with their relief status per
batch. It is read-only: no editing, and no data from any other sector.**

The scoping half is built and enforced today. The resident view is the one piece
that does not exist yet — see [Code vs ROLES.md](#code-vs-rolesmd).

---

## How to read this

- **Allowed** — the route's middleware admits this role, and the specification
  permits it.
- **Denied** — the route exists and the middleware returns **403** for this role
  (`EnsureUserHasRole:40`). A guest hitting a role-guarded route is redirected to
  `/login` instead (`EnsureUserHasRole:36`), which is why "Guest" is a separate column.
- Route names are the Laravel names, so they can be checked with
  `php artisan route:list --name=...`.

### Where authorisation actually lives

There is **no `app/Policies` directory and no Gate definition anywhere in the
application.** Nothing uses `@can` or `@role` in a Blade template. The entire
permission model is three things:

1. **Route middleware** — `role:admin`, `role:admin,secretary`, `role:kagawad`,
   `role:resident` (`App\Http\Middleware\EnsureUserHasRole`).
2. **Guards inside controllers and form requests** — `abort_if` and
   `$validator->errors()->add()` in five controllers and one FormRequest, listed
   under [Guards](#ad-hoc-guards-inside-controllers).
3. **Data scoping in the query** — a Kagawad's rows are filtered to their own
   sectors, and a resident's to their own household. A route being Allowed does
   not mean every row behind it is visible; see [Data scoping](#data-scoping-by-role).

### The `restricted` flag is enforced by the role middleware

A restricted account is refused with 403 on every route that carries `role:`
(`EnsureUserHasRole:55-57`). It keeps only `account.*`, `password.*` and `logout`,
so a frozen account can still change its own password and sign out.

Those three deliberately do **not** carry `role:` middleware, which means the
restriction check does not run on them. That is the intended escape hatch, and the
middleware says so — but it also means restriction enforcement is coupled to route
middleware, which is noted at the end.

---

## Route inventory by area

### Public — no authentication (`/`, 9 routes)

| Route | Method | URI | Admin | Secretary | Kagawad | Resident | Guest |
|---|---|---|---|---|---|---|---|
| `home` | GET | `/` | Allowed | Allowed | Allowed | Allowed | Allowed |
| `services.index` | GET | `/services` | Allowed | Allowed | Allowed | Allowed | Allowed |
| `about` | GET | `/about` | Allowed | Allowed | Allowed | Allowed | Allowed |
| `contact` | GET | `/contact` | Allowed | Allowed | Allowed | Allowed | Allowed |
| `announcements.index` | GET | `/announcements` | Allowed | Allowed | Allowed | Allowed | Allowed |
| `transparency.index` | GET | `/transparency` | Allowed | Allowed | Allowed | Allowed | Allowed |
| `public.directory` | any | `/directory` | Allowed | Allowed | Allowed | Allowed | Allowed |
| `requests.track` | any | `/track` | Allowed | Allowed | Allowed | Allowed | Allowed |
| — | GET | `/up` | Allowed | Allowed | Allowed | Allowed | Allowed |

`/directory` and `/track` are **permanent redirects** (to `/about#officials` and
`/resident/requests`), which is why they answer every HTTP verb.

`/transparency` has no middleware and its controller reads no user, but the view
wraps the only personalised part in `@auth` (lines 34–47), so a guest is fine.

### Guest-only — sign in and register (8 routes)

| Route | Method | URI | Admin | Secretary | Kagawad | Resident | Guest |
|---|---|---|---|---|---|---|---|
| `login` | GET, POST | `/login` | Allowed | Allowed | Allowed | Allowed | Allowed |
| `register` | GET, POST | `/register` | Allowed | Allowed | Allowed | Allowed | Allowed |
| `password.request` | GET | `/forgot-password` | Allowed | Allowed | Allowed | Allowed | Allowed |
| `password.email` | POST | `/forgot-password` | Allowed | Allowed | Allowed | Allowed | Allowed |
| `password.reset` | GET | `/reset-password/{token}` | Allowed | Allowed | Allowed | Allowed | Allowed |
| `password.store` | POST | `/reset-password` | Allowed | Allowed | Allowed | Allowed | Allowed |

All six are `guest` middleware, so a signed-in user of any role is redirected
away rather than shown a second login form.

**`/register` is open to the public, so anyone can create a Resident account.**
Verification is not self-asserted — a new Resident lands in
`verification_status = pending` — but account creation itself is ungated.

### Signed in, any role (11 routes, 10 rows — `password.confirm` answers two verbs)

| Route | Method | URI | Admin | Secretary | Kagawad | Resident | Guest |
|---|---|---|---|---|---|---|---|
| `logout` | POST | `/logout` | Allowed | Allowed | Allowed | Allowed | Denied |
| `password.update` | PUT | `/password` | Allowed | Allowed | Allowed | Allowed | Denied |
| `password.confirm` | GET, POST | `/confirm-password` | Allowed | Allowed | Allowed | Allowed | Denied |
| `verification.notice` | GET | `/verify-email` | Allowed | Allowed | Allowed | Allowed | Denied |
| `verification.verify` | GET | `/verify-email/{id}/{hash}` | Allowed | Allowed | Allowed | Allowed | Denied |
| `verification.send` | POST | `/email/verification-notification` | Allowed | Allowed | Allowed | Allowed | Denied |
| `dashboard` | GET | `/dashboard` | Allowed | Allowed | Allowed | Allowed | Denied |
| `account.edit` | GET | `/account` | Allowed | Allowed | Allowed | Allowed | Denied |
| `account.update` | PATCH | `/account` | Allowed | Allowed | Allowed | Allowed | Denied |
| `account.password` | GET | `/account/password` | Allowed | Allowed | Allowed | Allowed | Denied |

- `/dashboard` is a **signpost with no role guard**, by design ("the guard belongs
  on the destination rather than on the arrow"). It redirects by role
  (`EnsureUserHasRole::landingFor`): Kagawad → `kagawad.dashboard`, Admin and
  Secretary → `staff.dashboard`, everyone else → `home`.
- `/account` carries **no role middleware at all**, so all four roles reach it. It
  renders `account/edit.blade.php` for a Resident and `account/edit-staff.blade.php`
  for the other three, and `PATCH /account` writes only `name` and `email`.
  A changed email clears `email_verified_at`, so an unproven address cannot keep a
  session. It cannot change role or verification status — both are unreachable from
  this route.
- The five `verification.*` / `password.confirm` routes require only `auth`, not
  `verified`, because they are what a user needs *in order to become* verified.

### Staff — `role:admin,secretary` (31 routes)

| Route | Method | URI | Admin | Secretary | Kagawad | Resident | Guest |
|---|---|---|---|---|---|---|---|
| `staff.dashboard` | GET | `/staff/dashboard` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.verifications.index` | GET | `/staff/verifications` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.verifications.show` | GET | `/staff/verifications/{profile}` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.verifications.approve` | POST | `…/approve` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.verifications.reject` | POST | `…/reject` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.resident-verifications.id-photo` | GET | `…/id-photo` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.residents.index` | GET | `/staff/residents` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.residents.show` | GET | `/staff/residents/{profile}` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.residents.edit` | GET | `/staff/residents/{profile}/edit` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.residents.update` | PUT | `/staff/residents/{profile}` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.requests.index` | GET | `/staff/requests` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.requests.show` | GET | `/staff/requests/{documentRequest}` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.requests.review` | POST | `…/review` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.requests.ready` | POST | `…/ready` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.requests.claim` | POST | `…/claim` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.requests.reject` | POST | `…/reject` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.batches.index` | GET | `/staff/batches` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.batches.create` | GET | `/staff/batches/create` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.batches.store` | POST | `/staff/batches` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.batches.show` | GET | `/staff/batches/{reliefBatch}` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.batches.close` | POST | `…/close` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.batches.reopen` | POST | `…/reopen` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.announcements.index` | GET | `/staff/announcements` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.announcements.create` | GET | `/staff/announcements/create` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.announcements.store` | POST | `/staff/announcements` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.announcements.edit` | GET | `…/{announcement}/edit` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.announcements.update` | PUT | `…/{announcement}` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.announcements.publish` | POST | `…/publish` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.announcements.pin` | POST | `…/pin` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.announcements.unpin` | POST | `…/unpin` | Allowed | Allowed | Denied | Denied | Denied |
| `staff.announcements.destroy` | DELETE | `…/{announcement}` | Allowed | Allowed | Denied | Denied | Denied |

The nine announcement routes are a Secretary's to operate in full, **including
`destroy`** — that is decision 2, and it is intended. A Secretary can also verify
and unverify residents, which follows from Resident Verification being a
Secretary page.

### Staff, Admin only — `role:admin` (13 routes)

| Route | Method | URI | Admin | Secretary | Kagawad | Resident | Guest |
|---|---|---|---|---|---|---|---|
| `staff.accounts.index` | GET | `/staff/accounts` | Allowed | **Denied** | Denied | Denied | Denied |
| `staff.accounts.show` | GET | `/staff/accounts/{user}` | Allowed | **Denied** | Denied | Denied | Denied |
| `staff.accounts.role` | PUT | `…/{user}/role` | Allowed | **Denied** | Denied | Denied | Denied |
| `staff.accounts.restrict` | POST | `…/{user}/restrict` | Allowed | **Denied** | Denied | Denied | Denied |
| `staff.accounts.unrestrict` | POST | `…/{user}/unrestrict` | Allowed | **Denied** | Denied | Denied | Denied |
| `staff.sectors.index` | GET | `/staff/sectors` | Allowed | **Denied** | Denied | Denied | Denied |
| `staff.sectors.store` | POST | `/staff/sectors` | Allowed | **Denied** | Denied | Denied | Denied |
| `staff.sectors.update` | PUT | `/staff/sectors/{sector}` | Allowed | **Denied** | Denied | Denied | Denied |
| `staff.sectors.kagawad` | PUT | `…/{sector}/kagawad` | Allowed | **Denied** | Denied | Denied | Denied |
| `staff.sectors.destroy` | DELETE | `/staff/sectors/{sector}` | Allowed | **Denied** | Denied | Denied | Denied |
| `staff.settings.index` | GET | `/staff/settings` | Allowed | **Denied** | Denied | Denied | Denied |
| `staff.settings.update` | PUT | `/staff/settings` | Allowed | **Denied** | Denied | Denied | Denied |
| `staff.audit.index` | GET | `/staff/audit` | Allowed | **Denied** | Denied | Denied | Denied |

This is the Captain's boundary, and the four Admin-only screens are exactly the
four a Secretary cannot open. **Only the Captain can change another person's role,
restrict anyone, or touch a sector** — which is decision 1, and it is why a
Secretary who receives a new Kagawad hands them to the Captain to assign.

### Kagawad — `role:kagawad` (5 routes)

| Route | Method | URI | Admin | Secretary | Kagawad | Resident | Guest |
|---|---|---|---|---|---|---|---|
| `kagawad.dashboard` | GET | `/kagawad/dashboard` | Denied | Denied | Allowed | Denied | Denied |
| `kagawad.relief` | GET | `/kagawad/relief` | Denied | Denied | Allowed | Denied | Denied |
| `kagawad.relief.show` | GET | `/kagawad/relief/{reliefBatch}` | Denied | Denied | Allowed | Denied | Denied |
| `kagawad.relief.received` | POST | `…/received/{distribution}` | Denied | Denied | Allowed | Denied | Denied |
| `kagawad.residents.index` | GET | `/kagawad/residents` | Denied | Denied | Allowed | Denied | Denied |

A Kagawad can read only their own sector's checklist and mark only their own
sector's households collected. An **Admin or Secretary gets 403 here** — the role
areas do not overlap in either direction, which is the direct consequence of
decision 3 being read-only: a Kagawad neither needs nor gets staff pages. Marking
is refused with **409** if the batch is closed.

`kagawad.relief.received` is the only write a Kagawad has anywhere in the
application.

`kagawad.residents.index` is the read-only resident list decision 3 specifies:
residents of their own sectors, with relief status for a selected batch. It is
scoped in the query, shows only name / household number / sector / verification /
relief status, and has no write verb behind it. See
[Code vs ROLES.md](#code-vs-rolesmd).

### Resident — `role:resident` (8 routes)

| Route | Method | URI | Admin | Secretary | Kagawad | Resident | Guest |
|---|---|---|---|---|---|---|---|
| `resident.requests.index` | GET | `/resident/requests` | Denied | Denied | Denied | Allowed | Denied |
| `resident.requests.create` | GET | `/resident/requests/create` | Denied | Denied | Denied | Allowed | Denied |
| `resident.requests.store` | POST | `/resident/requests` | Denied | Denied | Denied | Allowed | Denied |
| `resident.requests.show` | GET | `/resident/requests/{documentRequest}` | Denied | Denied | Denied | Allowed | Denied |
| `resident.relief` | GET | `/resident/relief` | Denied | Denied | Denied | Allowed | Denied |
| `resident.verification` | GET | `/resident/verification` | Denied | Denied | Denied | Allowed | Denied |
| `resident.verification.update` | PATCH | `/resident/verification` | Denied | Denied | Denied | Allowed | Denied |
| `resident.verification.status` | GET | `/resident/verification/status` | Denied | Denied | Denied | Allowed | Denied |

`resident.requests.show` **redirects to the index** rather than rendering a page,
so the route name exists but there is no per-request screen and no ID to guess at.

### No role middleware at all

| URI | Method | Registered by | Notes |
|---|---|---|---|
| `/_boost/browser-logs` | POST | `laravel/boost` | Development tool. Flagged below. |
| `/storage/{path}` | GET, PUT | `config/filesystems.php` `local.serve = true` | Laravel's local-disk file server. |

---

## Data scoping, by role

A route being Allowed says nothing about the rows behind it.

**Kagawad — scoped to their own sectors, and only their own.**
`User::assignedSectorIds()` (`User.php:219`) resolves from
`sectors.kagawad_user_id`, and every Kagawad query passes it through
`ReliefDistribution::scopeForSectors()` (`ReliefDistribution.php:79`). A
distribution outside those sectors returns **404, not 403**, deliberately, so a
guessed id cannot confirm that the row exists — the reasoning is written at
`Kagawad/ReliefController.php:23`. An unassigned Kagawad's
`whereIn('sector_id', [])` matches nothing, so they see an empty list rather than
everyone's. `sectors.kagawad_user_id` is `UNIQUE`
(`2026_09_22_025934_create_sectors_table.php:15`), so a Kagawad holds **exactly one**
sector — which is the owner's decision, not a limitation. See
[Code vs ROLES.md](#code-vs-rolesmd).

**Resident — scoped to their own household.**
`resident.relief` queries
`scopeForHousehold($user->residentProfile->household_number)`
(`ReliefDistribution.php:95`); resident requests use `whereBelongsTo($user)`.
Nothing reads another household. Public pages never offer a household-number
lookup, and `/transparency` says why in the interface: the numbers are short
enough to guess.

**Staff — see everything, deliberately.** No sector filter anywhere in
`app/Http/Controllers/Staff`.

**Verification is not self-asserted.** A registered Resident starts
`pending`. `resident.verification.update` lets a Resident change their household
number, national/voter ID, address and sector, and doing so resets
`verification_status` to pending and `is_verified` to false. Only
`staff.verifications.approve` clears it.

---

## Ad-hoc guards inside controllers

Everything below sits *behind* a route that already passed `role:`. These are the
extra refusals, and they are the only place where the same action behaves
differently for different rows of the same table.

| Guard | Effect |
|---|---|
| `AccountController:89` | An Admin cannot change **their own** role — 422. |
| `AccountController:90` | An Admin cannot set **any** Admin to a lower role — 422. |
| `AccountController:129` | An Admin cannot restrict themselves — 422. |
| `AccountController:152` | An Admin cannot lift their own restriction — 422. |
| `AccountController:154` | Lifting a restriction on an unrestricted account — 409. |
| `SectorController:152` | Assigning a non-Kagawad to a sector — 422. |
| `SectorController:190` | A sector that still has households or relief entries cannot be deleted — redirected back with a message, naming both counts. |
| `AnnouncementController:178` | Unpinning something not pinned — 409. |
| `ReliefBatchController:142` | Closing an already-closed batch — 409. |
| `ReliefBatchController:163` | Reopening an already-open batch — 409. |
| `Kagawad/ReliefController:111` | Marking collected on a closed batch — 409. |

### Business rules enforced in a FormRequest

One more place holds real rules, and it is the one worth trusting most.
`StoreDocumentRequestRequest::withValidator()` refuses a document request with a
validation error rather than relying on a hidden button:

| Guard | Effect |
|---|---|
| `StoreDocumentRequestRequest:83` | The profile must be **verified**. A pending resident is told to get verified, not that their account is broken. |
| `StoreDocumentRequestRequest:92` | The account must not be **restricted**. |
| `StoreDocumentRequestRequest:103` | At most `OPEN_REQUEST_LIMIT` (3) open requests. |

The design is deliberate and the reason is written down at
`StoreDocumentRequestRequest:79-82`: the gate is the **profile**, not
`users.is_verified`. Each of the three is proven by posting directly, not by
checking that a button is absent — `a_pending_resident_cannot_submit`,
`a_restricted_resident_cannot_submit` and `a_fourth_open_request_is_refused` in
`tests/Feature/ResidentDocumentRequestsTest.php`.

Worth noting because it is the exception, not the rule: the *restriction* is
enforced twice — here and by the role middleware — while *verified* is enforced
only here. That is fine, but it means the two are not symmetric.

### What a staff member may correct about a resident, and what they may not

`UpdateResidentProfileRequest` is a **correction** form, not an account screen
and not an identity screen. The boundary is enforced by which fields the request
validates, not by which fields the view hides — a control that is absent from the
markup can still be posted, so absence is not the guarantee.

Editable, and exactly the eight fields the registration wizard collects for the
same person:

| Field | Stored on |
|---|---|
| `name`, `date_of_birth`, `gender`, `civil_status`, `nationality` | `users` |
| `address`, `sector_id`, `household_number` | `resident_profiles` |

Refused even when posted, because each is a decision rather than a correction and
each has its own audited screen:

| Field | Why not here |
|---|---|
| `email`, `password` | Account credentials; changing an address needs verification to run against it. |
| `national_or_voter_id` | **Not collected by the wizard at all.** The number is read off the uploaded document at review, so there is no wizard value to stay in step with. Displayed **masked to the last four** on the record page. |
| `id_photo_path` | The identity evidence. Substituting it is identity capture, and would leave a rejected document's photo and its rejection reason describing two different people. |
| `verification_status`, `rejection_reason`, `verified_at` | The verification decision. |
| `role`, `is_verified`, `restricted_until`, `assigned_sector_id` | Account administration, which the Admin area owns. |

Three rules that live with the request rather than in the controller:

| Guard | Effect |
|---|---|
| `ResidentProfileRules::uniqueHouseholdNumber()` | `household_number` must be unique across every **other** profile. The wizard cannot make this check — the row does not exist yet when it runs — but `relief_distributions` is unique on `(relief_batch_id, household_number)` and stores the number as a copy, so two profiles sharing one would silently collide on the next batch. |
| `UpdateResidentProfileRequest::withValidator()` | The submission's `profile_updated_at` and `user_updated_at` must match the rows as they are now. **Both** tables, because the form writes columns on each and watching one would leave every name and date of birth overwriteable. |
| `ResidentDirectoryController::update()` | A submission that changes nothing writes nothing, logs nothing, and flashes `No changes to save.` |

`ResidentProfileRules::shared()` is read by **both** `RegisterRequest` and
`UpdateResidentProfileRequest`, so the wizard and the correction form cannot drift
apart on what a valid value is. The wizard's rule list and key order are pinned by
`the_wizard_s_rule_list_is_unchanged`, and the two forms' rule arrays are compared
object-for-object by `the_staff_form_uses_the_wizard_s_own_rules_for_the_shared_fields`.

Every correction is audited as `resident.updated`, with the actor, the subject,
and the before/after of each changed field in `context`. Fields on
`UpdateResidentProfileRequest::redactedFields()` — the national ID, the ID photo
path, the email and the password — are recorded as `['changed' => true]` and never
in full. No correction has to be unique in the log to be findable: the audit is a
record of what staff did to a household, and an entry for a save that changed
nothing would be a record of how many times a button was pressed.

---

## What each role sees in the sidebar

From `App\Support\RoleNavigation::sidebarFor()`. Every role's list ends with
**My Account**, which is the one entry they all share.

- **Admin** — 11 entries in three headed groups. **Workspace**: the six a
  Secretary sees (Dashboard, Resident Verification, Residents, Document Requests,
  Relief Distribution, Announcements). **Administration**: **User Accounts**,
  **Sectors & Kagawad**, **Settings**, **Audit Logs**. **My Account**: My Account.
  The four Administration entries are exactly the four `role:admin` screens, so the
  menu, the middleware and decision 1 all agree.
- **Secretary** — 7 entries, no admin group: the six above plus My Account. All
  announcement capabilities, including delete, are reachable from here.
- **Kagawad** — 4 entries, still the shortest list: Dashboard, Relief
  Distribution, **My Sector Residents**, My Account. That third item is decision 3
  — the Kagawad's own sector residents, not the staff directory, which is
  `role:admin,secretary` and would render and then 403.
- **Resident** — no sidebar of its own. It gets the public navigation (Home,
  Services, About, Contact, Announcements, Transparency) plus its two portal
  destinations, a profile menu, and **no Dashboard entry**, because a Resident's
  home is the public home page (`RoleNavigation:91`).

`RoleNavigation::availableSidebarGroupsFor()` returns three buckets — `main`,
`admin` and `account` — and `layouts/navigation.blade.php` draws a heading per
bucket. A bucket is only drawn when it has entries in it, and the heading is only
drawn for a role whose sidebar is genuinely divided: an Admin labels all three
groups, while a Secretary and a Kagawad declare no `admin` bucket, so their
workspace links stay unlabelled and only My Account keeps a heading. Labelling that
lone run "Workspace" would be a heading that distinguishes nothing.

The phone bottom bar is built from the same three buckets
(`array_merge($groups['main'], $groups['admin'], $groups['account'])`), minus the
headings — a heading is a line of text the bar has no room for. So both surfaces
carry the same destinations: 11 for an Admin, 7 for a Secretary, 4 for a Kagawad.

**No mismatches.** Every sidebar entry is on a route its role can actually open,
and every admin-group entry is on a `role:admin` route. This is asserted rather
than assumed, in two layers because either alone leaves the same bug reachable:

- `tests/Feature/RoleNavigationTest.php` pins the exact lists — seven for a
  Secretary, "the Secretary's plus four" for an Admin, four for a Kagawad — and
  separately asserts that a Secretary is *not* given any admin-only route, that a
  Kagawad is *not* given any staff page, that a Kagawad is not offered the **staff**
  Residents page, the Verification queue or Document Requests, and that My Account
  is reachable by every role.
- `tests/Feature/StaffSidebarGroupingTest.php` reads the rendered DOM per role and
  pins which heading each destination sits under, so the buckets being correct
  cannot be separated from the headings being correct.

---

## Filters, and how a record is opened

### Every list shows everything until a filter is chosen

A staff page with no query string is not "a page with the default filter applied" —
it is a page showing every record there is. Two lists broke that and have been
fixed:

| Page | Filter | Was | Now |
|---|---|---|---|
| Resident Verification | Status | `pending` — the queue only | **All** (empty parameter) |
| Document Requests | Show | `queue` — submitted + under review only | **Everything** (empty parameter) |
| Relief Batches | Show | *(no control at all)* | **Everything**, and the control now exists |
| Announcements | Show | `all` sentinel | **Everything** (empty parameter; `?state=all` still resolves) |
| Residents | Sector | All sectors | All sectors *(unchanged; see below)* |
| User Accounts | Role | All roles | All roles *(unchanged; see below)* |
| Audit Logs | Action / Account / From / To | Every action / Anyone | *(unchanged)* |
| Sectors & Kagawad | Unassigned chip | Off | *(unchanged)* |
| Kagawad My Sector Residents | Relief status | All | All *(unchanged)* |
| Kagawad My Sector Residents | Batch | latest **active** batch | **kept** — see below |

The pattern: **the empty parameter means no filter, everywhere.** Document Requests
previously had both an `all` value *and* an absent filter, so "everything" had two
spellings and a bookmark of one did not mean the other.

**Ordering where a list used to default to the actionable filter.** Verification and
Document Requests now put the records needing a decision first, then newest. Relief
Distribution lists open batches before closed ones. Nothing is hidden.

**The Kagawad batch selector keeps defaulting to one batch.** Relief status belongs to
a batch — a household's "collected" is a claim about a particular handout — so
"all batches" is not a meaningful state for that column. The selector still opens on
the latest **active** batch, for the reason already recorded under
[Two judgement calls recorded](#two-judgement-calls-recorded).

**Four filters could not filter at all.** The Sector filter on Residents, the Account
filter on Audit Logs, and both the Sector and Batch filters on the Kagawad resident
list were built from `->pluck('name','id')`, and the select component decided that an
integer array key meant "this is a list of values". So the id was discarded and the
option rendered as `<option value="Purok 5">Purok 5</option>`; submitting it produced
`0`, which the controller read as no filter. They submit ids now, and
`StaffFiltersDefaultToAllTest` drives them by the value the rendered `<select>` sends.

### A row is opened from one control, and it says which record

Every staff, Kagawad and Admin table ends with an **Actions** column. Whole-row
clicking, the chevrons and the links on names and reference numbers are gone: they
gave three ways into one record and none of them named it — a `<tr>` is not focusable
and is not a link, the chevron was `aria-hidden`, and the link's text was the same
string as the cell it sat in.

Each action is an `<x-staff.icon-action>`: a Font Awesome glyph, `aria-hidden`, with
the accessible name naming the record it acts on, a tooltip, a 36x36 hit area (44x44
on the Kagawad checklist, which is used one-handed on a phone), a visible focus ring,
and red for the destructive one. A control that cannot be used is rendered
`aria-disabled` with its reason in text, not only in a tooltip.

**No Actions column on two tables, deliberately.** Audit Logs keeps its Context
disclosure — the row *is* the record, so an Actions column would hold a View button
that goes nowhere. The Kagawad resident list is read-only by decision 3 and has no
staff resident page a Kagawad may open.

**The Residents row carries two controls, not one.** View (`fa-eye`) and Edit
(`fa-pen`), each an `<x-staff.icon-action>` naming the resident in its accessible
name — "View Juan Dela Cruz" and "Edit Juan Dela Cruz". The rule above is about not
giving three ways into one record; a View and an Edit are two different
*decisions* about one record, and Edit has to be reachable from the list rather than
only from the record page a clerk has to open first.

Note the two icon systems are not interchangeable here. `<x-staff.icon-action>` takes
a Font Awesome name and renders `fa-solid fa-<name>`, so `pen` is correct. `x-btn`,
`x-alert` and `<x-staff.empty-state>` take an `<x-icon>` **SVG** name instead and
fall back silently to a document glyph for anything undeclared — so the same Edit
button uses `icon="edit"` on the record page. Both spellings are correct where they
appear; swapping one for the other draws the wrong glyph without any error.

### Font Awesome, staff only

`@fortawesome/fontawesome-free` is self-hosted through Vite as its own entry,
`resources/css/staff-icons.css`, which imports only `fontawesome.css` and
`solid.css`. It is listed in `layouts/app.blade.php` and **nowhere else**, so a
public or resident page never requests it. Verified: eight public and guest pages
and four resident pages load no `staff-icons` stylesheet and no icon font, and fetch
only `app.css`.

### Table alignment

Every cell is left-aligned, figures included, and the Actions column is left-aligned
too. This was reported fixed twice and was not. The earlier check read `text-align`
on `th` and `td` — which really was `left` — and missed that three other things were
centring the tables: an `align => 'right'` key on a column definition, `text-right`
on numeric cells, and `justify-end` on the Actions cells. That first key was a real
prop of `x-staff.table` and a **dead** one on the shared `x-data-table`, which reads
`$column['class']` instead — so the same key was honoured in one component and
silently ignored in the other. It is removed rather than fixed.

---

## Removed

### System Status

`staff.status.index` (`GET /staff/status`) has been **removed**, along with
`Staff\SystemStatusController`, `staff/status/index.blade.php` and
`App\Support\SystemHealth`. The sidebar entry, the phone-bottom-bar entry and the
Admin dashboard's System-health card went with it, so the Captain's admin-only
pages are four: User Accounts, Sectors & Kagawad, Settings, Audit Logs.

There is no longer a route, a controller, a view or a nav item for it, and the
`role:admin` group is now 13 routes rather than 14. `RolesMatrixTest` no longer
lists it, and `RoleNavigationTest` pins an Admin at 10 entries.

**Why it went:** the page's most prominent finding was the `APP_DEBUG` flag,
printed on a screen the Captain did not need to read, and a page whose main job was
to report on the system was one more thing to keep current. It also echoed a
warning that belongs in a deployment checklist instead.

> **This is a deployment note, not a page.** With the System Status page gone,
> **nothing in the running application shows whether `APP_DEBUG` is on.**
> `APP_DEBUG=true` in production is a real risk — it renders stack traces and
> environment values to whoever triggers an error — so it must be set to `false`
> before the first production deploy, and checked there. There is no longer a
> screen in the product that will tell you.

---

Behaviour that looks like a permission mistake on a first read, and is not. These
are settled by the [Owner decisions](#owner-decisions) above and are listed so the
next person reading the route table does not re-open them.

### A Secretary can delete a published announcement

`staff.announcements.destroy` is `DELETE` inside the `role:admin,secretary` group,
so a Secretary can permanently remove a notice the public is currently reading.
The audit log records *that* someone deleted it, not the notice itself, and
nothing in the interface can undo it.

**This is intended** — decision 2: a Secretary may create, edit, publish, pin and
**delete** announcements. Announcements are the Secretary's area, and deletion is
part of it. No guard is wanted here.

It is recorded only because it is the sharpest edge in the application: it is the
one destructive action available to the most numerous staff role, against content
with no copy. The contrast with the two neighbouring delete rules is deliberate,
not an inconsistency:

| | Who | Guarded? |
|---|---|---|
| Delete an **announcement** | Secretary or Captain | No — **intended** (decision 2) |
| Delete a **sector** | Captain only | Yes, refused while it still has households or relief entries (`SectorController:190`) |
| Remove a **person** | Captain only | No delete exists at all — accounts are *restricted*, which is reversible |

### Sectors and Settings belong to the Captain

- **Only the Captain can assign a Kagawad to a sector.** A Secretary who receives
  a new Kagawad cannot put them anywhere, and that Kagawad's dashboard stays empty
  until the Captain assigns them. **Intended** — decision 1, and decision 3 makes
  the assignment meaningful, because a Kagawad's entire remit is their sector.
- **Only the Captain can change the barangay name, address or contacts** — the
  values every public page reads. **Intended** under decision 1: these are
  official identity values, held by the Captain.

The former also explains the old "A Captain assigns a Kagawad…" interface copy.
There is no `captain` role; the Captain is the Admin.

---

## Code vs ROLES.md

Where the implementation does not match the specification above.

**Nothing outstanding.** Decision 3's read-only resident view is built.

### Implemented — the Kagawad resident view

`GET /kagawad/residents` → `kagawad.residents.index`, inside the existing
`role:kagawad` group, rendered by `Kagawad\ResidentController` and queried through
`App\Support\KagawadSectorResidents`. Kagawad routes are now five; this is the
fifth.

How it satisfies decision 3:

- **Own sectors only.** The sector filter is a `whereIn` on
  `resident_profiles.sector_id`, applied before anything else and never widened.
  A household in another sector is absent from the page *source*, not merely
  unlinked — proven against the raw HTML in
  `KagawadSectorResidentsTest::a_resident_from_another_sector_is_absent_from_the_html_not_merely_unlinked`.
- **No unscoped fallback.** An unassigned Kagawad gets the shared unassigned empty
  state and the controller returns before any query runs, so there is no code path
  that produces an unscoped list.
- **Read-only, structurally.** The route is a single `GET` with no POST, PUT,
  PATCH or DELETE behind it, and the controller has one method. "No editing" is a
  property of the route table, not a rule inside a method.
  (`the_route_offers_no_write_verb_at_all`.)
- **Privacy by selection, not omission.** The query returns a name, household
  number, sector, verification state and relief state. National ID, birthdate,
  contact number, address, ID photo and request history are never selected, so
  there is nothing to leak; `the_query_selects_only_the_five_permitted_fields`
  asserts the column list.
- **Relief status per batch** via a correlated subquery on
  `relief_distributions`, joined on `household_number` **and** `sector_id` so a
  number collision in another sector cannot surface a neighbour's collection.
- **No N+1.** The status arrives as a subselect on the row, so the query count is
  identical at 1 and at 20 residents
  (`the_query_count_does_not_grow_with_the_number_of_residents`).

Refusals follow the route middleware, so the role areas still do not overlap: a
Resident, a Secretary and the Captain each get **403**, and a guest is redirected
to `/login`.

### Two judgement calls recorded

1. **The batch default is the latest *active* batch, not the latest batch.** A
   closed batch can carry a later `distribution_date` than any open one, and
   defaulting to it would open the page on a worklist nobody can act on. Closed
   batches are still selectable — a Kagawad checking a past handout is reasonable.
2. **"Not yet received" is reserved for residents who are on the batch's
   checklist.** A verified resident absent from the checklist reads **"Not on this
   batch"**, and an unverified one reads **"No relief status"** — because "not yet
   received" is a claim about a batch they are not on. The legend under the filter
   bar says so.

### Resolved: one sector per Kagawad

Decision 3 says "their assigned sector**s**". **The owner has decided: a Kagawad
holds exactly one sector.** `sectors.kagawad_user_id` is `UNIQUE`
(`2026_09_22_025934_create_sectors_table.php:15`) and that is correct as it stands.
The schema is not to be changed.

This settles the question this file raised while the resident view was being built.
Two consequences, both now settled rather than open:

1. **The plural in decision 3 is read as "one or more", never as "several".** A
   Kagawad's remit is a single sector.
2. **The sector filter on the Kagawad resident list is unreachable and stays
   that way.** It renders only when `count($sectorIds) > 1`
   (`KagawadSectorResidents::filterOptions()`), which by this decision can never
   happen. The code is left in place and the logic is still tested directly against
   the support class, but the branch is dead by design rather than pending. If a
   Kagawad ever does need several sectors, that is a schema change and a new owner
   decision — not a UI change.

The end-to-end test for the multi-sector case is `markTestSkipped` naming this
reason, so it cannot be mistaken for an oversight.

---


## Suspicious

Not covered by an owner decision, and worth a look. Ordered by how much I would
worry about it.

### 1. A Secretary can view a resident's uploaded ID photo

`staff.resident-verifications.id-photo` serves the image file for any profile. That
is government-issued identity evidence, reachable by the most numerous staff role,
on a list route, with no second factor and no access log of its own beyond the fact
that a view is a read. It is presumably necessary to verify a registration — but
it is worth an explicit decision rather than an accident of grouping.

### 2. An Admin can restrict another Admin, but can never demote one

`AccountController:90` refuses **any** change away from `admin`, not just the last
one — the message says *"Demoting the last Admin would lock everyone out"*, but
the code blocks all of them however many Admins exist. Meanwhile `restrict` has no
equivalent guard beyond "not yourself", so **the Captain can freeze another
Captain out of the system** while being unable to demote them. Two adjacent
permissions, one far more permissive than the other, and the message describes a
narrower rule than the code enforces.

### 3. Restriction is only enforced on routes that carry `role:` middleware

The check lives in `EnsureUserHasRole`, so it runs only where that middleware
runs. Today that is correct — the exempt routes are `account.*`, `password.*` and
`logout`, which is exactly the intended escape hatch. But the coupling is
implicit: **a future route added without `role:` is automatically unrestrictable**,
with nothing to catch it. Nothing in the test suite asserts that every
authenticated route outside the escape hatch carries `role:`.

This one gets sharper as roles are added, and it is the direct cost of having no
policy layer (item 8).

### 4. An unauthenticated `POST /_boost/browser-logs`

Registered by `laravel/boost` with **no route middleware at all**. It is gated
only by `shouldRun()`: enabled unless `BOOST_ENABLED=false`, never in unit tests,
and only when `APP_ENV=local` **or** `APP_DEBUG=true`. On this machine `APP_ENV=local`,
so it is live now. The risk is not this machine — it is a production deploy that
leaves `APP_DEBUG=true`, which would expose an endpoint that accepts browser-log
POSTs from anyone. It is a vendor route rather than application code, so it is
listed here rather than fixed.

### 5. A Resident can change their own sector

`resident.verification.update` accepts `sector_id`. Re-submitting correctly resets
verification to pending, so it cannot be used to skip the check — but a Resident
can move themselves between sectors, and sector is what a relief checklist and a
Kagawad's remit are built on. Decision 3 makes a Kagawad's sector meaningful, so
this is worth confirming as intended self-service.

### 6. `laravel/boost` is a dev dependency, and that is what keeps item 4 local

`laravel/boost` sits in `require-dev`, alongside `laravel/pail` and `laravel/pao`
— all three are correctly absent from a production install, and none is referenced
by application code. The reason `/_boost/browser-logs` is nonetheless live here is
simply that a local checkout has dev dependencies installed. So item 4 is a
deployment-hygiene note, not a live exposure: it disappears on its own in
production. Listed because it is the one route in the table that is not the
application's own.

### 7. `storage.local.serve = true` in `config/filesystems.php`

This is what registers the unauthenticated `/storage/{path}` GET and PUT. It is
Laravel's development default and Laravel only registers those routes when it is
true, so it must be `false` in production. It is `true` here with `APP_ENV=local`,
which is correct — noted only because it is a value that must not travel.

### 8. There is no policy layer, so authorisation is invisible from the views

No `app/Policies`, no Gates, no `@can` in any template. Every screen decides what
to show from `$user->isAdmin()`-style conditionals in Blade, which is why
`RoleNavigation` had to be kept in step with the routes **by hand**. It currently
is, and `RoleNavigationTest` covers it — but nothing structural would stop the
two drifting apart, and a template can only ever *hide* a control, never refuse
the request behind it.

The three Owner decisions are currently held in four unrelated places — two
middleware groups, a sidebar list, and a test — and the fourth (the Kagawad
resident view) had to be added to all of them by hand. This is the item that
would let them drift.

### 9. Smaller things, listed for completeness

- `staff.accounts.role` accepts any role in `User::roles()`, so the Captain can
  make a Resident into another Captain. Nothing requires a second Captain's
  agreement.
- `/register` is open to the public, so Resident accounts can be created by anyone.
  Verification is the gate, not registration.
- An Admin can change a resident's role to `kagawad` without assigning a sector,
  which produces a Kagawad with an empty dashboard — a valid state, and the one
  the sectors page warns about. Under decision 3 that Kagawad sees nothing at all.
- `RoleNavigation` has no entry for any role outside the four constants, so an
  unexpected `role` value in the database yields an empty sidebar rather than an
  error.

---

## What is not covered here

- **Row-level audit of every controller.** The guards above are the ones found by
  searching for `abort_if`, `abort_unless` and `authorize`; a permission expressed
  as a filtered query would not appear there.
- **Anything about data the roles share.** The three staff roles all read the same
  tables; only Kagawad and Resident are scoped.
