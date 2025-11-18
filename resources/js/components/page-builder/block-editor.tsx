import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { BlockTemplate } from '@/types/blocks';
import type { PageBlock } from '@/types';
import { router } from '@inertiajs/react';
import { AlertCircle, Plus } from 'lucide-react';
import { useState, useEffect, useCallback } from 'react';
import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    DragEndEvent,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { BlockItem } from './block-item';
import { BlockLibrary, BLOCK_TEMPLATES } from './block-library';
import { BlockPreview } from './block-preview';
import { TextBlockForm } from './forms/text-block-form';
import { ImageBlockForm } from './forms/image-block-form';
import { HeroBlockForm } from './forms/hero-block-form';
import { CTABlockForm } from './forms/cta-block-form';
import { GalleryBlockForm } from './forms/gallery-block-form';
import { FeaturesBlockForm } from './forms/features-block-form';
import { TestimonialsBlockForm } from './forms/testimonials-block-form';

interface BlockEditorProps {
    pageId: number;
    blocks: PageBlock[];
    routePrefix?: string; // 'pages' or 'tenant.pages'
}

export function BlockEditor({
    pageId,
    blocks: initialBlocks,
    routePrefix = 'pages',
}: BlockEditorProps) {
    const [blocks, setBlocks] = useState<PageBlock[]>(initialBlocks);
    const [isLibraryOpen, setIsLibraryOpen] = useState(false);
    const [editingBlock, setEditingBlock] = useState<PageBlock | null>(null);
    const [deletingBlockId, setDeletingBlockId] = useState<number | null>(
        null
    );

    // Undo/Redo state
    const [history, setHistory] = useState<PageBlock[][]>([initialBlocks]);
    const [historyIndex, setHistoryIndex] = useState(0);

    // Drag and drop sensors
    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: {
                distance: 8, // 8px movimento necessário para iniciar drag
            },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    // Sync blocks with initialBlocks when they change
    useEffect(() => {
        setBlocks(initialBlocks);
    }, [initialBlocks]);

    // Add to history helper
    const addToHistory = useCallback((newBlocks: PageBlock[]) => {
        setHistory((prev) => {
            const newHistory = prev.slice(0, historyIndex + 1);
            return [...newHistory, newBlocks];
        });
        setHistoryIndex((prev) => prev + 1);
    }, [historyIndex]);

    // Undo function
    const undo = useCallback(() => {
        if (historyIndex > 0) {
            setHistoryIndex((prev) => prev - 1);
            setBlocks(history[historyIndex - 1]);
        }
    }, [historyIndex, history]);

    // Redo function
    const redo = useCallback(() => {
        if (historyIndex < history.length - 1) {
            setHistoryIndex((prev) => prev + 1);
            setBlocks(history[historyIndex + 1]);
        }
    }, [historyIndex, history]);

    // Keyboard shortcuts
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            // Ctrl+Z or Cmd+Z - Undo
            if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
                e.preventDefault();
                undo();
            }
            // Ctrl+Shift+Z or Cmd+Shift+Z or Ctrl+Y - Redo
            if (
                ((e.ctrlKey || e.metaKey) && e.key === 'z' && e.shiftKey) ||
                (e.ctrlKey && e.key === 'y')
            ) {
                e.preventDefault();
                redo();
            }
            // Ctrl+S or Cmd+S - Save (prevent default browser save)
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                // Form save is handled automatically by Inertia
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [undo, redo]);

    // Handle drag end
    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;

        if (over && active.id !== over.id) {
            const oldIndex = blocks.findIndex((b) => b.id === active.id);
            const newIndex = blocks.findIndex((b) => b.id === over.id);

            const newBlocks = arrayMove(blocks, oldIndex, newIndex);
            setBlocks(newBlocks);
            addToHistory(newBlocks);

            // Persist to backend
            router.post(
                `/pages/${pageId}/blocks/reorder`,
                {
                    blocks: newBlocks.map((block, index) => ({
                        id: block.id,
                        order: index,
                    })),
                },
                {
                    preserveScroll: true,
                }
            );
        }
    };

    // Add block
    const handleAddBlock = (template: BlockTemplate) => {
        router.post(
            `/pages/${pageId}/blocks`,
            {
                block_type: template.type,
                content: template.defaultContent,
                config: template.defaultConfig || {},
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsLibraryOpen(false);
                },
            }
        );
    };

    // Move block up
    const handleMoveUp = (blockId: number) => {
        router.post(
            `/pages/${pageId}/blocks/${blockId}/move-up`,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    // Move block down
    const handleMoveDown = (blockId: number) => {
        router.post(
            `/pages/${pageId}/blocks/${blockId}/move-down`,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    // Duplicate block
    const handleDuplicate = (blockId: number) => {
        router.post(
            `/pages/${pageId}/blocks/${blockId}/duplicate`,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    // Delete block
    const handleDelete = (blockId: number) => {
        setDeletingBlockId(blockId);
    };

    const confirmDelete = () => {
        if (!deletingBlockId) return;

        router.delete(
            `/pages/${pageId}/blocks/${deletingBlockId}`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDeletingBlockId(null);
                },
            }
        );
    };

    // Edit block
    const handleEdit = (block: PageBlock) => {
        setEditingBlock(block);
    };

    const handleSaveEdit = (updatedBlock: PageBlock) => {
        router.patch(
            `/pages/${pageId}/blocks/${updatedBlock.id}`,
            {
                block_type: updatedBlock.block_type,
                content: updatedBlock.content,
                config: updatedBlock.config || {},
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingBlock(null);
                },
            }
        );
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-2xl font-bold tracking-tight">
                        Page Content
                    </h2>
                    <p className="text-muted-foreground">
                        Build your page with blocks - Drag to reorder
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={undo}
                        disabled={historyIndex === 0}
                    >
                        Undo
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={redo}
                        disabled={historyIndex === history.length - 1}
                    >
                        Redo
                    </Button>
                    <Button onClick={() => setIsLibraryOpen(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Block
                    </Button>
                </div>
            </div>

            {/* Blocks List */}
            {blocks.length === 0 ? (
                <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        No blocks yet. Click "Add Block" to get started.
                    </AlertDescription>
                </Alert>
            ) : (
                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={handleDragEnd}
                >
                    <SortableContext
                        items={blocks.map((b) => b.id)}
                        strategy={verticalListSortingStrategy}
                    >
                        <div className="space-y-4">
                            {blocks.map((block, index) => (
                                <BlockItem
                                    key={block.id}
                                    block={block}
                                    isFirst={index === 0}
                                    isLast={index === blocks.length - 1}
                                    onEdit={handleEdit}
                                    onMoveUp={handleMoveUp}
                                    onMoveDown={handleMoveDown}
                                    onDuplicate={handleDuplicate}
                                    onDelete={handleDelete}
                                >
                                    <BlockPreview block={block} />
                                </BlockItem>
                            ))}
                        </div>
                    </SortableContext>
                </DndContext>
            )}

            {/* Block Library Sheet */}
            <Sheet open={isLibraryOpen} onOpenChange={setIsLibraryOpen}>
                <SheetContent>
                    <SheetHeader>
                        <SheetTitle>Block Library</SheetTitle>
                        <SheetDescription>
                            Choose a block to add to your page
                        </SheetDescription>
                    </SheetHeader>
                    <div className="mt-6">
                        <BlockLibrary onAddBlock={handleAddBlock} />
                    </div>
                </SheetContent>
            </Sheet>

            {/* Delete Confirmation Dialog */}
            <Dialog
                open={deletingBlockId !== null}
                onOpenChange={() => setDeletingBlockId(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Block</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this block? This
                            action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setDeletingBlockId(null)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            Delete
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            {/* Edit Block Dialog */}
            {editingBlock && (
                <Dialog
                    open={!!editingBlock}
                    onOpenChange={() => setEditingBlock(null)}
                >
                    <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>
                                Edit {BLOCK_TEMPLATES[editingBlock.block_type].label}
                            </DialogTitle>
                            <DialogDescription>
                                Make changes to your block content and settings
                            </DialogDescription>
                        </DialogHeader>
                        <div className="py-4">
                            {editingBlock.block_type === 'text' && (
                                <TextBlockForm
                                    block={editingBlock}
                                    onSave={handleSaveEdit}
                                    onCancel={() => setEditingBlock(null)}
                                />
                            )}
                            {editingBlock.block_type === 'image' && (
                                <ImageBlockForm
                                    block={editingBlock}
                                    onSave={handleSaveEdit}
                                    onCancel={() => setEditingBlock(null)}
                                />
                            )}
                            {editingBlock.block_type === 'hero' && (
                                <HeroBlockForm
                                    block={editingBlock}
                                    onSave={handleSaveEdit}
                                    onCancel={() => setEditingBlock(null)}
                                />
                            )}
                            {editingBlock.block_type === 'cta' && (
                                <CTABlockForm
                                    block={editingBlock}
                                    onSave={handleSaveEdit}
                                    onCancel={() => setEditingBlock(null)}
                                />
                            )}
                            {editingBlock.block_type === 'gallery' && (
                                <GalleryBlockForm
                                    block={editingBlock}
                                    onSave={handleSaveEdit}
                                    onCancel={() => setEditingBlock(null)}
                                />
                            )}
                            {editingBlock.block_type === 'features' && (
                                <FeaturesBlockForm
                                    block={editingBlock}
                                    onSave={handleSaveEdit}
                                    onCancel={() => setEditingBlock(null)}
                                />
                            )}
                            {editingBlock.block_type === 'testimonials' && (
                                <TestimonialsBlockForm
                                    block={editingBlock}
                                    onSave={handleSaveEdit}
                                    onCancel={() => setEditingBlock(null)}
                                />
                            )}
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </div>
    );
}
