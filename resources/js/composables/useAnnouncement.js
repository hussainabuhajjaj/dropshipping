import { computed, onMounted, onBeforeUnmount, ref, shallowRef, watchEffect } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useAnnouncement() {
    const page = usePage()

    const announcement = computed(() => page.props.announcement ?? { enabled: false })
    const liveAnnouncement = ref(null)
    const announcementVisible = ref(false)

    const effectiveAnnouncement = computed(() => liveAnnouncement.value ?? announcement.value)
    const announcementMessage = computed(() => String(effectiveAnnouncement.value?.message ?? '').trim())
    const announcementDismissible = computed(() => Boolean(effectiveAnnouncement.value?.dismissible ?? true))
    const announcementLevel = computed(() => String(effectiveAnnouncement.value?.level ?? 'warning'))
    const announcementId = computed(() => String(effectiveAnnouncement.value?.id ?? '').trim())

    const announcementStorageKey = computed(() => {
        const id = announcementId.value
        if (id) return `simbazu_announcement_dismissed_${id}`
        const msg = announcementMessage.value
        const slug = msg
            .toLowerCase()
            .slice(0, 60)
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
        return `simbazu_announcement_dismissed_${slug || 'default'}`
    })

    const announcementBarClass = computed(() => {
        const level = announcementLevel.value
        if (level === 'danger') return 'bg-gradient-to-r from-rose-700 via-rose-600 to-orange-600'
        if (level === 'success') return 'bg-gradient-to-r from-emerald-700 via-emerald-600 to-teal-600'
        if (level === 'info') return 'bg-gradient-to-r from-sky-700 via-sky-600 to-indigo-600'
        return 'bg-gradient-to-r from-amber-700 via-orange-600 to-rose-600'
    })

    const readAnnouncementDismissed = () => {
        try {
            return typeof window !== 'undefined' && window.localStorage.getItem(announcementStorageKey.value) === '1'
        } catch {
            return false
        }
    }

    const dismissAnnouncement = () => {
        announcementVisible.value = false
        try {
            if (typeof window !== 'undefined') {
                window.localStorage.setItem(announcementStorageKey.value, '1')
            }
        } catch {
            // ignore
        }
    }

    watchEffect(() => {
        const enabled = Boolean(effectiveAnnouncement.value?.enabled)
        if (!enabled || announcementMessage.value === '') {
            announcementVisible.value = false
            return
        }
        if (announcementDismissible.value && readAnnouncementDismissed()) {
            announcementVisible.value = false
            return
        }
        announcementVisible.value = true
    })

    // --- Realtime announcement push (Pusher) ---
    const storefrontRealtime = computed(() => page.props.storefrontRealtime ?? { enabled: false })
    const pusherState = shallowRef({ ready: false, pusher: null, channel: null })

    const ensurePusherScript = () => {
        if (typeof window === 'undefined') return Promise.resolve(false)
        if (window.Pusher) return Promise.resolve(true)

        return new Promise((resolve) => {
            const scriptId = 'pusher-js-sdk'
            const existing = document.getElementById(scriptId)
            if (existing) {
                existing.addEventListener('load', () => resolve(true), { once: true })
                existing.addEventListener('error', () => resolve(false), { once: true })
                return
            }

            const script = document.createElement('script')
            script.id = scriptId
            script.src = 'https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js'
            script.async = true
            script.onload = () => resolve(true)
            script.onerror = () => resolve(false)
            document.head.appendChild(script)
        })
    }

    const connectAnnouncementChannel = async () => {
        const cfg = storefrontRealtime.value || {}
        if (!cfg.enabled || cfg.driver !== 'pusher' || !cfg.key) return
        if (pusherState.value.ready) return

        const ok = await ensurePusherScript()
        if (!ok || typeof window === 'undefined' || !window.Pusher) return

        const wsHost = (cfg.ws_host || '').trim()
        const forceTLS = Boolean(cfg.force_tls)
        const wsPort = Number(cfg.ws_port || 443)
        const wssPort = Number(cfg.wss_port || 443)

        const pusher = new window.Pusher(cfg.key, {
            cluster: cfg.cluster || undefined,
            forceTLS,
            wsHost: wsHost || undefined,
            wsPort,
            wssPort,
            enabledTransports: ['ws', 'wss'],
        })

        const channel = pusher.subscribe('storefront.announcements')
        channel.bind('storefront.announcement', (data) => {
            const payload = data && typeof data === 'object' ? data : {}
            const message = String(payload.message || '').trim()
            if (!message) return
            liveAnnouncement.value = {
                enabled: true,
                message,
                level: String(payload.level || 'warning'),
                dismissible: Boolean(payload.dismissible ?? true),
                id: payload.id ? String(payload.id) : null,
            }
        })

        pusherState.value = { ready: true, pusher, channel }
    }

    onMounted(() => {
        connectAnnouncementChannel()
    })

    onBeforeUnmount(() => {
        const state = pusherState.value
        try { state?.channel?.unbind?.('storefront.announcement') } catch {}
        try { state?.pusher?.unsubscribe?.('storefront.announcements') } catch {}
        try { state?.pusher?.disconnect?.() } catch {}
        pusherState.value = { ready: false, pusher: null, channel: null }
    })

    return {
        announcementVisible,
        announcementMessage,
        announcementDismissible,
        announcementBarClass,
        dismissAnnouncement,
    }
}
