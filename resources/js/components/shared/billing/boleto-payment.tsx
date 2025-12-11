import { useState } from 'react';
import { toast } from 'sonner';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Copy, Download, FileText, CheckCircle2 } from 'lucide-react';

export interface BoletoPaymentProps {
    /** URL to download the boleto PDF */
    boletoUrl: string;
    /** Barcode line (linha digitavel) */
    barcode: string;
    /** Due date in formatted string */
    dueDate: string;
    /** Formatted amount (e.g., "R$ 49,90") */
    amount: string;
}

/**
 * Boleto Payment Component
 *
 * Displays boleto information with barcode copy and PDF download.
 * Used for async payment in Brazilian payment flows.
 */
export function BoletoPayment({
    boletoUrl,
    barcode,
    dueDate,
    amount,
}: BoletoPaymentProps) {
    const { t } = useLaravelReactI18n();
    const [copied, setCopied] = useState(false);

    const copyBarcode = async () => {
        try {
            await navigator.clipboard.writeText(barcode);
            setCopied(true);
            toast.success(t('billing.boleto.barcode_copied'));
            setTimeout(() => setCopied(false), 2000);
        } catch {
            toast.error(t('billing.boleto.copy_error'));
        }
    };

    // Format barcode for display (groups of 5 digits)
    const formatBarcode = (code: string) => {
        return code.replace(/(\d{5})/g, '$1 ').trim();
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <FileText className="h-5 w-5" />
                    {t('billing.boleto.bank_slip')}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Info */}
                <div className="grid grid-cols-2 gap-4 rounded-lg bg-muted p-4">
                    <div>
                        <p className="text-sm text-muted-foreground">{t('billing.boleto.amount')}</p>
                        <p className="font-semibold">{amount}</p>
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {t('billing.boleto.due_date')}
                        </p>
                        <p
                            className="font-semibold"
                            data-testid="boleto-due-date"
                        >
                            {dueDate}
                        </p>
                    </div>
                </div>

                {/* Barcode */}
                <div className="space-y-2">
                    <p className="text-sm text-muted-foreground">
                        {t('billing.boleto.barcode_line')}
                    </p>
                    <div
                        className="break-all rounded bg-muted p-3 font-mono text-sm"
                        data-testid="boleto-barcode"
                    >
                        {formatBarcode(barcode)}
                    </div>
                    <Button
                        variant="outline"
                        className="w-full"
                        onClick={copyBarcode}
                    >
                        {copied ? (
                            <CheckCircle2 className="mr-2 h-4 w-4" />
                        ) : (
                            <Copy className="mr-2 h-4 w-4" />
                        )}
                        {copied ? t('billing.boleto.copied') : t('billing.boleto.copy_barcode')}
                    </Button>
                </div>

                {/* Download */}
                <Button asChild className="w-full" data-testid="boleto-download">
                    <a
                        href={boletoUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <Download className="mr-2 h-4 w-4" />
                        {t('billing.boleto.download_pdf')}
                    </a>
                </Button>

                {/* Instructions */}
                <div className="space-y-1 text-center text-sm text-muted-foreground">
                    <p>{t('billing.boleto.instruction_1')}</p>
                    <p>{t('billing.boleto.instruction_2')}</p>
                </div>
            </CardContent>
        </Card>
    );
}
