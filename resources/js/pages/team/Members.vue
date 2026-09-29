<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Crown, MailPlus, Search, UserX } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Member {
    id: number;
    status: 'active' | 'suspended';
    joined_at: string | null;
    is_owner: boolean;
    is_current_user: boolean;
    user: { id: number; name: string; email: string; phone: string | null; status: string; last_login_at: string | null };
    roles: { id: number; name: string }[];
}

interface Invitation {
    id: string;
    email: string;
    role: { id: number; name: string };
    invited_by: string | null;
    expires_at: string;
}

interface Role {
    id: number;
    name: string;
    is_owner: boolean;
}

const props = defineProps<{
    members: Paginated<Member>;
    invitations: { data: Invitation[] };
    roles: { data: Role[] };
    filters: { search: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Team', href: '/team/members' },
    { title: 'Members', href: '/team/members' },
];

const page = usePage<SharedData & { errors: Record<string, string> }>();
const ruleError = computed(() => page.props.errors?.rule);

// Search (debounced, keeps URL as the source of truth)
const search = ref(props.filters.search);
watch(
    search,
    useDebounceFn((value: string) => {
        router.get(route('team.members.index'), { search: value || undefined }, { preserveState: true, replace: true });
    }, 300),
);

// Invite
const inviteOpen = ref(false);
const inviteForm = useForm({ email: '', role_id: props.roles.data.find((r) => !r.is_owner)?.id ?? null });
const invite = () =>
    inviteForm.post(route('team.invitations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            inviteOpen.value = false;
            inviteForm.reset('email');
        },
    });

// Edit roles
const editing = ref<Member | null>(null);
const rolesForm = useForm<{ role_ids: number[] }>({ role_ids: [] });
const openRoles = (member: Member) => {
    editing.value = member;
    rolesForm.role_ids = member.roles.map((r) => r.id);
    rolesForm.clearErrors();
};
const saveRoles = () =>
    rolesForm.put(route('team.members.roles', editing.value!.id), {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });

// Suspend / reactivate (destructive action requires confirmation)
const confirming = ref<Member | null>(null);
const toggleStatus = (member: Member) => {
    const action = member.status === 'active' ? 'team.members.suspend' : 'team.members.reactivate';
    router.post(route(action, member.id), {}, { preserveScroll: true, onFinish: () => (confirming.value = null) });
};

const revokeInvitation = (invitation: Invitation) =>
    router.delete(route('team.invitations.destroy', invitation.id), { preserveScroll: true });

const formatDate = (iso: string | null) => (iso ? new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : 'Never');
const initials = (name: string) =>
    name
        .split(' ')
        .map((p) => p[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Team members" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <HeadingSmall title="Team members" description="People who can access this company and what they are allowed to do." />
                <Button @click="inviteOpen = true"><MailPlus class="mr-2 h-4 w-4" /> Invite member</Button>
            </div>

            <p v-if="page.props.flash?.status" class="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-700 dark:text-emerald-300">
                {{ page.props.flash.status }}
            </p>
            <p v-if="ruleError" class="rounded-md border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-700 dark:text-red-300">
                {{ ruleError }}
            </p>

            <div class="relative max-w-sm">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" placeholder="Search by name or email" class="pl-9" />
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Member</th>
                            <th class="px-4 py-3 font-medium">Roles</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="hidden px-4 py-3 font-medium md:table-cell">Last login</th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="member in members.data" :key="member.id" :class="{ 'opacity-60': member.status === 'suspended' }">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                                        {{ initials(member.user.name) }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="flex items-center gap-1 font-medium">
                                            {{ member.user.name }}
                                            <Crown v-if="member.is_owner" class="h-3.5 w-3.5 text-amber-500" aria-label="Owner" />
                                            <span v-if="member.is_current_user" class="text-xs font-normal text-muted-foreground">(you)</span>
                                        </p>
                                        <p class="truncate text-muted-foreground">{{ member.user.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="role in member.roles" :key="role.id" class="rounded-md bg-secondary px-2 py-0.5 text-xs font-medium">
                                        {{ role.name }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1.5 text-xs font-medium"
                                    :class="member.status === 'active' ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="member.status === 'active' ? 'bg-emerald-500' : 'bg-zinc-400'" />
                                    {{ member.status === 'active' ? 'Active' : 'Suspended' }}
                                </span>
                            </td>
                            <td class="hidden px-4 py-3 text-muted-foreground md:table-cell">{{ formatDate(member.user.last_login_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div v-if="!member.is_current_user" class="flex justify-end gap-1">
                                    <Button variant="ghost" size="sm" @click="openRoles(member)">Roles</Button>
                                    <Button variant="ghost" size="sm" @click="confirming = member">
                                        {{ member.status === 'active' ? 'Suspend' : 'Reactivate' }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="members.data.length === 0">
                            <td colspan="5" class="px-4 py-12 text-center text-muted-foreground">
                                <UserX class="mx-auto mb-2 h-8 w-8" />
                                No members match “{{ filters.search }}”.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="members.meta.last_page > 1" class="flex flex-wrap items-center gap-1 text-sm">
                <template v-for="link in members.meta.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        class="rounded-md px-3 py-1.5"
                        :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                    >
                        <span v-html="link.label" />
                    </Link>
                    <span v-else class="px-3 py-1.5 text-muted-foreground" v-html="link.label" />
                </template>
            </nav>

            <section v-if="invitations.data.length" class="space-y-3">
                <h3 class="text-sm font-medium">Pending invitations</h3>
                <ul class="divide-y rounded-lg border">
                    <li v-for="invitation in invitations.data" :key="invitation.id" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                        <div>
                            <p class="font-medium">{{ invitation.email }}</p>
                            <p class="text-muted-foreground">
                                {{ invitation.role.name }} · expires {{ formatDate(invitation.expires_at) }}
                                <template v-if="invitation.invited_by"> · invited by {{ invitation.invited_by }}</template>
                            </p>
                        </div>
                        <Button variant="ghost" size="sm" @click="revokeInvitation(invitation)">Revoke</Button>
                    </li>
                </ul>
            </section>
        </div>

        <!-- Invite dialog -->
        <Dialog v-model:open="inviteOpen">
            <DialogContent>
                <form class="space-y-6" @submit.prevent="invite">
                    <DialogHeader>
                        <DialogTitle>Invite a member</DialogTitle>
                        <DialogDescription>They will receive an email with a link valid for 7 days.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-2">
                        <Label for="invite-email">Email</Label>
                        <Input id="invite-email" v-model="inviteForm.email" type="email" required placeholder="colleague@company.com" />
                        <InputError :message="inviteForm.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="invite-role">Role</Label>
                        <select
                            id="invite-role"
                            v-model="inviteForm.role_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option v-for="role in roles.data" :key="role.id" :value="role.id">{{ role.name }}</option>
                        </select>
                        <InputError :message="inviteForm.errors.role_id" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="inviteOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="inviteForm.processing">Send invitation</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Roles dialog -->
        <Dialog :open="editing !== null" @update:open="(open) => !open && (editing = null)">
            <DialogContent>
                <form class="space-y-6" @submit.prevent="saveRoles">
                    <DialogHeader>
                        <DialogTitle>Roles for {{ editing?.user.name }}</DialogTitle>
                        <DialogDescription>Permissions of all selected roles are combined.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-2">
                        <label v-for="role in roles.data" :key="role.id" class="flex items-center gap-3 rounded-md border px-3 py-2 text-sm hover:bg-muted/50">
                            <input v-model="rolesForm.role_ids" type="checkbox" :value="role.id" class="rounded border-input" />
                            {{ role.name }}
                        </label>
                        <InputError :message="rolesForm.errors.role_ids" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="editing = null">Cancel</Button>
                        <Button type="submit" :disabled="rolesForm.processing || rolesForm.role_ids.length === 0">Save roles</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Suspend confirmation -->
        <Dialog :open="confirming !== null" @update:open="(open) => !open && (confirming = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ confirming?.status === 'active' ? 'Suspend' : 'Reactivate' }} {{ confirming?.user.name }}?</DialogTitle>
                    <DialogDescription v-if="confirming?.status === 'active'">
                        They immediately lose access to this company. Their account and other companies are not affected.
                    </DialogDescription>
                    <DialogDescription v-else>They will regain access with their current roles.</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="secondary" @click="confirming = null">Cancel</Button>
                    <Button :variant="confirming?.status === 'active' ? 'destructive' : 'default'" @click="toggleStatus(confirming!)">
                        {{ confirming?.status === 'active' ? 'Suspend member' : 'Reactivate member' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
