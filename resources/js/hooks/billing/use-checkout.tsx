import {
    createContext,
    useContext,
    useState,
    useCallback,
    useEffect,
    type ReactNode,
} from 'react';
import type { BillingPeriod } from '@/types/enums';
import type { BillingProduct, CheckoutItem } from '@/types/billing';

interface CheckoutContextValue {
    /** Items in the cart */
    items: CheckoutItem[];

    /** Whether the checkout sheet is open */
    isOpen: boolean;

    /** Default billing period for new items */
    defaultBillingPeriod: BillingPeriod;

    // Actions
    /** Add an item to the cart */
    addItem: (item: Omit<CheckoutItem, 'id'>) => void;

    /** Remove an item from the cart */
    removeItem: (id: string) => void;

    /** Update item quantity */
    updateQuantity: (id: string, quantity: number) => void;

    /** Update item billing period */
    updateBillingPeriod: (id: string, billingPeriod: BillingPeriod) => void;

    /** Clear all items */
    clearCart: () => void;

    /** Open the checkout sheet */
    open: () => void;

    /** Close the checkout sheet */
    close: () => void;

    /** Set default billing period */
    setDefaultBillingPeriod: (period: BillingPeriod) => void;

    // Computed values
    /** Total item count */
    itemCount: number;

    /** Subtotal (sum of all items) */
    subtotal: number;

    /** Total discount amount */
    discount: number;

    /** Final total */
    total: number;

    /** Check if cart has items */
    hasItems: boolean;

    /** Check if a product is in cart */
    hasProduct: (slug: string) => boolean;

    /** Get item by product slug */
    getItemBySlug: (slug: string) => CheckoutItem | undefined;
}

const STORAGE_KEY = 'checkout-cart';

const CheckoutContext = createContext<CheckoutContextValue | null>(null);

// Helper function to format price
function formatPrice(amount: number, currency = 'USD'): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: amount % 100 === 0 ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(amount / 100);
}

// Generate UUID
function generateId(): string {
    return crypto.randomUUID();
}

/**
 * CheckoutProvider - Provides checkout cart state to children
 *
 * @example
 * <CheckoutProvider>
 *     <BillingPage />
 * </CheckoutProvider>
 */
export function CheckoutProvider({ children }: { children: ReactNode }) {
    const [items, setItems] = useState<CheckoutItem[]>(() => {
        if (typeof window === 'undefined') return [];

        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            return stored ? JSON.parse(stored) : [];
        } catch {
            return [];
        }
    });

    const [isOpen, setIsOpen] = useState(false);
    const [defaultBillingPeriod, setDefaultBillingPeriod] = useState<BillingPeriod>('monthly');

    // Persist to localStorage
    useEffect(() => {
        if (typeof window !== 'undefined') {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
        }
    }, [items]);

    const addItem = useCallback((item: Omit<CheckoutItem, 'id'>) => {
        setItems((prev) => {
            const existingItem = prev.find((i) => i.product.slug === item.product.slug);

            if (existingItem && item.product.type !== 'plan') {
                // Update quantity for existing addon/bundle
                return prev.map((i) =>
                    i.id === existingItem.id
                        ? {
                              ...i,
                              quantity: i.quantity + item.quantity,
                              totalPrice: i.unitPrice * (i.quantity + item.quantity),
                              formattedTotalPrice: formatPrice(
                                  i.unitPrice * (i.quantity + item.quantity)
                              ),
                          }
                        : i
                );
            }

            // Add new item
            return [...prev, { ...item, id: generateId() }];
        });

        setIsOpen(true);
    }, []);

    const removeItem = useCallback((id: string) => {
        setItems((prev) => prev.filter((item) => item.id !== id));
    }, []);

    const updateQuantity = useCallback((id: string, quantity: number) => {
        setItems((prev) =>
            prev.map((item) =>
                item.id === id
                    ? {
                          ...item,
                          quantity,
                          totalPrice: item.unitPrice * quantity,
                          formattedTotalPrice: formatPrice(item.unitPrice * quantity),
                      }
                    : item
            )
        );
    }, []);

    const updateBillingPeriodForItem = useCallback((id: string, billingPeriod: BillingPeriod) => {
        setItems((prev) =>
            prev.map((item) => (item.id === id ? { ...item, billingPeriod } : item))
        );
    }, []);

    const clearCart = useCallback(() => {
        setItems([]);
    }, []);

    const open = useCallback(() => setIsOpen(true), []);
    const close = useCallback(() => setIsOpen(false), []);

    const hasProduct = useCallback(
        (slug: string) => items.some((item) => item.product.slug === slug),
        [items]
    );

    const getItemBySlug = useCallback(
        (slug: string) => items.find((item) => item.product.slug === slug),
        [items]
    );

    // Computed values
    const itemCount = items.reduce((sum, item) => sum + item.quantity, 0);
    const subtotal = items.reduce((sum, item) => sum + item.totalPrice, 0);
    const discount = 0; // Future: calculate bundle discounts
    const total = subtotal - discount;
    const hasItems = items.length > 0;

    const value: CheckoutContextValue = {
        items,
        isOpen,
        defaultBillingPeriod,
        addItem,
        removeItem,
        updateQuantity,
        updateBillingPeriod: updateBillingPeriodForItem,
        clearCart,
        open,
        close,
        setDefaultBillingPeriod,
        itemCount,
        subtotal,
        discount,
        total,
        hasItems,
        hasProduct,
        getItemBySlug,
    };

    return (
        <CheckoutContext.Provider value={value}>
            {children}
        </CheckoutContext.Provider>
    );
}

/**
 * useCheckout - Access checkout cart state
 *
 * Must be used within a CheckoutProvider.
 *
 * @example
 * const { items, addItem, clearCart, total } = useCheckout();
 *
 * @example
 * // Add addon to cart
 * addItem({
 *     product: addon,
 *     quantity: 1,
 *     billingPeriod: 'monthly',
 *     unitPrice: addon.pricing.monthly.price,
 *     totalPrice: addon.pricing.monthly.price,
 *     isRecurring: true,
 *     formattedUnitPrice: addon.pricing.monthly.formattedPrice,
 *     formattedTotalPrice: addon.pricing.monthly.formattedPrice,
 * });
 */
export function useCheckout(): CheckoutContextValue {
    const context = useContext(CheckoutContext);

    if (!context) {
        throw new Error('useCheckout must be used within a CheckoutProvider');
    }

    return context;
}

/**
 * useCheckoutSafe - Access checkout state with fallback
 *
 * Returns empty cart if not within a provider.
 */
export function useCheckoutSafe(): CheckoutContextValue {
    const context = useContext(CheckoutContext);

    if (!context) {
        return {
            items: [],
            isOpen: false,
            defaultBillingPeriod: 'monthly',
            addItem: () => {},
            removeItem: () => {},
            updateQuantity: () => {},
            updateBillingPeriod: () => {},
            clearCart: () => {},
            open: () => {},
            close: () => {},
            setDefaultBillingPeriod: () => {},
            itemCount: 0,
            subtotal: 0,
            discount: 0,
            total: 0,
            hasItems: false,
            hasProduct: () => false,
            getItemBySlug: () => undefined,
        };
    }

    return context;
}

/**
 * createCheckoutItem - Helper to create a checkout item from a product
 */
export function createCheckoutItem(
    product: BillingProduct,
    pricing: { price: number; formattedPrice: string },
    quantity: number,
    billingPeriod: BillingPeriod,
    isRecurring: boolean
): Omit<CheckoutItem, 'id'> {
    const totalPrice = pricing.price * quantity;

    return {
        product,
        quantity,
        billingPeriod,
        unitPrice: pricing.price,
        totalPrice,
        isRecurring,
        formattedUnitPrice: pricing.formattedPrice,
        formattedTotalPrice: formatPrice(totalPrice),
    };
}
