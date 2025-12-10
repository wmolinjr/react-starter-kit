import CustomerLayout from '@/layouts/customer-layout';
import InputError from '@/components/shared/feedback/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';
import { Form, Head, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { AlertTriangle, ArrowRightLeft, Building2 } from 'lucide-react';

interface Tenant {
    id: string;
    name: string;
    domain: string;
    plan: string | null;
}

interface TransferCreateProps {
    tenant: Tenant;
}

export default function TransferCreate({ tenant }: TransferCreateProps) {
    const { t } = useLaravelReactI18n();
    const { errors } = usePage().props as { errors?: Record<string, string> };

    return (
        <CustomerLayout
            breadcrumbs={[
                { title: t('customer.dashboard'), href: '/account' },
                { title: t('customer.workspaces'), href: '/account/tenants' },
                { title: tenant.name, href: `/account/tenants/${tenant.id}` },
                { title: t('customer.transfer'), href: `/account/tenants/${tenant.id}/transfer` },
            ]}
        >
            <Head title={t('customer.transfer_ownership')} />

            <div className="space-y-6 max-w-2xl">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                        <ArrowRightLeft className="h-6 w-6" />
                        {t('customer.transfer_ownership')}
                    </h1>
                    <p className="text-muted-foreground">
                        {t('customer.transfer_ownership_description')}
                    </p>
                </div>

                {/* Warning Card */}
                <Card className="border-warning bg-warning/10">
                    <CardContent className="flex items-start gap-4 py-4">
                        <AlertTriangle className="h-5 w-5 text-warning mt-0.5" />
                        <div>
                            <p className="font-medium text-warning">{t('customer.transfer_warning_title')}</p>
                            <p className="text-sm text-muted-foreground mt-1">
                                {t('customer.transfer_warning_description')}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {/* Workspace Info */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="h-5 w-5" />
                            {t('customer.workspace_being_transferred')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-2">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">{t('customer.workspace_name')}</span>
                                <span className="font-medium">{tenant.name}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">{t('customer.domain')}</span>
                                <span className="font-medium">{tenant.domain}</span>
                            </div>
                            {tenant.plan && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('customer.plan')}</span>
                                    <span className="font-medium">{tenant.plan}</span>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Transfer Form */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('customer.new_owner_details')}</CardTitle>
                        <CardDescription>
                            {t('customer.new_owner_details_description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={`/account/tenants/${tenant.id}/transfer`}
                            method="post"
                            className="space-y-4"
                        >
                            {({ processing }) => (
                                <>
                                    <div className="space-y-2">
                                        <Label htmlFor="to_email">{t('customer.recipient_email')}</Label>
                                        <Input
                                            id="to_email"
                                            name="to_email"
                                            type="email"
                                            required
                                            autoFocus
                                            placeholder="new-owner@example.com"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {t('customer.recipient_email_help')}
                                        </p>
                                        <InputError message={errors?.to_email} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="notes">{t('customer.notes_optional')}</Label>
                                        <Textarea
                                            id="notes"
                                            name="notes"
                                            rows={3}
                                            placeholder={t('customer.notes_placeholder')}
                                        />
                                        <InputError message={errors?.notes} />
                                    </div>

                                    <div className="flex gap-4 pt-4">
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            {t('customer.initiate_transfer')}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => window.history.back()}
                                        >
                                            {t('common.cancel')}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </CustomerLayout>
    );
}
