import type { BlockType as BlockTypeBase, PageBlock } from './index';

// Re-export base types
export type BlockType = BlockTypeBase;
export type BaseBlock = PageBlock;

// Hero Block
export interface HeroBlock extends BaseBlock {
    block_type: 'hero';
    content: {
        title: string;
        subtitle?: string;
        cta_text?: string;
        cta_url?: string;
        image_url?: string;
    };
    config?: {
        alignment?: 'left' | 'center' | 'right';
        background_color?: string;
        text_color?: string;
    };
}

// Text Block
export interface TextBlock extends BaseBlock {
    block_type: 'text';
    content: {
        content: string;
    };
    config?: {
        alignment?: 'left' | 'center' | 'right' | 'justify';
        text_color?: string;
    };
}

// Image Block
export interface ImageBlock extends BaseBlock {
    block_type: 'image';
    content: {
        url: string;
        alt?: string;
        caption?: string;
    };
    config?: {
        alignment?: 'left' | 'center' | 'right';
        width?: 'small' | 'medium' | 'large' | 'full';
    };
}

// Gallery Block
export interface GalleryBlock extends BaseBlock {
    block_type: 'gallery';
    content: {
        images: Array<{
            url: string;
            alt?: string;
            caption?: string;
        }>;
    };
    config?: {
        columns?: number;
        gap?: 'small' | 'medium' | 'large';
    };
}

// CTA Block
export interface CTABlock extends BaseBlock {
    block_type: 'cta';
    content: {
        title: string;
        description?: string;
        button_text: string;
        button_url: string;
    };
    config?: {
        alignment?: 'left' | 'center' | 'right';
        background_color?: string;
        button_style?: 'primary' | 'secondary' | 'outline';
    };
}

// Features Block
export interface FeaturesBlock extends BaseBlock {
    block_type: 'features';
    content: {
        title?: string;
        features: Array<{
            title: string;
            description?: string;
            icon?: string;
        }>;
    };
    config?: {
        columns?: number;
        alignment?: 'left' | 'center' | 'right';
    };
}

// Testimonials Block
export interface TestimonialsBlock extends BaseBlock {
    block_type: 'testimonials';
    content: {
        title?: string;
        testimonials: Array<{
            quote: string;
            author: string;
            role?: string;
            avatar?: string;
        }>;
    };
    config?: {
        layout?: 'grid' | 'carousel' | 'list';
        columns?: number;
    };
}

// Union type for all blocks
export type Block =
    | HeroBlock
    | TextBlock
    | ImageBlock
    | GalleryBlock
    | CTABlock
    | FeaturesBlock
    | TestimonialsBlock;

// Block template for creating new blocks
export interface BlockTemplate {
    type: BlockType;
    label: string;
    icon: string;
    description: string;
    defaultContent: Record<string, any>;
    defaultConfig?: Record<string, any>;
}
