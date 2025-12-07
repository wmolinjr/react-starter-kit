import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { LaravelReactI18nProvider } from 'laravel-react-i18n';
import { StrictMode, type ComponentType, type ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/shared/use-appearance';
import { Toaster } from './components/ui/sonner';
import { FlashMessages } from './components/shared/feedback/flash-messages';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * AppShell wraps page content with FlashMessages.
 * FlashMessages must be inside this shell to access Inertia context via usePage().
 * Note: Toaster is rendered at the root level in setup(), not here.
 */
function AppShell({ children }: { children: ReactNode }) {
    return (
        <>
            {children}
            <FlashMessages />
        </>
    );
}

/**
 * Creates a wrapped page component that includes the AppShell.
 */
function createWrappedPage(PageComponent: ComponentType<Record<string, unknown>>) {
    return function WrappedPage(props: Record<string, unknown>) {
        return (
            <AppShell>
                <PageComponent {...props} />
            </AppShell>
        );
    };
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        const pages = import.meta.glob('./pages/**/*.tsx');
        const page = await resolvePageComponent(`./pages/${name}.tsx`, pages) as { default: ComponentType<Record<string, unknown>> };
        return { default: createWrappedPage(page.default) };
    },
    setup({ el, App, props }) {
        const root = createRoot(el);

        const pageProps = props.initialPage.props;
        const locale = (pageProps.locale as string) || (pageProps.fallbackLocale as string) || 'en';
        const fallbackLocale = (pageProps.fallbackLocale as string) || 'en';

        root.render(
            <StrictMode>
                <LaravelReactI18nProvider
                    locale={locale}
                    fallbackLocale={fallbackLocale}
                    files={import.meta.glob('/lang/*.json', { eager: true })}
                >
                    <App {...props} />
                    <Toaster position="top-right" richColors closeButton />
                </LaravelReactI18nProvider>
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
