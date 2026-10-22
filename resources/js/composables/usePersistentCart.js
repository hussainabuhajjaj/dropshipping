import { ref, watch } from 'vue'

const CART_KEY = 'dropshipping_cart_v1'

export function usePersistentCart(initialCart = { lines: [], count: 0, subtotal: 0 }) {
  const cart = ref(loadCart())

  function loadCart() {
    try {
      const stored = localStorage.getItem(CART_KEY)
      return stored ? JSON.parse(stored) : initialCart
    } catch {
      return initialCart
    }
  }

  function saveCart() {
    localStorage.setItem(CART_KEY, JSON.stringify(cart.value))
  }

  function setCart(newCart) {
    cart.value = newCart
    saveCart()
  }

  function addLine(line) {
    const idx = cart.value.lines.findIndex(l => l.id === line.id)
    if (idx !== -1) {
      cart.value.lines[idx].quantity += line.quantity
    } else {
      cart.value.lines.push(line)
    }
    cart.value.count = cart.value.lines.reduce((sum, l) => sum + l.quantity, 0)
    cart.value.subtotal = cart.value.lines.reduce((sum, l) => sum + l.price * l.quantity, 0)
    saveCart()
  }

  function updateLine(id, quantity) {
    const idx = cart.value.lines.findIndex(l => l.id === id)
    if (idx !== -1) {
      cart.value.lines[idx].quantity = quantity
      cart.value.count = cart.value.lines.reduce((sum, l) => sum + l.quantity, 0)
      cart.value.subtotal = cart.value.lines.reduce((sum, l) => sum + l.price * l.quantity, 0)
      saveCart()
    }
  }

  function removeLine(id) {
    cart.value.lines = cart.value.lines.filter(l => l.id !== id)
    cart.value.count = cart.value.lines.reduce((sum, l) => sum + l.quantity, 0)
    cart.value.subtotal = cart.value.lines.reduce((sum, l) => sum + l.price * l.quantity, 0)
    saveCart()
  }

  function clearCart() {
    cart.value = { lines: [], count: 0, subtotal: 0 }
    saveCart()
  }

  watch(cart, saveCart, { deep: true })

  return {
    cart,
    setCart,
    addLine,
    updateLine,
    removeLine,
    clearCart,
    loadCart
  }
}
