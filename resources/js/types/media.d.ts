/**
 * Media Library Types
 *
 * Type definitions for Spatie Media Library integration
 */

export interface Media {
    id: number;
    tenant_id: number | null;
    model_type: string;
    model_id: number;
    uuid: string | null;
    collection_name: string;
    name: string;
    file_name: string;
    mime_type: string | null;
    disk: string;
    conversions_disk: string | null;
    size: number;
    manipulations: Record<string, any>;
    custom_properties: Record<string, any>;
    generated_conversions: Record<string, boolean>;
    responsive_images: Record<string, any>;
    order_column: number | null;
    created_at: string;
    updated_at: string;
}

export interface MediaCollection {
    name: string;
    count: number;
}

export interface MediaUploadProgress {
    loaded: number;
    total: number;
    percentage: number;
}

export interface MediaUploadResponse {
    message: string;
    data: Media;
}

export interface MediaListResponse {
    data: Media[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export interface MediaFilters {
    collection?: string;
    type?: 'images' | 'videos' | 'documents';
    search?: string;
    sort_by?: 'created_at' | 'name' | 'size';
    sort_direction?: 'asc' | 'desc';
    per_page?: number;
    page?: number;
}

export interface MediaUploadOptions {
    collection?: string;
    name?: string;
    model_type?: string;
    model_id?: number;
    onProgress?: (progress: MediaUploadProgress) => void;
}

export interface MediaConversion {
    name: string;
    width?: number;
    height?: number;
    format?: string;
}

// Utility types
export type MediaType = 'image' | 'video' | 'document' | 'other';

export interface MediaTypeInfo {
    type: MediaType;
    icon: string;
    label: string;
    accepts: string;
}

// Form types for MediaPicker
export interface MediaPickerProps {
    collection?: string;
    multiple?: boolean;
    accept?: string;
    maxSize?: number; // in MB
    value?: Media | Media[] | null;
    onChange: (media: Media | Media[] | null) => void;
    onError?: (error: string) => void;
}

// Form types for MediaUpload
export interface MediaUploadProps {
    collection?: string;
    accept?: string;
    maxSize?: number; // in MB
    multiple?: boolean;
    onUploadComplete?: (media: Media[]) => void;
    onError?: (error: string) => void;
}
