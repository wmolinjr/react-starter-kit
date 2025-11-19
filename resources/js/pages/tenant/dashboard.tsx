import { Head } from '@inertiajs/react';
import { BarChart3, Users, CreditCard, FolderOpen, TrendingUp } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTenant } from '@/hooks/use-tenant';
import type { Tenant } from '@/types';

interface Props {
    tenant: Tenant;
}

export default function TenantDashboard({ tenant }: Props) {
    const { subscription, hasActiveSubscription, isOnTrial } = useTenant();

    return (
        <AppLayout>
            <Head title="Dashboard" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Dashboard</h1>
                    <p className="text-muted-foreground mt-2">
                        Bem-vindo ao {tenant.name}
                    </p>
                </div>

                {/* Subscription Status */}
                {subscription && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CreditCard className="h-5 w-5" />
                                Status da Assinatura
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-4">
                                <div className={`h-3 w-3 rounded-full ${hasActiveSubscription ? 'bg-green-500' : 'bg-yellow-500'}`} />
                                <div>
                                    <p className="font-medium">
                                        {isOnTrial ? 'Período de Teste' : subscription.active ? 'Ativa' : 'Inativa'}
                                    </p>
                                    {subscription.trial_ends_at && (
                                        <p className="text-sm text-muted-foreground">
                                            Trial termina em {new Date(subscription.trial_ends_at).toLocaleDateString('pt-BR')}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Stats Grid */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Membros
                            </CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">-</div>
                            <p className="text-xs text-muted-foreground">
                                Gerenciar equipe
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Projetos
                            </CardTitle>
                            <FolderOpen className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">-</div>
                            <p className="text-xs text-muted-foreground">
                                Ver todos os projetos
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Atividade
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">-</div>
                            <p className="text-xs text-muted-foreground">
                                Últimos 7 dias
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Relatórios
                            </CardTitle>
                            <BarChart3 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">-</div>
                            <p className="text-xs text-muted-foreground">
                                Ver relatórios
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Quick Actions */}
                <Card>
                    <CardHeader>
                        <CardTitle>Acesso Rápido</CardTitle>
                        <CardDescription>
                            Atalhos para as funções mais usadas
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <a href="/projects" className="block p-4 border rounded-lg hover:bg-accent transition-colors">
                                <FolderOpen className="h-8 w-8 mb-2 text-primary" />
                                <h3 className="font-semibold">Projetos</h3>
                                <p className="text-sm text-muted-foreground">Gerenciar projetos</p>
                            </a>
                            <a href="/team" className="block p-4 border rounded-lg hover:bg-accent transition-colors">
                                <Users className="h-8 w-8 mb-2 text-primary" />
                                <h3 className="font-semibold">Equipe</h3>
                                <p className="text-sm text-muted-foreground">Gerenciar membros</p>
                            </a>
                            <a href="/billing" className="block p-4 border rounded-lg hover:bg-accent transition-colors">
                                <CreditCard className="h-8 w-8 mb-2 text-primary" />
                                <h3 className="font-semibold">Cobrança</h3>
                                <p className="text-sm text-muted-foreground">Planos e faturas</p>
                            </a>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
