import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Can } from '@/components/shared/auth/can';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/tenant/admin-layout';
import admin from '@/routes/tenant/admin';
import { Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { FolderOpen, Plus, Search } from 'lucide-react';
import { Page, PageHeader, PageHeaderContent, PageHeaderActions, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type ProjectResource } from '@/types';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface Props {
    projects: ProjectResource[];
}

function ProjectsIndex({ projects: projectsList }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('tenant.projects.title'), href: admin.projects.index.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    return (
        <>
            <Head title={t('tenant.projects.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={FolderOpen}>{t('tenant.projects.title')}</PageTitle>
                        <PageDescription>
                            {t('tenant.projects.description')}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Can permission="projects:create">
                            <Link href={admin.projects.create.url()}>
                                <Button>
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('tenant.projects.new_project')}
                                </Button>
                            </Link>
                        </Can>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    {/* Search */}
                    <div className="flex items-center gap-4">
                        <div className="relative flex-1 max-w-md">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder={t('tenant.projects.search_placeholder')}
                                className="pl-9"
                            />
                        </div>
                    </div>

                    {/* Projects Grid */}
                    {projectsList.length === 0 ? (
                        <Card>
                            <CardContent className="flex flex-col items-center justify-center py-12">
                                <FolderOpen className="h-12 w-12 text-muted-foreground mb-4" />
                                <h3 className="text-lg font-semibold mb-2">{t('tenant.projects.no_projects')}</h3>
                                <p className="text-muted-foreground text-center mb-4">
                                    {t('tenant.projects.no_projects_description')}
                                </p>
                                <Can permission="projects:create">
                                    <Link href={admin.projects.create.url()}>
                                        <Button>
                                            <Plus className="mr-2 h-4 w-4" />
                                            {t('tenant.projects.create_project')}
                                        </Button>
                                    </Link>
                                </Can>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {projectsList.map((project) => (
                                <Link key={project.id} href={`/projects/${project.id}`}>
                                    <Card className="hover:border-primary transition-colors cursor-pointer h-full">
                                        <CardHeader>
                                            <div className="flex items-start justify-between">
                                                <CardTitle className="text-lg">{project.name}</CardTitle>
                                                <Badge variant={project.status === 'active' ? 'default' : 'secondary'}>
                                                    {project.status === 'active' ? t('tenant.projects.status_active') : t('tenant.projects.status_archived')}
                                                </Badge>
                                            </div>
                                            {project.description && (
                                                <CardDescription className="line-clamp-2">
                                                    {project.description}
                                                </CardDescription>
                                            )}
                                        </CardHeader>
                                        <CardContent>
                                            <p className="text-xs text-muted-foreground">
                                                {t('tenant.projects.created_at', { date: new Date(project.created_at).toLocaleDateString('pt-BR') })}
                                            </p>
                                        </CardContent>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                    )}
                </PageContent>
            </Page>
        </>
    );
}

ProjectsIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default ProjectsIndex;
