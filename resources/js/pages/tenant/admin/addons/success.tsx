import { Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import AdminLayout from '@/layouts/tenant/admin-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CheckCircle } from 'lucide-react';
import admin from '@/routes/tenant/admin';
import { type ReactElement } from 'react';

interface Props {
    addon_name?: string;
    quantity?: number;
    amount?: string;
}

function AddonsSuccess({ addon_name, quantity, amount }: Props) {
    const { t } = useLaravelReactI18n();

    return (
        <>
            <Head title={t('addons.page.purchase_successful')} />

            <div className="flex items-center justify-center py-12">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <CheckCircle className="mx-auto h-12 w-12 text-green-500" />
                        <CardTitle className="mt-4">{t('addons.page.purchase_successful')}</CardTitle>
                        <CardDescription>{t('addons.page.addon_activated')}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {addon_name && (
                            <div className="bg-muted rounded-lg p-4">
                                <div className="flex justify-between text-sm">
                                    <span>Add-on</span>
                                    <span className="font-medium">{addon_name}</span>
                                </div>
                                {quantity && (
                                    <div className="flex justify-between text-sm">
                                        <span>{t('addons.page.quantity')}</span>
                                        <span className="font-medium">{quantity}</span>
                                    </div>
                                )}
                                {amount && (
                                    <div className="flex justify-between text-sm">
                                        <span>{t('addons.page.amount')}</span>
                                        <span className="font-medium">{amount}</span>
                                    </div>
                                )}
                            </div>
                        )}
                        <Button asChild className="w-full">
                            <Link href={admin.addons.index.url()}>{t('addons.page.view_addons')}</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AddonsSuccess.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default AddonsSuccess;
