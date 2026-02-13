<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header Widgets --}}
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
            :columns="$this->getHeaderWidgetsColumns()"
            />

        {{-- My Progress Table --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-x-3">
                    <x-heroicon-o-book-open class="h-6 w-6 text-primary-500" />
                    <span>My Module Progress</span>
                </div>
            </x-slot>

            <x-slot name="description">
                Track your progress across all modules and cohorts
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>