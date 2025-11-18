# Laravel + React Starter Kit

## Introduction

Our React starter kit provides a robust, modern starting point for building Laravel applications with a React frontend using [Inertia](https://inertiajs.com).

Inertia allows you to build modern, single-page React applications using classic server-side routing and controllers. This lets you enjoy the frontend power of React combined with the incredible backend productivity of Laravel and lightning-fast Vite compilation.

This React starter kit utilizes React 19, TypeScript, Tailwind, and the [shadcn/ui](https://ui.shadcn.com) and [radix-ui](https://www.radix-ui.com) component libraries.

## Features

### Multi-Tenancy com Subdomínios
Sistema completo de multi-tenancy implementado com suporte a subdomínios e domínios customizados.

- **Roteamento por Subdomínio**: Acesse tenants via `{subdomain}.localhost`
- **Isolamento de Dados**: Cada tenant tem suas próprias páginas e conteúdo isolado
- **Middleware Customizado**: `IdentifyTenantByDomain` identifica automaticamente o tenant
- **Suporte a Domínios Customizados**: Permite que tenants usem seus próprios domínios
- **Tenant Switching**: Usuários podem alternar entre múltiplos tenants
- **Session Sharing**: Autenticação compartilhada entre subdomínios

**Documentação Completa**: Ver [SUBDOMAIN_SETUP.md](SUBDOMAIN_SETUP.md)

**URLs de Exemplo**:
- Central App: `http://localhost`
- Tenant Cliente: `http://cliente.localhost`
- Tenant Acme: `http://acme.localhost`

**Credenciais de Teste**:
- Email: `test@example.com`
- Password: `password`

### Page Builder
Sistema de construção de páginas com blocos reutilizáveis.

- **Blocos Customizáveis**: Hero, Features, CTA, Text, Image, Gallery, Testimonials
- **Editor Visual**: Interface amigável para criar páginas (em desenvolvimento)
- **Tenant-Scoped**: Cada tenant tem suas próprias páginas
- **Versionamento**: Histórico de versões de páginas
- **Status de Publicação**: Draft, Published, Archived
- **Templates**: Páginas pré-configuradas para início rápido

**Documentação Completa**: Ver [PAGE_BUILDER.md](PAGE_BUILDER.md)

### Autenticação
Sistema de autenticação completo usando Laravel Fortify.

- **Login/Register**: Interface moderna com Inertia.js
- **Verificação de Email**: Confirmação de email obrigatória
- **Two-Factor Authentication**: Autenticação de dois fatores
- **Password Reset**: Recuperação de senha via email
- **Profile Management**: Gerenciamento completo de perfil

## Official Documentation

Documentation for all Laravel starter kits can be found on the [Laravel website](https://laravel.com/docs/starter-kits).

## Contributing

Thank you for considering contributing to our starter kit! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## License

The Laravel + React starter kit is open-sourced software licensed under the MIT license.
