import { send } from '@/routes/central/admin/auth/verification';
import { type BreadcrumbItem, type PageProps } from '@/types';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import { Transition } from '@headlessui/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';

import HeadingSmall from '@/components/shared/typography/heading-small';

type PageErrors = Record<string, string>;
import InputError from '@/components/shared/feedback/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AdminLayout from '@/layouts/central/admin-layout';
import CentralUserSettingsLayout from '@/layouts/central/user-settings-layout';
import settings from '@/routes/central/admin/settings';

import DeleteUser from './components/delete-user';

function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth, errors } = usePage<PageProps & { errors?: PageErrors }>().props;
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('settings.page.title'),
            href: settings.profile.edit().url,
        },
        {
            title: t('settings.nav.profile'),
            href: settings.profile.edit().url,
        },
    ];

    useSetBreadcrumbs(breadcrumbs);

    // Profile page requires authentication
    if (!auth.user) {
        return null;
    }

    // Store user reference for use in render callbacks (TypeScript narrowing)
    const user = auth.user;

    return (
        <>
            <Head title={t('settings.profile.page_title')} />

            <CentralUserSettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title={t('settings.profile.title')}
                        description={t('settings.profile.description')}
                    />

                    <Form
                        {...settings.profile.update()}
                        options={{
                            preserveScroll: true,
                        }}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">{t('common.name')}</Label>

                                    <Input
                                        id="name"
                                        className="mt-1 block w-full"
                                        defaultValue={user.name}
                                        name="name"
                                        required
                                        autoComplete="name"
                                        placeholder={t('common.full_name')}
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors?.name}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">{t('common.email')}</Label>

                                    <Input
                                        id="email"
                                        type="email"
                                        className="mt-1 block w-full"
                                        defaultValue={user.email}
                                        name="email"
                                        required
                                        autoComplete="username"
                                        placeholder={t('common.email')}
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors?.email}
                                    />
                                </div>

                                {mustVerifyEmail &&
                                    user.email_verified_at === null && (
                                        <div>
                                            <p className="-mt-4 text-sm text-muted-foreground">
                                                {t('settings.profile.email_unverified')}{' '}
                                                <Link
                                                    href={send()}
                                                    as="button"
                                                    className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                                >
                                                    {t('settings.profile.resend_verification')}
                                                </Link>
                                            </p>

                                            {status ===
                                                'verification-link-sent' && (
                                                <div className="mt-2 text-sm font-medium text-green-600">
                                                    {t('settings.profile.verification_sent')}
                                                </div>
                                            )}
                                        </div>
                                    )}

                                <div className="flex items-center gap-4">
                                    <Button
                                        disabled={processing}
                                        data-test="update-profile-button"
                                    >
                                        {t('common.save')}
                                    </Button>

                                    <Transition
                                        show={recentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-neutral-600">
                                            {t('common.saved')}
                                        </p>
                                    </Transition>
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                <Separator />

                <DeleteUser />
            </CentralUserSettingsLayout>
        </>
    );
}

Profile.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default Profile;
