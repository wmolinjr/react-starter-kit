import { Head, Link, useForm, router } from '@inertiajs/react';
import { AppLayout } from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
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
import { ArrowLeft, Upload, Download, Trash2, Image as ImageIcon, Paperclip } from 'lucide-react';
import { FormEvent, useRef, useState } from 'react';

interface Media {
  id: number;
  name: string;
  size: string;
  mime_type?: string;
  url: string;
  thumb_url?: string;
}

interface Project {
  id: number;
  name: string;
  description: string | null;
  status: string;
  user: {
    id: number;
    name: string;
  };
  created_at: string;
  attachments: Media[];
  images: Media[];
}

interface ProjectShowProps {
  project: Project;
}

export default function ProjectShow({ project }: ProjectShowProps) {
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

  const handleDelete = (mediaId: number) => {
    if (!confirm('Are you sure you want to delete this file?')) return;

    router.delete(`/projects/${project.id}/media/${mediaId}`, {
      preserveScroll: true,
    });
  };

  return (
    <AppLayout>
      <Head title={project.name} />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <Button variant="ghost" size="icon" asChild>
              <Link href="/projects">
                <ArrowLeft className="h-4 w-4" />
              </Link>
            </Button>
            <div>
              <h1 className="text-3xl font-bold tracking-tight">{project.name}</h1>
              <p className="text-muted-foreground">
                Created by {project.user.name} on {project.created_at}
              </p>
            </div>
          </div>
          <Badge variant={project.status === 'active' ? 'default' : 'secondary'}>
            {project.status}
          </Badge>
        </div>

        {/* Description */}
        {project.description && (
          <Card>
            <CardHeader>
              <CardTitle>Description</CardTitle>
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
                  Attachments ({project.attachments.length})
                </CardTitle>
                <CardDescription>Upload files up to 10MB</CardDescription>
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
                  {uploadingAttachment ? 'Uploading...' : 'Upload File'}
                </Button>
              </form>
            </div>
          </CardHeader>
          <CardContent>
            {project.attachments.length === 0 ? (
              <p className="text-center text-muted-foreground py-8">
                No attachments yet. Upload your first file!
              </p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Size</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
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
                            Download
                          </a>
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleDelete(file.id)}
                        >
                          <Trash2 className="mr-2 h-4 w-4" />
                          Delete
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
                  Images ({project.images.length})
                </CardTitle>
                <CardDescription>Upload images up to 10MB</CardDescription>
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
                  {uploadingImage ? 'Uploading...' : 'Upload Image'}
                </Button>
              </form>
            </div>
          </CardHeader>
          <CardContent>
            {project.images.length === 0 ? (
              <p className="text-center text-muted-foreground py-8">
                No images yet. Upload your first image!
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
      </div>
    </AppLayout>
  );
}
