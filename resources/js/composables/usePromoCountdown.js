import { onUnmounted, ref } from 'vue'

let nowRef = null
let timerId = null
let subscribers = 0

export function usePromoNow(intervalMs = 1000) {
  if (!nowRef) {
    nowRef = ref(Date.now())
  }

  if (!timerId) {
    timerId = window.setInterval(() => {
      if (nowRef) {
        nowRef.value = Date.now()
      }
    }, intervalMs)
  }

  subscribers += 1
  onUnmounted(() => {
    subscribers -= 1
    if (subscribers <= 0 && timerId) {
      window.clearInterval(timerId)
      timerId = null
    }
  })

  return nowRef
}

export function formatCountdown(endAt, nowMs) {
  if (!endAt) return null
  const endTime = new Date(endAt).getTime()
  if (Number.isNaN(endTime)) return null

  const diffMs = endTime - nowMs
  if (diffMs <= 0) return null

  const totalSeconds = Math.floor(diffMs / 1000)
  const days = Math.floor(totalSeconds / 86400)
  const hours = Math.floor((totalSeconds % 86400) / 3600)
  const minutes = Math.floor((totalSeconds % 3600) / 60)
  const seconds = totalSeconds % 60

  if (days > 0) return `${days}d ${hours}h`
  if (hours > 0) return `${hours}h ${minutes}m`
  if (minutes > 0) return `${minutes}m ${seconds}s`
  return `${seconds}s`
}
