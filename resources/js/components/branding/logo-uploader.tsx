import { Upload, X, Image as ImageIcon } from 'lucide-react';
import { useCallback, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

interface LogoUploaderProps {
    label: string;
    description?: string;
    currentUrl?: string | null;
    onFileChange: (file: File | null) => void;
    maxSize?: number; // in MB
    accept?: string;
}

export function LogoUploader({
    label,
    description,
    currentUrl,
    onFileChange,
    maxSize = 2,
    accept = 'image/png,image/jpeg,image/jpg,image/svg+xml',
}: LogoUploaderProps) {
    const [preview, setPreview] = useState<string | null>(currentUrl || null);
    const [dragActive, setDragActive] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const validateFile = useCallback(
        (file: File): boolean => {
            setError(null);

            // Check file type
            const validTypes = accept.split(',');
            if (!validTypes.includes(file.type)) {
                setError('Invalid file type. Please upload a PNG, JPG, or SVG file.');
                return false;
            }

            // Check file size (convert MB to bytes)
            const maxSizeBytes = maxSize * 1024 * 1024;
            if (file.size > maxSizeBytes) {
                setError(`File size must be less than ${maxSize}MB`);
                return false;
            }

            return true;
        },
        [accept, maxSize]
    );

    const handleFile = useCallback(
        (file: File) => {
            if (!validateFile(file)) {
                return;
            }

            // Create preview
            const reader = new FileReader();
            reader.onloadend = () => {
                setPreview(reader.result as string);
            };
            reader.readAsDataURL(file);

            onFileChange(file);
        },
        [onFileChange, validateFile]
    );

    const handleDrag = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === 'dragenter' || e.type === 'dragover') {
            setDragActive(true);
        } else if (e.type === 'dragleave') {
            setDragActive(false);
        }
    }, []);

    const handleDrop = useCallback(
        (e: React.DragEvent) => {
            e.preventDefault();
            e.stopPropagation();
            setDragActive(false);

            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                handleFile(e.dataTransfer.files[0]);
            }
        },
        [handleFile]
    );

    const handleChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>) => {
            e.preventDefault();
            if (e.target.files && e.target.files[0]) {
                handleFile(e.target.files[0]);
            }
        },
        [handleFile]
    );

    const handleRemove = useCallback(() => {
        setPreview(null);
        setError(null);
        onFileChange(null);
    }, [onFileChange]);

    return (
        <div className="space-y-2">
            <div>
                <label className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                    {label}
                </label>
                {description && (
                    <p className="text-sm text-muted-foreground mt-1">{description}</p>
                )}
            </div>

            {preview ? (
                <Card className="relative overflow-hidden">
                    <div className="p-4">
                        <div className="flex items-center gap-4">
                            <div className="w-24 h-24 bg-muted rounded-lg flex items-center justify-center overflow-hidden">
                                <img
                                    src={preview}
                                    alt="Preview"
                                    className="max-w-full max-h-full object-contain"
                                />
                            </div>
                            <div className="flex-1">
                                <p className="text-sm font-medium">Current {label}</p>
                                <p className="text-xs text-muted-foreground mt-1">
                                    Click remove to upload a new file
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleRemove}
                            >
                                <X className="h-4 w-4 mr-2" />
                                Remove
                            </Button>
                        </div>
                    </div>
                </Card>
            ) : (
                <div
                    className={cn(
                        'relative border-2 border-dashed rounded-lg p-8 transition-colors',
                        dragActive
                            ? 'border-primary bg-primary/5'
                            : 'border-muted-foreground/25 hover:border-muted-foreground/50',
                        error && 'border-destructive'
                    )}
                    onDragEnter={handleDrag}
                    onDragLeave={handleDrag}
                    onDragOver={handleDrag}
                    onDrop={handleDrop}
                >
                    <input
                        type="file"
                        className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                        accept={accept}
                        onChange={handleChange}
                    />
                    <div className="flex flex-col items-center justify-center text-center space-y-4">
                        <div className="w-16 h-16 rounded-full bg-muted flex items-center justify-center">
                            {dragActive ? (
                                <Upload className="h-8 w-8 text-primary" />
                            ) : (
                                <ImageIcon className="h-8 w-8 text-muted-foreground" />
                            )}
                        </div>
                        <div>
                            <p className="text-sm font-medium">
                                {dragActive ? 'Drop file here' : 'Drag & drop or click to upload'}
                            </p>
                            <p className="text-xs text-muted-foreground mt-1">
                                PNG, JPG or SVG (max {maxSize}MB)
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {error && (
                <p className="text-sm text-destructive">{error}</p>
            )}
        </div>
    );
}
