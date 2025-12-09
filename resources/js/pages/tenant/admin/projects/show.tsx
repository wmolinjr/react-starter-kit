import AdminLayout from '@/layouts/tenant/admin-layout';
import admin from '@/routes/tenant/admin';
import { Head, Link, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Button } from '@/components/ui/button';
import { Page, PageHeader, PageHeaderContent, PageHeaderActions, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type ProjectDetailResource } from '@/types';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Input } from '@/components/ui/input';
import { Upload, Download, Trash2, Image as ImageIcon, Paperclip } from 'lucide-react';
import { FormEvent, useRef, useState, type ReactElement } from 'react';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';

interface ProjectShowProps {
  project: ProjectDetailResource;
}

function ProjectShow({ project }: ProjectShowProps) {
  const { t } = useLaravelReactI18n();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
    { title: t('tenant.projects.title'), href: admin.projects.index.url() },
    { title: project.name, href: admin.projects.show.url(project.id) },
  ];

  useSetBreadcrumbs(breadcrumbs);

  const fileInputRef = useRef<HTMLInputElement>(null);
  const imageInputRef = useRef<HTMLInputElement>(null);
  const [uploadingAttachment, setUploadingAttachment] = useState(false);
  const [uploadingImage, setUploadingImage] = useState(false);

  const handleFileUpload = (e: FormEvent, collection: 'attachments' | 'images') => {
    e.preventDefault();
    const input = collection === 'attachments' ? fileInputRef.current : imageInputRef.current;
    const file = input?.files?.[0];

    if (!file) return;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('collection', collection);

    if (collection === 'attachments') {
      setUploadingAttachment(true);
    } else {
      setUploadingImage(true);
    }

    router.post(`/projects/${project.id}/media`, formData, {
      preserveScroll: true,
      onSuccess: () => {
        if (input) input.value = '';
        setUploadingAttachment(false);
        setUploadingImage(false);
      },
      onError: () => {
        setUploadingAttachment(false);
        setUploadingImage(false);
      },
    });
  };

  const handleDelete = (mediaId: string) => {
    if (!confirm(t('common.confirm_delete'))) return;

    router.delete(`/projects/${project.id}/media/${mediaId}`, {
      preserveScroll: true,
    });
  };

  return (
    <>
      <Head title={project.name} />

      <Page>
        <PageHeader>
          <PageHeaderContent>
            <PageTitle>{project.name}</PageTitle>
            <PageDescription>
              {t('tenant.projects.created_by', { name: project.user?.name ?? t('common.unknown'), date: project.created_at })}
            </PageDescription>
          </PageHeaderContent>
          <PageHeaderActions>
            <Badge variant={project.status === 'active' ? 'default' : 'secondary'}>
              {project.status === 'active' ? t('tenant.projects.status_active') : t('tenant.projects.status_archived')}
            </Badge>
          </PageHeaderActions>
        </PageHeader>

        <PageContent>

        {/* Description */}
        {project.description && (
          <Card>
            <CardHeader>
              <CardTitle>{t('common.description')}</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-muted-foreground">{project.description}</p>
            </CardContent>
          </Card>
        )}

        {/* Attachments */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="flex items-center gap-2">
                  <Paperclip className="h-5 w-5" />
                  {t('tenant.projects.attachments')} ({project.attachments.length})
                </CardTitle>
                <CardDescription>{t('tenant.projects.upload_files_limit')}</CardDescription>
              </div>
              <form onSubmit={(e) => handleFileUpload(e, 'attachments')}>
                <Input
                  ref={fileInputRef}
                  type="file"
                  className="hidden"
                  onChange={(e) => e.target.form?.requestSubmit()}
                />
                <Button
                  type="button"
                  size="sm"
                  onClick={() => fileInputRef.current?.click()}
                  disabled={uploadingAttachment}
                >
                  <Upload className="mr-2 h-4 w-4" />
                  {uploadingAttachment ? t('tenant.projects.uploading') : t('tenant.projects.upload_file')}
                </Button>
              </form>
            </div>
          </CardHeader>
          <CardContent>
            {project.attachments.length === 0 ? (
              <p className="text-center text-muted-foreground py-8">
                {t('tenant.projects.no_attachments')}
              </p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('common.name')}</TableHead>
                    <TableHead>{t('tenant.projects.size')}</TableHead>
                    <TableHead>{t('tenant.projects.type')}</TableHead>
                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {project.attachments.map((file) => (
                    <TableRow key={file.id}>
                      <TableCell className="font-medium">{file.name}</TableCell>
                      <TableCell>{file.size}</TableCell>
                      <TableCell>{file.mime_type}</TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm" asChild>
                          <a href={file.url}>
                            <Download className="mr-2 h-4 w-4" />
                            {t('tenant.projects.download')}
                          </a>
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleDelete(file.id)}
                        >
                          <Trash2 className="mr-2 h-4 w-4" />
                          {t('common.delete')}
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>

        {/* Images */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="flex items-center gap-2">
                  <ImageIcon className="h-5 w-5" />
                  {t('tenant.projects.images')} ({project.images.length})
                </CardTitle>
                <CardDescription>{t('tenant.projects.upload_images_limit')}</CardDescription>
              </div>
              <form onSubmit={(e) => handleFileUpload(e, 'images')}>
                <Input
                  ref={imageInputRef}
                  type="file"
                  accept="image/*"
                  className="hidden"
                  onChange={(e) => e.target.form?.requestSubmit()}
                />
                <Button
                  type="button"
                  size="sm"
                  onClick={() => imageInputRef.current?.click()}
                  disabled={uploadingImage}
                >
                  <Upload className="mr-2 h-4 w-4" />
                  {uploadingImage ? t('tenant.projects.uploading') : t('tenant.projects.upload_image')}
                </Button>
              </form>
            </div>
          </CardHeader>
          <CardContent>
            {project.images.length === 0 ? (
              <p className="text-center text-muted-foreground py-8">
                {t('tenant.projects.no_images')}
              </p>
            ) : (
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                {project.images.map((image) => (
                  <div key={image.id} className="relative group">
                    <img
                      src={image.thumb_url}
                      alt={image.name}
                      className="w-full aspect-square object-cover rounded-lg"
                    />
                    <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center gap-2">
                      <Button variant="secondary" size="sm" asChild>
                        <a href={image.url} download>
                          <Download className="h-4 w-4" />
                        </a>
                      </Button>
                      <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => handleDelete(image.id)}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                    <p className="mt-2 text-sm truncate">{image.name}</p>
                    <p className="text-xs text-muted-foreground">{image.size}</p>
                  </div>
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

ProjectShow.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default ProjectShow;
