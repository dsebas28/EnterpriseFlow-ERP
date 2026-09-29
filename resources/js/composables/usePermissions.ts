import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * UI-only permission checks (show/hide actions). The backend re-authorises
 * every request, so hiding something here is never a security boundary.
 */
export function usePermissions() {
    const page = usePage<SharedData>();
    const permissions = computed(() => new Set(page.props.auth?.permissions ?? []));

    const can = (permission: string) => permissions.value.has(permission);
    const canAny = (...list: string[]) => list.some(can);

    return { can, canAny };
}
