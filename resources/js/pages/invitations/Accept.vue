<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    token: string;
    invitation: { email: string; company: string; role: string; expires_at: string };
    hasAccount: boolean;
    authenticatedEmail: string | null;
}>();

const page = usePage<{ errors: Record<string, string> }>();

const emailMatches = computed(() => props.authenticatedEmail?.toLowerCase() === props.invitation.email.toLowerCase());
const needsRegistration = computed(() => !props.authenticatedEmail && !props.hasAccount);

const form = useForm({ name: '', password: '', password_confirmation: '' });

const accept = () =>
    form.post(route('invitations.accept', props.token), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
</script>

<template>
    <AuthBase :title="`Join ${invitation.company}`" :description="`You have been invited as ${invitation.role}.`">
        <Head title="Accept invitation" />

        <p v-if="page.props.errors?.rule" class="rounded-md bg-red-500/10 px-3 py-2 text-center text-sm text-red-600">{{ page.props.errors.rule }}</p>

        <!-- Signed in with the invited address -->
        <form v-if="authenticatedEmail && emailMatches" class="flex flex-col gap-4" @submit.prevent="accept">
            <p class="text-center text-sm text-muted-foreground">
                Signed in as <strong>{{ authenticatedEmail }}</strong>.
            </p>
            <Button type="submit" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                Accept invitation
            </Button>
        </form>

        <!-- Signed in with a different address -->
        <div v-else-if="authenticatedEmail" class="space-y-4 text-center text-sm">
            <p>
                This invitation was sent to <strong>{{ invitation.email }}</strong>, but you are signed in as
                <strong>{{ authenticatedEmail }}</strong>.
            </p>
            <TextLink :href="route('logout')" method="post" as="button">Log out and try again</TextLink>
        </div>

        <!-- Existing account, not signed in -->
        <div v-else-if="!needsRegistration" class="space-y-4 text-center text-sm">
            <p>
                An account for <strong>{{ invitation.email }}</strong> already exists. Log in to accept the invitation.
            </p>
            <form @submit.prevent="accept">
                <Button type="submit" class="w-full">Log in to continue</Button>
            </form>
        </div>

        <!-- New user -->
        <form v-else class="flex flex-col gap-6" @submit.prevent="accept">
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input id="email" :model-value="invitation.email" disabled />
            </div>
            <div class="grid gap-2">
                <Label for="name">Your name</Label>
                <Input id="name" v-model="form.name" required autofocus autocomplete="name" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <Input id="password" v-model="form.password" type="password" required autocomplete="new-password" />
                <InputError :message="form.errors.password" />
            </div>
            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <Input id="password_confirmation" v-model="form.password_confirmation" type="password" required autocomplete="new-password" />
            </div>
            <Button type="submit" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                Create account and join
            </Button>
        </form>
    </AuthBase>
</template>
