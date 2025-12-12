import { Link, router, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { LogOut, Menu, Settings, User } from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/shared/branding/app-logo-icon';
import { UserInfo } from '@/components/shared/user/user-info';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useInitials } from '@/hooks/shared/use-initials';
import central from '@/routes/central';
import { index as signupIndex } from '@/routes/central/signup';
import type { Auth } from '@/types';

interface MarketingHeaderProps {
    showCta?: boolean;
}

interface NavItem {
    label: string;
    href: string;
}

export function MarketingHeader({ showCta = true }: MarketingHeaderProps) {
    const { t } = useLaravelReactI18n();
    const { name: appName, url, auth } = usePage<{ name: string; auth: Auth }>().props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const getInitials = useInitials();

    const isLoggedIn = !!auth?.user;
    const user = auth?.user;

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

    const handleLogout = () => {
        router.flushAll();
        router.post(central.account.logout.url());
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
                                    ? 'bg-accent text-foreground'
                                    : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground'
                            }`}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>

                {/* Desktop CTA / User Menu */}
                <div className="hidden items-center gap-3 md:flex">
                    {isLoggedIn && user ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="sm" className="gap-2">
                                    <Avatar className="h-7 w-7">
                                        <AvatarImage src={user.avatar} alt={user.name} />
                                        <AvatarFallback className="bg-primary/10 text-primary text-xs">
                                            {getInitials(user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="max-w-[120px] truncate">{user.name}</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <DropdownMenuLabel className="p-0 font-normal">
                                    <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                        <UserInfo user={user} showEmail={true} />
                                    </div>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuGroup>
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={central.account.profile.edit.url()}
                                            className="w-full"
                                        >
                                            <Settings className="mr-2 h-4 w-4" />
                                            {t('common.settings', { default: 'Settings' })}
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={handleLogout}>
                                    <LogOut className="mr-2 h-4 w-4" />
                                    {t('common.logout', { default: 'Log out' })}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : (
                        showCta && (
                            <>
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
                            </>
                        )
                    )}
                </div>

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

                            {/* Mobile User Info */}
                            {isLoggedIn && user && (
                                <div className="mt-6 flex items-center gap-3 rounded-lg border p-3">
                                    <Avatar className="h-10 w-10">
                                        <AvatarImage src={user.avatar} alt={user.name} />
                                        <AvatarFallback className="bg-primary/10 text-primary">
                                            {getInitials(user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="flex-1 overflow-hidden">
                                        <p className="truncate font-medium">{user.name}</p>
                                        <p className="text-muted-foreground truncate text-sm">
                                            {user.email}
                                        </p>
                                    </div>
                                </div>
                            )}

                            <nav className="mt-6 flex flex-col gap-1">
                                {navItems.map((item) => (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className={`rounded-md px-3 py-2 text-base font-medium transition-colors ${
                                            isActive(item.href)
                                                ? 'bg-accent text-foreground'
                                                : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground'
                                        }`}
                                    >
                                        {item.label}
                                    </Link>
                                ))}
                            </nav>

                            {/* Mobile User Actions */}
                            {isLoggedIn && user ? (
                                <div className="mt-6 flex flex-col gap-2 border-t pt-6">
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={central.account.profile.edit.url()}
                                            onClick={() => setMobileMenuOpen(false)}
                                        >
                                            <User className="mr-2 h-4 w-4" />
                                            {t('customer.dashboard.title', { default: 'My Account' })}
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        onClick={() => {
                                            setMobileMenuOpen(false);
                                            handleLogout();
                                        }}
                                    >
                                        <LogOut className="mr-2 h-4 w-4" />
                                        {t('common.logout', { default: 'Log out' })}
                                    </Button>
                                </div>
                            ) : (
                                showCta && (
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
                                )
                            )}
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>
    );
}
