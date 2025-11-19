import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import tenants from '@/routes/tenants';
import type { Domain } from '@/types';
import { router } from '@inertiajs/react';
import { Check, Copy, RefreshCw } from 'lucide-react';
import { useState } from 'react';

interface DomainVerificationInstructionsProps {
    domain: Domain;
    tenantSlug: string;
}

export function DomainVerificationInstructions({
    domain,
    tenantSlug,
}: DomainVerificationInstructionsProps) {
    const [copied, setCopied] = useState(false);
    const [verifying, setVerifying] = useState(false);

    const copyToClipboard = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch (err) {
            console.error('Failed to copy:', err);
        }
    };

    const handleVerify = () => {
        setVerifying(true);
        router.post(
            tenants.domains.verify({ slug: tenantSlug, domain: domain.id }).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setVerifying(false),
                onError: (errors) => {
                    console.error('Verification failed:', errors);
                },
            }
        );
    };

    if (!domain.verification_token) {
        return null;
    }

    const txtRecordHost = `_tenant-verify.${domain.domain}`;
    const txtRecordValue = domain.verification_token;

    return (
        <Card>
            <CardHeader>
                <CardTitle>DNS Verification Required</CardTitle>
                <CardDescription>
                    Add the following TXT record to your domain's DNS settings to verify ownership.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="space-y-3">
                    <div className="grid grid-cols-[120px_1fr] gap-2 rounded-lg bg-muted p-4 font-mono text-sm">
                        <div className="font-semibold">Record Type:</div>
                        <div>TXT</div>

                        <div className="font-semibold">Host:</div>
                        <div className="break-all">{txtRecordHost}</div>

                        <div className="font-semibold">Value:</div>
                        <div className="flex items-start justify-between gap-2">
                            <code className="break-all">{txtRecordValue}</code>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-8 w-8 shrink-0 p-0"
                                onClick={() => copyToClipboard(txtRecordValue)}
                            >
                                {copied ? (
                                    <Check className="h-4 w-4 text-green-600" />
                                ) : (
                                    <Copy className="h-4 w-4" />
                                )}
                            </Button>
                        </div>

                        <div className="font-semibold">TTL:</div>
                        <div>3600</div>
                    </div>

                    <div className="rounded-lg bg-blue-50 p-4 text-sm text-blue-900 dark:bg-blue-950/50 dark:text-blue-100">
                        <p className="font-medium">Important:</p>
                        <ul className="mt-2 list-inside list-disc space-y-1 text-xs">
                            <li>DNS changes can take up to 48 hours to propagate</li>
                            <li>Some DNS providers may require you to omit the domain from the host</li>
                            <li>Ensure there are no conflicting TXT records with the same host</li>
                        </ul>
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    <Button onClick={handleVerify} disabled={verifying}>
                        <RefreshCw className={`mr-2 h-4 w-4 ${verifying ? 'animate-spin' : ''}`} />
                        {verifying ? 'Verifying...' : 'Verify Domain'}
                    </Button>
                    <p className="text-xs text-muted-foreground">
                        Click to check if the DNS record has been added
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}
