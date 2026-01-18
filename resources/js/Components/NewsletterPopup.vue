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
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import Modal from '@/Components/Modal.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
})

const { t } = useTranslations()
const visible = ref(false)
const email = ref('')
const notice = ref('')

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

@media (min-width: 900px) {
  .newsletter-shell {
    grid-template-columns: 1.1fr 1fr;
  }

  .newsletter-media {
    min-height: 100%;
  }
}
</style>
