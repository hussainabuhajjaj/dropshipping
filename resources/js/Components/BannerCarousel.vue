<template>
  <div v-if="banners && banners.length" class="promotional-carousel" @mouseenter="pauseAutoplay" @mouseleave="resumeAutoplay">
    <div class="carousel-container">
      <div class="carousel-slides">
        <div
          v-for="(banner, index) in banners"
          :key="banner.id"
          class="carousel-slide"
          :class="[{ active: index === currentIndex, 'is-cover': isCoverMode(banner), 'is-image-only': isImageOnly(banner) }]"
          :style="{
            backgroundColor: banner.backgroundColor || '#ec4899',
            color: banner.textColor || '#ffffff',
          }"
        >
          <div v-if="isCoverMode(banner) && banner.imagePath" class="carousel-cover">
            <img :src="banner.imagePath" :alt="banner.title" />
          </div>
          <div class="carousel-content" :class="{ 'is-cover': isCoverMode(banner), 'is-image-only': isImageOnly(banner) }">
            <div v-if="!isImageOnly(banner)" class="carousel-text">
              <span v-if="banner.badgeText" 
                class="carousel-badge"
                :style="{ backgroundColor: banner.badgeColor || '#ef4444' }"
              >
                {{ banner.badgeText }}
              </span>
              <h3 class="carousel-title">{{ banner.title }}</h3>
              <p v-if="banner.description" class="carousel-description">
                {{ banner.description }}
              </p>
              <Link
                v-if="banner.ctaText && banner.ctaUrl"
                :href="banner.ctaUrl"
                class="carousel-cta"
              >
                {{ banner.ctaText }}
              </Link>
            </div>
            <div v-if="banner.imagePath && !isCoverMode(banner)" class="carousel-image">
              <img :src="banner.imagePath" :alt="banner.title" />
            </div>
          </div>
        </div>
      </div>

      <div v-if="banners.length > 1" class="carousel-controls">
        <button
          type="button"
          class="carousel-arrow"
          @click="previousSlide"
          :aria-label="t('Previous slide')"
        >
          <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>

        <div class="carousel-indicators">
          <button
            v-for="(_, index) in banners"
            :key="index"
            type="button"
            class="carousel-indicator"
            :class="{ active: index === currentIndex }"
            @click="goToSlide(index)"
            :aria-label="`Go to slide ${index + 1}`"
          ></button>
        </div>

        <button
          type="button"
          class="carousel-arrow"
          @click="nextSlide"
          :aria-label="t('Next slide')"
        >
          <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/i18n';

const { t } = useTranslations();

const props = defineProps({
  banners: {
    type: Array,
    required: true,
  },
});

const currentIndex = ref(0);
let autoplayInterval = null;
const isPaused = ref(false);

const nextSlide = () => {
  currentIndex.value = (currentIndex.value + 1) % props.banners.length;
};

const previousSlide = () => {
  currentIndex.value = currentIndex.value === 0 ? props.banners.length - 1 : currentIndex.value - 1;
};

const goToSlide = (index) => {
  currentIndex.value = index;
};

const startAutoplay = () => {
  if (props.banners.length > 1) {
    autoplayInterval = setInterval(() => {
      if (!isPaused.value) {
        nextSlide();
      }
    }, 5000);
  }
};

const stopAutoplay = () => {
  if (autoplayInterval) {
    clearInterval(autoplayInterval);
    autoplayInterval = null;
  }
};

const pauseAutoplay = () => {
  isPaused.value = true;
};

const resumeAutoplay = () => {
  isPaused.value = false;
};

const imageMode = (banner) => banner?.imageMode || banner?.image_mode || 'split';
const isCoverMode = (banner) => imageMode(banner) === 'cover' || imageMode(banner) === 'image_only';
const isImageOnly = (banner) => imageMode(banner) === 'image_only';

onMounted(() => {
  startAutoplay();
});

onBeforeUnmount(() => {
  stopAutoplay();
});
</script>

<style scoped>
.promotional-carousel {
  margin: 2rem 0;
}

.carousel-container {
  position: relative;
  border-radius: 24px;
  overflow: hidden;
}

.carousel-slides {
  position: relative;
  min-height: 350px;
}

.carousel-slide {
  position: absolute;
  inset: 0;
  opacity: 0;
  transition: opacity 0.5s ease;
  pointer-events: none;
  padding: 3rem 2rem;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
}

.carousel-slide.active {
  opacity: 1;
  pointer-events: auto;
}

.carousel-slide.is-cover,
.carousel-slide.is-image-only {
  padding: 0;
  overflow: hidden;
}

.carousel-content {
  display: grid;
  gap: 2rem;
  align-items: center;
  grid-template-columns: 1fr;
  height: 100%;
  position: relative;
  z-index: 2;
}

.carousel-cover {
  position: absolute;
  inset: 0;
  z-index: 0;
}

.carousel-cover img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.carousel-slide.is-image-only .carousel-cover {
  filter: none;
}

.carousel-slide.is-cover::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, rgba(15, 23, 42, 0.72), rgba(15, 23, 42, 0.15));
  z-index: 1;
}

.carousel-slide.is-image-only::before {
  background: transparent;
}

.carousel-content.is-cover {
  grid-template-columns: 1fr;
}

.carousel-content.is-image-only {
  grid-template-columns: 1fr;
}

.carousel-content.is-image-only .carousel-text {
  display: none;
}

.carousel-text {
  max-width: 600px;
}

.carousel-badge {
  display: inline-block;
  padding: 0.5rem 1rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 1rem;
}

.carousel-title {
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.1;
  margin-bottom: 1rem;
}

.carousel-description {
  font-size: 1rem;
  opacity: 0.9;
  margin-bottom: 1.5rem;
  line-height: 1.6;
}

.carousel-cta {
  display: inline-block;
  padding: 0.875rem 1.75rem;
  background: white;
  color: #111827;
  font-weight: 600;
  border-radius: 9999px;
  text-decoration: none;
  transition: transform 0.2s;
}

.carousel-cta:hover {
  transform: scale(1.05);
}

.carousel-image {
  display: flex;
  justify-content: center;
}

.carousel-image img {
  max-width: 100%;
  height: auto;
  max-height: 250px;
  border-radius: 12px;
}

.carousel-controls {
  position: absolute;
  bottom: 1.5rem;
  left: 0;
  right: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  z-index: 10;
}

.carousel-arrow {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 9999px;
  background: rgba(255, 255, 255, 0.9);
  color: #111827;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.carousel-arrow:hover {
  background: white;
  transform: scale(1.1);
}

.carousel-indicators {
  display: flex;
  gap: 0.5rem;
}

.carousel-indicator {
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 9999px;
  background: rgba(255, 255, 255, 0.5);
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.carousel-indicator.active {
  width: 2rem;
  background: white;
}

@media (min-width: 768px) {
  .carousel-content {
    grid-template-columns: 1fr 1fr;
  }

  .carousel-content.is-cover {
    grid-template-columns: 1fr;
  }

  .carousel-slide {
    padding: 4rem 3rem;
  }
}

@media (max-width: 767px) {
  .carousel-title {
    font-size: 1.5rem;
  }

  .carousel-slides {
    min-height: 400px;
  }
}
</style>
