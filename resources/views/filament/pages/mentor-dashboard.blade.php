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
@php
$programFilter = request('program');
$programTitle = match ($programFilter) {
    'newborn' => 'Newborn Care',
    'infant' => 'Infant and Child Care',
    default => 'All Programs',
};
@endphp
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <p class="text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Mentor Dashboard · {{ $programTitle }}</p>
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
$colorMap = [
    'emerald' => 'from-emerald-50 to-teal-50 border-emerald-200 dark:from-emerald-950/30 dark:to-teal-950/20 dark:border-emerald-800',
    'blue'    => 'from-blue-50 to-indigo-50 border-blue-200 dark:from-blue-950/30 dark:to-indigo-950/20 dark:border-blue-800',
    'amber'   => 'from-amber-50 to-orange-50 border-amber-200 dark:from-amber-950/30 dark:to-orange-950/20 dark:border-amber-800',
    'red'     => 'from-red-50 to-rose-50 border-red-200 dark:from-red-950/30 dark:to-rose-950/20 dark:border-red-800',
    'violet'  => 'from-violet-50 to-purple-50 border-violet-200 dark:from-violet-950/30 dark:to-purple-950/20 dark:border-violet-800',
];
$valColorMap = ['emerald'=>'text-emerald-700 dark:text-emerald-400','blue'=>'text-blue-700 dark:text-blue-400','amber'=>'text-amber-700 dark:text-amber-400','red'=>'text-red-600 dark:text-red-400','violet'=>'text-violet-700 dark:text-violet-400'];
@endphp

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    {{-- Primary KPIs --}}
    <div class="kpi-card rounded-2xl border bg-gradient-to-br p-5 {{ $colorMap['emerald'] }}">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-3xl font-extrabold count-animate {{ $valColorMap['emerald'] }}">{{ $kpis['active_mentorships'] }}</p>
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mt-1">Active Mentorships</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $kpis['active_classes'] }} active classes</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center text-2xl">🏥</div>
        </div>
    </div>
    <div class="kpi-card rounded-2xl border bg-gradient-to-br p-5 {{ $colorMap['blue'] }}">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-3xl font-extrabold count-animate {{ $valColorMap['blue'] }}">{{ $kpis['total_mentees'] }}</p>
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mt-1">Total Mentees</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $kpis['total_enrollments'] }} enrollments</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center text-2xl">👥</div>
        </div>
    </div>
    <div class="kpi-card rounded-2xl border bg-gradient-to-br p-5 {{ $kpis['avg_completion']>=75?$colorMap['emerald']:($kpis['avg_completion']>=50?$colorMap['amber']:$colorMap['red']) }}">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-3xl font-extrabold count-animate {{ $kpis['avg_completion']>=75?$valColorMap['emerald']:($kpis['avg_completion']>=50?$valColorMap['amber']:$valColorMap['red']) }}">{{ $kpis['avg_completion'] }}%</p>
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mt-1">Avg Completion</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $kpis['module_completion_rate'] }}% modules done</p>
            </div>
            <div class="w-12 h-12 rounded-xl {{ $kpis['avg_completion']>=75?'bg-emerald-100 dark:bg-emerald-900/40':($kpis['avg_completion']>=50?'bg-amber-100 dark:bg-amber-900/40':'bg-red-100 dark:bg-red-900/40') }} flex items-center justify-center text-2xl">🎯</div>
        </div>
    </div>
</div>

{{-- ─── SECONDARY STATS & PENDING ACTIONS ───────────────────────────────── --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="kpi-card rounded-2xl border bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-lg">📊</div>
        <div>
            <p class="text-lg font-extrabold {{ $kpis['attendance_rate']>=75?$valColorMap['emerald']:($kpis['attendance_rate']>=50?$valColorMap['amber']:$valColorMap['red']) }}">{{ $kpis['attendance_rate'] }}%</p>
            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Attendance</p>
        </div>
    </div>
    <div class="kpi-card rounded-2xl border bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center text-lg">💬</div>
        <div>
            <p class="text-lg font-extrabold {{ $valColorMap['violet'] }}">{{ $kpis['recommendations'] }}</p>
            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Recommendations</p>
        </div>
    </div>
    <div class="kpi-card rounded-2xl border bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800 p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center text-lg">🎥</div>
        <div>
            <p class="text-lg font-extrabold {{ $valColorMap['amber'] }}">{{ $kpis['pending_video_reviews'] ?? 0 }}</p>
            <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Pending Video Reviews</p>
        </div>
    </div>
    <div class="kpi-card rounded-2xl border bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800 p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center text-lg">✅</div>
        <div>
            <p class="text-lg font-extrabold {{ $valColorMap['blue'] }}">{{ $kpis['pending_mentor_approvals'] ?? 0 }}</p>
            <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Pending Approvals</p>
        </div>
    </div>
</div>

{{-- ─── PENDING VIDEO REVIEWS ─────────────────────────────────────────────── --}}
@if(! empty($pendingVideoReviews))
    <div class="rounded-2xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/20 overflow-hidden">
        <div class="px-6 py-4 border-b border-amber-200 dark:border-amber-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-xl">🎥</span>
                <h3 class="text-base font-bold text-amber-900 dark:text-amber-100">Pending Video Reviews</h3>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-amber-200 text-amber-800 dark:bg-amber-800 dark:text-amber-100">{{ count($pendingVideoReviews) }}</span>
            </div>
        </div>
        <div class="divide-y divide-amber-200 dark:divide-amber-800/50">
            @foreach($pendingVideoReviews as $review)
                <div class="px-6 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-amber-900 dark:text-amber-100 truncate">
                            {{ $review['mentee']?->full_name ?? $review['mentee']?->name ?? 'Unknown Mentee' }}
                        </p>
                        <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5 truncate">
                            {{ $review['programModule']?->name ?? 'Module' }}
                            <span class="opacity-60">·</span>
                            {{ $review['class']?->name ?? 'Class' }}
                            <span class="opacity-60">·</span>
                            {{ $review['training']?->title ?? 'Mentorship' }}
                        </p>
                    </div>
                    <a href="{{ $review['url'] }}"
                       class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold transition-colors shrink-0">
                        Review Video
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if($mentorships->isEmpty())
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
    $insightCards[] = ['color'=>'#ef4444','bg'=>'bg-red-50 dark:bg-red-950/20','text'=>"<strong>{$insights['mentees_needing_attention']}</strong> mentee(s) have low module completion — consider scheduling a check-in.",'icon'=>'⚠️'];
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
        <span class="text-xs text-gray-400">{{ $mentorships->total() }} program(s) · latest first</span>
    </div>
    <div class="divide-y divide-gray-50 dark:divide-gray-800/50">
        @foreach($mentorshipItems as $m)
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
    <div class="px-6 py-3 border-t border-gray-100 dark:border-gray-800">
        {{ $mentorships->onEachSide(1)->links('pagination::tailwind') }}
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