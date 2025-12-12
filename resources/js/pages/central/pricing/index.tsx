import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { PlanCard } from '@/components/shared/billing/plans/plan-card';
import { PricingToggle } from '@/components/shared/billing/primitives/pricing-toggle';
import MarketingLayout from '@/layouts/marketing-layout';
import { index as signupIndex } from '@/routes/central/signup';
import type { PlanResource } from '@/types/resources';

type PricingBillingPeriod = 'monthly' | 'yearly';

interface PricingPageProps {
    plans: PlanResource[];
}

export default function PricingPage({ plans }: PricingPageProps) {
    const { t } = useLaravelReactI18n();
    const [billingPeriod, setBillingPeriod] = useState<PricingBillingPeriod>('monthly');

    const handleSelectPlan = (planSlug: string) => {
        router.visit(signupIndex.url({ plan: planSlug }));
    };

    return (
        <MarketingLayout title={t('pricing.page.title', { default: 'Pricing' })}>
            {/* Hero Section */}
            <section className="py-16 sm:py-24">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="text-center">
                        <h1 className="text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                            {t('pricing.hero.title', { default: 'Simple, transparent pricing' })}
                        </h1>
                        <p className="text-muted-foreground mx-auto mt-6 max-w-2xl text-lg">
                            {t('pricing.hero.subtitle', {
                                default: 'Choose the plan that best fits your needs. All plans include a 14-day free trial.',
                            })}
                        </p>
                    </div>

                    {/* Billing Toggle */}
                    <div className="mt-10 flex justify-center">
                        <PricingToggle
                            value={billingPeriod}
                            onChange={setBillingPeriod}
                            savings={t('pricing.toggle.savings', { default: 'Save 20%' })}
                        />
                    </div>

                    {/* Plans Grid */}
                    <div className="mt-12 grid gap-8 lg:grid-cols-3">
                        {plans.map((plan) => (
                            <PlanCard
                                key={plan.id}
                                plan={plan}
                                billingPeriod={billingPeriod}
                                onSelect={handleSelectPlan}
                                ctaLabel={t('pricing.cta.get_started', { default: 'Get Started' })}
                                showFeatures={true}
                                maxFeatures={8}
                            />
                        ))}
                    </div>

                    {/* Additional Info */}
                    <div className="mt-16 text-center">
                        <p className="text-muted-foreground text-sm">
                            {t('pricing.info.guarantee', {
                                default: 'All plans come with a 30-day money-back guarantee. No questions asked.',
                            })}
                        </p>
                    </div>
                </div>
            </section>

            {/* CTA Section */}
            <section className="bg-muted/50 border-t py-16">
                <div className="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                    <h2 className="text-2xl font-bold sm:text-3xl">
                        {t('pricing.cta_section.title', { default: 'Ready to get started?' })}
                    </h2>
                    <p className="text-muted-foreground mt-4 text-lg">
                        {t('pricing.cta_section.subtitle', {
                            default: 'Create your workspace in minutes and start growing your business today.',
                        })}
                    </p>
                    <div className="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <Button asChild size="lg">
                            <Link href={signupIndex.url({ plan: 'starter' })}>
                                {t('pricing.cta_section.primary', { default: 'Start Free Trial' })}
                                <ArrowRight className="ml-2 h-4 w-4" />
                            </Link>
                        </Button>
                        <Button variant="outline" size="lg" asChild>
                            <Link href="/contact">
                                {t('pricing.cta_section.secondary', { default: 'Contact Sales' })}
                            </Link>
                        </Button>
                    </div>
                </div>
            </section>
        </MarketingLayout>
    );
}
