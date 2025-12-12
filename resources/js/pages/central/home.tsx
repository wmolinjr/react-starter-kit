import { Link, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowRight, Users, FolderKanban, CreditCard, Shield } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import MarketingLayout from '@/layouts/marketing-layout';
import central from '@/routes/central';
import { index as signupIndex } from '@/routes/central/signup';

interface FeatureItem {
    icon: typeof Users;
    titleKey: string;
    descriptionKey: string;
}

export default function HomePage() {
    const { t } = useLaravelReactI18n();
    const { name: appName } = usePage<{ name: string }>().props;

    const features: FeatureItem[] = [
        {
            icon: Users,
            titleKey: 'marketing.home.features.team.title',
            descriptionKey: 'marketing.home.features.team.description',
        },
        {
            icon: FolderKanban,
            titleKey: 'marketing.home.features.projects.title',
            descriptionKey: 'marketing.home.features.projects.description',
        },
        {
            icon: CreditCard,
            titleKey: 'marketing.home.features.billing.title',
            descriptionKey: 'marketing.home.features.billing.description',
        },
        {
            icon: Shield,
            titleKey: 'marketing.home.features.security.title',
            descriptionKey: 'marketing.home.features.security.description',
        },
    ];

    return (
        <MarketingLayout title={t('marketing.home.page_title', { default: 'Home' })}>
            {/* Hero Section */}
            <section className="relative overflow-hidden py-20 sm:py-32">
                <div className="bg-grid-pattern absolute inset-0 opacity-5" />
                <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mx-auto max-w-3xl text-center">
                        <h1 className="text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                            {t('marketing.home.hero.title', {
                                default: 'Build Your Business with :appName',
                                appName,
                            })}
                        </h1>
                        <p className="text-muted-foreground mt-6 text-lg sm:text-xl">
                            {t('marketing.home.hero.subtitle', {
                                default: 'The all-in-one platform for managing your team, projects, and billing.',
                            })}
                        </p>
                        <div className="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                            <Button size="lg" asChild>
                                <Link href={signupIndex.url({ plan: 'starter' })}>
                                    {t('marketing.home.hero.cta.primary', { default: 'Start Free Trial' })}
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Link>
                            </Button>
                            <Button variant="outline" size="lg" asChild>
                                <Link href="/features">
                                    {t('marketing.home.hero.cta.secondary', { default: 'Learn More' })}
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </section>

            {/* Features Section */}
            <section className="bg-muted/30 border-y py-20">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mx-auto max-w-2xl text-center">
                        <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
                            {t('marketing.home.features.title', { default: 'Everything you need' })}
                        </h2>
                        <p className="text-muted-foreground mt-4 text-lg">
                            {t('marketing.home.features.subtitle', {
                                default: 'Powerful features to help you grow your business.',
                            })}
                        </p>
                    </div>
                    <div className="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                        {features.map((feature) => (
                            <Card key={feature.titleKey} className="border-0 shadow-none bg-transparent">
                                <CardContent className="pt-6">
                                    <div className="bg-primary/10 text-primary mb-4 inline-flex h-12 w-12 items-center justify-center rounded-lg">
                                        <feature.icon className="h-6 w-6" />
                                    </div>
                                    <h3 className="text-lg font-semibold">
                                        {t(feature.titleKey, { default: feature.titleKey })}
                                    </h3>
                                    <p className="text-muted-foreground mt-2 text-sm">
                                        {t(feature.descriptionKey, { default: feature.descriptionKey })}
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                    <div className="mt-12 text-center">
                        <Button variant="outline" asChild>
                            <Link href="/features">
                                {t('marketing.home.hero.cta.secondary', { default: 'Learn More' })}
                                <ArrowRight className="ml-2 h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </section>

            {/* Pricing Preview Section */}
            <section className="py-20">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mx-auto max-w-2xl text-center">
                        <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
                            {t('pricing.hero.title', { default: 'Simple, transparent pricing' })}
                        </h2>
                        <p className="text-muted-foreground mt-4 text-lg">
                            {t('pricing.hero.subtitle', {
                                default: 'Choose the plan that best fits your needs. All plans include a 14-day free trial.',
                            })}
                        </p>
                        <div className="mt-8">
                            <Button size="lg" asChild>
                                <Link href={central.pricing.url()}>
                                    {t('marketing.nav.pricing', { default: 'View Pricing' })}
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </section>

            {/* CTA Section */}
            <section className="bg-primary text-primary-foreground py-20">
                <div className="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                    <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
                        {t('marketing.home.cta_section.title', { default: 'Ready to get started?' })}
                    </h2>
                    <p className="mt-4 text-lg opacity-90">
                        {t('marketing.home.cta_section.subtitle', {
                            default: 'Create your workspace in minutes and start growing your business today.',
                        })}
                    </p>
                    <div className="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <Button size="lg" variant="secondary" asChild>
                            <Link href={signupIndex.url({ plan: 'starter' })}>
                                {t('marketing.home.hero.cta.primary', { default: 'Start Free Trial' })}
                                <ArrowRight className="ml-2 h-4 w-4" />
                            </Link>
                        </Button>
                        <Button
                            size="lg"
                            variant="outline"
                            className="border-primary-foreground/20 text-primary-foreground hover:bg-primary-foreground/10"
                            asChild
                        >
                            <Link href="/contact">
                                {t('marketing.nav.contact', { default: 'Contact Sales' })}
                            </Link>
                        </Button>
                    </div>
                </div>
            </section>
        </MarketingLayout>
    );
}
