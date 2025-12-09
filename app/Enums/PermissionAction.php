<?php

namespace App\Enums;

/**
 * Permission Action Enum
 *
 * Single source of truth for permission actions.
 * Used to provide type-safe action references for permissions.
 *
 * Usage:
 * - PermissionAction::VIEW->value // 'view'
 * - PermissionAction::VIEW->name() // ['en' => 'View', 'pt_BR' => 'Visualizar']
 */
enum PermissionAction: string
{
    case VIEW = 'view';
    case CREATE = 'create';
    case EDIT = 'edit';
    case EDIT_OWN = 'editOwn';
    case DELETE = 'delete';
    case UPLOAD = 'upload';
    case DOWNLOAD = 'download';
    case ARCHIVE = 'archive';
    case INVITE = 'invite';
    case REMOVE = 'remove';
    case MANAGE_ROLES = 'manageRoles';
    case ACTIVITY = 'activity';
    case DANGER = 'danger';
    case MANAGE = 'manage';
    case INVOICES = 'invoices';
    case EXPORT = 'export';
    case SCHEDULE = 'schedule';
    case CUSTOMIZE = 'customize';
    case CONFIGURE = 'configure';
    case TEST_CONNECTION = 'testConnection';
    case PREVIEW = 'preview';
    case PUBLISH = 'publish';
    case LEAVE = 'leave';

    /**
     * Get translatable name.
     *
     * @return array<string, string>
     */
    public function name(): array
    {
        return match ($this) {
            self::VIEW => ['en' => 'View', 'pt_BR' => 'Visualizar', 'es' => 'Ver'],
            self::CREATE => ['en' => 'Create', 'pt_BR' => 'Criar', 'es' => 'Crear'],
            self::EDIT => ['en' => 'Edit', 'pt_BR' => 'Editar', 'es' => 'Editar'],
            self::EDIT_OWN => ['en' => 'Edit Own', 'pt_BR' => 'Editar Próprio', 'es' => 'Editar Propio'],
            self::DELETE => ['en' => 'Delete', 'pt_BR' => 'Excluir', 'es' => 'Eliminar'],
            self::UPLOAD => ['en' => 'Upload', 'pt_BR' => 'Enviar', 'es' => 'Subir'],
            self::DOWNLOAD => ['en' => 'Download', 'pt_BR' => 'Baixar', 'es' => 'Descargar'],
            self::ARCHIVE => ['en' => 'Archive', 'pt_BR' => 'Arquivar', 'es' => 'Archivar'],
            self::INVITE => ['en' => 'Invite', 'pt_BR' => 'Convidar', 'es' => 'Invitar'],
            self::REMOVE => ['en' => 'Remove', 'pt_BR' => 'Remover', 'es' => 'Eliminar'],
            self::MANAGE_ROLES => ['en' => 'Manage Roles', 'pt_BR' => 'Gerenciar Papéis', 'es' => 'Gestionar Roles'],
            self::ACTIVITY => ['en' => 'Activity', 'pt_BR' => 'Atividade', 'es' => 'Actividad'],
            self::DANGER => ['en' => 'Danger Zone', 'pt_BR' => 'Zona de Perigo', 'es' => 'Zona de Peligro'],
            self::MANAGE => ['en' => 'Manage', 'pt_BR' => 'Gerenciar', 'es' => 'Gestionar'],
            self::INVOICES => ['en' => 'Invoices', 'pt_BR' => 'Faturas', 'es' => 'Facturas'],
            self::EXPORT => ['en' => 'Export', 'pt_BR' => 'Exportar', 'es' => 'Exportar'],
            self::SCHEDULE => ['en' => 'Schedule', 'pt_BR' => 'Agendar', 'es' => 'Programar'],
            self::CUSTOMIZE => ['en' => 'Customize', 'pt_BR' => 'Personalizar', 'es' => 'Personalizar'],
            self::CONFIGURE => ['en' => 'Configure', 'pt_BR' => 'Configurar', 'es' => 'Configurar'],
            self::TEST_CONNECTION => ['en' => 'Test Connection', 'pt_BR' => 'Testar Conexão', 'es' => 'Probar Conexión'],
            self::PREVIEW => ['en' => 'Preview', 'pt_BR' => 'Pré-visualizar', 'es' => 'Vista Previa'],
            self::PUBLISH => ['en' => 'Publish', 'pt_BR' => 'Publicar', 'es' => 'Publicar'],
            self::LEAVE => ['en' => 'Leave', 'pt_BR' => 'Sair', 'es' => 'Salir'],
        };
    }

    /**
     * Get translated label for current locale.
     */
    public function label(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $names = $this->name();

        return $names[$locale] ?? $names['en'];
    }

    /**
     * Get all action values as strings.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Convert single action to frontend format.
     *
     * @return array<string, mixed>
     */
    public function toFrontend(?string $locale = null): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label($locale),
        ];
    }

    /**
     * Convert all actions to frontend array format.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function toFrontendArray(?string $locale = null): array
    {
        return array_map(
            fn (self $action) => $action->toFrontend($locale),
            self::cases()
        );
    }
}
