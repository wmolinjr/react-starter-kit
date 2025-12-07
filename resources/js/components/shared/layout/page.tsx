import * as React from 'react';
import { cn } from '@/lib/utils';

function Page({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="page"
            className={cn('space-y-6', className)}
            {...props}
        />
    );
}

function PageHeader({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="page-header"
            className={cn(
                'flex flex-col p-4  md:p-6 shadow-xs bg-muted/30 gap-4 sm:flex-row sm:items-center sm:justify-between',
                className
            )}
            {...props}
        />
    );
}

function PageHeaderContent({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="page-header-content"
            className={cn('space-y-1', className)}
            {...props}
        />
    );
}

function PageHeaderActions({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="page-header-actions"
            className={cn('flex items-center gap-2', className)}
            {...props}
        />
    );
}

interface PageTitleProps extends React.ComponentProps<'h1'> {
    icon?: React.ComponentType<{ className?: string }>;
}

function PageTitle({ className, icon: Icon, children, ...props }: PageTitleProps) {
    return (
        <h1
            data-slot="page-title"
            className={cn('flex items-center gap-2 text-2xl font-bold tracking-tight', className)}
            {...props}
        >
            {Icon && <Icon className="h-6 w-6" />}
            {children}
        </h1>
    );
}

function PageDescription({
    className,
    ...props
}: React.ComponentProps<'p'>) {
    return (
        <p
            data-slot="page-description"
            className={cn('text-muted-foreground', className)}
            {...props}
        />
    );
}

function PageContent({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="page-content"
            className={cn('space-y-6 px-4 md:px-6', className)}
            {...props}
        />
    );
}

export {
    Page,
    PageHeader,
    PageHeaderContent,
    PageHeaderActions,
    PageTitle,
    PageDescription,
    PageContent,
};
