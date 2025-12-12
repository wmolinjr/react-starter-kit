import { Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ArrowRight,
    Users,
    FolderKanban,
    CreditCard,
    Shield,
    Globe,
    Zap,
    BarChart3,
    Lock,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import MarketingLayout from '@/layouts/marketing-layout';
import central from '@/routes/central';
import { index as signupIndex } from '@/routes/central/signup';

interface Feature {
    icon: typeof Users;
    title: string;
    description: string;
}

export default function FeaturesPage() {
    const { t } = useLaravelReactI18n();

    const features: Feature[] = [
        {
            icon: Users,
            title: t('marketing.home.features.team.title', { default: 'Team Management' }),
            description: t('marketing.home.features.team.description', {
                default: 'Invite team members, manage roles, and collaborate seamlessly.',
            }),
        },
        {
            icon: FolderKanban,
            title: t('marketing.home.features.projects.title', { default: 'Project Tracking' }),
            description: t('marketing.home.features.projects.description', {
                default: 'Organize tasks, track progress, and meet deadlines.',
            }),
        },
        {
            icon: CreditCard,
            title: t('marketing.home.features.billing.title', { default: 'Integrated Billing' }),
            description: t('marketing.home.features.billing.description', {
                default: 'Manage subscriptions, invoices, and payments in one place.',
            }),
        },
        {
            icon: Shield,
            title: t('marketing.home.features.security.title', { default: 'Enterprise Security' }),
            description: t('marketing.home.features.security.description', {
                default: 'Two-factor auth, role-based access, and data encryption.',
            }),
        },
        {
            icon: Globe,
            title: 'Multi-Tenant Architecture',
            description: 'Isolated data per workspace with dedicated databases for maximum security.',
        },
        {
            icon: Zap,
            title: 'Real-Time Updates',
            description: 'Instant synchronization across all devices and team members.',
        },
        {
            icon: BarChart3,
            title: 'Advanced Analytics',
            description: 'Track usage, monitor performance, and make data-driven decisions.',
        },
        {
            icon: Lock,
            title: 'Privacy First',
            description: 'GDPR compliant with full data portability and deletion support.',
        },
    ];

    return (
        <MarketingLayout title={t('marketing.features.page_title', { default: 'Features' })}>
            {/* Hero Section */}
            <section className="py-20 sm:py-32">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mx-auto max-w-3xl text-center">
                        <h1 className="text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                            {t('marketing.features.hero.title', {
                                default: 'Powerful Features for Modern Teams',
                            })}
                        </h1>
                        <p className="text-muted-foreground mt-6 text-lg sm:text-xl">
                            {t('marketing.features.hero.subtitle', {
                                default: 'Everything you need to manage your business efficiently.',
                            })}
                        </p>
                    </div>
                </div>
            </section>

            {/* Features Grid */}
            <section className="bg-muted/30 border-y py-20">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        {features.map((feature) => (
                            <Card key={feature.title} className="border-0 shadow-sm">
                                <CardHeader>
                                    <div className="bg-primary/10 text-primary mb-2 inline-flex h-10 w-10 items-center justify-center rounded-lg">
                                        <feature.icon className="h-5 w-5" />
                                    </div>
                                    <CardTitle className="text-lg">{feature.title}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CardDescription className="text-sm">
                                        {feature.description}
                                    </CardDescription>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>
            </section>

            {/* CTA Section */}
            <section className="py-20">
                <div className="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                    <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
                        {t('marketing.features.cta.title', { default: 'Start building today' })}
                    </h2>
                    <p className="text-muted-foreground mt-4 text-lg">
                        {t('marketing.features.cta.subtitle', {
                            default: 'Join thousands of teams already using our platform.',
                        })}
                    </p>
                    <div className="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <Button size="lg" asChild>
                            <Link href={signupIndex.url({ plan: 'starter' })}>
                                {t('marketing.home.hero.cta.primary', { default: 'Start Free Trial' })}
                                <ArrowRight className="ml-2 h-4 w-4" />
                            </Link>
                        </Button>
                        <Button variant="outline" size="lg" asChild>
                            <Link href={central.pricing.url()}>
                                {t('marketing.nav.pricing', { default: 'View Pricing' })}
                            </Link>
                        </Button>
                    </div>
                </div>
            </section>
        </MarketingLayout>
    );
}
