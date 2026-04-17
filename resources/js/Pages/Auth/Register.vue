<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useTranslations } from '@/i18n';

const { t } = useTranslations();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    accept_terms: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('Register')" />

        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">{{ t('Create your account') }}</h1>
                <p class="text-sm text-slate-500">{{ t('Sign up to track orders, save favorites, and checkout faster.') }}</p>
            </div>

            <!-- OAuth must be a full-page redirect (not an Inertia/XHR visit), otherwise the browser blocks it (CORS). -->
            <a
                :href="route('social.redirect', { provider: 'google' })"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300"
            >
                {{ t('Continue with Google') }}
            </a>

            <div class="flex items-center gap-3 text-xs text-slate-400">
                <span class="h-px flex-1 bg-slate-200" />
                {{ t('Or register with email') }}
                <span class="h-px flex-1 bg-slate-200" />
            </div>

            <form class="space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel for="name" :value="t('Name')" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" :value="t('Email')" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" :value="t('Password')" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    :value="t('Confirm Password')"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div>
                <label class="flex items-start gap-3 text-sm text-slate-600">
                    <input
                        v-model="form.accept_terms"
                        type="checkbox"
                        class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                        required
                    />
                    <span>
                        {{ t('I agree to the') }}
                        <Link :href="route('legal.terms')" class="font-semibold text-slate-900 underline hover:text-slate-700">
                            {{ t('Terms and Conditions') }}
                        </Link>
                    </span>
                </label>

                <InputError class="mt-2" :message="form.errors.accept_terms" />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <Link
                    :href="route('login')"
                    class="text-sm text-slate-600 hover:text-slate-900"
                >
                    {{ t('Already registered?') }}
                </Link>

                <PrimaryButton
                    class="w-full sm:w-auto"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ t('Register') }}
                </PrimaryButton>
            </div>
            </form>
        </div>
    </GuestLayout>
</template>
