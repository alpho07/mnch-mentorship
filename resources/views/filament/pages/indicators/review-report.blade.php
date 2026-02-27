<x-filament-panels::page>

    {{-- ── Period summary ──────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Facility</p>
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $period->facility->name }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Report Type</p>
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $period->reportType->name }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Period</p>
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $period->period_label }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Overall Completion</p>
            <p class="text-sm font-semibold {{ $overallCompletion['percentage'] === 100 ? 'text-success-600' : 'text-warning-600' }}">
                {{ $overallCompletion['percentage'] }}%
                <span class="text-xs font-normal text-gray-400">({{ $overallCompletion['filled'] }}/{{ $overallCompletion['total'] }})</span>
            </p>
        </div>
    </div>

    {{-- ── Overall progress bar ────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-medium text-gray-500">Completion</span>
            <span class="text-xs text-gray-500">{{ $overallCompletion['filled'] }} / {{ $overallCompletion['total'] }} indicators filled</span>
        </div>
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
            <div class="h-3 rounded-full transition-all duration-700
                {{ $overallCompletion['percentage'] === 100 ? 'bg-success-500' : 'bg-primary-500' }}"
                 style="width: {{ $overallCompletion['percentage'] }}%">
            </div>
        </div>

        @if($overallCompletion['percentage'] < 100)
            <p class="text-xs text-warning-600 dark:text-warning-400 mt-2 flex items-center gap-1">
                <x-heroicon-m-exclamation-triangle class="w-3 h-3 shrink-0" />
                {{ $overallCompletion['total'] - $overallCompletion['filled'] }} indicator(s) have not been filled.
        You may still submit, but incomplete data may affect reporting quality.
            </p>
        @endif
    </div>

    {{-- ── Module-by-module completion table ──────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Module Completion Summary</h3>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 uppercase tracking-wider">
                    <th class="text-left px-5 py-3">Module</th>
                    <th class="text-center px-4 py-3">Total</th>
                    <th class="text-center px-4 py-3">Filled</th>
                    <th class="text-center px-4 py-3">Completion</th>
                    <th class="text-center px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($groupStats as $stat)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30">
                        <td class="px-5 py-3 font-medium text-gray-700 dark:text-gray-300">
                            {{ $stat['group_name'] }}
                        </td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $stat['total'] }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $stat['filled'] }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                    <div class="h-1.5 rounded-full {{ $stat['complete'] ? 'bg-success-500' : 'bg-primary-400' }}"
                                    style="width: {{ $stat['percentage'] }}%"></div>
                                </div>
                                <span class="text-xs text-gray-500 w-8 text-right">{{ $stat['percentage'] }}%</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($stat['total'] === 0)
                                <span class="text-xs text-gray-400">N/A</span>
                            @elseif($stat['complete'])
                                <span class="inline-flex items-center gap-1 text-xs text-success-600 dark:text-success-400">
                                    <x-heroicon-m-check-circle class="w-4 h-4" /> Complete
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs text-warning-600 dark:text-warning-400">
                                    <x-heroicon-m-exclamation-circle class="w-4 h-4" /> Incomplete
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ── Notes / Submission notes ─────────────────────────────────────────── --}}
    @if($isSubmittable)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm p-5">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Submission Notes <span class="text-gray-400 font-normal text-xs">(optional)</span>
            </label>
            <textarea
            wire:model="notes"
            rows="3"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 text-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            placeholder="Add any notes, data quality observations, or explanations for the validator…"
            ></textarea>
        </div>

        {{-- ── Submit call-to-action ──────────────────────────────────────── --}}
        <div class="rounded-xl border border-primary-200 bg-primary-50 dark:bg-primary-950/20 dark:border-primary-800 p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-primary-700 dark:text-primary-300">Ready to Submit?</p>
                <p class="text-xs text-primary-600 dark:text-primary-400 mt-0.5">
                Once submitted, this report will be locked for editing and sent for validation.
                </p>
            </div>
            <x-filament::button
            wire:click="confirmSubmit"
            wire:loading.attr="disabled"
            color="primary"
            icon="heroicon-o-paper-airplane"
            >
                <span wire:loading.remove wire:target="confirmSubmit">Submit for Validation</span>
                <span wire:loading wire:target="confirmSubmit">Submitting…</span>
            </x-filament::button>
        </div>
    @else
        {{-- Already submitted / validated / rejected status --}}
        <div class="rounded-xl border p-5
            @if($period->isValidated()) border-success-200 bg-success-50 dark:bg-success-950/20
            @elseif($period->isRejected()) border-danger-200 bg-danger-50 dark:bg-danger-950/20
            @else border-gray-200 bg-gray-50 dark:bg-gray-800 @endif">
            <p class="text-sm font-semibold
                @if($period->isValidated()) text-success-700 dark:text-success-300
                @elseif($period->isRejected()) text-danger-700 dark:text-danger-300
                @else text-gray-700 dark:text-gray-300 @endif">
                Status: {{ ucfirst(str_replace('_', ' ', $period->status)) }}
            </p>
            @if($period->rejection_reason)
                <p class="text-xs mt-1 text-danger-600">Reason: {{ $period->rejection_reason }}</p>
            @endif
        </div>
    @endif

</x-filament-panels::page>