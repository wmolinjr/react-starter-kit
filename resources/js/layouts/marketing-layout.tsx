import { Head } from '@inertiajs/react';
import { type ReactNode } from 'react';
import { MarketingHeader } from '@/components/marketing/header';
import { MarketingFooter } from '@/components/marketing/footer';
import { FlashMessages } from '@/components/shared/feedback/flash-messages';

interface MarketingLayoutProps {
    children: ReactNode;
    title?: string;
    showHeaderCta?: boolean;
    showFooter?: boolean;
}

export default function MarketingLayout({
    children,
    title,
    showHeaderCta = true,
    showFooter = true,
}: MarketingLayoutProps) {
    return (
        <>
            {title && <Head title={title} />}

            <div className="bg-background flex min-h-svh flex-col">
                <MarketingHeader showCta={showHeaderCta} />

                <main className="flex-1">{children}</main>

                {showFooter && <MarketingFooter />}
            </div>

            <FlashMessages />
        </>
    );
}
