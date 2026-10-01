<x-public-layout>
    <x-slot name="title">Services</x-slot>

    {{-- ============================================================
         SERVICES PAGE
         ============================================================
         The document service catalogue plus the request walkthrough that used
         to sit on the homepage as a separate "How It Works" band. Both live
         here because they describe the same thing: how a resident obtains a
         document through BDRPIS.
    --}}

    <x-page-header :crumbs="['Services']" title="Supported Document Services"
        description="Barangay Linasin processes four document types online. Browse them below, then follow the five steps to submit your request.">
        {{-- Small and wrapping on purpose. The banner is 300px tall and shared
             with six other pages: at the default size these two stack on a
             phone and push this banner to 344px, past every other page's. --}}
        <div class="flex flex-wrap items-center gap-2">
            <x-btn :href="auth()->check() ? route('resident.requests.create') : route('register')" variant="primary" size="sm"
                icon="document">
                Request a Document
            </x-btn>
              <x-btn :href="route('resident.requests.index')" variant="outline" size="sm" icon="search">
                  Track my Requests
              </x-btn>
        </div>
    </x-page-header>

    {{-- Catalogue --}}
    <x-shell tone="surface">
        <x-section-heading eyebrow="Catalogue" title="Available Documents"
            description="Each request is reviewed by the Barangay Secretary and released at the Barangay Hall." />

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (config('bdrpis.services') as $service)
                <x-service-card
                    :title="$service['title']"
                    :description="$service['description']"
                    :icon="$service['icon']"
                    :tone="$service['tone']"
                    :href="auth()->check() ? route('resident.requests.create') : route('register')" />
            @endforeach
        </div>
    </x-shell>

    {{-- Walkthrough, relocated from the homepage --}}
    <x-shell id="how-it-works">
        <x-section-heading eyebrow="How It Works" title="Five Simple Steps"
            description="From account creation to claiming your document at the Barangay Hall." />

        <ol class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-5">
            @foreach (config('bdrpis.steps') as $index => $step)
                <li class="group relative flex flex-col rounded-xl border border-navy-100 bg-white p-6 shadow-card transition duration-200 hover:-translate-y-1 hover:shadow-card-hover">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-navy text-sm font-extrabold text-gold">
                        {{ str_pad((string) ($index + 1), 02, '0', STR_PAD_LEFT) }}
                    </span>

                    <h3 class="mt-4 text-base font-bold leading-snug text-navy-900">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-navy-600/80">{{ $step['description'] }}</p>
                </li>
            @endforeach
        </ol>

        <x-alert variant="info" icon="inbox" class="mx-auto mt-10 max-w-3xl">
            Documents are released at the <span class="font-semibold">Barangay Hall</span> and are not
            delivered digitally. You will be able to claim your document once its status is marked ready.
        </x-alert>
    </x-shell>

    {{-- Next step --}}
    <x-shell tone="surface" width="narrow">
        <x-section-heading eyebrow="Next Step" title="Ready to request a document?"
            align="center" />

        <div class="mt-8 flex flex-col items-center gap-3 text-center">
            <p class="text-sm text-navy-600">
                @auth
                    Your resident account is ready. Open the
                    <a href="{{ route('resident.requests.create') }}" class="font-semibold text-sky-700 underline underline-offset-2 hover:text-navy-900">document request form</a>
                    to begin.
                @else
                    New to BDRPIS?
                    <a href="{{ route('register') }}" class="font-semibold text-sky-700 underline underline-offset-2 hover:text-navy-900">Create a resident account</a>
                    to begin.
                @endauth
            </p>
            <p class="text-sm text-navy-600">
                Already applied?
                  <a href="{{ route('resident.requests.index') }}" class="font-semibold text-sky-700 underline underline-offset-2 hover:text-navy-900">Track my requests</a>.
            </p>
        </div>

        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
            <x-btn :href="route('about')" variant="outline" size="md" icon="building">
                About the Barangay
            </x-btn>
            <x-btn :href="route('contact')" variant="outline" size="md" icon="phone">
                Contact the Hall
            </x-btn>
        </div>
    </x-shell>
</x-public-layout>
