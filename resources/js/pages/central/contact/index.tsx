import { useForm } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Mail, MapPin, Phone } from 'lucide-react';
import { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import MarketingLayout from '@/layouts/marketing-layout';

export default function ContactPage() {
    const { t } = useLaravelReactI18n();

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        subject: '',
        message: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/contact', {
            onSuccess: () => reset(),
        });
    };

    return (
        <MarketingLayout title={t('marketing.contact.page_title', { default: 'Contact Us' })}>
            {/* Hero Section */}
            <section className="py-16 sm:py-24">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mx-auto max-w-2xl text-center">
                        <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">
                            {t('marketing.contact.hero.title', { default: 'Get in Touch' })}
                        </h1>
                        <p className="text-muted-foreground mt-6 text-lg">
                            {t('marketing.contact.hero.subtitle', {
                                default: "Have questions? We'd love to hear from you.",
                            })}
                        </p>
                    </div>
                </div>
            </section>

            {/* Contact Form & Info */}
            <section className="pb-20">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-8 lg:grid-cols-3">
                        {/* Contact Information */}
                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <Mail className="h-5 w-5" />
                                        {t('marketing.contact.info.email', { default: 'Email' })}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CardDescription>
                                        <a
                                            href="mailto:support@example.com"
                                            className="text-primary hover:underline"
                                        >
                                            support@example.com
                                        </a>
                                    </CardDescription>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <Phone className="h-5 w-5" />
                                        Phone
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CardDescription>+1 (555) 123-4567</CardDescription>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <MapPin className="h-5 w-5" />
                                        Address
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CardDescription>
                                        123 Business Street
                                        <br />
                                        San Francisco, CA 94102
                                    </CardDescription>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Contact Form */}
                        <Card className="lg:col-span-2">
                            <CardHeader>
                                <CardTitle>
                                    {t('marketing.contact.form.submit', { default: 'Send Message' })}
                                </CardTitle>
                                <CardDescription>
                                    Fill out the form below and we'll get back to you as soon as
                                    possible.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    <div className="grid gap-6 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="name">
                                                {t('marketing.contact.form.name', {
                                                    default: 'Your Name',
                                                })}
                                            </Label>
                                            <Input
                                                id="name"
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                                placeholder="John Doe"
                                                required
                                            />
                                            {errors.name && (
                                                <p className="text-destructive text-sm">
                                                    {errors.name}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="email">
                                                {t('marketing.contact.form.email', {
                                                    default: 'Email Address',
                                                })}
                                            </Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                value={data.email}
                                                onChange={(e) => setData('email', e.target.value)}
                                                placeholder="john@example.com"
                                                required
                                            />
                                            {errors.email && (
                                                <p className="text-destructive text-sm">
                                                    {errors.email}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="subject">
                                            {t('marketing.contact.form.subject', {
                                                default: 'Subject',
                                            })}
                                        </Label>
                                        <Input
                                            id="subject"
                                            value={data.subject}
                                            onChange={(e) => setData('subject', e.target.value)}
                                            placeholder="How can we help?"
                                            required
                                        />
                                        {errors.subject && (
                                            <p className="text-destructive text-sm">
                                                {errors.subject}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="message">
                                            {t('marketing.contact.form.message', {
                                                default: 'Message',
                                            })}
                                        </Label>
                                        <Textarea
                                            id="message"
                                            value={data.message}
                                            onChange={(e) => setData('message', e.target.value)}
                                            placeholder="Tell us more about your inquiry..."
                                            rows={5}
                                            required
                                        />
                                        {errors.message && (
                                            <p className="text-destructive text-sm">
                                                {errors.message}
                                            </p>
                                        )}
                                    </div>

                                    <Button type="submit" disabled={processing} className="w-full">
                                        {processing
                                            ? t('marketing.contact.form.sending', {
                                                  default: 'Sending...',
                                              })
                                            : t('marketing.contact.form.submit', {
                                                  default: 'Send Message',
                                              })}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>
        </MarketingLayout>
    );
}
