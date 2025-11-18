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

interface CTABlockFormProps {
    block: PageBlock;
    onSave: (block: PageBlock) => void;
    onCancel: () => void;
}

export function CTABlockForm({ block, onSave, onCancel }: CTABlockFormProps) {
    const [title, setTitle] = useState(block.content.title || '');
    const [description, setDescription] = useState(block.content.description || '');
    const [buttonText, setButtonText] = useState(block.content.button_text || '');
    const [buttonUrl, setButtonUrl] = useState(block.content.button_url || '');
    const [alignment, setAlignment] = useState<string>(
        block.config?.alignment || 'center'
    );
    const [backgroundColor, setBackgroundColor] = useState(
        block.config?.background_color || ''
    );
    const [buttonStyle, setButtonStyle] = useState<string>(
        block.config?.button_style || 'primary'
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSave({
            ...block,
            content: {
                title,
                description,
                button_text: buttonText,
                button_url: buttonUrl,
            },
            config: {
                alignment,
                background_color: backgroundColor,
                button_style: buttonStyle,
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
                    placeholder="Ready to get started?"
                />
            </div>

            <div className="space-y-2">
                <Label htmlFor="description">Description</Label>
                <Textarea
                    id="description"
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    rows={3}
                    placeholder="Join thousands of satisfied customers"
                />
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                    <Label htmlFor="button_text">
                        Button Text
                        <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="button_text"
                        type="text"
                        value={buttonText}
                        onChange={(e) => setButtonText(e.target.value)}
                        required
                        placeholder="Get Started Now"
                    />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="button_url">
                        Button URL
                        <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="button_url"
                        type="text"
                        value={buttonUrl}
                        onChange={(e) => setButtonUrl(e.target.value)}
                        required
                        placeholder="/signup"
                    />
                </div>
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
                    <Label htmlFor="button_style">Button Style</Label>
                    <Select value={buttonStyle} onValueChange={setButtonStyle}>
                        <SelectTrigger id="button_style">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="primary">Primary</SelectItem>
                            <SelectItem value="secondary">Secondary</SelectItem>
                            <SelectItem value="outline">Outline</SelectItem>
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
                        placeholder="#f3f4f6"
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
