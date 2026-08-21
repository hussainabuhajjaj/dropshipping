import { computed, shallowRef } from 'vue'

const presets = [
  {
    key: 'back-to-school',
    months: [8, 9],
    badge: 'Back to school',
    title: 'School-ready picks for every budget',
    subtitle: 'Organized essentials, tech accessories, bags, home study finds, and daily deals in one clear shopping path.',
    href: '/products?season=back-to-school',
    primaryLabel: 'Shop school season',
    secondaryLabel: 'Browse essentials',
    theme: 'amber',
    prompts: ['Study setup', 'Bags and storage', 'Daily outfits', 'Tech accessories'],
  },
  {
    key: 'winter',
    months: [12, 1, 2],
    badge: 'Winter edit',
    title: 'Warm, practical picks for colder days',
    subtitle: 'Layering, cozy home upgrades, beauty care, gifting ideas, and fast-moving winter deals.',
    href: '/products?season=winter',
    primaryLabel: 'Shop winter picks',
    secondaryLabel: 'See cozy deals',
    theme: 'blue',
    prompts: ['Cold weather style', 'Cozy home', 'Gift ideas', 'Self care'],
  },
  {
    key: 'summer',
    months: [6, 7, 8],
    badge: 'Summer deals',
    title: 'Easy summer finds for travel, style, and home',
    subtitle: 'Light outfits, travel accessories, beauty staples, outdoor gear, and sunny-day savings.',
    href: '/products?season=summer',
    primaryLabel: 'Shop summer picks',
    secondaryLabel: 'Open travel finds',
    theme: 'emerald',
    prompts: ['Travel ready', 'Light outfits', 'Outdoor picks', 'Beauty refresh'],
  },
  {
    key: 'eid',
    months: [3, 4],
    badge: 'Celebration edit',
    title: 'Fresh gifts, outfits, and home details',
    subtitle: 'A clear seasonal edit for celebrations, hosting, thoughtful gifts, and everyday upgrades.',
    href: '/products?season=celebration',
    primaryLabel: 'Shop celebration picks',
    secondaryLabel: 'Find gifts',
    theme: 'rose',
    prompts: ['Giftable finds', 'Occasion outfits', 'Hosting details', 'Beauty picks'],
  },
]

const evergreen = {
  key: 'evergreen',
  badge: 'This week on Simbazu',
  title: 'Find what you need faster',
  subtitle: 'Shop by season, department, deal type, and customer favorites without digging through the catalog.',
  href: '/products',
  primaryLabel: 'Start shopping',
  secondaryLabel: 'See best sellers',
  theme: 'amber',
  prompts: ['New arrivals', 'Best value', 'Trending now', 'Customer favorites'],
}

export function useSeasonalHomepage() {
  const today = shallowRef(new Date())

  const activeSeason = computed(() => {
    const month = today.value.getMonth() + 1

    return presets.find((preset) => preset.months.includes(month)) || evergreen
  })

  return {
    activeSeason,
  }
}
