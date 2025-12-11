import CentralTwoFactorSetupModal from '@/components/central/dialogs/two-factor-setup-modal';
import HeadingSmall from '@/components/shared/typography/heading-small';
import TwoFactorRecoveryCodes from '@/components/shared/auth/two-factor-recovery-codes';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/hooks/shared/use-two-factor-auth';
import AdminLayout from '@/layouts/central/admin-layout';
import CentralUserSettingsLayout from '@/layouts/central/user-settings-layout';
import settings from '@/routes/central/admin/settings';
import { type BreadcrumbItem } from '@/types';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import { Form, Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ShieldBan, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

interface TwoFactorProps {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
    setupPending?: boolean;
}

function TwoFactor({
    requiresConfirmation = false,
    twoFactorEnabled = false,
    setupPending = false,
}: TwoFactorProps) {
    const { t } = useLaravelReactI18n();
    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
        routes,
    } = useTwoFactorAuth({ context: 'central' });
    const [showSetupModal, setShowSetupModal] = useState<boolean>(setupPending);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('settings.page.title'),
            href: settings.profile.edit().url,
        },
        {
            title: t('settings.nav.two_factor'),
            href: settings.twoFactor.show().url,
        },
    ];

    useSetBreadcrumbs(breadcrumbs);

    return (
        <>
            <Head title={t('settings.two_factor.title')} />
            <CentralUserSettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title={t('settings.two_factor.title')}
                        description={t('settings.two_factor.description')}
                    />
                    {twoFactorEnabled ? (
                        <div className="flex flex-col items-start justify-start space-y-4">
                            <Badge variant="default">{t('settings.two_factor.enabled')}</Badge>
                            <p className="text-muted-foreground">
                                {t('settings.two_factor.enabled_description')}
                            </p>

                            <TwoFactorRecoveryCodes
                                recoveryCodesList={recoveryCodesList}
                                fetchRecoveryCodes={fetchRecoveryCodes}
                                errors={errors}
                                regenerateRecoveryCodes={routes.regenerateRecoveryCodes}
                            />

                            <div className="relative inline">
                                <Form {...routes.disable()}>
                                    {({ processing }) => (
                                        <Button
                                            variant="destructive"
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <ShieldBan /> {t('settings.two_factor.disable')}
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        </div>
                    ) : (
                        <div className="flex flex-col items-start justify-start space-y-4">
                            <Badge variant="destructive">{t('settings.two_factor.disabled')}</Badge>
                            <p className="text-muted-foreground">
                                {t('settings.two_factor.disabled_description')}
                            </p>

                            <div>
                                {hasSetupData ? (
                                    <Button
                                        onClick={() => setShowSetupModal(true)}
                                    >
                                        <ShieldCheck />
                                        {t('settings.two_factor.continue_setup')}
                                    </Button>
                                ) : (
                                    <Form
                                        {...routes.enable()}
                                        onSuccess={() =>
                                            setShowSetupModal(true)
                                        }
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                <ShieldCheck />
                                                {t('settings.two_factor.enable')}
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </div>
                        </div>
                    )}

                    <CentralTwoFactorSetupModal
                        isOpen={showSetupModal}
                        onClose={() => setShowSetupModal(false)}
                        requiresConfirmation={requiresConfirmation}
                        twoFactorEnabled={twoFactorEnabled}
                        qrCodeSvg={qrCodeSvg}
                        manualSetupKey={manualSetupKey}
                        clearSetupData={clearSetupData}
                        fetchSetupData={fetchSetupData}
                        errors={errors}
                    />
                </div>
            </CentralUserSettingsLayout>
        </>
    );
}

TwoFactor.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default TwoFactor;
