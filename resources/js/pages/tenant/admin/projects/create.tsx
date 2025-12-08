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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/tenant/admin-layout';
import admin from '@/routes/tenant/admin';
import { Head, Link, useForm } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { FolderPlus } from 'lucide-react';
import { FormEvent, type ReactElement } from 'react';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';

function ProjectCreate() {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('tenant.projects.title'), href: admin.projects.index.url() },
        { title: t('tenant.projects.create_project'), href: admin.projects.create.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        status: 'active',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(admin.projects.store.url());
    };

    return (
        <>
            <Head title={t('tenant.projects.new_project')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={FolderPlus}>{t('tenant.projects.new_project')}</PageTitle>
                        <PageDescription>
                            {t('tenant.projects.new_project_description')}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <Card>
                    <CardHeader>
                        <CardTitle>{t('tenant.projects.project_info')}</CardTitle>
                        <CardDescription>
                            {t('tenant.projects.fill_project_data')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="name">{t('common.name')} *</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder={t('tenant.projects.name_placeholder')}
                                    className={errors.name ? 'border-red-500' : ''}
                                />
                                {errors.name && (
                                    <p className="text-sm text-red-500">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">{t('common.description')}</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    placeholder={t('tenant.projects.description_placeholder')}
                                    rows={4}
                                    className={
                                        errors.description ? 'border-red-500' : ''
                                    }
                                />
                                {errors.description && (
                                    <p className="text-sm text-red-500">
                                        {errors.description}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="status">Status *</Label>
                                <Select
                                    value={data.status}
                                    onValueChange={(value) =>
                                        setData('status', value)
                                    }
                                >
                                    <SelectTrigger
                                        className={
                                            errors.status ? 'border-red-500' : ''
                                        }
                                    >
                                        <SelectValue placeholder={t('tenant.projects.select_status')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">
                                            {t('tenant.projects.status_active')}
                                        </SelectItem>
                                        <SelectItem value="archived">
                                            {t('tenant.projects.status_archived')}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.status && (
                                    <p className="text-sm text-red-500">
                                        {errors.status}
                                    </p>
                                )}
                            </div>

                            <div className="flex justify-end gap-4">
                                <Button variant="outline" asChild>
                                    <Link href={admin.projects.index.url()}>{t('common.cancel')}</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? t('tenant.projects.creating') : t('tenant.projects.create_project')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
                </PageContent>
            </Page>
        </>
    );
}

ProjectCreate.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default ProjectCreate;
