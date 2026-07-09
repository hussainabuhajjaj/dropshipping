<template>
    <Modal :show="visible" maxWidth="2xl" @close="dismiss">
        <div class="grid overflow-hidden bg-white md:grid-cols-[1.02fr_0.98fr]">
            <div class="relative min-h-[260px] overflow-hidden bg-[#111111]">
                <img
                    v-if="heroImage"
                    :src="heroImage"
                    :alt="resolvedTitle"
                    class="h-full w-full object-cover"
                />
                <div class="absolute inset-0 bg-gradient-to-br from-black/75 via-black/35 to-black/10" />
                <div class="absolute inset-x-0 top-0 flex items-center justify-between p-4 sm:p-5">
                    <span class="inline-flex rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[0.62rem] font-bold uppercase tracking-[0.2em] text-white backdrop-blur">
                        {{ resolvedBadge }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-slate-900 transition hover:bg-white"
                        @click="dismiss"
                    >
                        <span class="sr-only">{{ t("Close") }}</span>
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                        </svg>
                    </button>
                </div>
                <div class="absolute inset-x-0 bottom-0 p-4 text-white sm:p-5">
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full bg-white/12 px-3 py-1 text-[0.62rem] font-semibold text-white/92 backdrop-blur">
                            {{ t("App-only offers") }}
                        </span>
                        <span class="rounded-full bg-white/12 px-3 py-1 text-[0.62rem] font-semibold text-white/92 backdrop-blur">
                            {{ t("Real-time tracking") }}
                        </span>
                        <span class="rounded-full bg-white/12 px-3 py-1 text-[0.62rem] font-semibold text-white/92 backdrop-blur">
                            {{ t("Faster checkout") }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="space-y-4 p-5 sm:p-6">
                <div>
                    <p class="text-[0.68rem] font-bold uppercase tracking-[0.2em] text-[#ff6b35]">
                        {{ t("Download the app") }}
                    </p>
                    <h3 class="mt-2 text-[1.45rem] font-black leading-tight tracking-[-0.04em] text-slate-950 sm:text-[1.8rem]">
                        {{ resolvedTitle }}
                    </h3>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        {{ resolvedSubtitle }}
                    </p>
                </div>

                <div class="grid gap-3 rounded-[1.35rem] bg-[#faf5ef] p-3.5 sm:p-4">
                    <div class="rounded-[1.1rem] border border-[#e7dbc9] bg-white p-3.5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[0.72rem] font-bold uppercase tracking-[0.18em] text-slate-500">
                                    {{ t("iPhone & iPad") }}
                                </p>
                                <p class="mt-1 text-sm font-black text-slate-950">
                                    {{ resolvedIosLabel }}
                                </p>
                            </div>
                            <a
                                v-if="iosEnabled"
                                :href="settings.ios_href"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex min-h-11 items-center justify-center rounded-full bg-slate-950 px-4 text-[0.76rem] font-bold uppercase tracking-[0.14em] text-white transition hover:bg-slate-800"
                                @click="markConverted"
                            >
                                {{ t("Download") }}
                            </a>
                            <button
                                v-else
                                type="button"
                                disabled
                                class="inline-flex min-h-11 items-center justify-center rounded-full bg-slate-200 px-4 text-[0.76rem] font-bold uppercase tracking-[0.14em] text-slate-400"
                            >
                                {{ t("Unavailable") }}
                            </button>
                        </div>
                    </div>

                    <div class="rounded-[1.1rem] border border-[#e7dbc9] bg-white p-3.5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[0.72rem] font-bold uppercase tracking-[0.18em] text-slate-500">
                                    {{ t("Android") }}
                                </p>
                                <p class="mt-1 text-sm font-black text-slate-950">
                                    {{ resolvedAndroidLabel }}
                                </p>
                            </div>
                            <a
                                :href="androidHref"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex min-h-11 items-center justify-center rounded-full border border-slate-900 bg-white px-4 text-[0.76rem] font-bold uppercase tracking-[0.14em] text-slate-950 transition hover:bg-slate-50"
                                @click="markConverted"
                            >
                                {{ androidButtonLabel }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-[0.76rem] font-semibold text-slate-500">
                        {{ t("Open on your phone for the fastest Simbazu shopping flow.") }}
                    </p>
                    <button
                        type="button"
                        class="text-[0.74rem] font-bold uppercase tracking-[0.16em] text-slate-500 transition hover:text-slate-950"
                        @click="dismiss"
                    >
                        {{ t("Not now") }}
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import Modal from "@/Components/Modal.vue";
import { useTranslations } from "@/i18n";
import { computed, onMounted, ref } from "vue";

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
    heroImage: { type: String, default: null },
});

const { t } = useTranslations();
const visible = ref(false);
const dismissedKey = "simbazu_app_download_popup_seen_at";

const resolvedBadge = computed(
    () => props.settings?.badge || t("App-only deals"),
);
const resolvedTitle = computed(
    () => props.settings?.title || t("Unlock the full Simbazu app experience"),
);
const resolvedSubtitle = computed(
    () =>
        props.settings?.subtitle ||
        t("Get faster checkout, real-time order tracking, and mobile-only drops."),
);
const resolvedIosLabel = computed(
    () => props.settings?.ios_label || t("Download on the App Store"),
);
const resolvedAndroidLabel = computed(
    () => props.settings?.android_label || t("Google Play coming soon"),
);
const iosEnabled = computed(() => Boolean(props.settings?.ios_href));
const androidEnabled = computed(() => Boolean(props.settings?.android_href));
const isEnabled = computed(() => Boolean(props.settings?.enabled));

const androidHref = computed(() => {
  if (props.settings?.android_href) return props.settings.android_href
  return '/download'
})

const androidButtonLabel = computed(() => {
  if (props.settings?.android_label && props.settings?.android_href) return props.settings.android_label
  return t('Download APK')
})

const hasSeen = () => {
    try {
        return Boolean(localStorage.getItem(dismissedKey));
    } catch {
        return false;
    }
};

const markSeen = () => {
    try {
        localStorage.setItem(dismissedKey, new Date().toISOString());
    } catch {
        // ignore
    }
};

const dismiss = () => {
    markSeen();
    visible.value = false;
};

const markConverted = () => {
    markSeen();
    visible.value = false;
};

const open = () => {
    visible.value = true;
};

defineExpose({ open, dismiss });

onMounted(() => {
    if (!isEnabled.value || hasSeen()) return;
    window.setTimeout(() => {
        visible.value = true;
    }, 1400);
});
</script>
