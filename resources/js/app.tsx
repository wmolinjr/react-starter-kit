import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { LaravelReactI18nProvider } from 'laravel-react-i18n';
import { StrictMode, type ComponentType, type ReactNode, type ReactElement } from 'react';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/shared/use-appearance';
import { Toaster } from './components/ui/sonner';
import { FlashMessages } from './components/shared/feedback/flash-messages';
import { BreadcrumbProvider } from './contexts/breadcrumb-context';
import { CheckoutProvider, BillingPeriodProvider } from './hooks/billing';

/**
 * Inertia v2: Handle invalid (non-Inertia) responses.
 *
 * This prevents the default modal from showing when we intentionally
 * return JSON responses from API endpoints (like checkout, payments, etc.)
 *
 * @see https://inertiajs.com/events#invalid
 */
router.on('invalid', (event) => {
    const response = event.detail.response;

    // Check if this is an expected JSON API response (not an error)
    // We cancel the event to prevent the Inertia error modal
    const contentType = response.headers?.['content-type'] as string | undefined;
    if (contentType?.includes('application/json')) {
        event.preventDefault();
        return false;
    }
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * Page component type with optional persistent layout.
 * @see https://inertiajs.com/pages#persistent-layouts
 */
type PageComponent = ComponentType<Record<string, unknown>> & {
    layout?: (page: ReactElement) => ReactElement;
};

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
 * Wraps the page with AppShell and applies persistent layout if defined.
 * Persistent layouts don't remount on navigation, preserving their state.
 */
function createWrappedPage(PageComponent: PageComponent) {
    const WrappedPage = (props: Record<string, unknown>) => {
        return (
            <AppShell>
                <PageComponent {...props} />
            </AppShell>
        );
    };

    // Preserve the layout property for Inertia's persistent layout system
    if (PageComponent.layout) {
        WrappedPage.layout = PageComponent.layout;
    }

    return WrappedPage;
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        const pages = import.meta.glob('./pages/**/*.tsx');
        const page = await resolvePageComponent(`./pages/${name}.tsx`, pages) as { default: PageComponent };

        // Apply wrapper and preserve layout
        const wrappedPage = createWrappedPage(page.default);

        return { default: wrappedPage, layout: page.default.layout };
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
                    files={import.meta.glob('/lang/**/*.json', { eager: true })}
                >
                    <BreadcrumbProvider>
                        <BillingPeriodProvider>
                            <CheckoutProvider>
                                <App {...props} />
                                <Toaster position="top-right" richColors closeButton />
                            </CheckoutProvider>
                        </BillingPeriodProvider>
                    </BreadcrumbProvider>
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
