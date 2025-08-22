<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublishCollectionRequest;
use App\Http\Requests\StoreCollectionRequest;
use App\Http\Requests\UpdateCollectionRequest;
use App\Http\Resources\CollectionCollection;
use App\Http\Resources\CollectionResource;
use App\Http\Resources\VideoResource;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    /**
     * Display a listing of collections.
     */
    public function index(Request $request): CollectionCollection
    {
        $collections = Collection::with(['user.profile', 'tags'])
            ->when($request->user_id, function ($query, $userId) {
                $query->where('user_id', $userId);
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->featured, function ($query) {
                $query->where('is_featured', true);
            })
            ->when($request->public !== null, function ($query) use ($request) {
                $query->where('is_public', $request->boolean('public'));
            })
            ->when($request->layout, function ($query, $layout) {
                $query->where('layout', $layout);
            })
            ->when($request->tag_id, function ($query, $tagId) {
                $query->whereHas('tags', function ($q) use ($tagId) {
                    $q->where('tags.id', $tagId);
                });
            })
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return new CollectionCollection($collections);
    }

    /**
     * Store a newly created collection.
     */
    public function store(StoreCollectionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $collection = Collection::create($data);

        // Attach tags if provided
        if (isset($data['tags'])) {
            $collection->tags()->attach($data['tags']);
        }

        $collection->load(['user.profile', 'tags']);

        return response()->json([
            'message' => 'Collection created successfully',
            'collection' => new CollectionResource($collection),
        ], 201);
    }

    /**
     * Display the specified collection.
     */
    public function show(Request $request, Collection $collection): CollectionResource|JsonResponse
    {
        // Check if user can view the collection
        if (!$collection->is_public && $collection->user_id !== $request->user()?->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Increment view count for public collections
        if ($collection->is_public) {
            $collection->increment('view_count');
        }

        $collection->load(['user.profile', 'videos', 'tags']);

        return new CollectionResource($collection);
    }

    /**
     * Update the specified collection.
     */
    public function update(UpdateCollectionRequest $request, Collection $collection): JsonResponse
    {
        // Check if user owns the collection
        if ($collection->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validated();
        $collection->update($data);

        // Update tags if provided
        if (isset($data['tags'])) {
            $collection->tags()->sync($data['tags']);
        }

        $collection->load(['user.profile', 'tags']);

        return response()->json([
            'message' => 'Collection updated successfully',
            'collection' => new CollectionResource($collection),
        ]);
    }

    /**
     * Remove the specified collection.
     */
    public function destroy(Request $request, Collection $collection): JsonResponse
    {
        // Check if user owns the collection
        if ($collection->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $collection->delete();

        return response()->json([
            'message' => 'Collection deleted successfully',
        ]);
    }

    /**
     * Display collections for the current user.
     */
    public function myCollections(Request $request): CollectionCollection
    {
        $collections = Collection::with(['tags'])
            ->where('user_id', $request->user()->id)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->public !== null, function ($query) use ($request) {
                $query->where('is_public', $request->boolean('public'));
            })
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return new CollectionCollection($collections);
    }

    /**
     * Display public collections for a specific user.
     */
    public function userCollections(Request $request, int $userId): CollectionCollection
    {
        $collections = Collection::with(['tags'])
            ->where('user_id', $userId)
            ->where('is_public', true)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return new CollectionCollection($collections);
    }

    /**
     * Display videos for a specific collection.
     */
    public function videos(Request $request, Collection $collection): JsonResponse
    {
        // Check if user can view the collection
        if (!$collection->is_public) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $videos = $collection->videos()
            ->orderBy('collection_video.position', 'asc')
            ->orderBy('videos.created_at', 'asc')
            ->get();

        return response()->json([
            'data' => VideoResource::collection($videos),
        ]);
    }

    /**
     * Publish or unpublish a collection.
     */
    public function publish(PublishCollectionRequest $request, Collection $collection): JsonResponse
    {
        // Check if user owns the collection
        if ($collection->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validated();
        $isPublished = $data['is_published'];

        // Check if collection has videos before allowing publish
        if ($isPublished && $collection->videos()->count() === 0) {
            return response()->json([
                'message' => 'Cannot publish collection without videos',
                'errors' => [
                    'is_published' => ['A collection must have at least one video to be published.']
                ]
            ], 422);
        }

        $collection->update(['is_published' => $isPublished]);
        $collection->load(['user.profile', 'tags']);

        $action = $isPublished ? 'published' : 'unpublished';

        return response()->json([
            'message' => "Collection {$action} successfully",
            'collection' => new CollectionResource($collection),
        ]);
    }
}
