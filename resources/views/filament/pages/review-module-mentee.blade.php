@php
$mentee = $participant->user;
$facility = $mentee?->facility;
$subcounty = $facility?->subcounty;
$county = $subcounty?->county;
$progressStatus = $progress?->status ?? 'not_started';
$isPresent = in_array($progressStatus, ['in_progress', 'completed']);
@endphp

<x-filament-panels::page>
    {{-- ─── MENTEE DETAILS ─────────────────────────────────────────────────── --}}
    <div class="rounded-2xl border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-base font-bold text-gray-900 dark:text-white">Mentee Details</h3>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Name</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $mentee?->full_name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Email</p>
                <p class="text-sm text-gray-700 dark:text-gray-200">{{ $mentee?->email ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Phone</p>
                <p class="text-sm text-gray-700 dark:text-gray-200">{{ $mentee?->phone ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Cadre</p>
                <p class="text-sm text-gray-700 dark:text-gray-200">{{ $mentee?->cadre?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Facility</p>
                <p class="text-sm text-gray-700 dark:text-gray-200">{{ $facility?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Subcounty</p>
                <p class="text-sm text-gray-700 dark:text-gray-200">{{ $subcounty?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">County</p>
                <p class="text-sm text-gray-700 dark:text-gray-200">{{ $county?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Department</p>
                <p class="text-sm text-gray-700 dark:text-gray-200">{{ $mentee?->department?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Enrollment Status</p>
                <span @class([
                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' => $participant->status === 'active',
                    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' => $participant->status === 'enrolled',
                    'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' => ! in_array($participant->status, ['active', 'enrolled']),
                ])>{{ ucfirst($participant->status) }}</span>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Module Attendance</p>
                <span @class([
                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' => $isPresent,
                    'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' => ! $isPresent,
                ])>{{ $isPresent ? 'Confirmed Present' : 'Not Confirmed' }}</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- ─── LEFT COLUMN: CONTEXT ─────────────────────────────────────────── --}}
        <div class="lg:col-span-1 space-y-6">
            {{-- Mentorship details --}}
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Mentorship</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Program</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $training->program?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Mentorship</p>
                        <p class="text-sm text-gray-700 dark:text-gray-200">{{ $training->title ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Facility</p>
                        <p class="text-sm text-gray-700 dark:text-gray-200">{{ $training->facility?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Mentor</p>
                        <p class="text-sm text-gray-700 dark:text-gray-200">{{ $training->mentor?->full_name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Status</p>
                        <p class="text-sm text-gray-700 dark:text-gray-200 capitalize">{{ $training->status ?? '—' }}</p>
                    </div>
                    @if(! $isEmonc && ($training->start_date || $training->end_date))
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Schedule</p>
                            <p class="text-sm text-gray-700 dark:text-gray-200">
                                {{ $training->start_date?->format('d M Y') ?? '—' }} — {{ $training->end_date?->format('d M Y') ?? '—' }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Module details --}}
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Module</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Module Name</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $module->programModule?->name ?? '—' }}</p>
                    </div>
                    @if($module->programModule?->parent)
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Track / Parent</p>
                            <p class="text-sm text-blue-600 dark:text-blue-400">{{ $module->programModule->parent->name }}</p>
                        </div>
                    @endif
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Class</p>
                        <p class="text-sm text-gray-700 dark:text-gray-200">{{ $class->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Module Status</p>
                        <span @class([
                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                            'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' => $module->status === 'not_started',
                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $module->status === 'in_progress',
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' => $module->status === 'completed',
                        ])>{{ ucfirst(str_replace('_', ' ', $module->status ?? 'not_started')) }}</span>
                    </div>
                    @if(! $isEmonc && ($module->start_date || $module->end_date))
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Module Dates</p>
                            <p class="text-sm text-gray-700 dark:text-gray-200">
                                {{ $module->start_date?->format('d M Y') ?? '—' }} — {{ $module->end_date?->format('d M Y') ?? '—' }}
                            </p>
                        </div>
                    @endif
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Confirmed Attendance</p>
                        <p class="text-sm text-gray-700 dark:text-gray-200">{{ $module->confirmedAttendanceCount() }} / {{ $module->enrolledMenteeCount() }}</p>
                    </div>
                </div>
            </div>

            {{-- Activities --}}
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Activities</h3>
                </div>
                <div class="divide-y divide-gray-50 dark:divide-gray-800/50">
                    @forelse($activities as $activity)
                        <div class="px-6 py-3 flex items-center justify-between">
                            <span class="text-sm text-gray-800 dark:text-gray-200">{{ $activity['name'] }}</span>
                            <div class="flex items-center gap-2">
                                @if($activity['enrolled'])
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Enrolled</span>
                                @else
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">Not Enrolled</span>
                                @endif
                                @if($activity['completed'])
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">Completed</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-8 text-center text-sm text-gray-400">No activities attached to this module.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ─── RIGHT COLUMN: VIDEO, QUIZZES, EVALUATION ─────────────────────── --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Hands-on video --}}
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Hands-on Video</h3>
                </div>
                <div class="p-6">
                    @if($progress && ($progress->hands_on_video_url || $progress->hands_on_video_path))
                        @include('filament.components.video-preview', [
                            'embedUrl' => $progress->youtubeEmbedUrl(),
                            'videoUrl' => $progress->hands_on_video_url,
                            'isDirectVideo' => $progress->isDirectVideoUrl(),
                        ])
                    @else
                        <div class="text-center py-10 rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-700">
                            <p class="text-3xl mb-2">🎥</p>
                            <p class="text-sm text-gray-500">No hands-on video submitted yet.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Pre-test --}}
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Pre-Test</h3>
                    @if($preTestStatus['exists'])
                        <span @class([
                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' => $preTestStatus['completed'],
                            'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => ! $preTestStatus['completed'],
                        ])>{{ $preTestStatus['completed'] ? 'Completed' : 'Not Taken' }}</span>
                    @else
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">No Pre-Test</span>
                    @endif
                </div>
                <div class="p-6">
                    @if($preTestStatus['exists'] && $preTestStatus['completed'])
                        @include('mentee.partials.quiz-review', ['status' => $preTestStatus])
                    @elseif($preTestStatus['exists'])
                        <p class="text-sm text-gray-500">The mentee has not completed the pre-test yet.</p>
                    @else
                        <p class="text-sm text-gray-500">This module does not have a pre-test.</p>
                    @endif
                </div>
            </div>

            {{-- Post-test --}}
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Post-Test</h3>
                    @if($postTestStatus['exists'])
                        <span @class([
                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' => $postTestStatus['completed'],
                            'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => ! $postTestStatus['completed'],
                        ])>{{ $postTestStatus['completed'] ? 'Completed' : 'Not Taken' }}</span>
                    @else
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">No Post-Test</span>
                    @endif
                </div>
                <div class="p-6">
                    @if($postTestStatus['exists'] && $postTestStatus['completed'])
                        @include('mentee.partials.quiz-review', ['status' => $postTestStatus])
                    @elseif($postTestStatus['exists'])
                        <p class="text-sm text-gray-500">The mentee has not completed the post-test yet.</p>
                    @else
                        <p class="text-sm text-gray-500">This module does not have a post-test.</p>
                    @endif
                </div>
            </div>

            {{-- Evaluation form --}}
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Video Evaluation</h3>
                </div>
                <div class="p-6">
                    <form wire:submit="saveReview" class="space-y-4">
                        {{ $this->form }}

                        <div class="flex items-center gap-3 pt-2">
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 transition-colors">
                                Save Review
                            </button>
                            <span class="text-xs text-gray-400">This updates the video review outcome and notifies the mentee.</span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
