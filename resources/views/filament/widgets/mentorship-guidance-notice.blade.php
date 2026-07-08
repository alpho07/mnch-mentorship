<style>
@keyframes guide-pulse {
    0%,100% { box-shadow: 0 0 0 0 rgba(0,151,167,0.55); }
    50%      { box-shadow: 0 0 0 10px rgba(0,151,167,0); }
}
.guide-pulse-btn { animation: guide-pulse 2s ease-in-out infinite; }
</style>

<div
    x-data="{
        showPrompt: false,
        open: false,
        step: 0,
        init() {
            if (!localStorage.getItem('mnch_guide_prompted')) {
                this.$nextTick(() => { this.showPrompt = true; });
            }
        },
        dismiss() {
            localStorage.setItem('mnch_guide_prompted', '1');
            this.showPrompt = false;
        },
        openGuide() {
            this.step = 0;
            this.open = true;
        },
        acceptGuide() {
            this.dismiss();
            this.openGuide();
        },
        next() { if (this.step < 6) this.step++; },
        prev() { if (this.step > 0) this.step--; },
        close() { this.open = false; }
    }"
>
    {{-- ── Auto-prompt banner ─────────────────────────────────── --}}
    <div
        x-show="showPrompt"
        x-cloak
        x-transition:enter="transition ease-out duration-400"
        x-transition:enter-start="opacity-0 -translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-3"
        class="mb-4 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-primary-200 bg-primary-50 px-5 py-4 dark:border-primary-800 dark:bg-primary-900/20"
    >
        <div class="flex items-center gap-3">
            <span class="text-2xl">👋</span>
            <p class="text-base font-medium text-primary-900 dark:text-primary-100">
                New here? Want a quick walkthrough of how mentorships work on this system?
            </p>
        </div>
        <div class="flex shrink-0 gap-2">
            <button
                @click="acceptGuide()"
                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-primary-700 focus:outline-none"
            >
                Yes, show me
            </button>
            <button
                @click="dismiss()"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
                No thanks
            </button>
        </div>
    </div>

    {{-- ── Existing guidance notice (content unchanged) ───────── --}}
    <x-filament::section>
        <div class="space-y-4">
            <div class="flex items-start justify-between gap-4">
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
                <button
                    @click="openGuide()"
                    class="guide-pulse-btn shrink-0 flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-primary-700 focus:outline-none"
                >
                    <x-filament::icon icon="heroicon-o-play-circle" class="h-5 w-5 text-white" />
                    How does this work?
                </button>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <a href="{{ url('/resources/infant-child-mentorship-manual') }}" target="_blank" rel="noopener noreferrer"
                   class="rounded-lg border border-gray-200 bg-white p-4 text-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600 dark:hover:bg-gray-800">
                    <div class="font-semibold text-gray-950 dark:text-white">Infant and Child Mentorship Manual</div>
                    <div class="mt-1 text-gray-600 dark:text-gray-400">Use this to plan content and session flow.</div>
                </a>
                <a href="https://mnchkenyamentorship.org/resources/newborn-mentorship-mentors-manual" target="_blank" rel="noopener noreferrer"
                   class="rounded-lg border border-gray-200 bg-white p-4 text-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600 dark:hover:bg-gray-800">
                    <div class="font-semibold text-gray-950 dark:text-white">Newborn Mentorship Mentor's Manual</div>
                    <div class="mt-1 text-gray-600 dark:text-gray-400">Use this when the mentorship focus includes newborn care.</div>
                </a>
                <a href="{{ route('resources.search', ['q' => 'mentorship manual']) }}" target="_blank" rel="noopener noreferrer"
                   class="rounded-lg border border-gray-200 bg-white p-4 text-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600 dark:hover:bg-gray-800">
                    <div class="font-semibold text-gray-950 dark:text-white">Search Mentorship Manuals</div>
                    <div class="mt-1 text-gray-600 dark:text-gray-400">Find manuals, guides, and learning materials.</div>
                </a>
                <a href="{{ route('resources.search', ['q' => 'MNCH guidelines']) }}" target="_blank" rel="noopener noreferrer"
                   class="rounded-lg border border-gray-200 bg-white p-4 text-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600 dark:hover:bg-gray-800">
                    <div class="font-semibold text-gray-950 dark:text-white">MNCH Guidelines</div>
                    <div class="mt-1 text-gray-600 dark:text-gray-400">Review supporting protocols before mentoring.</div>
                </a>
            </div>
        </div>
    </x-filament::section>

</div>
