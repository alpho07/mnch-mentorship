<?php
namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\ResourceCategory;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ResourceController extends Controller
{
    /**
     * Display the homepage/landing page
     */
    public function home(): View
    {
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

        return view('frontend.home', compact(
            'featuredResources',
            'recentResources',
            'popularResources',
            'categories',
            'resourceTypes'
        ));
    }

    /**
     * Display a listing of resources
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

        // Sorting
        $sortBy = $request->get('sort', 'latest');
        match ($sortBy) {
            'popular' => $query->popular(),
            'oldest' => $query->oldest('published_at'),
            'title' => $query->orderBy('title'),
            'views' => $query->orderByDesc('view_count'),
            'downloads' => $query->orderByDesc('download_count'),
            default => $query->latest('published_at'),
        };

        $resources = $query->paginate(12)->withQueryString();

        // Get filter options with access control
        $categories = ResourceCategory::active()
            ->withCount(['resources' => function($q) {
                $q->published();
                if (auth()->check()) {
                    $q->accessibleTo(auth()->user());
                } else {
                    $q->public();
                }
            }])
            ->orderBy('name')
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
            ->orderBy('name')
            ->get();

        $popularTags = Tag::popular(20)->get();

        return view('frontend.resources.index', compact(
            'resources',
            'categories',
            'resourceTypes',
            'popularTags'
        ));
    }

    /**
     * Display the specified resource - Enhanced security
     */
    public function show(Request $request, Resource $resource)
    {
        // Check published status first
        if ($resource->status !== 'published') {
            abort(404);
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
        $resource->incrementViews($user, $request->ip());

        // Get related resources with same access control
        $relatedQuery = Resource::published()
            ->where('id', '!=', $resource->id)
            ->where(function ($query) use ($resource) {
                $query->where('category_id', $resource->category_id)
                      ->orWhereHas('tags', fn($q) => $q->whereIn('tags.id', $resource->tags->pluck('id')));
            })
            ->with(['resourceType', 'category', 'author']);

        if ($user) {
            $relatedQuery->accessibleTo($user);
        } else {
            $relatedQuery->public();
        }

        $relatedResources = $relatedQuery->limit(4)->get();

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
     * Download a resource file - Enhanced security
     */
    public function download(Resource $resource)
    {
        // Check access permissions
        $user = auth()->user();
        if (!$resource->canUserAccess($user)) {
            abort(403, 'You do not have permission to download this resource.');
        }

        // Check if resource is published
        if ($resource->status !== 'published') {
            abort(404, 'This resource is not available.');
        }

        // Check if resource is downloadable
        if (!$resource->is_downloadable || !$resource->file_path) {
            abort(404, 'This resource is not available for download.');
        }

        // Check if file exists
        if (!Storage::exists($resource->file_path)) {
            abort(404, 'The requested file could not be found.');
        }

        // Track download
        $resource->incrementDownloads($user);

        // Get file info
        $filename = basename($resource->file_path);
        $originalName = $resource->title . '.' . pathinfo($filename, PATHINFO_EXTENSION);

        return Storage::download($resource->file_path, $originalName);
    }

    /**
     * Display resources by type
     */
    public function byType(ResourceType $type, Request $request): View
    {
        $query = Resource::published()
            ->byType($type->slug)
            ->with(['resourceType', 'category', 'author', 'tags']);

        // Apply visibility filter
        $user = auth()->user();
        $query->accessibleTo($user);

        $resources = $query->latest('published_at')->paginate(12);

        return view('frontend.resources.by-type', compact('type', 'resources'));
    }

    /**
     * Display resources by tag
     */
    public function byTag(Tag $tag, Request $request): View
    {
        $query = Resource::published()
            ->whereHas('tags', fn($q) => $q->where('tags.id', $tag->id))
            ->with(['resourceType', 'category', 'author', 'tags']);

        // Apply visibility filter
        $user = auth()->user();
        $query->accessibleTo($user);

        $resources = $query->latest('published_at')->paginate(12);

        return view('frontend.resources.by-tag', compact('tag', 'resources'));
    }

    /**
     * Browse resources with advanced filtering
     */
    public function browse(Request $request): View
    {
        $query = Resource::published()
            ->with(['resourceType', 'category', 'author', 'tags'])
            ->select('resources.*');

        // Apply visibility filter
        $user = auth()->user();
        $query->accessibleTo($user);

        // Advanced filtering
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

        if ($request->filled('is_downloadable')) {
            $query->where('is_downloadable', true);
        }

        if ($request->filled('featured')) {
            $query->where('is_featured', true);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('date_from')) {
            $query->where('published_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('published_at', '<=', $request->date_to);
        }

        // Sorting
        $sortBy = $request->get('sort', 'latest');
        match ($sortBy) {
            'popular' => $query->popular(),
            'oldest' => $query->oldest('published_at'),
            'title' => $query->orderBy('title'),
            'views' => $query->orderByDesc('view_count'),
            'downloads' => $query->orderByDesc('download_count'),
            default => $query->latest('published_at'),
        };

        $resources = $query->paginate(12)->withQueryString();

        // Get filter options with access control
        $categories = ResourceCategory::active()
            ->with(['children' => function($q) use ($user) {
                $q->active()->withCount(['resources' => function($rq) use ($user) {
                    $rq->published()->accessibleTo($user);
                }]);
            }])
            ->withCount(['resources' => function($q) use ($user) {
                $q->published()->accessibleTo($user);
            }])
            ->orderBy('name')
            ->get();

        $resourceTypes = ResourceType::active()
            ->withCount(['resources' => function($q) use ($user) {
                $q->published()->accessibleTo($user);
            }])
            ->orderBy('name')
            ->get();

        $tags = Tag::withCount(['resources' => function($q) use ($user) {
                $q->published()->accessibleTo($user);
            }])
            ->orderBy('name')
            ->get();

        return view('frontend.resources.browse', compact(
            'resources',
            'categories',
            'resourceTypes',
            'tags'
        ));
    }
}