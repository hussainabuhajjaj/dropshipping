import { computed, ref } from 'vue'

const visualOptionPattern = /(color|colour|finish|shade|pattern|style)/i
const dropdownOptionPattern = /(bundle|pack|quantity|set)/i
const sizeOptionPattern = /^(xxxs|xxs|xs|s|m|l|xl|xxl|xxxl|xxxxl|one size|free size|small|medium|large|us\s*\d+.*|eu\s*\d+.*|uk\s*\d+.*|\d{2,3}([-/]\d{2,3})?)$/i

function normalizeOptionKey(value) {
  return String(value ?? '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '_')
}

function splitCompoundValue(value, separator) {
  return String(value ?? '')
    .split(separator)
    .map((part) => part.trim())
    .filter(Boolean)
}

function inferCompoundOptionConfig(variants) {
  if (variants.length < 2) return null

  const rawVariantOptions = variants.map((variant) => {
    const rawOptions =
      variant?.options && typeof variant.options === 'object'
        ? Object.entries(variant.options)
        : []
    return rawOptions.length === 1 ? rawOptions[0] : null
  })

  if (rawVariantOptions.some((entry) => !entry)) return null

  const separators = [/\s*\/\s*/, /\s*\|\s*/, /\s*-\s*/, /\s*,\s*/]

  for (const separator of separators) {
    const partsList = rawVariantOptions.map(([, value]) => splitCompoundValue(value, separator))
    const partCount = partsList[0]?.length ?? 0

    if (partCount < 2 || partsList.some((parts) => parts.length !== partCount)) continue

    const labels = Array.from({ length: partCount }, (_, index) => {
      const values = partsList.map((parts) => parts[index])
      if (values.every((v) => sizeOptionPattern.test(v))) return 'Size'
      if (index === 0) return 'Color'
      return `Option ${index + 1}`
    })

    return { separator, labels }
  }

  return null
}

function variantMatchesSelection(variant, selection) {
  return Object.entries(selection).every(([key, value]) => {
    if (!value) return true
    return variant.normalizedOptions[key] === value
  })
}

function choosePreferredValue(groupKey, values, variants) {
  return [...values]
    .sort((left, right) => {
      const leftVariants = variants.filter((v) => v.normalizedOptions[groupKey] === left)
      const rightVariants = variants.filter((v) => v.normalizedOptions[groupKey] === right)
      const leftScore =
        (leftVariants.some((v) => Number(v.stock_on_hand ?? 0) > 0) ? 100 : 0) +
        (leftVariants.some((v) => v.variant_image) ? 10 : 0)
      const rightScore =
        (rightVariants.some((v) => Number(v.stock_on_hand ?? 0) > 0) ? 100 : 0) +
        (rightVariants.some((v) => v.variant_image) ? 10 : 0)
      return rightScore - leftScore
    })[0] ?? null
}

function choosePreferredVariant(variants, selectedVariantId) {
  if (!variants.length) return null
  return (
    variants.find((v) => v.id === selectedVariantId) ??
    variants.find((v) => Number(v.stock_on_hand ?? 0) > 0) ??
    variants[0]
  )
}

export function useProductVariantSelection(product, selectedVariantId) {
  const selectedOptions = ref({})

  const normalizedVariants = computed(() => {
    const variants = Array.isArray(product.value?.variants) ? product.value.variants : []
    const compoundConfig = inferCompoundOptionConfig(variants)

    return variants.map((variant) => {
      const rawOptions = variant?.options && typeof variant.options === 'object' ? variant.options : {}
      const normalizedOptions = {}
      const optionLabels = {}

      Object.entries(rawOptions).forEach(([key, value]) => {
        const normalizedKey = normalizeOptionKey(key)
        const label = String(key ?? '').trim()
        const normalizedValue = String(value ?? '').trim()

        if (!normalizedKey || !normalizedValue) return

        normalizedOptions[normalizedKey] = normalizedValue
        optionLabels[normalizedKey] = label || normalizedKey
      })

      if (compoundConfig && Object.keys(normalizedOptions).length === 1 && normalizedOptions.option) {
        const parts = splitCompoundValue(normalizedOptions.option, compoundConfig.separator)

        if (parts.length === compoundConfig.labels.length) {
          Object.keys(normalizedOptions).forEach((key) => delete normalizedOptions[key])
          Object.keys(optionLabels).forEach((key) => delete optionLabels[key])

          parts.forEach((part, index) => {
            const label = compoundConfig.labels[index]
            const normalizedKey = normalizeOptionKey(label)
            normalizedOptions[normalizedKey] = part
            optionLabels[normalizedKey] = label
          })
        }
      }

      return {
        ...variant,
        normalizedOptions,
        optionLabels,
        variant_image: variant?.variant_image || null,
      }
    })
  })

  const optionGroups = computed(() => {
    const groups = new Map()

    normalizedVariants.value.forEach((variant) => {
      Object.entries(variant.normalizedOptions).forEach(([key, value]) => {
        if (!groups.has(key)) {
          groups.set(key, { key, label: variant.optionLabels[key] || key, values: new Map() })
        }

        const group = groups.get(key)
        if (!group.values.has(value)) {
          group.values.set(value, { value, label: value, image: variant.variant_image || null })
        } else if (!group.values.get(value).image && variant.variant_image) {
          group.values.get(value).image = variant.variant_image
        }
      })
    })

    const scoreGroup = (group) => {
      const label = group.label || group.key
      const hasImages = Array.from(group.values.values()).some((v) => Boolean(v.image))
      if (hasImages) return -30
      if (visualOptionPattern.test(label)) return -20
      if (dropdownOptionPattern.test(label)) return 15
      return 0
    }

    return Array.from(groups.values())
      .map((group) => {
        const values = Array.from(group.values.values())
        const hasImages = values.some((v) => v.image)
        const presentation =
          values.length > 8 || dropdownOptionPattern.test(group.label)
            ? 'dropdown'
            : hasImages
              ? 'image'
              : visualOptionPattern.test(group.label)
                ? 'circle'
                : 'button'

        return { key: group.key, label: group.label, presentation, values }
      })
      .sort((a, b) => scoreGroup(a) - scoreGroup(b))
  })

  const useGroupedVariantPicker = computed(() =>
    optionGroups.value.length > 0 && optionGroups.value.some((g) => g.values.length > 1),
  )

  const resolveVariantState = (desiredSelection = {}) => {
    let candidates = [...normalizedVariants.value]
    const resolvedSelection = {}

    optionGroups.value.forEach((group) => {
      const values = [
        ...new Set(candidates.map((v) => v.normalizedOptions[group.key]).filter(Boolean)),
      ]
      if (!values.length) return

      const desiredValue = desiredSelection[group.key]
      const chosenValue =
        desiredValue && values.includes(desiredValue)
          ? desiredValue
          : choosePreferredValue(group.key, values, candidates)

      if (!chosenValue) return

      resolvedSelection[group.key] = chosenValue
      candidates = candidates.filter((v) => v.normalizedOptions[group.key] === chosenValue)
    })

    return {
      selection: resolvedSelection,
      variant: choosePreferredVariant(candidates, selectedVariantId?.value),
    }
  }

  const getGroupChoices = (groupKey) => {
    const group = optionGroups.value.find((entry) => entry.key === groupKey)
    if (!group) return []

    const baseSelection = { ...selectedOptions.value }
    delete baseSelection[groupKey]

    return group.values.map((choice) => {
      const matchingVariants = normalizedVariants.value.filter((v) =>
        variantMatchesSelection(v, { ...baseSelection, [groupKey]: choice.value }),
      )

      return {
        ...choice,
        disabled: matchingVariants.length === 0,
        outOfStock:
          matchingVariants.length > 0 &&
          !matchingVariants.some((v) => Number(v.stock_on_hand ?? 0) > 0),
        selected: selectedOptions.value[groupKey] === choice.value,
      }
    })
  }

  const updateOptionSelection = (groupKey, value, selectVariantFn) => {
    if (!value) return

    const next = { ...selectedOptions.value, [groupKey]: value }
    const resolved = resolveVariantState(next)
    selectedOptions.value = resolved.selection

    if (resolved.variant && resolved.variant.id !== selectedVariantId?.value) {
      selectVariantFn(resolved.variant.id)
    }
  }

  return {
    normalizedVariants,
    optionGroups,
    useGroupedVariantPicker,
    selectedOptions,
    getGroupChoices,
    updateOptionSelection,
    resolveVariantState,
  }
}
