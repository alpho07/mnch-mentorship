<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Resource Center') - {{ config('app.name') }}</title>
    <meta name="description" content="@yield('meta_description', 'Discover valuable resources, tools, and learning materials.')">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
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
                        <a href="{{ route('resources.browse') }}" class="nav-link">
                            <i class="fas fa-search mr-1"></i> Browse
                        </a>
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
                                <form method="POST" action="{{ url('logout') }}">
                                    @csrf
                                    <button type="submit"
                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a href="{{ url('login') }}" class="text-gray-700 hover:text-gray-900">
                            <i class="fas fa-sign-in-alt mr-1"></i> Login
                        </a>
                        <a href="{{ url('register') }}"
                            class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors">
                            <i class="fas fa-user-plus mr-1"></i> Register
                        </a>
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
                        <li><i class="fas fa-envelope mr-2"></i> info@example.com</li>
                        <li><i class="fas fa-phone mr-2"></i> +1 (555) 123-4567</li>
                        <li><i class="fas fa-map-marker-alt mr-2"></i> 123 Main St, City</li>
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
