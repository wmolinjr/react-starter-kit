import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { BlockEditor } from '@/components/page-builder/block-editor';
import AppLayout from '@/layouts/app-layout';
import pagesRoutes from '@/routes/pages';
import type { BreadcrumbItem, Page } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Eye,
    FileText,
    Globe,
    Save,
    Settings,
} from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Props {
    page: Page;
}

const breadcrumbs = (page: Page): BreadcrumbItem[] => [
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
        href: pagesRoutes.edit({ page: page.id }).url,
    },
];

export default function PagesEditor({ page }: Props) {
    const [activeTab, setActiveTab] = useState<'content' | 'settings'>('settings');

    const { data, setData, put, processing, errors, isDirty } = useForm({
        title: page.title || '',
        slug: page.slug || '',
        meta_title: page.meta_title || '',
        meta_description: page.meta_description || '',
        meta_keywords: page.meta_keywords || '',
        status: page.status || 'draft',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(pagesRoutes.update({ page: page.id }).url, {
            preserveScroll: true,
        });
    };

    const handlePublish = () => {
        router.post(
            pagesRoutes.publish({ page: page.id }).url,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    const handleUnpublish = () => {
        router.post(
            pagesRoutes.unpublish({ page: page.id }).url,
            {},
            {
                preserveScroll: true,
            }
        );
    };

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

    return (
        <AppLayout breadcrumbs={breadcrumbs(page)}>
            <Head title={`Edit ${page.title}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="flex items-start gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <a href={pagesRoutes.index().url}>
                                <ArrowLeft className="h-4 w-4" />
                            </a>
                        </Button>
                        <div className="space-y-1">
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-semibold">Edit Page</h1>
                                {getStatusBadge(page.status)}
                            </div>
                            <p className="text-sm text-muted-foreground">/{page.slug}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <Button
                            variant="outline"
                            asChild
                        >
                            <a href={pagesRoutes.show({ page: page.id }).url}>
                                <Eye className="mr-2 h-4 w-4" />
                                Preview
                            </a>
                        </Button>
                        {page.status === 'published' ? (
                            <Button variant="outline" onClick={handleUnpublish}>
                                <Globe className="mr-2 h-4 w-4" />
                                Unpublish
                            </Button>
                        ) : (
                            <Button onClick={handlePublish}>
                                <Globe className="mr-2 h-4 w-4" />
                                Publish
                            </Button>
                        )}
                    </div>
                </div>

                {/* Tabs */}
                <div className="flex gap-4 border-b">
                    <button
                        onClick={() => setActiveTab('settings')}
                        className={`flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors ${
                            activeTab === 'settings'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <Settings className="h-4 w-4" />
                        Page Settings
                    </button>
                    <button
                        onClick={() => setActiveTab('content')}
                        className={`flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors ${
                            activeTab === 'content'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <FileText className="h-4 w-4" />
                        Content Blocks
                        <Badge variant="secondary">{page.blocks.length}</Badge>
                    </button>
                </div>

                {/* Page Settings Tab */}
                {activeTab === 'settings' && (
                    <div className="mx-auto w-full max-w-2xl space-y-6">
                        <form onSubmit={submit} className="space-y-6">
                            {/* Basic Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Basic Information</CardTitle>
                                    <CardDescription>
                                        Edit the basic details of your page
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    <div className="space-y-2">
                                        <Label htmlFor="title">
                                            Page Title
                                            <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="title"
                                            type="text"
                                            value={data.title}
                                            onChange={(e) =>
                                                setData('title', e.target.value)
                                            }
                                            required
                                        />
                                        {errors.title && (
                                            <p className="text-sm text-destructive">
                                                {errors.title}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="slug">Slug</Label>
                                        <Input
                                            id="slug"
                                            type="text"
                                            value={data.slug}
                                            onChange={(e) => setData('slug', e.target.value)}
                                        />
                                        {errors.slug && (
                                            <p className="text-sm text-destructive">
                                                {errors.slug}
                                            </p>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            URL-friendly identifier
                                        </p>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="status">Status</Label>
                                        <Select
                                            value={data.status}
                                            onValueChange={(value) =>
                                                setData(
                                                    'status',
                                                    value as 'draft' | 'published' | 'archived'
                                                )
                                            }
                                        >
                                            <SelectTrigger id="status">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="draft">Draft</SelectItem>
                                                <SelectItem value="published">
                                                    Published
                                                </SelectItem>
                                                <SelectItem value="archived">
                                                    Archived
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* SEO Settings */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>SEO Settings</CardTitle>
                                    <CardDescription>
                                        Optimize your page for search engines
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    <div className="space-y-2">
                                        <Label htmlFor="meta_title">Meta Title</Label>
                                        <Input
                                            id="meta_title"
                                            type="text"
                                            value={data.meta_title}
                                            onChange={(e) =>
                                                setData('meta_title', e.target.value)
                                            }
                                            maxLength={60}
                                        />
                                        {errors.meta_title && (
                                            <p className="text-sm text-destructive">
                                                {errors.meta_title}
                                            </p>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            Recommended: 50-60 characters
                                        </p>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="meta_description">
                                            Meta Description
                                        </Label>
                                        <Textarea
                                            id="meta_description"
                                            value={data.meta_description}
                                            onChange={(e) =>
                                                setData('meta_description', e.target.value)
                                            }
                                            rows={3}
                                            maxLength={160}
                                        />
                                        {errors.meta_description && (
                                            <p className="text-sm text-destructive">
                                                {errors.meta_description}
                                            </p>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            Recommended: 150-160 characters
                                        </p>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="meta_keywords">Meta Keywords</Label>
                                        <Input
                                            id="meta_keywords"
                                            type="text"
                                            value={data.meta_keywords}
                                            onChange={(e) =>
                                                setData('meta_keywords', e.target.value)
                                            }
                                        />
                                        {errors.meta_keywords && (
                                            <p className="text-sm text-destructive">
                                                {errors.meta_keywords}
                                            </p>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            Comma-separated keywords
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Save Button */}
                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing || !isDirty}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                                {isDirty && (
                                    <p className="text-sm text-muted-foreground">
                                        You have unsaved changes
                                    </p>
                                )}
                            </div>
                        </form>
                    </div>
                )}

                {/* Content Blocks Tab */}
                {activeTab === 'content' && (
                    <BlockEditor pageId={page.id} blocks={page.blocks} />
                )}
            </div>
        </AppLayout>
    );
}
