import { Link, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Github, Twitter, Linkedin } from 'lucide-react';
import AppLogoIcon from '@/components/shared/branding/app-logo-icon';
import central from '@/routes/central';

interface FooterLink {
    label: string;
    href: string;
    external?: boolean;
}

interface FooterSection {
    title: string;
    links: FooterLink[];
}

export function MarketingFooter() {
    const { t } = useLaravelReactI18n();
    const { name: appName } = usePage<{ name: string }>().props;
    const currentYear = new Date().getFullYear();

    const sections: FooterSection[] = [
        {
            title: t('marketing.footer.product', { default: 'Product' }),
            links: [
                { label: t('marketing.nav.features', { default: 'Features' }), href: '/features' },
                { label: t('marketing.nav.pricing', { default: 'Pricing' }), href: central.pricing.url() },
                { label: t('marketing.footer.changelog', { default: 'Changelog' }), href: '/changelog' },
            ],
        },
        {
            title: t('marketing.footer.company', { default: 'Company' }),
            links: [
                { label: t('marketing.footer.about', { default: 'About' }), href: '/about' },
                { label: t('marketing.footer.blog', { default: 'Blog' }), href: '/blog' },
                { label: t('marketing.nav.contact', { default: 'Contact' }), href: '/contact' },
            ],
        },
        {
            title: t('marketing.footer.legal', { default: 'Legal' }),
            links: [
                { label: t('marketing.footer.privacy', { default: 'Privacy Policy' }), href: '/privacy' },
                { label: t('marketing.footer.terms', { default: 'Terms of Service' }), href: '/terms' },
                { label: t('marketing.footer.cookies', { default: 'Cookie Policy' }), href: '/cookies' },
            ],
        },
    ];

    const socialLinks = [
        { label: 'Twitter', href: 'https://twitter.com', icon: Twitter },
        { label: 'GitHub', href: 'https://github.com', icon: Github },
        { label: 'LinkedIn', href: 'https://linkedin.com', icon: Linkedin },
    ];

    return (
        <footer className="bg-muted/30 border-t">
            <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                {/* Main Footer Content */}
                <div className="grid gap-8 lg:grid-cols-5">
                    {/* Brand Column */}
                    <div className="lg:col-span-2">
                        <Link href={central.home.url()} className="flex items-center gap-2">
                            <AppLogoIcon className="fill-foreground size-8" />
                            <span className="text-lg font-semibold">{appName}</span>
                        </Link>
                        <p className="text-muted-foreground mt-4 max-w-xs text-sm">
                            {t('marketing.footer.description', {
                                default: 'The all-in-one platform for managing your team, projects, and billing.',
                            })}
                        </p>
                        {/* Social Links */}
                        <div className="mt-6 flex gap-4">
                            {socialLinks.map((social) => (
                                <a
                                    key={social.label}
                                    href={social.href}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-muted-foreground hover:text-foreground transition-colors"
                                    aria-label={social.label}
                                >
                                    <social.icon className="h-5 w-5" />
                                </a>
                            ))}
                        </div>
                    </div>

                    {/* Link Columns */}
                    {sections.map((section) => (
                        <div key={section.title}>
                            <h3 className="text-foreground text-sm font-semibold">
                                {section.title}
                            </h3>
                            <ul className="mt-4 space-y-3">
                                {section.links.map((link) => (
                                    <li key={link.href}>
                                        {link.external ? (
                                            <a
                                                href={link.href}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-muted-foreground hover:text-foreground text-sm transition-colors"
                                            >
                                                {link.label}
                                            </a>
                                        ) : (
                                            <Link
                                                href={link.href}
                                                className="text-muted-foreground hover:text-foreground text-sm transition-colors"
                                            >
                                                {link.label}
                                            </Link>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>

                {/* Bottom Bar */}
                <div className="mt-12 flex flex-col items-center justify-between gap-4 border-t pt-8 md:flex-row">
                    <p className="text-muted-foreground text-sm">
                        &copy; {currentYear} {appName}.{' '}
                        {t('marketing.footer.copyright', { default: 'All rights reserved.' })}
                    </p>
                </div>
            </div>
        </footer>
    );
}
