<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters Section -->
        <x-filament::card>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Filters</h3>
                <x-filament::button wire:click="clearFilters" color="gray" size="sm" icon="heroicon-o-x-mark">
                    Clear All
                </x-filament::button>
            </div>

            {{$this->form }}
        </x-filament::card>

         <div class="grid grid-cols-1 gap-6">
            <livewire:training-coverage-stats-widget :program_id="$program_id" :period="$period" :county_id="$county_id"
                :subcounty_id="$subcounty_id" :facility_id="$facility_id" :department_id="$department_id" :cadre_id="$cadre_id" />
        </div>

        {{--<div class="grid grid-cols-1 gap-6">
            <livewire:training-charts-widget :program_id="$program_id" :period="$period" :county_id="$county_id" :subcounty_id="$subcounty_id"
                :facility_id="$facility_id" :department_id="$department_id" :cadre_id="$cadre_id" />
        </div>--}}

    </div>

</x-filament-panels::page>

