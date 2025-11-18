import { Head, Link, router } from '@inertiajs/react';
import { PageListItem } from '@/types';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Eye, FileEdit, Globe, MoreVertical, Plus, Trash2 } from 'lucide-react';
import pagesRoutes from '@/routes/pages';

interface Props {
    pages: PageListItem[];
}

export default function PagesIndex({ pages }: Props) {
    const handlePublish = (pageId: number) => {
        router.post(
            pagesRoutes.publish({ page: pageId }).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    // Success handled by backend flash message
                },
            }
        );
    };

    const handleUnpublish = (pageId: number) => {
        router.post(
            pagesRoutes.unpublish({ page: pageId }).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    // Success handled by backend flash message
                },
            }
        );
    };

    const handleDelete = (pageId: number, title: string) => {
        if (
            confirm(
                `Are you sure you want to delete "${title}"? This action cannot be undone.`
            )
        ) {
            router.delete(pagesRoutes.destroy({ page: pageId }).url, {
                preserveScroll: true,
            });
        }
    };

    const getStatusBadge = (status: PageListItem['status']) => {
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
            month: 'short',
            day: 'numeric',
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Pages', href: pagesRoutes.index().url },
            ]}
        >
            <Head title="Pages" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Pages</h1>
                        <p className="mt-2 text-muted-foreground">
                            Manage your website pages and content
                        </p>
                    </div>
                    <Link href={pagesRoutes.create().url}>
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            New Page
                        </Button>
                    </Link>
                </div>

                {/* Pages Table */}
                {pages.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-12 text-center">
                        <div className="mx-auto flex max-w-[420px] flex-col items-center justify-center text-center">
                            <FileEdit className="h-10 w-10 text-muted-foreground" />
                            <h3 className="mt-4 text-lg font-semibold">No pages yet</h3>
                            <p className="mb-4 mt-2 text-sm text-muted-foreground">
                                You haven't created any pages yet. Start building your
                                website by creating your first page.
                            </p>
                            <Link href={pagesRoutes.create().url}>
                                <Button>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Create Page
                                </Button>
                            </Link>
                        </div>
                    </div>
                ) : (
                    <div className="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Title</TableHead>
                                    <TableHead>Slug</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Blocks</TableHead>
                                    <TableHead>Created By</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead className="w-[70px]"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pages.map((page) => (
                                    <TableRow key={page.id}>
                                        <TableCell className="font-medium">
                                            {page.title}
                                        </TableCell>
                                        <TableCell className="font-mono text-sm text-muted-foreground">
                                            /{page.slug}
                                        </TableCell>
                                        <TableCell>{getStatusBadge(page.status)}</TableCell>
                                        <TableCell>
                                            <span className="text-sm text-muted-foreground">
                                                {page.blocks_count} blocks
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {page.created_by?.name || (
                                                <span className="text-muted-foreground">
                                                    Unknown
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm text-muted-foreground">
                                            {formatDate(page.created_at)}
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8"
                                                    >
                                                        <MoreVertical className="h-4 w-4" />
                                                        <span className="sr-only">
                                                            Open menu
                                                        </span>
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={
                                                                pagesRoutes.edit({
                                                                    page: page.id,
                                                                }).url
                                                            }
                                                        >
                                                            <FileEdit className="mr-2 h-4 w-4" />
                                                            Edit
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={
                                                                pagesRoutes.show({
                                                                    page: page.id,
                                                                }).url
                                                            }
                                                        >
                                                            <Eye className="mr-2 h-4 w-4" />
                                                            Preview
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    {page.status === 'published' ? (
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                handleUnpublish(page.id)
                                                            }
                                                        >
                                                            <Globe className="mr-2 h-4 w-4" />
                                                            Unpublish
                                                        </DropdownMenuItem>
                                                    ) : (
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                handlePublish(page.id)
                                                            }
                                                        >
                                                            <Globe className="mr-2 h-4 w-4" />
                                                            Publish
                                                        </DropdownMenuItem>
                                                    )}
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        className="text-destructive focus:text-destructive"
                                                        onClick={() =>
                                                            handleDelete(page.id, page.title)
                                                        }
                                                    >
                                                        <Trash2 className="mr-2 h-4 w-4" />
                                                        Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
