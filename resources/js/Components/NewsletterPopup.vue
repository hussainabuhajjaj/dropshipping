<template>
  <Modal :show="visible" maxWidth="2xl" @close="dismiss">
    <div class="newsletter-shell">
      <div class="newsletter-media">
        <img
          v-if="resolvedImage"
          :src="resolvedImage"
          alt="Newsletter"
          class="newsletter-img"
        />
        <div v-else class="newsletter-fallback">
          <div class="fallback-chip">{{ t('New drops') }}</div>
          <h4>{{ t('Shop smarter') }}</h4>
          <p>{{ t('Early access to promos, restocks, and shipping updates.') }}</p>
        </div>
      </div>

      <div class="newsletter-body">
        <div class="newsletter-top">
          <span class="newsletter-kicker">{{ t('Storefront insider') }}</span>
          <button type="button" class="newsletter-close" @click="dismiss">{{ t('Close') }}</button>
        </div>
        <h3 class="newsletter-title">{{ resolvedTitle }}</h3>
        <p class="newsletter-copy">{{ resolvedBody }}</p>
        <p v-if="resolvedIncentive" class="newsletter-incentive">{{ resolvedIncentive }}</p>
        <form class="newsletter-form" @submit.prevent="submit">
          <input
            v-model="email"
            type="email"
            required
            :placeholder="t('Email address')"
            class="newsletter-input"
          />
          <button type="submit" class="newsletter-submit">
            {{ t('Get deals') }}
          </button>
        </form>
        <p v-if="notice" class="newsletter-note">{{ notice }}</p>
        <div class="newsletter-proof">
          <span>{{ t('Weekly drops') }}</span>
          <span>{{ t('Local delivery updates') }}</span>
          <span>{{ t('Exclusive coupons') }}</span>
        </div>
        
        <!-- Social Media Links -->
        <div v-if="socialLinks.length" class="newsletter-social">
          <p class="newsletter-social-title">{{ t('Follow us') }}</p>
          <div class="newsletter-social-links">
            <a
              v-for="link in socialLinks"
              :key="link.href"
              :href="link.href"
              :aria-label="link.label"
              target="_blank"
              rel="noopener noreferrer"
              class="newsletter-social-link"
            >
              <svg v-if="link.icon === 'facebook'" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
              <svg v-else-if="link.icon === 'twitter'" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M23.953 4.57a10 10 0 01-2.825.748 4.958 4.958 0 00-8.343 2.488A4.958 4.958 0 003.031 9.75a4.958 4.958 0 002.632-2.917 4.958 4.958 0 01-2.212-.085 4.958 4.958 0 004.631 3.414 9.917 9.917 0 01-6.107 2.107 4.958 4.958 0 003.414-4.631 9.917 9.917 0 01-2.107 6.107 4.958 4.958 0 004.631-3.414 9.917 9.917 0 016.107-2.107 4.958 4.958 0 00-3.414 4.631z"/>
              </svg>
              <svg v-else-if="link.icon === 'instagram'" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.85 0 3.204-.012 3.584-.069 4.85-.148 3.252-1.691 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.85-.069-3.252-.148-4.771-1.691-4.919-4.919-.058-1.265-.07-1.644-.07-4.85 0-3.204.012-3.583.069-4.849.148-3.252 1.691-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zM5.838 12a6.162 6.162 0 1112.324 0 6.162 6.162 0 01-12.324 0zM12 16a4 4 0 110-8 4 4 0 010 8zm4.965-10.405a1.44 1.44 0 112.881.001 1.44 1.44 0 01-2.881-.001z"/>
              </svg>
              <svg v-else-if="link.icon === 'youtube'" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-11.376.405a3.016 3.016 0 00-2.122 2.136C3.505 6.186 1.545 8.827 1.545 12c0 3.173 1.96 5.814 5.957 6.414a3.016 3.016 0 002.122 2.136c.878.347 3.87.405 11.376.405 7.505 0 10.498-.058 11.376-.405 1.498-1.082 2.136-2.122 2.136-3.173 0-5.814-1.96-8.827-5.957-6.414z"/>
              </svg>
              <span v-else class="text-slate-600">{{ link.label }}</span>
            </a>
          </div>
        </div>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import Modal from '@/Components/Modal.vue'
import { useTranslations } from '@/i18n'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
})

const { t } = useTranslations()
const page = usePage()
const visible = ref(false)
const email = ref('')
const notice = ref('')

// Get social links from storefront settings
const socialLinks = computed(() => page.props.storefront?.social_links ?? [])

const resolveImage = (path) => {
  if (!path) return null
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
    return path
  }
  return `/storage/${path}`
}

const resolvedTitle = computed(() => props.settings?.newsletter_popup_title || t('Join our list'))
const resolvedBody = computed(() => props.settings?.newsletter_popup_body || t('Get drop alerts and logistics updates.'))
const resolvedIncentive = computed(() => props.settings?.newsletter_popup_incentive || '')
const resolvedImage = computed(() => resolveImage(props.settings?.newsletter_popup_image))

const delaySeconds = computed(() => Number(props.settings?.newsletter_popup_delay_seconds ?? 3))
const dismissDays = computed(() => Number(props.settings?.newsletter_popup_dismiss_days ?? 14))

const dismissedKey = 'newsletter_popup_dismissed_at'

const shouldShow = () => {
  if (!props.settings?.newsletter_popup_enabled) return false
  try {
    const last = localStorage.getItem(dismissedKey)
    if (!last) return true
    const lastDate = new Date(last)
    const now = new Date()
    const diffDays = (now - lastDate) / (1000 * 60 * 60 * 24)
    return diffDays >= dismissDays.value
  } catch {
    return true
  }
}

const dismiss = () => {
  try {
    localStorage.setItem(dismissedKey, new Date().toISOString())
  } catch {}
  visible.value = false
}

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
      body: JSON.stringify({ email: email.value, source: 'newsletter_popup' }),
    })
    if (response.ok) {
      notice.value = t('Thanks for joining!')
      email.value = ''
      dismiss()
    } else {
      notice.value = t('Please check your email and try again.')
    }
  } catch {
    notice.value = t('Unable to subscribe right now.')
  }
}

onMounted(() => {
  if (!shouldShow()) return
  setTimeout(() => {
    visible.value = true
  }, delaySeconds.value * 1000)
})
</script>

<style scoped>
.newsletter-shell {
  display: grid;
  gap: 0;
  background: #fff;
  overflow: hidden;
  border-radius: 18px;
  min-height: 360px;
}

.newsletter-media {
  position: relative;
  background: linear-gradient(135deg, #0f172a, #111827);
}

.newsletter-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  min-height: 220px;
}

.newsletter-fallback {
  color: #f8fafc;
  padding: 28px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.fallback-chip {
  align-self: flex-start;
  font-size: 10px;
  letter-spacing: 0.25em;
  text-transform: uppercase;
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(251, 191, 36, 0.2);
  color: #fbbf24;
  border: 1px solid rgba(251, 191, 36, 0.4);
}

.newsletter-fallback h4 {
  font-size: 20px;
  font-weight: 700;
}

.newsletter-fallback p {
  font-size: 13px;
  color: rgba(226, 232, 240, 0.85);
}

.newsletter-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: #ffffff;
}

.newsletter-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.newsletter-kicker {
  font-size: 10px;
  letter-spacing: 0.3em;
  text-transform: uppercase;
  color: #94a3b8;
  font-weight: 700;
}

.newsletter-close {
  font-size: 11px;
  color: #64748b;
  font-weight: 600;
}

.newsletter-title {
  font-size: 20px;
  font-weight: 700;
  color: #0f172a;
}

.newsletter-copy {
  font-size: 13px;
  color: #64748b;
  line-height: 1.6;
}

.newsletter-incentive {
  font-size: 12px;
  font-weight: 700;
  color: #059669;
}

.newsletter-form {
  display: grid;
  gap: 10px;
  margin-top: 4px;
}

.newsletter-input {
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  padding: 10px 12px;
  font-size: 13px;
}

.newsletter-submit {
  border-radius: 12px;
  background: linear-gradient(135deg, #29ab87, #0ea5e9);
  color: #fff;
  padding: 10px 12px;
  font-size: 12px;
  font-weight: 700;
}

.newsletter-note {
  font-size: 11px;
  color: #64748b;
}

.newsletter-proof {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  font-size: 10px;
  font-weight: 600;
  color: #475569;
}

.newsletter-proof span {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  padding: 4px 10px;
}

.newsletter-social {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e2e8f0;
}

.newsletter-social-title {
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 0.5rem;
  text-align: center;
}

.newsletter-social-links {
  display: flex;
  justify-content: center;
  gap: 0.75rem;
}

.newsletter-social-link {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 50%;
  color: #64748b;
  transition: all 0.2s ease;
}

.newsletter-social-link:hover {
  background: #f1f5f9;
  color: #334155;
  transform: translateY(-1px);
}

@media (min-width: 900px) {
  .newsletter-shell {
    grid-template-columns: 1.1fr 1fr;
  }

  .newsletter-media {
    min-height: 100%;
  }
}
</style>
