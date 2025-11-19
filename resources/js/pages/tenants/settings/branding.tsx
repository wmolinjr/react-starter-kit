import { BrandingPreview } from '@/components/branding/branding-preview';
import { ColorPicker } from '@/components/branding/color-picker';
import { LogoUploader } from '@/components/branding/logo-uploader';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import tenants from '@/routes/tenants';
import type { BreadcrumbItem, Tenant } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Loader2, Save } from 'lucide-react';
import { useEffect, useState } from 'react';

interface BrandingPageProps {
    tenant: Tenant;
}

export default function BrandingPage({ tenant }: BrandingPageProps) {
    const [logoFile, setLogoFile] = useState<File | null>(null);
    const [faviconFile, setFaviconFile] = useState<File | null>(null);
    const [logoPreview, setLogoPreview] = useState<string | null>(
        tenant.logo_url || null
    );

    const { data, setData, post, processing, errors } = useForm({
        description: tenant.description || '',
        primary_color: tenant.primary_color || '#000000',
        logo: null as File | null,
        favicon: null as File | null,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Workspaces',
            href: tenants.index().url,
        },
        {
            title: tenant.name,
            href: tenants.show({ slug: tenant.slug }).url,
        },
        {
            title: 'Branding',
            href: tenants.show({ slug: tenant.slug }).url + '/branding',
        },
    ];

    // Update logo preview when file changes
    useEffect(() => {
        if (logoFile) {
            const reader = new FileReader();
            reader.onloadend = () => {
                setLogoPreview(reader.result as string);
            };
            reader.readAsDataURL(logoFile);
        }
    }, [logoFile]);

    const handleLogoChange = (file: File | null) => {
        setLogoFile(file);
        setData('logo', file);

        // If logo is removed, reset preview
        if (!file) {
            setLogoPreview(tenant.logo_url || null);
        }
    };

    const handleFaviconChange = (file: File | null) => {
        setFaviconFile(file);
        setData('favicon', file);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Create FormData for multipart upload
        const formData = new FormData();
        formData.append('description', data.description);
        formData.append('primary_color', data.primary_color);

        if (logoFile) {
            formData.append('logo', logoFile);
        }

        if (faviconFile) {
            formData.append('favicon', faviconFile);
        }

        // Use router.post with forceFormData for Inertia v2
        router.post(
            tenants.branding.update({ slug: tenant.slug }).url,
            formData,
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    // Reset file states on success
                    setLogoFile(null);
                    setFaviconFile(null);
                },
                onError: (errors) => {
                    console.error('Failed to update branding:', errors);
                },
            }
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Branding - ${tenant.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-2 mb-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => router.visit(tenants.show({ slug: tenant.slug }).url)}
                            >
                                <ArrowLeft className="h-4 w-4 mr-1" />
                                Back
                            </Button>
                        </div>
                        <h1 className="text-3xl font-bold tracking-tight">Workspace Branding</h1>
                        <p className="text-muted-foreground mt-2">
                            Customize the visual identity of your workspace with logo, colors, and description.
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Left Column - Form Fields */}
                        <div className="space-y-6">
                            {/* Description */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Description</CardTitle>
                                    <CardDescription>
                                        A brief description of your workspace
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        <Label htmlFor="description">Workspace Description</Label>
                                        <Textarea
                                            id="description"
                                            placeholder="Enter a brief description of your workspace..."
                                            value={data.description}
                                            onChange={(e) => setData('description', e.target.value)}
                                            rows={4}
                                            maxLength={500}
                                        />
                                        <div className="flex justify-between text-xs text-muted-foreground">
                                            <span>Maximum 500 characters</span>
                                            <span>{data.description.length}/500</span>
                                        </div>
                                        {errors.description && (
                                            <p className="text-sm text-destructive">{errors.description}</p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Logo Upload */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Logo</CardTitle>
                                    <CardDescription>
                                        Upload your workspace logo (PNG, JPG, or SVG)
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <LogoUploader
                                        label="Workspace Logo"
                                        description="Recommended size: 512x512px or larger. Max file size: 2MB"
                                        currentUrl={logoFile ? undefined : tenant.logo_url}
                                        onFileChange={handleLogoChange}
                                        maxSize={2}
                                        accept="image/png,image/jpeg,image/jpg,image/svg+xml"
                                    />
                                    {errors.logo && (
                                        <p className="text-sm text-destructive mt-2">{errors.logo}</p>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Favicon Upload */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Favicon</CardTitle>
                                    <CardDescription>
                                        Upload your workspace favicon (ICO or PNG)
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <LogoUploader
                                        label="Favicon"
                                        description="Recommended: 32x32px or 16x16px ICO/PNG. Max file size: 512KB"
                                        currentUrl={faviconFile ? undefined : tenant.favicon_url}
                                        onFileChange={handleFaviconChange}
                                        maxSize={0.5}
                                        accept="image/x-icon,image/png"
                                    />
                                    {errors.favicon && (
                                        <p className="text-sm text-destructive mt-2">{errors.favicon}</p>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Primary Color */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Primary Color</CardTitle>
                                    <CardDescription>
                                        Choose the primary color for your workspace
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ColorPicker
                                        label="Brand Color"
                                        description="This color will be used for buttons, links, and other accent elements"
                                        value={data.primary_color}
                                        onChange={(color) => setData('primary_color', color || '#000000')}
                                    />
                                    {errors.primary_color && (
                                        <p className="text-sm text-destructive mt-2">
                                            {errors.primary_color}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Submit Button */}
                            <div className="flex justify-end gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => router.visit(tenants.show({ slug: tenant.slug }).url)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <>
                                            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                            Saving...
                                        </>
                                    ) : (
                                        <>
                                            <Save className="h-4 w-4 mr-2" />
                                            Save Branding
                                        </>
                                    )}
                                </Button>
                            </div>
                        </div>

                        {/* Right Column - Preview */}
                        <div className="lg:sticky lg:top-6 lg:self-start">
                            <BrandingPreview
                                name={tenant.name}
                                logo={logoPreview}
                                primaryColor={data.primary_color}
                                description={data.description}
                            />
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
