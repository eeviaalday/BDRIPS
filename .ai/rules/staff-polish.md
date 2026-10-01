# Staff-area polish rules

Scope: Secretary, Kagawad and Admin screens only. Full audit: `docs/STAFF_UI_AUDIT.md`.

## Hard scope

1. **Never edit the public/guest or resident area** — views, components, controllers, routes, tests.
   Not "just a class", not "just copy".
2. **Never edit a shared file in place.** If resident/public and staff both use it, create a staff
   variant (`resources/views/components/staff/*`, `resources/views/layouts/staff*.blade.php`) and
   leave the original byte-for-byte unchanged.
3. No new JS framework, no new composer/npm dependencies. Blade + Tailwind + Alpine only.
4. No change to authorization, validation rules or business logic unless the audit finding is marked
   **Bug** *and* the owner has explicitly approved that item.

## SHARED — do not edit

`layouts/public.blade.php` · `components/{shell,page-header,breadcrumb,data-table,empty-state,btn,status-badge,stat-card,card,alert,field,select-field,input-label,input-error,confirm-dialog,icon,request-timeline}.blade.php` · `account/partials/details.blade.php` · `components/resident/*` · `tailwind.config.js` · `resources/css/app.css` · `app/Models/*` · `config/bdrpis.php` · `app/Support/RoleNavigation.php`

**Trap:** `/account` renders `account/edit.blade.php` (`x-public-layout`, Resident) *or*
`account/edit-staff.blade.php` (`x-app-layout`, staff) on `$user->isResident()`. Only the latter
is safe to edit.

Safe: `layouts/app.blade.php` · `layouts/navigation.blade.php` · `app/View/Components/AppLayout.php` ·
`views/staff/**` · `views/kagawad/**` · `views/account/edit-staff.blade.php` · `app/Http/Controllers/{Staff,Kagawad}/**`

## Resident design tokens to match

- **Colour:** `navy` `#0D233A` (50–900) primary · `gold` `#FBBF24` sole accent · `surface` · `sky` links.
  Font **Figtree**; `text-2xs` = 0.6875rem. Radius `rounded-xl` cards / `rounded-lg` controls.
- **Card:** `rounded-xl border shadow-card transition duration-200`, tones `bg-white border-navy-100`
  · `bg-navy-50/60` · `bg-navy text-white` · transparent. `shadow-card` = two 1–3px navy-tinted shadows.
- **Button:** `inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition duration-200 ease-smooth`;
  variants `primary|navy|sky|secondary|outline|ghost|danger`; sizes `sm px-3 py-1.5 text-xs` · `md px-4 py-2.5 text-sm` · `lg px-6 py-3.5 text-base`.
- **Field:** `mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200`;
  label `block text-sm font-semibold text-navy-800`.
- **Badge:** colour **and** text **and** glyph, never colour alone. `rounded-full px-2.5 py-1 text-xs font-medium` + `h-1.5 w-1.5 rounded-full` dot.
- **Table:** `x-data-table` only — `min-w-[46rem]`, cells `px-4 py-3`, `sr-only` `<caption>`, focusable `role="region"`. Pass `variant="resident"`; never change the default string, current staff callers depend on it.
- **Empty state:** `rounded-xl border border-dashed border-navy-200 bg-navy-50/40 px-6 py-12 text-center`, icon `h-14 w-14 rounded-full bg-white text-navy-400 shadow-card ring-1 ring-navy-100`.
- **Page header:** staff use `x-breadcrumb` + `<h1 class="text-xl font-bold text-navy-900">` + `<p class="mt-1 text-sm text-gray-600">`. Do **not** use the photographic `x-page-header`.
- **Rhythm:** `pt-14 sm:pt-16 lg:pt-20` / `pb-14 sm:pb-16 lg:pb-20`.

## Verification

```bash
php artisan test && vendor/bin/pint --dirty --format agent
npm run build                        # if any CSS/Tailwind class changed

# resident/public guard — MUST print nothing
git diff --stat -- resources/views/resident resources/views/public resources/views/account/edit.blade.php \
  resources/views/components/resident resources/views/layouts/public.blade.php resources/css/app.css \
  tailwind.config.js app/Models config/bdrpis.php
git diff --stat -- resources/views/components/{shell,page-header,breadcrumb,data-table,empty-state,btn,status-badge,stat-card,card,alert,field,select-field,input-label,input-error,confirm-dialog,icon,request-timeline}.blade.php
git status --short
```

Markup-only assertions are not evidence a control works. A `x-on:click` outside any `x-data`
scope is silently inert with **no console error** — see `ResidentRequestsPageIsWiredTest`, added
after exactly that bug shipped. Verify interactions in a real browser.
