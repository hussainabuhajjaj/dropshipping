import { onMounted, watch, onUnmounted, unref } from 'vue'

export function useJsonLd(schema) {
  let scriptEl = null

  const injectJsonLd = () => {
    // Remove existing script if any
    if (scriptEl) {
      document.head.removeChild(scriptEl)
    }

    // Create new script element
    scriptEl = document.createElement('script')
    scriptEl.type = 'application/ld+json'
    
    // Unwrap computed refs before stringifying
    const schemaValue = unref(schema)
    scriptEl.textContent = typeof schemaValue === 'string' ? schemaValue : JSON.stringify(schemaValue)
    document.head.appendChild(scriptEl)
  }

  const removeJsonLd = () => {
    if (scriptEl) {
      document.head.removeChild(scriptEl)
      scriptEl = null
    }
  }

  onMounted(() => {
    injectJsonLd()
  })

  if (typeof schema !== 'string') {
    watch(schema, injectJsonLd, { deep: true })
  }

  onUnmounted(() => {
    removeJsonLd()
  })
}

export function useMultipleJsonLd(schemas) {
  const scriptEls = []

  const injectJsonLd = () => {
    // Remove existing scripts
    scriptEls.forEach(el => {
      if (el) document.head.removeChild(el)
    })
    scriptEls.length = 0

    // Add new scripts
    schemas.forEach(schema => {
      const scriptEl = document.createElement('script')
      scriptEl.type = 'application/ld+json'
      
      // Unwrap computed refs before stringifying
      const schemaValue = unref(schema)
      scriptEl.textContent = typeof schemaValue === 'string' ? schemaValue : JSON.stringify(schemaValue)
      document.head.appendChild(scriptEl)
      scriptEls.push(scriptEl)
    })
  }

  const removeJsonLd = () => {
    scriptEls.forEach(el => {
      if (el) document.head.removeChild(el)
    })
    scriptEls.length = 0
  }

  onMounted(() => {
    injectJsonLd()
  })

  onUnmounted(() => {
    removeJsonLd()
  })
}
