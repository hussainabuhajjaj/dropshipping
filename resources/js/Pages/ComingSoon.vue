<template>
  <div class="coming-hero">
    <div class="coming-orb orb-a"></div>
    <div class="coming-orb orb-b"></div>
    <div class="coming-grid"></div>

    <div class="coming-shell">
      <header class="coming-intro">
        <p class="coming-kicker">{{ t('Coming soon') }}</p>
        <h1 class="coming-title">{{ resolvedTitle }}</h1>
        <p class="coming-copy">{{ resolvedMessage }}</p>
      </header>

      <div class="coming-panel">
        <div v-if="resolvedImage" class="coming-image">
          <img :src="resolvedImage" alt="Coming soon" />
        </div>
        <div class="coming-body">
          <div class="coming-badge">{{ t('Get notified') }}</div>
          <h2>{{ t('Be first in line') }}</h2>
          <p>{{ t('Drop alerts, inventory signals, and delivery updates sent to your inbox.') }}</p>
          <form class="coming-form" @submit.prevent="submit">
            <input
              v-model="email"
              type="email"
              required
              :placeholder="t('Email address')"
              class="coming-input"
            />
            <button type="submit" class="coming-submit">{{ ctaLabel }}</button>
          </form>
          <p v-if="notice" class="coming-notice">{{ notice }}</p>
          <Link v-if="ctaUrl" :href="ctaUrl" class="coming-link">
            {{ t('Continue to site') }}
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const props = defineProps({
  title: { type: String, default: '' },
  message: { type: String, default: '' },
  image: { type: String, default: '' },
  cta_label: { type: String, default: '' },
  cta_url: { type: String, default: '' },
})

const { t } = useTranslations()
const email = ref('')
const notice = ref('')

const resolvedTitle = computed(() => props.title || t('We are opening soon'))
const resolvedMessage = computed(() => props.message || t('We are preparing the best drops and delivery experience.'))
const resolveImage = (path) => {
  if (!path) return null
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
    return path
  }
  return `/storage/${path}`
}

const resolvedImage = computed(() => resolveImage(props.image || null))
const ctaLabel = computed(() => props.cta_label || t('Notify me'))
const ctaUrl = computed(() => props.cta_url || null)

const submit = async () => {
  if (!email.value) return
  try {
    const response = await fetch('/newsletter/subscribe', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({ email: email.value, source: 'coming_soon' }),
    })
    if (response.ok) {
      notice.value = t('Thanks! We will be in touch.')
      email.value = ''
    } else {
      notice.value = t('Please check your email and try again.')
    }
  } catch {
    notice.value = t('Unable to subscribe right now.')
  }
}
</script>

<style scoped>
.coming-hero {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 64px 24px;
  background: radial-gradient(circle at top, rgba(249, 214, 125, 0.25), transparent 55%),
    radial-gradient(circle at 20% 20%, rgba(55, 201, 167, 0.25), transparent 50%),
    linear-gradient(135deg, #0f172a 0%, #0b1020 45%, #111827 100%);
  color: #f8fafc;
  position: relative;
  overflow: hidden;
  font-family: "Plus Jakarta Sans", "Segoe UI", "Helvetica Neue", Arial, sans-serif;
}

.coming-grid {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
  background-size: 32px 32px;
  opacity: 0.25;
  pointer-events: none;
}

.coming-orb {
  position: absolute;
  width: 320px;
  height: 320px;
  border-radius: 999px;
  filter: blur(0px);
  opacity: 0.35;
}

.orb-a {
  right: -120px;
  top: -80px;
  background: radial-gradient(circle, rgba(245, 158, 11, 0.6), transparent 70%);
}

.orb-b {
  left: -160px;
  bottom: -120px;
  background: radial-gradient(circle, rgba(45, 212, 191, 0.5), transparent 70%);
}

.coming-shell {
  width: min(1100px, 100%);
  display: grid;
  gap: 32px;
  position: relative;
  z-index: 2;
}

.coming-intro {
  max-width: 640px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.coming-kicker {
  text-transform: uppercase;
  letter-spacing: 0.45em;
  font-size: 11px;
  color: rgba(251, 191, 36, 0.9);
  font-weight: 700;
}

.coming-title {
  font-size: clamp(2rem, 3vw + 1rem, 3.4rem);
  line-height: 1.1;
  font-weight: 700;
  color: #f8fafc;
}

.coming-copy {
  color: rgba(226, 232, 240, 0.85);
  font-size: 1rem;
  line-height: 1.6;
}

.coming-panel {
  display: grid;
  gap: 0;
  border-radius: 24px;
  overflow: hidden;
  background: rgba(15, 23, 42, 0.75);
  border: 1px solid rgba(148, 163, 184, 0.25);
  box-shadow: 0 32px 60px rgba(15, 23, 42, 0.45);
}

.coming-image {
  aspect-ratio: 16 / 9;
  overflow: hidden;
}

.coming-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.coming-body {
  padding: 28px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.coming-badge {
  align-self: flex-start;
  background: rgba(251, 191, 36, 0.2);
  color: #fbbf24;
  border: 1px solid rgba(251, 191, 36, 0.4);
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  font-weight: 700;
}

.coming-body h2 {
  font-size: 1.35rem;
  font-weight: 600;
}

.coming-body p {
  color: rgba(226, 232, 240, 0.75);
  font-size: 0.95rem;
}

.coming-form {
  display: grid;
  gap: 12px;
  margin-top: 8px;
}

.coming-input {
  background: rgba(15, 23, 42, 0.8);
  border: 1px solid rgba(148, 163, 184, 0.35);
  padding: 12px 14px;
  border-radius: 12px;
  color: #f8fafc;
  font-size: 0.95rem;
}

.coming-input::placeholder {
  color: rgba(148, 163, 184, 0.7);
}

.coming-submit {
  background: linear-gradient(135deg, #fbbf24, #f59e0b);
  color: #0f172a;
  padding: 12px 16px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.95rem;
}

.coming-notice {
  font-size: 0.8rem;
  color: rgba(251, 191, 36, 0.9);
}

.coming-link {
  font-size: 0.85rem;
  font-weight: 600;
  color: rgba(125, 211, 252, 0.9);
}

@media (min-width: 900px) {
  .coming-panel {
    grid-template-columns: 1.1fr 1fr;
  }

  .coming-image {
    aspect-ratio: auto;
  }
}
</style>
