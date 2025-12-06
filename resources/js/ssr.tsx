import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { LaravelReactI18nProvider } from 'laravel-react-i18n';
import ReactDOMServer from 'react-dom/server';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => (title ? `${title} - ${appName}` : appName),
        resolve: (name) =>
            resolvePageComponent(
                `./pages/${name}.tsx`,
                import.meta.glob('./pages/**/*.tsx'),
            ),
        setup: ({ App, props }) => {
            const pageProps = props.initialPage.props;
            const locale = (pageProps.locale as string) || (pageProps.fallbackLocale as string) || 'en';
            const fallbackLocale = (pageProps.fallbackLocale as string) || 'en';

            return (
                <LaravelReactI18nProvider
                    locale={locale}
                    fallbackLocale={fallbackLocale}
                    files={import.meta.glob('/lang/*.json', { eager: true })}
                >
                    <App {...props} />
                </LaravelReactI18nProvider>
            );
        },
    }),
);
