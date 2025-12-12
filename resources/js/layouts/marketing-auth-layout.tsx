import { Head } from '@inertiajs/react';
import { type ReactNode } from 'react';
import { MarketingHeader } from '@/components/marketing/header';
import { MarketingFooter } from '@/components/marketing/footer';
import { FlashMessages } from '@/components/shared/feedback/flash-messages';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface MarketingAuthLayoutProps {
    children: ReactNode;
    title: string;
    description?: string;
    cardTitle?: string;
    cardDescription?: string;
}

export default function MarketingAuthLayout({
    children,
    title,
    description,
    cardTitle,
    cardDescription,
}: MarketingAuthLayoutProps) {
    return (
        <>
            <Head title={title}>
                {description && <meta name="description" content={description} />}
            </Head>

            <div className="bg-background flex min-h-svh flex-col">
                <MarketingHeader showCta={false} />

                <main className="flex flex-1 items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
                    <Card className="w-full max-w-md">
                        {(cardTitle || cardDescription) && (
                            <CardHeader className="text-center">
                                {cardTitle && <CardTitle className="text-2xl">{cardTitle}</CardTitle>}
                                {cardDescription && (
                                    <CardDescription>{cardDescription}</CardDescription>
                                )}
                            </CardHeader>
                        )}
                        <CardContent className={!cardTitle && !cardDescription ? 'pt-6' : ''}>
                            {children}
                        </CardContent>
                    </Card>
                </main>

                <MarketingFooter />
            </div>

            <FlashMessages />
        </>
    );
}
