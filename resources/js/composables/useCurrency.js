import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { getAvailableCurrencies, getCurrencyDisplaySettings } from '@/utils/currency'

const CURRENCY_KEY = 'dropshipping_currency'
const currencyOptions = ['XOF']
const selectedCurrency = ref('XOF')

const normalizeCurrency = (value) => {
  const normalized = String(value || '').trim().toUpperCase()
  const availableCurrencies = getAvailableCurrencies()
  return availableCurrencies.includes(normalized) ? normalized : 'XOF'
}

const csrfHeaders = () => {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  return token ? { 'X-CSRF-TOKEN': token } : {}
}

const setCurrency = (value) => {
  const next = normalizeCurrency(value)
  selectedCurrency.value = next
  if (typeof window !== 'undefined') {
    window.localStorage.setItem(CURRENCY_KEY, next)
    // Make API call to backend to persist currency preference
    router.post('/currency', { currency: next }, {
      preserveScroll: true,
      headers: csrfHeaders(),
      onSuccess: () => {
        console.log('Currency preference saved successfully')
      },
      onError: (errors) => {
        console.error('Failed to save currency preference:', errors)
      }
    })
  }
}

if (typeof window !== 'undefined') {
  const stored = window.localStorage.getItem(CURRENCY_KEY)
  if (stored) {
    selectedCurrency.value = normalizeCurrency(stored)
  }
  
  // Initialize from backend if available
  const backendCurrency = document.querySelector('meta[name="current-currency"]')?.getAttribute('content')
  if (backendCurrency) {
    selectedCurrency.value = normalizeCurrency(backendCurrency)
    window.localStorage.setItem(CURRENCY_KEY, selectedCurrency.value)
  }

  window.addEventListener('storage', (event) => {
    if (event.key === CURRENCY_KEY && event.newValue) {
      selectedCurrency.value = normalizeCurrency(event.newValue)
    }
  })
}

export function useCurrency() {
  return {
    currencyOptions: getAvailableCurrencies(),
    selectedCurrency,
    setCurrency,
    displaySettings: getCurrencyDisplaySettings(),
  }
}
