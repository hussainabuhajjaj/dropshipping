// Simple currency conversion utility for USD <-> XOF (CFA)
// In production, use dynamic rates from a reliable API/service

const RATES = {
  USD: 1,
  XOF: 600, // Example: 1 USD = 600 XOF
}

export function convertCurrency(amount, from, to) {
  if (from === to) return amount
  if (!RATES[from] || !RATES[to]) return amount
  // Convert to USD base, then to target
  const usdAmount = amount / RATES[from]
  return usdAmount * RATES[to]
}

export function formatCurrency(amount, currency) {
  if (currency === 'XOF') {
    return amount.toLocaleString('fr-FR', { style: 'currency', currency: 'XOF', minimumFractionDigits: 0, maximumFractionDigits: 0 })
  }
  return amount.toLocaleString('en-US', { style: 'currency', currency: 'USD' })
}
