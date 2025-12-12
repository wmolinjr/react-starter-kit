import { Link, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Menu } from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/shared/branding/app-logo-icon';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import central from '@/routes/central';
import { index as signupIndex } from '@/routes/central/signup';

interface MarketingHeaderProps {
    showCta?: boolean;
}

interface NavItem {
    label: string;
    href: string;
}

export function MarketingHeader({ showCta = true }: MarketingHeaderProps) {
    const { t } = useLaravelReactI18n();
    const { name: appName, url } = usePage<{ name: string }>().props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    const navItems: NavItem[] = [
        { label: t('marketing.nav.home', { default: 'Home' }), href: central.home.url() },
        { label: t('marketing.nav.features', { default: 'Features' }), href: '/features' },
        { label: t('marketing.nav.pricing', { default: 'Pricing' }), href: central.pricing.url() },
        { label: t('marketing.nav.contact', { default: 'Contact' }), href: '/contact' },
    ];

    const isActive = (href: string) => {
        const currentUrl = typeof url === 'string' ? url : window.location.pathname;
        if (href === central.home.url() || href === '/') {
            return currentUrl === '/' || currentUrl === central.home.url();
        }
        return currentUrl.startsWith(href);
    };

    return (
        <header className="bg-background/95 supports-[backdrop-filter]:bg-background/60 sticky top-0 z-50 border-b backdrop-blur">
            <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                {/* Logo */}
                <Link href={central.home.url()} className="flex items-center gap-2">
                    <AppLogoIcon className="fill-foreground size-8" />
                    <span className="text-lg font-semibold">{appName}</span>
                </Link>

                {/* Desktop Navigation */}
                <nav className="hidden items-center gap-1 md:flex">
                    {navItems.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                                isActive(item.href)
                                    ? 'text-foreground bg-accent'
                                    : 'text-muted-foreground hover:text-foreground hover:bg-accent/50'
                            }`}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>

                {/* Desktop CTA */}
                {showCta && (
                    <div className="hidden items-center gap-3 md:flex">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={central.account.login.url()}>
                                {t('marketing.nav.login', { default: 'Log in' })}
                            </Link>
                        </Button>
                        <Button size="sm" asChild>
                            <Link href={signupIndex.url({ plan: 'starter' })}>
                                {t('marketing.nav.get_started', { default: 'Get Started' })}
                            </Link>
                        </Button>
                    </div>
                )}

                {/* Mobile Menu */}
                <div className="md:hidden">
                    <Sheet open={mobileMenuOpen} onOpenChange={setMobileMenuOpen}>
                        <SheetTrigger asChild>
                            <Button variant="ghost" size="icon">
                                <Menu className="h-5 w-5" />
                                <span className="sr-only">
                                    {t('marketing.nav.open_menu', { default: 'Open menu' })}
                                </span>
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right" className="w-[300px] sm:w-[350px]">
                            <SheetHeader>
                                <SheetTitle className="flex items-center gap-2">
                                    <AppLogoIcon className="fill-foreground size-6" />
                                    <span>{appName}</span>
                                </SheetTitle>
                            </SheetHeader>
                            <nav className="mt-6 flex flex-col gap-1">
                                {navItems.map((item) => (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className={`rounded-md px-3 py-2 text-base font-medium transition-colors ${
                                            isActive(item.href)
                                                ? 'text-foreground bg-accent'
                                                : 'text-muted-foreground hover:text-foreground hover:bg-accent/50'
                                        }`}
                                    >
                                        {item.label}
                                    </Link>
                                ))}
                            </nav>
                            {showCta && (
                                <div className="mt-6 flex flex-col gap-2 border-t pt-6">
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={central.account.login.url()}
                                            onClick={() => setMobileMenuOpen(false)}
                                        >
                                            {t('marketing.nav.login', { default: 'Log in' })}
                                        </Link>
                                    </Button>
                                    <Button asChild>
                                        <Link
                                            href={signupIndex.url({ plan: 'starter' })}
                                            onClick={() => setMobileMenuOpen(false)}
                                        >
                                            {t('marketing.nav.get_started', { default: 'Get Started' })}
                                        </Link>
                                    </Button>
                                </div>
                            )}
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>
    );
}
