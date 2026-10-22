<template>
  <GuestLayout>
    <Head title="Become an Affiliate" />

    <div class="min-h-screen bg-gray-50 py-12">
      <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
        <div class="rounded-xl bg-white p-8 shadow-sm">
          <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900">Become an Affiliate</h1>
            <p class="mt-2 text-gray-600">Earn {{ defaultCommissionRate }}% commission on every sale you refer</p>
          </div>

          <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-3">
            <div class="rounded-lg bg-blue-50 p-4 text-center">
              <div class="text-2xl font-bold text-blue-600">{{ defaultCommissionRate }}%</div>
              <div class="mt-1 text-sm text-gray-600">Commission Rate</div>
            </div>
            <div class="rounded-lg bg-green-50 p-4 text-center">
              <div class="text-2xl font-bold text-green-600">${{ minWithdrawal }}</div>
              <div class="mt-1 text-sm text-gray-600">Min. Withdrawal</div>
            </div>
            <div class="rounded-lg bg-purple-50 p-4 text-center">
              <div class="text-2xl font-bold text-purple-600">30 Days</div>
              <div class="mt-1 text-sm text-gray-600">Cookie Lifetime</div>
            </div>
          </div>

          <form @submit.prevent="submit" class="mt-8 space-y-6">
            <div>
              <InputLabel for="name" value="Full Name" />
              <TextInput
                id="name"
                v-model="form.name"
                type="text"
                class="mt-1 block w-full"
                placeholder="Your full name"
                required
              />
              <InputError :message="form.errors.name" class="mt-2" />
            </div>

            <div>
              <InputLabel for="email" value="Email Address" />
              <TextInput
                id="email"
                v-model="form.email"
                type="email"
                class="mt-1 block w-full"
                placeholder="you@example.com"
                required
              />
              <InputError :message="form.errors.email" class="mt-2" />
            </div>

            <div>
              <InputLabel for="password" value="Password" />
              <TextInput
                id="password"
                v-model="form.password"
                type="password"
                class="mt-1 block w-full"
                placeholder="Min. 8 characters"
                required
              />
              <InputError :message="form.errors.password" class="mt-2" />
            </div>

            <div>
              <InputLabel for="password_confirmation" value="Confirm Password" />
              <TextInput
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                class="mt-1 block w-full"
                placeholder="Repeat password"
                required
              />
            </div>

            <div class="flex items-center gap-2">
              <Checkbox id="agree_terms" v-model:checked="form.agree_terms" />
              <label for="agree_terms" class="text-sm text-gray-600">
                I agree to the
                <Link href="/legal/terms-of-service" class="text-blue-600 hover:underline">Terms of Service</Link>
                and
                <Link href="/legal/privacy-policy" class="text-blue-600 hover:underline">Privacy Policy</Link>
              </label>
            </div>
            <InputError :message="form.errors.agree_terms" class="mt-2" />

            <PrimaryButton :disabled="form.processing" class="w-full justify-center">
              {{ form.processing ? 'Creating Account...' : 'Join Affiliate Program' }}
            </PrimaryButton>
          </form>

          <p class="mt-6 text-center text-sm text-gray-500">
            Already have an account?
            <a :href="route('filament.affiliate.auth.login')" class="font-medium text-blue-600 hover:underline">Sign in</a>
          </p>
        </div>
      </div>
    </div>
  </GuestLayout>
</template>

<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import Checkbox from '@/Components/Checkbox.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  minWithdrawal: { type: Number, default: 50 },
  defaultCommissionRate: { type: Number, default: 10 },
})

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  agree_terms: false,
})

function submit() {
  form.post(route('affiliate.signup.store'), {
    onError: () => form.reset('password', 'password_confirmation'),
  })
}
</script>
