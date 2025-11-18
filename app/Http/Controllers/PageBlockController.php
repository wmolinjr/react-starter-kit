<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePageBlockRequest;
use App\Http\Requests\UpdatePageBlockRequest;
use App\Models\Page;
use App\Models\PageBlock;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class PageBlockController extends Controller
{
    use AuthorizesRequests;

    /**
     * Store a newly created block in storage.
     */
    public function store(StorePageBlockRequest $request, Page $page)
    {
        $validated = $request->validated();

        // Get the next order number
        $maxOrder = $page->blocks()->max('order') ?? -1;
        $validated['order'] = $maxOrder + 1;
        $validated['page_id'] = $page->id;

        $block = PageBlock::create($validated);

        return back()->with('success', 'Block added successfully!');
    }

    /**
     * Update the specified block in storage.
     */
    public function update(UpdatePageBlockRequest $request, Page $page, PageBlock $block)
    {
        // Ensure block belongs to page
        if ($block->page_id !== $page->id) {
            abort(404);
        }

        $validated = $request->validated();

        $block->update($validated);

        return back()->with('success', 'Block updated successfully!');
    }

    /**
     * Remove the specified block from storage.
     */
    public function destroy(Page $page, PageBlock $block)
    {
        $this->authorize('update', $page);

        // Ensure block belongs to page
        if ($block->page_id !== $page->id) {
            abort(404);
        }

        // Store the order to reorder remaining blocks
        $deletedOrder = $block->order;

        $block->delete();

        // Reorder blocks after the deleted one
        PageBlock::where('page_id', $page->id)
            ->where('order', '>', $deletedOrder)
            ->decrement('order');

        return back()->with('success', 'Block deleted successfully!');
    }

    /**
     * Move block up in order.
     */
    public function moveUp(Page $page, PageBlock $block)
    {
        $this->authorize('update', $page);

        // Ensure block belongs to page
        if ($block->page_id !== $page->id) {
            abort(404);
        }

        if ($block->moveUp()) {
            return back()->with('success', 'Block moved up successfully!');
        }

        return back()->with('error', 'Cannot move block up. It is already at the top.');
    }

    /**
     * Move block down in order.
     */
    public function moveDown(Page $page, PageBlock $block)
    {
        $this->authorize('update', $page);

        // Ensure block belongs to page
        if ($block->page_id !== $page->id) {
            abort(404);
        }

        if ($block->moveDown()) {
            return back()->with('success', 'Block moved down successfully!');
        }

        return back()->with('error', 'Cannot move block down. It is already at the bottom.');
    }

    /**
     * Reorder blocks based on provided order array.
     */
    public function reorder(Request $request, Page $page)
    {
        $this->authorize('update', $page);

        $validated = $request->validate([
            'blocks' => ['required', 'array'],
            'blocks.*.id' => ['required', 'exists:page_blocks,id'],
            'blocks.*.order' => ['required', 'integer', 'min:0'],
        ]);

        // Update each block's order
        foreach ($validated['blocks'] as $blockData) {
            $block = PageBlock::find($blockData['id']);

            // Ensure block belongs to this page
            if ($block && $block->page_id === $page->id) {
                $block->update(['order' => $blockData['order']]);
            }
        }

        return back()->with('success', 'Blocks reordered successfully!');
    }

    /**
     * Duplicate the specified block.
     */
    public function duplicate(Page $page, PageBlock $block)
    {
        $this->authorize('update', $page);

        // Ensure block belongs to page
        if ($block->page_id !== $page->id) {
            abort(404);
        }

        $duplicatedBlock = $block->duplicate();

        return back()->with('success', 'Block duplicated successfully!');
    }
}
