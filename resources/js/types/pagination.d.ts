/**
 * Pagination Types - Common pagination interfaces for Laravel API responses
 *
 * DO NOT EDIT MANUALLY!
 * These types match Laravel's paginator responses.
 */

/**
 * Links for paginated response
 */
export interface PaginationLinks {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
}

/**
 * Metadata for paginated response
 */
export interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    path: string;
    per_page: number;
    to: number | null;
    total: number;
}

/**
 * Full paginated response with links and meta
 * Used by: Model::paginate()
 */
export interface PaginatedResponse<T> {
    data: T[];
    links: PaginationLinks;
    meta: PaginationMeta;
}

/**
 * Simple paginated response (prev/next only)
 * Used by: Model::simplePaginate()
 */
export interface SimplePaginatedResponse<T> {
    data: T[];
    next_page_url: string | null;
    prev_page_url: string | null;
    per_page: number;
    current_page: number;
}

/**
 * Cursor paginated response
 * Used by: Model::cursorPaginate()
 */
export interface CursorPaginatedResponse<T> {
    data: T[];
    next_cursor: string | null;
    prev_cursor: string | null;
    per_page: number;
}

/**
 * Pagination link for Inertia default format
 */
export interface InertiaPaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/**
 * Inertia paginated response (Laravel's default paginate() format)
 * Used by most Inertia pages when Resource::collection() wraps a paginator
 */
export interface InertiaPaginatedResponse<T> {
    data: T[];
    links: InertiaPaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}
