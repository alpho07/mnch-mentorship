<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Resource Center') - {{ config('app.name') }}</title>
        <meta name="description" content="@yield('meta_description', 'Discover valuable resources, tools, and learning materials.')"/>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- Icons -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://cdn.tailwindcss.com"></script>

        <!-- Styles -->
        <script src="https://cdn.tailwindcss.com"></script>
         <link rel="stylesheet" href="{{ asset('css/map.css') }}">
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            'sans': ['Inter', 'system-ui', 'sans-serif'],
                        },
                        colors: {
                            primary: {
                                50: '#eff6ff',
                                100: '#dbeafe',
                                200: '#bfdbfe',
                                300: '#93c5fd',
                                400: '#60a5fa',
                                500: '#3b82f6',
                                600: '#2563eb',
                                700: '#1d4ed8',
                                800: '#1e40af',
                                900: '#1e3a8a',
                            }
                        }
                    }
                }
            }
        </script>

        @stack('styles')
    </head>

    <body class="bg-gray-50 font-sans antialiased">
        <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                    <!-- Logo -->
                <div class="flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center">
                        <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-graduation-cap text-white text-sm"></i>
                        </div>
                        <span class="ml-3 text-xl font-bold text-gray-900">{{ config('app.name') }}</span>
                    </a>
                </div>

                    <!-- Main Navigation -->
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-8">
                        <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}">
                            <i class="fas fa-home mr-1"></i> Home
                        </a>
                        <a href="{{ route('resources.index') }}"
                               class="nav-link {{ request()->routeIs('resources.*') ? 'active' : '' }}">
                            <i class="fas fa-book mr-1"></i> Resources
                        </a>
                        <a href="{{ route('categories.index') }}"
                               class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                            <i class="fas fa-folder mr-1"></i> Categories
                        </a>

                            <!-- NEW: Enhanced Training Dropdown for Desktop -->
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" 
                                               class="nav-link {{ request()->routeIs('training.*') ? 'active' : '' }} inline-flex items-center">
                                <i class="fas fa-graduation-cap mr-1"></i> Training & Mentorships
                                <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button> 

                            <div x-show="open" @click.outside="open = false" x-transition
                                     class="absolute left-0 mt-2 w-72 bg-white rounded-xl shadow-lg py-2 z-50 border border-gray-200">

                                    <!-- Core Training Section -->
                                <!--div class="px-4 py-2 border-b border-gray-100">
                                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Training Programs</h3>
                                </div>
                                <a href="{{ url('training.moh') }}" 
                                       class="flex items-center px-4 py-3 text-sm hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                    <i class="fas fa-hospital w-5 text-blue-500 mr-3"></i>
                                    <div>
                                        <div class="font-medium">MOH Training</div>
                                        <div class="text-xs text-gray-500">Global training programs</div>
                                    </div>
                                </a>
                                <a href="{{ url('training.mentorship') }}" 
                                       class="flex items-center px-4 py-3 text-sm hover:bg-green-50 hover:text-green-700 transition-colors">
                                    <i class="fas fa-user-friends w-5 text-green-500 mr-3"></i>
                                    <div>
                                        <div class="font-medium">Mentorship Programs</div>
                                        <div class="text-xs text-gray-500">Facility-based mentorship</div>
                                    </div>
                                </a-->

                                    <!-- Interactive Dashboards Section -->
                                <div class="px-4 py-2 border-b border-t border-gray-100 mt-2">
                                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Interactive Analytics</h3>
                                </div>
                                <a href="{{ url('analytics/dashboard') }}" 
                                       class="flex items-center px-4 py-3 text-sm hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                    <i class="fas fa-map w-5 text-blue-500 mr-3"></i>
                                    <div>
                                        <div class="font-medium flex items-center">
                                                🗺️ Trainings & Mentorships
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">New</span>
                                        </div>
                                        <div class="text-xs text-gray-500">County-level drill-down with participant insights</div>
                                    </div>
                                </a>
                          

                                    <!-- Overview Section -->
                                <!--div class="border-t border-gray-100 mt-2">
                                    <a href="{{ url('training.index') }}" 
                                           class="flex items-center px-4 py-3 text-sm hover:bg-gray-50 transition-colors">
                                        <i class="fas fa-list w-5 text-gray-500 mr-3"></i>
                                        <div>
                                            <div class="font-medium">All Training Programs</div>
                                            <div class="text-xs text-gray-500">Complete training overview</div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <a href="{{ route('resources.browse') }}" class="nav-link">
                            <i class="fas fa-search mr-1"></i> Browse
                        </a-->
                    </div>
                </div>

                    <!-- Search Bar -->
                <div class="hidden lg:block flex-1 max-w-md mx-8">
                    <form action="{{ route('resources.search') }}" method="GET" class="relative">
                        <div class="relative">
                            <input type="text" name="q" value="{{ request('q') }}"
                                       placeholder="Search resources..."
                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                        </div>
                    </form>
                </div>

                    <!-- User Menu -->
                <div class="flex items-center space-x-4">
                    @auth
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center text-gray-700 hover:text-gray-900">
                                <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
                                    <span class="text-sm font-medium text-primary-600">
                                        {{ substr(auth()->user()->full_name, 0, 1) }}
                                    </span>
                                </div>
                                <i class="fas fa-chevron-down ml-1 text-xs"></i>
                            </button>

                            <div x-show="open" @click.away="open = false" x-transition
                                 class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user mr-2"></i> Profile
                                </a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-bookmark mr-2"></i> Bookmarks
                                </a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-download mr-2"></i> Downloads
                                </a>
                                <hr class="my-1">
                                <form method="POST" action="{{ url('admin/logout') }}">
                                    @csrf
                                    <button type="submit"
                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a href="{{ url('admin/login') }}" class="text-gray-700 hover:text-gray-900">
                            <i class="fas fa-sign-in-alt mr-1"></i> Admin
                        </a>
<!--                            <a href="{{ url('register') }}"
                               class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors">
                                <i class="fas fa-user-plus mr-1"></i> Register
                            </a>-->
                    @endauth
                </div>

                    <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button @click="mobileOpen = !mobileOpen" class="text-gray-700 hover:text-gray-900">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </div>

            <!-- Mobile Navigation -->
        <div x-show="mobileOpen" x-transition class="md:hidden bg-white border-t border-gray-200">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="{{ route('home') }}" class="mobile-nav-link">
                    <i class="fas fa-home mr-2"></i> Home
                </a>
                <a href="{{ route('resources.index') }}" class="mobile-nav-link">
                    <i class="fas fa-book mr-2"></i> Resources
                </a>
                <a href="{{ route('categories.index') }}" class="mobile-nav-link">
                    <i class="fas fa-folder mr-2"></i> Categories
                </a>
                <a href="{{ route('resources.browse') }}" class="mobile-nav-link">
                    <i class="fas fa-search mr-2"></i> Browse
                </a>
                    <!-- Training dropdown -->
                <div x-data="{ open:false }" class="relative">
                    <button @click="open = !open" class="inline-flex items-center gap-2 hover:text-indigo-700">
                            Training
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div x-cloak x-show="open" @click.outside="open=false" class="absolute right-0 mt-2 w-56 rounded-xl border bg-white shadow-lg p-2">
                        <a href="{{ url('training.moh') }}" class="block rounded-lg px-3 py-2 hover:bg-gray-50">MOH</a>
                        <a href="{{ url('training.mentorship') }}" class="block rounded-lg px-3 py-2 hover:bg-gray-50">Mentorship</a>
                        <div class="border-t my-2"></div>
                        <a href="{{ url('training.index') }}" class="block rounded-lg px-3 py-2 hover:bg-gray-50">All Training</a>
                        <a href="{{ url('training.heatmap') }}" class="block rounded-lg px-3 py-2 hover:bg-gray-50">MOH Heatmap</a>
                    </div>
                </div>
            </div>

                <!-- Mobile Search -->
            <div class="px-4 pb-4">
                <form action="{{ route('resources.search') }}" method="GET">
                    <input type="text" name="q" placeholder="Search resources..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </form>
            </div>
        </div>
    </nav>

        <!-- Breadcrumbs -->
    @if (!request()->routeIs('home'))
        <div class="bg-gray-100 border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="{{ route('home') }}" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-home"></i>
                            </a>
                        </li>
                        @yield('breadcrumbs')
                    </ol>
                </nav>
            </div>
        </div>
    @endif

        <!-- Page Header -->
    @hasSection('page_header')
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                @yield('page_header')
            </div>
        </div>
    @endif

        <!-- Flash Messages -->
    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative max-w-7xl mx-auto mt-4"
             role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times cursor-pointer"></i>
            </span>
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative max-w-7xl mx-auto mt-4"
             role="alert">
            <strong class="font-bold">There were some problems:</strong>
            <ul class="mt-2">
                @foreach ($errors->all() as $error)
                    <li>• {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

        <!-- Main Content -->
    <main class="min-h-screen">
        @yield('content')
    </main>

        <!-- Footer -->
    <footer class="bg-gray-900 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <!-- About -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">{{ config('app.name') }}</h3>
                    <p class="text-gray-400 mb-4">
                            Your comprehensive resource center for learning materials, tools, and knowledge sharing.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-facebook"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-linkedin"></i>
                        </a>
                    </div>
                </div>

                    <!-- Quick Links -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="{{ route('resources.index') }}" class="text-gray-400 hover:text-white">All
                        Resources</a></li>
                        <li><a href="{{ route('categories.index') }}"
                        class="text-gray-400 hover:text-white">Categories</a></li>
                        <li><a href="{{ route('resources.browse') }}"
                        class="text-gray-400 hover:text-white">Browse</a></li>
                        <li><a href="{{ route('feed') }}" class="text-gray-400 hover:text-white">RSS Feed</a></li>
                    </ul>
                </div>

                    <!-- Categories -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">Popular Categories</h3>
                    <ul class="space-y-2">
                        @foreach (\App\Models\ResourceCategory::active()->parent()->withCount('resources')->orderByDesc('resources_count')->limit(5)->get() as $category)
                            <li>
                                <a href="{{ route('resources.category', $category->slug) }}"
                                   class="text-gray-400 hover:text-white">
                                    {{ $category->name }} ({{ $category->resources_count }})
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                    <!-- Contact -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">Contact</h3>
                    <ul class="space-y-2 text-gray-400">
                        <li><i class="fas fa-envelope mr-2"></i> mnch@example.com</li>
                        <li><i class="fas fa-phone mr-2"></i> +254 700 000 000</li>
                        <li><i class="fas fa-map-marker-alt mr-2"></i> Nairobi 00100, City</li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            </div>
        </div>
    </footer>

        <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <script>
                        document.addEventListener('alpine:init', () => {
                            Alpine.data('app', () => ({
                                mobileOpen: false
                            }))
                        })
    </script>

    @stack('scripts')

    <style>
            .nav-link {
                @apply text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors;
            }

            .nav-link.active {
                @apply text-primary-600 bg-primary-50;
            }

            .mobile-nav-link {
                @apply text-gray-600 hover:text-gray-900 hover:bg-gray-50 block px-3 py-2 rounded-md text-base font-medium;
            }
    </style>
</body>

</html>
