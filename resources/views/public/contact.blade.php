<x-public-layout>
    <x-slot name="title">Contact</x-slot>

    {{-- ============================================================
         CONTACT PAGE
         ============================================================
         Three panels side by side, each answering a different question a visitor
         arrives with:

           1. How do I reach the barangay?      - the contact details
           2. I am about to come in. What now? - the before-you-visit checklist
           3. Was my relief given out?          - the transparency lookup

         Side by side rather than stacked because they are read together: a
         visitor who is about to visit wants the checklist and the number in the
         same glance, and the relief lookup sits beside both because it is the
         other thing people come here to do.

         The two action buttons both lead behind a sign-in. A guest is sent to
         the login screen and returned to the page they wanted by
         redirect()->intended(); the header and footer link to the same two
         routes, so the redirect happens there too rather than only on this page.
    --}}

    <x-page-header :crumbs="['Contact Us']" title="Contact Us"
        description="For document concerns, resident verification, and relief enquiries. The Barangay Hall serves Zone 1, Bangued, Abra." />

    <x-shell>
        <div class="grid grid-cols-1 items-stretch gap-6 lg:grid-cols-3">

            {{-- ============================================================
                 1. REACH US
                 ============================================================ --}}
            <section aria-labelledby="reach-us-heading"
                class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-sky-700">Reach Us</p>
                <h2 id="reach-us-heading" class="mt-2 text-xl font-extrabold tracking-tight text-navy-900">
                    Contact the Barangay Hall
                </h2>
                <p class="mt-2 text-sm text-navy-600/80">
                    For document concerns, resident verification, and relief enquiries.
                </p>

                <dl class="mt-6 space-y-4">
                    <div class="flex gap-3">
                        <dt class="shrink-0">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-navy-50 text-navy-700">
                                <x-icon name="pin" class="h-5 w-5" />
                            </span>
                        </dt>
                        <dd>
                            <p class="text-sm font-bold text-navy-900">Address</p>
                            <p class="mt-0.5 text-sm text-navy-600">{{ $barangayAddress }}, Philippines</p>
                        </dd>
                    </div>

                    <div class="flex gap-3">
                        <dt class="shrink-0">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-navy-50 text-navy-700">
                                <x-icon name="phone" class="h-5 w-5" />
                            </span>
                        </dt>
                        <dd>
                            <p class="text-sm font-bold text-navy-900">Contact No.</p>
                            <p class="mt-0.5 text-sm text-navy-600">
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $officePhone) }}" class="underline underline-offset-2 hover:text-navy-900">{{ $officePhone }}</a>
                            </p>
                        </dd>
                    </div>

                    <div class="flex gap-3">
                        <dt class="shrink-0">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-navy-50 text-navy-700">
                                <x-icon name="mail" class="h-5 w-5" />
                            </span>
                        </dt>
                        <dd class="min-w-0">
                            <p class="text-sm font-bold text-navy-900">Email</p>
                            <a href="mailto:{{ $officeEmail }}" class="mt-0.5 block break-all text-sm text-navy-600 underline underline-offset-2 hover:text-navy-900">{{ $officeEmail }}</a>
                        </dd>
                    </div>

                    {{-- The official page, shown here as well as in the footer so a
                         visitor who came straight to /contact can find it without
                         scrolling. --}}
                    <div class="flex gap-3">
                        <dt class="shrink-0">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-navy-50 text-navy-700">
                                <x-icon name="facebook" class="h-5 w-5" />
                            </span>
                        </dt>
                        <dd class="min-w-0">
                            <p class="text-sm font-bold text-navy-900">Facebook</p>
                            <a href="{{ $officeFacebook }}" target="_blank" rel="noopener noreferrer"
                                class="mt-0.5 inline-flex items-center gap-1.5 break-all text-sm text-navy-600 underline underline-offset-2 hover:text-navy-900">
                                Barangay Linasin official page
                                <span class="sr-only">(opens in a new tab)</span>
                            </a>
                        </dd>
                    </div>
                </dl>
            </section>

            {{-- ============================================================
                 2. BEFORE YOU VISIT
                 ============================================================ --}}
            <section aria-labelledby="before-you-visit-heading"
                class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-4">
                    <img src="{{ asset('images/Zone 1_logo.png') }}" alt="" width="48" height="48"
                        class="h-12 w-12 rounded-full object-cover ring-2 ring-navy-200" />
                    <div class="min-w-0">
                        <p class="text-base font-extrabold leading-tight text-navy-900">{{ $barangayName }}</p>
                        <p class="text-xs text-navy-600">Zone 1, Bangued, Abra</p>
                    </div>
                </div>

                <h2 id="before-you-visit-heading" class="mt-6 text-sm font-bold uppercase tracking-wider text-navy-900">
                    Before you visit
                </h2>
                <ul class="mt-4 space-y-3 text-sm text-navy-600">
                    <li class="flex gap-2.5">
                        <x-icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-green-600" />
                        <span>Bring a valid government ID when claiming your document.</span>
                    </li>
                    <li class="flex gap-2.5">
                        <x-icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-green-600" />
                        <span>Present your reference number so staff can locate your request quickly.</span>
                    </li>
                    <li class="flex gap-2.5">
                        <x-icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-green-600" />
                        <span>Check your request status online first to avoid an unnecessary trip.</span>
                    </li>
                </ul>

                {{-- One action only. The old pair put a "Document Services" link
                     beside this one, which duplicated the header's own Services
                     link and gave the card two equally-weighted exits; the card is
                     about arriving at the hall, so the tracking lookup is the one
                     thing that acts on it. The button sits in a mt-auto wrapper so
                     all three panels line up along the foot, and the padding is on
                     the wrapper so it spaces the button rather than growing it. --}}
                <div class="mt-auto pt-6">
                    <x-btn :href="auth()->check() ? route('requests.track') : route('login')" variant="navy" size="lg"
                        icon="search" class="w-full">
                        Track a Request
                    </x-btn>
                </div>
            </section>

            {{-- ============================================================
                 3. RELIEF TRANSPARENCY
                 ============================================================ --}}
            <section aria-labelledby="verify-relief-heading"
                class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-sky-700">Relief Transparency</p>
                <h2 id="verify-relief-heading" class="mt-2 text-xl font-extrabold tracking-tight text-navy-900">
                    Verify Relief Records
                </h2>
                <p class="mt-2 text-sm leading-relaxed text-navy-600/80">
                    Residents must log in to verify relief distribution records using their registered
                    account and household details.
                </p>

                <ul class="mt-6 space-y-3 text-sm text-navy-600">
                    <li class="flex gap-2.5">
                        <x-icon name="shield" class="mt-0.5 h-4 w-4 shrink-0 text-sky-700" />
                        <span>Search returns only records matching the exact household reference you enter.</span>
                    </li>
                    <li class="flex gap-2.5">
                        <x-icon name="lock" class="mt-0.5 h-4 w-4 shrink-0 text-sky-700" />
                        <span>No other household&rsquo;s information is displayed.</span>
                    </li>
                </ul>

                {{-- The lookup itself lives on /transparency, behind the same
                     sign-in. This panel is the way in rather than a second copy
                     of the form. --}}
                <div class="mt-auto pt-6">
                    <x-btn :href="auth()->check() ? route('transparency.index') : route('login')" variant="primary" size="lg"
                        icon="search" class="w-full">
                        {{ auth()->check() ? 'Check Status' : 'Log In to Verify Status' }}
                    </x-btn>
                </div>
            </section>
        </div>
    </x-shell>
</x-public-layout>
