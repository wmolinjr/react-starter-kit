import { Head, Link } from '@inertiajs/react';
import { FolderOpen, Plus, Search } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Can } from '@/components/can';

interface Project {
    id: number;
    name: string;
    description: string | null;
    status: string;
    created_at: string;
}

interface Props {
    projects: Project[];
}

export default function ProjectsIndex({ projects }: Props) {
    return (
        <AppLayout>
            <Head title="Projetos" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight flex items-center gap-2">
                            <FolderOpen className="h-8 w-8" />
                            Projetos
                        </h1>
                        <p className="text-muted-foreground mt-2">
                            Gerencie todos os seus projetos
                        </p>
                    </div>

                    <Can permission="canCreateResources">
                        <Link href="/projects/create">
                            <Button>
                                <Plus className="mr-2 h-4 w-4" />
                                Novo Projeto
                            </Button>
                        </Link>
                    </Can>
                </div>

                {/* Search */}
                <div className="flex items-center gap-4">
                    <div className="relative flex-1 max-w-md">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                            placeholder="Buscar projetos..."
                            className="pl-9"
                        />
                    </div>
                </div>

                {/* Projects Grid */}
                {projects.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <FolderOpen className="h-12 w-12 text-muted-foreground mb-4" />
                            <h3 className="text-lg font-semibold mb-2">Nenhum projeto ainda</h3>
                            <p className="text-muted-foreground text-center mb-4">
                                Comece criando seu primeiro projeto
                            </p>
                            <Can permission="canCreateResources">
                                <Link href="/projects/create">
                                    <Button>
                                        <Plus className="mr-2 h-4 w-4" />
                                        Criar Projeto
                                    </Button>
                                </Link>
                            </Can>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {projects.map((project) => (
                            <Link key={project.id} href={`/projects/${project.id}`}>
                                <Card className="hover:border-primary transition-colors cursor-pointer h-full">
                                    <CardHeader>
                                        <div className="flex items-start justify-between">
                                            <CardTitle className="text-lg">{project.name}</CardTitle>
                                            <Badge variant={project.status === 'active' ? 'default' : 'secondary'}>
                                                {project.status === 'active' ? 'Ativo' : 'Arquivado'}
                                            </Badge>
                                        </div>
                                        {project.description && (
                                            <CardDescription className="line-clamp-2">
                                                {project.description}
                                            </CardDescription>
                                        )}
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-xs text-muted-foreground">
                                            Criado em {new Date(project.created_at).toLocaleDateString('pt-BR')}
                                        </p>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
