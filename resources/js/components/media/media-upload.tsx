import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import type { Media, MediaUploadOptions } from '@/types/media';
import { router } from '@inertiajs/react';
import { Upload, X, FileImage, FileVideo, File as FileIcon } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useDropzone } from 'react-dropzone';

export interface MediaUploadProps {
    collection?: string;
    accept?: string;
    maxSize?: number; // in MB
    multiple?: boolean;
    onUploadComplete?: (media: Media[]) => void;
    onError?: (error: string) => void;
    className?: string;
}

interface UploadingFile {
    file: File;
    preview?: string;
    progress: number;
    error?: string;
}

export function MediaUpload({
    collection = 'default',
    accept = 'image/*',
    maxSize = 10, // 10MB default
    multiple = false,
    onUploadComplete,
    onError,
    className,
}: MediaUploadProps) {
    const [uploadingFiles, setUploadingFiles] = useState<UploadingFile[]>([]);

    const onDrop = useCallback(
        (acceptedFiles: File[]) => {
            // Filter by size
            const maxBytes = maxSize * 1024 * 1024;
            const validFiles = acceptedFiles.filter((file) => {
                if (file.size > maxBytes) {
                    onError?.(
                        `File ${file.name} is too large. Max size is ${maxSize}MB`
                    );
                    return false;
                }
                return true;
            });

            // Create preview URLs for images
            const newFiles: UploadingFile[] = validFiles.map((file) => ({
                file,
                preview: file.type.startsWith('image/')
                    ? URL.createObjectURL(file)
                    : undefined,
                progress: 0,
            }));

            // Capture the starting index before adding files
            const startIndex = uploadingFiles.length;

            setUploadingFiles((prev) => [...prev, ...newFiles]);

            // Upload each file with its absolute index
            validFiles.forEach((file, relativeIndex) => {
                uploadFile(file, startIndex + relativeIndex);
            });
        },
        [maxSize, onError, uploadingFiles.length]
    );

    const uploadFile = async (file: File, absoluteIndex: number) => {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('collection', collection);
        formData.append('name', file.name);

        try {
            // Using Inertia's router.post with preserveScroll
            router.post('/media', formData, {
                preserveScroll: true,
                onProgress: (progress) => {
                    const percentage = progress?.percentage ?? 0;
                    setUploadingFiles((prev) =>
                        prev.map((f, i) =>
                            i === absoluteIndex
                                ? { ...f, progress: percentage }
                                : f
                        )
                    );
                },
                onSuccess: (page) => {
                    // @ts-ignore - Inertia types
                    const uploadedMedia = page.props.flash?.data as Media;

                    setUploadingFiles((prev) =>
                        prev.filter((_, i) => i !== absoluteIndex)
                    );

                    if (uploadedMedia && onUploadComplete) {
                        onUploadComplete([uploadedMedia]);
                    }
                },
                onError: (errors) => {
                    setUploadingFiles((prev) =>
                        prev.map((f, i) =>
                            i === absoluteIndex
                                ? {
                                      ...f,
                                      error:
                                          typeof errors === 'string'
                                              ? errors
                                              : 'Upload failed',
                                  }
                                : f
                        )
                    );
                    onError?.(
                        typeof errors === 'string' ? errors : 'Upload failed'
                    );
                },
            });
        } catch (error) {
            setUploadingFiles((prev) =>
                prev.map((f, i) =>
                    i === absoluteIndex
                        ? { ...f, error: 'Upload failed' }
                        : f
                )
            );
            onError?.('Upload failed');
        }
    };

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        accept: accept ? { [accept]: [] } : undefined,
        multiple,
        maxSize: maxSize * 1024 * 1024,
    });

    const removeFile = (index: number) => {
        setUploadingFiles((prev) => {
            const newFiles = [...prev];
            if (newFiles[index].preview) {
                URL.revokeObjectURL(newFiles[index].preview!);
            }
            newFiles.splice(index, 1);
            return newFiles;
        });
    };

    const getFileIcon = (file: File) => {
        if (file.type.startsWith('image/')) return FileImage;
        if (file.type.startsWith('video/')) return FileVideo;
        return FileIcon;
    };

    return (
        <div className={cn('space-y-4', className)}>
            {/* Dropzone */}
            <div
                {...getRootProps()}
                className={cn(
                    'border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition-colors',
                    isDragActive
                        ? 'border-primary bg-primary/5'
                        : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                )}
            >
                <input {...getInputProps()} />
                <Upload className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                <p className="text-sm font-medium mb-1">
                    {isDragActive
                        ? 'Drop files here...'
                        : 'Drag & drop files here, or click to select'}
                </p>
                <p className="text-xs text-muted-foreground">
                    {accept === 'image/*' && 'Images only'}
                    {accept === 'video/*' && 'Videos only'}
                    {!accept && 'Any file type'} • Max {maxSize}MB
                    {multiple && ' • Multiple files allowed'}
                </p>
            </div>

            {/* Uploading Files */}
            {uploadingFiles.length > 0 && (
                <div className="space-y-2">
                    {uploadingFiles.map((uploadingFile, index) => {
                        const Icon = getFileIcon(uploadingFile.file);

                        return (
                            <div
                                key={index}
                                className="flex items-center gap-3 p-3 border rounded-lg"
                            >
                                {/* Preview or Icon */}
                                <div className="flex-shrink-0">
                                    {uploadingFile.preview ? (
                                        <img
                                            src={uploadingFile.preview}
                                            alt={uploadingFile.file.name}
                                            className="h-12 w-12 object-cover rounded"
                                        />
                                    ) : (
                                        <div className="h-12 w-12 flex items-center justify-center bg-muted rounded">
                                            <Icon className="h-6 w-6 text-muted-foreground" />
                                        </div>
                                    )}
                                </div>

                                {/* File Info */}
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium truncate">
                                        {uploadingFile.file.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {(uploadingFile.file.size / 1024 / 1024).toFixed(
                                            2
                                        )}{' '}
                                        MB
                                    </p>

                                    {/* Progress */}
                                    {!uploadingFile.error && (
                                        <Progress
                                            value={uploadingFile.progress}
                                            className="mt-2 h-1"
                                        />
                                    )}

                                    {/* Error */}
                                    {uploadingFile.error && (
                                        <p className="text-xs text-destructive mt-1">
                                            {uploadingFile.error}
                                        </p>
                                    )}
                                </div>

                                {/* Remove Button */}
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => removeFile(index)}
                                    className="flex-shrink-0"
                                >
                                    <X className="h-4 w-4" />
                                </Button>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
