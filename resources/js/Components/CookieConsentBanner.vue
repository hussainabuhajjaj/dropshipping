<script setup>
import { computed, onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const { t } = useTranslations()

const COOKIE_NAME = 'storefront_cookie_consent'
const CONSENT_TTL_DAYS = 365

const visible = ref(false)
const customize = ref(false)
const consent = ref({
    necessary: true,
    analytics: true,
    preferences: true,
    marketing: false,
})

const summaryText = computed(() =>
    consent.value.analytics
        ? t('Analytics cookies enabled')
        : t('Only essential cookies enabled')
)

const readCookie = (name) => {
    if (typeof document === 'undefined') {
        return null
    }

    const prefix = `${name}=`
    const match = document.cookie
        .split(';')
        .map((part) => part.trim())
        .find((part) => part.startsWith(prefix))

    return match ? decodeURIComponent(match.slice(prefix.length)) : null
}

const writeCookie = (name, value, days) => {
    if (typeof document === 'undefined') {
        return
    }

    const expires = new Date(Date.now() + days * 24 * 60 * 60 * 1000).toUTCString()
    document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`
}

const persistConsent = (payload) => {
    const body = {
        ...payload,
        updated_at: new Date().toISOString(),
    }

    writeCookie(COOKIE_NAME, JSON.stringify(body), CONSENT_TTL_DAYS)
    window.dispatchEvent(new CustomEvent('cookie-consent-updated', { detail: body }))
    visible.value = false
    customize.value = false
}

const acceptAll = () => {
    consent.value = {
        necessary: true,
        analytics: true,
        preferences: true,
        marketing: true,
    }

    persistConsent(consent.value)
}

const acceptEssential = () => {
    consent.value = {
        necessary: true,
        analytics: false,
        preferences: false,
        marketing: false,
    }

    persistConsent(consent.value)
}

const savePreferences = () => {
    persistConsent({
        necessary: true,
        analytics: Boolean(consent.value.analytics),
        preferences: Boolean(consent.value.preferences),
        marketing: Boolean(consent.value.marketing),
    })
}

onMounted(() => {
    const raw = readCookie(COOKIE_NAME)
    if (!raw) {
        visible.value = true
        return
    }

    try {
        const parsed = JSON.parse(raw)
        consent.value = {
            necessary: true,
            analytics: Boolean(parsed.analytics),
            preferences: Boolean(parsed.preferences),
            marketing: Boolean(parsed.marketing),
        }
    } catch {
        visible.value = true
    }
})
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-0 z-[160] flex justify-center px-4 pb-4">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-4 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-4 opacity-0"
        >
            <div
                v-if="visible"
                class="pointer-events-auto w-full max-w-4xl rounded-3xl border border-slate-200 bg-white/95 p-5 shadow-2xl backdrop-blur"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-sm font-semibold text-slate-900">{{ t('Cookie preferences') }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {{ t('We use essential cookies to keep the store secure and working. With your permission, we also use analytics and preference cookies to improve the storefront experience.') }}
                        </p>
                        <p class="mt-2 text-xs text-slate-500">
                            {{ summaryText }}
                            <span class="mx-1">·</span>
                            <Link href="/legal/cookie-policy" class="font-semibold text-slate-900 hover:text-slate-700">
                                {{ t('Cookie policy') }}
                            </Link>
                        </p>
                    </div>

                    <div class="flex w-full flex-col gap-2 lg:w-auto lg:min-w-[220px]">
                        <button
                            type="button"
                            class="rounded-full bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700"
                            @click="acceptAll"
                        >
                            {{ t('Accept all') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-full border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                            @click="acceptEssential"
                        >
                            {{ t('Essential only') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-4 py-2.5 text-sm font-semibold text-slate-500 transition hover:text-slate-900"
                            @click="customize = !customize"
                        >
                            {{ customize ? t('Hide preferences') : t('Customize') }}
                        </button>
                    </div>
                </div>

                <div v-if="customize" class="mt-4 grid gap-3 border-t border-slate-200 pt-4 md:grid-cols-3">
                    <label class="rounded-2xl border border-slate-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ t('Essential') }}</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500">
                                    {{ t('Required for checkout, security, and basic storefront functionality.') }}
                                </p>
                            </div>
                            <input type="checkbox" checked disabled class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-900" />
                        </div>
                    </label>

                    <label class="rounded-2xl border border-slate-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ t('Analytics') }}</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500">
                                    {{ t('Helps us measure visits, popular products, and storefront performance.') }}
                                </p>
                            </div>
                            <input v-model="consent.analytics" type="checkbox" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-900" />
                        </div>
                    </label>

                    <label class="rounded-2xl border border-slate-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ t('Preferences') }}</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500">
                                    {{ t('Remembers convenience choices like storefront presentation and non-essential preferences.') }}
                                </p>
                            </div>
                            <input v-model="consent.preferences" type="checkbox" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-900" />
                        </div>
                    </label>

                    <label class="rounded-2xl border border-slate-200 p-4 md:col-span-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ t('Marketing') }}</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500">
                                    {{ t('Supports promotion and campaign personalization when enabled.') }}
                                </p>
                            </div>
                            <input v-model="consent.marketing" type="checkbox" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-900" />
                        </div>
                    </label>

                    <div class="md:col-span-3 flex justify-end">
                        <button
                            type="button"
                            class="rounded-full bg-[#f59e0b] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#d97706]"
                            @click="savePreferences"
                        >
                            {{ t('Save preferences') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
