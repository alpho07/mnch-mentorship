<x-filament::page>
    {{-- Global Filters --}}
    @include('filament.pages.partials.training-dashboard-filters', [
        'years' => $this->years,
        'monthYears' => $this->monthYears,
        'quarters' => $this->quarters,
        'counties' => $this->counties,
        'cadres' => $this->cadres,
        'facilities' => $this->facilities,
        'departments' => $this->departments,
    ])

</x-filament::page>
