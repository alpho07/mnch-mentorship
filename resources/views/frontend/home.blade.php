@extends('layouts.app')

@section('title', 'MNCH Kenya — Training & Resource Hub')
@section('meta_description', 'Access training resources, upcoming programs, and learning materials for maternal, neonatal, and child health in Kenya.')

@section('content')
<div x-data="homePage">

    {{-- ═══════════════════════════════════════════
         QUICK LINKS STRIP
    ═══════════════════════════════════════════ --}}
    <section class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-2 overflow-x-auto py-3 scrollbar-none" style="-webkit-overflow-scrolling: touch;">
                @php $quickLinks = [
                    ['href' => route('resources.index'),                          'icon' => 'fas fa-book-open',   'label' => 'All Resources'],
                    ['href' => route('categories.index'),                         'icon' => 'fas fa-th-large',    'label' => 'Categories'],
                    ['href' => url('analytics/dashboard'),                        'icon' => 'fas fa-map',         'label' => 'Training Map'],
                    ['href' => route('resources.index', ['sort' => 'latest']),    'icon' => 'fas fa-clock',       'label' => 'Recently Added'],
                    ['href' => route('resources.index', ['sort' => 'popular']),   'icon' => 'fas fa-fire',        'label' => 'Most Popular'],
                ]; @endphp
                @foreach($quickLinks as $link)
                <a href="{{ $link['href'] }}"
                   class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-gray-600 hover:bg-primary-50 hover:text-primary-700 transition-all whitespace-nowrap flex-shrink-0 border border-transparent hover:border-primary-100">
                    <i class="{{ $link['icon'] }} text-xs text-primary-500"></i>
                    {{ $link['label'] }}
                </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════
         TRAINING + MENTORSHIP INSIGHTS
    ═══════════════════════════════════════════ --}}
    @if($trainingInsights['has_data'] ?? false)
    <section class="relative overflow-hidden py-14" style="background: linear-gradient(135deg, #062F2F 0%, #004D40 48%, #0F766E 100%);">
        <div class="absolute inset-0 opacity-[0.07]">
            <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                <pattern id="insight-grid" width="42" height="42" patternUnits="userSpaceOnUse">
                    <path d="M 42 0 L 0 0 0 42" fill="none" stroke="white" stroke-width="1"/>
                </pattern>
                <rect width="100%" height="100%" fill="url(#insight-grid)"/>
            </svg>
        </div>
        <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full opacity-20" style="background: radial-gradient(circle, #67E8F9 0%, transparent 68%);"></div>
        <div class="absolute -left-20 bottom-0 h-64 w-64 rounded-full opacity-10" style="background: radial-gradient(circle, #FCD34D 0%, transparent 70%);"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5 mb-8">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-cyan-200/20 px-3 py-1 text-xs font-bold uppercase tracking-widest text-cyan-100"
                         style="background: rgba(255,255,255,0.08);">
                        <i class="fas fa-chart-line text-cyan-200"></i>
                        Program Intelligence
                    </div>
                    <h2 class="mt-4 text-2xl md:text-4xl font-extrabold text-white tracking-tight">
                        Training and mentorship signals that need attention
                    </h2>
                    <p class="mt-2 text-sm md:text-base text-cyan-100/90 leading-relaxed">
                        A live readout from scheduled trainings, facility mentorships, class enrollments, and participant records.
                    </p>
                </div>
                <a href="{{ url('analytics/dashboard') }}"
                   class="inline-flex w-fit items-center gap-2 rounded-xl border border-white/20 px-5 py-3 text-sm font-bold text-white transition-all hover:border-white/40"
                   style="background: rgba(255,255,255,0.1);">
                    Open analytics map <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                @foreach(($trainingInsights['cards'] ?? []) as $card)
                <div class="group rounded-2xl border border-white/12 p-5 shadow-2xl transition-all hover:-translate-y-1 hover:border-white/25"
                     style="background: rgba(255,255,255,0.96);">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-extrabold uppercase tracking-widest text-gray-500">{{ $card['label'] }}</p>
                            <div class="mt-2 text-3xl font-black tracking-tight text-gray-950">{{ $card['value'] }}</div>
                        </div>
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl"
                             style="background: {{ $card['bg'] }}; color: {{ $card['accent'] }};">
                            <i class="{{ $card['icon'] }}"></i>
                        </div>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-gray-600">{{ $card['detail'] }}</p>
                </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                @foreach(($trainingInsights['signals'] ?? []) as $signal)
                <div class="rounded-2xl border border-white/15 p-5 text-white"
                     style="background: rgba(255,255,255,0.09); backdrop-filter: blur(10px);">
                    <div class="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-cyan-100">
                        <span class="h-2 w-2 rounded-full bg-cyan-300"></span>
                        {{ $signal['title'] }}
                    </div>
                    <p class="text-sm leading-relaxed text-white/88">{{ $signal['text'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════
         UPCOMING TRAININGS  ← moved to top
    ═══════════════════════════════════════════ --}}
    @if(isset($upcomingTrainings) && $upcomingTrainings->count() > 0)
    <section class="py-14 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-8">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-1 h-5 rounded-full" style="background: linear-gradient(180deg, #0097A7, #26C6DA);"></div>
                        <span class="text-xs font-semibold text-primary-600 uppercase tracking-widest">Scheduled</span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">Upcoming Trainings</h2>
                    <p class="text-gray-500 text-sm mt-1">Global MOH training programs open for participation</p>
                </div>
                <a href="{{ url('analytics/dashboard') }}"
                   class="hidden md:inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                    View all <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($upcomingTrainings as $training)
                <div class="group relative bg-white rounded-2xl border border-gray-200 p-5 hover:border-primary-300 hover:shadow-lg transition-all duration-200 overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-0.5 rounded-t-2xl"
                         style="background: linear-gradient(90deg, #0097A7 0%, #26C6DA 100%);"></div>
                    @if($training->start_date)
                    <div class="inline-flex items-center gap-2 mb-3">
                        <div class="w-10 h-10 rounded-xl text-white flex flex-col items-center justify-center leading-none flex-shrink-0"
                             style="background: linear-gradient(135deg, #0097A7 0%, #26C6DA 100%);">
                            <span class="text-sm font-extrabold">{{ $training->start_date->format('d') }}</span>
                            <span class="font-semibold uppercase" style="font-size: 8px; letter-spacing: 0.5px;">{{ $training->start_date->format('M') }}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            <div class="font-semibold text-gray-700">{{ $training->start_date->format('M d, Y') }}</div>
                            @if($training->end_date)<div>to {{ $training->end_date->format('M d') }}</div>@endif
                        </div>
                    </div>
                    @endif
                    <h3 class="font-bold text-gray-900 text-sm leading-snug mb-3 group-hover:text-primary-700 transition-colors line-clamp-2">
                        {{ $training->title }}
                    </h3>
                    <div class="space-y-1.5">
                        @if($training->county)
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <i class="fas fa-map-marker-alt text-primary-400 w-3.5"></i>
                            <span class="truncate">{{ $training->county->name }}</span>
                        </div>
                        @endif
                        <div class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                             style="background: #E0F7FA; color: #006064;">
                            {{ ucfirst($training->status) }}
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════
         ONGOING MENTORSHIPS
    ═══════════════════════════════════════════ --}}
    @if(isset($ongoingMentorships) && $ongoingMentorships->count() > 0)
    <section class="py-14" style="background: linear-gradient(135deg, #E0F7FA 0%, #F0FDFF 100%);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-8">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="inline-block w-2 h-2 rounded-full bg-green-400" style="animation:pulse 2s cubic-bezier(.4,0,.6,1) infinite;box-shadow:0 0 6px #4ade80;"></span>
                        <span class="text-xs font-semibold text-primary-700 uppercase tracking-widest">Live Now · {{ $ongoingMentorships->count() }}</span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">Ongoing Mentorships</h2>
                    <p class="text-gray-500 text-sm mt-1">Currently running at facilities across Kenya</p>
                </div>
                <a href="{{ url('analytics/dashboard') }}"
                   class="hidden md:inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                    View all <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($ongoingMentorships as $mentorship)
                <div class="group bg-white rounded-2xl border border-primary-100 p-5 hover:border-primary-300 hover:shadow-lg transition-all duration-200 relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl" style="background: linear-gradient(90deg, #00838F, #0097A7);"></div>
                    <div class="flex items-start justify-between mb-3 mt-1">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                             style="background: linear-gradient(135deg, #00838F 0%, #0097A7 100%);">
                            <i class="fas fa-user-md text-white text-xs"></i>
                        </div>
                        <div class="text-right text-xs text-gray-500 leading-tight">
                            @if($mentorship->end_date)
                            <div class="font-semibold text-gray-700">ends {{ $mentorship->end_date->format('M d, Y') }}</div>
                            @endif
                            @if($mentorship->duration_label)
                            <div class="text-primary-600 mt-0.5">⏱ {{ $mentorship->duration_label }}</div>
                            @endif
                        </div>
                    </div>
                    <h3 class="font-bold text-gray-900 text-sm leading-snug mb-3 group-hover:text-primary-700 transition-colors line-clamp-2">
                        {{ $mentorship->title }}
                    </h3>
                    <div class="space-y-1.5">
                        @if($mentorship->facility)
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <i class="fas fa-hospital text-primary-400 w-3.5"></i>
                            <span class="truncate">{{ $mentorship->facility->name }}</span>
                        </div>
                        @elseif($mentorship->county)
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <i class="fas fa-map-marker-alt text-primary-400 w-3.5"></i>
                            <span class="truncate">{{ $mentorship->county->name }}</span>
                        </div>
                        @endif
                        <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold"
                             style="background: #E0F7FA; color: #0097A7;">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-400 inline-block"></span>
                            Ongoing
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════
         UPCOMING MENTORSHIPS
    ═══════════════════════════════════════════ --}}
    @if(isset($upcomingMentorships) && $upcomingMentorships->count() > 0)
    <section class="py-14" style="background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-8">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <i class="fas fa-calendar-alt text-xs" style="color:#15803d;"></i>
                        <span class="text-xs font-semibold uppercase tracking-widest" style="color:#15803d;">Coming Up · {{ $upcomingMentorships->count() }}</span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">Upcoming Mentorships</h2>
                    <p class="text-gray-500 text-sm mt-1">Facility mentorships scheduled to begin soon</p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($upcomingMentorships as $mentorship)
                <div class="group bg-white rounded-2xl border border-green-100 p-5 hover:border-green-300 hover:shadow-lg transition-all duration-200 relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl" style="background: linear-gradient(90deg, #15803d, #22c55e);"></div>
                    <div class="flex items-start justify-between mb-3 mt-1">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                             style="background: linear-gradient(135deg, #15803d 0%, #22c55e 100%);">
                            <i class="fas fa-calendar-check text-white text-xs"></i>
                        </div>
                        <div class="text-right text-xs text-gray-500 leading-tight">
                            @if($mentorship->start_date)
                            <div class="font-semibold text-gray-700">starts {{ $mentorship->start_date->format('M d, Y') }}</div>
                            @endif
                            @if($mentorship->duration_label)
                            <div class="mt-0.5" style="color:#15803d;">⏱ {{ $mentorship->duration_label }}</div>
                            @endif
                        </div>
                    </div>
                    <h3 class="font-bold text-gray-900 text-sm leading-snug mb-3 group-hover:text-green-700 transition-colors line-clamp-2">
                        {{ $mentorship->title }}
                    </h3>
                    <div class="space-y-1.5">
                        @if($mentorship->facility)
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <i class="fas fa-hospital text-green-400 w-3.5"></i>
                            <span class="truncate">{{ $mentorship->facility->name }}</span>
                        </div>
                        @elseif($mentorship->county)
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <i class="fas fa-map-marker-alt text-green-400 w-3.5"></i>
                            <span class="truncate">{{ $mentorship->county->name }}</span>
                        </div>
                        @endif
                        <div class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                             style="background: #DCFCE7; color: #15803d;">
                            Upcoming
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════
         RECENTLY CLOSED MENTORSHIPS
    ═══════════════════════════════════════════ --}}
    @if(isset($closedMentorships) && $closedMentorships->count() > 0)
    <section class="py-10" style="background: linear-gradient(135deg, #F9FAFB 0%, #F3F4F6 100%);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <i class="fas fa-check-circle text-xs text-gray-400"></i>
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Recently Closed · {{ $closedMentorships->count() }}</span>
                    </div>
                    <h2 class="text-xl md:text-2xl font-extrabold text-gray-500">Completed Mentorships</h2>
                    <p class="text-gray-400 text-sm mt-1">Programs that wrapped up in the last 30 days</p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($closedMentorships as $mentorship)
                <div class="bg-white rounded-2xl border border-gray-100 p-5 opacity-75 relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl bg-gray-300"></div>
                    <div class="flex items-start justify-between mb-3 mt-1">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 bg-gray-200">
                            <i class="fas fa-user-md text-gray-400 text-xs"></i>
                        </div>
                        <div class="text-right text-xs text-gray-400 leading-tight">
                            @if($mentorship->end_date)
                            <div class="font-semibold">ended {{ $mentorship->end_date->format('M d, Y') }}</div>
                            @endif
                            @if($mentorship->duration_label)
                            <div class="mt-0.5">⏱ {{ $mentorship->duration_label }}</div>
                            @endif
                        </div>
                    </div>
                    <h3 class="font-semibold text-gray-500 text-sm leading-snug mb-3 line-clamp-2">
                        {{ $mentorship->title }}
                    </h3>
                    <div class="space-y-1.5">
                        @if($mentorship->facility)
                        <div class="flex items-center gap-1.5 text-xs text-gray-400">
                            <i class="fas fa-hospital text-gray-300 w-3.5"></i>
                            <span class="truncate">{{ $mentorship->facility->name }}</span>
                        </div>
                        @elseif($mentorship->county)
                        <div class="flex items-center gap-1.5 text-xs text-gray-400">
                            <i class="fas fa-map-marker-alt text-gray-300 w-3.5"></i>
                            <span class="truncate">{{ $mentorship->county->name }}</span>
                        </div>
                        @endif
                        <div class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-400">
                            ✓ Closed
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════
         FEATURED RESOURCES
    ═══════════════════════════════════════════ --}}
    @if($featuredResources->count() > 0)
    <section class="py-14 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-8">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-1 h-5 rounded-full" style="background: linear-gradient(180deg, #0097A7, #26C6DA);"></div>
                        <span class="text-xs font-semibold text-primary-600 uppercase tracking-widest">Curated</span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">Featured Resources</h2>
                    <p class="text-gray-500 text-sm mt-1">Hand-picked clinical materials our community values most</p>
                </div>
                <a href="{{ route('resources.index', ['featured' => 1]) }}"
                   class="hidden md:inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                    View all <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($featuredResources as $resource)
                    @include('components.resource-card', ['resource' => $resource, 'featured' => true])
                @endforeach
            </div>
            <div class="text-center mt-8 md:hidden">
                <a href="{{ route('resources.index', ['featured' => 1]) }}"
                   class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-white text-sm font-semibold shadow-md"
                   style="background: linear-gradient(135deg, #0097A7 0%, #26C6DA 100%);">
                    <i class="fas fa-star"></i>View All Featured
                </a>
            </div>
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════
         CATEGORIES GRID
    ═══════════════════════════════════════════ --}}
    <section class="py-14 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-8">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-1 h-5 rounded-full" style="background: linear-gradient(180deg, #0097A7, #26C6DA);"></div>
                        <span class="text-xs font-semibold text-primary-600 uppercase tracking-widest">Browse by Topic</span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">Explore Categories</h2>
                    <p class="text-gray-500 text-sm mt-1">Resources organised by clinical topics and subject areas</p>
                </div>
                <a href="{{ route('categories.index') }}"
                   class="hidden md:inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                    All categories <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($categories as $category)
                <a href="{{ route('resources.category', $category->slug) }}"
                   class="group bg-white rounded-xl border border-gray-200 p-5 hover:border-primary-200 hover:shadow-md transition-all duration-200">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform"
                             style="background: linear-gradient(135deg, #E0F7FA 0%, #B2EBF2 100%);">
                            <i class="{{ $category->icon ?? 'fas fa-folder' }} text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 text-sm group-hover:text-primary-700 transition-colors">{{ $category->name }}</h3>
                            <p class="text-xs text-gray-400">{{ $category->resources_count }} resources</p>
                        </div>
                    </div>
                    @if($category->description)
                    <p class="text-gray-500 text-xs line-clamp-2 mb-3">{{ $category->description }}</p>
                    @endif
                    @if($category->children->count() > 0)
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($category->children->take(3) as $child)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $child->name }}</span>
                        @endforeach
                        @if($category->children->count() > 3)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">+{{ $category->children->count() - 3 }}</span>
                        @endif
                    </div>
                    @endif
                </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════
         RECENTLY ADDED
    ═══════════════════════════════════════════ --}}
    @if($recentResources->count() > 0)
    <section class="py-14 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-8">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-1 h-5 rounded-full bg-emerald-400"></div>
                        <span class="text-xs font-semibold text-emerald-600 uppercase tracking-widest">New</span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">Recently Added</h2>
                    <p class="text-gray-500 text-sm mt-1">Latest materials added to our clinical library</p>
                </div>
                <a href="{{ route('resources.index', ['sort' => 'latest']) }}"
                   class="hidden md:inline-flex items-center gap-2 text-sm font-semibold text-emerald-600 hover:text-emerald-700 transition-colors">
                    View all <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                @foreach($recentResources as $resource)
                    @include('components.resource-card-compact', ['resource' => $resource])
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════
         TRENDING
    ═══════════════════════════════════════════ --}}
    @if($popularResources->count() > 0)
    <section class="py-14 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-8">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-1 h-5 rounded-full bg-orange-400"></div>
                        <span class="text-xs font-semibold text-orange-500 uppercase tracking-widest">Popular</span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">Trending Now</h2>
                    <p class="text-gray-500 text-sm mt-1">Most accessed resources this month</p>
                </div>
                <a href="{{ route('resources.index', ['sort' => 'popular']) }}"
                   class="hidden md:inline-flex items-center gap-2 text-sm font-semibold text-orange-500 hover:text-orange-600 transition-colors">
                    View all <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($popularResources as $resource)
                    @include('components.resource-card', ['resource' => $resource])
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════
         CTA BANNER
    ═══════════════════════════════════════════ --}}
    <section class="py-16 relative overflow-hidden" style="background: linear-gradient(135deg, #004D40 0%, #0097A7 100%);">
        <div class="absolute inset-0 opacity-[0.04]">
            <svg width="100%" height="100%"><pattern id="dots" x="0" y="0" width="40" height="40" patternUnits="userSpaceOnUse"><circle cx="20" cy="20" r="1.5" fill="white"/></pattern><rect width="100%" height="100%" fill="url(#dots)"/></svg>
        </div>
        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="mb-10">
                <h2 class="text-2xl md:text-3xl font-extrabold text-white mb-2">Stay Informed</h2>
                <p class="text-cyan-100 text-sm mb-6">Receive updates on new resources, training schedules, and clinical guidelines</p>
                <form class="max-w-md mx-auto" x-data="{ email: '', subscribed: false }">
                    <div class="flex gap-2">
                        <input type="email" x-model="email" placeholder="Your email address"
                               class="flex-1 px-4 py-3 rounded-xl text-gray-900 text-sm border-0 outline-none focus:ring-2 focus:ring-white/40">
                        <button type="button" @click.prevent="subscribed = true; email = ''"
                                class="px-5 py-3 bg-white text-primary-700 rounded-xl text-sm font-bold hover:bg-gray-100 transition-colors flex-shrink-0">
                            Subscribe
                        </button>
                    </div>
                    <p x-show="subscribed" x-transition class="text-cyan-200 text-xs mt-2">
                        <i class="fas fa-check-circle mr-1"></i>Thank you! You're subscribed.
                    </p>
                </form>
            </div>
            <div class="border-t border-white/10 pt-10">
                <h2 class="text-2xl font-extrabold text-white mb-4">Ready to Start Learning?</h2>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    @guest
                    <a href="{{ url('register') }}"
                       class="inline-flex items-center justify-center gap-2 px-7 py-3.5 bg-white text-primary-700 rounded-xl font-bold text-sm hover:bg-gray-100 transition-all shadow-lg">
                        <i class="fas fa-user-plus"></i>Get Started Free
                    </a>
                    @endguest
                    <a href="{{ route('resources.index') }}"
                       class="inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-xl font-bold text-sm text-white border-2 border-white/30 hover:border-white/60 transition-all"
                       style="background: rgba(255,255,255,0.12);">
                        <i class="fas fa-book-open"></i>Explore Resources
                    </a>
                    <a href="{{ url('analytics/dashboard') }}"
                       class="inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-xl font-bold text-sm text-white border-2 border-white/30 hover:border-white/60 transition-all"
                       style="background: rgba(255,255,255,0.12);">
                        <i class="fas fa-map"></i>View Training Map
                    </a>
                </div>
                <div class="flex items-center justify-center gap-8 mt-8 text-cyan-200 text-xs">
                    <div class="flex items-center gap-1.5"><i class="fas fa-shield-alt text-cyan-300"></i>Secure Platform</div>
                    <div class="flex items-center gap-1.5"><i class="fas fa-mobile-alt text-cyan-300"></i>Mobile Ready</div>
                    <div class="flex items-center gap-1.5"><i class="fas fa-cloud-download-alt text-cyan-300"></i>Offline Access</div>
                </div>
            </div>
        </div>
    </section>

</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('homePage', () => ({ init() {} }));
    });
</script>
@endpush

@push('styles')
<style>
    .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .scrollbar-none::-webkit-scrollbar { display: none; }
    .scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }
</style>
@endpush
@endsection
