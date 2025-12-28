<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const { t, locale, availableLocales } = useTranslations()
const localeOptions = computed(() => {
    const entries = Object.entries(availableLocales.value ?? {})
    return entries.map(([code, label]) => ({ code, label }))
})

const setLocale = (target) => {
    if (! target || target === locale.value) {
        return
    }
    router.get(`/locale/${target}`, {}, { preserveScroll: true })
}
</script>

<template>
    <div class="auth-shell">
        <div class="auth-glow auth-glow-one" />
        <div class="auth-glow auth-glow-two" />

        <div class="auth-wrapper">
            <aside class="auth-aside">
                <Link href="/" class="auth-brand">Azura</Link>
                <p class="auth-tagline">{{ t('Curated global essentials, delivered with clarity.') }}</p>
                <div class="auth-points">
                    <div class="auth-point">
                        <span>✓</span>
                        <p>{{ t('Fast supplier confirmation and tracking updates.') }}</p>
                    </div>
                    <div class="auth-point">
                        <span>✓</span>
                        <p>{{ t('Customs and duties shown before you pay.') }}</p>
                    </div>
                    <div class="auth-point">
                        <span>✓</span>
                        <p>{{ t('Support on WhatsApp for every order.') }}</p>
                    </div>
                </div>
            </aside>

            <div class="auth-card">
                <div class="mb-5 flex justify-end">
                    <div class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-1 py-1 text-[0.6rem] font-semibold text-slate-600">
                        <button
                            v-for="option in localeOptions"
                            :key="option.code"
                            type="button"
                            class="rounded-full px-2 py-1 uppercase transition"
                            :class="option.code === locale ? 'bg-slate-900 text-white' : 'hover:bg-slate-100'"
                            :title="option.label"
                            @click="setLocale(option.code)"
                        >
                            {{ option.code }}
                        </button>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.auth-shell {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(120deg, #f7f4ef 0%, #f2f7fb 45%, #f0f7f2 100%);
    position: relative;
    overflow: hidden;
    padding: 48px 16px;
    font-family: "Segoe UI", ui-sans-serif, system-ui, -apple-system, "Helvetica Neue", Arial, sans-serif;
    color: #1f2937;
}

.auth-glow {
    position: absolute;
    border-radius: 999px;
    filter: blur(0px);
    opacity: 0.6;
}

.auth-glow-one {
    width: 320px;
    height: 320px;
    background: radial-gradient(circle, rgba(255, 206, 120, 0.5), rgba(255, 206, 120, 0));
    top: -120px;
    right: -80px;
}

.auth-glow-two {
    width: 260px;
    height: 260px;
    background: radial-gradient(circle, rgba(93, 156, 236, 0.35), rgba(93, 156, 236, 0));
    bottom: -120px;
    left: -60px;
}

.auth-wrapper {
    width: min(960px, 100%);
    display: grid;
    gap: 24px;
    grid-template-columns: minmax(0, 1fr);
    position: relative;
    z-index: 1;
}

.auth-aside {
    background: #111827;
    color: #f9fafb;
    border-radius: 24px;
    padding: 28px;
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.auth-brand {
    font-family: ui-serif, Georgia, "Times New Roman", serif;
    font-size: 28px;
    letter-spacing: 0.04em;
}

.auth-tagline {
    font-size: 14px;
    color: #d1d5db;
}

.auth-points {
    display: grid;
    gap: 12px;
    font-size: 13px;
}

.auth-point {
    display: flex;
    gap: 10px;
    align-items: flex-start;
}

.auth-point span {
    color: #facc15;
    font-weight: 700;
}

.auth-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 24px;
    padding: 28px;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
}

@media (min-width: 900px) {
    .auth-wrapper {
        grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
        align-items: stretch;
    }

    .auth-card {
        padding: 36px;
    }
}
</style>
