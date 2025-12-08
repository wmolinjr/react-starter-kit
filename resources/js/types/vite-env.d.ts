/// <reference types="vite/client" />

declare module 'laravel-react-i18n/vite' {
    import type { Plugin } from 'vite';
    export default function i18n(): Plugin;
}
