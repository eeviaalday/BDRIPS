# Staff, Kagawad and Admin UI Audit

**Branch:** `staff-ui`
**Date:** 2026-10-01
**Scope:** read-only analysis of the staff / kagawad / admin areas. No view, route, controller,
model, test or stylesheet was modified to produce this document.

Every claim below was checked against the code, the route table, or a real browser session
(Playwright driving Chrome against `php artisan serve` on the seeded `bdrpis` database).
Where a reported symptom did **not** reproduce, that is stated explicitly with the measurement
that disproves it, rather than being quietly dropped.

---

## 0. Blocker: ROLES.md does not exist

`ROLES.md` was asked for as the source of truth for the permission matrix. **It is not in this
repository.** Checked and confirmed absent:

- `ls ROLES.md` — no such file
- `find . -iname "*roles*"` — no match outside `node_modules`/`vendor`/`.git`
- `git log --all --diff-filter=A -- '*ROLES*'` — no commit ever added it
- The only root markdown files are `AGENTS.md`, `CLAUDE.md`, `PROMPT_LOG.md`, `README.md`

Consequence: the matrix in §1 is derived from **`routes/web.php` middleware, which is the
enforcement that actually runs.** It is not a transcription of ROLES.md, and the
"mismatches between ROLES.md and the code" that were requested **cannot be produced until
ROLES.md is supplied.** §3 lists what *can* be compared without it, and flags the one place
where the code contradicts its own stated intent.

There is no in-repo substitute: `tests/Feature/PermissionMatrixTest.php` reads the expected
answer *out of each route's own middleware* rather than from an independent specification, so
it verifies the middleware is applied and enforced, not that it was written correctly.

---

## 1. Permission matrix

Derived from `php artisan route:list --except-vendor`. "Denied" means the request is refused
by `EnsureUserHasRole` with HTTP 403 (or redirected to login for a guest) — not merely hidden
from navigation.

### 1.1 Staff area — `role:admin,secretary`

| Screen | Route name | URI | Admin | Secretary | Kagawad | Resident | Guest |
|---|---|---|---|---|---|---|---|
| Dashboard | `staff.dashboard` | GET `/staff/dashboard` | Allowed | Allowed | Denied | Denied | Denied |
| Resident Verification queue | `staff.verifications.index` | GET `/staff/verifications` | Allowed | Allowed | Denied | Denied | Denied |
| Verification review | `staff.verifications.show` | GET `/staff/verifications/{profile}` | Allowed | Allowed | Denied | Denied | Denied |
| — approve | `staff.verifications.approve` | POST `…/approve` | Allowed | Allowed | Denied | Denied | Denied |
| — reject | `staff.verifications.reject` | POST `…/reject` | Allowed | Allowed | Denied | Denied | Denied |
| — ID photo stream | `staff.resident-verifications.id-photo` | GET `…/id-photo` | Allowed | Allowed | Denied | Denied | Denied |
| Residents directory | `staff.residents.index` | GET `/staff/residents` | Allowed | Allowed | Denied | Denied | Denied |
| Resident record | `staff.residents.show` | GET `/staff/residents/{profile}` | Allowed | Allowed | Denied | Denied | Denied |
| Document Requests queue | `staff.requests.index` | GET `/staff/requests` | Allowed | Allowed | Denied | Denied | Denied |
| Request review | `staff.requests.show` | GET `/staff/requests/{documentRequest}` | Allowed | Allowed | Denied | Denied | Denied |
| — review / ready / claim / reject | `staff.requests.{review,ready,claim,reject}` | POST `…/*` | Allowed | Allowed | Denied | Denied | Denied |
| Relief Distribution list | `staff.batches.index` | GET `/staff/batches` | Allowed | Allowed | Denied | Denied | Denied |
| New batch form | `staff.batches.create` | GET `/staff/batches/create` | Allowed | Allowed | Denied | Denied | Denied |
| Batch record | `staff.batches.show` | GET `/staff/batches/{reliefBatch}` | Allowed | Allowed | Denied | Denied | Denied |
| — close / reopen | `staff.batches.{close,reopen}` | POST `…/*` | Allowed | Allowed | Denied | Denied | Denied |
| Announcements | `staff.announcements.index` | GET `/staff/announcements` | Allowed | Allowed | Denied | Denied | Denied |
| Announcement form | `staff.announcements.{create,edit}` | GET | Allowed | Allowed | Denied | Denied | Denied |
| — store / update / destroy | `staff.announcements.{store,update,destroy}` | POST/PUT/DELETE | Allowed | Allowed | Denied | Denied | Denied |
| — publish / pin / unpin | `staff.announcements.{publish,pin,unpin}` | POST `…/*` | Allowed | Allowed | Denied | Denied | Denied |

### 1.2 Admin-only area — `role:admin`

| Screen | Route name | URI | Admin | Secretary | Kagawad | Resident | Guest |
|---|---|---|---|---|---|---|---|
| User Accounts | `staff.accounts.index` | GET `/staff/accounts` | Allowed | Denied | Denied | Denied | Denied |
| Account record | `staff.accounts.show` | GET `/staff/accounts/{user}` | Allowed | Denied | Denied | Denied | Denied |
| — change role | `staff.accounts.role` | PUT `…/role` | Allowed | Denied | Denied | Denied | Denied |
| — restrict / unrestrict | `staff.accounts.{restrict,unrestrict}` | POST `…/*` | Allowed | Denied | Denied | Denied | Denied |
| Sectors & Kagawad | `staff.sectors.index` | GET `/staff/sectors` | Allowed | Denied | Denied | Denied | Denied |
| — create / update / destroy | `staff.sectors.{store,update,destroy}` | POST/PUT/DELETE | Allowed | Denied | Denied | Denied | Denied |
| — assign Kagawad | `staff.sectors.kagawad` | PUT `…/kagawad` | Allowed | Denied | Denied | Denied | Denied |
| Settings | `staff.settings.index` | GET `/staff/settings` | Allowed | Denied | Denied | Denied | Denied |
| — save | `staff.settings.update` | PUT `/staff/settings` | Allowed | Denied | Denied | Denied | Denied |
| Audit Logs | `staff.audit.index` | GET `/staff/audit` | Allowed | Denied | Denied | Denied | Denied |
| System Status | `staff.status.index` | GET `/staff/status` | Allowed | Denied | Denied | Denied | Denied |

### 1.3 Kagawad area — `role:kagawad`

| Screen | Route name | URI | Admin | Secretary | Kagawad | Resident | Guest |
|---|---|---|---|---|---|---|---|
| Kagawad dashboard | `kagawad.dashboard` | GET `/kagawad/dashboard` | Denied | Denied | Allowed | Denied | Denied |
| Sector relief list | `kagawad.relief` | GET `/kagawad/relief` | Denied | Denied | Allowed | Denied | Denied |
| Relief checklist | `kagawad.relief.show` | GET `/kagawad/relief/{reliefBatch}` | Denied | Denied | Allowed | Denied | Denied |
| — mark received | `kagawad.relief.received` | POST `…/received/{distribution}` | Denied | Denied | Allowed | Denied | Denied |

### 1.4 Shared — no role middleware by design

| Screen | Route name | URI | Admin | Secretary | Kagawad | Resident | Guest |
|---|---|---|---|---|---|---|---|
| Role signpost | `dashboard` | GET `/dashboard` | Allowed | Allowed | Allowed | Allowed | Denied |
| My Account | `account.edit` | GET `/account` | Allowed | Allowed | Allowed | Allowed | Denied |
| Save account | `account.update` | PATCH `/account` | Allowed | Allowed | Allowed | Allowed | Denied |
| Change password | `account.password` | GET `/account/password` | Allowed | Allowed | Allowed | Allowed | Denied |
| Update password | `password.update` | PUT `/password` | Allowed | Allowed | Allowed | Allowed | Denied |

`/dashboard` carries `auth` + `verified` but no role, and forwards to `EnsureUserHasRole::landingFor()`.
`EnsureUserHasRole` also applies a restricted-account check that exempts `account.*`, `password.*` and
`logout`, so a frozen account can still change its password and sign out.

### 1.5 Sidebar visibility — `App\Support\RoleNavigation::sidebarFor()`

| Destination | Group declared | Admin | Secretary | Kagawad |
|---|---|---|---|---|
| Dashboard | `main` | `staff.dashboard` | `staff.dashboard` | `kagawad.dashboard` |
| Resident Verification | `main` | yes | yes | — |
| Residents | `main` | yes | yes | — |
| Document Requests | `main` | yes | yes | — |
| Relief Distribution | `main` | yes | yes | `kagawad.relief` |
| Announcements | `main` | yes | yes | — |
| User Accounts | **`admin`** | yes | — | — |
| Sectors & Kagawad | **`admin`** | yes | — | — |
| Settings | **`admin`** | yes | — | — |
| Audit Logs | **`admin`** | yes | — | — |
| System Status | **`admin`** | yes | — | — |
| My Account | `account` | yes | yes | yes |

Resident: no sidebar at all — the public top bar (`RoleNavigation::residentBar()`).

---

## 2. Design language to match (resident/public)

Taken from `tailwind.config.js`, `resources/css/app.css`, and the resident components.

### 2.1 Colour tokens

| Token | Value | Use |
|---|---|---|
| `navy.DEFAULT` / `navy.900` | `#0D233A` | Primary brand, headers, sidebar, hero |
| `navy.50`…`navy.800` | 9 steps | Tints, borders (`navy-100`, `navy-200`), muted rows (`navy-50/60`) |
| `gold.DEFAULT` | `#FBBF24` | Single accent — CTA, active state, focus accent |
| `gold-50`…`gold-900` | 9 steps | Badge fills (`bg-gold-50`), accents |
| `surface` | `#F6F6F9`→`#F6F9FC` | Section band tint |
| `sky` | Tailwind default | Links, info states, active nav chip |

Font: **Figtree** (`theme.fontFamily.sans`). Extra size token `text-2xs` = `0.6875rem/1rem`.

### 2.2 Spacing / radius / elevation

- Container: `max-w-shell` = **80rem (1280px)**, the resident shell width.
- Section rhythm: `pt-14 sm:pt-16 lg:pt-20` / `pb-14 sm:pb-16 lg:pb-20` (overridable via `x-shell`'s `top` prop).
- Radii: cards `rounded-xl` (0.75rem), small controls/buttons `rounded-lg` (0.5rem), pills `rounded-full`.
- Shadow: **`shadow-card`** = `0 1px 2px 0 rgb(13 35 58 / .04), 0 1px 3px 0 rgb(13 35 58 / .06)`; hover adds `shadow-card-hover`. Never a heavy drop shadow on cards.
- Transitions: `duration-200`, `ease-smooth` = `cubic-bezier(.4,0,.2,1)`.

### 2.3 Card — `x-card`

```
rounded-xl border shadow-card transition duration-200
tone=white    → bg-white border-navy-100
tone=tinted   → bg-navy-50/60 border-navy-100
tone=navy     → bg-navy text-white border-navy-800
tone=outline  → bg-transparent border-navy-200
padding default p-6
```

`x-resident.filter-card` adds `rounded-xl p-5 bg-white border border-navy-100 shadow-card`.

### 2.4 Buttons — `x-btn`

`inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition duration-200 ease-smooth focus-visible:ring-2`
Variants: `primary` (`bg-gold text-navy-900`), `navy`, `sky`, `secondary`, `outline`, `ghost`, `danger`.
Sizes: `sm` `px-3 py-1.5 text-xs` · `md` `px-4 py-2.5 text-sm` · `lg` `px-6 py-3.5 text-base`.

### 2.5 Form fields — `x-field` / `x-select-field`

Inputs/selects/date inputs share:
`mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200`

Labels: `block text-sm font-semibold text-navy-800`. Help text `text-sm text-navy-500`.
Errors render through the component's own `@error` block with `aria-live="polite"`.

### 2.6 Status badge — `x-status-badge`

Always colour **plus** text **plus** a glyph — never colour alone.
`rounded-full` + `px-2.5 py-1 text-xs font-medium` + a `h-1.5 w-1.5 rounded-full` dot, wrapped in
tone pairs such as `bg-gold-50 text-gold-800 border-gold-300` (pending) and
`bg-green-50 text-green-800 border-green-200` (verified/received).

### 2.7 Table — `x-data-table` `variant="resident"`

- Wrapper: `rounded-xl border border-navy-100`, `overflow-x-auto`, focusable `role="region"`, always has an `sr-only` `<caption>`.
- `<table class="w-full min-w-[46rem] text-sm">`
- Resident header: `border-b-[3px] border-gold bg-navy text-xs font-bold uppercase tracking-wider text-white`
- Resident body: `divide-y divide-navy-100/70 [&>tr:nth-child(even)]:bg-sky-50/40 [&>tr:hover]:bg-gold/10 [&>tr]:transition-colors`
- Cells: `px-4 py-3`
- Empty slot renders `x-empty-state`.

### 2.8 Empty state — `x-empty-state`

`flex flex-col items-center rounded-xl border border-dashed border-navy-200 bg-navy-50/40 px-6 py-12 text-center`,
icon in a `h-14 w-14 rounded-full bg-white text-navy-400 shadow-card ring-1 ring-navy-100`,
heading `text-base font-bold text-navy-900`, body `text-sm text-navy-600/80`, `level` must match the outline.

### 2.9 Page header / breadcrumb

- `x-page-header` is the **full-bleed photographic banner** used by public inner pages; it is a
  sibling of `x-shell`, not inside it.
- `x-breadcrumb` is the lightweight trail used by staff and kagawad pages: `nav aria-label="Breadcrumb" class="mb-4"`.
- Both shared. See §4.

### 2.10 Alerts

`x-alert` variants: `info` `sky-50/sky-200/sky-900`, `success` `green-*`, `warning` `gold-50/gold-200/gold-800`,
`danger` `red-*`, `privacy` `navy-*`, `on-dark`. Renders `role="status"` when given a `title`.

---

## 3. Mismatches

### 3.1 Against ROLES.md — **cannot be produced**

See §0. Supplying `ROLES.md` is the only unblock; nothing else is missing.

### 3.2 Code contradicting its own stated intent

| # | Mismatch | Evidence |
|---|---|---|
| M1 | `RoleNavigation` declares five Admin-only links with `'group' => 'admin'` (accounts, sectors, settings, audit, status), but `availableSidebarGroupsFor()` buckets every link where `group !== 'main'` into a single `account` array. The `admin` group value is therefore **never rendered as a distinct group** — the distinction exists in the data and is discarded before it reaches the view. | `app/Support/RoleNavigation.php:74-78` vs `:162-163` |
| M2 | `navigation.blade.php` hard-codes the second bucket's heading as `My Account`, so those five Admin destinations render under a heading that says "My Account". This is the visible face of M1. | `resources/views/layouts/navigation.blade.php:54` |
| M3 | `RoleNavigation::sidebarFor()` returns `[]` for any role not in the `match` (so a Resident, if the staff layout were ever rendered for them, gets an empty shell). Currently unreachable because `/dashboard` sends Residents to `/`, but it is a silent failure rather than a refusal. | `app/Support/RoleNavigation.php:82` |
| M4 | `PermissionMatrixTest` derives its expected answers from each route's own middleware, so it cannot catch a route whose middleware is wrong — only one that is missing or unenforced. There is no independent specification in the repo to close that gap. | `tests/Feature/PermissionMatrixTest.php:26-30` |

### 3.3 Navigation vs. routes

No mismatches found. Every route a role is granted has a sidebar entry, and no sidebar entry
points at a route the role cannot open:

- Admin: 11 destinations, all reachable; all `staff.*` routes reachable via those.
- Secretary: 6 destinations; correctly has **no** link to the five `role:admin` screens.
- Kagawad: 2 destinations, both `kagawad.*` — no `staff.*` route is reachable and none is linked.
- `availableSidebarFor()` filters to `Route::has()`, and **all** entries currently resolve, so
  nothing is being silently dropped.

---

## 4. SHARED — do not edit in place

These are consumed by both the resident/public area and the staff/kagawad area. Under the task's
hard scope rules they must not be modified; create `resources/views/components/staff/*` or
`resources/views/layouts/staff*.blade.php` instead.

| File | Used by staff | Used by resident/public |
|---|---|---|
| `resources/views/layouts/public.blade.php` | indirectly — `account.edit` is `x-public-layout` only for Residents, so **not** staff | yes — every public page + `account/edit.blade.php` |
| `resources/views/components/shell.blade.php` (`x-shell`) | yes — the `max-w-*` containers are hand-rolled instead, so **not** used by staff | yes — every public/resident page |
| `resources/views/components/page-header.blade.php` | **yes** — `x-breadcrumb` is a sibling concept but page-header is not used by staff views | yes |
| `resources/views/components/breadcrumb.blade.php` | **yes** — 17 of 20 staff/kagawad pages | yes |
| `resources/views/components/data-table.blade.php` | **yes** — every staff table; has a `variant="resident"` branch | yes — resident tables |
| `resources/views/components/empty-state.blade.php` | **yes** — `residents/show`, `verifications/show`, and via `x-data-table` | yes |
| `resources/views/components/btn.blade.php` | **yes** — every staff page | yes |
| `resources/views/components/status-badge.blade.php` | **yes** | yes |
| `resources/views/components/stat-card.blade.php` | **yes** — staff + kagawad dashboards, accounts | yes — resident filter cards |
| `resources/views/components/card.blade.php` | **yes** — batches, accounts/show, settings, sectors, kagawad relief | yes |
| `resources/views/components/alert.blade.php` | **yes** | yes |
| `resources/views/components/field.blade.php`, `select-field.blade.php`, `input-label.blade.php`, `input-error.blade.php` | **yes** — announcements form, batches create | yes — resident forms, registration wizard |
| `resources/views/components/confirm-dialog.blade.php` | **yes** — announcements, batches, sectors, requests | yes |
| `resources/views/components/icon.blade.php` | **yes** | yes |
| `resources/views/components/request-timeline.blade.php` | **yes** — `staff/requests/show` | yes |
| `resources/views/components/resident/*.blade.php` | **no** — `filter-card`, `modal`, `profile-tabs`, `verification-banner` are resident-only | yes |
| `resources/views/components/resident/toast.blade.php` | **no** | yes |
| `tailwind.config.js` (tokens, breakpoints) | **yes** — every staff class resolves through it | yes |
| `resources/css/app.css` (`@layer base` and `@layer components`) | **yes** | yes |
| `resources/views/account/partials/details.blade.php` | **yes** — included by `account/edit-staff.blade.php` | yes — by `account/edit.blade.php` |
| `app/Models/*`, `config/bdrpis.php`, `app/Support/RoleNavigation.php` | yes | yes |

**Staff-only (safe to change):** `layouts/app.blade.php`, `layouts/navigation.blade.php`,
`app/View/Components/AppLayout.php`, everything under `resources/views/staff/**`,
`resources/views/kagawad/**`, `resources/views/account/edit-staff.blade.php`, and every
controller under `app/Http/Controllers/Staff|Kagawad`.

**Trap:** `/account` is a single route rendering **two different layouts** —
`AccountController::edit()` picks `account.edit` (Resident, `x-public-layout`) or
`account.edit-staff` (staff/kagawad, `x-app-layout`) on `$user->isResident()`. Editing
`account/edit.blade.php` touches the resident area; `edit-staff.blade.php` does not.

---

## 5. Per-page inventory

Layouts: `app` = `x-app-layout` (`layouts/app.blade.php`), staff-only.

| Route name | View | Controller | Layout | Container | Components | Crumb | Empty state | Pagination |
|---|---|---|---|---|---|---|---|---|
| `staff.dashboard` | `staff/dashboard.blade.php` | `Staff\DashboardController` | app | `max-w-5xl` | stat-card, alert | no | no | no |
| `staff.verifications.index` | `staff/verifications/index.blade.php` | `Staff\ResidentVerificationController` | app | `max-w-6xl` | breadcrumb, btn, data-table, stat-card, status-badge | yes | via table | `paginate(15)` + `links()` |
| `staff.verifications.show` | `staff/verifications/show.blade.php` | `Staff\ResidentVerificationController` | app | `max-w-4xl` | alert, breadcrumb, btn, card, confirm-dialog, empty-state, status-badge | yes | yes | no |
| `staff.residents.index` | `staff/residents/index.blade.php` | `Staff\ResidentDirectoryController` | app | `max-w-6xl` | breadcrumb, btn, data-table, status-badge | yes | via table | `paginate(20)` + `links()` |
| `staff.residents.show` | `staff/residents/show.blade.php` | `Staff\ResidentDirectoryController` | app | `max-w-4xl` | breadcrumb, btn, card, data-table, empty-state, status-badge | yes | yes | no |
| `staff.requests.index` | `staff/requests/index.blade.php` | `Staff\DocumentReviewController` | app | `max-w-6xl` | breadcrumb, btn, data-table, stat-card, status-badge | yes | via table | `paginate(15)` + `links()` |
| `staff.requests.show` | `staff/requests/show.blade.php` | `Staff\DocumentReviewController` | app | `max-w-4xl` | breadcrumb, btn, card, confirm-dialog, request-timeline, status-badge | yes | no | no |
| `staff.batches.index` | `staff/batches/index.blade.php` | `Staff\ReliefBatchController` | app | `max-w-6xl` | breadcrumb, btn, data-table, status-badge | yes | via table | `paginate(15)` + `links()` |
| `staff.batches.create` | `staff/batches/create.blade.php` | `Staff\ReliefBatchController` | app | `max-w-3xl` | alert, breadcrumb, btn, card, field | yes | no | no |
| `staff.batches.show` | `staff/batches/show.blade.php` | `Staff\ReliefBatchController` | app | `max-w-5xl` | alert, breadcrumb, btn, card, confirm-dialog, data-table, stat-card, status-badge | yes | via table | no |
| `staff.announcements.index` | `staff/announcements/index.blade.php` | `Staff\AnnouncementController` | app | `max-w-6xl` | alert, breadcrumb, btn, confirm-dialog, data-table | yes | via table | `paginate(15)` + `links()` |
| `staff.announcements.create` / `.edit` | `staff/announcements/form.blade.php` | `Staff\AnnouncementController` | app | `max-w-3xl` | alert, breadcrumb, btn, card, field, select-field | yes | no | no |
| `staff.accounts.index` | `staff/accounts/index.blade.php` | `Staff\AccountController` | app | `max-w-6xl` | breadcrumb, btn, data-table, stat-card, status-badge | yes | via table | `paginate(20)` + `links()` |
| `staff.accounts.show` | `staff/accounts/show.blade.php` | `Staff\AccountController` | app | `max-w-4xl` | alert, breadcrumb, btn, card, status-badge | yes | no | no |
| `staff.sectors.index` | `staff/sectors/index.blade.php` | `Staff\SectorController` | app | `max-w-5xl` | alert, breadcrumb, btn, card, confirm-dialog | yes | no | no |
| `staff.settings.index` | `staff/settings/index.blade.php` | `Staff\SettingController` | app | `max-w-3xl` | alert, breadcrumb, btn, card | yes | no | no |
| `staff.audit.index` | `staff/audit/index.blade.php` | `Staff\AuditLogController` | app | `max-w-6xl` | breadcrumb, btn, data-table | yes | via table | `paginate(25)` + `links()` |
| `staff.status.index` | `staff/status/index.blade.php` | `Staff\SystemStatusController` | app | `max-w-4xl` | breadcrumb, card, status-badge | yes | no | no |
| `kagawad.dashboard` | `kagawad/dashboard.blade.php` | `Kagawad\DashboardController` | app | `max-w-5xl` | alert, stat-card | no | no | no |
| `kagawad.relief` | `kagawad/relief/index.blade.php` | `Kagawad\ReliefController` | app | `max-w-5xl` | alert, breadcrumb, card, data-table, status-badge | yes | via table | `paginate(10)` + `links()` |
| `kagawad.relief.show` | `kagawad/relief/show.blade.php` | `Kagawad\ReliefController` | app | `max-w-5xl` | alert, breadcrumb, btn, card, stat-card, status-badge | yes | no | no |
| `account.edit` (staff branch) | `account/edit-staff.blade.php` | `AccountController` | app | `max-w-3xl` | app-layout only | no | no | no |
| `account.password` (staff branch) | `account/edit-staff.blade.php` | `AccountController` | app | `max-w-3xl` | app-layout only | no | no | no |
| `dashboard` | — (redirect only) | `DashboardController` | — | — | — | — | — | — |

Pagination is implemented on all 8 list screens but **renders nothing with the seeded dataset**
(7 users, 6 requests, 5 audit entries — all under one page). It is not missing; it is unobservable
unless a page is exceeded.

No staff page uses `x-page-header` (the photographic banner) — they all use `x-breadcrumb` plus a
hand-rolled `<header>` with `<h1 class="text-xl font-bold text-navy-900">` and a
`<p class="mt-1 text-sm text-gray-600">` subtitle.

---

## 6. Findings

Severity: **Bug** = wrong data or a dead/inverted control · **Inconsistency** = works but
diverges from the design language · **Stale copy** = text no longer true · **Not reproduced** =
reported symptom does not occur.

### F1 — Pin/Unpin control is inverted and there is no Pin button · **Bug** · High

`staff/announcements/index.blade.php:80-86`:

```blade
<form method="POST" action="{{ route('staff.announcements.unpin', $announcement) }}">
    @csrf
    <button type="submit" @disabled($announcement->isPinned()) …>
        Unpin
    </button>
</form>
```

`@disabled($announcement->isPinned())` disables the button **exactly when it is needed**. A
pinned announcement cannot be unpinned from this screen, and the label is the literal string
`Unpin` on every row, so an unpinned row offers an enabled "Unpin" against something that is not
pinned. The `staff.announcements.pin` route exists
(`POST /staff/announcements/{announcement}/pin`) and has **no UI anywhere**.

Browser confirmation (3 seeded announcements, first is pinned):

| Row | Button label | `disabled` |
|---|---|---|
| Scheduled Financial Aid (pinned) | Unpin | **true** |
| Typhoon Relief Distribution | Unpin | false |
| Community Clean-Up Drive | Unpin | false |

Not a permissions issue: `pin`, `unpin` are both `role:admin,secretary`.

### F2 — User Accounts role-count cards show 0 for every role · **Bug** · High

`app/Http/Controllers/Staff/AccountController.php:171-182`:

```php
$counts = array_fill_keys(User::roles(), 0);

User::query()
    ->selectRaw('role, count(*) as total')
    ->groupBy('role')
    ->pluck('total', 'role')
    ->each(fn ($total, $role) => $counts[$role] = (int) $total);
```

**Root cause: `fn()` is an arrow function and captures `$counts` by value.** The assignment
mutates a copy that is discarded, so `$counts` stays at the `array_fill_keys(…, 0)` defaults.
Proven in `tinker` against the seeded data:

```
arrow fn:          {"admin":0,"secretary":0,"kagawad":0,"resident":0}
closure by &ref:   {"admin":1,"secretary":1,"kagawad":2,"resident":3}
```

The query itself is fine — `pluck('total','role')` returns `admin=1, kagawad=2, resident=3,
secretary=1`. Browser confirms four cards reading `0 Admin`, `0 Secretary`, `0 Kagawad`,
`0 Resident` while 7 users exist. This is the only occurrence of the pattern in `app/`
(`grep -rn "each(fn" app/` returns this line alone). Fix would be
`function ($total, $role) use (&$counts) { $counts[$role] = (int) $total; }`.

### F3 — `<title>` is `BDRPIS - BDRPIS` on all 20 staff/kagawad pages · **Bug** · Medium

**Root cause: `AppLayout` never declares a `$title` property.**

```php
// app/View/Components/AppLayout.php
public function __construct(public readonly ?User $user = null) {}
```

Every view writes `<x-app-layout :user="auth()->user()" title="Dashboard">`. In a class-based
component only declared public properties are extracted into the view, so `title` stays in the
attribute bag and never reaches `layouts/app.blade.php:7`:

```blade
<title>{{ $title ?? 'BDRPIS' }} - BDRPIS</title>
```

Hence `BDRPIS - BDRPIS` regardless of the passed title. Browser check across 18 Admin pages:
every one returns `title="BDRPIS - BDRPIS"`.

Side effect: `layouts/app.blade.php:77-79` guards its own `<h1>{{ $title }}</h1>` with
`@isset($title)`, so **that block is dead code** and never renders. Each page has exactly one
`<h1>` of its own, so there is no duplicate-`<h1>` defect — but the layout's heading slot is
silently non-functional.

### F4 — Sidebar is not sticky and scrolls away · **Bug (UX)** · Medium

`layouts/navigation.blade.php:29` — the `<aside>` has `min-h-screen` but **no `sticky`, no
`fixed`, no `h-screen`**. Computed `position` on all 18 pages: **`static`**, `top: auto`. The
wrapper is `<div class="flex min-h-screen">`, so on a page taller than the viewport the sidebar
scrolls out of view and the user loses navigation. Only the inner `<nav>` scrolls
(`overflow-y-auto`), which is useless once the aside itself has left the viewport.

### F5 — Admin-only pages are grouped under the "MY ACCOUNT" heading · **Inconsistency** · Medium

Confirmed — see M1/M2. Five Admin destinations (User Accounts, Sectors & Kagawad, Settings, Audit
Logs, System Status) render under a hard-coded `My Account` heading, and the `group => 'admin'`
metadata that would separate them is discarded in `RoleNavigation::availableSidebarGroupsFor()`.

### F6 — "Still being built" notices are stale · **Stale copy** · Medium

- `staff/dashboard.blade.php:26-29` — **unconditional.** Claims "The rest of the staff area is
  still being built. The destinations in the sidebar appear here as each one is finished." All
  six Secretary destinations are registered and working, and `availableSidebarFor()` filters
  nothing out.
- `kagawad/dashboard.blade.php:33-36` — shows whenever the Kagawad **has** sectors (inside the
  `@else` of `$sectorIds === []`). Claims "The relief checklist is still being built. It will
  appear here under Relief Distribution" while `kagawad.relief` exists and is in their sidebar.

### F7 — Copy names a "Captain" and an Admin-only page to a Kagawad · **Stale copy** · Medium

`kagawad/relief/index.blade.php:41-42`:

> "A Captain assigns a Kagawad to a sector from **Sectors & Kagawad**."

- There is **no Captain role**: `User::roles()` returns exactly `admin, secretary, kagawad,
  resident`. The sector→kagawad assignment is performed by an Admin at `PUT /staff/sectors/{sector}/kagawad`.
  (`config/bdrpis.php:458` does list a "Barangay Captain" as a *real-world official* on the About
  page, so the title is not invented — it is simply the wrong actor for this action.)
- `Sectors & Kagawad` is `role:admin`. A Kagawad cannot open it. It is not a hyperlink (a
  `<span class="font-semibold">`), so there is no dead link — but the text names a destination
  the reader can neither reach nor act on.
- Same pattern at `kagawad/dashboard.blade.php:24` ("The barangay office assigns sectors from
  Sectors & Kagawad").

Only rendered when the Kagawad has no sectors, so it is seen by new accounts rather than every visit.

### F8 — Content container width is not consistent · **Inconsistency** · Low-Medium

Not "every page different" — there are **four** tiers across 22 pages:

| Width | Pages |
|---|---|
| `max-w-3xl` (48rem) | `announcements/form`, `batches/create`, `settings/index`, `account/edit-staff` |
| `max-w-4xl` (56rem) | `accounts/show`, `requests/show`, `residents/show`, `status/index`, `verifications/show` |
| `max-w-5xl` (64rem) | `staff/dashboard`, `kagawad/dashboard`, `kagawad/relief/index`, `kagawad/relief/show`, `batches/show`, `sectors/index` |
| `max-w-6xl` (72rem) | the 7 table screens |

There is a defensible rule buried in it — forms at `3xl`, wide tables at `6xl` — but the middle
tiers are arbitrary: the staff dashboard sits at `5xl` while the verification queue beside it is
`6xl`, and `residents/show` (`4xl`) is the same shape of page as `batches/show` (`5xl`). The
resident shell's `max-w-shell` is 80rem, so no staff page reaches the public content width.

### F9 — Sectors page is 17 full inline forms · **Inconsistency** · Medium

`staff/sectors/index.blade.php`, 131 lines. Browser count for 17 sectors:
**35 `<form>` elements, 137 inputs, 17 selects, 51 submit buttons, 16 confirm dialogs.**
Roughly three forms and three submits per card on one page, with 17 `x-confirm-dialog` components
mounted regardless. There is no create-in-place affordance separate from the per-row edit, and no
pagination or grouping. Readable for 3 sectors; heavy at 17.

### F10 — Settings is one ungrouped form · **Inconsistency** · Low

`staff/settings/index.blade.php`, 70 lines. Browser count: **1 `<form>`, 0 `<fieldset>`,
0 `<legend>`, 0 `<section>`, 8 labels, 1 submit.** Eight unrelated settings in a single column
with no headings or grouping, so there is no visual separation between, say, contact details and
system toggles.

### F11 — "Verified" column shows an email-verification badge on staff accounts · **Inconsistency** · Medium

`staff/accounts/index.blade.php:57-61` renders `<x-status-badge status="active"/>` or
`status="pending"` from `$account->is_verified` for **every** account. That column reflects
`users.is_verified` (the email-confirmed flag, which `AccountController` resets when an address
changes) and has no bearing on staff. Browser output shows `VERIFIED: ACTIVE` for the Kagawad,
Secretary and Admin rows. The view's own header comment explains verification is deliberately not
editable — but the column is also meaningless for 4 of the 5 roles and reads as an endorsement of
the account.

### F12 — Table cell alignment: **not reproduced** · **Not reproduced**

Checked computed `text-align` on `<th>` and `<td>` for all 7 staff tables (audit, verifications,
accounts, requests, residents, announcements, batches):

| Table | `thead th` | `thead tr` | `tbody td` |
|---|---|---|---|
| audit | `left` | `left` | `start` |
| verifications | `left` | `left` | `start` |
| accounts | `left` | `left` | `start` |
| requests | `left` | `left` | `start` |
| residents | `left` | `left` | `start` |
| announcements | `left` | `left` | `start` |
| batches | `left` | `left` | `start` |

Every table is identical, including Audit Logs, and `left` and `start` are equivalent in LTR, so
nothing is centred or offset. There is no `<table>` written by hand anywhere in `staff/**` or
`kagawad/**` — all seven go through `x-data-table`.

One latent asymmetry worth noting: `<th>` gets `text-left` explicitly (inherited from the
`<tr>`'s `$headClass`), while `<td>` has no alignment declaration at all and relies on the UA
default. Identical today; would diverge under `dir="rtl"` or a `<th class="text-right">`.

### F13 — Selects and date inputs truncate their value: **not reproduced** · **Not reproduced**

Measured rendered width, available content width, and the width the selected text actually needs
at **1440px and 400px**:

| Page | Control | Width | Available | Text needs | Overflow |
|---|---|---|---|---|---|
| `/staff/accounts` | `#role` select | 93px | 63px | 55px | no |
| `/staff/audit` | `#action` select | 218px | 216px | 78px | no |
| `/staff/audit` | `#actor` select | 218px | 216px | 48px | no |
| `/staff/audit` | `#from`, `#to` date | 105px | 75px | 73px | no |
| `/staff/requests` | `#status` select | 152px | 122px | 122px | no (0px margin) |
| `/staff/announcements` | `#state` select | 121px | 91px | 68px | no |
| `/staff/residents` | `#sector_id` select | 156px | 126px | 65px | no |

No `scrollWidth > clientWidth` anywhere at either width. Two marginal risks, both unconfirmed as
visible defects:

- `#status` on `/staff/requests` has exactly **0px** spare at 1440px, and the longest option is
  wider than the selected one.
- `#role` on `/staff/accounts` is the only filter select **without `w-full`** (93px, while every
  other filter select is `w-full`). Its longest option, "Every role", needs 62px against 63px
  available. `@tailwindcss/forms` sets `appearance: none`, which also removes the native arrow
  that would otherwise signal a dropdown.

---

## 7. Method / reproducibility

- Route matrix: `php artisan route:list --except-vendor --json`.
- Alignment, truncation, title, sidebar position, card counts, table structure: Playwright 1.63
  driving **real Chrome** against `php artisan serve` on port 8124, authenticated as
  `admin@bdrpis.test`, at 1440×900 and 400×800. **0 console errors** on every page.
- F2 root cause proven by executing both the arrow-function and by-reference closure forms against
  the seeded database in `tinker`.
- Existing suite at the time of writing: **801 tests passing**, Pint clean.
