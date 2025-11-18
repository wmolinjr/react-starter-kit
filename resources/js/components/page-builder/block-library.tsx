import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { BlockTemplate, BlockType } from '@/types/blocks';
import {
    FileText,
    Image,
    Images,
    Layout,
    MessageSquare,
    Sparkles,
    Users,
} from 'lucide-react';

// Block templates configuration
export const BLOCK_TEMPLATES: Record<BlockType, BlockTemplate> = {
    hero: {
        type: 'hero',
        label: 'Hero Section',
        icon: 'Layout',
        description: 'Large hero section with title, subtitle, and CTA',
        defaultContent: {
            title: 'Welcome to Our Site',
            subtitle: 'Build amazing pages with ease',
            cta_text: 'Get Started',
            cta_url: '#',
        },
        defaultConfig: {
            alignment: 'center',
        },
    },
    text: {
        type: 'text',
        label: 'Text Content',
        icon: 'FileText',
        description: 'Rich text content block',
        defaultContent: {
            content: 'Enter your text content here...',
        },
        defaultConfig: {
            alignment: 'left',
        },
    },
    image: {
        type: 'image',
        label: 'Image',
        icon: 'Image',
        description: 'Single image with optional caption',
        defaultContent: {
            url: 'https://via.placeholder.com/800x600',
            alt: 'Placeholder image',
            caption: '',
        },
        defaultConfig: {
            alignment: 'center',
            width: 'full',
        },
    },
    gallery: {
        type: 'gallery',
        label: 'Image Gallery',
        icon: 'Images',
        description: 'Grid of images',
        defaultContent: {
            images: [
                {
                    url: 'https://via.placeholder.com/400x300',
                    alt: 'Gallery image 1',
                },
                {
                    url: 'https://via.placeholder.com/400x300',
                    alt: 'Gallery image 2',
                },
                {
                    url: 'https://via.placeholder.com/400x300',
                    alt: 'Gallery image 3',
                },
            ],
        },
        defaultConfig: {
            columns: 3,
            gap: 'medium',
        },
    },
    cta: {
        type: 'cta',
        label: 'Call to Action',
        icon: 'Sparkles',
        description: 'Prominent call to action section',
        defaultContent: {
            title: 'Ready to get started?',
            description: 'Join thousands of satisfied customers',
            button_text: 'Get Started Now',
            button_url: '#',
        },
        defaultConfig: {
            alignment: 'center',
            button_style: 'primary',
        },
    },
    features: {
        type: 'features',
        label: 'Features Grid',
        icon: 'MessageSquare',
        description: 'Showcase product features',
        defaultContent: {
            title: 'Our Features',
            features: [
                {
                    title: 'Easy to Use',
                    description: 'Intuitive interface',
                    icon: 'check',
                },
                {
                    title: 'Fast Performance',
                    description: 'Lightning quick',
                    icon: 'zap',
                },
                {
                    title: 'Secure',
                    description: 'Bank-level security',
                    icon: 'shield',
                },
            ],
        },
        defaultConfig: {
            columns: 3,
            alignment: 'left',
        },
    },
    testimonials: {
        type: 'testimonials',
        label: 'Testimonials',
        icon: 'Users',
        description: 'Customer testimonials and reviews',
        defaultContent: {
            title: 'What Our Customers Say',
            testimonials: [
                {
                    quote:
                        'This product has transformed our business. Highly recommended!',
                    author: 'John Doe',
                    role: 'CEO, Company Inc',
                },
                {
                    quote: 'Outstanding service and support. 5 stars!',
                    author: 'Jane Smith',
                    role: 'Marketing Director',
                },
            ],
        },
        defaultConfig: {
            layout: 'grid',
            columns: 2,
        },
    },
};

const ICON_MAP = {
    Layout,
    FileText,
    Image,
    Images,
    Sparkles,
    MessageSquare,
    Users,
};

interface BlockLibraryProps {
    onAddBlock: (template: BlockTemplate) => void;
}

export function BlockLibrary({ onAddBlock }: BlockLibraryProps) {
    return (
        <div className="space-y-4">
            <div>
                <h3 className="text-lg font-semibold">Add Block</h3>
                <p className="text-sm text-muted-foreground">
                    Choose a block type to add to your page
                </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                {Object.values(BLOCK_TEMPLATES).map((template) => {
                    const Icon = ICON_MAP[
                        template.icon as keyof typeof ICON_MAP
                    ] || FileText;

                    return (
                        <Card
                            key={template.type}
                            className="group cursor-pointer transition-all hover:border-primary hover:shadow-md"
                            onClick={() => onAddBlock(template)}
                        >
                            <CardHeader className="p-4">
                                <div className="flex items-start gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-md border bg-background group-hover:bg-primary/5">
                                        <Icon className="h-5 w-5 text-muted-foreground group-hover:text-primary" />
                                    </div>
                                    <div className="flex-1 space-y-1">
                                        <CardTitle className="text-sm">
                                            {template.label}
                                        </CardTitle>
                                        <CardDescription className="text-xs">
                                            {template.description}
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                        </Card>
                    );
                })}
            </div>
        </div>
    );
}
