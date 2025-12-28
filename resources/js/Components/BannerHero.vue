<template>
  <div v-if="banner" class="promotional-hero">
    <div
      class="hero-banner-card"
      :style="{
        backgroundColor: banner.backgroundColor || '#1e3a8a',
        color: banner.textColor || '#ffffff'
      }"
    >
      <div class="hero-banner-content">
        <div class="hero-banner-text">
          <span v-if="banner.badgeText" 
            class="hero-badge"
            :style="{ backgroundColor: banner.badgeColor || '#ef4444' }"
          >
            {{ banner.badgeText }}
          </span>
          <h2 class="hero-banner-title">{{ banner.title }}</h2>
          <p v-if="banner.description" class="hero-banner-description">
            {{ banner.description }}
          </p>
          <Link
            v-if="banner.ctaText && banner.ctaUrl"
            :href="banner.ctaUrl"
            class="hero-banner-cta"
          >
            {{ banner.ctaText }}
          </Link>
        </div>
        <div v-if="banner.imagePath" class="hero-banner-image">
          <img :src="banner.imagePath" :alt="banner.title" />
        </div>
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
.promotional-hero {
  margin-bottom: 2rem;
}

.hero-banner-card {
  border-radius: 24px;
  padding: 3rem 2rem;
  overflow: hidden;
  position: relative;
}

.hero-banner-content {
  display: grid;
  gap: 2rem;
  align-items: center;
  grid-template-columns: 1fr;
  position: relative;
  z-index: 1;
}

.hero-banner-text {
  max-width: 600px;
}

.hero-badge {
  display: inline-block;
  padding: 0.5rem 1rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 1rem;
}

.hero-banner-title {
  font-size: 2.5rem;
  font-weight: 700;
  line-height: 1.1;
  margin-bottom: 1rem;
}

.hero-banner-description {
  font-size: 1.125rem;
  opacity: 0.9;
  margin-bottom: 1.5rem;
  line-height: 1.6;
}

.hero-banner-cta {
  display: inline-block;
  padding: 1rem 2rem;
  background: white;
  color: #111827;
  font-weight: 600;
  border-radius: 9999px;
  text-decoration: none;
  transition: transform 0.2s;
}

.hero-banner-cta:hover {
  transform: scale(1.05);
}

.hero-banner-image {
  display: flex;
  justify-content: center;
}

.hero-banner-image img {
  max-width: 100%;
  height: auto;
  border-radius: 16px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

@media (min-width: 768px) {
  .hero-banner-content {
    grid-template-columns: 1fr 1fr;
  }

  .hero-banner-card {
    padding: 4rem 3rem;
  }
}

@media (max-width: 767px) {
  .hero-banner-title {
    font-size: 1.875rem;
  }

  .hero-banner-description {
    font-size: 1rem;
  }
}
</style>
