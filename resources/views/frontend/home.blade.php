@extends('layouts.app')

@section('title', 'Welcome to Resource Center')
@section('meta_description', 'Discover valuable resources, tools, and learning materials to enhance your knowledge and skills.')

@section('content')
<div x-data="homePage">
    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-primary-600 via-primary-700 to-primary-800 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="text-center">
                <h1 class="text-4xl md:text-6xl font-bold mb-6">
                    Discover Knowledge,<br>
                    <span class="text-primary-200">Share Resources</span>
                </h1>
                <p class="text-xl md:text-2xl text-primary-100 mb-8 max-w-3xl mx-auto">
                    Access thousands of educational resources, tools, and materials curated for learners and professionals.
                </p>
                <!-- Hero Search -->
                <div class="max-w-2xl mx-auto mb-8">
                    <form action="{{ route('resources.search') }}" method="GET" class="relative">
                        <input type="text"
                            name="q"
                            placeholder="What would you like to learn today?"
                            class="w-full px-6 py-4 text-lg rounded-full border-0 text-gray-900 shadow-lg focus:ring-4 focus:ring-primary-300 focus:outline-none">
                        <button type="submit" class="absolute right-2 top-2 bg-primary-600 text-white px-6 py-2 rounded-full hover:bg-primary-700 transition-colors">
                            <i class="fas fa-search mr-2"></i> Search
                        </button>
                    </form>
                </div>
                <!-- Quick Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto">
                    <div class="bg-white/10 backdrop-blur rounded-lg p-4">
                        <div class="text-2xl md:text-3xl font-bold">{{ number_format(\App\Models\Resource::published()->count()) }}</div>
                        <div class="text-primary-200 text-sm">Resources</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur rounded-lg p-4">
                        <div class="text-2xl md:text-3xl font-bold">{{ number_format($categories->count()) }}</div>
                        <div class="text-primary-200 text-sm">Categories</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur rounded-lg p-4">
                        <div class="text-2xl md:text-3xl font-bold">{{ number_format(\App\Models\User::count()) }}</div>
                        <div class="text-primary-200 text-sm">Users</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur rounded-lg p-4">
                        <div class="text-2xl md:text-3xl font-bold">{{ number_format(\App\Models\ResourceDownload::count()) }}</div>
                        <div class="text-primary-200 text-sm">Downloads</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Resources -->
    @if($featuredResources->count() > 0)
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-star text-yellow-500 mr-3"></i>
                    Featured Resources
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Hand-picked resources that our community loves most
                </p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($featuredResources as $resource)
                    @include('components.resource-card', ['resource' => $resource, 'featured' => true])
                @endforeach
            </div>
            <div class="text-center mt-12">
                <a href="{{ route('resources.index', ['featured' => 1]) }}" class="inline-flex items-center px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                    <i class="fas fa-star mr-2"></i>
                    View All Featured Resources
                </a>
            </div>
        </div>
    </section>
    @endif

    <!-- Categories Grid -->
    <section class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-folder-open text-primary-600 mr-3"></i>
                    Explore Categories
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Browse resources organized by topics and subjects
                </p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach($categories as $category)
                <a href="{{ route('resources.category', $category->slug) }}"
                    class="group bg-white rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 p-6 border border-gray-200 hover:border-primary-300">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center group-hover:bg-primary-200 transition-colors">
                            <i class="{{ $category->icon ?? 'fas fa-folder' }} text-primary-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-semibold text-gray-900 group-hover:text-primary-600 transition-colors">
                                {{ $category->name }}
                            </h3>
                            <p class="text-sm text-gray-500">{{ $category->resources_count }} resources</p>
                        </div>
                    </div>
                    @if($category->description)
                        <p class="text-gray-600 text-sm mb-4">{{ Str::limit($category->description, 100) }}</p>
                    @endif
                    @if($category->children->count() > 0)
                        <div class="flex flex-wrap gap-2">
                            @foreach($category->children->take(3) as $child)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    {{ $child->name }}
                                </span>
                            @endforeach
                            @if($category->children->count() > 3)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    +{{ $category->children->count() - 3 }} more
                                </span>
                            @endif
                        </div>
                    @endif
                </a>
                @endforeach
            </div>
            <div class="text-center mt-12">
                <a href="{{ route('categories.index') }}" class="inline-flex items-center px-6 py-3 bg-white text-primary-600 border border-primary-600 rounded-lg hover:bg-primary-50 transition-colors">
                    <i class="fas fa-folder mr-2"></i>
                    View All Categories
                </a>
            </div>
        </div>
    </section>

    <!-- Resource Types -->
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-shapes text-primary-600 mr-3"></i>
                    Resource Types
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Find the perfect format for your learning style
                </p>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-6">
                @foreach($resourceTypes as $type)
                <a href="{{ route('resources.type', $type->slug) }}"
                    class="group text-center p-6 bg-gray-50 rounded-xl hover:bg-primary-50 transition-all duration-300 hover:shadow-lg">
                    <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4 group-hover:bg-primary-100 transition-colors shadow-sm">
                        <i class="{{ $type->icon ?? 'fas fa-file' }} text-2xl text-primary-600"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 group-hover:text-primary-600 transition-colors">
                        {{ $type->name }}
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">{{ $type->resources_count }} items</p>
                </a>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Recent Resources -->
    @if($recentResources->count() > 0)
    <section class="py-16 bg-gradient-to-br from-gray-50 to-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-12">
                <div>
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-clock text-green-600 mr-3"></i>
                        Recently Added
                    </h2>
                    <p class="text-xl text-gray-600">
                        Fresh content added to our collection
                    </p>
                    <div class="w-24 h-1 bg-green-600 mt-4 rounded-full"></div>
                </div>
                <a href="{{ route('resources.index', ['sort' => 'latest']) }}"
                    class="hidden md:inline-flex items-center px-6 py-3 text-green-600 hover:text-green-700 font-medium border-2 border-green-600 rounded-xl hover:bg-green-50 transition-all duration-300">
                    View All <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($recentResources as $resource)
                    @include('components.resource-card-compact', ['resource' => $resource])
                @endforeach
            </div>
            <div class="text-center mt-8 md:hidden">
                <a href="{{ route('resources.index', ['sort' => 'latest']) }}"
                    class="inline-flex items-center px-8 py-4 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-all duration-300 transform hover:scale-105 font-semibold shadow-lg">
                    <i class="fas fa-clock mr-2"></i>
                    View All Recent
                </a>
            </div>
        </div>
    </section>
    @endif

    <!-- Popular Resources -->
    @if($popularResources->count() > 0)
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-12">
                <div>
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-fire text-red-500 mr-3"></i>
                        Trending Now
                    </h2>
                    <p class="text-xl text-gray-600">
                        Most popular resources this month
                    </p>
                    <div class="w-24 h-1 bg-red-500 mt-4 rounded-full"></div>
                </div>
                <a href="{{ route('resources.index', ['sort' => 'popular']) }}"
                    class="hidden md:inline-flex items-center px-6 py-3 text-red-600 hover:text-red-700 font-medium border-2 border-red-600 rounded-xl hover:bg-red-50 transition-all duration-300">
                    View All <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($popularResources as $resource)
                    @include('components.resource-card', ['resource' => $resource])
                @endforeach
            </div>
            <div class="text-center mt-8 md:hidden">
                <a href="{{ route('resources.index', ['sort' => 'popular']) }}"
                    class="inline-flex items-center px-8 py-4 bg-red-500 text-white rounded-xl hover:bg-red-600 transition-all duration-300 transform hover:scale-105 font-semibold shadow-lg">
                    <i class="fas fa-fire mr-2"></i>
                    View All Popular
                </a>
            </div>
        </div>
    </section>
    @endif

    <!-- Newsletter & Community -->
    <section class="py-16 bg-gradient-to-r from-purple-600 to-indigo-600">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 md:p-12">
                <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">
                    Stay Updated with Latest Resources
                </h2>
                <p class="text-xl text-purple-100 mb-8">
                    Get weekly updates on new resources, featured content, and learning opportunities
                </p>
                <form class="max-w-md mx-auto mb-8" x-data="{ email: '', subscribed: false }">
                    <div class="flex gap-3">
                        <input type="email"
                            x-model="email"
                            placeholder="Enter your email address"
                            class="flex-1 px-4 py-3 rounded-xl border-0 text-gray-900 focus:ring-4 focus:ring-white/30 focus:outline-none">
                        <button type="submit"
                            @click.prevent="subscribed = true; email = ''"
                            class="px-6 py-3 bg-white text-purple-600 rounded-xl hover:bg-gray-100 transition-all duration-300 transform hover:scale-105 font-semibold shadow-lg">
                            Subscribe
                        </button>
                    </div>
                    <p x-show="subscribed" x-transition class="text-purple-100 text-sm mt-2">
                        ✓ Thank you for subscribing!
                    </p>
                </form>
                <div class="flex items-center justify-center space-x-6 text-purple-100">
                    <div class="flex items-center">
                        <i class="fas fa-users mr-2"></i>
                        <span class="text-sm">Join {{ number_format(\App\Models\User::count()) }}+ learners</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-envelope mr-2"></i>
                        <span class="text-sm">Weekly updates</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-times mr-2"></i>
                        <span class="text-sm">No spam</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-quote-left text-primary-600 mr-3"></i>
                    What Our Community Says
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Hear from learners who have transformed their skills with our resources
                </p>
                <div class="w-24 h-1 bg-primary-600 mx-auto mt-4 rounded-full"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Testimonial 1 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center mb-4">
                        <div class="flex text-yellow-400">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i>
                            <i class="fas fa-star"></i><i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <blockquote class="text-gray-700 mb-6">
                        "The quality of resources here is outstanding. I've been able to advance my career significantly thanks to the comprehensive materials available."
                    </blockquote>
                    <div class="flex items-center">
                        <img src="https://ui-avatars.com/api/?name=Sarah+Johnson&size=48&background=3b82f6&color=ffffff" alt="Sarah Johnson" class="w-12 h-12 rounded-full mr-4">
                        <div>
                            <div class="font-semibold text-gray-900">Sarah Johnson</div>
                            <div class="text-sm text-gray-500">Software Developer</div>
                        </div>
                    </div>
                </div>
                <!-- Testimonial 2 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center mb-4">
                        <div class="flex text-yellow-400">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i>
                            <i class="fas fa-star"></i><i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <blockquote class="text-gray-700 mb-6">
                        "Amazing platform! The categorization makes it so easy to find exactly what I need. The download feature is a game-changer for offline learning."
                    </blockquote>
                    <div class="flex items-center">
                        <img src="https://ui-avatars.com/api/?name=Michael+Chen&size=48&background=10b981&color=ffffff" alt="Michael Chen" class="w-12 h-12 rounded-full mr-4">
                        <div>
                            <div class="font-semibold text-gray-900">Michael Chen</div>
                            <div class="text-sm text-gray-500">Data Scientist</div>
                        </div>
                    </div>
                </div>
                <!-- Testimonial 3 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center mb-4">
                        <div class="flex text-yellow-400">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i>
                            <i class="fas fa-star"></i><i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <blockquote class="text-gray-700 mb-6">
                        "The community aspect with comments and discussions really enhances the learning experience. It's like having study buddies everywhere!"
                    </blockquote>
                    <div class="flex items-center">
                        <img src="https://ui-avatars.com/api/?name=Emily+Rodriguez&size=48&background=f59e0b&color=ffffff" alt="Emily Rodriguez" class="w-12 h-12 rounded-full mr-4">
                        <div>
                            <div class="font-semibold text-gray-900">Emily Rodriguez</div>
                            <div class="text-sm text-gray-500">UX Designer</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action (Footer) -->
    <section class="py-16 bg-gradient-to-r from-primary-600 to-primary-700 relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000">
                <defs>
                    <pattern id="cta-grid" width="100" height="100" patternUnits="userSpaceOnUse">
                        <circle cx="50" cy="50" r="2" fill="currentColor"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#cta-grid)"/>
            </svg>
        </div>
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 relative">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-6">
                Ready to Start Learning?
            </h2>
            <p class="text-xl text-primary-100 mb-8 max-w-2xl mx-auto">
                Join thousands of learners and access our complete resource library. Start your learning journey today!
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                @guest
                <a href="{{ url('register') }}"
                    class="inline-flex items-center px-8 py-4 bg-white text-primary-600 rounded-xl hover:bg-gray-50 transition-all duration-300 transform hover:scale-105 font-semibold shadow-xl">
                    <i class="fas fa-user-plus mr-2"></i>
                    Get Started Free
                </a>
                <a href="{{ route('resources.index') }}"
                    class="inline-flex items-center px-8 py-4 bg-primary-500 text-white rounded-xl hover:bg-primary-400 transition-all duration-300 transform hover:scale-105 font-semibold border-2 border-primary-400 shadow-xl">
                    <i class="fas fa-book mr-2"></i>
                    Browse Resources
                </a>
                @else
                <a href="{{ route('resources.index') }}"
                    class="inline-flex items-center px-8 py-4 bg-white text-primary-600 rounded-xl hover:bg-gray-50 transition-all duration-300 transform hover:scale-105 font-semibold shadow-xl">
                    <i class="fas fa-book mr-2"></i>
                    Explore Resources
                </a>
                <a href="{{ route('resources.browse') }}"
                    class="inline-flex items-center px-8 py-4 bg-primary-500 text-white rounded-xl hover:bg-primary-400 transition-all duration-300 transform hover:scale-105 font-semibold border-2 border-primary-400 shadow-xl">
                    <i class="fas fa-search mr-2"></i>
                    Advanced Search
                </a>
                @endguest
            </div>
            <!-- Trust indicators -->
            <div class="mt-12 flex items-center justify-center space-x-8 text-primary-200">
                <div class="flex items-center">
                    <i class="fas fa-shield-alt mr-2"></i>
                    <span class="text-sm">Secure & Safe</span>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-mobile-alt mr-2"></i>
                    <span class="text-sm">Mobile Friendly</span>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-cloud-download-alt mr-2"></i>
                    <span class="text-sm">Offline Access</span>
                </div>
            </div>
        </div>
    </section>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('homePage', () => ({
            init() { this.animateCounters(); },
            animateCounters() {
                const counters = document.querySelectorAll('[data-count]');
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const counter = entry.target;
                            const target = parseInt(counter.dataset.count);
                            let current = 0;
                            const increment = target / 50;
                            const updateCounter = () => {
                                current += increment;
                                if (current < target) {
                                    counter.textContent = Math.floor(current).toLocaleString();
                                    requestAnimationFrame(updateCounter);
                                } else {
                                    counter.textContent = target.toLocaleString();
                                }
                            };
                            updateCounter();
                            observer.unobserve(counter);
                        }
                    });
                });
                counters.forEach(counter => observer.observe(counter));
            }
        }))
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // Add scroll-triggered animations
    const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade-in-up');
            }
        });
    }, observerOptions);
    document.querySelectorAll('section').forEach(section => observer.observe(section));
</script>
@endpush

@push('styles')
<style>
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px);}
        to   { opacity: 1; transform: translateY(0);}
    }
    .animate-fade-in-up { animation: fadeInUp 0.6s ease-out forwards; }
    .gradient-text {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .hover-lift:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);}
    .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;}
    .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;}
</style>
@endpush
@endsection
