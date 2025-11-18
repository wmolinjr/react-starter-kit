import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
import { useState } from 'react';
import { X } from 'lucide-react';

interface ImageBlockFormProps {
    block: PageBlock;
    onSave: (block: PageBlock) => void;
    onCancel: () => void;
}

export function ImageBlockForm({ block, onSave, onCancel }: ImageBlockFormProps) {
    const [media, setMedia] = useState<Media | null>(block.content.media || null);
    const [alt, setAlt] = useState(block.content.alt || '');
    const [caption, setCaption] = useState(block.content.caption || '');
    const [alignment, setAlignment] = useState<string>(
        block.config?.alignment || 'center'
    );
    const [width, setWidth] = useState<string>(
        block.config?.width || 'full'
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!media) {
            alert('Please select an image');
            return;
        }

        onSave({
            ...block,
            content: {
                media_id: media.id,
                media, // Store media object for preview
                alt: alt || media.name,
                caption,
            },
            config: {
                alignment,
                width,
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {/* Media Picker */}
            <div className="space-y-2">
                <Label>
                    Image
                    <span className="text-destructive">*</span>
                </Label>
                {media ? (
                    <div className="space-y-2">
                        <div className="relative rounded-lg border p-4">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => setMedia(null)}
                                className="absolute top-2 right-2"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                            <LazyImage
                                media={media}
                                conversion="medium"
                                alt={alt || media.name}
                                className="max-h-48 rounded"
                                objectFit="contain"
                            />
                            <p className="mt-2 text-xs text-muted-foreground">
                                {media.name} ({(media.size / 1024 / 1024).toFixed(2)} MB)
                            </p>
                        </div>
                        <MediaPicker
                            collection="page-images"
                            accept="image/*"
                            value={media}
                            onChange={(m) => setMedia(m as Media | null)}
                            triggerLabel="Change Image"
                            triggerVariant="outline"
                        />
                    </div>
                ) : (
                    <MediaPicker
                        collection="page-images"
                        accept="image/*"
                        value={media}
                        onChange={(m) => setMedia(m as Media | null)}
                        triggerLabel="Select Image"
                    />
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="alt">Alt Text</Label>
                <Input
                    id="alt"
                    type="text"
                    value={alt}
                    onChange={(e) => setAlt(e.target.value)}
                    placeholder="Describe the image for accessibility"
                />
                <p className="text-xs text-muted-foreground">
                    Important for SEO and accessibility. Defaults to image name if empty.
                </p>
            </div>

            <div className="space-y-2">
                <Label htmlFor="caption">Caption</Label>
                <Textarea
                    id="caption"
                    value={caption}
                    onChange={(e) => setCaption(e.target.value)}
                    rows={2}
                    placeholder="Optional image caption"
                />
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                    <Label htmlFor="alignment">Alignment</Label>
                    <Select value={alignment} onValueChange={setAlignment}>
                        <SelectTrigger id="alignment">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="left">Left</SelectItem>
                            <SelectItem value="center">Center</SelectItem>
                            <SelectItem value="right">Right</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="space-y-2">
                    <Label htmlFor="width">Width</Label>
                    <Select value={width} onValueChange={setWidth}>
                        <SelectTrigger id="width">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="small">Small</SelectItem>
                            <SelectItem value="medium">Medium</SelectItem>
                            <SelectItem value="large">Large</SelectItem>
                            <SelectItem value="full">Full Width</SelectItem>
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
