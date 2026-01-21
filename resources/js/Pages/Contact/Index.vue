<template>
  <StorefrontLayout>
    <div class="mx-auto max-w-4xl px-4 py-8">
      <!-- Header -->
      <header class="mb-12 text-center">
        <h1 class="text-4xl font-bold text-slate-900 mb-4">
          {{ pageTitle }}
        </h1>
        <p class="text-lg text-slate-600">
          {{ pageDescription }}
        </p>
      </header>

      <!-- Contact Info Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
        <!-- Email -->
        <div class="rounded-lg border border-slate-200 p-6 hover:border-orange-300 transition-colors">
          <div class="flex items-start">
            <div class="flex-shrink-0">
              <svg class="h-6 w-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-semibold text-slate-900 mb-1">{{ t('Email') }}</h3>
              <a
                v-if="supportEmail"
                :href="`mailto:${supportEmail}`"
                class="text-orange-600 hover:text-orange-700"
              >
                {{ supportEmail }}
              </a>
              <p v-else class="text-slate-500">{{ t('Coming soon') }}</p>
            </div>
          </div>
        </div>

        <!-- WhatsApp -->
        <div class="rounded-lg border border-slate-200 p-6 hover:border-orange-300 transition-colors">
          <div class="flex items-start">
            <div class="flex-shrink-0">
              <svg class="h-6 w-6 text-orange-600" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
              </svg>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-semibold text-slate-900 mb-1">{{ t('WhatsApp') }}</h3>
              <a
                v-if="supportWhatsapp"
                :href="`https://wa.me/${supportWhatsapp.replace(/[^0-9]/g, '')}`"
                target="_blank"
                rel="noopener noreferrer"
                class="text-orange-600 hover:text-orange-700"
              >
                {{ supportWhatsapp }}
              </a>
              <p v-else class="text-slate-500">{{ t('Coming soon') }}</p>
            </div>
          </div>
        </div>

        <!-- Phone -->
        <div class="rounded-lg border border-slate-200 p-6 hover:border-orange-300 transition-colors">
          <div class="flex items-start">
            <div class="flex-shrink-0">
              <svg class="h-6 w-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-semibold text-slate-900 mb-1">{{ t('Phone') }}</h3>
              <a
                v-if="supportPhone"
                :href="`tel:${supportPhone}`"
                class="text-orange-600 hover:text-orange-700"
              >
                {{ supportPhone }}
              </a>
              <p v-else class="text-slate-500">{{ t('Coming soon') }}</p>
            </div>
          </div>
        </div>

        <!-- Support Hours -->
        <div class="rounded-lg border border-slate-200 p-6 hover:border-orange-300 transition-colors">
          <div class="flex items-start">
            <div class="flex-shrink-0">
              <svg class="h-6 w-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-semibold text-slate-900 mb-1">{{ t('Support Hours') }}</h3>
              <p v-if="supportHours" class="text-slate-600">{{ supportHours }}</p>
              <p v-else class="text-slate-500">{{ t('24/7') }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Contact Form Section -->
      <div class="rounded-lg bg-slate-50 p-8">
        <h2 class="text-2xl font-semibold text-slate-900 mb-4">
          {{ t('Send Us a Message') }}
        </h2>
        <p class="text-slate-600 mb-6">
          {{ t('Fill out the form below and we\'ll get back to you as soon as possible.') }}
        </p>

        <div
          v-if="contactNotice"
          class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
        >
          {{ contactNotice }}
        </div>
        <div
          v-if="form.errors.contact"
          class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
        >
          {{ form.errors.contact }}
        </div>

        <form class="space-y-4" @submit.prevent="submit">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="name" class="block text-sm font-medium text-slate-700 mb-1">
                {{ t('Name') }}
              </label>
              <input
                type="text"
                id="name"
                v-model="form.name"
                class="w-full rounded-md border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                :placeholder="t('Your name')"
              />
              <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
            </div>
            <div>
              <label for="email" class="block text-sm font-medium text-slate-700 mb-1">
                {{ t('Email') }}
              </label>
              <input
                type="email"
                id="email"
                v-model="form.email"
                class="w-full rounded-md border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                :placeholder="t('your@email.com')"
              />
              <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
            </div>
          </div>

          <div>
            <label for="subject" class="block text-sm font-medium text-slate-700 mb-1">
              {{ t('Subject') }}
            </label>
            <input
              type="text"
              id="subject"
              v-model="form.subject"
              class="w-full rounded-md border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
              :placeholder="t('What is this regarding?')"
            />
            <p v-if="form.errors.subject" class="mt-1 text-xs text-rose-600">{{ form.errors.subject }}</p>
          </div>

          <div>
            <label for="message" class="block text-sm font-medium text-slate-700 mb-1">
              {{ t('Message') }}
            </label>
            <textarea
              id="message"
              rows="6"
              v-model="form.message"
              class="w-full rounded-md border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
              :placeholder="t('Tell us more...')"
            ></textarea>
            <p v-if="form.errors.message" class="mt-1 text-xs text-rose-600">{{ form.errors.message }}</p>
          </div>

          <button
            type="submit"
            :disabled="form.processing"
            class="w-full md:w-auto inline-flex items-center justify-center rounded-md bg-orange-600 px-6 py-3 text-sm font-semibold text-white hover:bg-orange-700 transition-colors disabled:cursor-not-allowed disabled:opacity-70"
          >
            {{ form.processing ? t('Sending...') : t('Send Message') }}
          </button>
        </form>
      </div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useForm, usePage } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

const { t } = useI18n();

const props = defineProps({
  supportEmail: {
    type: String,
    default: null,
  },
  supportWhatsapp: {
    type: String,
    default: null,
  },
  supportPhone: {
    type: String,
    default: null,
  },
  supportHours: {
    type: String,
    default: null,
  },
  pageTitle: {
    type: String,
    default: 'Contact Us',
  },
  pageDescription: {
    type: String,
    default: 'Get in touch with our customer support team.',
  },
});

const page = usePage();
const authUser = page?.props?.auth?.user ?? null;
const initialName = authUser
  ? [authUser.first_name, authUser.last_name].filter(Boolean).join(' ') || authUser.name || ''
  : '';

const form = useForm({
  name: initialName,
  email: authUser?.email ?? '',
  subject: '',
  message: '',
});

const contactNotice = computed(() => page?.props?.flash?.contact_notice ?? null);

const submit = () => {
  form.post('/contact', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('subject', 'message');
    },
  });
};
</script>
