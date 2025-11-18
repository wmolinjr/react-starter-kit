<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /**
     * Display a listing of media files.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Media::class);

        $query = Media::query();

        // Filter by collection
        if ($request->has('collection')) {
            $query->where('collection_name', $request->collection);
        }

        // Filter by type (images, videos, documents)
        if ($request->has('type')) {
            match ($request->type) {
                'images' => $query->images(),
                'videos' => $query->videos(),
                'documents' => $query->documents(),
                default => null,
            };
        }

        // Search by name or filename
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $request->get('per_page', 24);
        $media = $query->paginate($perPage);

        return response()->json([
            'data' => $media->items(),
            'meta' => [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
            ],
        ]);
    }

    /**
     * Upload a new media file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create', Media::class);

        $request->validate([
            'file' => 'required|file|max:10240', // 10MB
            'collection' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'model_type' => 'nullable|string',
            'model_id' => 'nullable|integer',
        ]);

        try {
            $file = $request->file('file');
            $collection = $request->get('collection', 'default');
            $name = $request->get('name', $file->getClientOriginalName());

            // If model_type and model_id are provided, attach to that model
            if ($request->has('model_type') && $request->has('model_id')) {
                $modelClass = $request->model_type;
                $model = $modelClass::findOrFail($request->model_id);

                $media = $model
                    ->addMedia($file)
                    ->usingName($name)
                    ->toMediaCollection($collection);
            } else {
                // Create standalone media entry
                $path = $file->store("tenants/".auth()->user()->current_tenant_id.'/temp', 'tenant-media');

                $media = Media::create([
                    'tenant_id' => auth()->user()->current_tenant_id,
                    'collection_name' => $collection,
                    'name' => $name,
                    'file_name' => basename($path),
                    'mime_type' => $file->getMimeType(),
                    'disk' => 'tenant-media',
                    'size' => $file->getSize(),
                    'manipulations' => [],
                    'custom_properties' => [],
                    'generated_conversions' => [],
                    'responsive_images' => [],
                ]);
            }

            return response()->json([
                'message' => 'Media uploaded successfully',
                'data' => $media->fresh(),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload media',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified media.
     *
     * @param  \App\Models\Media  $media
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Media $media)
    {
        $this->authorize('view', $media);

        return response()->json([
            'data' => $media,
        ]);
    }

    /**
     * Update the specified media.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Media  $media
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Media $media)
    {
        $this->authorize('update', $media);

        $request->validate([
            'name' => 'nullable|string|max:255',
            'custom_properties' => 'nullable|array',
        ]);

        $media->update($request->only(['name', 'custom_properties']));

        return response()->json([
            'message' => 'Media updated successfully',
            'data' => $media->fresh(),
        ]);
    }

    /**
     * Remove the specified media.
     *
     * @param  \App\Models\Media  $media
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Media $media)
    {
        $this->authorize('delete', $media);

        try {
            $media->delete();

            return response()->json([
                'message' => 'Media deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete media',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download the specified media.
     *
     * @param  \App\Models\Media  $media
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function download(Media $media)
    {
        $this->authorize('download', $media);

        return response()->download($media->getPath(), $media->file_name);
    }

    /**
     * Get media URL (for conversion or original).
     *
     * @param  \App\Models\Media  $media
     * @param  string  $conversion
     * @return \Illuminate\Http\JsonResponse
     */
    public function url(Media $media, string $conversion = '')
    {
        $this->authorize('view', $media);

        return response()->json([
            'url' => $media->getUrl($conversion),
        ]);
    }

    /**
     * Bulk delete media files.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:media,id',
        ]);

        try {
            $media = Media::whereIn('id', $request->ids)->get();

            foreach ($media as $item) {
                $this->authorize('delete', $item);
                $item->delete();
            }

            return response()->json([
                'message' => count($request->ids).' media files deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete media files',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get media collections and their counts.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function collections()
    {
        $this->authorize('viewAny', Media::class);

        $collections = Media::select('collection_name')
            ->selectRaw('count(*) as count')
            ->groupBy('collection_name')
            ->get()
            ->pluck('count', 'collection_name');

        return response()->json([
            'data' => $collections,
        ]);
    }
}
