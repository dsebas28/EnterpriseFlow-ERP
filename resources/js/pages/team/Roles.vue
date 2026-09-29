<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Lock, Plus, ShieldCheck, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Role {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_system: boolean;
    is_owner: boolean;
    members_count: number;
    permissions: string[];
}

const props = defineProps<{
    roles: { data: Role[] };
    permissionGroups: Record<string, string[]>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Team', href: '/team/members' },
    { title: 'Roles & permissions', href: '/team/roles' },
];

const page = usePage<SharedData & { errors: Record<string, string> }>();
const ruleError = computed(() => page.props.errors?.rule);
const { can } = usePermissions();
const canManage = computed(() => can('roles.manage'));
const totalPermissions = computed(() => Object.values(props.permissionGroups).flat().length);

const editorOpen = ref(false);
const editing = ref<Role | null>(null);
const form = useForm<{ name: string; description: string; permissions: string[] }>({ name: '', description: '', permissions: [] });

const openEditor = (role: Role | null) => {
    editing.value = role;
    form.name = role?.name ?? '';
    form.description = role?.description ?? '';
    form.permissions = [...(role?.permissions ?? [])];
    form.clearErrors();
    editorOpen.value = true;
};

const readOnly = computed(() => !canManage.value || editing.value?.is_owner === true);

const toggleGroup = (group: string) => {
    const perms = props.permissionGroups[group];
    const allSelected = perms.every((p) => form.permissions.includes(p));
    form.permissions = allSelected ? form.permissions.filter((p) => !perms.includes(p)) : [...new Set([...form.permissions, ...perms])];
};

const save = () => {
    const options = { preserveScroll: true, onSuccess: () => (editorOpen.value = false) };
    if (editing.value) {
        form.put(route('team.roles.update', editing.value.id), options);
    } else {
        form.post(route('team.roles.store'), options);
    }
};

const deleting = ref<Role | null>(null);
const destroy = () =>
    router.delete(route('team.roles.destroy', deleting.value!.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });

const action = (permission: string) => permission.split('.')[1];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Roles & permissions" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <HeadingSmall title="Roles & permissions" description="Roles are sets of permissions. A member's access is the union of their roles." />
                <Button v-if="canManage" @click="openEditor(null)"><Plus class="mr-2 h-4 w-4" /> New role</Button>
            </div>

            <p v-if="page.props.flash?.status" class="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-700 dark:text-emerald-300">
                {{ page.props.flash.status }}
            </p>
            <p v-if="ruleError" class="rounded-md border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-700 dark:text-red-300">
                {{ ruleError }}
            </p>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="role in roles.data" :key="role.id" class="flex flex-col rounded-lg border p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="flex items-center gap-2 font-medium">
                                {{ role.name }}
                                <span v-if="role.is_system" class="rounded bg-secondary px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                    System
                                </span>
                            </h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ role.description ?? 'No description' }}</p>
                        </div>
                        <Lock v-if="role.is_owner" class="h-4 w-4 shrink-0 text-muted-foreground" aria-label="Locked" />
                    </div>

                    <div class="mt-4">
                        <div class="mb-1 flex justify-between text-xs text-muted-foreground">
                            <span>{{ role.permissions.length }} / {{ totalPermissions }} permissions</span>
                            <span>{{ role.members_count }} {{ role.members_count === 1 ? 'member' : 'members' }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-primary" :style="{ width: `${(role.permissions.length / totalPermissions) * 100}%` }" />
                        </div>
                    </div>

                    <div class="mt-4 flex gap-2 pt-2">
                        <Button variant="outline" size="sm" class="flex-1" @click="openEditor(role)">
                            <ShieldCheck class="mr-2 h-4 w-4" /> {{ role.is_owner || !canManage ? 'View' : 'Edit' }}
                        </Button>
                        <Button v-if="canManage && !role.is_system" variant="ghost" size="sm" aria-label="Delete role" @click="deleting = role">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </article>
            </div>
        </div>

        <!-- Editor -->
        <Dialog v-model:open="editorOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form class="space-y-6" @submit.prevent="save">
                    <DialogHeader>
                        <DialogTitle>{{ editing ? editing.name : 'New role' }}</DialogTitle>
                        <DialogDescription v-if="editing?.is_owner">The Owner role always has every permission and cannot be changed.</DialogDescription>
                        <DialogDescription v-else-if="editing?.is_system">System role: its name is fixed, its permissions can be adjusted.</DialogDescription>
                        <DialogDescription v-else>Pick exactly what this role can do.</DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="role-name">Name</Label>
                            <Input id="role-name" v-model="form.name" required :disabled="readOnly || editing?.is_system" />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="role-description">Description</Label>
                            <Input id="role-description" v-model="form.description" :disabled="readOnly" />
                            <InputError :message="form.errors.description" />
                        </div>
                    </div>

                    <fieldset class="space-y-3" :disabled="readOnly">
                        <legend class="mb-2 text-sm font-medium">Permissions</legend>
                        <div v-for="(perms, group) in permissionGroups" :key="group" class="rounded-md border p-3">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-sm font-medium capitalize">{{ group }}</span>
                                <button v-if="!readOnly" type="button" class="text-xs text-muted-foreground hover:text-foreground" @click="toggleGroup(group as string)">
                                    Toggle all
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-x-4 gap-y-2">
                                <label v-for="permission in perms" :key="permission" class="flex items-center gap-2 text-sm">
                                    <input v-model="form.permissions" type="checkbox" :value="permission" class="rounded border-input" />
                                    {{ action(permission) }}
                                </label>
                            </div>
                        </div>
                        <InputError :message="form.errors.permissions" />
                    </fieldset>

                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="editorOpen = false">{{ readOnly ? 'Close' : 'Cancel' }}</Button>
                        <Button v-if="!readOnly" type="submit" :disabled="form.processing">Save role</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete confirmation -->
        <Dialog :open="deleting !== null" @update:open="(open) => !open && (deleting = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete role “{{ deleting?.name }}”?</DialogTitle>
                    <DialogDescription>This cannot be undone. Roles that are still assigned to members cannot be deleted.</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="secondary" @click="deleting = null">Cancel</Button>
                    <Button variant="destructive" @click="destroy">Delete role</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
