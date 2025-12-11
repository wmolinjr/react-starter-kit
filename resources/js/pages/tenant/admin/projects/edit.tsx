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
import { FolderEdit } from 'lucide-react';
import { FormEvent, type ReactElement } from 'react';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type ProjectEditResource } from '@/types';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';

interface Props {
    project: ProjectEditResource;
}

function ProjectEdit({ project }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('projects.page.title'), href: admin.projects.index.url() },
        { title: project.name, href: admin.projects.show.url(project.id) },
        { title: t('common.edit'), href: admin.projects.edit.url(project.id) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const { data, setData, patch, processing, errors } = useForm({
        name: project.name,
        description: project.description || '',
        status: project.status,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        patch(admin.projects.update.url(project.id));
    };

    return (
        <>
            <Head title={t('projects.edit_project', { name: project.name })} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={FolderEdit}>{t('projects.page.edit_project_title')}</PageTitle>
                        <PageDescription>
                            {project.name}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <Card>
                    <CardHeader>
                        <CardTitle>{t('projects.page.project_info')}</CardTitle>
                        <CardDescription>
                            {t('projects.page.update_project_data')}
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
                                    placeholder={t('projects.page.name_placeholder')}
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
                                    placeholder={t('projects.page.description_placeholder')}
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
                                        <SelectValue placeholder={t('projects.page.select_status')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">
                                            {t('projects.page.status_active')}
                                        </SelectItem>
                                        <SelectItem value="archived">
                                            {t('projects.page.status_archived')}
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
                                    <Link href={admin.projects.show.url(project.id)}>
                                        {t('common.cancel')}
                                    </Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? t('common.saving') : t('common.save')}
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

ProjectEdit.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default ProjectEdit;
