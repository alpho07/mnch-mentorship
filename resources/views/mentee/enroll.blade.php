{{-- resources/views/mentee/enroll.blade.php --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Enroll – {{ $class->name }}</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            body {
                font-family: 'Plus Jakarta Sans', sans-serif;
            }
        </style>
    </head>
    <body class="min-h-full bg-gradient-to-br from-slate-50 to-blue-50">

    {{-- Header --}}
    <div class="bg-white border-b border-slate-200 shadow-sm">
        <div class="max-w-3xl mx-auto px-4 py-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-blue-600 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <span class="text-sm font-semibold text-slate-700">MNCH Mentorship Platform</span>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 py-10 space-y-6">

        {{-- Flash messages --}}
        @if(session('error'))
            <div class="rounded-xl bg-red-50 border border-red-200 px-5 py-4 flex items-start gap-3">
                <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-red-700">{{ session('error') }}</p>
            </div>
        @endif

        @if(session('info'))
            <div class="rounded-xl bg-blue-50 border border-blue-200 px-5 py-4 flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-blue-700">{{ session('info') }}</p>
            </div>
        @endif

        {{-- Class card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

            {{-- Banner --}}
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-blue-200 text-xs font-medium uppercase tracking-wider mb-1">
                                You've been invited to enroll
                            </p>
                            <h1 class="text-white text-2xl font-bold leading-tight">
                            {{ $class->name }}
                            </h1>
                        @if($class->training)
                            <p class="text-blue-200 text-sm mt-1">
                                {{ $class->training->name }}
                            </p>
                        @endif
                        </div>
                        <span class="flex-shrink-0 inline-flex items-center px-3 py-1 rounded-full bg-green-400/20 border border-green-300/30 text-green-100 text-xs font-semibold">
                            Enrollment Open
                        </span>
                    </div>
                </div>

                <div class="p-6 space-y-6">

                {{-- Class details --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    @if($class->start_date)
                        <div class="bg-slate-50 rounded-xl px-4 py-3">
                            <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Start Date</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">
                                {{ \Carbon\Carbon::parse($class->start_date)->format('d M Y') }}
                            </p>
                        </div>
                    @endif
                    @if($class->end_date)
                        <div class="bg-slate-50 rounded-xl px-4 py-3">
                            <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">End Date</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">
                                {{ \Carbon\Carbon::parse($class->end_date)->format('d M Y') }}
                            </p>
                        </div>
                    @endif
                        <div class="bg-slate-50 rounded-xl px-4 py-3">
                            <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Modules</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">
                            {{ $class->classModules->count() }} {{ Str::plural('module', $class->classModules->count()) }}
                            </p>
                        </div>
                    </div>

                {{-- Description --}}
                @if($class->description)
                    <div>
                        <p class="text-sm text-slate-600 leading-relaxed">{{ $class->description }}</p>
                    </div>
                @endif

                {{-- Modules list --}}
                @if($class->classModules->isNotEmpty())
                    <div>
                        <h3 class="text-sm font-semibold text-slate-700 mb-3">Curriculum</h3>
                        <div class="space-y-2">
                            @foreach($class->classModules->sortBy('order_sequence') as $cm)
                            <div class="flex items-center gap-3 px-4 py-3 bg-slate-50 rounded-xl">
                                <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold flex-shrink-0">
                                        {{ $loop->iteration }}
                                </div>
                                <span class="text-sm text-slate-700 font-medium">
                                        {{ $cm->programModule?->name ?? 'Module ' . $loop->iteration }}
                                </span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Enrollment form --}}
                    <div class="border-t border-slate-100 pt-6">
                        <h3 class="text-sm font-semibold text-slate-700 mb-1">Ready to enroll?</h3>
                        <p class="text-xs text-slate-500 mb-4">
                            Enter the email address associated with your account on this platform.
                        </p>

                        <form method="POST" action="{{ route('mentee.enroll.submit', ['token' => request()->route('token')]) }}" class="space-y-4">
                        @csrf

                            <div>
                                <label for="email" class="block text-xs font-medium text-slate-600 mb-1.5">
                                    Your Email Address
                                </label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    autofocus
                                    placeholder="you@example.com"
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-sm text-slate-800
                                    placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500
                                    focus:border-transparent transition
                                       @error('email') border-red-300 focus:ring-red-400 @enderror"
                                    >
                            @error('email')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button
                                type="submit"
                                class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-semibold
                                text-sm py-3 px-6 rounded-xl transition-colors duration-150 focus:outline-none
                                focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                >
                                Continue to Enroll →
                        </button>
                    </form>
                </div>

            </div>
        </div>

        <p class="text-center text-xs text-slate-400">
                MNCH Kenya Mentorship Platform &mdash; Ministry of Health
        </p>
    </div>

</body>
</html>