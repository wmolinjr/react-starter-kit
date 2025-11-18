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
import { useState } from 'react';

interface ImageBlockFormProps {
    block: PageBlock;
    onSave: (block: PageBlock) => void;
    onCancel: () => void;
}

export function ImageBlockForm({ block, onSave, onCancel }: ImageBlockFormProps) {
    const [url, setUrl] = useState(block.content.url || '');
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
        onSave({
            ...block,
            content: {
                url,
                alt,
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
            <div className="space-y-2">
                <Label htmlFor="url">
                    Image URL
                    <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="url"
                    type="url"
                    value={url}
                    onChange={(e) => setUrl(e.target.value)}
                    required
                    placeholder="https://example.com/image.jpg"
                />
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
                    Important for SEO and accessibility
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

            {url && (
                <div className="rounded-lg border p-4">
                    <p className="mb-2 text-sm font-medium">Preview:</p>
                    <img
                        src={url}
                        alt={alt}
                        className="max-h-48 rounded object-contain"
                    />
                </div>
            )}

            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onCancel}>
                    Cancel
                </Button>
                <Button type="submit">Save Changes</Button>
            </div>
        </form>
    );
}
