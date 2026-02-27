<x-filament-panels::page>

    {{-- ── Status banner ───────────────────────────────────────────────────── --}}
    <div class="rounded-xl border p-4 flex items-start gap-3
        @if($period->isValidated() || $period->isPushed()) border-success-200 bg-success-50 dark:bg-success-950/20 dark:border-success-800
        @elseif($period->isRejected()) border-danger-200 bg-danger-50 dark:bg-danger-950/20 dark:border-danger-800
        @elseif($period->isSubmitted()) border-info-200 bg-info-50 dark:bg-info-950/20 dark:border-info-800
        @else border-gray-200 bg-gray-50 dark:bg-gray-800 @endif">

        @if($period->isValidated() || $period->isPushed())
            <x-heroicon-o-check-circle class="w-5 h-5 text-success-600 shrink-0 mt-0.5" />
        @elseif($period->isRejected())
            <x-heroicon-o-x-circle class="w-5 h-5 text-danger-600 shrink-0 mt-0.5" />
        @elseif($period->isSubmitted())
            <x-heroicon-o-clock class="w-5 h-5 text-info-600 shrink-0 mt-0.5" />
        @else
            <x-heroicon-o-pencil class="w-5 h-5 text-gray-500 shrink-0 mt-0.5" />
        @endif

        <div class="flex-1">
            <p class="text-sm font-semibold
                @if($period->isValidated() || $period->isPushed()) text-success-700 dark:text-success-300
                @elseif($period->isRejected()) text-danger-700 dark:text-danger-300
                @elseif($period->isSubmitted()) text-info-700 dark:text-info-300
                @else text-gray-700 dark:text-gray-300 @endif">
                Status: {{ ucfirst(str_replace('_', ' ', $period->status)) }}
            </p>
            <div class="mt-1 text-xs text-gray-500 flex flex-wrap gap-x-4 gap-y-1">
                @if($period->submitted_at)
                    <span>Submitted: {{ $period->submitted_at->format('d M Y, H:i') }} by {{ $period->submittedByUser?->name ?? '—' }}</span>
                @endif
                @if($period->validated_at)
                    <span>Validated: {{ $period->validated_at->format('d M Y, H:i') }} by {{ $period->validatedByUser?->name ?? '—' }}</span>
                @endif
                @if($period->dhis2_push_at)
                    <span>DHIS2 Push: {{ $period->dhis2_push_at->format('d M Y, H:i') }}
                        ({{ $period->dhis2_push_status }})</span>
                @endif
            </div>
            @if($period->rejection_reason)
                <p class="mt-2 text-xs text-danger-600 dark:text-danger-400">
                    <strong>Rejection reason:</strong> {{ $period->rejection_reason }}
                </p>
            @endif
            @if($period->notes)
                <p class="mt-1 text-xs text-gray-500 italic">Notes: {{ $period->notes }}</p>
            @endif
        </div>

        {{-- Completion pill --}}
        <div class="shrink-0 text-center">
            <p class="text-2xl font-bold {{ $overallCompletion['percentage'] === 100 ? 'text-success-600' : 'text-warning-500' }}">
                {{ $overallCompletion['percentage'] }}%
            </p>
            <p class="text-xs text-gray-400">{{ $overallCompletion['filled'] }}/{{ $overallCompletion['total'] }}</p>
        </div>
    </div>

    {{-- ── Module-by-module indicator values ──────────────────────────────── --}}
    @foreach($groups as $gi => $group)
        @php $gStat = $groupStats[$gi] ?? null; @endphp
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">

            {{-- Group header --}}
            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $group['name'] }}</h3>
                @if($gStat)
                    <span class="text-xs {{ $gStat['complete'] ? 'text-success-600' : 'text-warning-500' }}">
                        {{ $gStat['filled'] }}/{{ $gStat['total'] }} filled
                    </span>
                @endif
            </div>

            @if(empty($group['indicators']))
                <p class="px-5 py-4 text-xs text-gray-400 italic">No indicators configured for this module.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 dark:border-gray-800">
                            <th class="text-left px-5 py-2">Indicator</th>
                            <th class="text-center px-3 py-2 w-28">Numerator</th>
                            <th class="text-center px-3 py-2 w-28">Denominator</th>
                            <th class="text-center px-3 py-2 w-20">%</th>
                            <th class="text-left px-3 py-2">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($group['indicators'] as $ind)
                            @if(empty($ind['children']))
                                @php
                                    $v   = $existingValues[$ind['id']] ?? null;
                                    $pct = $v['computed_percentage'] ?? null;
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30">
                                    <td class="px-5 py-3">
                                        <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 rounded px-1.5 py-0.5 mr-1 font-mono">{{ $ind['code'] }}</span>
                                        {{ $ind['name'] }}
                                        @if($v['comment'] ?? null)
                                            <p class="text-xs text-gray-400 italic mt-0.5">Note: {{ $v['comment'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-center {{ $v ? 'font-medium text-gray-800 dark:text-gray-100' : 'text-gray-300' }}">
                                        {{ $v ? ($v['numerator'] ?? ($v['count'] ?? ($v['yes_no'] !== null ? ($v['yes_no'] ? 'Yes' : 'No') : '—'))) : '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-center {{ $v ? 'font-medium text-gray-800 dark:text-gray-100' : 'text-gray-300' }}">
                                        {{ $v ? ($v['denominator'] ?? '—') : '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        @if($pct !== null)
                                            <span class="font-bold {{ $pct >= 80 ? 'text-success-600' : ($pct >= 50 ? 'text-warning-500' : 'text-danger-500') }}">
                                                {{ round($pct, 1) }}%
                                            </span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-xs text-gray-400">{{ $ind['source_document'] ?? '—' }}</td>
                                </tr>
                            @else
                                {{-- Parent row --}}
                                <tr class="bg-gray-50 dark:bg-gray-800/30">
                                    <td colspan="5" class="px-5 py-2 text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                                        {{ $ind['code'] }}: {{ $ind['name'] }}
                                    </td>
                                </tr>
                                {{-- Child/band rows --}}
                                @foreach($ind['children'] as $child)
                                    @php
                                        $cv   = $existingValues[$child['id']] ?? null;
                                        $cpct = $cv['computed_percentage'] ?? null;
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30">
                                        <td class="px-5 py-2 pl-10 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $child['short_name'] ?? $child['name'] }}
                                        </td>
                                        <td class="px-3 py-2 text-center {{ $cv ? 'font-medium text-gray-800 dark:text-gray-100' : 'text-gray-300' }}">
                                            {{ $cv['numerator'] ?? '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-center {{ $cv ? 'font-medium text-gray-800 dark:text-gray-100' : 'text-gray-300' }}">
                                            {{ $cv['denominator'] ?? '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            @if($cpct !== null)
                                                <span class="font-bold text-sm {{ $cpct >= 80 ? 'text-success-600' : ($cpct >= 50 ? 'text-warning-500' : 'text-danger-500') }}">
                                                    {{ round($cpct, 1) }}%
                                                </span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-xs text-gray-400">{{ $ind['source_document'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    {{-- DHIS2 Import Summary (if pushed) --}}
    @if($period->dhis2_import_summary)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                    <x-heroicon-o-arrow-up-circle class="w-4 h-4 text-info-500" />
                DHIS2 Import Summary
                </h3>
            </div>
            <div class="p-5">
                <pre class="text-xs text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-lg p-3 overflow-auto">{{ json_encode($period->dhis2_import_summary, JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    @endif

</x-filament-panels::page>