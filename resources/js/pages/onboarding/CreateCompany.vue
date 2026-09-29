<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{
    defaults: { currency: string; timezone: string };
}>();

const form = useForm({
    name: '',
    tax_id: '',
    country: '',
    currency: props.defaults.currency,
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || props.defaults.timezone,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        country: data.country.toUpperCase(),
        currency: data.currency.toUpperCase(),
    })).post(route('onboarding.company.store'));
};
</script>

<template>
    <AuthBase title="Set up your company" description="Every record in EnterpriseFlow belongs to a company. You can create or join more later.">
        <Head title="Create company" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Company name</Label>
                    <Input id="name" v-model="form.name" required autofocus placeholder="Acme Distribution" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="tax_id">Tax ID <span class="text-muted-foreground">(optional)</span></Label>
                    <Input id="tax_id" v-model="form.tax_id" placeholder="900123456-7" />
                    <InputError :message="form.errors.tax_id" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="country">Country</Label>
                        <Input id="country" v-model="form.country" required maxlength="2" placeholder="CO" class="uppercase" />
                        <InputError :message="form.errors.country" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="currency">Currency</Label>
                        <Input id="currency" v-model="form.currency" required maxlength="3" placeholder="COP" class="uppercase" />
                        <InputError :message="form.errors.currency" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="timezone">Time zone</Label>
                    <Input id="timezone" v-model="form.timezone" required placeholder="America/Bogota" />
                    <InputError :message="form.errors.timezone" />
                </div>

                <Button type="submit" class="mt-2 w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Create company
                </Button>
            </div>
        </form>
    </AuthBase>
</template>
