import { Badge } from '@/components/ui/badge';
import type { PageBlock } from '@/types';
import { FileText, Image, Images, Layout, MessageSquare, Sparkles, Users } from 'lucide-react';

interface BlockPreviewProps {
    block: PageBlock;
}

export function BlockPreview({ block }: BlockPreviewProps) {
    switch (block.block_type) {
        case 'hero':
            return (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Layout className="h-4 w-4 text-muted-foreground" />
                        <span className="text-xs font-medium text-muted-foreground">Hero Section</span>
                    </div>
                    <div>
                        <h3 className="text-xl font-bold">{block.content.title}</h3>
                        {block.content.subtitle && (
                            <p className="mt-1 text-muted-foreground">{block.content.subtitle}</p>
                        )}
                    </div>
                    {block.content.cta_text && (
                        <Badge variant="secondary">{block.content.cta_text}</Badge>
                    )}
                </div>
            );

        case 'text':
            return (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <FileText className="h-4 w-4 text-muted-foreground" />
                        <span className="text-xs font-medium text-muted-foreground">Text Content</span>
                    </div>
                    <p className="text-sm line-clamp-4">{block.content.content}</p>
                </div>
            );

        case 'image':
            return (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Image className="h-4 w-4 text-muted-foreground" />
                        <span className="text-xs font-medium text-muted-foreground">Image</span>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <img
                            src={block.content.url}
                            alt={block.content.alt || ''}
                            className="max-h-48 rounded object-cover"
                        />
                        {block.content.caption && (
                            <p className="mt-2 text-xs text-muted-foreground">{block.content.caption}</p>
                        )}
                    </div>
                </div>
            );

        case 'gallery':
            return (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Images className="h-4 w-4 text-muted-foreground" />
                        <span className="text-xs font-medium text-muted-foreground">Image Gallery</span>
                    </div>
                    <div className="grid grid-cols-3 gap-2">
                        {block.content.images.slice(0, 6).map((image: any, index: number) => (
                            <div key={index} className="aspect-square rounded-lg border bg-muted/30 p-1">
                                <img
                                    src={image.url}
                                    alt={image.alt || ''}
                                    className="h-full w-full rounded object-cover"
                                />
                            </div>
                        ))}
                    </div>
                    {block.content.images.length > 6 && (
                        <p className="text-xs text-muted-foreground">
                            +{block.content.images.length - 6} more images
                        </p>
                    )}
                </div>
            );

        case 'cta':
            return (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Sparkles className="h-4 w-4 text-muted-foreground" />
                        <span className="text-xs font-medium text-muted-foreground">Call to Action</span>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <h4 className="font-semibold">{block.content.title}</h4>
                        {block.content.description && (
                            <p className="mt-1 text-sm text-muted-foreground">{block.content.description}</p>
                        )}
                        <Badge variant="default" className="mt-3">
                            {block.content.button_text}
                        </Badge>
                    </div>
                </div>
            );

        case 'features':
            return (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <MessageSquare className="h-4 w-4 text-muted-foreground" />
                        <span className="text-xs font-medium text-muted-foreground">Features Grid</span>
                    </div>
                    {block.content.title && (
                        <h4 className="font-semibold">{block.content.title}</h4>
                    )}
                    <div className="grid gap-2">
                        {block.content.features.slice(0, 3).map((feature: any, index: number) => (
                            <div key={index} className="rounded-lg border bg-muted/30 p-3">
                                <p className="text-sm font-medium">{feature.title}</p>
                                {feature.description && (
                                    <p className="text-xs text-muted-foreground">{feature.description}</p>
                                )}
                            </div>
                        ))}
                    </div>
                    {block.content.features.length > 3 && (
                        <p className="text-xs text-muted-foreground">
                            +{block.content.features.length - 3} more features
                        </p>
                    )}
                </div>
            );

        case 'testimonials':
            return (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Users className="h-4 w-4 text-muted-foreground" />
                        <span className="text-xs font-medium text-muted-foreground">Testimonials</span>
                    </div>
                    {block.content.title && (
                        <h4 className="font-semibold">{block.content.title}</h4>
                    )}
                    <div className="space-y-2">
                        {block.content.testimonials.slice(0, 2).map((testimonial: any, index: number) => (
                            <div key={index} className="rounded-lg border bg-muted/30 p-3">
                                <p className="text-sm italic">"{testimonial.quote}"</p>
                                <p className="mt-2 text-xs font-medium">{testimonial.author}</p>
                                {testimonial.role && (
                                    <p className="text-xs text-muted-foreground">{testimonial.role}</p>
                                )}
                            </div>
                        ))}
                    </div>
                    {block.content.testimonials.length > 2 && (
                        <p className="text-xs text-muted-foreground">
                            +{block.content.testimonials.length - 2} more testimonials
                        </p>
                    )}
                </div>
            );

        default: {
            // Type guard to ensure all block types are handled
            const _exhaustiveCheck: never = block.block_type;
            return (
                <div className="text-sm text-muted-foreground">
                    Unknown block type: {String(_exhaustiveCheck)}
                </div>
            );
        }
    }
}
