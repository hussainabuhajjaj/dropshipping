// Currency conversion utility - fetches all settings from backend
let backendSettings = {}
let settingsFetched = false

// Fetch currency settings from backend API
async function fetchCurrencySettings() {
  if (settingsFetched) return backendSettings
  
  try {
    const response = await fetch('/api/currency-settings')
    if (response.ok) {
      const data = await response.json()
      backendSettings = data
      settingsFetched = true
    }
  } catch (error) {
    console.warn('Failed to fetch currency settings, using fallback:', error)
    // Fallback settings for critical currencies
    backendSettings = {
      rates: {
        USD: 1,
        XOF: 600,
        XAF: 600,
        JOD: 0.71,
        EUR: 0.92
      },
      decimals: {
        USD: 2,
        EUR: 2,
        JOD: 3,
        XOF: 0,
        XAF: 0
      },
      display: {
        auto_convert_prices: true,
        show_currency_selector: true,
        default_customer_currency: 'USD'
      }
    }
  }
  
  return backendSettings
}

export function convertCurrency(amount, from, to) {
  if (from === to) return amount
  
  const rates = backendSettings.rates || {}
  
  if (!rates[from] || !rates[to]) return amount
  
  // Convert to USD base, then to target
  const usdAmount = amount / rates[from]
  return usdAmount * rates[to]
}

export function formatCurrency(amount, currency) {
  const decimals = backendSettings.decimals || {}
  const precision = decimals[currency] || 2
  
  if (currency === 'XOF' || currency === 'XAF') {
    return amount.toLocaleString('fr-FR', { 
      style: 'currency', 
      currency: currency, 
      minimumFractionDigits: precision, 
      maximumFractionDigits: precision 
    })
  }
  
  if (currency === 'JOD') {
    return amount.toLocaleString('en-JO', { 
      style: 'currency', 
      currency: 'JOD', 
      minimumFractionDigits: precision, 
      maximumFractionDigits: precision 
    })
  }
  
  if (currency === 'EUR') {
    return amount.toLocaleString('de-DE', { 
      style: 'currency', 
      currency: 'EUR', 
      minimumFractionDigits: precision, 
      maximumFractionDigits: precision 
    })
  }
  
  return amount.toLocaleString('en-US', { 
    style: 'currency', 
    currency: currency, 
    minimumFractionDigits: precision, 
    maximumFractionDigits: precision 
  })
}

// Get currency display settings
export function getCurrencyDisplaySettings() {
  return backendSettings.display || {
    auto_convert_prices: true,
    show_currency_selector: true,
    default_customer_currency: 'USD'
  }
}

// Get available currencies
export function getAvailableCurrencies() {
  const rates = backendSettings.rates || {}
  const currencies = Object.keys(rates)
  
  // Fallback to default currencies if backend not loaded yet
  if (currencies.length === 0) {
    return ['USD', 'EUR', 'JOD', 'XOF', 'XAF']
  }
  
  return currencies
}

// Get decimal precision for a currency
export function getCurrencyDecimals(currency) {
  const decimals = backendSettings.decimals || {}
  return decimals[currency] || 2
}

// Initialize settings on import
fetchCurrencySettings()
