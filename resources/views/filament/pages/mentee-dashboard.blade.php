<x-filament-panels::page>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        .lms-root {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        @keyframes ring-fill {
            from {
                stroke-dasharray: 0 100;
            }
        }
        .ring-animate {
            animation: ring-fill 1s ease-out forwards;
        }
        .module-card {
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .module-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }
        .session-line {
            border-left: 2px solid #e5e7eb;
            padding-left: 20px;
            position: relative;
        }
        .dark .session-line {
            border-left-color: #374151;
        }
        .session-dot {
            position: relative;
        }
        .session-dot::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 14px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #d1d5db;
        }
        .session-dot.dot-done::before {
            background: #10b981;
        }
        .session-dot.dot-active::before {
            background: #14b8a6;
        }
        @keyframes soft-pulse {
            0%,100% {
                box-shadow: 0 0 0 0 rgba(16,185,129,0.3);
            }
            50% {
                box-shadow: 0 0 0 6px rgba(16,185,129,0);
            }
        }
        .cta-pulse {
            animation: soft-pulse 2s infinite;
        }
    </style>

    <div class="lms-root space-y-6">

        {{-- HERO --}}
        <div class="relative rounded-2xl overflow-hidden bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 p-6 md:p-8">
            <div class="absolute inset-0 opacity-10">
                <svg width="100%" height="100%"><defs><pattern id="g" width="32" height="32" patternUnits="userSpaceOnUse"><path d="M 32 0 L 0 0 0 32" fill="none" stroke="white" stroke-width="1"/></pattern></defs><rect width="100%" height="100%" fill="url(#g)"/></svg>
            </div>
            <div class="relative flex flex-col md:flex-row md:items-center gap-6">
                <div class="flex-1">
                    <p class="text-emerald-200 text-xs font-bold uppercase tracking-widest mb-1">MNCH Learning Portal</p>
                    <h1 class="text-2xl md:text-3xl font-extrabold text-white mb-2">Welcome back, {{ auth()->user()->first_name ?? auth()->user()->name }}</h1>
            @if ($summaryStats['pending_attendance'] > 0)
                        <p class="text-emerald-100 text-sm"><span class="font-bold text-white">{{ $summaryStats['pending_attendance'] }}</span> module(s) waiting for your attendance confirmation.</p>
                    @else
                        <p class="text-emerald-100 text-sm">You're all caught up — keep going!</p>
            @endif
                </div>
                <div class="flex items-center gap-5">
                    <div class="relative w-24 h-24">
                        <svg class="w-24 h-24 -rotate-90" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="3"/>
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="white" stroke-width="3"
                                stroke-dasharray="{{ $summaryStats['overall_pct'] }} {{ 100 - $summaryStats['overall_pct'] }}"
                                stroke-linecap="round" class="ring-animate"/>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-xl font-extrabold text-white">{{ $summaryStats['overall_pct'] }}%</span>
                            <span class="text-xs text-emerald-200">Overall</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                @foreach ([['Classes',$summaryStats['enrollments']],['Modules',$summaryStats['total_modules']],['Done',$summaryStats['completed_modules']],['Feedback',$summaryStats['recommendations']]] as $q)
                        <div class="bg-white/10 rounded-xl px-3 py-2 text-center">
                            <p class="text-lg font-bold text-white">{{ $q[1] }}</p>
                            <p class="text-xs text-emerald-200">{{ $q[0] }}</p>
                        </div>
                @endforeach
                    </div>
                </div>
            </div>
        </div>

{{-- ALERT --}}
@if ($summaryStats['pending_attendance'] > 0)
        <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-700/50 dark:bg-amber-900/20 px-4 py-3">
            <span class="text-xl flex-shrink-0">⚡</span>
            <div>
                <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Action Required</p>
                <p class="text-xs text-amber-700 dark:text-amber-400">{{ $summaryStats['pending_attendance'] }} module(s) in progress waiting for attendance confirmation.</p>
            </div>
        </div>
@endif

{{-- ENROLLMENT CARDS --}}
@forelse ($enrollments as $enrollment)
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">

    {{-- Class header --}}
            <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-800">
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-0.5">{{ $enrollment['training_name'] }}@if($enrollment['facility_name']) · {{ $enrollment['facility_name'] }}@endif</p>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $enrollment['class_name'] }}</h2>
                @if($enrollment['enrolled_at'])<p class="text-xs text-gray-400 mt-0.5">Enrolled {{ $enrollment['enrolled_at'] }}</p>@endif
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <div class="hidden sm:block w-36">
                            <div class="flex justify-between text-xs text-gray-400 mb-1">
                                <span>Progress</span><span class="font-semibold">{{ $enrollment['completed_modules'] }}/{{ $enrollment['total_modules'] }}</span>
                            </div>
                            <div class="h-2 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-700 {{ $enrollment['progress_pct'] >= 100 ? 'bg-emerald-500' : ($enrollment['progress_pct'] >= 50 ? 'bg-teal-500' : 'bg-blue-500') }}" style="width:{{ $enrollment['progress_pct'] }}%"></div>
                            </div>
                            <p class="text-xs text-right mt-0.5 font-bold {{ $enrollment['progress_pct'] >= 100 ? 'text-emerald-600' : 'text-gray-400' }}">{{ $enrollment['progress_pct'] }}%</p>
                        </div>
                @php $sc = ['active'=>'bg-emerald-100 text-emerald-700','completed'=>'bg-gray-100 text-gray-600','draft'=>'bg-blue-100 text-blue-700']; @endphp
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $sc[$enrollment['class_status']] ?? 'bg-gray-100 text-gray-600' }}">{{ ucfirst($enrollment['class_status']) }}</span>
                @if($enrollment['pending_attendance'] > 0)
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold bg-amber-500 text-white">{{ $enrollment['pending_attendance'] }} ⚡</span>
                @endif
                    </div>
                </div>
            </div>

    {{-- Modules --}}
            <div class="divide-y divide-gray-50 dark:divide-gray-800/50">
        @forelse ($enrollment['modules'] as $idx => $module)
                <div x-data="{open:{{ ($module['status']==='in_progress'||!empty($module['recommendation'])) ? 'true':'false' }}}" class="module-card">

            {{-- Module row --}}
                    <button type="button" @click="open=!open" class="w-full text-left px-6 py-4 flex items-center gap-4 hover:bg-gray-50/80 dark:hover:bg-gray-800/30 transition-colors">
                        <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold
                    {{ $module['status']==='completed' ? 'bg-emerald-500 text-white' : ($module['status']==='in_progress' ? 'bg-teal-500 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-400') }}">
                    @if($module['status']==='completed')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    @elseif($module['status']==='in_progress')
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    @else
                        {{ $idx + 1 }}
                    @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $module['name'] }}</p>
                            <div class="flex flex-wrap gap-x-3 mt-0.5">
                                <span class="text-xs text-gray-400">{{ count($module['sessions']) }} session(s)</span>
                        @if($module['status']==='in_progress'&&$module['started_at'])<span class="text-xs text-teal-500">Started {{ $module['started_at'] }}</span>@endif
                        @if($module['status']==='completed'&&$module['completed_at'])<span class="text-xs text-emerald-500">Completed {{ $module['completed_at'] }}</span>@endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                    @if(!empty($module['recommendation']))<span class="hidden sm:inline text-xs rounded-full px-2 py-0.5 bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400 font-medium">💬 Feedback</span>@endif
                    @if($module['attendance_open']&&!$module['already_confirmed'])<span class="text-xs rounded-full px-2.5 py-0.5 font-bold bg-amber-500 text-white">Action ⚡</span>@endif
                    @if($module['attendance_open']&&$module['already_confirmed'])<span class="text-xs rounded-full px-2 py-0.5 bg-emerald-100 text-emerald-700 font-medium">✓ Confirmed</span>@endif
                    @php $pc=['not_started'=>'bg-gray-100 text-gray-500','in_progress'=>'bg-teal-100 text-teal-700','completed'=>'bg-emerald-100 text-emerald-700','exempted'=>'bg-blue-100 text-blue-700']; @endphp
                            <span class="text-xs rounded-full px-2 py-0.5 font-medium {{ $pc[$module['progress_status']] ?? 'bg-gray-100 text-gray-500' }}">{{ match($module['progress_status']){'not_started'=>'Not Started','in_progress'=>'In Progress','completed'=>'Completed','exempted'=>'Exempted',default=>ucfirst($module['progress_status'])} }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" ::class="{'rotate-180':open}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </button>

            {{-- Expanded --}}
                    <div x-show="open" x-collapse class="bg-gray-50/40 dark:bg-gray-800/10">
                        <div class="px-6 pb-6 pt-2 space-y-4">

                    @if(!empty($module['description']))
                            <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ $module['description'] }}</p>
                    @endif

                    {{-- Attendance CTA --}}
                    @if($module['attendance_open']&&!$module['already_confirmed'])
                            <div class="rounded-xl border-2 border-amber-300 dark:border-amber-700 bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-950/30 dark:to-orange-950/20 p-4 flex flex-col sm:flex-row sm:items-center gap-4">
                                <div class="flex-1">
                                    <p class="font-semibold text-amber-900 dark:text-amber-200 text-sm">📣 This module is currently in session</p>
                                    <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">Tap the button to confirm your presence.</p>
                                </div>
                                <button wire:click="confirmAttendance({{ $module['id'] }})" wire:loading.attr="disabled" wire:target="confirmAttendance({{ $module['id'] }})"
                                        class="cta-pulse flex-shrink-0 inline-flex items-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 px-5 py-2.5 text-sm font-bold text-white shadow-md transition-all disabled:opacity-60 whitespace-nowrap">
                                    <span wire:loading.remove wire:target="confirmAttendance({{ $module['id'] }})">✓ Confirm Attendance</span>
                                    <span wire:loading wire:target="confirmAttendance({{ $module['id'] }})">
                                        <svg class="animate-spin h-4 w-4 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                                        Confirming...
                                    </span>
                                </button>
                            </div>
                    @elseif($module['attendance_open']&&$module['already_confirmed'])
                            <div class="rounded-xl border border-emerald-200 dark:border-emerald-700/50 bg-emerald-50 dark:bg-emerald-950/20 px-4 py-3 flex items-center gap-2.5">
                                <span class="text-emerald-500 text-lg">✓</span>
                        <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Attendance confirmed — you're marked as present.</p>
                    </div>
                @endif

                {{-- Mentor feedback --}}
                    @if(!empty($module['recommendation']))
                    <div class="rounded-xl border border-violet-200 dark:border-violet-700/50 bg-gradient-to-br from-violet-50 to-purple-50 dark:from-violet-950/20 dark:to-purple-950/10 p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <p class="text-xs font-bold uppercase tracking-widest text-violet-600 dark:text-violet-400">💬 Mentor Feedback</p>
                            @if($module['rec_written_at'])<span class="ml-auto text-xs text-gray-400">{{ $module['rec_written_at'] }}</span>@endif
                        </div>
                        <p class="text-sm text-violet-900 dark:text-violet-200 leading-relaxed whitespace-pre-line">{{ $module['recommendation'] }}</p>
                    </div>
                @endif

                {{-- Sessions --}}
                    @if(count($module['sessions'])>0)
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3">Sessions ({{ count($module['sessions']) }})</p>
                        <div class="session-line space-y-2 ml-2">
                            @foreach($module['sessions'] as $s)
                                @php
                                $dc = $s['status']==='completed' ? 'dot-done' : ($s['status']==='in_progress' ? 'dot-active' : '');
                                $dur = $s['duration_minutes'];
                                $dt = $dur ? ($dur>=60 ? intdiv($dur,60).'h '.($dur%60).'m' : $dur.' min') : null;
                            @endphp
                                <div class="session-dot {{ $dc }} rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 px-4 py-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                                <span class="text-xs text-gray-400 font-normal">{{ $s['session_number'] }}. </span>{{ $s['title'] }}
                                            </p>
                                        @if($s['description'])<p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ $s['description'] }}</p>@endif
                                            <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1.5">
                                            @if($dt)<span class="text-xs text-gray-400">⏱ {{ $dt }}</span>@endif
                                            @if($s['scheduled_date'])<span class="text-xs text-gray-400">📅 {{ $s['scheduled_date'] }}@if($s['scheduled_time']) at {{ $s['scheduled_time'] }}@endif</span>@endif
                                            @if($s['location'])<span class="text-xs text-gray-400">📍 {{ $s['location'] }}</span>@endif
                                </div>
                            </div>
                                    @if(($s['status']??'scheduled')!=='scheduled')
                                <span @class(['flex-shrink-0 text-xs rounded-full px-2 py-0.5 font-medium','bg-emerald-100 text-emerald-700'=>$s['status']==='completed','bg-teal-100 text-teal-700'=>$s['status']==='in_progress','bg-red-100 text-red-500'=>$s['status']==='cancelled'])>{{ ucfirst(str_replace('_',' ',$s['status'])) }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <p class="text-xs text-gray-400 italic">No sessions scheduled yet.</p>
    @endif

</div>
</div>
</div>
        @empty
<div class="px-6 py-10 text-center">
    <p class="text-3xl mb-2">📋</p>
    <p class="text-sm text-gray-400">No modules assigned yet.</p>
</div>
@endforelse
</div>
</div>
@empty
<div class="text-center py-20 rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700">
    <p class="text-5xl mb-4">🎓</p>
    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">No Active Mentorships</h3>
    <p class="text-sm text-gray-500 max-w-sm mx-auto">You are not currently enrolled in any mentorship class.</p>
</div>
@endforelse

    </div>
</x-filament-panels::page>