import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Check } from 'lucide-react';
import type { AddonCatalogItem } from '@/types/addons';

interface AddonCardProps {
    addon: AddonCatalogItem;
    onPurchase: (slug: string, billingPeriod: 'monthly' | 'yearly' | 'one_time') => void;
    disabled?: boolean;
}

export function AddonCard({ addon, onPurchase, disabled }: AddonCardProps) {
    const monthlyPrice = addon.billing.monthly?.formatted_price;
    const yearlyPrice = addon.billing.yearly?.formatted_price;
    const oneTimePrice = addon.billing.one_time?.formatted_price;

    return (
        <Card className={!addon.is_available ? 'opacity-60' : ''}>
            <CardHeader>
                <div className="flex items-start justify-between">
                    <CardTitle className="text-lg">{addon.name}</CardTitle>
                    {addon.badge && <Badge variant="secondary">{addon.badge}</Badge>}
                </div>
                <CardDescription>{addon.description}</CardDescription>
            </CardHeader>

            <CardContent>
                <div className="space-y-4">
                    {monthlyPrice && (
                        <div>
                            <div className="text-3xl font-bold">{monthlyPrice}</div>
                            <div className="text-muted-foreground text-sm">per month</div>
                        </div>
                    )}

                    {yearlyPrice && (
                        <div className="text-muted-foreground text-sm">or {yearlyPrice}/year (save 2 months)</div>
                    )}

                    {oneTimePrice && !monthlyPrice && (
                        <div>
                            <div className="text-3xl font-bold">{oneTimePrice}</div>
                            <div className="text-muted-foreground text-sm">one-time purchase</div>
                        </div>
                    )}

                    {addon.features && addon.features.length > 0 && (
                        <ul className="space-y-2 text-sm">
                            {addon.features.map((feature, i) => (
                                <li key={i} className="flex items-start">
                                    <Check className="mr-2 mt-0.5 h-4 w-4 text-green-500" />
                                    <span>{feature}</span>
                                </li>
                            ))}
                        </ul>
                    )}

                    {addon.current_quantity > 0 && (
                        <Badge variant="outline">Active: {addon.current_quantity} units</Badge>
                    )}
                </div>
            </CardContent>

            <CardFooter className="flex gap-2">
                {monthlyPrice && (
                    <Button
                        onClick={() => onPurchase(addon.slug, 'monthly')}
                        disabled={disabled || !addon.is_available}
                        className="flex-1"
                    >
                        Add Monthly
                    </Button>
                )}

                {yearlyPrice && (
                    <Button
                        onClick={() => onPurchase(addon.slug, 'yearly')}
                        disabled={disabled || !addon.is_available}
                        variant="outline"
                        className="flex-1"
                    >
                        Add Yearly
                    </Button>
                )}

                {oneTimePrice && !monthlyPrice && (
                    <Button
                        onClick={() => onPurchase(addon.slug, 'one_time')}
                        disabled={disabled || !addon.is_available}
                        className="flex-1"
                    >
                        Purchase
                    </Button>
                )}
            </CardFooter>
        </Card>
    );
}
