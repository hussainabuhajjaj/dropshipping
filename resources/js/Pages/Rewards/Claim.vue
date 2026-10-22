<template>
  <StorefrontLayout>
    <div class="min-h-screen bg-[#f7f3eb] pb-24 sm:pb-28">
      <div class="mx-auto max-w-lg px-4 pt-12 sm:pt-20">
        <!-- Not found -->
        <div v-if="!campaign" class="rounded-2xl border border-[#eadfce] bg-white p-8 text-center shadow-sm">
          <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-50">
            <svg viewBox="0 0 24 24" class="h-8 w-8 text-red-400" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </div>
          <h1 class="text-xl font-bold text-slate-900">{{ t('Campaign not found') }}</h1>
          <p class="mt-2 text-sm text-slate-500">{{ t('This reward link is invalid or has expired.') }}</p>
          <Link href="/" class="mt-6 inline-flex min-h-11 items-center rounded-full bg-[#ff6b35] px-6 text-sm font-bold text-white transition hover:bg-[#e55a2b]">
            {{ t('Go home') }}
          </Link>
        </div>

        <!-- Campaign -->
        <div v-else class="space-y-4">
          <!-- Success / celebration -->
          <div v-if="justClaimed" class="overflow-hidden rounded-2xl border border-green-200 bg-gradient-to-b from-green-50 to-white shadow-sm">
            <div class="flex justify-center pt-8">
              <div class="flex h-24 w-24 items-center justify-center rounded-full bg-green-100">
                <svg viewBox="0 0 24 24" class="h-12 w-12 text-green-500" fill="none" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
            </div>
            <div class="p-6 text-center">
              <h1 class="text-2xl font-black tracking-[-0.03em] text-green-800">{{ t('Congratulations!') }}</h1>
              <p class="mt-1 text-sm text-green-600">{{ t('You successfully claimed your reward.') }}</p>
              <div class="mt-4 rounded-xl bg-green-50 border border-green-200 p-4">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-green-600">{{ t('Your reward') }}</p>
                <p class="mt-1 text-2xl font-black text-green-900">{{ campaign.reward_label }}</p>
              </div>
              <p class="mt-4 text-sm text-slate-500">
                {{ rewardDescription }}
              </p>
              <div class="mt-6 flex flex-col gap-3">
                <Link href="/account" class="w-full rounded-full bg-[#ff6b35] px-6 py-3 text-sm font-bold text-white transition hover:bg-[#e55a2b]">
                  {{ t('View my rewards') }}
                </Link>
                <Link href="/" class="w-full rounded-full border border-[#eadfce] bg-[#fffaf4] px-6 py-3 text-sm font-bold text-slate-700 transition hover:border-slate-300">
                  {{ t('Continue shopping') }}
                </Link>
              </div>
            </div>
          </div>

          <!-- Already claimed -->
          <div v-else-if="alreadyClaimed" class="rounded-2xl border border-green-200 bg-green-50 p-6 text-center shadow-sm">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-green-100">
              <svg viewBox="0 0 24 24" class="h-7 w-7 text-green-600" fill="none" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
              </svg>
            </div>
            <h2 class="text-lg font-bold text-green-800">{{ t('Reward claimed!') }}</h2>
            <p class="mt-1 text-sm text-green-600">{{ t('You have already claimed this reward.') }}</p>
            <Link href="/" class="mt-4 inline-flex min-h-10 items-center rounded-full bg-[#ff6b35] px-5 text-sm font-bold text-white transition hover:bg-[#e55a2b]">
              {{ t('Continue shopping') }}
            </Link>
          </div>

          <!-- Claim in progress (auto-claim) -->
          <div v-else-if="isClaiming" class="rounded-2xl border border-[#eadfce] bg-white p-8 text-center shadow-sm">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-50">
              <svg viewBox="0 0 24 24" class="h-8 w-8 animate-spin text-[#ff6b35]" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/>
              </svg>
            </div>
            <h2 class="text-lg font-bold text-slate-900">{{ t('Claiming your reward...') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ t('Please wait while we process your reward.') }}</p>
          </div>

          <!-- Reward card (not claimed yet) -->
          <div v-else class="overflow-hidden rounded-2xl border border-[#eadfce] bg-white shadow-sm">
            <div class="flex justify-center pt-8">
              <div
                class="flex h-20 w-20 items-center justify-center rounded-full"
                :class="{
                  'bg-green-50': campaign.reward_type === 'product',
                  'bg-amber-50': campaign.reward_type === 'money',
                  'bg-blue-50': campaign.reward_type === 'points',
                }"
              >
                <svg v-if="campaign.reward_type === 'product'" viewBox="0 0 24 24" class="h-10 w-10 text-green-500" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                <svg v-else-if="campaign.reward_type === 'money'" viewBox="0 0 24 24" class="h-10 w-10 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <svg v-else viewBox="0 0 24 24" class="h-10 w-10 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                </svg>
              </div>
            </div>

            <div class="p-6 text-center">
              <h1 class="text-2xl font-black tracking-[-0.03em] text-slate-900">{{ campaign.title }}</h1>
              <p v-if="campaign.description" class="mt-2 text-sm text-slate-500">{{ campaign.description }}</p>

              <div class="mt-6 rounded-xl bg-[#fffaf4] border border-[#eadfce] p-4">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#c55b24]">{{ t('Your reward') }}</p>
                <p class="mt-1 text-2xl font-black text-slate-900">{{ campaign.reward_label }}</p>
              </div>

              <div v-if="isLoggedIn" class="mt-6">
                <button
                  type="button"
                  class="w-full rounded-full bg-[#ff6b35] px-6 py-3 text-sm font-bold text-white transition hover:bg-[#e55a2b]"
                  @click="claimReward"
                >
                  {{ t('Claim my reward') }}
                </button>
              </div>
            </div>
          </div>

          <!-- Register to claim -->
          <div v-if="!isLoggedIn && !justClaimed && campaign" class="rounded-2xl border border-[#eadfce] bg-white p-6 text-center shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">{{ t('Register to claim') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ t('Create an account or log in to claim this reward.') }}</p>
            <div class="mt-4 flex flex-col gap-3">
              <Link
                :href="`/register?redirect=${encodeURIComponent(currentUrl + '?auto_claim=1')}`"
                class="w-full rounded-full bg-[#ff6b35] px-6 py-3 text-sm font-bold text-white transition hover:bg-[#e55a2b]"
              >
                {{ t('Create an account') }}
              </Link>
              <Link
                :href="`/login?redirect=${encodeURIComponent(currentUrl + '?auto_claim=1')}`"
                class="w-full rounded-full border border-[#eadfce] bg-[#fffaf4] px-6 py-3 text-sm font-bold text-slate-700 transition hover:border-slate-300"
              >
                {{ t('Log in') }}
              </Link>
            </div>
          </div>

          <!-- Expiry info -->
          <p v-if="campaign?.expires_at && !justClaimed" class="text-center text-xs text-slate-400">
            {{ t('Offer expires') }} {{ campaign.expires_at }}
          </p>
        </div>
      </div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import { useTranslations } from '@/i18n'
import { toastAlert } from '@/utils/toast'

const { t } = useTranslations()

const props = defineProps({
  campaign: { type: Object, default: null },
  isLoggedIn: { type: Boolean, default: false },
  alreadyClaimed: { type: Boolean, default: false },
  justClaimed: { type: Boolean, default: false },
  autoClaim: { type: Boolean, default: false },
})

const isClaiming = ref(false)
const currentUrl = computed(() => window.location.href.split('?')[0])

const rewardDescription = computed(() => {
  if (!props.campaign) return ''
  switch (props.campaign.reward_type) {
    case 'money':
      return t('The amount has been added to your gift card balance. You can use it at checkout.')
    case 'product':
      return t('Your reward has been registered. Our team will contact you to arrange delivery.')
    case 'points':
      return t('Points have been credited to your account.')
    default:
      return ''
  }
})

const claimReward = async () => {
  if (isClaiming.value) return
  isClaiming.value = true
  router.post(`/r/${props.campaign.slug}/claim`, {}, {
    preserveScroll: true,
    preserveState: false,
    onSuccess: () => {
      toastAlert('success', t('Reward claimed successfully!'))
      isClaiming.value = false
    },
    onError: () => {
      toastAlert('error', t('Failed to claim reward. Please try again.'))
      isClaiming.value = false
    },
  })
}

onMounted(() => {
  if (props.autoClaim && props.isLoggedIn && !props.alreadyClaimed && !props.justClaimed) {
    claimReward()
  }

  if (props.justClaimed) {
    toastAlert('success', t('Congratulations! You claimed your reward!'))
  }
})
</script>
