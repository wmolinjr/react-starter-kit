import { usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';
import type { AddonSubscription, AddonCatalogItem } from '@/types/addons';

interface AddonsData {
    active: AddonSubscription[];
    catalog: AddonCatalogItem[];
    monthly_cost: number;
    formatted_monthly_cost: string;
}

interface UseAddonsReturn {
    active: AddonSubscription[];
    catalog: AddonCatalogItem[];
    monthlyCost: number;
    formattedMonthlyCost: string;
    hasAddon: (slug: string) => boolean;
    getAddon: (slug: string) => AddonSubscription | undefined;
    getQuantity: (slug: string) => number;
    getCatalogItem: (slug: string) => AddonCatalogItem | undefined;
    isAvailable: (slug: string) => boolean;
    canPurchase: (slug: string, quantity?: number) => boolean;
    getAddonsByType: (type: string) => AddonSubscription[];
    getCatalogByType: (type: string) => AddonCatalogItem[];
}

export function useAddons(): UseAddonsReturn {
    const { addons } = usePage<SharedData & { addons: AddonsData | null }>().props;

    const active = addons?.active || [];
    const catalog = addons?.catalog || [];

    const hasAddon = (slug: string): boolean => {
        return active.some((addon) => addon.slug === slug);
    };

    const getAddon = (slug: string): AddonSubscription | undefined => {
        return active.find((addon) => addon.slug === slug);
    };

    const getQuantity = (slug: string): number => {
        return active.filter((addon) => addon.slug === slug).reduce((sum, addon) => sum + addon.quantity, 0);
    };

    const getCatalogItem = (slug: string): AddonCatalogItem | undefined => {
        return catalog.find((item) => item.slug === slug);
    };

    const isAvailable = (slug: string): boolean => {
        const catalogItem = getCatalogItem(slug);
        return catalogItem?.is_available || false;
    };

    const canPurchase = (slug: string, quantity: number = 1): boolean => {
        const catalogItem = getCatalogItem(slug);
        if (!catalogItem || !catalogItem.is_available) {
            return false;
        }

        const currentQty = getQuantity(slug);
        const newTotal = currentQty + quantity;

        return newTotal >= catalogItem.min_quantity && newTotal <= catalogItem.max_quantity;
    };

    const getAddonsByType = (type: string): AddonSubscription[] => {
        return active.filter((addon) => addon.type === type);
    };

    const getCatalogByType = (type: string): AddonCatalogItem[] => {
        return catalog.filter((item) => item.type === type);
    };

    return {
        active,
        catalog,
        monthlyCost: addons?.monthly_cost || 0,
        formattedMonthlyCost: addons?.formatted_monthly_cost || '$0.00',
        hasAddon,
        getAddon,
        getQuantity,
        getCatalogItem,
        isAvailable,
        canPurchase,
        getAddonsByType,
        getCatalogByType,
    };
}
