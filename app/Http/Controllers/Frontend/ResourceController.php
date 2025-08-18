<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\ResourceCategory;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;


class ResourceController extends Controller
{
    /**
     * Display the homepage/landing page with featured content
     */
    public function home(): View
    {
        // Cache the home page data for better performance
        $cacheKey = 'homepage_data_' . (auth()->check() ? auth()->id() : 'guest');
        
        $data = Cache::remember($cacheKey, 300, function () { // 5 minutes cache
            // Apply consistent access control for authenticated users
            $baseQuery = Resource::published()->with(['resourceType', 'category', 'author', 'tags']);
            
            if (auth()->check()) {
                $baseQuery->accessibleTo(auth()->user());
            } else {
                $baseQuery->public();
            }

            $featuredResources = (clone $baseQuery)->featured()->limit(6)->get();
            $recentResources = (clone $baseQuery)->latest('published_at')->limit(8)->get();
            $popularResources = (clone $baseQuery)->popular(30)->limit(6)->get();

            // Categories and types should also respect access
            $categories = ResourceCategory::active()
                ->parent()
                ->withCount(['resources' => function($q) {
                    $q->published();
                    if (auth()->check()) {
                        $q->accessibleTo(auth()->user());
                    } else {
                        $q->public();
                    }
                }])
                ->orderBy('sort_order')
                ->get();

            $resourceTypes = ResourceType::active()
                ->withCount(['resources' => function($q) {
                    $q->published();
                    if (auth()->check()) {
                        $q->accessibleTo(auth()->user());
                    } else {
                        $q->public();
                    }
                }])
                ->orderBy('sort_order')
                ->get();

            return compact('featuredResources', 'recentResources', 'popularResources', 'categories', 'resourceTypes');
        });

        return view('frontend.home', $data);
    }

    /**
     * Display a listing of resources with filtering and search
     */
    public function index(Request $request): View
    {
        $query = Resource::published()
            ->with(['resourceType', 'category', 'author', 'tags']);

        // Apply visibility filter based on authentication
        if (auth()->check()) {
            $query->accessibleTo(auth()->user());
        } else {
            $query->public();
        }

        // Apply filters
        $this->applyFilters($query, $request);

        // Apply sorting
        $this->applySorting($query, $request);

        $resources = $query->paginate(12)->withQueryString();

        // Get filter options with access control
        $filterData = $this->getFilterData();

        return view('frontend.resources.index', array_merge(
            compact('resources'),
            $filterData
        ));
    }

    /**
     * Display the specified resource with enhanced security and tracking
     */
    public function show(Request $request, Resource $resource): View
    {
        // Check published status first
        if ($resource->status !== 'published') {
            abort(404, 'Resource not found or not published');
        }

        // Check access permissions
        $user = auth()->user();
        if (!$resource->canUserAccess($user)) {
            if (!$user) {
                return redirect()->route('login')
                    ->with('message', 'Please log in to access this resource.');
            }
            abort(403, 'You do not have permission to access this resource.');
        }

        // Load relationships efficiently
        $resource->load([
            'resourceType',
            'category',
            'author',
            'tags',
            'comments' => fn($q) => $q->approved()
                ->parent()
                ->latest()
                ->with(['user', 'replies' => fn($r) => $r->approved()->latest()->with('user')])
        ]);

        // Track view (only for legitimate access)
        $this->trackView($resource, $user, $request);

        // Get related resources with same access control
        $relatedResources = $this->getRelatedResources($resource, $user);

        // Get user interactions efficiently
        $userInteractions = [];
        if ($user) {
            $userInteractions = $user->resourceInteractions()
                ->where('resource_id', $resource->id)
                ->pluck('type')
                ->toArray();
        }

        return view('frontend.resources.show', compact(
            'resource',
            'relatedResources',
            'userInteractions'
        ));
    }

    /**
     * Download a resource file with comprehensive security and tracking
     */
    public function download(Resource $resource): StreamedResponse
    {
        // Comprehensive access and security checks
        $this->validateDownloadAccess($resource);

        // Check if file exists and is valid
        if (!$resource->hasValidFile()) {
            abort(404, 'The requested file could not be found or is corrupted.');
        }

        // Track download with detailed logging
        $this->trackDownload($resource);

        // Generate secure filename
        $filename = $this->generateSecureFilename($resource);

        // Set appropriate headers for security and performance
        $headers = [
            'Content-Type' => Storage::disk('resources')->mimeType($resource->file_path),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
        ];

        // Stream the file for better memory usage with large files
        return response()->stream(function () use ($resource) {
            $stream = Storage::disk('resources')->readStream($resource->file_path);
            
            if (!$stream) {
                abort(500, 'Unable to read file');
            }
            
            // Stream in chunks for better memory management
            while (!feof($stream)) {
                echo fread($stream, 8192); // 8KB chunks
                flush();
            }
            
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }

    /**
     * Preview a resource file (for PDFs, images, videos, etc.)
     */
    public function preview(Resource $resource): StreamedResponse
    {
        // Access checks
        if (!$resource->canUserAccess(auth()->user()) || $resource->status !== 'published') {
            abort(403, 'You do not have permission to preview this resource.');
        }

        if (!$resource->hasValidFile()) {
            abort(404, 'File not found or corrupted');
        }

        if (!$resource->isPreviewable()) {
            abort(400, 'This file type cannot be previewed');
        }

        // Track view for analytics
        $this->trackView($resource, auth()->user(), request(), 'preview');

        $mimeType = Storage::disk('resources')->mimeType($resource->file_path);
        $filename = basename($resource->file_path);

        return response()->stream(function () use ($resource) {
            $stream = Storage::disk('resources')->readStream($resource->file_path);
            
            if (!$stream) {
                abort(500, 'Unable to read file');
            }
            
            fpassthru($stream);
            
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Get resource thumbnail/featured image with proper caching
     */
    public function thumbnail(Resource $resource): Response
    {
        // Featured images are public, but check if resource is accessible
        if ($resource->status !== 'published') {
            return $this->getPlaceholderImage();
        }

        // Check if featured image exists
        if (!$resource->featured_image || !Storage::disk('thumbnails')->exists($resource->featured_image)) {
            return $this->getPlaceholderImage();
        }

        $path = Storage::disk('thumbnails')->path($resource->featured_image);
        $mimeType = Storage::disk('thumbnails')->mimeType($resource->featured_image);
        
        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=86400', // Cache for 1 day
            'ETag' => md5_file($path),
        ]);
    }

    /**
     * Display resources by type with enhanced filtering
     */
    public function byType(ResourceType $type, Request $request): View
    {
        $query = Resource::published()
            ->byType($type->slug)
            ->with(['resourceType', 'category', 'author', 'tags']);

        // Apply visibility filter
        $user = auth()->user();
        if ($user) {
            $query->accessibleTo($user);
        } else {
            $query->public();
        }

        // Apply additional filters
        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $resources = $query->paginate(12)->withQueryString();

        return view('frontend.resources.by-type', compact('type', 'resources'));
    }

    /**
     * Display resources by tag with related suggestions
     */
    public function byTag(Tag $tag, Request $request): View
    {
        $query = Resource::published()
            ->whereHas('tags', fn($q) => $q->where('tags.id', $tag->id))
            ->with(['resourceType', 'category', 'author', 'tags']);

        // Apply visibility filter
        $user = auth()->user();
        if ($user) {
            $query->accessibleTo($user);
        } else {
            $query->public();
        }

        // Apply sorting
        $this->applySorting($query, $request);

        $resources = $query->paginate(12)->withQueryString();

        // Get related tags for suggestions
        $relatedTags = $this->getRelatedTags($tag, $user);

        return view('frontend.resources.by-tag', compact('tag', 'resources', 'relatedTags'));
    }

    /**
     * Browse resources with advanced filtering capabilities
     */
    public function browse(Request $request): View
    {
        $query = Resource::published()
            ->with(['resourceType', 'category', 'author', 'tags'])
            ->select('resources.*');

        // Apply visibility filter
        $user = auth()->user();
        if ($user) {
            $query->accessibleTo($user);
        } else {
            $query->public();
        }

        // Advanced filtering
        $this->applyAdvancedFilters($query, $request);
        $this->applySorting($query, $request);

        $resources = $query->paginate(12)->withQueryString();

        // Get comprehensive filter data
        $filterData = $this->getAdvancedFilterData($user);

        return view('frontend.resources.browse', array_merge(
            compact('resources'),
            $filterData
        ));
    }

    /**
     * Get user's bookmarked resources
     */
    public function bookmarks(Request $request): View
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login')->with('message', 'Please log in to view your bookmarks.');
        }

        $query = Resource::published()
            ->whereHas('interactions', function($q) use ($user) {
                $q->where('user_id', $user->id)->where('type', 'bookmark');
            })
            ->with(['resourceType', 'category', 'author', 'tags']);

        // Apply sorting
        $this->applySorting($query, $request);

        $resources = $query->paginate(12)->withQueryString();

        return view('frontend.resources.bookmarks', compact('resources'));
    }

    /**
     * Get user's download history
     */
    public function downloads(Request $request): View
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login')->with('message', 'Please log in to view your downloads.');
        }

        $downloads = $user->resourceDownloads()
            ->with(['resource' => function($q) {
                $q->with(['resourceType', 'category', 'author']);
            }])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('frontend.resources.downloads', compact('downloads'));
    }

    // === PRIVATE HELPER METHODS ===

    /**
     * Apply standard filters to resource query
     */
    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
        }

        if ($request->filled('type')) {
            $query->whereHas('resourceType', fn($q) => $q->where('slug', $request->type));
        }

        if ($request->filled('tag')) {
            $query->whereHas('tags', fn($q) => $q->where('slug', $request->tag));
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty_level', $request->difficulty);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('author')) {
            $query->whereHas('author', fn($q) => $q->where('id', $request->author));
        }

        if ($request->boolean('downloadable')) {
            $query->where('is_downloadable', true)->whereNotNull('file_path');
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }
    }

    /**
     * Apply advanced filters for browse page
     */
    private function applyAdvancedFilters($query, Request $request): void
    {
        // Standard filters
        $this->applyFilters($query, $request);

        // Multi-select filters
        if ($request->filled('categories')) {
            $query->whereIn('category_id', $request->categories);
        }

        if ($request->filled('types')) {
            $query->whereIn('resource_type_id', $request->types);
        }

        if ($request->filled('tags')) {
            $query->whereHas('tags', fn($q) => $q->whereIn('tags.id', $request->tags));
        }

        if ($request->filled('difficulty_levels')) {
            $query->whereIn('difficulty_level', $request->difficulty_levels);
        }

        // Date range filters
        if ($request->filled('date_from')) {
            $query->where('published_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('published_at', '<=', $request->date_to);
        }

        // File type filters
        if ($request->filled('file_types')) {
            $mimeTypes = $this->getFileTypeMimeMapping($request->file_types);
            $query->whereIn('file_type', $mimeTypes);
        }

        // Size filters
        if ($request->filled('min_size')) {
            $query->where('file_size', '>=', $request->min_size * 1024 * 1024); // Convert MB to bytes
        }

        if ($request->filled('max_size')) {
            $query->where('file_size', '<=', $request->max_size * 1024 * 1024);
        }
    }

    /**
     * Apply sorting to resource query
     */
    private function applySorting($query, Request $request): void
    {
        $sortBy = $request->get('sort', 'latest');
        
        match ($sortBy) {
            'popular' => $query->popular(),
            'oldest' => $query->oldest('published_at'),
            'title' => $query->orderBy('title'),
            'views' => $query->orderByDesc('view_count'),
            'downloads' => $query->orderByDesc('download_count'),
            'likes' => $query->orderByDesc('like_count'),
            'comments' => $query->withCount('comments')->orderByDesc('comments_count'),
            'size' => $query->orderByDesc('file_size'),
            'author' => $query->join('users', 'resources.author_id', '=', 'users.id')
                             ->orderBy('users.first_name'),
            default => $query->latest('published_at'),
        };
    }

    /**
     * Get filter data for resource listings
     */
    private function getFilterData(): array
    {
        $user = auth()->user();
        
        $categories = ResourceCategory::active()
            ->withCount(['resources' => function($q) use ($user) {
                $q->published();
                if ($user) {
                    $q->accessibleTo($user);
                } else {
                    $q->public();
                }
            }])
            ->having('resources_count', '>', 0)
            ->orderBy('name')
            ->get();

        $resourceTypes = ResourceType::active()
            ->withCount(['resources' => function($q) use ($user) {
                $q->published();
                if ($user) {
                    $q->accessibleTo($user);
                } else {
                    $q->public();
                }
            }])
            ->having('resources_count', '>', 0)
            ->orderBy('name')
            ->get();

        $popularTags = Tag::withCount(['resources' => function($q) use ($user) {
                $q->published();
                if ($user) {
                    $q->accessibleTo($user);
                } else {
                    $q->public();
                }
            }])
            ->having('resources_count', '>', 0)
            ->orderByDesc('resources_count')
            ->limit(20)
            ->get();

        return compact('categories', 'resourceTypes', 'popularTags');
    }

    /**
     * Get advanced filter data for browse page
     */
    private function getAdvancedFilterData(?User $user): array
    {
        $filterData = $this->getFilterData();

        // Add nested categories
        $filterData['categories'] = ResourceCategory::active()
            ->with(['children' => function($q) use ($user) {
                $q->active()->withCount(['resources' => function($rq) use ($user) {
                    $rq->published();
                    if ($user) {
                        $rq->accessibleTo($user);
                    } else {
                        $rq->public();
                    }
                }]);
            }])
            ->withCount(['resources' => function($q) use ($user) {
                $q->published();
                if ($user) {
                    $q->accessibleTo($user);
                } else {
                    $q->public();
                }
            }])
            ->having('resources_count', '>', 0)
            ->orderBy('name')
            ->get();

        // Add all tags for advanced filtering
        $filterData['tags'] = Tag::withCount(['resources' => function($q) use ($user) {
                $q->published();
                if ($user) {
                    $q->accessibleTo($user);
                } else {
                    $q->public();
                }
            }])
            ->having('resources_count', '>', 0)
            ->orderBy('name')
            ->get();

        return $filterData;
    }

    /**
     * Validate download access with comprehensive checks
     */
    private function validateDownloadAccess(Resource $resource): void
    {
        // Check access permissions
        if (!$resource->canUserAccess(auth()->user())) {
            abort(403, 'You do not have permission to download this resource.');
        }

        // Check if resource is published and downloadable
        if ($resource->status !== 'published') {
            abort(404, 'This resource is not available.');
        }

        if (!$resource->is_downloadable) {
            abort(403, 'This resource is not available for download.');
        }

        if (!$resource->file_path) {
            abort(404, 'No file is attached to this resource.');
        }
    }

    /**
     * Track resource view with detailed analytics
     */
    private function trackView(Resource $resource, ?User $user, Request $request, string $type = 'view'): void
    {
        // Prevent duplicate views from same user/IP in short time
        $cacheKey = "resource_view_{$resource->id}_" . ($user?->id ?? $request->ip());
        
        if (!Cache::has($cacheKey)) {
            $resource->incrementViews($user, $request->ip());
            Cache::put($cacheKey, true, 300); // 5 minutes
            
            // Enhanced analytics tracking
            if ($user) {
                activity()
                    ->performedOn($resource)
                    ->causedBy($user)
                    ->withProperties([
                        'type' => $type,
                        'referrer' => $request->header('referer'),
                        'user_agent' => $request->userAgent(),
                    ])
                    ->log('resource_viewed');
            }
        }
    }

    /**
     * Track download with comprehensive logging
     */
    private function trackDownload(Resource $resource): void
    {
        $user = auth()->user();
        
        // Increment download counter
        $resource->incrementDownloads($user);

        // Log detailed download activity
        activity()
            ->performedOn($resource)
            ->causedBy($user)
            ->withProperties([
                'resource_title' => $resource->title,
                'file_size' => $resource->file_size,
                'file_type' => $resource->file_type,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'referrer' => request()->header('referer'),
            ])
            ->log('resource_downloaded');
    }

    /**
     * Generate secure filename for downloads
     */
    private function generateSecureFilename(Resource $resource): string
    {
        $extension = $resource->getFileExtension();
        $baseName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $resource->title);
        $baseName = trim($baseName, '_');
        
        return $extension ? "{$baseName}.{$extension}" : $baseName;
    }

    /**
     * Get related resources using intelligent matching
     */
    private function getRelatedResources(Resource $resource, ?User $user, int $limit = 4)
    {
        $query = Resource::published()
            ->where('id', '!=', $resource->id)
            ->with(['resourceType', 'category', 'author']);

        // Apply access control
        if ($user) {
            $query->accessibleTo($user);
        } else {
            $query->public();
        }

        // Prioritize related content
        $query->where(function ($q) use ($resource) {
            // Same category gets highest priority
            $q->where('category_id', $resource->category_id)
              // Same resource type gets medium priority  
              ->orWhere('resource_type_id', $resource->resource_type_id)
              // Same tags get lower priority
              ->orWhereHas('tags', fn($tagQ) => $tagQ->whereIn('tags.id', $resource->tags->pluck('id')));
        });

        return $query->limit($limit)->get();
    }

    /**
     * Get related tags for tag suggestion
     */
    private function getRelatedTags(Tag $tag, ?User $user, int $limit = 10)
    {
        // Find tags that frequently appear together with the current tag
        $query = Tag::whereHas('resources', function($resourceQuery) use ($tag, $user) {
            $resourceQuery->published()
                ->whereHas('tags', fn($tagQuery) => $tagQuery->where('tags.id', $tag->id));
            
            if ($user) {
                $resourceQuery->accessibleTo($user);
            } else {
                $resourceQuery->public();
            }
        })
        ->where('id', '!=', $tag->id)
        ->withCount('resources')
        ->orderByDesc('resources_count');

        return $query->limit($limit)->get();
    }

    /**
     * Get placeholder image response
     */
    private function getPlaceholderImage(): Response
    {
        $placeholderPath = public_path('images/placeholder-resource.png');
        
        if (file_exists($placeholderPath)) {
            return response()->file($placeholderPath);
        }
        
        // Generate a simple SVG placeholder if no image exists
        $svg = '<svg width="400" height="300" xmlns="http://www.w3.org/2000/svg">
            <rect width="100%" height="100%" fill="#f3f4f6"/>
            <text x="50%" y="50%" text-anchor="middle" dy="0.3em" font-family="Arial" font-size="16" fill="#6b7280">
                No Image Available
            </text>
        </svg>';
        
        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    /**
     * Map file type names to MIME types
     */
    private function getFileTypeMimeMapping(array $fileTypes): array
    {
        $mapping = [
            'pdf' => ['application/pdf'],
            'document' => [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ],
            'presentation' => [
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation'
            ],
            'spreadsheet' => [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ],
            'image' => ['image/jpeg', 'image/png', 'image/gif'],
            'video' => ['video/mp4', 'video/mpeg'],
            'audio' => ['audio/mpeg', 'audio/wav'],
            'archive' => ['application/zip', 'application/x-rar-compressed'],
            'text' => ['text/plain', 'text/csv'],
        ];

        $mimeTypes = [];
        foreach ($fileTypes as $type) {
            if (isset($mapping[$type])) {
                $mimeTypes = array_merge($mimeTypes, $mapping[$type]);
            }
        }

        return $mimeTypes;
    }
}