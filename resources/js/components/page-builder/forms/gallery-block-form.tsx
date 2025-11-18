import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PageBlock } from '@/types';
import type { Media } from '@/types/media';
import { MediaPicker } from '@/components/media/media-picker';
import { LazyImage } from '@/components/media/lazy-image';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';

interface GalleryImage {
    media: Media;
    alt?: string;
    caption?: string;
}

interface GalleryBlockFormProps {
    block: PageBlock;
    onSave: (block: PageBlock) => void;
    onCancel: () => void;
}

export function GalleryBlockForm({ block, onSave, onCancel }: GalleryBlockFormProps) {
    const [images, setImages] = useState<GalleryImage[]>(
        block.content.images || []
    );
    const [columns, setColumns] = useState<string>(
        String(block.config?.columns || 3)
    );
    const [gap, setGap] = useState<string>(
        block.config?.gap || 'medium'
    );

    const handleMediaSelection = (media: Media | Media[] | null) => {
        if (!media) return;

        const mediaArray = Array.isArray(media) ? media : [media];
        const newImages: GalleryImage[] = mediaArray.map((m) => ({
            media: m,
            alt: m.name,
            caption: '',
        }));

        setImages([...images, ...newImages]);
    };

    const removeImage = (index: number) => {
        setImages(images.filter((_, i) => i !== index));
    };

    const updateImageAlt = (index: number, alt: string) => {
        const newImages = [...images];
        newImages[index] = { ...newImages[index], alt };
        setImages(newImages);
    };

    const updateImageCaption = (index: number, caption: string) => {
        const newImages = [...images];
        newImages[index] = { ...newImages[index], caption };
        setImages(newImages);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (images.length === 0) {
            alert('Please add at least one image');
            return;
        }

        onSave({
            ...block,
            content: {
                images: images.map((img) => ({
                    media_id: img.media.id,
                    media: img.media,
                    alt: img.alt || img.media.name,
                    caption: img.caption,
                })),
            },
            config: {
                columns: parseInt(columns),
                gap,
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <Label>
                        Gallery Images
                        <span className="text-destructive">*</span>
                    </Label>
                    <MediaPicker
                        collection="gallery-images"
                        accept="image/*"
                        multiple={true}
                        value={null}
                        onChange={handleMediaSelection}
                        triggerLabel="Add Images"
                        triggerVariant="outline"
                    />
                </div>

                {images.length === 0 ? (
                    <p className="text-sm text-muted-foreground text-center py-8 border-2 border-dashed rounded-lg">
                        No images selected. Click "Add Images" to get started.
                    </p>
                ) : (
                    <div className="space-y-4">
                        {images.map((image, index) => (
                            <div key={index} className="rounded-lg border p-4 space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium">Image {index + 1}</span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeImage(index)}
                                    >
                                        <Trash2 className="h-4 w-4 text-destructive" />
                                    </Button>
                                </div>

                                {/* Image Preview */}
                                <div className="rounded border overflow-hidden">
                                    <LazyImage
                                        media={image.media}
                                        conversion="medium"
                                        alt={image.alt || image.media.name}
                                        className="h-32 w-full"
                                        objectFit="cover"
                                    />
                                </div>

                                {/* Image Details */}
                                <p className="text-xs text-muted-foreground">
                                    {image.media.name} ({(image.media.size / 1024 / 1024).toFixed(2)} MB)
                                </p>

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-2">
                                        <Label htmlFor={`alt-${index}`}>Alt Text</Label>
                                        <Input
                                            id={`alt-${index}`}
                                            type="text"
                                            value={image.alt || ''}
                                            onChange={(e) => updateImageAlt(index, e.target.value)}
                                            placeholder="Image description"
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor={`caption-${index}`}>Caption</Label>
                                        <Input
                                            id={`caption-${index}`}
                                            type="text"
                                            value={image.caption || ''}
                                            onChange={(e) => updateImageCaption(index, e.target.value)}
                                            placeholder="Optional caption"
                                        />
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                    <Label htmlFor="columns">Columns</Label>
                    <Select value={columns} onValueChange={setColumns}>
                        <SelectTrigger id="columns">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="1">1 Column</SelectItem>
                            <SelectItem value="2">2 Columns</SelectItem>
                            <SelectItem value="3">3 Columns</SelectItem>
                            <SelectItem value="4">4 Columns</SelectItem>
                            <SelectItem value="5">5 Columns</SelectItem>
                            <SelectItem value="6">6 Columns</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="space-y-2">
                    <Label htmlFor="gap">Gap Size</Label>
                    <Select value={gap} onValueChange={setGap}>
                        <SelectTrigger id="gap">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="small">Small</SelectItem>
                            <SelectItem value="medium">Medium</SelectItem>
                            <SelectItem value="large">Large</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onCancel}>
                    Cancel
                </Button>
                <Button type="submit">Save Changes</Button>
            </div>
        </form>
    );
}
