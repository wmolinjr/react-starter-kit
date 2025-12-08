import { type BreadcrumbItem } from '@/types';
import { createContext, useContext, useState, useCallback, useEffect, useMemo, type ReactNode } from 'react';

interface BreadcrumbContextType {
    breadcrumbs: BreadcrumbItem[];
    setBreadcrumbs: (items: BreadcrumbItem[]) => void;
}

const BreadcrumbContext = createContext<BreadcrumbContextType | null>(null);

/**
 * Provider for breadcrumb context.
 * Used by persistent layouts to share breadcrumb state.
 */
export function BreadcrumbProvider({ children }: { children: ReactNode }) {
    const [breadcrumbs, setBreadcrumbsState] = useState<BreadcrumbItem[]>([]);

    const setBreadcrumbs = useCallback((items: BreadcrumbItem[]) => {
        setBreadcrumbsState(items);
    }, []);

    // Memoize context value to prevent unnecessary re-renders
    const contextValue = useMemo(
        () => ({ breadcrumbs, setBreadcrumbs }),
        [breadcrumbs, setBreadcrumbs]
    );

    return (
        <BreadcrumbContext.Provider value={contextValue}>
            {children}
        </BreadcrumbContext.Provider>
    );
}

/**
 * Hook to access breadcrumbs in layouts.
 */
export function useBreadcrumbs(): BreadcrumbItem[] {
    const context = useContext(BreadcrumbContext);
    if (!context) {
        return [];
    }
    return context.breadcrumbs;
}

/**
 * Hook for pages to set their breadcrumbs.
 * Call this in page components to update the layout's breadcrumbs.
 *
 * @example
 * function MyPage() {
 *     useSetBreadcrumbs([{ title: 'Home', href: '/' }]);
 *     return <div>...</div>;
 * }
 */
export function useSetBreadcrumbs(items: BreadcrumbItem[]) {
    const context = useContext(BreadcrumbContext);
    // Serialize to avoid infinite loops with array dependency
    const serialized = JSON.stringify(items);

    useEffect(() => {
        if (context) {
            context.setBreadcrumbs(JSON.parse(serialized));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- setBreadcrumbs is stable (useCallback)
    }, [serialized]);
}
