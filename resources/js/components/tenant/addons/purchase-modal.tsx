import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useState } from 'react';
import { QuantitySelector } from './quantity-selector';
import { BillingPeriodToggle, type RecurringBillingPeriod } from './billing-period-toggle';
import type { AddonCatalogItem } from '@/types/addons';
import type { BillingPeriod } from '@/types/enums';

interface PurchaseModalProps {
    addon: AddonCatalogItem | null;
    open: boolean;
    onClose: () => void;
    onConfirm: (slug: string, quantity: number, billingPeriod: BillingPeriod) => void;
    isPurchasing: boolean;
}

export function PurchaseModal({ addon, open, onClose, onConfirm, isPurchasing }: PurchaseModalProps) {
    const [quantity, setQuantity] = useState(1);
    const [billingPeriod, setBillingPeriod] = useState<RecurringBillingPeriod>('monthly');

    if (!addon) return null;

    const price = addon.billing[billingPeriod]?.price || 0;
    const totalPrice = price * quantity;
    const formattedTotal = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(totalPrice / 100);

    const handleConfirm = () => {
        onConfirm(addon.slug, quantity, billingPeriod);
    };

    const hasMonthly = !!addon.billing.monthly;
    const hasYearly = !!addon.billing.yearly;

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Purchase {addon.name}</DialogTitle>
                    <DialogDescription>{addon.description}</DialogDescription>
                </DialogHeader>

                <div className="space-y-6 py-4">
                    <div className="space-y-2">
                        <Label>Quantity</Label>
                        <QuantitySelector
                            value={quantity}
                            onChange={setQuantity}
                            min={addon.min_quantity}
                            max={addon.max_quantity}
                            disabled={isPurchasing}
                        />
                        {addon.current_quantity > 0 && (
                            <p className="text-muted-foreground text-sm">Current: {addon.current_quantity} units</p>
                        )}
                    </div>

                    {hasMonthly && hasYearly && (
                        <div className="space-y-2">
                            <Label>Billing Period</Label>
                            <BillingPeriodToggle
                                value={billingPeriod}
                                onChange={setBillingPeriod}
                                monthlyPrice={addon.billing.monthly?.formatted_price}
                                yearlyPrice={addon.billing.yearly?.formatted_price}
                                disabled={isPurchasing}
                            />
                        </div>
                    )}

                    <div className="bg-muted rounded-lg p-4">
                        <div className="flex justify-between text-sm">
                            <span>Subtotal</span>
                            <span>{formattedTotal}</span>
                        </div>
                        <div className="text-muted-foreground flex justify-between text-sm">
                            <span>Billing</span>
                            <span>{billingPeriod === 'monthly' ? 'Monthly' : 'Yearly'}</span>
                        </div>
                        <div className="mt-2 flex justify-between font-semibold">
                            <span>Total</span>
                            <span>{formattedTotal}</span>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose} disabled={isPurchasing}>
                        Cancel
                    </Button>
                    <Button onClick={handleConfirm} disabled={isPurchasing}>
                        {isPurchasing ? 'Processing...' : 'Continue to Checkout'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
