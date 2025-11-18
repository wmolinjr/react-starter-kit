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
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface GalleryImage {
    url: string;
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
        block.content.images || [{ url: '', alt: '', caption: '' }]
    );
    const [columns, setColumns] = useState<string>(
        String(block.config?.columns || 3)
    );
    const [gap, setGap] = useState<string>(
        block.config?.gap || 'medium'
    );

    const addImage = () => {
        setImages([...images, { url: '', alt: '', caption: '' }]);
    };

    const removeImage = (index: number) => {
        setImages(images.filter((_, i) => i !== index));
    };

    const updateImage = (index: number, field: keyof GalleryImage, value: string) => {
        const newImages = [...images];
        newImages[index] = { ...newImages[index], [field]: value };
        setImages(newImages);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Filter out empty images
        const validImages = images.filter(img => img.url.trim() !== '');

        if (validImages.length === 0) {
            alert('Please add at least one image');
            return;
        }

        onSave({
            ...block,
            content: {
                images: validImages,
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
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addImage}
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        Add Image
                    </Button>
                </div>

                <div className="space-y-4">
                    {images.map((image, index) => (
                        <div key={index} className="rounded-lg border p-4 space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium">Image {index + 1}</span>
                                {images.length > 1 && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeImage(index)}
                                    >
                                        <Trash2 className="h-4 w-4 text-destructive" />
                                    </Button>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor={`url-${index}`}>Image URL</Label>
                                <Input
                                    id={`url-${index}`}
                                    type="url"
                                    value={image.url}
                                    onChange={(e) => updateImage(index, 'url', e.target.value)}
                                    placeholder="https://example.com/image.jpg"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-2">
                                    <Label htmlFor={`alt-${index}`}>Alt Text</Label>
                                    <Input
                                        id={`alt-${index}`}
                                        type="text"
                                        value={image.alt || ''}
                                        onChange={(e) => updateImage(index, 'alt', e.target.value)}
                                        placeholder="Image description"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor={`caption-${index}`}>Caption</Label>
                                    <Input
                                        id={`caption-${index}`}
                                        type="text"
                                        value={image.caption || ''}
                                        onChange={(e) => updateImage(index, 'caption', e.target.value)}
                                        placeholder="Optional caption"
                                    />
                                </div>
                            </div>

                            {image.url && (
                                <div className="rounded border p-2">
                                    <img
                                        src={image.url}
                                        alt={image.alt || ''}
                                        className="h-24 w-full rounded object-cover"
                                    />
                                </div>
                            )}
                        </div>
                    ))}
                </div>
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
