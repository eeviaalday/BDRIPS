{{--
    Settings, one section per group of keys.

    Rendered from the rows rather than from a hardcoded list, so a key added by a
    later migration appears here without a code change. The field name is the key,
    because the public templates read these by key and this screen must not be
    able to invent one.

    The section layout is presentation only. Every key is still stored in the
    "header" group it was seeded into, and no key is renamed, added or removed -
    SettingController::sections() groups by key name and falls back to the stored
    group for anything it does not recognise.

    The save bar appears only when the form is actually dirty. A permanently
    visible Save invites the click that submits an unchanged form, and the
    controller answers that with "Nothing changed", which reads as a failure.
--}}
<x-app-layout :user="auth()->user()" title="Settings">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'Settings']]" />

        <header>
            <h1 class="text-xl font-bold text-navy-900">Settings</h1>
            <p class="mt-1 text-sm text-gray-600">
                These are the values the public pages read. Changing one changes every page that
                shows it, immediately.
            </p>
        </header>

        @if (session('success'))
            <x-alert variant="success" icon="check-circle">{{ session('success') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert variant="warning" icon="x-circle">{{ session('error') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="danger" icon="x-circle">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        {{--
            WHY THE x-data BELOW IS BARE

            The notes that used to be inside that attribute are here instead, and
            that is not tidiness - it is the difference between working and not.

            A double quote inside a double-quoted attribute ENDS the attribute. A
            comment reading x-show="revision > 0" silently truncated the object
            literal, everything after it became stray markup, and the form ended up
            with no isDirty method at all: the save bar never appeared, and no
            error named the cause. A probe element in the same scope using a plain
            revision > 0 did appear, which is what proved the reactivity was fine
            and the attribute was broken.

            Three things the bare version below depends on, recorded here:

              revision is the tracked value. x-show only re-evaluates when something
              it tracked changes, and a field's value is a DOM property Alpine
              knows nothing about, so isDirty() reads this.revision to register the
              dependency. Without that line the effect has no dependency and the bar
              never reappears. It also covers paste, autofill and a browser
              restoring a field, none of which fire a keystroke.

              $root, not $el. Inside a method reached from x-on, this is the
              scope proxy, and its $el is the element the handler was bound to -
              the Discard button, which contains no inputs. check() therefore
              compared an empty list, reported not dirty, and discard() wrote
              nothing: the bar hid while the field kept the edited value. $root is
              the component's own root element, which is this form, and it does not
              change with the handler.

              dirty is a plain property that x-show reads directly, and the
              comparison lives in a method that ASSIGNS it from an input handler.
              Not a getter, and not a method called from the expression.

              Both of those were tried and both are dead in this build. A getter on
              the x-data object was read once while the scope was built, so the bar
              kept the value it had at init. A method called from the expression -
              x-show reading a method that reads this.revision - never re-ran its
              effect at all, even though calling that same method from the console
              returned true.

              What does work, and is what is used here: x-show reading a bare
              property, assigned by a handler. A probe with x-show on revision > 0
              updated correctly on every attempt, which is what identified the
              shape of the problem rather than the reactivity of it.

              Listening on the form rather than per field also covers paste, autofill
              and a browser restoring a field - none of which fire a keystroke on
              the field itself.

            $el rather than $refs.form, because x-data and x-ref were both on this
            form and x-init runs as a provider - before x-ref on the same element is
            registered - so $refs.form was undefined and readInitial threw on load.

            One Alpine scope for the form.

            dirty is computed by comparing every field to the value it was
            rendered with, rather than by listening for input events: a paste, an
            autofill, or a browser restoring a field all change a value without
            firing the events a naive dirty-check listens for. Reading the DOM on
            demand cannot miss one.

            Everything is an IIFE or a single expression because Alpine evaluates
            these attributes as expressions and does not fall back to a statement
            body - see the note in the kagawad checklist for the full story.
        --}}
        <form method="POST" action="{{ route('staff.settings.update') }}"
            x-data="{
                initial: {},
                dirty: false,
                readInitial() {
                    this.initial = {}
                    this.$root.querySelectorAll('input[name]').forEach((field) => { this.initial[field.name] = field.value })
                    this.check()
                },
                check() {
                    this.dirty = [...this.$root.querySelectorAll('input[name]')].some(
                        (field) => (this.initial[field.name] ?? '') !== field.value
                    )
                },
                discard() {
                    this.$root.querySelectorAll('input[name]').forEach((field) => {
                        if (field.name in this.initial) field.value = this.initial[field.name]
                    })
                    this.check()
                },
            }"
            x-init="readInitial()"
            x-on:input="check()"
            class="space-y-6">

            @csrf
            @method('PUT')

            {{-- Anchor nav. Real jump links rather than tabs: a tab would hide the
                 other sections, and on a form that is a trap, because Save has to
                 submit everything and a hidden section is a section nobody checked. --}}
            <nav aria-label="Settings sections" class="rounded-xl border border-navy-100 bg-white p-4 shadow-card">
                <ul class="flex flex-wrap gap-2">
                    @foreach ($sections as $section)
                        <li>
                            <a href="#settings-{{ $section['anchor'] }}"
                                class="inline-flex min-h-[36px] items-center gap-1.5 rounded-full border border-navy-200 bg-navy-50 px-3.5 py-1.5 text-xs font-semibold text-navy-800 transition hover:bg-navy-100">
                                {{ $section['label'] }}
                                <span class="tabular-nums opacity-60">{{ $section['settings']->count() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            {{-- Read-only preview of what is published right now. The values come
                 from the database, not from the inputs above: this box answers
                 "what is on the public site at this moment", and a preview that
                 mirrored the form would show unsaved text under a heading that
                 claims to be the live header. --}}
            <x-card tone="soft" padding="p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold text-navy-900">Preview on public header</h2>
                        <p class="mt-0.5 text-xs text-navy-600/80">
                            What the public pages are showing now, from the saved values.
                        </p>
                    </div>
                    <x-staff.status-badge status="neutral" label="Read-only" :dot="false" size="sm" />
                </div>

                <div class="mt-3 rounded-lg border border-navy-200 bg-white p-4">
                    <p class="font-bold leading-tight text-navy-900">
                        {{ $published['barangay_name'] ?? 'Barangay name not set' }}
                    </p>
                    <p class="text-xs text-navy-600/80">
                        {{ $published['barangay_address'] ?? 'Address not set' }}
                    </p>
                </div>
            </x-card>

            @foreach ($sections as $section)
                <section id="settings-{{ $section['anchor'] }}" aria-labelledby="heading-{{ $section['anchor'] }}"
                    class="scroll-mt-6 rounded-xl border border-navy-100 bg-white p-6 shadow-card">
                    <h2 id="heading-{{ $section['anchor'] }}" class="mb-4 text-base font-bold text-navy-900">
                        {{ $section['label'] }}
                    </h2>

                    <div class="space-y-4">
                        @foreach ($section['settings'] as $setting)
                            <div>
                                <label for="setting-{{ $setting->key }}" class="block text-sm font-semibold text-navy-800">
                                    {{ \Illuminate\Support\Str::headline($setting->key) }}
                                </label>
                                @if ($setting->description)
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $setting->description }}</p>
                                @endif
                                <input id="setting-{{ $setting->key }}" name="values[{{ $setting->key }}]" type="text"
                                    maxlength="2000" value="{{ old('values.'.$setting->key, $setting->value) }}"
                                    class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            {{-- The sticky bar. Fixed to the bottom rather than inline, so it is
                 still reachable after scrolling past the last field - which is
                 where a long form leaves you when you realise you changed
                 something at the top.

                 bottom-16 on a phone clears the fixed phone navigation, which is
                 also at the bottom of the viewport; the two would sit on top of
                 each other and neither would be tappable. --}}
            <div x-show="dirty" x-cloak
                class="fixed inset-x-0 bottom-16 z-40 border-t border-navy-200 bg-white/95 shadow-lg backdrop-blur md:bottom-0"
                role="status" aria-live="polite">
                <div class="mx-auto flex w-full max-w-4xl flex-col gap-3 px-6 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="flex items-center gap-2 text-sm font-semibold text-navy-900">
                        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gold-100 text-gold-800"
                            aria-hidden="true">
                            <x-icon name="edit" class="h-3.5 w-3.5" />
                        </span>
                        You have unsaved changes
                    </p>
                    <div class="flex gap-2">
                        <x-btn type="button" variant="ghost" size="md" x-on:click="discard()">Discard</x-btn>
                        <x-btn type="submit" variant="navy" size="md" icon="check">Save settings</x-btn>
                    </div>
                </div>
            </div>

            {{-- Room for the bar, so the last field is never underneath it. Only
                 while it is showing, so an untouched form has no dead space. --}}
            <div x-show="dirty" x-cloak aria-hidden="true" class="h-20 md:h-16"></div>
        </form>
    </div>
</x-app-layout>
