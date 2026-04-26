@extends('layouts.app')

@section('title', 'MNCH Kenya — Training & Resource Hub')
@section('meta_description', 'Access training resources, upcoming programs, and learning materials for maternal, neonatal, and child health in Kenya.')

@section('content')
<div x-data="homePage">

    {{-- ═══════════════════════════════════════════
         HERO
    ═══════════════════════════════════════════ --}}
    <section class="relative overflow-hidden" style="background: linear-gradient(135deg, #004D40 0%, #00695C 25%, #0097A7 70%, #26C6DA 100%);">
        <div class="absolute top-0 right-0 w-96 h-96 opacity-10"
             style="background: radial-gradient(circle, #ffffff 0%, transparent 70%); transform: translate(30%, -30%);"></div>
        <div class="absolute bottom-0 left-0 w-72 h-72 opacity-10"
             style="background: radial-gradient(circle, #26C6DA 0%, transparent 70%); transform: translate(-30%, 30%);"></div>
        <div class="absolute inset-0 opacity-5">
            <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                <pattern id="hb" x="0" y="0" width="300" height="60" patternUnits="userSpaceOnUse">
                    <polyline points="0,30 40,30 55,10 65,50 75,30 90,30 100,20 110,40 120,30 300,30"
                              fill="none" stroke="white" stroke-width="1.5"/>
                </pattern>
                <rect width="100%" height="100%" fill="url(#hb)"/>
            </svg>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 md:py-24">
            <div class="text-center max-w-3xl mx-auto">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full mb-6 text-sm font-semibold text-white border border-white/20"
                     style="background: rgba(255,255,255,0.12); backdrop-filter: blur(8px);">
                    <span class="w-1.5 h-1.5 bg-green-400 rounded-full animate-pulse"></span>
                    Kenya Ministry of Health · MNCH Program
                </div>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-white mb-5 leading-tight tracking-tight">
                    Strengthening Skills,<br>
                    <span class="text-cyan-200">Saving Lives</span>
                </h1>
                <p class="text-lg md:text-xl text-cyan-100 mb-8 leading-relaxed max-w-2xl mx-auto">
                    Access training materials, clinical guidelines, and learning resources for frontline health workers across Kenya.
                </p>
                <form action="{{ route('resources.search') }}" method="GET" class="max-w-xl mx-auto mb-10">
                    <div class="relative">
                        <input type="text" name="q" value="{{ request('q') }}"
                               placeholder="Search guidelines, protocols, training materials…"
                               class="w-full pl-12 pr-32 py-4 rounded-2xl text-gray-900 shadow-2xl border-0 outline-none focus:ring-4 focus:ring-white/30"
                               style="font-size: 15px;">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <button type="submit"
                                class="absolute right-2 top-2 bottom-2 px-5 rounded-xl text-white text-sm font-semibold"
                                style="background: linear-gradient(135deg, #0097A7 0%, #00838F 100%);">
                            Search
                        </button>
                    </div>
                </form>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 max-w-3xl mx-auto">
                    @php $heroStats = [
                        ['value' => number_format(\App\Models\Resource::published()->count()), 'label' => 'Resources'],
                        ['value' => number_format($categories->count()), 'label' => 'Categories'],
                        ['value' => number_format(\App\Models\User::count()), 'label' => 'Health Workers'],
                        ['value' => number_format(\App\Models\ResourceDownload::count()), 'label' => 'Downloads'],
                    ]; @endphp
                    @foreach($heroStats as $stat)
                    <div class="rounded-xl px-4 py-3 text-center border border-white/15"
                         style="background: rgba(255,255,255,0.1); backdrop-filter: blur(8px);">
                        <div class="text-2xl md:text-3xl font-extrabold text-white">{{ $stat['value'] }}</div>
                        <div class="text-xs text-cyan-200 font-medium mt-0.5">{{ $stat['label'] }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

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
         UPCOMING MENTORSHIPS  ← moved to top
    ═══════════════════════════════════════════ --}}
    @if(isset($upcomingMentorships) && $upcomingMentorships->count() > 0)
    <section class="py-14" style="background: linear-gradient(135deg, #E0F7FA 0%, #F0FDFF 100%);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-8">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-1 h-5 rounded-full" style="background: linear-gradient(180deg, #00838F, #0097A7);"></div>
                        <span class="text-xs font-semibold text-primary-700 uppercase tracking-widest">Facility-Based</span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">Upcoming Mentorships</h2>
                    <p class="text-gray-500 text-sm mt-1">On-site clinical mentorship programs at facilities near you</p>
                </div>
                <a href="{{ url('analytics/dashboard') }}"
                   class="hidden md:inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                    View all <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($upcomingMentorships as $mentorship)
                <div class="group bg-white rounded-2xl border border-primary-100 p-5 hover:border-primary-300 hover:shadow-lg transition-all duration-200 relative overflow-hidden">
                    <div class="absolute -top-6 -right-6 w-20 h-20 rounded-full opacity-10"
                         style="background: radial-gradient(circle, #0097A7 0%, transparent 70%);"></div>
                    <div class="flex items-start gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                             style="background: linear-gradient(135deg, #00838F 0%, #0097A7 100%);">
                            <i class="fas fa-user-md text-white text-sm"></i>
                        </div>
                        @if($mentorship->start_date)
                        <div class="text-xs text-gray-500">
                            <div class="font-semibold text-gray-700">{{ $mentorship->start_date->format('M d, Y') }}</div>
                            @if($mentorship->end_date)<div>to {{ $mentorship->end_date->format('M d') }}</div>@endif
                        </div>
                        @endif
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
                        <div class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                             style="background: #D1FAE5; color: #065F46;">
                            Mentorship
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
