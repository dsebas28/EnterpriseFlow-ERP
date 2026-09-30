import type { MoneyValue } from '@/composables/useMoney';
import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
    permissions: string[];
}

export interface Paginated<T> {
    data: T[];
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface CategoryNode {
    id: number;
    parent_id: number | null;
    name: string;
    slug: string;
    description: string | null;
    depth: number;
    products_count: number;
}

export interface Product {
    id: string;
    type: 'simple' | 'variable' | 'variant';
    parent_id: string | null;
    sku: string;
    barcode: string | null;
    name: string;
    description: string | null;
    status: 'active' | 'inactive';
    category?: { id: number; name: string } | null;
    cost: MoneyValue;
    price: MoneyValue;
    tax_rate: string;
    min_stock: number;
    attributes: Record<string, string> | null;
    variants_count?: number;
    variants?: Product[];
    images?: { id: number; url: string }[];
}

export interface Warehouse {
    id: string;
    code: string;
    name: string;
    address: string | null;
    city: string | null;
    is_default: boolean;
    is_active: boolean;
}

export interface Option {
    value: string;
    label: string;
}

export interface CompanySummary {
    id: string;
    name: string;
}

export interface CurrentCompany extends CompanySummary {
    currency: string;
    timezone: string;
}

export interface Tenant {
    current: CurrentCompany | null;
    companies: CompanySummary[];
}

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash: { status: string | null };
    tenant: Tenant | null;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
