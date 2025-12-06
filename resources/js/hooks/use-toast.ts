import { toast, type ExternalToast } from 'sonner';

/**
 * useToast hook - Provides toast notification utilities
 *
 * This hook wraps Sonner's toast function with common patterns.
 * Use this for client-side notifications (e.g., after API calls).
 *
 * For server-side flash messages (redirects), the FlashMessages component
 * handles them automatically via Inertia props.
 *
 * @example
 * const { success, error, info, warning, promise } = useToast();
 *
 * // Simple notifications
 * success('Item saved successfully');
 * error('Failed to save item');
 *
 * // With options
 * success('Created!', { description: 'Your item was created.' });
 *
 * // Promise-based (for async operations)
 * promise(saveItem(), {
 *   loading: 'Saving...',
 *   success: 'Saved!',
 *   error: 'Failed to save',
 * });
 */
export function useToast() {
    return {
        /**
         * Show a success toast
         */
        success: (message: string, options?: ExternalToast) => {
            toast.success(message, options);
        },

        /**
         * Show an error toast
         */
        error: (message: string, options?: ExternalToast) => {
            toast.error(message, options);
        },

        /**
         * Show an info toast
         */
        info: (message: string, options?: ExternalToast) => {
            toast.info(message, options);
        },

        /**
         * Show a warning toast
         */
        warning: (message: string, options?: ExternalToast) => {
            toast.warning(message, options);
        },

        /**
         * Show a loading toast that updates based on promise result
         */
        promise: <T>(
            promise: Promise<T>,
            options: {
                loading: string;
                success: string | ((data: T) => string);
                error: string | ((error: unknown) => string);
            },
        ) => {
            return toast.promise(promise, options);
        },

        /**
         * Show a toast with custom content
         */
        custom: toast,

        /**
         * Dismiss a specific toast or all toasts
         */
        dismiss: (toastId?: string | number) => {
            toast.dismiss(toastId);
        },
    };
}

// Re-export toast for direct usage if needed
export { toast };
