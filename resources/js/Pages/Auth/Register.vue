<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useTranslations } from '@/i18n';

const { t } = useTranslations();

const redirectParam = new URL(window.location.href).searchParams.get('redirect') || ''

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    accept_terms: false,
});

const submit = () => {
    form.post(redirectParam ? `${route('register')}?redirect=${encodeURIComponent(redirectParam)}` : route('register'), {
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
                :href="route('social.redirect', { provider: 'google', ...(redirectParam ? { redirect: redirectParam } : {}) })"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300"
            >
                <svg viewBox="0 0 24 24" class="h-5 w-5"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                {{ t('Continue with Google') }}
            </a>

            <a
                :href="route('social.redirect', { provider: 'facebook', ...(redirectParam ? { redirect: redirectParam } : {}) })"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300"
            >
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                {{ t('Continue with Facebook') }}
            </a>

            <a
                :href="route('social.redirect', { provider: 'apple', ...(redirectParam ? { redirect: redirectParam } : {}) })"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300"
            >
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>
                {{ t('Continue with Apple') }}
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
