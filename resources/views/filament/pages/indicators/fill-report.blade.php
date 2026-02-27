<x-filament-panels::page>

    {{-- ── Overall progress bar ────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Overall Completion</span>
            <span class="text-xs font-bold text-gray-700 dark:text-gray-200">
                {{ $overallCompletion['filled'] }} / {{ $overallCompletion['total'] }} indicators
                ({{ $overallCompletion['percentage'] }}%)
            </span>
        </div>
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
            <div class="h-2 rounded-full transition-all duration-500
                @if($overallCompletion['percentage'] === 100) bg-success-500
                @elseif($overallCompletion['percentage'] >= 50) bg-primary-500
                @else bg-warning-400 @endif"
                 style="width: {{ $overallCompletion['percentage'] }}%">
            </div>
        </div>

        {{-- Rejection reason --}}
        @if($period->isRejected() && $period->rejection_reason)
            <div class="mt-3 rounded-lg border border-danger-200 bg-danger-50 dark:bg-danger-950/20 dark:border-danger-800 px-3 py-2">
                <p class="text-xs font-semibold text-danger-700 dark:text-danger-300">Report Rejected — Corrections Required</p>
                <p class="text-xs text-danger-600 dark:text-danger-400 mt-0.5">{{ $period->rejection_reason }}</p>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-4 gap-5">

        {{-- ── Left: Module Tab Navigation ────────────────────────────────── --}}
        <div class="xl:col-span-1">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden sticky top-4">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Modules</p>
                </div>
                <nav class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($groups as $i => $group)
                        @php $stats = $groupStats[$group['id']] ?? ['total' => 0, 'filled' => 0, 'complete' => false, 'percentage' => 0]; @endphp
                        <button
                        wire:click="setActiveGroup({{ $i }})"
                        class="w-full text-left px-4 py-3 flex items-center gap-3 transition-colors
                                @if($i === $activeGroupIndex)
                        bg-primary-50 dark:bg-primary-900/20 border-l-2 border-l-primary-500
                                @else
                        hover:bg-gray-50 dark:hover:bg-gray-800/50 border-l-2 border-l-transparent
                                @endif">

                            {{-- Completion dot --}}
                            <span class="shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-xs
                                @if($stats['complete'])
                              bg-success-100 text-success-600 dark:bg-success-900/30 dark:text-success-400
                                @elseif($stats['filled'] > 0)
                              bg-warning-100 text-warning-600 dark:bg-warning-900/30 dark:text-warning-400
                                @else
                              bg-gray-100 text-gray-400 dark:bg-gray-800
                                @endif">
                                @if($stats['complete'])
                                    <x-heroicon-m-check class="w-3 h-3" />
                                @elseif($stats['filled'] > 0)
                                    <x-heroicon-m-ellipsis-horizontal class="w-3 h-3" />
                                @else
                                    <span class="text-[10px] font-bold">{{ $i + 1 }}</span>
                                @endif
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-medium truncate
                                    @if($i === $activeGroupIndex) text-primary-700 dark:text-primary-300
                                    @else text-gray-700 dark:text-gray-300 @endif">
                                    {{ $group['name'] }}
                                </p>
                                @if($stats['total'] > 0)
                                    <p class="text-[10px] text-gray-400 mt-0.5">
                                        {{ $stats['filled'] }}/{{ $stats['total'] }}
                                    </p>
                                @endif
                            </div>
                        </button>
                    @endforeach
                </nav>
            </div>
        </div>

        {{-- ── Right: Active Module Indicators ────────────────────────────── --}}
        <div class="xl:col-span-3">
            @if($activeGroup)
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">

                    {{-- Module header --}}
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                        <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">{{ $activeGroup['name'] }}</h2>
                        @if($activeGroup['description'])
                            <p class="text-xs text-gray-500 mt-0.5">{{ $activeGroup['description'] }}</p>
                        @endif
                    </div>

                    {{-- Indicators --}}
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">

                        @forelse($activeGroup['indicators'] as $indicator)

                            @if(empty($indicator['children']))
                                {{-- ── Regular indicator ────────────────────── --}}
                                @include('filament.pages.indicators.partials.indicator-row', ['indicator' => $indicator])
                            @else
                                {{-- ── Banded indicator (parent + sub-bands) ── --}}
                                <div class="px-5 py-4">
                                    <div class="mb-3">
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-500 mr-2">{{ $indicator['code'] }}</span>
                                            {{ $indicator['name'] }}
                                        </p>
                                        @if($indicator['source_document'])
                                            <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                                                <x-heroicon-m-document-text class="w-3 h-3" />
                                                Source: {{ $indicator['source_document'] }}
                                            </p>
                                        @endif
                                        @if($indicator['display_hint'])
                                            <p class="text-xs text-primary-600 dark:text-primary-400 mt-1 italic">{{ $indicator['display_hint'] }}</p>
                                        @endif
                                    </div>

                                    {{-- Sub-band rows --}}
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="bg-gray-50 dark:bg-gray-800">
                                                    <th class="text-left px-3 py-2 text-gray-500 font-medium">Band</th>
                                                    <th class="text-center px-3 py-2 text-gray-500 font-medium w-32">Numerator</th>
                                                    <th class="text-center px-3 py-2 text-gray-500 font-medium w-32">Denominator</th>
                                                    <th class="text-center px-3 py-2 text-gray-500 font-medium w-24">%</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                @foreach($indicator['children'] as $child)
                                                    @php
                                                        $cId  = $child['id'];
                                                        $cVal = $values[$cId] ?? [];
                                                        $hasError = isset($valueErrors[$cId]);
                                                        $n = $cVal['numerator'] ?? null;
                                                        $d = $cVal['denominator'] ?? null;
                                                        $pct = (is_numeric($n) && is_numeric($d) && $d > 0) ? round(($n / $d) * 100, 1) : null;
                                                    @endphp
                                                    <tr class="{{ $hasError ? 'bg-danger-50 dark:bg-danger-950/20' : '' }}">
                                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $child['short_name'] ?? $child['name'] }}</td>
                                                        <td class="px-3 py-2">
                                                            @if($isEditable)
                                                                <input
                                                type="number"
                                                min="0"
                                                wire:model.lazy="values.{{ $cId }}.numerator"
                                                class="w-full text-center rounded border text-sm py-1 px-2
                                                                        {{ $hasError ? 'border-danger-400 bg-danger-50' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800' }}
                                                focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-gray-100"
                                                placeholder="0"
                                                />
                                                            @else
                                                                <span class="block text-center">{{ $n ?? '—' }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            @if($isEditable)
                                                                <input
                                                type="number"
                                                min="0"
                                                wire:model.lazy="values.{{ $cId }}.denominator"
                                                class="w-full text-center rounded border text-sm py-1 px-2
                                                                        {{ $hasError ? 'border-danger-400 bg-danger-50' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800' }}
                                                focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-gray-100"
                                                placeholder="0"
                                                />
                                                            @else
                                                                <span class="block text-center">{{ $d ?? '—' }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2 text-center font-medium
                                                            @if($pct !== null && $pct > 0) text-primary-600 dark:text-primary-400
                                                            @else text-gray-400 @endif">
                                                            {{ $pct !== null ? $pct . '%' : '—' }}
                                                        </td>
                                                    </tr>
                                                    @if($hasError)
                                                        <tr>
                                                            <td colspan="4" class="px-3 pb-2">
                                                                <p class="text-xs text-danger-600">⚠ {{ $valueErrors[$cId] }}</p>
                                                            </td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                        @empty
                            <div class="px-5 py-8 text-center">
                                <x-heroicon-o-clipboard-document-list class="w-10 h-10 text-gray-300 mx-auto mb-2" />
                                <p class="text-sm text-gray-400">No indicators configured for this module yet.</p>
                            </div>
                        @endforelse

                    </div>

                    {{-- ── Module navigation footer ─────────────────────────── --}}
                    <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            @if(! $isFirstGroup)
                                <x-filament::button
                            wire:click="goToPreviousGroup"
                            color="gray"
                            icon="heroicon-o-arrow-left"
                            size="sm"
                                >Previous</x-filament::button>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            @if($isEditable)
                                <x-filament::button
                            wire:click="saveDraft"
                            wire:loading.attr="disabled"
                            wire:target="saveDraft"
                            color="gray"
                            icon="heroicon-o-document-check"
                            size="sm"
                            >
                                    <span wire:loading.remove wire:target="saveDraft">Save Draft</span>
                                    <span wire:loading wire:target="saveDraft">Saving…</span>
                                </x-filament::button>
                            @endif

                            @if(! $isLastGroup)
                                <x-filament::button
                            wire:click="goToNextGroup"
                            color="primary"
                            icon-position="after"
                            icon="heroicon-o-arrow-right"
                            size="sm"
                                >Save & Next Module</x-filament::button>
                            @else
                                {{-- Last module - show review button --}}
                                @if($isEditable)
                                    <x-filament::button
                            wire:click="submitForValidation"
                            color="primary"
                            icon="heroicon-o-paper-airplane"
                            size="sm"
                                    >Review & Submit</x-filament::button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

</x-filament-panels::page>