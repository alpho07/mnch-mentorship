<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header Widgets --}}
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
            :columns="$this->getHeaderWidgetsColumns()"
            />

        {{-- My Mentorships Table --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-x-3">
                    <x-heroicon-o-academic-cap class="h-6 w-6 text-primary-500" />
                    <span>My Mentorship Programs</span>
                </div>
            </x-slot>

            <x-slot name="description">
                Manage your active mentorship programs, classes, and mentees
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>