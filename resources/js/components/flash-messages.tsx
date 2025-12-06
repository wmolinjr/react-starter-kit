import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import type { FlashMessages as FlashMessagesType } from '@/types';

/**
 * FlashMessages component - Displays toast notifications from Laravel flash messages
 *
 * This component listens to flash messages from the Inertia page props
 * and displays them as toast notifications using Sonner.
 *
 * Flash messages are set in Laravel controllers:
 * - return redirect()->back()->with('success', 'Item created!');
 * - return redirect()->back()->with('error', 'Something went wrong.');
 */
export function FlashMessages() {
    const { flash } = usePage<{ flash: FlashMessagesType }>().props;

    // Track shown messages to avoid duplicates on re-renders
    const shownMessages = useRef<Set<string>>(new Set());

    useEffect(() => {
        // Create a unique key for each flash state
        const flashKey = JSON.stringify(flash);

        // Skip if we've already shown this exact flash state
        if (shownMessages.current.has(flashKey)) {
            return;
        }

        // Show toasts for each flash type
        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }

        if (flash.warning) {
            toast.warning(flash.warning);
        }

        if (flash.info) {
            toast.info(flash.info);
        }

        // Mark this flash state as shown
        if (flash.success || flash.error || flash.warning || flash.info) {
            shownMessages.current.add(flashKey);

            // Clean up old entries to prevent memory leak
            if (shownMessages.current.size > 100) {
                const entries = Array.from(shownMessages.current);
                shownMessages.current = new Set(entries.slice(-50));
            }
        }
    }, [flash]);

    return null;
}
