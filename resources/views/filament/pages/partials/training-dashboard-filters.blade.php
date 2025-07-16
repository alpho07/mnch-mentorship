<div>
    {{-- FILTERS ROW --}}
    <div class="p-4 mb-6 rounded bg-white shadow flex flex-wrap gap-4 items-end">
        {{-- YEAR --}}
        <div>
            <label class="text-xs font-bold">Year</label>
            <select wire:model="filterYear" class="filament-input rounded">
                @foreach($this->years as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </div>
        {{-- QUARTER --}}
        <div>
            <label class="text-xs font-bold">Quarter</label>
            <select wire:model="filterQuarter" class="filament-input rounded">
                <option value="">All</option>
                @foreach($this->quarters as $quarter)
                    <option value="{{ $quarter }}">Q{{ $quarter }}</option>
                @endforeach
            </select>
        </div>
        {{-- MONTH --}}
        <div>
            <label class="text-xs font-bold">Month</label>
            <select wire:model="filterMonth" class="filament-input rounded">
                <option value="">All</option>
                @foreach($this->months as $month)
                    <option value="{{ $month }}">{{ \Carbon\Carbon::create()->month($month)->format('M') }}</option>
                @endforeach
            </select>
        </div>
        {{-- COUNTY --}}
        <div>
            <label class="text-xs font-bold">County</label>
            <select wire:model="filterCounty" class="filament-input rounded">
                <option value="">All</option>
                @foreach($this->counties as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        {{-- SUBCOUNTY --}}
        <div>
            <label class="text-xs font-bold">Subcounty</label>
            <select wire:model="filterSubcounty" class="filament-input rounded">
                <option value="">All</option>
                @foreach($this->subcounties as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        {{-- FACILITY --}}
        <div>
            <label class="text-xs font-bold">Facility</label>
            <select wire:model="filterFacility" class="filament-input rounded">
                <option value="">All</option>
                @foreach($this->facilities as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        {{-- PROGRAM --}}
        <div>
            <label class="text-xs font-bold">Program</label>
            <select wire:model="filterProgram" class="filament-input rounded">
                <option value="">All</option>
                @foreach($this->programs as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        {{-- MODULE --}}
        <div>
            <label class="text-xs font-bold">Module</label>
            <select wire:model="filterModule" class="filament-input rounded">
                <option value="">All</option>
                @foreach($this->modules as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        {{-- CADRE --}}
        <div>
            <label class="text-xs font-bold">Cadre</label>
            <select wire:model="filterCadre" class="filament-input rounded">
                <option value="">All</option>
                @foreach($this->cadres as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        {{-- DATE RANGE (readonly) --}}
        <div>
            <label class="text-xs font-bold">Start</label>
            <input type="date" wire:model="filterDateStart" class="filament-input rounded" readonly>
        </div>
        <div>
            <label class="text-xs font-bold">End</label>
            <input type="date" wire:model="filterDateEnd" class="filament-input rounded" readonly>
        </div>
        {{-- RESET --}}
        <div>
            <button wire:click="resetFilters" type="button" class="rounded bg-gray-200 px-3 py-1">Reset</button>
        </div>
    </div>

    {{-- KPI WIDGET --}}
    @livewire('dashboard.training-kpi-widget', [
        'filterYear' => $filterYear,
        'filterMonth' => $filterMonth,
        'filterQuarter' => $filterQuarter,
        'filterCounty' => $filterCounty,
        'filterSubcounty' => $filterSubcounty,
        'filterFacility' => $filterFacility,
        'filterProgram' => $filterProgram,
        'filterModule' => $filterModule,
        'filterCadre' => $filterCadre,
        'filterDateStart' => $filterDateStart,
        'filterDateEnd' => $filterDateEnd,
    ])

    {{-- You can add other chart/data table widgets the same way! --}}
</div>
