<x-public-layout>
    <x-slot name="title">Help &amp; FAQ</x-slot>

    <div class="mx-auto max-w-3xl space-y-5">
        <x-breadcrumb :items="[
            ['label' => 'My Dashboard', 'route' => 'resident.dashboard'],
            ['label' => 'Help & FAQ'],
        ]" />

        <x-card tone="white" class="bg-navy-50/40">
            <h2 class="text-base font-bold text-navy-900">Two different verifications</h2>
            <p class="mt-1 text-sm leading-relaxed text-navy-700">
                These are separate processes and one does not satisfy the other. It is the single
                most common source of confusion here, so it is spelled out first.
            </p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-navy-100 bg-white p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-navy-500">1. Email confirmation</p>
                    <p class="mt-1.5 text-sm leading-relaxed text-navy-800">
                        Automatic. We send a link to your email address; opening it proves the address
                        is yours. Nothing to do at the Hall.
                    </p>
                    <a href="{{ route('verification.notice') }}"
                        class="mt-3 inline-block text-sm font-semibold text-navy-700 hover:text-navy-900 hover:underline">
                        Check or resend the confirmation
                    </a>
                </div>

                <div class="rounded-xl border border-navy-100 bg-white p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-navy-500">2. Resident profile verification</p>
                    <p class="mt-1.5 text-sm leading-relaxed text-navy-800">
                        Done by a person. You submit a photo or scan of a valid ID during registration,
                        and the Barangay Secretary checks it. Until this is approved you cannot request
                        a document or be listed for relief.
                    </p>
                    <a href="{{ route('resident.verification.status') }}"
                        class="mt-3 inline-block text-sm font-semibold text-navy-700 hover:text-navy-900 hover:underline">
                        Check my verification status
                    </a>
                </div>
            </div>
        </x-card>

        {{--
            Every answer below is drawn from the backend's actual behaviour - the
            three-request cap, the seven-working-day window, the three-strike
            restriction and the four document types all come from
            DocumentRequestController and EnforceAbandonmentPolicy. Nothing here
            invents a fee, a processing time or an office hour.
        --}}
        @foreach ([
            [
                'q' => 'How do I request a document?',
                'a' => 'Open Request a Document from your dashboard, choose the type, state your purpose, and review before submitting. You will get a reference number immediately, and you can follow it under My Requests.',
            ],
            [
                'q' => 'Why can I not request a document yet?',
                'a' => 'Every document request is checked against your verified resident profile. Until the Barangay Secretary has approved the ID you submitted, there is nothing to check against. See Verification status for where yours has reached.',
            ],
            [
                'q' => 'How many requests can I have at once?',
                'a' => 'You may hold up to 3 open requests at a time. A request stops counting once it is completed, rejected or recorded as abandoned.',
            ],
            [
                'q' => 'How long do I have to collect a document?',
                'a' => '7 working days from the moment it is marked Ready for Pickup. After that the request is recorded as abandoned, and three abandoned requests restrict online applications for 30 days.',
            ],
            [
                'q' => 'What are the document types?',
                'a' => 'Barangay Clearance, Certificate of Indigency, First-Time Jobseeker Certificate, and Barangay Residency Certificate. The portal processes only these four.',
            ],
            [
                'q' => 'How much does each document cost?',
                'a' => 'Fees are set by the barangay and are confirmed by the Secretary when your request is processed. They are collected at the Barangay Hall, not online.',
            ],
            [
                'q' => 'Can I download my certificate from here?',
                'a' => 'No. This portal records and tracks requests; it does not deliver documents. A ready document is viewed from your request page and claimed in person at the Barangay Hall.',
            ],
            [
                'q' => 'How do I know my request was received?',
                'a' => 'A reference number is generated the moment you submit. It appears in My Requests, and you can look it up directly with Track by reference number.',
            ],
            [
                'q' => 'Why was my request rejected?',
                'a' => 'The Secretary records a reason, and it is shown in full on the request page. If you think it is wrong, or your circumstances have changed, please speak to the Secretary at the Hall.',
            ],
            [
                'q' => 'How do I get on a relief list?',
                'a' => 'Relief batches are created by staff and mapped automatically to households with a verified profile. If your household is included, the assistance and its status appear under Relief Program.',
            ],
            [
                'q' => 'I changed my phone number or email. What now?',
                'a' => 'Change your email or password under Account Settings. Your resident record — household number, address, purok and submitted ID — is verified by the Secretary, so ask at the Hall to have it corrected.',
            ],
            [
                'q' => 'Who can see my personal details?',
                'a' => 'Only you and the barangay staff handling your verification and requests. Another resident cannot look up your requests or documents by reference number.',
            ],
        ] as $item)
            <x-card tone="white" class="p-0">
                <details class="group">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-left text-sm font-semibold text-navy-900 transition hover:bg-navy-50/50 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-navy-400">
                        {{ $item['q'] }}
                        <span class="shrink-0 text-navy-400 transition group-open:rotate-45" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                            </svg>
                        </span>
                    </summary>
                    <p class="border-t border-navy-50 px-5 py-4 text-sm leading-relaxed text-navy-700">
                        {{ $item['a'] }}
                    </p>
                </details>
            </x-card>
        @endforeach

        <x-card tone="white" class="bg-navy-50/40">
            <div class="flex items-start gap-3">
                <x-icon name="phone" class="mt-0.5 h-5 w-5 shrink-0 text-navy-500" />
                <div class="text-sm leading-relaxed text-navy-700">
                    <p class="font-semibold text-navy-900">Still need help?</p>
                    <p class="mt-1">
                        Contact details for the hall and the current office hours are published on the
                        barangay's public pages. Bring your reference number if you are asking about a
                        specific request.
                    </p>
                    <a href="{{ route('resident.directory') }}"
                        class="mt-3 inline-block font-semibold text-navy-700 hover:text-navy-900 hover:underline">
                        Public information and officials
                    </a>
                </div>
            </div>
        </x-card>
    </div>
</x-public-layout>
