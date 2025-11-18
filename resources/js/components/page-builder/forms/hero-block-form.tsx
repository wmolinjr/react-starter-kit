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

interface HeroBlockFormProps {
    block: PageBlock;
    onSave: (block: PageBlock) => void;
    onCancel: () => void;
}

export function HeroBlockForm({ block, onSave, onCancel }: HeroBlockFormProps) {
    const [title, setTitle] = useState(block.content.title || '');
    const [subtitle, setSubtitle] = useState(block.content.subtitle || '');
    const [ctaText, setCtaText] = useState(block.content.cta_text || '');
    const [ctaUrl, setCtaUrl] = useState(block.content.cta_url || '');
    const [imageUrl, setImageUrl] = useState(block.content.image_url || '');
    const [alignment, setAlignment] = useState<string>(
        block.config?.alignment || 'center'
    );
    const [backgroundColor, setBackgroundColor] = useState(
        block.config?.background_color || ''
    );
    const [textColor, setTextColor] = useState(
        block.config?.text_color || ''
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSave({
            ...block,
            content: {
                title,
                subtitle,
                cta_text: ctaText,
                cta_url: ctaUrl,
                image_url: imageUrl,
            },
            config: {
                alignment,
                background_color: backgroundColor,
                text_color: textColor,
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="space-y-2">
                <Label htmlFor="title">
                    Title
                    <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="title"
                    type="text"
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    required
                    placeholder="Welcome to Our Site"
                />
            </div>

            <div className="space-y-2">
                <Label htmlFor="subtitle">Subtitle</Label>
                <Textarea
                    id="subtitle"
                    value={subtitle}
                    onChange={(e) => setSubtitle(e.target.value)}
                    rows={2}
                    placeholder="A brief description or tagline"
                />
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                    <Label htmlFor="cta_text">CTA Button Text</Label>
                    <Input
                        id="cta_text"
                        type="text"
                        value={ctaText}
                        onChange={(e) => setCtaText(e.target.value)}
                        placeholder="Get Started"
                    />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="cta_url">CTA Button URL</Label>
                    <Input
                        id="cta_url"
                        type="text"
                        value={ctaUrl}
                        onChange={(e) => setCtaUrl(e.target.value)}
                        placeholder="/signup"
                    />
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor="image_url">Background/Hero Image URL</Label>
                <Input
                    id="image_url"
                    type="url"
                    value={imageUrl}
                    onChange={(e) => setImageUrl(e.target.value)}
                    placeholder="https://example.com/hero.jpg"
                />
            </div>

            <div className="grid grid-cols-3 gap-4">
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
                    <Label htmlFor="background_color">Background Color</Label>
                    <Input
                        id="background_color"
                        type="text"
                        value={backgroundColor}
                        onChange={(e) => setBackgroundColor(e.target.value)}
                        placeholder="#000000"
                    />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="text_color">Text Color</Label>
                    <Input
                        id="text_color"
                        type="text"
                        value={textColor}
                        onChange={(e) => setTextColor(e.target.value)}
                        placeholder="#ffffff"
                    />
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
