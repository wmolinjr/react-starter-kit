import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/central/admin-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Trash2, Eye, Search } from 'lucide-react';
import { useState, type ReactElement } from 'react';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import admin from '@/routes/central/admin';
import { type BreadcrumbItem, type CentralUserResource, type InertiaPaginatedResponse } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';

interface Props {
    users: InertiaPaginatedResponse<CentralUserResource>;
    filters: { search?: string };
}

function UsersIndex({ users, filters }: Props) {
    const { t } = useLaravelReactI18n();
    const [search, setSearch] = useState(filters.search || '');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('users.page.title'), href: admin.users.index.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/users', { search }, { preserveState: true });
    };

    const handleDelete = (userId: string) => {
        if (confirm(t('users.page.delete_confirm'))) {
            router.delete(`/admin/users/${userId}`);
        }
    };

    return (
        <>
            <Head title={t('users.page.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('users.page.title')}</PageTitle>
                        <PageDescription>{t('users.page.description')}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle>{t('users.page.all_users')}</CardTitle>
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <Input
                                    placeholder={t('users.page.search_placeholder')}
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="w-64"
                                />
                                <Button type="submit" variant="outline" size="icon">
                                    <Search className="h-4 w-4" />
                                </Button>
                            </form>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead>{t('common.email')}</TableHead>
                                    <TableHead>{t('common.role')}</TableHead>
                                    <TableHead>{t('users.page.verified')}</TableHead>
                                    <TableHead>{t('common.created')}</TableHead>
                                    <TableHead className="w-24">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.data.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell className="font-medium">{user.name}</TableCell>
                                        <TableCell>{user.email}</TableCell>
                                        <TableCell>
                                            {user.role ? (
                                                <Badge variant={user.role === 'super-admin' ? 'default' : 'secondary'}>
                                                    {user.role_display_name || user.role}
                                                </Badge>
                                            ) : (
                                                <Badge variant="outline">{t('common.no_role')}</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {user.email_verified_at ? (
                                                <Badge variant="default">{t('users.page.verified')}</Badge>
                                            ) : (
                                                <Badge variant="secondary">{t('users.page.pending')}</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {new Date(user.created_at).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex gap-1">
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={`/admin/users/${user.id}`}>
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => handleDelete(user.id)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {users.data.length === 0 && (
                            <div className="py-12 text-center">
                                <p className="text-muted-foreground">{t('users.page.no_users')}</p>
                            </div>
                        )}

                        {users.last_page > 1 && (
                            <div className="mt-4 flex justify-center gap-2">
                                {users.links.map((link, i) => (
                                    <Button
                                        key={i}
                                        variant={link.active ? 'default' : 'outline'}
                                        size="sm"
                                        disabled={!link.url}
                                        onClick={() => link.url && router.get(link.url)}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
                </PageContent>
            </Page>
        </>
    );
}

UsersIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default UsersIndex;
