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
    const [backgroundMedia, setBackgroundMedia] = useState<Media | null>(
        block.content.background_media || null
    );
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
                background_media_id: backgroundMedia?.id,
                background_media: backgroundMedia, // Store media object for preview
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

            {/* Background Image */}
            <div className="space-y-2">
                <Label>Background/Hero Image (Optional)</Label>
                {backgroundMedia ? (
                    <div className="space-y-2">
                        <div className="relative rounded-lg border p-4">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => setBackgroundMedia(null)}
                                className="absolute top-2 right-2 z-10"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                            <LazyImage
                                media={backgroundMedia}
                                conversion="large"
                                alt="Hero background"
                                className="max-h-48 rounded"
                                objectFit="cover"
                            />
                            <p className="mt-2 text-xs text-muted-foreground">
                                {backgroundMedia.name} ({(backgroundMedia.size / 1024 / 1024).toFixed(2)} MB)
                            </p>
                        </div>
                        <MediaPicker
                            collection="hero-images"
                            accept="image/*"
                            value={backgroundMedia}
                            onChange={(m) => setBackgroundMedia(m as Media | null)}
                            triggerLabel="Change Background"
                            triggerVariant="outline"
                        />
                    </div>
                ) : (
                    <MediaPicker
                        collection="hero-images"
                        accept="image/*"
                        value={backgroundMedia}
                        onChange={(m) => setBackgroundMedia(m as Media | null)}
                        triggerLabel="Select Background Image"
                        triggerVariant="outline"
                    />
                )}
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
