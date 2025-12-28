<template>
  <GuestLayout>
    <Head title="Forgot Password" />

    <div class="mx-auto max-w-md space-y-6">
      <div class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ t('Forgot password?') }}</h1>
        <p class="text-sm text-slate-600">
          {{ t('No problem. Enter your email and we will send you a password reset link.') }}
        </p>
      </div>

      <div v-if="status" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">
        {{ status }}
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

        <div class="flex items-center justify-between gap-4">
          <Link :href="route('login')" class="text-sm text-slate-600 hover:text-slate-900">
            {{ t('Back to login') }}
          </Link>
          <button
            type="submit"
            class="btn-primary"
            :disabled="form.processing"
          >
            {{ form.processing ? t('Sending...') : t('Email reset link') }}
          </button>
        </div>
      </form>
    </div>
  </GuestLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { useTranslations } from '@/i18n'

const { t } = useTranslations()

defineProps({
  status: { type: String, default: null },
})

const form = useForm({
  email: '',
})

const submit = () => {
  form.post(route('password.email'), {
    onFinish: () => form.reset('email'),
  })
}
</script>
