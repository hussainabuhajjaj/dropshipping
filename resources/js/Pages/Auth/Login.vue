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

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const submit = () => {
  form.post(route('login'), {
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
          :href="route('social.redirect', { provider: 'google' })"
          class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300"
        >
          Google
        </a>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600">
        <Link :href="route('register')" class="hover:text-slate-900">{{ t('Create an account') }}</Link>
        <Link :href="route('claim-account.create')" class="hover:text-slate-900">{{ t('Claim existing order') }}</Link>
      </div>
    </div>
  </GuestLayout>
</template>
