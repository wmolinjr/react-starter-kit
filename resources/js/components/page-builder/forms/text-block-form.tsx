import { Button } from '@/components/ui/button';
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

interface TextBlockFormProps {
    block: PageBlock;
    onSave: (block: PageBlock) => void;
    onCancel: () => void;
}

export function TextBlockForm({ block, onSave, onCancel }: TextBlockFormProps) {
    const [content, setContent] = useState(block.content.content || '');
    const [alignment, setAlignment] = useState<string>(
        block.config?.alignment || 'left'
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSave({
            ...block,
            content: {
                content,
            },
            config: {
                alignment,
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="space-y-2">
                <Label htmlFor="content">
                    Text Content
                    <span className="text-destructive">*</span>
                </Label>
                <Textarea
                    id="content"
                    value={content}
                    onChange={(e) => setContent(e.target.value)}
                    rows={8}
                    required
                    placeholder="Enter your text content here..."
                />
                <p className="text-xs text-muted-foreground">
                    You can use plain text or markdown
                </p>
            </div>

            <div className="space-y-2">
                <Label htmlFor="alignment">Text Alignment</Label>
                <Select value={alignment} onValueChange={setAlignment}>
                    <SelectTrigger id="alignment">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="left">Left</SelectItem>
                        <SelectItem value="center">Center</SelectItem>
                        <SelectItem value="right">Right</SelectItem>
                        <SelectItem value="justify">Justify</SelectItem>
                    </SelectContent>
                </Select>
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
