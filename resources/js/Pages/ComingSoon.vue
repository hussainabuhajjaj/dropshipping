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
          <img :src="resolvedImage" :alt="t('Coming soon')" />
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
              :disabled="submitting"
              class="coming-input"
            />
            <button type="submit" class="coming-submit" :disabled="submitting">{{ submitting ? t('Submitting...') : ctaLabel }}</button>
          </form>
          <p v-if="notice" class="coming-notice">{{ notice }}</p>
          <Link v-if="ctaUrl" :href="ctaUrl" class="coming-link">
            {{ t('Continue to site') }}
          </Link>
          
          <!-- Social Media Links -->
          <div v-if="socialLinks.length" class="coming-social">
            <p class="coming-social-title">{{ t('Follow us') }}</p>
            <div class="coming-social-links">
              <a
                v-for="link in socialLinks"
                :key="link.href"
                :href="link.href"
                :aria-label="link.label"
                target="_blank"
                rel="noopener noreferrer"
                class="coming-social-link"
              >
                <svg v-if="link.icon === 'facebook'" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                <svg v-else-if="link.icon === 'twitter'" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M23.953 4.57a10 10 0 01-2.825.748 4.958 4.958 0 00-8.343 2.488A4.958 4.958 0 003.031 9.75a4.958 4.958 0 002.632-2.917 4.958 4.958 0 01-2.212-.085 4.958 4.958 0 004.631 3.414 9.917 9.917 0 01-6.107 2.107 4.958 4.958 0 003.414-4.631 9.917 9.917 0 01-2.107 6.107 4.958 4.958 0 004.631-3.414 9.917 9.917 0 016.107-2.107 4.958 4.958 0 00-3.414 4.631z"/>
                </svg>
                <svg v-else-if="link.icon === 'instagram'" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.85 0 3.204-.012 3.584-.069 4.85-.148 3.252-1.691 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.85-.069-3.252-.148-4.771-1.691-4.919-4.919-.058-1.265-.07-1.644-.07-4.85 0-3.204.012-3.583.069-4.849.148-3.252 1.691-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zM5.838 12a6.162 6.162 0 1112.324 0 6.162 6.162 0 01-12.324 0zM12 16a4 4 0 110-8 4 4 0 010 8zm4.965-10.405a1.44 1.44 0 112.881.001 1.44 1.44 0 01-2.881-.001z"/>
                </svg>
                <svg v-else-if="link.icon === 'youtube'" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-11.376.405a3.016 3.016 0 00-2.122 2.136C3.505 6.186 1.545 8.827 1.545 12c0 3.173 1.96 5.814 5.957 6.414a3.016 3.016 0 002.122 2.136c.878.347 3.87.405 11.376.405 7.505 0 10.498-.058 11.376-.405 1.498-1.082 2.136-2.122 2.136-3.173 0-5.814-1.96-8.827-5.957-6.414z"/>
                </svg>
                <svg v-else-if="link.icon === 'email'" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                </svg>
                <svg v-else-if="link.icon === 'whatsapp'" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.149-.67.149-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414-.074-.123-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.885-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
                <span v-else class="text-slate-600">{{ link.label }}</span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
  title: { type: String, default: '' },
  message: { type: String, default: '' },
  image: { type: String, default: '' },
  cta_label: { type: String, default: '' },
  cta_url: { type: String, default: '' },
})

const { t } = useTranslations()
const page = usePage()
const email = ref('')
const notice = ref('')
const submitting = ref(false)

// Get social links from storefront settings
const socialLinks = computed(() => page.props.storefront?.social_links ?? [])

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
  const normalizedEmail = email.value.trim().toLowerCase()

  if (!normalizedEmail || submitting.value) return

  submitting.value = true
  notice.value = ''

  try {
    const response = await fetch('/newsletter/subscribe', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({ email: normalizedEmail, source: 'coming_soon' }),
    })
    if (response.ok) {
      notice.value = t('Thanks! We will be in touch.')
      email.value = ''
    } else {
      notice.value = t('Please check your email and try again.')
    }
  } catch {
    notice.value = t('Unable to subscribe right now.')
  } finally {
    submitting.value = false
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

.coming-social {
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 1px solid rgba(148, 163, 184, 0.2);
}

.coming-social-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: #f8fafc;
  margin-bottom: 1rem;
  text-align: center;
}

.coming-social-links {
  display: flex;
  justify-content: center;
  gap: 1rem;
}

.coming-social-link {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: rgba(148, 163, 184, 0.1);
  border-radius: 50%;
  color: rgba(148, 163, 184, 0.8);
  transition: all 0.2s ease;
}

.coming-social-link:hover {
  background: rgba(148, 163, 184, 0.2);
  color: #f8fafc;
  transform: translateY(-2px);
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
