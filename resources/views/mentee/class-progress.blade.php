<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>My Class Progress - {{ $class->name }}</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50">
    <div class="min-h-screen py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            {{-- Header --}}
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">{{ $class->name }}</h1>
                        <p class="text-gray-600 mt-1">{{ $class->training->name ?? 'Mentorship Program' }}</p>
                    </div>
                    @auth
                        <a href="{{ route('filament.admin.pages.mentee-dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Dashboard
                        </a>
                    @endauth
                </div>

                {{-- Progress Stats --}}
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="text-sm text-blue-600 font-medium">Total Modules</div>
                        <div class="text-2xl font-bold text-blue-900 mt-1">{{ $totalModules }}</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="text-sm text-green-600 font-medium">Completed</div>
                        <div class="text-2xl font-bold text-green-900 mt-1">{{ $completedCount }}</div>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-4">
                        <div class="text-sm text-purple-600 font-medium">Exempted</div>
                        <div class="text-2xl font-bold text-purple-900 mt-1">{{ $exemptedCount }}</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-4">
                        <div class="text-sm text-yellow-600 font-medium">Progress</div>
                        <div class="text-2xl font-bold text-yellow-900 mt-1">{{ $progressPercentage }}%</div>
                    </div>
                </div>

                {{-- Progress Bar --}}
                <div class="mt-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700">Overall Progress</span>
                        <span class="text-sm font-medium text-gray-700">{{ $progressPercentage }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-blue-600 h-3 rounded-full transition-all duration-500" style="width: {{ $progressPercentage }}%"></div>
                    </div>
                </div>

                @if($attendanceRate > 0)
                    <div class="mt-4 flex items-center text-sm text-gray-600">
                        <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Average Attendance: <strong class="ml-1">{{ $attendanceRate }}%</strong>
                    </div>
                @endif
            </div>

            {{-- Exempted Modules --}}
            @if($exemptedModules->count() > 0)
                <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Exempted Modules ({{ $exemptedModules->count() }})
                    </h2>
                    <p class="text-sm text-gray-600 mb-4">You've been exempted from these modules because you completed them in a previous class.</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($exemptedModules as $progress)
                        <div class="border border-purple-200 rounded-lg p-4 bg-purple-50">
                            <h3 class="font-semibold text-gray-900">{{ $progress->classModule->programModule->name ?? 'Module' }}</h3>
                            <p class="text-sm text-gray-600 mt-1">{{ $progress->classModule->programModule->description ?? '' }}</p>
                            <div class="flex items-center mt-2 text-sm text-purple-600">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Exempted {{ $progress->exempted_at ? $progress->exempted_at->format('M d, Y') : '' }}
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Active Modules --}}
            @if($activeModules->count() > 0)
                <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        Active Modules ({{ $activeModules->count() }})
                    </h2>

                    <div class="space-y-4">
                        @foreach($activeModules as $progress)
                        <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-900">{{ $progress->classModule->programModule->name ?? 'Module' }}</h3>
                                    <p class="text-sm text-gray-600 mt-1">{{ $progress->classModule->programModule->description ?? '' }}</p>

                                        {{-- Status Badge --}}
                                    <div class="mt-3">
                                            @if($progress->status === 'not_started')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            Not Started
                                        </span>
                                            @elseif($progress->status === 'in_progress')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            In Progress
                                        </span>
                                            @endif
                                    </div>

                                        {{-- Progress Details --}}
                                    <div class="mt-3 grid grid-cols-2 gap-4 text-sm">
                                            @if($progress->attendance_percentage !== null)
                                        <div>
                                            <span class="text-gray-600">Attendance:</span>
                                            <strong class="ml-1 text-gray-900">{{ $progress->attendance_percentage }}%</strong>
                                        </div>
                                            @endif
                                            @if($progress->assessment_score !== null)
                                        <div>
                                            <span class="text-gray-600">Assessment:</span>
                                            <strong class="ml-1 text-gray-900">{{ $progress->assessment_score }}%</strong>
                                        </div>
                                            @endif
                                    </div>

                                        {{-- Assessments --}}
                                        @if($progress->classModule->moduleAssessments && $progress->classModule->moduleAssessments->count() > 0)
                                    <div class="mt-4 pt-4 border-t border-gray-200">
                                        <h4 class="text-sm font-medium text-gray-700 mb-2">Assessments:</h4>
                                        <div class="space-y-2">
                                                    @foreach($progress->classModule->moduleAssessments as $assessment)
                                                        @php
                                                            $result = $progress->assessmentResults->firstWhere('module_assessment_id', $assessment->id);
                                                        @endphp
                                            <div class="flex items-center justify-between text-sm bg-gray-50 rounded p-2">
                                                <span class="text-gray-700">{{ $assessment->title }}</span>
                                                            @if($result)
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $result->status === 'passed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                                    {{ $result->score }}% - {{ ucfirst($result->status) }}
                                                </span>
                                                            @else
                                                <span class="text-gray-400 text-xs">Not attempted</span>
                                                            @endif
                                            </div>
                                                    @endforeach
                                        </div>
                                    </div>
                                        @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Completed Modules --}}
            @if($completedModules->count() > 0)
                <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Completed Modules ({{ $completedModules->count() }})
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($completedModules as $progress)
                        <div class="border border-green-200 rounded-lg p-4 bg-green-50">
                            <h3 class="font-semibold text-gray-900">{{ $progress->classModule->programModule->name ?? 'Module' }}</h3>
                            <p class="text-sm text-gray-600 mt-1">{{ $progress->classModule->programModule->description ?? '' }}</p>
                            <div class="mt-3 space-y-1 text-sm">
                                <div class="flex items-center text-green-600">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Completed {{ $progress->completed_at ? $progress->completed_at->format('M d, Y') : '' }}
                                </div>
                                    @if($progress->attendance_percentage)
                                <div class="text-gray-600">
                                    Attendance: <strong>{{ $progress->attendance_percentage }}%</strong>
                                </div>
                                    @endif
                                    @if($progress->assessment_score)
                                <div class="text-gray-600">
                                    Assessment: <strong>{{ $progress->assessment_score }}%</strong>
                                    <span class="ml-1 text-xs {{ $progress->assessment_status === 'passed' ? 'text-green-600' : 'text-red-600' }}">
                                        ({{ ucfirst($progress->assessment_status) }})
                                    </span>
                                </div>
                                    @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Empty State --}}
            @if($totalModules === 0)
                <div class="bg-white rounded-lg shadow-lg p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No modules yet</h3>
                    <p class="mt-1 text-sm text-gray-500">Modules will appear here once they're added to your class.</p>
                </div>
            @endif
        </div>
    </div>
</body>
</html>