<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useTranslations } from '@/i18n';

const { t } = useTranslations();

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('claim-account.store'));
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('Claim account')" />

        <div class="mb-4 text-sm text-gray-600">
            {{ t('Enter the email you used at checkout. We’ll send you a link to set your password.') }}
        </div>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" :value="t('Email')" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4 flex items-center justify-between">
                <Link
                    :href="route('login')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    {{ t('Back to login') }}
                </Link>

                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ t('Send link') }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
