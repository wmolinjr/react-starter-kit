import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import type { Media, MediaFilters } from '@/types/media';
import { router } from '@inertiajs/react';
import {
    Check,
    FileImage,
    FileVideo,
    File as FileIcon,
    Loader2,
    Search,
    Upload,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { MediaUpload } from './media-upload';

export interface MediaPickerProps {
    collection?: string;
    multiple?: boolean;
    accept?: string;
    maxSize?: number; // in MB
    value?: Media | Media[] | null;
    onChange: (media: Media | Media[] | null) => void;
    onError?: (error: string) => void;
    className?: string;
    triggerLabel?: string;
    triggerVariant?: 'default' | 'outline' | 'secondary' | 'ghost';
}

interface MediaListResponse {
    data: Media[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export function MediaPicker({
    collection = 'default',
    multiple = false,
    accept = 'image/*',
    maxSize = 10,
    value,
    onChange,
    onError,
    className,
    triggerLabel = 'Select Media',
    triggerVariant = 'outline',
}: MediaPickerProps) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [mediaList, setMediaList] = useState<Media[]>([]);
    const [selectedMedia, setSelectedMedia] = useState<Media[]>([]);
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [filters, setFilters] = useState<MediaFilters>({
        collection,
        search: '',
        type: undefined,
        sort_by: 'created_at',
        sort_direction: 'desc',
        per_page: 20,
    });

    // Initialize selected media from value prop
    useEffect(() => {
        if (value) {
            setSelectedMedia(Array.isArray(value) ? value : [value]);
        } else {
            setSelectedMedia([]);
        }
    }, [value]);

    // Fetch media list from API
    const fetchMedia = useCallback(
        async (page: number = 1) => {
            setLoading(true);
            try {
                const params = new URLSearchParams({
                    page: page.toString(),
                    per_page: (filters.per_page || 20).toString(),
                    ...(filters.collection && { collection: filters.collection }),
                    ...(filters.search && { search: filters.search }),
                    ...(filters.type && { type: filters.type }),
                    ...(filters.sort_by && { sort_by: filters.sort_by }),
                    ...(filters.sort_direction && {
                        sort_direction: filters.sort_direction,
                    }),
                });

                const response = await fetch(`/media?${params.toString()}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch media');
                }

                const data: MediaListResponse = await response.json();
                setMediaList(data.data);
                setCurrentPage(data.meta.current_page);
                setTotalPages(data.meta.last_page);
            } catch (error) {
                console.error('Error fetching media:', error);
                onError?.('Failed to load media library');
            } finally {
                setLoading(false);
            }
        },
        [filters, onError]
    );

    // Fetch media when dialog opens or filters change
    useEffect(() => {
        if (open) {
            fetchMedia(1);
        }
    }, [open, filters.collection, filters.search, filters.type, filters.sort_by]);

    // Toggle media selection
    const toggleMediaSelection = (media: Media) => {
        if (multiple) {
            const isSelected = selectedMedia.some((m) => m.id === media.id);
            if (isSelected) {
                setSelectedMedia(selectedMedia.filter((m) => m.id !== media.id));
            } else {
                setSelectedMedia([...selectedMedia, media]);
            }
        } else {
            setSelectedMedia([media]);
        }
    };

    // Check if media is selected
    const isMediaSelected = (media: Media) => {
        return selectedMedia.some((m) => m.id === media.id);
    };

    // Get media icon based on type
    const getMediaIcon = (media: Media) => {
        if (media.mime_type?.startsWith('image/')) return FileImage;
        if (media.mime_type?.startsWith('video/')) return FileVideo;
        return FileIcon;
    };

    // Handle confirm selection
    const handleConfirm = () => {
        if (multiple) {
            onChange(selectedMedia.length > 0 ? selectedMedia : null);
        } else {
            onChange(selectedMedia[0] || null);
        }
        setOpen(false);
    };

    // Handle upload complete
    const handleUploadComplete = (uploadedMedia: Media[]) => {
        // Add uploaded media to the list
        setMediaList((prev) => [...uploadedMedia, ...prev]);

        // Auto-select uploaded media
        if (multiple) {
            setSelectedMedia((prev) => [...prev, ...uploadedMedia]);
        } else {
            setSelectedMedia([uploadedMedia[0]]);
        }

        // Refresh the list
        fetchMedia(currentPage);
    };

    // Clear selection
    const handleClearSelection = () => {
        setSelectedMedia([]);
        onChange(null);
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant={triggerVariant} className={className}>
                    <Upload className="h-4 w-4 mr-2" />
                    {triggerLabel}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-4xl max-h-[80vh] overflow-hidden flex flex-col">
                <DialogHeader>
                    <DialogTitle>Media Library</DialogTitle>
                    <DialogDescription>
                        {multiple
                            ? 'Select one or more media files'
                            : 'Select a media file'}
                    </DialogDescription>
                </DialogHeader>

                <Tabs defaultValue="library" className="flex-1 flex flex-col overflow-hidden">
                    <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger value="library">Select from Library</TabsTrigger>
                        <TabsTrigger value="upload">Upload New</TabsTrigger>
                    </TabsList>

                    {/* Library Tab */}
                    <TabsContent
                        value="library"
                        className="flex-1 flex flex-col overflow-hidden mt-4"
                    >
                        {/* Filters */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            {/* Search */}
                            <div className="space-y-2">
                                <Label htmlFor="search">Search</Label>
                                <div className="relative">
                                    <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        id="search"
                                        placeholder="Search media..."
                                        value={filters.search || ''}
                                        onChange={(e) =>
                                            setFilters((prev) => ({
                                                ...prev,
                                                search: e.target.value,
                                            }))
                                        }
                                        className="pl-8"
                                    />
                                </div>
                            </div>

                            {/* Type Filter */}
                            <div className="space-y-2">
                                <Label htmlFor="type">Type</Label>
                                <Select
                                    value={filters.type || 'all'}
                                    onValueChange={(value) =>
                                        setFilters((prev) => ({
                                            ...prev,
                                            type:
                                                value === 'all'
                                                    ? undefined
                                                    : (value as
                                                          | 'images'
                                                          | 'videos'
                                                          | 'documents'),
                                        }))
                                    }
                                >
                                    <SelectTrigger id="type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Types</SelectItem>
                                        <SelectItem value="images">Images</SelectItem>
                                        <SelectItem value="videos">Videos</SelectItem>
                                        <SelectItem value="documents">Documents</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Sort */}
                            <div className="space-y-2">
                                <Label htmlFor="sort">Sort By</Label>
                                <Select
                                    value={filters.sort_by || 'created_at'}
                                    onValueChange={(value) =>
                                        setFilters((prev) => ({
                                            ...prev,
                                            sort_by: value as 'created_at' | 'name' | 'size',
                                        }))
                                    }
                                >
                                    <SelectTrigger id="sort">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="created_at">Date Created</SelectItem>
                                        <SelectItem value="name">Name</SelectItem>
                                        <SelectItem value="size">Size</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Media Grid */}
                        <div className="flex-1 overflow-y-auto border rounded-lg p-4">
                            {loading ? (
                                <div className="flex items-center justify-center h-64">
                                    <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
                                </div>
                            ) : mediaList.length === 0 ? (
                                <div className="flex flex-col items-center justify-center h-64 text-muted-foreground">
                                    <FileImage className="h-12 w-12 mb-4" />
                                    <p>No media found</p>
                                </div>
                            ) : (
                                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                                    {mediaList.map((media) => {
                                        const Icon = getMediaIcon(media);
                                        const isSelected = isMediaSelected(media);

                                        return (
                                            <button
                                                key={media.id}
                                                onClick={() => toggleMediaSelection(media)}
                                                className={cn(
                                                    'relative aspect-square rounded-lg overflow-hidden border-2 transition-all hover:scale-105',
                                                    isSelected
                                                        ? 'border-primary ring-2 ring-primary ring-offset-2'
                                                        : 'border-transparent hover:border-primary/50'
                                                )}
                                            >
                                                {/* Media Preview */}
                                                {media.mime_type?.startsWith('image/') ? (
                                                    <img
                                                        src={`/media/${media.id}/url`}
                                                        alt={media.name}
                                                        className="w-full h-full object-cover"
                                                    />
                                                ) : (
                                                    <div className="w-full h-full flex items-center justify-center bg-muted">
                                                        <Icon className="h-12 w-12 text-muted-foreground" />
                                                    </div>
                                                )}

                                                {/* Selection Indicator */}
                                                {isSelected && (
                                                    <div className="absolute top-2 right-2 bg-primary text-primary-foreground rounded-full p-1">
                                                        <Check className="h-4 w-4" />
                                                    </div>
                                                )}

                                                {/* Media Name */}
                                                <div className="absolute bottom-0 left-0 right-0 bg-black/60 text-white px-2 py-1">
                                                    <p className="text-xs truncate">{media.name}</p>
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        {/* Pagination */}
                        {totalPages > 1 && (
                            <div className="flex items-center justify-between mt-4">
                                <p className="text-sm text-muted-foreground">
                                    Page {currentPage} of {totalPages}
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={currentPage === 1 || loading}
                                        onClick={() => fetchMedia(currentPage - 1)}
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={currentPage === totalPages || loading}
                                        onClick={() => fetchMedia(currentPage + 1)}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </TabsContent>

                    {/* Upload Tab */}
                    <TabsContent value="upload" className="mt-4">
                        <MediaUpload
                            collection={collection}
                            accept={accept}
                            maxSize={maxSize}
                            multiple={multiple}
                            onUploadComplete={handleUploadComplete}
                            onError={onError}
                        />
                    </TabsContent>
                </Tabs>

                {/* Footer Actions */}
                <div className="flex items-center justify-between pt-4 border-t">
                    <div className="text-sm text-muted-foreground">
                        {selectedMedia.length > 0 ? (
                            <>
                                {selectedMedia.length} selected
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={handleClearSelection}
                                    className="ml-2"
                                >
                                    <X className="h-3 w-3 mr-1" />
                                    Clear
                                </Button>
                            </>
                        ) : (
                            'No media selected'
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleConfirm}
                            disabled={selectedMedia.length === 0}
                        >
                            Confirm Selection
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
