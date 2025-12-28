<template>
  <GuestLayout>
    <Head title="Reset Password" />

    <div class="mx-auto max-w-md space-y-6">
      <div class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ t('Reset password') }}</h1>
        <p class="text-sm text-slate-600">
          {{ t('Enter your new password below.') }}
        </p>
      </div>

      <form @submit.prevent="submit" class="card p-6 space-y-4">
        <div class="space-y-1">
          <label for="email" class="text-sm font-medium text-slate-700">{{ t('Email') }}</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            class="input-base w-full"
            :class="{ 'border-red-500': form.errors.email }"
            required
            autofocus
            autocomplete="username"
          />
          <p v-if="form.errors.email" class="text-xs text-red-600">{{ form.errors.email }}</p>
        </div>

        <div class="space-y-1">
          <label for="password" class="text-sm font-medium text-slate-700">{{ t('Password') }}</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            class="input-base w-full"
            :class="{ 'border-red-500': form.errors.password }"
            required
            autocomplete="new-password"
          />
          <p v-if="form.errors.password" class="text-xs text-red-600">{{ form.errors.password }}</p>
        </div>

        <div class="space-y-1">
          <label for="password_confirmation" class="text-sm font-medium text-slate-700">{{ t('Confirm password') }}</label>
          <input
            id="password_confirmation"
            v-model="form.password_confirmation"
            type="password"
            class="input-base w-full"
            :class="{ 'border-red-500': form.errors.password_confirmation }"
            required
            autocomplete="new-password"
          />
          <p v-if="form.errors.password_confirmation" class="text-xs text-red-600">{{ form.errors.password_confirmation }}</p>
        </div>

        <button
          type="submit"
          class="btn-primary w-full"
          :disabled="form.processing"
        >
          {{ form.processing ? t('Resetting...') : t('Reset password') }}
        </button>
      </form>
    </div>
  </GuestLayout>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { useTranslations } from '@/i18n'

const { t } = useTranslations()

const props = defineProps({
  email: { type: String, required: true },
  token: { type: String, required: true },
})

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.post(route('password.store'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>
