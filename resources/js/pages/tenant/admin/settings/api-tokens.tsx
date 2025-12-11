import admin from '@/routes/tenant/admin';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import AdminLayout from '@/layouts/tenant/admin-layout';
import apiTokens from '@/routes/tenant/api/tokens';
import { Head, useForm, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Copy, Key, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type ApiTokenResource } from '@/types';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface Props {
    tokens: ApiTokenResource[];
    newToken?: string;
}

function ApiTokensSettings({ tokens, newToken }: Props) {
    const { t } = useLaravelReactI18n();
    const [showNewToken, setShowNewToken] = useState(!!newToken);
    const [copied, setCopied] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('settings.title'), href: admin.settings.index.url() },
        { title: 'API Tokens', href: admin.settings.apiTokens.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(apiTokens.store.url(), {
            onSuccess: () => {
                reset();
                setShowNewToken(true);
            },
        });
    };

    const handleDelete = (tokenId: string) => {
        router.delete(apiTokens.destroy.url(tokenId));
    };

    const copyToClipboard = () => {
        if (newToken) {
            navigator.clipboard.writeText(newToken);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    return (
        <>
            <Head title="API Tokens" />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Key}>{t('settings.api_tokens')}</PageTitle>
                        <PageDescription>
                            {t('settings.api_tokens_description')}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>

                {/* New Token Alert */}
                {newToken && showNewToken && (
                    <Card className="border-green-500 bg-green-50 dark:bg-green-950">
                        <CardHeader>
                            <CardTitle className="text-green-700 dark:text-green-400">
                                {t('settings.token_created')}
                            </CardTitle>
                            <CardDescription>
                                {t('settings.token_copy_warning')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex gap-2">
                                <Input
                                    value={newToken}
                                    readOnly
                                    className="font-mono"
                                />
                                <Button
                                    variant="outline"
                                    onClick={copyToClipboard}
                                >
                                    <Copy className="mr-2 h-4 w-4" />
                                    {copied ? t('settings.copied') : t('settings.copy')}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Create Token */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('settings.create_new_token')}</CardTitle>
                        <CardDescription>
                            {t('settings.create_token_description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="flex gap-4">
                            <div className="flex-1 space-y-2">
                                <Label htmlFor="name" className="sr-only">
                                    {t('settings.token_name')}
                                </Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder={t('settings.token_name_placeholder')}
                                    className={
                                        errors.name ? 'border-red-500' : ''
                                    }
                                />
                                {errors.name && (
                                    <p className="text-sm text-red-500">
                                        {errors.name}
                                    </p>
                                )}
                            </div>
                            <Button type="submit" disabled={processing}>
                                <Plus className="mr-2 h-4 w-4" />
                                {t('settings.create_token')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {/* Tokens List */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('settings.active_tokens')}</CardTitle>
                        <CardDescription>
                            {tokens.length === 0
                                ? t('settings.no_tokens')
                                : t('settings.tokens_count', { count: tokens.length })}
                        </CardDescription>
                    </CardHeader>
                    {tokens.length > 0 && (
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('common.name')}</TableHead>
                                        <TableHead>{t('settings.last_used')}</TableHead>
                                        <TableHead>{t('settings.created_at')}</TableHead>
                                        <TableHead className="text-right">
                                            {t('common.actions')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {tokens.map((token) => (
                                        <TableRow key={token.id}>
                                            <TableCell className="font-medium">
                                                {token.name}
                                            </TableCell>
                                            <TableCell>
                                                {token.last_used_at
                                                    ? new Date(
                                                          token.last_used_at,
                                                      ).toLocaleDateString(
                                                          'pt-BR',
                                                      )
                                                    : t('settings.never_used')}
                                            </TableCell>
                                            <TableCell>
                                                {new Date(
                                                    token.created_at,
                                                ).toLocaleDateString('pt-BR')}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <AlertDialog>
                                                    <AlertDialogTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </AlertDialogTrigger>
                                                    <AlertDialogContent>
                                                        <AlertDialogHeader>
                                                            <AlertDialogTitle>
                                                                {t('settings.revoke_token_title')}
                                                            </AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                {t('settings.revoke_token_description', { name: token.name })}
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <AlertDialogFooter>
                                                            <AlertDialogCancel>
                                                                {t('common.cancel')}
                                                            </AlertDialogCancel>
                                                            <AlertDialogAction
                                                                onClick={() =>
                                                                    handleDelete(
                                                                        token.id,
                                                                    )
                                                                }
                                                            >
                                                                {t('settings.revoke')}
                                                            </AlertDialogAction>
                                                        </AlertDialogFooter>
                                                    </AlertDialogContent>
                                                </AlertDialog>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    )}
                </Card>

                {/* Documentation */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('settings.api_usage')}</CardTitle>
                        <CardDescription>
                            {t('settings.api_usage_description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            {t('settings.api_usage_instructions')}
                        </p>
                        <pre className="bg-muted p-4 rounded-lg text-sm overflow-x-auto">
                            {`curl -X GET \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Accept: application/json" \\
  https://tenant.setor3.app/api/projects`}
                        </pre>
                    </CardContent>
                </Card>
                </PageContent>
            </Page>
        </>
    );
}

ApiTokensSettings.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default ApiTokensSettings;
