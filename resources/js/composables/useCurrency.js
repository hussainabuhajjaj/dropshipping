import { ref } from 'vue'

const CURRENCY_KEY = 'dropshipping_currency'
const currencyOptions = ['USD', 'XOF']
const selectedCurrency = ref('USD')

const normalizeCurrency = (value) => {
  const normalized = String(value || '').trim().toUpperCase()
  return currencyOptions.includes(normalized) ? normalized : 'USD'
}

const setCurrency = (value) => {
  const next = normalizeCurrency(value)
  selectedCurrency.value = next
  if (typeof window !== 'undefined') {
    window.localStorage.setItem(CURRENCY_KEY, next)
  }
}

if (typeof window !== 'undefined') {
  const stored = window.localStorage.getItem(CURRENCY_KEY)
  if (stored) {
    selectedCurrency.value = normalizeCurrency(stored)
  }

  window.addEventListener('storage', (event) => {
    if (event.key === CURRENCY_KEY && event.newValue) {
      selectedCurrency.value = normalizeCurrency(event.newValue)
    }
  })
}

export function useCurrency() {
  return {
    currencyOptions,
    selectedCurrency,
    setCurrency,
  }
}
