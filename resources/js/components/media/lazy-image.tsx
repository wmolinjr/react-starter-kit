import { cn } from '@/lib/utils';
import type { Media } from '@/types/media';
import { FileImage } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface LazyImageProps {
    media: Media;
    conversion?: string; // e.g., 'thumb', 'medium', 'large'
    alt?: string;
    className?: string;
    aspectRatio?: 'square' | 'video' | 'auto'; // 1:1, 16:9, or auto
    objectFit?: 'cover' | 'contain' | 'fill' | 'none' | 'scale-down';
    onLoad?: () => void;
    onError?: () => void;
    priority?: boolean; // Skip lazy loading for above-the-fold images
}

export function LazyImage({
    media,
    conversion,
    alt,
    className,
    aspectRatio = 'auto',
    objectFit = 'cover',
    onLoad,
    onError,
    priority = false,
}: LazyImageProps) {
    const [isLoaded, setIsLoaded] = useState(false);
    const [isInView, setIsInView] = useState(priority);
    const [hasError, setHasError] = useState(false);
    const imgRef = useRef<HTMLDivElement>(null);

    // Intersection Observer for lazy loading
    useEffect(() => {
        if (priority || !imgRef.current) return;

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        setIsInView(true);
                        observer.disconnect();
                    }
                });
            },
            {
                rootMargin: '50px', // Start loading 50px before entering viewport
            }
        );

        observer.observe(imgRef.current);

        return () => {
            observer.disconnect();
        };
    }, [priority]);

    // Build image URL
    const getImageUrl = (conv?: string) => {
        const baseUrl = `/media/${media.id}/url`;
        return conv ? `${baseUrl}?conversion=${conv}` : baseUrl;
    };

    // Build responsive srcset
    const getSrcSet = () => {
        if (!media.responsive_images || Object.keys(media.responsive_images).length === 0) {
            return undefined;
        }

        // Generate srcset from responsive images
        // Format: "url width, url width, ..."
        const srcsetEntries = Object.entries(media.responsive_images).map(
            ([name, urls]) => {
                // Extract width from name (e.g., "400w" -> "400")
                const match = name.match(/(\d+)w?/);
                if (!match) return null;
                const width = match[1];
                return `${urls} ${width}w`;
            }
        );

        return srcsetEntries.filter(Boolean).join(', ');
    };

    // Handle image load
    const handleLoad = () => {
        setIsLoaded(true);
        onLoad?.();
    };

    // Handle image error
    const handleError = () => {
        setHasError(true);
        onError?.();
    };

    // Get aspect ratio class
    const getAspectRatioClass = () => {
        switch (aspectRatio) {
            case 'square':
                return 'aspect-square';
            case 'video':
                return 'aspect-video';
            default:
                return '';
        }
    };

    // Get object fit class
    const getObjectFitClass = () => {
        switch (objectFit) {
            case 'cover':
                return 'object-cover';
            case 'contain':
                return 'object-contain';
            case 'fill':
                return 'object-fill';
            case 'none':
                return 'object-none';
            case 'scale-down':
                return 'object-scale-down';
            default:
                return 'object-cover';
        }
    };

    return (
        <div
            ref={imgRef}
            className={cn(
                'relative overflow-hidden bg-muted',
                getAspectRatioClass(),
                className
            )}
        >
            {/* Error State */}
            {hasError ? (
                <div className="absolute inset-0 flex flex-col items-center justify-center text-muted-foreground">
                    <FileImage className="h-12 w-12 mb-2" />
                    <p className="text-xs">Failed to load image</p>
                </div>
            ) : (
                <>
                    {/* Loading Skeleton */}
                    {!isLoaded && isInView && (
                        <div className="absolute inset-0 animate-pulse bg-muted" />
                    )}

                    {/* Blur Placeholder (thumbnail) */}
                    {!isLoaded && isInView && conversion !== 'thumb' && (
                        <img
                            src={getImageUrl('thumb')}
                            alt=""
                            className={cn(
                                'absolute inset-0 w-full h-full blur-lg scale-110 transition-opacity duration-300',
                                getObjectFitClass(),
                                isLoaded ? 'opacity-0' : 'opacity-100'
                            )}
                            aria-hidden="true"
                        />
                    )}

                    {/* Main Image */}
                    {isInView && (
                        <img
                            src={getImageUrl(conversion)}
                            srcSet={getSrcSet()}
                            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
                            alt={alt || media.name}
                            className={cn(
                                'w-full h-full transition-opacity duration-300',
                                getObjectFitClass(),
                                isLoaded ? 'opacity-100' : 'opacity-0'
                            )}
                            onLoad={handleLoad}
                            onError={handleError}
                            loading={priority ? 'eager' : 'lazy'}
                        />
                    )}

                    {/* Loading Spinner (optional) */}
                    {!isLoaded && isInView && (
                        <div className="absolute inset-0 flex items-center justify-center">
                            <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

/**
 * Simple wrapper for displaying media images with automatic URL generation
 */
export interface MediaImageProps extends Omit<LazyImageProps, 'media'> {
    mediaId: number;
    media?: Media; // Optional Media object if available
}

export function MediaImage({ mediaId, media, ...props }: MediaImageProps) {
    const [loadedMedia, setLoadedMedia] = useState<Media | null>(media || null);
    const [loading, setLoading] = useState(!media);
    const [error, setError] = useState(false);

    // Fetch media data if not provided
    useEffect(() => {
        if (media) {
            setLoadedMedia(media);
            setLoading(false);
            return;
        }

        const fetchMedia = async () => {
            try {
                const response = await fetch(`/media/${mediaId}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch media');
                }

                const data = await response.json();
                setLoadedMedia(data.data);
            } catch (err) {
                console.error('Error fetching media:', err);
                setError(true);
            } finally {
                setLoading(false);
            }
        };

        fetchMedia();
    }, [mediaId, media]);

    if (loading) {
        return (
            <div className={cn('relative overflow-hidden bg-muted', props.className)}>
                <div className="absolute inset-0 animate-pulse bg-muted" />
            </div>
        );
    }

    if (error || !loadedMedia) {
        return (
            <div
                className={cn(
                    'relative overflow-hidden bg-muted flex flex-col items-center justify-center',
                    props.className
                )}
            >
                <FileImage className="h-12 w-12 text-muted-foreground mb-2" />
                <p className="text-xs text-muted-foreground">Image not found</p>
            </div>
        );
    }

    return <LazyImage media={loadedMedia} {...props} />;
}
