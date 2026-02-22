{{-- resources/views/mentee/class-progress.blade.php --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Class Progress – {{ $class->name }}</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                        colors: {
                            brand: { 50:'#ecfdf5',100:'#d1fae5',500:'#10b981',600:'#059669',700:'#047857' },
                        },
                    },
                },
            };
        </script>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
    </head>
    <body class="bg-gray-50 dark:bg-gray-900 min-h-screen font-sans">

    {{-- HEADER --}}
    <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-10">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-brand-600 flex items-center justify-center text-white">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $training->title }}</p>
                    <h1 class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $class->name }}</h1>
                </div>
            </div>
            <span class="flex-shrink-0 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
            @if($class->status === 'active') bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300
            @elseif($class->status === 'completed') bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300
            @elseif($class->status === 'draft') bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300
            @else bg-yellow-100 text-yellow-700 @endif">
                {{ ucfirst($class->status) }}
            </span>
        </div>
    </header>

    {{-- FLASH MESSAGES --}}
@if(session('success') || session('error') || session('info'))
        <div class="max-w-5xl mx-auto px-4 sm:px-6 pt-5">
            @if(session('success'))
                <div class="flex items-center gap-3 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 text-green-800 dark:text-green-200 rounded-xl px-4 py-3 text-sm">
                    <svg class="w-5 h-5 flex-shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    {{ session('success') }}
                </div>
            @endif
    @if(session('error'))
                <div class="flex items-center gap-3 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 text-red-800 dark:text-red-200 rounded-xl px-4 py-3 text-sm">
                    <svg class="w-5 h-5 flex-shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                    {{ session('error') }}
                </div>
            @endif
    @if(session('info'))
                <div class="flex items-center gap-3 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 text-blue-800 dark:text-blue-200 rounded-xl px-4 py-3 text-sm">
                    <svg class="w-5 h-5 flex-shrink-0 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                    {{ session('info') }}
                </div>
            @endif
        </div>
    @endif

    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 space-y-8">

        {{-- WELCOME STRIP --}}
        <div class="bg-gradient-to-r from-brand-600 to-brand-700 rounded-2xl p-6 text-white shadow-lg">
            <p class="text-brand-100 text-sm font-medium mb-1">Your Learning Progress</p>
            <h2 class="text-2xl font-bold mb-1">{{ auth()->user()->name }}</h2>
            <p class="text-brand-100 text-sm">
                Enrolled {{ $participant->enrolled_at?->format('d M Y') ?? 'N/A' }}
            @if($class->start_date)
                    &nbsp;·&nbsp; Class started {{ $class->start_date->format('d M Y') }}
                @endif
            </p>
        </div>

        {{-- KPI CARDS --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 flex flex-col gap-2">
                <div class="w-9 h-9 rounded-lg bg-brand-50 dark:bg-brand-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $completionRate }}%</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Completion Rate</p>
                <div class="h-1.5 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                    <div class="h-full rounded-full bg-brand-500 transition-all duration-500" style="width:{{ $completionRate }}%"></div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 flex flex-col gap-2">
                <div class="w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $completedCount }}<span class="text-sm font-medium text-gray-400">/{{ $totalCount }}</span></p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Modules Completed</p>
            @if($exemptedCount > 0)
                    <p class="text-xs text-indigo-500">{{ $exemptedCount }} exempted</p>
                @else
                    <div class="h-1.5 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                        <div class="h-full rounded-full bg-blue-500" style="width:{{ $totalCount > 0 ? round(($completedCount/$totalCount)*100) : 0 }}%"></div>
                    </div>
                @endif
            </div>
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 flex flex-col gap-2">
                <div class="w-9 h-9 rounded-lg bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $attendanceCount }}<span class="text-sm font-medium text-gray-400">/{{ $totalSessions }}</span></p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Modules Attended</p>
                <div class="h-1.5 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                    <div class="h-full rounded-full bg-purple-500" style="width:{{ $attendanceRate }}%"></div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 flex flex-col gap-2">
                <div class="w-9 h-9 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                </div>
            @if($avgAssessmentScore !== null)
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ round($avgAssessmentScore,1) }}<span class="text-sm font-medium text-gray-400">%</span></p>
                @else
                    <p class="text-2xl font-bold text-gray-400">—</p>
                @endif
                <p class="text-xs text-gray-500 dark:text-gray-400">Avg. Assessment Score</p>
            @if($assessedModules > 0)
                    <p class="text-xs text-green-500">{{ $assessedModules }} passed</p>
                @else
                    <p class="text-xs text-gray-400">No assessments yet</p>
                @endif
            </div>
        </div>

        {{-- MODULE LIST --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">

            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Modules</h3>
                <span class="text-xs text-gray-400">{{ $totalCount }} module{{ $totalCount !== 1 ? 's' : '' }}</span>
            </div>

            @if($moduleProgress->isEmpty())
                <div class="px-6 py-12 text-center">
                    <p class="text-sm text-gray-400">No modules assigned to this class yet.</p>
                </div>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($moduleProgress as $progress)
                        @php
                        $cm     = $progress->classModule;           // ClassModule
                        $module = $cm?->programModule ?? null;      // ProgramModule
                        $classModuleId = $cm?->id;

                        // Can this mentee confirm attendance right now?
                        // Requires BOTH the ClassModule to be active AND the mentee's
                        // own progress row to be in_progress (not not_started).
                        $canConfirm = $cm
                            && $cm->status === 'in_progress'
                            && $cm->attendance_link_active
                            && $cm->attendance_token
                            && $progress->status === 'in_progress'
                            && ! in_array($classModuleId, $confirmedModuleIds);

                        $alreadyConfirmed = in_array($classModuleId, $confirmedModuleIds);

                        $statusColors = [
                            'completed'   => ['dot'=>'bg-green-500',  'badge'=>'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'],
                            'exempted'    => ['dot'=>'bg-indigo-400', 'badge'=>'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'],
                            'in_progress' => ['dot'=>'bg-amber-400',  'badge'=>'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
                            'not_started' => ['dot'=>'bg-gray-300',   'badge'=>'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'],
                        ];
                        $colors = $statusColors[$progress->status] ?? $statusColors['not_started'];
                        $statusLabel = [
                            'completed'   => 'Completed',
                            'exempted'    => 'Exempted',
                            'in_progress' => 'In Progress',
                            'not_started' => 'Not Started',
                        ][$progress->status] ?? ucfirst($progress->status);
                    @endphp

                        <li class="px-6 py-4 @if($canConfirm) bg-amber-50/60 dark:bg-amber-900/10 @endif">
                            <div class="flex items-start gap-4">

                                {{-- Status dot --}}
                                <div class="mt-1 flex-shrink-0 w-3 h-3 rounded-full {{ $colors['dot'] }} ring-2 ring-white dark:ring-gray-800"></div>

                                {{-- Module info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $module?->name ?? 'Unknown Module' }}
                                        </p>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $colors['badge'] }}">
                                            {{ $statusLabel }}
                                        </span>
                                    @if($progress->completed_in_previous_class)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        Prior class
                                            </span>
                                        @endif
                                    @if($alreadyConfirmed)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-300">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        Attendance confirmed
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Sub-details --}}
                                    <div class="flex items-center gap-4 mt-1.5 text-xs text-gray-400 flex-wrap">
                                        @if($progress->started_at)
                                            <span>Started {{ $progress->started_at->format('d M Y') }}</span>
                                        @endif
                                    @if($progress->completed_at)
                                            <span>· Completed {{ $progress->completed_at->format('d M Y') }}</span>
                                        @endif
                                    @if($progress->attendance_percentage !== null)
                                            <span>· Attendance {{ number_format($progress->attendance_percentage, 0) }}%</span>
                                        @endif
                                    @if($progress->assessment_score !== null)
                                            <span>· Score
                                                <span class="font-semibold
                                                @if($progress->assessment_status === 'passed') text-green-600 dark:text-green-400
                                                @elseif($progress->assessment_status === 'failed') text-red-500
                                                @else text-gray-500 @endif">
                                                    {{ number_format($progress->assessment_score, 1) }}%
                                                </span>
                                            </span>
                                        @endif
                                    </div>

                                    {{-- ── CONFIRM ATTENDANCE BUTTON ──────────────────── --}}
                                @if($canConfirm)
                                        <div class="mt-3">
                                            <a href="{{ route('module.attendance', ['token' => $cm->attendance_token]) }}"
                                       class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                        Confirm My Attendance
                                            </a>
                                            <p class="mt-1.5 text-xs text-amber-600 dark:text-amber-400">
                                        This module is in progress — tap to record your attendance.
                                            </p>
                                        </div>
                                    @endif
                                    {{-- ────────────────────────────────────────────────── --}}

                                </div>

                                {{-- Right: assessment icon --}}
                                <div class="flex-shrink-0 mt-0.5">
                                    @if($progress->assessment_status === 'passed')
                                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    @elseif($progress->assessment_status === 'failed')
                                        <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- FOOTER --}}
        <div class="text-center pb-6">
            <p class="text-xs text-gray-400 dark:text-gray-500">
                    MNCH Mentorship Platform &nbsp;·&nbsp; Attendance opens when your mentor starts a module.
            </p>
        </div>

    </main>
</body>
</html>