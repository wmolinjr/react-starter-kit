import HeadingSmall from '@/components/shared/typography/heading-small';
import InputError from '@/components/shared/feedback/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useRef } from 'react';

import userSettings from '@/routes/tenant/admin/user-settings';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const { t } = useLaravelReactI18n();
    const { errors } = usePage().props as { errors?: Record<string, string> };

    return (
        <div className="space-y-6">
            <HeadingSmall
                title={t('settings.delete.title')}
                description={t('settings.delete.description')}
            />
            <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                <div className="relative space-y-0.5 text-red-600 dark:text-red-100">
                    <p className="font-medium">{t('common.warning')}</p>
                    <p className="text-sm">
                        {t('settings.delete.warning')}
                    </p>
                </div>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            data-test="delete-user-button"
                        >
                            {t('settings.delete.title')}
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            {t('settings.delete.confirm_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('settings.delete.confirm_description')}
                        </DialogDescription>

                        <Form
                            {...userSettings.profile.destroy()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="password"
                                            className="sr-only"
                                        >
                                            {t('common.password')}
                                        </Label>

                                        <Input
                                            id="password"
                                            type="password"
                                            name="password"
                                            ref={passwordInput}
                                            placeholder={t('common.password')}
                                            autoComplete="current-password"
                                        />

                                        <InputError message={errors?.password} />
                                    </div>

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                {t('common.cancel')}
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            disabled={processing}
                                            asChild
                                        >
                                            <button
                                                type="submit"
                                                data-test="confirm-delete-user-button"
                                            >
                                                {t('settings.delete.title')}
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}
