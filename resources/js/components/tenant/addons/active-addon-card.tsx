import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import type { AddonSubscription } from '@/types/addons';

interface ActiveAddonCardProps {
    addon: AddonSubscription;
    onCancel: (addonId: string) => void;
    disabled?: boolean;
}

export function ActiveAddonCard({ addon, onCancel, disabled }: ActiveAddonCardProps) {
    const getBillingLabel = (period: string) => {
        switch (period) {
            case 'monthly':
                return '/month';
            case 'yearly':
                return '/year';
            case 'one_time':
                return 'one-time';
            case 'metered':
                return 'usage-based';
            default:
                return '';
        }
    };

    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between">
                    <CardTitle className="text-lg">{addon.name}</CardTitle>
                    <Badge variant="default">Active</Badge>
                </div>
                {addon.is_metered && addon.metered_usage !== undefined && (
                    <CardDescription>Current usage: {addon.metered_usage.toLocaleString()} units</CardDescription>
                )}
            </CardHeader>

            <CardContent>
                <div className="space-y-2">
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Quantity</span>
                        <span className="font-medium">{addon.quantity}</span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Price</span>
                        <span className="font-medium">
                            {addon.total_price}
                            {getBillingLabel(addon.billing_period)}
                        </span>
                    </div>
                    {addon.expires_at && (
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">Expires</span>
                            <span className="font-medium">{new Date(addon.expires_at).toLocaleDateString()}</span>
                        </div>
                    )}
                </div>
            </CardContent>

            {addon.is_recurring && (
                <CardFooter>
                    <Button variant="destructive" size="sm" onClick={() => onCancel(addon.id)} disabled={disabled}>
                        Cancel
                    </Button>
                </CardFooter>
            )}
        </Card>
    );
}
