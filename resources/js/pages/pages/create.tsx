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
import AppLayout from '@/layouts/app-layout';
import pages from '@/routes/pages';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, FileText } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Pages',
        href: pages.index().url,
    },
    {
        title: 'Create',
        href: pages.create().url,
    },
];

export default function PagesCreate() {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        slug: '',
        meta_title: '',
        meta_description: '',
        meta_keywords: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(pages.store().url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Page" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <a href={pages.index().url}>
                            <ArrowLeft className="h-4 w-4" />
                        </a>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold">Create Page</h1>
                        <p className="text-sm text-muted-foreground">
                            Create a new page for your website
                        </p>
                    </div>
                </div>

                <div className="mx-auto w-full max-w-2xl space-y-6">
                    {/* Basic Information */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <FileText className="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle>Basic Information</CardTitle>
                                    <CardDescription>
                                        Enter the basic details for your page
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-6">
                                <div className="space-y-2">
                                    <Label htmlFor="title">
                                        Page Title
                                        <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="title"
                                        type="text"
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                        placeholder="About Us"
                                        autoFocus
                                        required
                                    />
                                    {errors.title && (
                                        <p className="text-sm text-destructive">
                                            {errors.title}
                                        </p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        This is the main title of your page
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="slug">
                                        Slug
                                        <span className="ml-1 text-xs text-muted-foreground">
                                            (optional)
                                        </span>
                                    </Label>
                                    <Input
                                        id="slug"
                                        type="text"
                                        value={data.slug}
                                        onChange={(e) => setData('slug', e.target.value)}
                                        placeholder="about-us"
                                    />
                                    {errors.slug && (
                                        <p className="text-sm text-destructive">{errors.slug}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        URL-friendly identifier. Leave blank to auto-generate
                                        from title.
                                    </p>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    {/* SEO Settings */}
                    <Card>
                        <CardHeader>
                            <CardTitle>SEO Settings</CardTitle>
                            <CardDescription>
                                Optimize your page for search engines (optional)
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="meta_title">Meta Title</Label>
                                <Input
                                    id="meta_title"
                                    type="text"
                                    value={data.meta_title}
                                    onChange={(e) => setData('meta_title', e.target.value)}
                                    placeholder="About Our Company - Best Services"
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
                                <Label htmlFor="meta_description">Meta Description</Label>
                                <Textarea
                                    id="meta_description"
                                    value={data.meta_description}
                                    onChange={(e) =>
                                        setData('meta_description', e.target.value)
                                    }
                                    placeholder="Learn more about our company, mission, and the team behind our success..."
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
                                    onChange={(e) => setData('meta_keywords', e.target.value)}
                                    placeholder="about, company, team, services"
                                />
                                {errors.meta_keywords && (
                                    <p className="text-sm text-destructive">
                                        {errors.meta_keywords}
                                    </p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    Comma-separated keywords related to this page
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Form Actions */}
                    <div className="flex items-center gap-3">
                        <Button type="submit" onClick={submit} disabled={processing}>
                            {processing ? 'Creating...' : 'Create Page'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <a href={pages.index().url}>Cancel</a>
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
