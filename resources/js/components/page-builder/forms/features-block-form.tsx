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
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Feature {
    title: string;
    description?: string;
    icon?: string;
}

interface FeaturesBlockFormProps {
    block: PageBlock;
    onSave: (block: PageBlock) => void;
    onCancel: () => void;
}

export function FeaturesBlockForm({ block, onSave, onCancel }: FeaturesBlockFormProps) {
    const [title, setTitle] = useState(block.content.title || '');
    const [features, setFeatures] = useState<Feature[]>(
        block.content.features || [{ title: '', description: '', icon: '' }]
    );
    const [columns, setColumns] = useState<string>(
        String(block.config?.columns || 3)
    );
    const [alignment, setAlignment] = useState<string>(
        block.config?.alignment || 'left'
    );

    const addFeature = () => {
        setFeatures([...features, { title: '', description: '', icon: '' }]);
    };

    const removeFeature = (index: number) => {
        setFeatures(features.filter((_, i) => i !== index));
    };

    const updateFeature = (index: number, field: keyof Feature, value: string) => {
        const newFeatures = [...features];
        newFeatures[index] = { ...newFeatures[index], [field]: value };
        setFeatures(newFeatures);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Filter out empty features
        const validFeatures = features.filter(feat => feat.title.trim() !== '');

        if (validFeatures.length === 0) {
            alert('Please add at least one feature');
            return;
        }

        onSave({
            ...block,
            content: {
                title,
                features: validFeatures,
            },
            config: {
                columns: parseInt(columns),
                alignment,
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="space-y-2">
                <Label htmlFor="title">Section Title</Label>
                <Input
                    id="title"
                    type="text"
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    placeholder="Our Features"
                />
            </div>

            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <Label>
                        Features
                        <span className="text-destructive">*</span>
                    </Label>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addFeature}
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        Add Feature
                    </Button>
                </div>

                <div className="space-y-4">
                    {features.map((feature, index) => (
                        <div key={index} className="rounded-lg border p-4 space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium">Feature {index + 1}</span>
                                {features.length > 1 && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeFeature(index)}
                                    >
                                        <Trash2 className="h-4 w-4 text-destructive" />
                                    </Button>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor={`feature-title-${index}`}>
                                    Title
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id={`feature-title-${index}`}
                                    type="text"
                                    value={feature.title}
                                    onChange={(e) => updateFeature(index, 'title', e.target.value)}
                                    placeholder="Feature name"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor={`feature-description-${index}`}>Description</Label>
                                <Textarea
                                    id={`feature-description-${index}`}
                                    value={feature.description || ''}
                                    onChange={(e) => updateFeature(index, 'description', e.target.value)}
                                    rows={2}
                                    placeholder="Brief description of the feature"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor={`feature-icon-${index}`}>Icon Name</Label>
                                <Input
                                    id={`feature-icon-${index}`}
                                    type="text"
                                    value={feature.icon || ''}
                                    onChange={(e) => updateFeature(index, 'icon', e.target.value)}
                                    placeholder="check, zap, shield (Lucide icon name)"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Use Lucide icon names (e.g., check, zap, shield)
                                </p>
                            </div>
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
                        </SelectContent>
                    </Select>
                </div>

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
