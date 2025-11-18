import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import pagesRoutes from '@/routes/pages';
import type { BreadcrumbItem, Page, PageBlock } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Calendar, Edit, Globe, User } from 'lucide-react';

interface Props {
    page: Page;
}

// Block renderers for preview
const HeroBlock = ({ content }: { content: any }) => (
    <div className="relative overflow-hidden rounded-lg bg-gradient-to-r from-primary/10 to-primary/5 p-12">
        <div className="mx-auto max-w-4xl text-center">
            <h1 className="text-4xl font-bold tracking-tight sm:text-6xl">
                {content.title}
            </h1>
            <p className="mt-6 text-lg leading-8 text-muted-foreground">
                {content.subtitle}
            </p>
            {content.button_text && (
                <div className="mt-10">
                    <Button size="lg">{content.button_text}</Button>
                </div>
            )}
        </div>
    </div>
);

const TextBlock = ({ content }: { content: any }) => (
    <div className="prose prose-neutral dark:prose-invert max-w-none">
        {content.heading && <h2>{content.heading}</h2>}
        <p className="whitespace-pre-wrap">{content.content}</p>
    </div>
);

const ImageBlock = ({ content }: { content: any }) => (
    <figure className="space-y-2">
        <div className="overflow-hidden rounded-lg border bg-muted">
            <img
                src={content.image_url}
                alt={content.alt_text || ''}
                className="h-auto w-full object-cover"
            />
        </div>
        {content.caption && (
            <figcaption className="text-sm text-muted-foreground">
                {content.caption}
            </figcaption>
        )}
    </figure>
);

const GalleryBlock = ({ content }: { content: any }) => (
    <div className="grid grid-cols-2 gap-4 md:grid-cols-3">
        {content.images?.map((image: any, index: number) => (
            <div key={index} className="overflow-hidden rounded-lg border bg-muted">
                <img
                    src={image.url}
                    alt={image.alt || ''}
                    className="h-48 w-full object-cover"
                />
            </div>
        ))}
    </div>
);

const CtaBlock = ({ content }: { content: any }) => (
    <div className="rounded-lg border bg-muted/50 p-8 text-center">
        <h2 className="text-2xl font-bold">{content.title}</h2>
        <p className="mt-4 text-muted-foreground">{content.description}</p>
        {content.button_text && (
            <div className="mt-6">
                <Button>{content.button_text}</Button>
            </div>
        )}
    </div>
);

const FeaturesBlock = ({ content }: { content: any }) => (
    <div className="space-y-6">
        {content.title && <h2 className="text-3xl font-bold">{content.title}</h2>}
        <div className="grid gap-6 md:grid-cols-3">
            {content.features?.map((feature: any, index: number) => (
                <div key={index} className="space-y-2">
                    <h3 className="font-semibold">{feature.title}</h3>
                    <p className="text-sm text-muted-foreground">{feature.description}</p>
                </div>
            ))}
        </div>
    </div>
);

const TestimonialsBlock = ({ content }: { content: any }) => (
    <div className="space-y-6">
        {content.title && <h2 className="text-3xl font-bold">{content.title}</h2>}
        <div className="grid gap-6 md:grid-cols-2">
            {content.testimonials?.map((testimonial: any, index: number) => (
                <div key={index} className="rounded-lg border p-6">
                    <p className="text-muted-foreground">"{testimonial.quote}"</p>
                    <div className="mt-4 flex items-center gap-3">
                        {testimonial.avatar && (
                            <img
                                src={testimonial.avatar}
                                alt={testimonial.author}
                                className="h-10 w-10 rounded-full"
                            />
                        )}
                        <div>
                            <p className="font-semibold">{testimonial.author}</p>
                            <p className="text-sm text-muted-foreground">
                                {testimonial.role} at {testimonial.company}
                            </p>
                        </div>
                    </div>
                </div>
            ))}
        </div>
    </div>
);

const BlockRenderer = ({ block }: { block: PageBlock }) => {
    const renderers = {
        hero: HeroBlock,
        text: TextBlock,
        image: ImageBlock,
        gallery: GalleryBlock,
        cta: CtaBlock,
        features: FeaturesBlock,
        testimonials: TestimonialsBlock,
    };

    const Component = renderers[block.block_type as keyof typeof renderers];

    if (!Component) {
        return (
            <div className="rounded-lg border border-dashed p-6 text-center text-muted-foreground">
                Unknown block type: {block.block_type}
            </div>
        );
    }

    return <Component content={block.content} />;
};

export default function PagesShow({ page }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Pages',
            href: pagesRoutes.index().url,
        },
        {
            title: page.title,
            href: pagesRoutes.show({ page: page.id }).url,
        },
    ];

    const getStatusBadge = (status: Page['status']) => {
        const variants = {
            draft: 'secondary',
            published: 'default',
            archived: 'outline',
        } as const;

        return (
            <Badge variant={variants[status]} className="capitalize">
                {status}
            </Badge>
        );
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={page.title} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="flex items-start gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <Link href={pagesRoutes.index().url}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div className="space-y-1">
                            <div className="flex items-center gap-3">
                                <h1 className="text-3xl font-bold tracking-tight">
                                    {page.title}
                                </h1>
                                {getStatusBadge(page.status)}
                            </div>
                            <p className="text-muted-foreground">/{page.slug}</p>
                        </div>
                    </div>
                    <Link href={pagesRoutes.edit({ page: page.id }).url}>
                        <Button>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit Page
                        </Button>
                    </Link>
                </div>

                {/* Meta Information */}
                <div className="flex flex-wrap gap-6 text-sm text-muted-foreground">
                    {page.created_by && (
                        <div className="flex items-center gap-2">
                            <User className="h-4 w-4" />
                            <span>Created by {page.created_by.name}</span>
                        </div>
                    )}
                    <div className="flex items-center gap-2">
                        <Calendar className="h-4 w-4" />
                        <span>Created {formatDate(page.created_at)}</span>
                    </div>
                    {page.published_at && (
                        <div className="flex items-center gap-2">
                            <Globe className="h-4 w-4" />
                            <span>Published {formatDate(page.published_at)}</span>
                        </div>
                    )}
                </div>

                {/* SEO Information */}
                {(page.meta_title || page.meta_description) && (
                    <div className="rounded-lg border p-6">
                        <h2 className="mb-4 text-lg font-semibold">SEO Information</h2>
                        <dl className="space-y-3">
                            {page.meta_title && (
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">
                                        Meta Title
                                    </dt>
                                    <dd className="mt-1">{page.meta_title}</dd>
                                </div>
                            )}
                            {page.meta_description && (
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">
                                        Meta Description
                                    </dt>
                                    <dd className="mt-1">{page.meta_description}</dd>
                                </div>
                            )}
                            {page.meta_keywords && (
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">
                                        Keywords
                                    </dt>
                                    <dd className="mt-1">{page.meta_keywords}</dd>
                                </div>
                            )}
                        </dl>
                    </div>
                )}

                {/* Page Content */}
                <div className="space-y-8">
                    <h2 className="text-2xl font-bold">Page Preview</h2>

                    {page.blocks.length === 0 ? (
                        <div className="rounded-lg border border-dashed p-12 text-center">
                            <div className="mx-auto flex max-w-[420px] flex-col items-center justify-center">
                                <h3 className="mt-4 text-lg font-semibold">No blocks yet</h3>
                                <p className="mb-4 mt-2 text-sm text-muted-foreground">
                                    This page doesn't have any content blocks yet. Edit the
                                    page to add blocks.
                                </p>
                                <Link href={pagesRoutes.edit({ page: page.id }).url}>
                                    <Button>
                                        <Edit className="mr-2 h-4 w-4" />
                                        Edit Page
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-12 rounded-lg border p-8">
                            {page.blocks.map((block) => (
                                <div key={block.id}>
                                    <BlockRenderer block={block} />
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
