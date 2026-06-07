<template>
    <div
        v-if="announcementVisible"
        class="border-b border-black/10"
        :class="announcementBarClass"
    >
        <div class="container-base flex items-start gap-3 py-2.5 text-sm">
            <div class="mt-0.5 h-2.5 w-2.5 flex-none rounded-full bg-white/90"></div>
            <p class="min-w-0 flex-1 leading-snug text-white">
                {{ announcementMessage }}
            </p>
            <button
                v-if="announcementDismissible"
                type="button"
                class="flex-none rounded-md px-2 py-1 text-xs font-semibold text-white/95 hover:bg-white/10"
                @click="dismissAnnouncement"
            >
                {{ t('Dismiss') }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { useAnnouncement } from '@/composables/useAnnouncement'
import { useTranslations } from '@/i18n'

const { t } = useTranslations()
const {
    announcementVisible,
    announcementMessage,
    announcementDismissible,
    announcementBarClass,
    dismissAnnouncement,
} = useAnnouncement()
</script>
