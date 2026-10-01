<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';

interface DemoAccount {
    role: string;
    email: string;
    summary: string;
}

defineProps<{
    status?: string;
    canResetPassword: boolean;
    /** Only sent when DEMO_MODE is on. */
    demo: { password: string; accounts: DemoAccount[] } | null;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submitButton = ref<InstanceType<typeof Button> | null>(null);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const useAccount = (account: DemoAccount, password: string) => {
    form.email = account.email;
    form.password = password;
    form.clearErrors();
    (submitButton.value?.$el as HTMLButtonElement | undefined)?.focus();
};
</script>

<template>
    <AuthBase title="Sign in" description="Use the email and password of your EnterpriseFlow account.">
        <Head title="Sign in" />

        <div v-if="status" class="mb-6 rounded-md bg-ledger-green/10 px-4 py-3 text-sm font-medium text-ledger-green">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="grid gap-5">
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    v-model="form.email"
                    placeholder="you@company.com"
                    class="h-11 bg-ledger-paper"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm">Forgot your password?</TextLink>
                </div>
                <Input id="password" type="password" required autocomplete="current-password" v-model="form.password" class="h-11 bg-ledger-paper" />
                <InputError :message="form.errors.password" />
            </div>

            <Label for="remember" class="flex w-fit items-center gap-3 font-normal">
                <Checkbox id="remember" v-model:checked="form.remember" />
                Keep me signed in
            </Label>

            <Button ref="submitButton" type="submit" class="mt-2 h-11 w-full text-base" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                Sign in
            </Button>

            <p class="text-center text-sm text-ledger-muted">
                New to EnterpriseFlow?
                <TextLink :href="route('register')">Create an account</TextLink>
            </p>
        </form>

        <section v-if="demo" class="mt-10 border-t border-ledger-rule pt-6" aria-labelledby="demo-heading">
            <h2 id="demo-heading" class="font-medium">Try a demo account</h2>
            <p class="mt-1 text-sm text-ledger-muted">
                Each one sees what its role allows. Choosing one fills in the form; the password is
                <span class="font-medium text-ledger-ink">{{ demo.password }}</span
                >.
            </p>

            <ul class="mt-4 grid gap-1">
                <li v-for="account in demo.accounts" :key="account.email">
                    <button
                        type="button"
                        class="flex w-full items-baseline justify-between gap-4 rounded-md px-3 py-2 text-left hover:bg-ledger-paper focus-visible:outline focus-visible:outline-2 focus-visible:outline-ledger-green"
                        :class="form.email === account.email ? 'bg-ledger-paper ring-1 ring-ledger-rule' : ''"
                        :aria-pressed="form.email === account.email"
                        @click="useAccount(account, demo.password)"
                    >
                        <span class="min-w-0">
                            <span class="block text-sm font-medium">{{ account.role }}</span>
                            <span class="block text-xs text-ledger-muted">{{ account.summary }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-ledger-muted">{{ account.email }}</span>
                    </button>
                </li>
            </ul>
        </section>
    </AuthBase>
</template>
