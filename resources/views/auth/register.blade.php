<!DOCTYPE html>
<html lang="en" style="--photo: url('{{ asset('images/login.jpg') }}')">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Create your account · Barangay Linasin</title>
{{--
    app.css first, then the design's own rules after it, so where the two
    disagree on equal specificity the design wins. app.css is what styles the
    <x-field> and <x-select-field> controls the wizard is built from, and app.js
    is Alpine, which drives the step navigation - without either, all five
    steps would render stacked and the Next button would do nothing.

    Figtree comes from app.css, which self-hosts it, so there is no third-party
    font request on a page that collects a resident's personal data.
--}}
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
:root{
  --photo:linear-gradient(180deg,#5a86c0 0%,#9dbbe0 30%,#4d7358 58%,#1d3a2b 100%);
  --navy:#0c2240; --gold:#f5b82e; --goldink:#3a2900;
  --glass:rgba(255,255,255,.72); --ink:#15294a; --muted:#43536e; --line:rgba(21,41,74,.18); --field:rgba(255,255,255,.75); --blue:#2b5aa8;
  --ribbon:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 800' preserveAspectRatio='none'><path d='M60 0C100 50 100 150 60 200C20 250 20 350 60 400C100 450 100 550 60 600C20 650 20 750 60 800' fill='none' stroke='black' stroke-width='10'/></svg>") 0 0/120px 100% no-repeat;
  --wave:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 800' preserveAspectRatio='none'><path d='M60 0C100 50 100 150 60 200C20 250 20 350 60 400C100 450 100 550 60 600C20 650 20 750 60 800L120 800L120 0Z'/></svg>");
  --mask:var(--wave) 0 0/120px 100% no-repeat, linear-gradient(#000,#000) 119px 0/calc(100% - 119px) 100% no-repeat;
  box-sizing:border-box; padding-top:env(safe-area-inset-top,0px); padding-bottom:env(safe-area-inset-bottom,0px);
}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){--glass:rgba(14,24,42,.86);--ink:#e8eefb;--muted:#a3b2cc;--line:rgba(232,238,251,.2);--field:rgba(255,255,255,.06);--blue:#8db1f2}}
:root[data-theme="dark"]{--glass:rgba(14,24,42,.86);--ink:#e8eefb;--muted:#a3b2cc;--line:rgba(232,238,251,.2);--field:rgba(255,255,255,.06);--blue:#8db1f2}
*{box-sizing:border-box}
/* The wizard hides its steps with x-show. Without this the first paint would
   show all five at once; app.css defines it too, and a duplicate is harmless. */
[x-cloak]{display:none !important}
html{scroll-padding-top:env(safe-area-inset-top,0px)}
body{margin:0;min-height:100vh;font-family:Figtree,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink);line-height:1.5;background:var(--photo) center/cover fixed}
:focus-visible{outline:3px solid var(--gold);outline-offset:2px}
a{color:var(--blue);font-weight:600}

/*
  .page is overflow-x, not overflow. The five steps make this column taller
  than a viewport, and a hidden overflow would render the fields below the fold
  while making them impossible to scroll to.
*/
.page{min-height:100vh;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);position:relative;overflow-x:hidden}

.left{position:relative;z-index:1;color:#fff;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;padding:56px 150px 56px 48px;
  background:linear-gradient(180deg,rgba(12,34,64,.62),rgba(12,34,64,.8));backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px)}
.home{position:absolute;top:24px;left:28px;color:#fff;font-size:14px;font-weight:600;text-decoration:none;opacity:.9}
.home:hover{text-decoration:underline}
.seal{width:100px;height:100px;border-radius:50%;border:4px solid rgba(255,255,255,.45);box-shadow:0 10px 30px rgba(0,0,0,.35);object-fit:cover}
.left h2{margin:20px 0 2px;font-size:28px;letter-spacing:.14em;font-weight:800}
.zone{opacity:.85;font-size:15px}
.rule{width:56px;height:3px;background:var(--gold);border-radius:3px;margin:24px auto}
.left h1{margin:0 0 8px;font-size:38px;letter-spacing:-.02em;line-height:1.1}
.left p{margin:0;max-width:340px;opacity:.92}
.perks{list-style:none;margin:28px 0 0;padding:0;display:grid;gap:12px;text-align:left}
.perks li{display:flex;gap:12px;align-items:center;font-size:15px}
.perks span{flex:none;width:34px;height:34px;border-radius:10px;background:rgba(245,184,46,.2);color:var(--gold);display:grid;place-items:center}
/* The shared icon set is stroke-width 1.7; the design is 2. stroke-width is a
   presentation attribute, so this declaration wins over it. */
.perks svg{stroke-width:2}

/*
  The right column is shared with /login, so the values that place the glass
  sheet and the wave - 170px of left padding clearing the curve, and 40px at the
  trailing edge - are unchanged. Only the vertical padding is tightened, from
  48px to 24px, which is symmetric whitespace inside a sheet that is already in
  the right place and so is invisible, but buys back half the height the wizard
  needs to avoid scrolling.
*/
.right{position:relative;margin-left:-120px;z-index:2;display:grid;place-items:center;padding:24px 40px 24px 170px}
.right::before,.right::after{content:"";position:absolute;top:0;bottom:0;z-index:-1}
.right::before{left:-14px;width:120px;background:var(--gold);-webkit-mask:var(--ribbon);mask:var(--ribbon)}
.right::after{left:0;right:0;-webkit-mask:var(--mask);mask:var(--mask);background:var(--glass);backdrop-filter:blur(18px) saturate(1.2);-webkit-backdrop-filter:blur(18px) saturate(1.2)}

.wizard{width:100%;max-width:560px}
.wizard h3{margin:0;font-size:24px;letter-spacing:-.01em}
.sub{margin:2px 0 14px;font-size:13.5px;color:var(--muted)}

/* ============================================================
     STEP INDICATOR
     ============================================================ */

.steps{list-style:none;margin:0 0 12px;padding:0;display:flex;align-items:flex-start}
.steps li{display:flex;flex:1;align-items:flex-start}
.steps li:last-child{flex:none}
.dot{position:relative;z-index:10;flex:none;width:28px;height:28px;border-radius:50%;display:grid;place-items:center;
  font-size:12px;font-weight:700;transition:background-color .18s,color .18s,box-shadow .18s;
  border:1.5px solid var(--line);background:var(--field);color:var(--muted)}
.dot.is-current{background:var(--gold);border-color:var(--gold);color:var(--goldink);box-shadow:0 6px 16px rgba(245,184,46,.45)}
.dot.is-done{background:var(--gold);border-color:var(--gold);color:var(--goldink);opacity:.7}
.rail{flex:1;height:2px;margin-top:13px;background:var(--line);transition:background-color .18s}
.rail.is-done{background:var(--gold)}
.step-label{margin:7px 0 0;text-align:center;font-size:12px;font-weight:500;color:var(--muted)}
.step-label strong{color:var(--ink);font-weight:700}

/* ============================================================
     STEP BODY
     ============================================================ */

.wizard fieldset{border:0;margin:0;padding:0;min-width:0}
.step-heading{margin:0 0 8px;font-size:15.5px;font-weight:800;color:var(--ink)}
.grid2{display:grid;grid-template-columns:1fr;gap:9px 10px;margin-bottom:10px}
@media(min-width:560px){.grid2{grid-template-columns:1fr 1fr}}
.span2{grid-column:1 / -1}

/*
  Compactness, for one reason: the tallest step has to clear a laptop viewport
  without the card scrolling, because a step you have to scroll to finish is a
  step people abandon. The controls are the bulk of the height, so they are
  tightened here rather than by editing the field and select components
  themselves - those are shared with the sign-in card, which has all the room it
  needs and should not inherit this form's tighter rhythm.

  Note for whoever edits this next: no component tags in angle brackets in here.
  Blade compiles them wherever it finds them, including inside a raw style
  block, so naming one in this comment makes the template try to render a real
  instance of it mid-stylesheet and leaves its control flow unterminated.

  These are 0,2,1 selectors so they beat the components' own single-class
  padding utilities on specificity rather than on stylesheet order, which is not
  something a caller is allowed to depend on.
*/
.wizard .grid2 label{font-size:13px}
.wizard .grid2 .relative,       /* the icon/reveal wrapper the field component renders */
.wizard .grid2 select{margin-top:3px}
.wizard .grid2 input:not([type=file]),
.wizard .grid2 select{padding-top:.45rem;padding-bottom:.45rem}
.wizard .grid2 [id$="-hint"]{margin-top:2px;font-size:11.5px;line-height:1.35}

/*
  A file control is two pieces here: the real input, clipped to nothing, and the
  row beside it. A native file input cannot be laid out this way - the browser
  paints the button and the chosen filename together inside one box - so the
  filename is echoed into a read-only box and the button opens the picker.

  The real input is clipped rather than given display:none, so it stays in the
  form, stays in the tab order, and keeps the name the server receives. The echo
  box carries no name, so nothing about the presentation reaches the request.
*/
.file-input{
  position:absolute;width:1px;height:1px;padding:0;margin:-1px;
  overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0}

.file-row{display:flex;align-items:stretch;gap:10px}
.file-name{width:100%;min-width:0;
  padding-left:44px;              /* clears the leading icon, as the text inputs do */
  border:1px solid var(--line);border-radius:12px;background:var(--field);
  font-size:14px;color:var(--muted)}
.file-name::placeholder{color:var(--muted);opacity:.75}
/* A chosen filename is content, not a hint, so it is drawn in the ink colour
   and the "No file chosen" prompt stays muted. */
.file-name:not(:placeholder-shown){color:var(--ink)}
.file-browse{flex:none;display:inline-flex;align-items:center;padding:0 1rem;border:0;border-radius:12px;
  background:var(--navy);color:#fff;font:inherit;font-size:13.5px;font-weight:700;cursor:pointer;
  transition:background .15s}
.file-browse:hover{background:#14304f}

/* ============================================================
     BUTTONS - gold to advance, navy to commit, outline to go back
     ============================================================ */

.actions{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;padding-top:12px;border-top:1px solid var(--line)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:0 18px;border:0;border-radius:12px;
  font:inherit;font-size:14px;font-weight:700;cursor:pointer;transition:transform .15s,box-shadow .15s,background .15s,border-color .15s}
.btn:focus-visible{outline:3px solid var(--gold);outline-offset:2px}
.btn-gold{background:var(--gold);color:var(--goldink);box-shadow:0 4px 14px rgba(245,184,46,.35)}
.btn-gold:hover{transform:translateY(-1px);box-shadow:0 10px 22px rgba(245,184,46,.45)}
.btn-navy{background:var(--navy);color:#fff;box-shadow:0 6px 18px rgba(12,34,64,.28)}
.btn-navy:hover{background:#14304f;transform:translateY(-1px)}
.btn-outline{background:transparent;color:var(--ink);border:1.5px solid var(--line)}
.btn-outline:hover{border-color:var(--blue);background:var(--field)}

/* ============================================================
     THE WAY OUT
     ============================================================
     Sitting on the same row as Next Step, on the first step only, so it is
     never in the way of the steps that actually need that space. It lives
     inside step 1's fieldset, so the other four steps hide it with everything
     else in them.
*/
.signin-prompt{margin:0;font-size:13px;color:var(--muted)}
.signin-prompt a{font-style:italic;font-weight:700;text-decoration:none;white-space:nowrap}
.signin-prompt a:hover{text-decoration:underline}

@media(max-width:860px){
  .page{grid-template-columns:1fr}
  .left{padding:64px 24px 56px}
  .perks{display:none}.left h1{font-size:30px}.rule{margin:16px auto}
  .right{margin:-28px 0 0;padding:26px 18px 30px;border-radius:28px 28px 0 0}
  .right::before,.right::after{-webkit-mask:none;mask:none;border-radius:28px 28px 0 0}
  .right::before{left:0;right:0;width:auto;top:-6px;background:rgba(245,184,46,.85)}
}
</style>
</head>
<body>
@php
    $steps = config('bdrpis.registration.steps');
    $lastStep = count($steps);
    $defaultNationality = config('bdrpis.registration.default_nationality');
    $otherNationality = config('bdrpis.registration.other_nationality');

    /*
     * Which step to open on. Normally the first; after a rejected submission,
     * the step that held the field the server refused, so the resident is put
     * back on the question they got wrong instead of at the top of a form they
     * have already filled in. Flashed by RegisterRequest.
     */
    $opensOn = (int) session('registration_step', 1);
@endphp

<div class="page">
    {{-- ============================================================
         LEFT: BRANDING
         ============================================================
         The same frame as /login - navy gradient over the photograph, blurred,
         with the seal, wordmark, gold rule and a gold-iconed list. The list
         carries the five steps rather than a sales pitch, which is the one
         thing a resident signing up actually wants to know here.
    --}}
    <section class="left">
        <a class="home" href="{{ route('home') }}">&larr; Back to home</a>

        <img class="seal" src="{{ asset('images/Zone 1_logo.png') }}" alt=""
            width="100" height="100" />

        <h2>BARANGAY LINASIN</h2>
        <div class="zone">Zone 1, Bangued, Abra</div>
        <div class="rule"></div>

        <h1>Create your account</h1>
        <p>Five short steps. Have a photo or scan of a valid ID and your household reference
            number ready - the Barangay Secretary checks them against the master list.</p>

        <ul class="perks">
            @foreach ([
                ['icon' => 'user', 'text' => 'Your name, birth date and civil status'],
                ['icon' => 'pin', 'text' => 'Your address and sector or purok'],
                ['icon' => 'mail', 'text' => 'An email we can confirm your account on'],
                ['icon' => 'home', 'text' => 'Your household reference number'],
                ['icon' => 'id-badge', 'text' => 'Your valid ID, and a password'],
            ] as $perk)
                <li>
                    <span aria-hidden="true"><x-icon :name="$perk['icon']" class="h-[18px] w-[18px]" /></span>
                    {{ $perk['text'] }}
                </li>
            @endforeach
        </ul>
    </section>

    {{-- ============================================================
         RIGHT: THE WIZARD
         ============================================================
         One form, five client-side steps. The steps are a way of filling the
         form in, not five separate saves: every field posts together on the
         single submit, so a half-filled wizard can never be saved and a
         resident who abandons it halfway has entered nothing. The steps and the
         fields each one owns come from config('bdrpis.registration'), which is
         also what RegisterRequest validates against, so a step cannot render a
         field the form does not post.

         Nothing here asks for anything twice. The address, household number and
         national ID collected in steps 2, 4 and 5 are written to the resident
         profile as part of signing up, so the verification screen afterwards
         only has to take the ID photograph.
    --}}
    <section class="right">
        <div class="wizard" x-data="{
            step: {{ $opensOn }},
            nationality: '{{ old('nationality', $defaultNationality) }}',
            lastStep: {{ $lastStep }},
            // Step number => label, so the visible step name is looked up from
            // the same config the stepper is built from rather than spelled out
            // a second time here.
            labels: @js(collect($steps)->mapWithKeys(fn (array $s, int $i): array => [$i + 1 => $s['label']])->all()),
            goTo(n) {
                this.step = Math.min(Math.max(n, 1), this.lastStep);
                this.$nextTick(() => this.$root.querySelector('[data-step-heading]')?.focus());
            },
            next() { this.goTo(this.step + 1) },
            back() { this.goTo(this.step - 1) },
        }">
            <h3>Create your account</h3>
            <p class="sub">Join our community and access our online services.</p>

            {{-- ============================================================
                 STEP INDICATOR
                 ============================================================
                 An ordered list, not a row of bare circles: it is a sequence the
                 resident is moving through, and a screen reader should say so.
                 The badge is decorative - "Step 2 of 5, Contact" is what is read
                 out instead, from the label - so the number is hidden from
                 assistive technology rather than read as a bare digit.

                 The connecting rail is a flex-grow child of each item rather
                 than a separate element, so it stops short of the last circle
                 instead of running past it.
            --}}
            <nav aria-label="Registration progress">
                <ol class="steps">
                    @foreach ($steps as $index => $step)
                        @php $number = $index + 1; @endphp
                        <li>
                            <span class="dot"
                                x-bind:class="step > {{ $number }} ? 'is-done' : (step === {{ $number }} ? 'is-current' : '')"
                                aria-hidden="true"
                                x-text="step > {{ $number }} ? '&#10003;' : '{{ $number }}'"></span>

                            @if (! $loop->last)
                                <span class="rail" x-bind:class="step > {{ $number }} ? 'is-done' : ''"
                                    aria-hidden="true"></span>
                            @endif

                            <span class="sr-only">Step {{ $number }} of {{ $lastStep }}: {{ $step['label'] }}</span>
                        </li>
                    @endforeach
                </ol>

                <p class="step-label">
                    Step <span x-text="step"></span> of {{ $lastStep }} &mdash;
                    <strong x-text="labels[step]"></strong>
                </p>
            </nav>

            {{-- multipart, because step 5 posts a file. Without this the browser
                 sends the filename as a plain string and the request has no
                 uploaded file at all, which surfaces as "please attach a valid
                 ID" no matter what the resident actually picked. --}}
            <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
                @csrf

                @foreach ($steps as $index => $step)
                    @php $number = $index + 1; @endphp
                    {{-- Only the step being shown is not x-cloak'd, so the first
                         paint shows exactly one step with no flash of all five -
                         and no dependence on Alpine removing the attribute before
                         the form becomes visible, which would leave the form
                         blank if it ever did not. --}}
                    <fieldset x-show="step === {{ $number }}" @if ($number !== $opensOn) x-cloak @endif>
                        <legend class="sr-only">{{ $step['heading'] }}</legend>

                        <h2 class="step-heading" data-step-heading tabindex="-1">{{ $step['heading'] }}</h2>

                        {{-- Two columns for pairs of short fields; the
                             full-width ones opt out with .span2. --}}
                        <div class="grid2">

                            @switch($step['key'])
                                @case('personal')
                                    <x-field id="name" name="name" label="Full name" icon="user" variant="soft"
                                        :value="old('name')" placeholder="Juan Dela Cruz"
                                        autocomplete="name" required class="span2" />

                                    <x-field id="date_of_birth" name="date_of_birth" type="date" variant="soft"
                                        label="Date of birth" :value="old('date_of_birth')"
                                        autocomplete="bday" required
                                        hint="The Secretary cross-checks this against the master list." />

                                    <x-select-field id="gender" name="gender" label="Gender" variant="soft"
                                        :options="config('bdrpis.registration.genders')"
                                        :value="old('gender')" placeholder="Select Gender" required />

                                    <x-select-field id="civil_status" name="civil_status" label="Civil status" variant="soft"
                                        :options="config('bdrpis.registration.civil_statuses')"
                                        :value="old('civil_status')" placeholder="Select Civil Status" required />

                                    {{-- Nationality is not a closed list, so
                                         "Others" swaps the select for a box
                                         rather than forcing a free-text answer
                                         for everyone. The select itself pairs
                                         with Civil status rather than spanning
                                         the row, which is what keeps this - the
                                         tallest of the five steps - on one
                                         screen. --}}
                                    <x-select-field id="nationality" name="nationality" label="Nationality" variant="soft"
                                        :options="[$defaultNationality => $defaultNationality, $otherNationality => $otherNationality]"
                                        :value="old('nationality', $defaultNationality)"
                                        placeholder="Select Nationality" x-model="nationality" required
                                        hint="Most residents here are Filipino." />

                                    <div x-show="nationality === @js($otherNationality)" x-cloak class="span2">
                                        <x-field id="nationality_other" name="nationality_other" variant="soft"
                                            label="Your nationality" :value="old('nationality_other')"
                                            placeholder="e.g. American" />
                                    </div>
                                    @break

                                @case('address')
                                    <x-field id="address" name="address" label="Complete address" icon="pin" variant="soft"
                                        :value="old('address')" placeholder="Purok 3, Barangay Linasin"
                                        autocomplete="street-address" required class="span2" />

                                    <x-select-field id="sector_id" name="sector_id" label="Sector / Purok" variant="soft"
                                        :options="$sectors->pluck('sector_name', 'id')"
                                        :value="old('sector_id')" placeholder="Select your sector" required
                                        class="span2" />
                                    @break

                                @case('contact')
                                    <x-field id="email" name="email" type="email" label="Email address" icon="mail" variant="soft"
                                        :value="old('email')" placeholder="you@example.com"
                                        autocomplete="username" required class="span2" />

                                    <x-alert variant="info" icon="mail" class="span2">
                                        Your email is how the Barangay Secretary reaches you about a request, and how
                                        you confirm this account. It has to be one you actually read.
                                    </x-alert>
                                    @break

                                @case('household')
                                    <x-field id="household_number" name="household_number" variant="soft"
                                        label="Household reference number" icon="home"
                                        :value="old('household_number')" placeholder="e.g. HH-0001"
                                        mono required class="span2"
                                        hint="Shown on your Barangay ID. Ask at the hall if you are not sure of it." />
                                    @break

                                @case('identity')
                                    {{-- Replaces the typed national / voter ID
                                         number. Not every resident has one the
                                         barangay has recorded, and mistyping a
                                         number is worse than photographing the
                                         document it is printed on - the
                                         Secretary reads the number off the
                                         upload either way. --}}
                                    {{-- No hint under this one. The label says what
                                         the control is, and the accepted formats
                                         are already enforced by the accept
                                         attribute on the picker and by the
                                         validation messages if a resident
                                         ignores it - repeating them underneath
                                         made step 5 the only field in the form
                                         with a paragraph of help text under it,
                                         and pushed the card past the height the
                                         other steps fit in. --}}
                                    <x-field id="valid_id_photo" name="valid_id_photo" type="file" variant="soft"
                                        label="Valid ID Photo / Document" icon="id-badge" required
                                        accept=".jpg,.jpeg,.png,.pdf" class="span2" />

                                    <x-field id="password" name="password" type="password" label="Password" variant="soft"
                                        icon="lock" autocomplete="new-password" revealable required
                                        hint="Use at least 8 characters." />

                                    <x-field id="password_confirmation" name="password_confirmation" type="password"
                                        variant="soft" label="Confirm password" icon="lock"
                                        autocomplete="new-password" revealable required />
                                    @break
                            @endswitch
                        </div>

                        {{-- Step navigation. On the first step the left of the
                             row is the way out rather than a dead placeholder -
                             a resident who realises they already have an
                             account should not have to scroll to find the link.
                             On the later steps that space goes to "Previous",
                             so the prompt never competes with the control the
                             resident actually needs there.

                             The prompt lives inside step 1's own fieldset, so
                             the x-show that reveals steps 2 to 5 hides it along
                             with the rest of their contents. There is no second
                             copy to keep in step with anything. --}}
                        <div class="actions">
                            @if ($number > 1)
                                <button type="button" class="btn btn-outline" @click="back">Previous Step</button>
                            @else
                                <p class="signin-prompt">
                                    Already have an account?
                                    <a href="{{ route('login') }}" class="italic">Login</a>
                                </p>
                            @endif

                            @if ($number < $lastStep)
                                <button type="button" class="btn btn-gold" @click="next">Next Step</button>
                            @else
                                <button type="submit" class="btn btn-navy">Create Account</button>
                            @endif
                        </div>
                    </fieldset>
                @endforeach

                {{-- A registration the server rejected is a failure of the whole
                     form, so the resident is told where it went wrong rather
                     than being left to hunt. --}}
                @if ($errors->any() && $opensOn === 1)
                    <x-alert variant="danger" icon="x-circle" class="mt-5">
                        Some details need fixing. The step below has been marked so you can see which.
                    </x-alert>
                @endif
            </form>
        </div>
    </section>
</div>
</body>
</html>
