<x-filament-panels::page>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap');
.ceo-root { font-family: 'DM Sans', sans-serif; }
.kpi-card { transition: all 0.2s; }
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,0.1); }
.progress-bar-fill { transition: width 1s ease-out; }
@keyframes count-up { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
.count-animate { animation: count-up 0.5s ease-out forwards; }
.insight-card { border-left: 4px solid; }
.table-row-hover:hover { background: rgba(16,185,129,0.04); }
</style>

<div class="ceo-root space-y-6">

{{-- ─── HEADER ──────────────────────────────────────────────────────────── --}}
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <p class="text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Mentor Dashboard</p>
        <h1 class="text-2xl md:text-3xl font-extrabold text-gray-900 dark:text-white mt-0.5">
            {{ auth()->user()->first_name ?? auth()->user()->name }}
            <span class="text-gray-400 font-normal text-xl">— Overview</span>
        </h1>
    </div>
    <div class="flex items-center gap-2 text-xs text-gray-400">
        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
        Live data · {{ now()->format('d M Y, H:i') }}
    </div>
</div>

{{-- ─── KPI ROW ─────────────────────────────────────────────────────────── --}}
@php
$kpis = [
    ['label'=>'Active Mentorships', 'value'=>$kpis['active_mentorships'], 'sub'=>$kpis['active_classes'].' active classes', 'color'=>'emerald', 'icon'=>'🏥'],
    ['label'=>'Total Mentees',       'value'=>$kpis['total_mentees'],       'sub'=>$kpis['total_enrollments'].' enrollments',  'color'=>'blue',    'icon'=>'👥'],
    ['label'=>'Attendance Rate',     'value'=>$kpis['attendance_rate'].'%', 'sub'=>'module-level',                             'color'=>$kpis['attendance_rate']>=75?'emerald':($kpis['attendance_rate']>=50?'amber':'red'), 'icon'=>'📊'],
    ['label'=>'Avg Completion',      'value'=>$kpis['avg_completion'].'%',  'sub'=>$kpis['module_completion_rate'].'% modules done', 'color'=>$kpis['avg_completion']>=75?'emerald':($kpis['avg_completion']>=50?'amber':'red'), 'icon'=>'🎯'],
    ['label'=>'Recommendations',     'value'=>$kpis['recommendations'],     'sub'=>'written to mentees',                       'color'=>'violet',  'icon'=>'💬'],
];
$colorMap = [
    'emerald' => 'from-emerald-50 to-teal-50 border-emerald-200 dark:from-emerald-950/30 dark:to-teal-950/20 dark:border-emerald-800',
    'blue'    => 'from-blue-50 to-indigo-50 border-blue-200 dark:from-blue-950/30 dark:to-indigo-950/20 dark:border-blue-800',
    'amber'   => 'from-amber-50 to-orange-50 border-amber-200 dark:from-amber-950/30 dark:to-orange-950/20 dark:border-amber-800',
    'red'     => 'from-red-50 to-rose-50 border-red-200 dark:from-red-950/30 dark:to-rose-950/20 dark:border-red-800',
    'violet'  => 'from-violet-50 to-purple-50 border-violet-200 dark:from-violet-950/30 dark:to-purple-950/20 dark:border-violet-800',
];
$valColorMap = ['emerald'=>'text-emerald-700 dark:text-emerald-400','blue'=>'text-blue-700 dark:text-blue-400','amber'=>'text-amber-700 dark:text-amber-400','red'=>'text-red-600 dark:text-red-400','violet'=>'text-violet-700 dark:text-violet-400'];
@endphp

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
    @foreach($kpis as $kpi)
    <div class="kpi-card rounded-2xl border bg-gradient-to-br p-4 {{ $colorMap[$kpi['color']] ?? $colorMap['blue'] }}">
        <p class="text-2xl mb-2">{{ $kpi['icon'] }}</p>
        <p class="text-2xl font-extrabold count-animate {{ $valColorMap[$kpi['color']] ?? '' }}">{{ $kpi['value'] }}</p>
        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200 mt-0.5">{{ $kpi['label'] }}</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $kpi['sub'] }}</p>
    </div>
    @endforeach
</div>

@if(empty($mentorships))
<div class="text-center py-16 rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700">
    <p class="text-5xl mb-4">🏥</p>
    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">No Active Mentorships</h3>
    <p class="text-sm text-gray-500">You are not currently leading or co-mentoring any mentorships.</p>
</div>
@else

{{-- ─── INSIGHTS ROW ────────────────────────────────────────────────────── --}}
@php
$insightCards = [];
if($insights['mentees_needing_attention']>0)
    $insightCards[] = ['color'=>'#ef4444','bg'=>'bg-red-50 dark:bg-red-950/20','text'=>"<strong>{$insights['mentees_needing_attention']}</strong> mentee(s) have low module completion — consider scheduling a check-in.","icon'=>'⚠️'];
if($insights['low_attendance_classes']>0)
    $insightCards[] = ['color'=>'#f59e0b','bg'=>'bg-amber-50 dark:bg-amber-950/20','text'=>"<strong>{$insights['low_attendance_classes']}</strong> class(es) have attendance below 60% — review and follow up.",'icon'=>'📉'];
if($insights['stalled_modules']>0)
    $insightCards[] = ['color'=>'#6366f1','bg'=>'bg-indigo-50 dark:bg-indigo-950/20','text'=>"<strong>{$insights['stalled_modules']}</strong> module(s) haven't been started yet — consider activating them.",'icon'=>'⏸'];
if($insights['recs_coverage']<50)
    $insightCards[] = ['color'=>'#8b5cf6','bg'=>'bg-violet-50 dark:bg-violet-950/20','text'=>"Only <strong>{$insights['recs_coverage']}%</strong> of mentees have received written recommendations — boost this for better learning outcomes.",'icon'=>'💬'];
if(empty($insightCards))
    $insightCards[] = ['color'=>'#10b981','bg'=>'bg-emerald-50 dark:bg-emerald-950/20','text'=>'Excellent! All key indicators are healthy. Keep up the great mentorship work.','icon'=>'✅'];
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    @foreach($insightCards as $ins)
    <div class="insight-card rounded-xl {{ $ins['bg'] }} px-4 py-3" style="border-left-color:{{ $ins['color'] }}">
        <div class="flex items-start gap-3">
            <span class="text-xl flex-shrink-0">{{ $ins['icon'] }}</span>
            <p class="text-sm text-gray-700 dark:text-gray-200 leading-relaxed">{!! $ins['text'] !!}</p>
        </div>
    </div>
    @endforeach
</div>

{{-- ─── MENTORSHIP BREAKDOWN ────────────────────────────────────────────── --}}
<div class="rounded-2xl border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">
    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
        <h3 class="text-base font-bold text-gray-900 dark:text-white">Mentorship Programs</h3>
        <span class="text-xs text-gray-400">{{ count($mentorships) }} program(s)</span>
    </div>
    <div class="divide-y divide-gray-50 dark:divide-gray-800/50">
        @foreach($mentorships as $m)
        <div class="px-6 py-4">
            <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $m['title'] }}</p>
                        <span @class(['text-xs rounded-full px-2 py-0.5 font-medium',
                            'bg-emerald-100 text-emerald-700'=>$m['active_classes']>0,
                            'bg-gray-100 text-gray-500'=>$m['active_classes']===0])>
                            {{ $m['active_classes'] }} active
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $m['facility'] }} · {{ $m['classes_count'] }} class(es) · {{ $m['mentees'] }} mentee(s)</p>

                    {{-- Module completion bar --}}
                    <div class="mt-3 flex items-center gap-3">
                        <div class="flex-1 h-2 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                            <div class="h-full rounded-full progress-bar-fill {{ $m['module_pct']>=100?'bg-emerald-500':($m['module_pct']>=60?'bg-teal-500':'bg-blue-500') }}"
                                 style="width:{{ $m['module_pct'] }}%"></div>
                        </div>
                        <span class="text-xs font-bold text-gray-600 dark:text-gray-300 w-12 text-right">{{ $m['module_pct'] }}%</span>
                        <span class="text-xs text-gray-400">{{ $m['modules_done'] }}/{{ $m['modules_total'] }} modules</span>
                    </div>

                    {{-- Progress distribution --}}
                    <div class="mt-2 flex items-center gap-3 text-xs text-gray-400">
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-gray-300 inline-block"></span>{{ $m['dist_not_started'] }}% not started</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-teal-400 inline-block"></span>{{ $m['dist_in_progress'] }}% in progress</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>{{ $m['dist_completed'] }}% done</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <div class="text-center">
                        <p class="text-lg font-extrabold text-gray-900 dark:text-white">{{ $m['recommendations'] }}</p>
                        <p class="text-xs text-gray-400">recs</p>
                    </div>
                    <a href="{{ $m['url'] }}"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 px-3 py-2 text-xs font-semibold text-gray-700 dark:text-gray-200 transition-colors">
                        View →
                    </a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- ─── MENTEE ROSTER ───────────────────────────────────────────────────── --}}
<div class="rounded-2xl border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">
    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
        <div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white">Mentee Performance</h3>
            <p class="text-xs text-gray-400 mt-0.5">Sorted by attention priority · {{ count($menteeRoster) }} mentees</p>
        </div>
        <div class="flex items-center gap-3 text-xs">
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-red-400 inline-block"></span>Needs attention</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-400 inline-block"></span>In progress</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400 inline-block"></span>On track</span>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-gray-800">
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Mentee</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400 hidden md:table-cell">Cadre</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400 hidden lg:table-cell">Facility</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Classes</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Completion</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400 hidden sm:table-cell">Recs</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
                @forelse($menteeRoster as $mentee)
                <tr class="table-row-hover transition-colors">
                    <td class="px-6 py-3">
                        <p class="font-semibold text-gray-900 dark:text-white text-sm">{{ $mentee['name'] }}</p>
                        <p class="text-xs text-gray-400 truncate max-w-[180px]">{{ $mentee['email'] }}</p>
                    </td>
                    <td class="px-4 py-3 hidden md:table-cell">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $mentee['cadre'] }}</span>
                    </td>
                    <td class="px-4 py-3 hidden lg:table-cell">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ Str::limit($mentee['facility'],24) }}</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $mentee['enrollments'] }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2 min-w-[100px]">
                            <div class="flex-1 h-2 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                <div class="h-full rounded-full progress-bar-fill
                                    {{ $mentee['completion_pct']>=80?'bg-emerald-500':($mentee['completion_pct']>=40?'bg-amber-400':'bg-red-400') }}"
                                    style="width:{{ $mentee['completion_pct'] }}%"></div>
                            </div>
                            <span class="text-xs font-bold w-8 text-right {{ $mentee['completion_pct']>=80?'text-emerald-600':($mentee['completion_pct']>=40?'text-amber-600':'text-red-500') }}">
                                {{ $mentee['completion_pct'] }}%
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $mentee['modules_done'] }}/{{ $mentee['modules_total'] }} modules</p>
                    </td>
                    <td class="px-4 py-3 text-center hidden sm:table-cell">
                        @if($mentee['recommendations']>0)
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400">
                                💬 {{ $mentee['recommendations'] }}
                            </span>
                        @else
                            <span class="text-xs text-gray-300 dark:text-gray-600">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        @php $sf = $mentee['status_flag']; @endphp
                        <span @class(['inline-flex rounded-full w-2.5 h-2.5',
                            'bg-red-400'    => $sf==='needs_attention',
                            'bg-amber-400'  => $sf==='in_progress',
                            'bg-emerald-400'=> $sf==='on_track'])></span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-400">No mentees enrolled yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ─── ACTIVITY FEED ───────────────────────────────────────────────────── --}}
@if(count($activityFeed)>0)
<div class="rounded-2xl border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">
    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
        <h3 class="text-base font-bold text-gray-900 dark:text-white">Recent Recommendations</h3>
        <p class="text-xs text-gray-400 mt-0.5">Latest feedback written to mentees</p>
    </div>
    <div class="divide-y divide-gray-50 dark:divide-gray-800/50">
        @foreach($activityFeed as $activity)
        <div class="px-6 py-3 flex items-start gap-3">
            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center text-sm">💬</div>
            <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-800 dark:text-gray-200">
                    <span class="font-semibold">{{ $activity['mentee'] }}</span>
                    <span class="text-gray-400"> · </span>
                    <span class="text-gray-500">{{ $activity['module'] }}</span>
                </p>
                <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $activity['excerpt'] }}</p>
            </div>
            @if($activity['at'])
            <p class="flex-shrink-0 text-xs text-gray-400">{{ \Carbon\Carbon::parse($activity['at'])->diffForHumans() }}</p>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif

@endif {{-- end empty check --}}
</div>
</x-filament-panels::page>