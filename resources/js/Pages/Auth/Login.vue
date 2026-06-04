<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import Checkbox from '@/Components/Checkbox.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const page = usePage()
const { t } = useTranslations()

const redirectParam = new URL(window.location.href).searchParams.get('redirect') || ''

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const submit = () => {
  form.post(redirectParam ? `${route('login')}?redirect=${encodeURIComponent(redirectParam)}` : route('login'), {
    onFinish: () => form.reset('password'),
  })
}
</script>

<template>
  <GuestLayout>
    <Head :title="t('Sign in')" />

    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900">{{ t('Sign in') }}</h1>
        <p class="text-sm text-slate-500">{{ t('Welcome back. Continue where you left off.') }}</p>
      </div>

      <div v-if="page.props.flash?.status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
        {{ page.props.flash.status }}
      </div>

      <form class="space-y-4" @submit.prevent="submit">
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
            autocomplete="current-password"
          />
          <InputError class="mt-2" :message="form.errors.password" />
        </div>

        <div class="flex items-center justify-between text-sm">
          <label class="flex items-center gap-2 text-slate-600">
            <Checkbox v-model:checked="form.remember" name="remember" />
            {{ t('Remember me') }}
          </label>
          <Link :href="route('password.request')" class="text-slate-600 hover:text-slate-900">
            {{ t('Forgot password?') }}
          </Link>
        </div>

        <PrimaryButton class="w-full" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
          {{ form.processing ? t('Signing in...') : t('Sign in') }}
        </PrimaryButton>
      </form>

      <div class="flex items-center gap-3 text-xs text-slate-400">
        <span class="h-px flex-1 bg-slate-200" />
        {{ t('Or continue with') }}
        <span class="h-px flex-1 bg-slate-200" />
      </div>

      <div class="grid gap-2">
        <!-- OAuth must be a full-page redirect (not an Inertia/XHR visit), otherwise the browser blocks it (CORS). -->
        <a
          :href="route('social.redirect', { provider: 'google', ...(redirectParam ? { redirect: redirectParam } : {}) })"
          class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300"
        >
          <svg viewBox="0 0 24 24" class="h-5 w-5"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
          {{ t('Continue with Google') }}
        </a>

        <a
          :href="route('social.redirect', { provider: 'facebook', ...(redirectParam ? { redirect: redirectParam } : {}) })"
          class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300"
        >
          <svg viewBox="0 0 24 24" class="h-5 w-5" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
          {{ t('Continue with Facebook') }}
        </a>

        <a
          :href="route('social.redirect', { provider: 'apple', ...(redirectParam ? { redirect: redirectParam } : {}) })"
          class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300"
        >
          <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>
          {{ t('Continue with Apple') }}
        </a>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600">
        <Link :href="route('register')" class="hover:text-slate-900">{{ t('Create an account') }}</Link>
        <Link :href="route('claim-account.create')" class="hover:text-slate-900">{{ t('Claim existing order') }}</Link>
      </div>
    </div>
  </GuestLayout>
</template>
