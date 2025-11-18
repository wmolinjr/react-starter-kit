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

interface Testimonial {
    quote: string;
    author: string;
    role?: string;
    avatar?: string;
}

interface TestimonialsBlockFormProps {
    block: PageBlock;
    onSave: (block: PageBlock) => void;
    onCancel: () => void;
}

export function TestimonialsBlockForm({ block, onSave, onCancel }: TestimonialsBlockFormProps) {
    const [title, setTitle] = useState(block.content.title || '');
    const [testimonials, setTestimonials] = useState<Testimonial[]>(
        block.content.testimonials || [{ quote: '', author: '', role: '', avatar: '' }]
    );
    const [layout, setLayout] = useState<string>(
        block.config?.layout || 'grid'
    );
    const [columns, setColumns] = useState<string>(
        String(block.config?.columns || 2)
    );

    const addTestimonial = () => {
        setTestimonials([...testimonials, { quote: '', author: '', role: '', avatar: '' }]);
    };

    const removeTestimonial = (index: number) => {
        setTestimonials(testimonials.filter((_, i) => i !== index));
    };

    const updateTestimonial = (index: number, field: keyof Testimonial, value: string) => {
        const newTestimonials = [...testimonials];
        newTestimonials[index] = { ...newTestimonials[index], [field]: value };
        setTestimonials(newTestimonials);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Filter out empty testimonials
        const validTestimonials = testimonials.filter(
            test => test.quote.trim() !== '' && test.author.trim() !== ''
        );

        if (validTestimonials.length === 0) {
            alert('Please add at least one testimonial with quote and author');
            return;
        }

        onSave({
            ...block,
            content: {
                title,
                testimonials: validTestimonials,
            },
            config: {
                layout,
                columns: parseInt(columns),
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
                    placeholder="What Our Customers Say"
                />
            </div>

            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <Label>
                        Testimonials
                        <span className="text-destructive">*</span>
                    </Label>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addTestimonial}
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        Add Testimonial
                    </Button>
                </div>

                <div className="space-y-4">
                    {testimonials.map((testimonial, index) => (
                        <div key={index} className="rounded-lg border p-4 space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium">Testimonial {index + 1}</span>
                                {testimonials.length > 1 && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeTestimonial(index)}
                                    >
                                        <Trash2 className="h-4 w-4 text-destructive" />
                                    </Button>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor={`quote-${index}`}>
                                    Quote
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Textarea
                                    id={`quote-${index}`}
                                    value={testimonial.quote}
                                    onChange={(e) => updateTestimonial(index, 'quote', e.target.value)}
                                    rows={3}
                                    placeholder="This product changed my life..."
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-2">
                                    <Label htmlFor={`author-${index}`}>
                                        Author
                                        <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id={`author-${index}`}
                                        type="text"
                                        value={testimonial.author}
                                        onChange={(e) => updateTestimonial(index, 'author', e.target.value)}
                                        placeholder="John Doe"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor={`role-${index}`}>Role/Title</Label>
                                    <Input
                                        id={`role-${index}`}
                                        type="text"
                                        value={testimonial.role || ''}
                                        onChange={(e) => updateTestimonial(index, 'role', e.target.value)}
                                        placeholder="CEO, Company Inc"
                                    />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor={`avatar-${index}`}>Avatar URL</Label>
                                <Input
                                    id={`avatar-${index}`}
                                    type="url"
                                    value={testimonial.avatar || ''}
                                    onChange={(e) => updateTestimonial(index, 'avatar', e.target.value)}
                                    placeholder="https://example.com/avatar.jpg"
                                />
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                    <Label htmlFor="layout">Layout</Label>
                    <Select value={layout} onValueChange={setLayout}>
                        <SelectTrigger id="layout">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="grid">Grid</SelectItem>
                            <SelectItem value="carousel">Carousel</SelectItem>
                            <SelectItem value="list">List</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

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
