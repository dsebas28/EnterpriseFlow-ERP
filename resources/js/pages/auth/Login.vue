<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { BadgeDollarSign, Briefcase, Calculator, Check, Crown, Eye, LoaderCircle, ShieldCheck, Warehouse, type LucideIcon } from 'lucide-vue-next';
import { ref } from 'vue';

interface DemoAccount {
    key: string;
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

const roleIcons: Record<string, LucideIcon> = {
    owner: Crown,
    administrator: ShieldCheck,
    manager: Briefcase,
    accountant: Calculator,
    sales: BadgeDollarSign,
    warehouse: Warehouse,
    employee: Eye,
};
</script>

<template>
    <AuthBase title="Welcome back" description="Sign in to your EnterpriseFlow workspace.">
        <Head title="Sign in" />

        <div v-if="status" class="mb-6 rounded-lg bg-brand-positive/10 px-4 py-3 text-sm font-medium text-brand-positive">
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
                    class="h-11"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm">Forgot your password?</TextLink>
                </div>
                <Input id="password" type="password" required autocomplete="current-password" v-model="form.password" class="h-11" />
                <InputError :message="form.errors.password" />
            </div>

            <Label for="remember" class="flex w-fit items-center gap-3 font-normal">
                <Checkbox id="remember" v-model:checked="form.remember" />
                Keep me signed in
            </Label>

            <Button ref="submitButton" type="submit" class="mt-1 h-11 w-full text-base" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                Sign in
            </Button>

            <p class="text-center text-sm text-brand-muted">
                New to EnterpriseFlow?
                <TextLink :href="route('register')">Create an account</TextLink>
            </p>
        </form>

        <template v-if="demo" #after>
            <section class="mt-8" aria-labelledby="demo-heading">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 id="demo-heading" class="font-medium">Try a demo account</h2>
                    <p class="text-xs text-brand-muted">
                        Password <span class="font-semibold text-brand-ink">{{ demo.password }}</span>
                    </p>
                </div>
                <p class="mt-1 text-sm text-brand-muted">Each role sees only what it is allowed to. Choosing one fills in the form.</p>

                <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                    <li v-for="(account, index) in demo.accounts" :key="account.email">
                        <button
                            type="button"
                            class="group flex h-full w-full items-start gap-3 rounded-xl border bg-brand-surface p-3 text-left transition-colors hover:border-brand-primary/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary"
                            :class="form.email === account.email ? 'border-brand-primary ring-1 ring-brand-primary' : 'border-brand-line'"
                            :aria-pressed="form.email === account.email"
                            @click="useAccount(account, demo.password)"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                :class="index === 0 ? 'bg-[#f59e0b] text-[#1e1b4b]' : 'bg-brand-primary/10 text-brand-primary'"
                            >
                                <Check v-if="form.email === account.email" class="size-4" />
                                <component :is="roleIcons[account.key] ?? Eye" v-else class="size-4" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium">{{ account.role }}</span>
                                <span class="block text-xs leading-snug text-brand-muted">{{ account.summary }}</span>
                            </span>
                        </button>
                    </li>
                </ul>
            </section>
        </template>
    </AuthBase>
</template>
