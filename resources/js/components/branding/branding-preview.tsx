import { Building2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface BrandingPreviewProps {
    name: string;
    logo?: string | null;
    primaryColor?: string | null;
    description?: string | null;
}

export function BrandingPreview({
    name,
    logo,
    primaryColor,
    description,
}: BrandingPreviewProps) {
    const color = primaryColor || '#000000';

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Brand Preview</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Logo Preview */}
                <div className="space-y-2">
                    <p className="text-sm font-medium">Logo</p>
                    <div className="rounded-lg border bg-muted/50 p-6 flex items-center justify-center min-h-[120px]">
                        {logo ? (
                            <img
                                src={logo}
                                alt={`${name} logo`}
                                className="max-w-full max-h-20 object-contain"
                            />
                        ) : (
                            <div className="text-center space-y-2">
                                <Building2 className="h-12 w-12 text-muted-foreground mx-auto" />
                                <p className="text-sm text-muted-foreground">No logo uploaded</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Primary Color Preview */}
                <div className="space-y-2">
                    <p className="text-sm font-medium">Primary Color</p>
                    <div className="rounded-lg border overflow-hidden">
                        <div
                            className="h-24 flex items-center justify-center text-white font-semibold"
                            style={{ backgroundColor: color }}
                        >
                            <div className="text-center">
                                <p className="text-lg">{name}</p>
                                <p className="text-xs opacity-90 mt-1 font-mono">{color}</p>
                            </div>
                        </div>
                        <div className="p-4 bg-background">
                            <div className="flex items-center gap-2">
                                <div
                                    className="w-8 h-8 rounded-md border"
                                    style={{ backgroundColor: color }}
                                />
                                <div className="flex-1">
                                    <p className="text-sm font-medium">Accent Color</p>
                                    <p className="text-xs text-muted-foreground">
                                        Used for buttons, links, and highlights
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Description Preview */}
                {description && (
                    <div className="space-y-2">
                        <p className="text-sm font-medium">Description</p>
                        <div className="rounded-lg border bg-muted/50 p-4">
                            <p className="text-sm text-muted-foreground">{description}</p>
                        </div>
                    </div>
                )}

                {/* Example Card */}
                <div className="space-y-2">
                    <p className="text-sm font-medium">Example Usage</p>
                    <div className="rounded-lg border p-4 space-y-3">
                        <div className="flex items-center gap-3">
                            {logo ? (
                                <img
                                    src={logo}
                                    alt={`${name} logo`}
                                    className="w-8 h-8 object-contain"
                                />
                            ) : (
                                <div
                                    className="w-8 h-8 rounded-md flex items-center justify-center"
                                    style={{ backgroundColor: color }}
                                >
                                    <span className="text-white text-xs font-bold">
                                        {name.charAt(0).toUpperCase()}
                                    </span>
                                </div>
                            )}
                            <div className="flex-1">
                                <p className="text-sm font-medium">{name}</p>
                                {description && (
                                    <p className="text-xs text-muted-foreground line-clamp-1">
                                        {description}
                                    </p>
                                )}
                            </div>
                        </div>
                        <button
                            type="button"
                            className="w-full rounded-md py-2 text-sm font-medium text-white transition-opacity hover:opacity-90"
                            style={{ backgroundColor: color }}
                        >
                            Example Button
                        </button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
