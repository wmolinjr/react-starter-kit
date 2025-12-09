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
            self::VIEW => ['en' => 'View', 'pt_BR' => 'Visualizar'],
            self::CREATE => ['en' => 'Create', 'pt_BR' => 'Criar'],
            self::EDIT => ['en' => 'Edit', 'pt_BR' => 'Editar'],
            self::EDIT_OWN => ['en' => 'Edit Own', 'pt_BR' => 'Editar Próprio'],
            self::DELETE => ['en' => 'Delete', 'pt_BR' => 'Excluir'],
            self::UPLOAD => ['en' => 'Upload', 'pt_BR' => 'Enviar'],
            self::DOWNLOAD => ['en' => 'Download', 'pt_BR' => 'Baixar'],
            self::ARCHIVE => ['en' => 'Archive', 'pt_BR' => 'Arquivar'],
            self::INVITE => ['en' => 'Invite', 'pt_BR' => 'Convidar'],
            self::REMOVE => ['en' => 'Remove', 'pt_BR' => 'Remover'],
            self::MANAGE_ROLES => ['en' => 'Manage Roles', 'pt_BR' => 'Gerenciar Papéis'],
            self::ACTIVITY => ['en' => 'Activity', 'pt_BR' => 'Atividade'],
            self::DANGER => ['en' => 'Danger Zone', 'pt_BR' => 'Zona de Perigo'],
            self::MANAGE => ['en' => 'Manage', 'pt_BR' => 'Gerenciar'],
            self::INVOICES => ['en' => 'Invoices', 'pt_BR' => 'Faturas'],
            self::EXPORT => ['en' => 'Export', 'pt_BR' => 'Exportar'],
            self::SCHEDULE => ['en' => 'Schedule', 'pt_BR' => 'Agendar'],
            self::CUSTOMIZE => ['en' => 'Customize', 'pt_BR' => 'Personalizar'],
            self::CONFIGURE => ['en' => 'Configure', 'pt_BR' => 'Configurar'],
            self::TEST_CONNECTION => ['en' => 'Test Connection', 'pt_BR' => 'Testar Conexão'],
            self::PREVIEW => ['en' => 'Preview', 'pt_BR' => 'Pré-visualizar'],
            self::PUBLISH => ['en' => 'Publish', 'pt_BR' => 'Publicar'],
            self::LEAVE => ['en' => 'Leave', 'pt_BR' => 'Sair'],
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
