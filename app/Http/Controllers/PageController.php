<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PageController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of pages.
     */
    public function index()
    {
        $this->authorize('viewAny', Page::class);

        $pages = Page::with(['creator', 'updater'])
            ->withCount('blocks')
            ->latest()
            ->get()
            ->map(fn($page) => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status,
                'published_at' => $page->published_at?->toDateTimeString(),
                'blocks_count' => $page->blocks_count,
                'created_by' => $page->creator ? [
                    'id' => $page->creator->id,
                    'name' => $page->creator->name,
                ] : null,
                'created_at' => $page->created_at->toDateTimeString(),
                'updated_at' => $page->updated_at->toDateTimeString(),
            ]);

        return Inertia::render('pages/index', [
            'pages' => $pages,
        ]);
    }

    /**
     * Show the form for creating a new page.
     */
    public function create()
    {
        $this->authorize('create', Page::class);

        return Inertia::render('pages/create');
    }

    /**
     * Store a newly created page in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Page::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'alpha_dash',
                Rule::unique('pages', 'slug')->where('tenant_id', auth()->user()->current_tenant_id),
            ],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'og_image' => ['nullable', 'string', 'max:255'],
        ]);

        // Auto-generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);

            // Ensure uniqueness within tenant
            $originalSlug = $validated['slug'];
            $counter = 1;
            while (Page::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        $validated['created_by'] = auth()->id();
        $validated['status'] = 'draft';

        $page = Page::create($validated);

        return redirect()->route('pages.edit', $page->id)
            ->with('success', 'Page created successfully!');
    }

    /**
     * Display the specified page.
     */
    public function show(Page $page)
    {
        $this->authorize('view', $page);

        $page->load(['blocks', 'creator', 'updater']);

        return Inertia::render('pages/show', [
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'content' => $page->content,
                'status' => $page->status,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'meta_keywords' => $page->meta_keywords,
                'og_image' => $page->og_image,
                'published_at' => $page->published_at?->toDateTimeString(),
                'blocks' => $page->blocks->map(fn($block) => [
                    'id' => $block->id,
                    'block_type' => $block->block_type,
                    'content' => $block->content,
                    'config' => $block->config,
                    'order' => $block->order,
                ]),
                'created_by' => $page->creator ? [
                    'id' => $page->creator->id,
                    'name' => $page->creator->name,
                    'email' => $page->creator->email,
                ] : null,
                'created_at' => $page->created_at->toDateTimeString(),
                'updated_at' => $page->updated_at->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified page.
     */
    public function edit(Page $page)
    {
        $this->authorize('update', $page);

        $page->load(['blocks' => fn($query) => $query->orderBy('order')]);

        return Inertia::render('pages/editor', [
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'content' => $page->content,
                'status' => $page->status,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'meta_keywords' => $page->meta_keywords,
                'og_image' => $page->og_image,
                'published_at' => $page->published_at?->toDateTimeString(),
                'blocks' => $page->blocks->map(fn($block) => [
                    'id' => $block->id,
                    'block_type' => $block->block_type,
                    'content' => $block->content,
                    'config' => $block->config,
                    'order' => $block->order,
                ]),
            ],
        ]);
    }

    /**
     * Update the specified page in storage.
     */
    public function update(Request $request, Page $page)
    {
        $this->authorize('update', $page);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'alpha_dash',
                Rule::unique('pages', 'slug')
                    ->where('tenant_id', $page->tenant_id)
                    ->ignore($page->id),
            ],
            'content' => ['nullable', 'array'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'og_image' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['updated_by'] = auth()->id();

        $page->update($validated);

        return back()->with('success', 'Page updated successfully!');
    }

    /**
     * Remove the specified page from storage.
     */
    public function destroy(Page $page)
    {
        $this->authorize('delete', $page);

        $page->delete();

        return redirect()->route('pages.index')
            ->with('success', 'Page deleted successfully!');
    }

    /**
     * Publish the specified page.
     */
    public function publish(Page $page)
    {
        $this->authorize('publish', $page);

        $page->publish();

        return back()->with('success', 'Page published successfully!');
    }

    /**
     * Unpublish the specified page.
     */
    public function unpublish(Page $page)
    {
        $this->authorize('publish', $page);

        $page->unpublish();

        return back()->with('success', 'Page unpublished successfully!');
    }

    /**
     * Create a version snapshot of the page.
     */
    public function createVersion(Page $page)
    {
        $this->authorize('update', $page);

        $version = $page->createVersion();

        return back()->with('success', "Version {$version->version_number} created successfully!");
    }
}
