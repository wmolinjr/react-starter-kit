import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Loader2, Copy, CheckCircle2, XCircle, Clock } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface PixPaymentProps {
    /** Base64 encoded QR code image or URL */
    qrCodeUrl: string;
    /** PIX copia e cola string */
    qrCodeData: string;
    /** ISO date string when the QR code expires */
    expiresAt: string;
    /** Purchase ID for status polling */
    purchaseId: string;
    /** Callback when payment is confirmed */
    onSuccess?: () => void;
    /** Callback when QR code expires */
    onExpired?: () => void;
    /** Status polling endpoint (default: /api/purchases/{id}/status) */
    statusEndpoint?: string;
    /** Polling interval in ms (default: 3000) */
    pollInterval?: number;
}

type PaymentStatus = 'pending' | 'completed' | 'failed' | 'expired';

/**
 * PIX Payment Component
 *
 * Displays a PIX QR code with real-time status polling and expiration countdown.
 * Used for async payment confirmation in Brazilian payment flows.
 */
export function PixPayment({
    qrCodeUrl,
    qrCodeData,
    expiresAt,
    purchaseId,
    onSuccess,
    onExpired,
    statusEndpoint,
    pollInterval = 3000,
}: PixPaymentProps) {
    const [status, setStatus] = useState<PaymentStatus>('pending');
    const [timeLeft, setTimeLeft] = useState<number>(0);
    const [copied, setCopied] = useState(false);

    // Calculate time left and handle expiration
    useEffect(() => {
        const expires = new Date(expiresAt).getTime();

        const updateTimer = () => {
            const now = Date.now();
            const diff = Math.max(0, Math.floor((expires - now) / 1000));
            setTimeLeft(diff);

            if (diff === 0 && status === 'pending') {
                setStatus('expired');
                onExpired?.();
            }
        };

        updateTimer();
        const interval = setInterval(updateTimer, 1000);

        return () => clearInterval(interval);
    }, [expiresAt, status, onExpired]);

    // Poll for payment status
    useEffect(() => {
        if (status !== 'pending') return;

        const endpoint = statusEndpoint || `/api/purchases/${purchaseId}/status`;

        const poll = async () => {
            try {
                const response = await fetch(endpoint, {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) return;

                const data = await response.json();

                if (data.status === 'completed') {
                    setStatus('completed');
                    onSuccess?.();
                    toast.success('Pagamento confirmado!');
                } else if (data.status === 'failed') {
                    setStatus('failed');
                    toast.error('Pagamento falhou');
                }
            } catch (error) {
                console.error('Failed to check payment status', error);
            }
        };

        const interval = setInterval(poll, pollInterval);

        return () => clearInterval(interval);
    }, [purchaseId, status, onSuccess, statusEndpoint, pollInterval]);

    const copyToClipboard = async () => {
        try {
            await navigator.clipboard.writeText(qrCodeData);
            setCopied(true);
            toast.success('Codigo PIX copiado!');
            setTimeout(() => setCopied(false), 2000);
        } catch {
            toast.error('Erro ao copiar');
        }
    };

    const formatTime = (seconds: number) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    // Success state
    if (status === 'completed') {
        return (
            <Card className="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950">
                <CardContent className="flex flex-col items-center py-8">
                    <CheckCircle2 className="mb-4 h-16 w-16 text-green-600 dark:text-green-400" />
                    <p className="text-lg font-semibold text-green-800 dark:text-green-200">
                        Pagamento confirmado!
                    </p>
                </CardContent>
            </Card>
        );
    }

    // Expired state
    if (status === 'expired') {
        return (
            <Card className="border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950">
                <CardContent className="flex flex-col items-center py-8">
                    <XCircle className="mb-4 h-16 w-16 text-red-600 dark:text-red-400" />
                    <p className="text-lg font-semibold text-red-800 dark:text-red-200">
                        QR Code expirado
                    </p>
                    <Button
                        variant="outline"
                        className="mt-4"
                        onClick={() => window.location.reload()}
                    >
                        Gerar novo QR Code
                    </Button>
                </CardContent>
            </Card>
        );
    }

    // Failed state
    if (status === 'failed') {
        return (
            <Card className="border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950">
                <CardContent className="flex flex-col items-center py-8">
                    <XCircle className="mb-4 h-16 w-16 text-red-600 dark:text-red-400" />
                    <p className="text-lg font-semibold text-red-800 dark:text-red-200">
                        Pagamento falhou
                    </p>
                    <Button
                        variant="outline"
                        className="mt-4"
                        onClick={() => window.location.reload()}
                    >
                        Tentar novamente
                    </Button>
                </CardContent>
            </Card>
        );
    }

    // Pending state - show QR code
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Clock className="h-5 w-5" />
                    Pague com PIX
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col items-center space-y-4">
                {/* QR Code */}
                <div
                    className="rounded-lg border bg-white p-4"
                    data-testid="pix-qr-code"
                >
                    <img
                        src={qrCodeUrl}
                        alt="QR Code PIX"
                        className="h-48 w-48"
                    />
                </div>

                {/* Timer */}
                <div
                    className={cn(
                        'flex items-center gap-2 text-sm',
                        timeLeft < 60
                            ? 'text-red-600 dark:text-red-400'
                            : 'text-muted-foreground',
                    )}
                    data-testid="pix-timer"
                >
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Expira em {formatTime(timeLeft)}
                </div>

                {/* Copy button */}
                <div className="w-full space-y-2">
                    <p className="text-center text-sm text-muted-foreground">
                        Ou copie o codigo PIX:
                    </p>
                    <Button
                        variant="outline"
                        className="w-full"
                        onClick={copyToClipboard}
                        data-testid="pix-copy-button"
                    >
                        {copied ? (
                            <CheckCircle2 className="mr-2 h-4 w-4" />
                        ) : (
                            <Copy className="mr-2 h-4 w-4" />
                        )}
                        {copied ? 'Copiado!' : 'Copiar codigo PIX'}
                    </Button>
                </div>

                {/* Instructions */}
                <div className="space-y-1 text-center text-sm text-muted-foreground">
                    <p>1. Abra o app do seu banco</p>
                    <p>2. Escolha pagar com PIX</p>
                    <p>3. Escaneie o QR Code ou cole o codigo</p>
                </div>
            </CardContent>
        </Card>
    );
}
