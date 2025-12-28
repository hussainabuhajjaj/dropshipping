<template>
  <div v-if="banner" class="promotional-strip">
    <div
      class="strip-banner"
      :style="{
        backgroundColor: banner.backgroundColor || '#10b981',
        color: banner.textColor || '#ffffff'
      }"
    >
      <div class="strip-content">
        <div class="strip-text">
          <span v-if="banner.badgeText" class="strip-badge">{{ banner.badgeText }}</span>
          <span class="strip-title">{{ banner.title }}</span>
          <span v-if="banner.description" class="strip-description">{{ banner.description }}</span>
        </div>
        <Link
          v-if="banner.ctaText && banner.ctaUrl"
          :href="banner.ctaUrl"
          class="strip-cta"
        >
          {{ banner.ctaText }}
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  banner: {
    type: Object,
    required: true,
  },
});
</script>

<style scoped>
.promotional-strip {
  margin: 2rem 0;
}

.strip-banner {
  border-radius: 16px;
  padding: 1.5rem 2rem;
}

.strip-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.strip-text {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.strip-badge {
  background: rgba(255, 255, 255, 0.2);
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
}

.strip-title {
  font-weight: 600;
  font-size: 1.125rem;
}

.strip-description {
  opacity: 0.9;
  font-size: 0.875rem;
}

.strip-cta {
  background: white;
  color: #111827;
  padding: 0.625rem 1.5rem;
  border-radius: 9999px;
  font-weight: 600;
  font-size: 0.875rem;
  text-decoration: none;
  white-space: nowrap;
  transition: transform 0.2s;
}

.strip-cta:hover {
  transform: scale(1.05);
}

@media (max-width: 640px) {
  .strip-content {
    justify-content: center;
    text-align: center;
  }

  .strip-text {
    justify-content: center;
  }
}
</style>
