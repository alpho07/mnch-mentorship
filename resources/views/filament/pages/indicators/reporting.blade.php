<x-filament-panels::page>

    {{-- ── Facility header ──────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-3 mb-1">
        <x-heroicon-o-building-office-2 class="w-5 h-5 text-gray-400" />
        <span class="text-sm text-gray-500 dark:text-gray-400">Reporting for: <strong class="text-gray-700 dark:text-gray-200">{{ $facilityName }}</strong></span>
    </div>

    {{-- ── Period Selector Card ─────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <x-heroicon-o-calendar-days class="w-4 h-4 text-primary-500" />
                Select Reporting Period
            </h3>
            <p class="text-xs text-gray-500 mt-0.5">Choose the report type, frequency and period — similar to DHIS2 data entry.</p>
        </div>
        <div class="p-5">
            {{ $this->form }}

            {{-- ── Status indicator ─────────────────────────────────────────── --}}
            @if ($periodStatus)
                <div class="mt-4">
                    @php
                        $status = $periodStatus['status'];
                        $exists = $periodStatus['exists'];
                        $completion = $periodStatus['completion'];

                        $colors = [
                            'not_started'    => 'gray',
                            'draft'          => 'warning',
                            'submitted'      => 'info',
                            'validated'      => 'success',
                            'rejected'       => 'danger',
                            'pushed_to_dhis2'=> 'success',
                        ];
                        $labels = [
                            'not_started'    => 'Not yet started',
                            'draft'          => 'Draft saved',
                            'submitted'      => 'Submitted — awaiting validation',
                            'validated'      => 'Validated',
                            'rejected'       => 'Rejected — corrections needed',
                            'pushed_to_dhis2'=> 'Pushed to DHIS2',
                        ];
                        $color = $colors[$status] ?? 'gray';
                        $label = $labels[$status] ?? ucfirst($status);
                    @endphp

                    <div class="rounded-lg border p-4
                        @if($color === 'success') border-success-200 bg-success-50 dark:bg-success-950/20 dark:border-success-800
                        @elseif($color === 'warning') border-warning-200 bg-warning-50 dark:bg-warning-950/20 dark:border-warning-800
                        @elseif($color === 'danger') border-danger-200 bg-danger-50 dark:bg-danger-950/20 dark:border-danger-800
                        @elseif($color === 'info') border-info-200 bg-info-50 dark:bg-info-950/20 dark:border-info-800
                        @else border-gray-200 bg-gray-50 dark:bg-gray-800 dark:border-gray-700 @endif
                     flex items-center justify-between gap-4">

                        <div>
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
                                {{ $periodStatus['report_type'] }} — {{ $periodStatus['period_label'] }}
                            </p>
                            <p class="text-xs mt-0.5
                                @if($color === 'success') text-success-600 dark:text-success-400
                                @elseif($color === 'warning') text-warning-600 dark:text-warning-400
                                @elseif($color === 'danger') text-danger-600 dark:text-danger-400
                                @elseif($color === 'info') text-info-600 dark:text-info-400
                                @else text-gray-500 @endif">
                                {{ $label }}
                                @if($completion && $exists)
                                    &nbsp;·&nbsp; {{ $completion['filled'] }}/{{ $completion['total'] }} indicators filled ({{ $completion['percentage'] }}%)
                                @endif
                            </p>
                        </div>

                        <x-filament::button
                        wire:click="proceed"
                        wire:loading.attr="disabled"
                        color="{{ $status === 'not_started' ? 'primary' : ($status === 'draft' || $status === 'rejected' ? 'warning' : 'gray') }}"
                        icon="{{ $status === 'not_started' ? 'heroicon-o-play' : 'heroicon-o-pencil-square' }}"
                        size="sm"
                        >
                            @if($status === 'not_started') Start Data Entry
                            @elseif($status === 'draft') Continue Editing
                            @elseif($status === 'rejected') Review & Correct
                            @else View Report
                            @endif
                        </x-filament::button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ── Recent Submissions ───────────────────────────────────────────────── --}}
    @if($recentPeriods->isNotEmpty())
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                    <x-heroicon-o-clock class="w-4 h-4 text-gray-400" />
                Recent Reports
                </h3>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($recentPeriods as $rp)
                    <a href="{{ $rp['url'] }}" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <div>
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $rp['report_type'] }}</p>
                            <p class="text-xs text-gray-500">{{ $rp['period_label'] }} · {{ $rp['updated_at'] }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                            @if($rp['status_color'] === 'success') bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400
                            @elseif($rp['status_color'] === 'warning') bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-400
                            @elseif($rp['status_color'] === 'danger') bg-danger-100 text-danger-700 dark:bg-danger-900/30 dark:text-danger-400
                            @else bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 @endif">
                            {{ ucfirst(str_replace(['_', 'pushed to dhis2'], [' ', 'DHIS2'], $rp['status'])) }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

</x-filament-panels::page>