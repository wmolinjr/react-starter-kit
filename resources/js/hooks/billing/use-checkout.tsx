import {
    createContext,
    useContext,
    useState,
    useCallback,
    useEffect,
    useRef,
    type ReactNode,
} from 'react';
import { toast } from 'sonner';
import type { BillingPeriod } from '@/types/enums';
import type { BillingProduct, CheckoutItem } from '@/types/billing';

/** Time in milliseconds before cart expires (24 hours) */
const CART_EXPIRY_MS = 24 * 60 * 60 * 1000;

/** Stored cart data structure */
interface StoredCart {
    items: CheckoutItem[];
    timestamp: number;
}

interface CheckoutContextValue {
    /** Items in the cart */
    items: CheckoutItem[];

    /** Whether the checkout sheet is open */
    isOpen: boolean;

    /** Default billing period for new items */
    defaultBillingPeriod: BillingPeriod;

    /** Whether cart is being restored from storage */
    isRestoring: boolean;

    // Actions
    /** Add an item to the cart */
    addItem: (item: Omit<CheckoutItem, 'id'>, showToast?: boolean) => void;

    /** Remove an item from the cart */
    removeItem: (id: string, showToast?: boolean) => void;

    /** Update item quantity */
    updateQuantity: (id: string, quantity: number) => void;

    /** Update item billing period */
    updateBillingPeriod: (id: string, billingPeriod: BillingPeriod) => void;

    /** Clear all items */
    clearCart: (showToast?: boolean) => void;

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

    /** Time remaining until cart expires (in minutes) */
    expiresInMinutes: number | null;
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

// Generate UUID with fallback for older browsers
function generateId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    // Fallback for environments without crypto.randomUUID
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

/**
 * Load cart from localStorage with expiry check
 */
function loadStoredCart(): { items: CheckoutItem[]; timestamp: number } {
    if (typeof window === 'undefined') {
        return { items: [], timestamp: Date.now() };
    }

    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (!stored) {
            return { items: [], timestamp: Date.now() };
        }

        const data = JSON.parse(stored);

        // Handle legacy format (array only)
        if (Array.isArray(data)) {
            return { items: data, timestamp: Date.now() };
        }

        // Check expiry
        const storedCart = data as StoredCart;
        const now = Date.now();
        const isExpired = now - storedCart.timestamp > CART_EXPIRY_MS;

        if (isExpired) {
            localStorage.removeItem(STORAGE_KEY);
            return { items: [], timestamp: now };
        }

        return storedCart;
    } catch {
        return { items: [], timestamp: Date.now() };
    }
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
    const [items, setItems] = useState<CheckoutItem[]>([]);
    const [cartTimestamp, setCartTimestamp] = useState<number>(Date.now());
    const [isRestoring, setIsRestoring] = useState(true);
    const [isOpen, setIsOpen] = useState(false);
    const [defaultBillingPeriod, setDefaultBillingPeriod] = useState<BillingPeriod>('monthly');
    const initialLoadRef = useRef(false);

    // Initialize cart from storage
    useEffect(() => {
        if (initialLoadRef.current) return;
        initialLoadRef.current = true;

        const { items: storedItems, timestamp } = loadStoredCart();
        setItems(storedItems);
        setCartTimestamp(timestamp);
        setIsRestoring(false);

        // Show notification if cart was restored with items
        if (storedItems.length > 0) {
            const itemCount = storedItems.reduce((sum, item) => sum + item.quantity, 0);
            toast.info(`Cart restored with ${itemCount} item${itemCount > 1 ? 's' : ''}`, {
                description: 'Your previous cart has been restored.',
                duration: 3000,
            });
        }
    }, []);

    // Persist to localStorage with timestamp
    useEffect(() => {
        if (typeof window !== 'undefined' && !isRestoring) {
            const cartData: StoredCart = {
                items,
                timestamp: items.length > 0 ? cartTimestamp : Date.now(),
            };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(cartData));
        }
    }, [items, cartTimestamp, isRestoring]);

    // Calculate expiry time
    const expiresInMinutes = items.length > 0
        ? Math.max(0, Math.floor((CART_EXPIRY_MS - (Date.now() - cartTimestamp)) / 60000))
        : null;

    const addItem = useCallback((item: Omit<CheckoutItem, 'id'>, showToast = true) => {
        setItems((prev) => {
            const existingItem = prev.find((i) => i.product.slug === item.product.slug);

            if (existingItem && item.product.type !== 'plan') {
                // Update quantity for existing addon/bundle
                if (showToast) {
                    toast.success('Quantity updated', {
                        description: `${item.product.name} quantity increased`,
                        duration: 2000,
                    });
                }
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
            if (showToast) {
                toast.success('Added to cart', {
                    description: item.product.name,
                    duration: 2000,
                });
            }
            return [...prev, { ...item, id: generateId() }];
        });

        // Update timestamp when adding items
        setCartTimestamp(Date.now());
        setIsOpen(true);
    }, []);

    const removeItem = useCallback((id: string, showToast = true) => {
        setItems((prev) => {
            const item = prev.find((i) => i.id === id);
            if (item && showToast) {
                toast.info('Removed from cart', {
                    description: item.product.name,
                    duration: 2000,
                });
            }
            return prev.filter((i) => i.id !== id);
        });
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

    const clearCart = useCallback((showToast = true) => {
        setItems([]);
        setCartTimestamp(Date.now());
        if (showToast) {
            toast.info('Cart cleared', {
                description: 'All items have been removed',
                duration: 2000,
            });
        }
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
        isRestoring,
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
        expiresInMinutes,
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
            isRestoring: false,
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
            expiresInMinutes: null,
        };
    }

    return context;
}

/**
 * Pricing info for createCheckoutItem
 */
interface CreateCheckoutItemPricing {
    price: number;
    formattedPrice: string;
}

/**
 * createCheckoutItem - Helper to create a checkout item from a product
 *
 * @param product - The product being added to cart
 * @param pricing - Current pricing (based on selected period)
 * @param quantity - Quantity to add
 * @param billingPeriod - Selected billing period
 * @param isRecurring - Whether this is a recurring charge
 * @param pricingByPeriod - Optional pricing for both periods (enables dynamic price updates)
 */
export function createCheckoutItem(
    product: BillingProduct,
    pricing: CreateCheckoutItemPricing,
    quantity: number,
    billingPeriod: BillingPeriod,
    isRecurring: boolean,
    pricingByPeriod?: {
        monthly?: CreateCheckoutItemPricing;
        yearly?: CreateCheckoutItemPricing;
    }
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
        pricingByPeriod,
    };
}
