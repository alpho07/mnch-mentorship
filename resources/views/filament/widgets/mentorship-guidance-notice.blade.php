<x-filament::section>
    <div class="space-y-4">
        <div class="flex items-start gap-3">
            <x-filament::icon icon="heroicon-o-light-bulb" class="mt-1 h-6 w-6 text-primary-600" />
            <div class="space-y-1">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    Prepare before starting mentorship
                </h3>
                <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                    First identify the gaps at the facility, then review the mentorship manuals so you know what to mentor on and how each session should be delivered. After that, confirm the mentee list and check every mentee has a name, email, phone, cadre, and department before you start the class.
                </p>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <a
                href="{{ url('/resources/infant-child-mentorship-manual') }}"
                target="_blank"
                rel="noopener noreferrer"
                class="rounded-lg border border-gray-200 bg-white p-4 text-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600 dark:hover:bg-gray-800"
            >
                <div class="font-semibold text-gray-950 dark:text-white">Infant and Child Mentorship Manual</div>
                <div class="mt-1 text-gray-600 dark:text-gray-400">Use this to plan content and session flow.</div>
            </a>

            <a
                href="https://mnchkenyamentorship.org/resources/newborn-mentorship-mentors-manual"
                target="_blank"
                rel="noopener noreferrer"
                class="rounded-lg border border-gray-200 bg-white p-4 text-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600 dark:hover:bg-gray-800"
            >
                <div class="font-semibold text-gray-950 dark:text-white">Newborn Mentorship Mentor's Manual</div>
                <div class="mt-1 text-gray-600 dark:text-gray-400">Use this when the mentorship focus includes newborn care.</div>
            </a>

            <a
                href="{{ route('resources.search', ['q' => 'mentorship manual']) }}"
                target="_blank"
                rel="noopener noreferrer"
                class="rounded-lg border border-gray-200 bg-white p-4 text-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600 dark:hover:bg-gray-800"
            >
                <div class="font-semibold text-gray-950 dark:text-white">Search Mentorship Manuals</div>
                <div class="mt-1 text-gray-600 dark:text-gray-400">Find manuals, guides, and learning materials.</div>
            </a>

            <a
                href="{{ route('resources.search', ['q' => 'MNCH guidelines']) }}"
                target="_blank"
                rel="noopener noreferrer"
                class="rounded-lg border border-gray-200 bg-white p-4 text-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600 dark:hover:bg-gray-800"
            >
                <div class="font-semibold text-gray-950 dark:text-white">MNCH Guidelines</div>
                <div class="mt-1 text-gray-600 dark:text-gray-400">Review supporting protocols before mentoring.</div>
            </a>
        </div>
    </div>
</x-filament::section>
