@props(['banners'])

@if($banners->isNotEmpty())
<div class="relative mb-8" x-data="{ activeSlide: 0, slides: {{ $banners->count() }} }">
    <!-- Carousel Container -->
    <div class="relative overflow-hidden rounded-lg shadow-lg">
        @foreach($banners as $index => $banner)
            <div class="carousel-slide"
                 x-show="activeSlide === {{ $index }}"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform translate-x-full"
                 x-transition:enter-end="opacity-100 transform translate-x-0"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100 transform translate-x-0"
                 x-transition:leave-end="opacity-0 transform -translate-x-full"
                 style="display: none;">
                
                <div class="relative"
                     style="background-color: {{ $banner->background_color }}; color: {{ $banner->text_color }};">
                    
                    @if($banner->image_path)
                        <img src="{{ Storage::url($banner->image_path) }}" 
                             alt="{{ $banner->title }}" 
                             class="w-full h-64 md:h-96 object-cover">
                    @endif
                    
                    <div class="absolute inset-0 bg-gradient-to-r from-black/60 to-transparent flex items-center">
                        <div class="container mx-auto px-4">
                            <div class="max-w-2xl">
                                @if($banner->badge_text)
                                    <span class="inline-block px-4 py-2 rounded-full text-sm font-semibold mb-4
                                        {{ $banner->badge_color === 'danger' ? 'bg-red-500 text-white' : '' }}
                                        {{ $banner->badge_color === 'warning' ? 'bg-yellow-500 text-white' : '' }}
                                        {{ $banner->badge_color === 'success' ? 'bg-green-500 text-white' : '' }}
                                        {{ $banner->badge_color === 'primary' ? 'bg-blue-500 text-white' : '' }}">
                                        {{ $banner->badge_text }}
                                    </span>
                                @endif
                                
                                <h2 class="text-3xl md:text-5xl font-bold mb-4 text-white">
                                    {{ $banner->title }}
                                </h2>
                                
                                @if($banner->description)
                                    <p class="text-lg md:text-xl mb-6 text-white/90">
                                        {{ $banner->description }}
                                    </p>
                                @endif
                                
                                @if($banner->cta_text && $banner->getCtaUrl())
                                    <a href="{{ $banner->getCtaUrl() }}" 
                                       class="inline-block px-6 py-3 bg-white text-gray-900 font-semibold rounded-lg hover:bg-gray-100 transition-colors">
                                        {{ $banner->cta_text }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    
    <!-- Navigation Buttons -->
    @if($banners->count() > 1)
        <button @click="activeSlide = activeSlide === 0 ? slides - 1 : activeSlide - 1"
                class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white text-gray-900 rounded-full p-2 shadow-lg transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
        
        <button @click="activeSlide = activeSlide === slides - 1 ? 0 : activeSlide + 1"
                class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white text-gray-900 rounded-full p-2 shadow-lg transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </button>
        
        <!-- Indicators -->
        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
            @foreach($banners as $index => $banner)
                <button @click="activeSlide = {{ $index }}"
                        class="w-3 h-3 rounded-full transition-colors"
                        :class="activeSlide === {{ $index }} ? 'bg-white' : 'bg-white/50'">
                </button>
            @endforeach
        </div>
    @endif
</div>

<script>
    // Auto-advance carousel every 5 seconds
    setInterval(() => {
        const carousel = document.querySelector('[x-data*="activeSlide"]');
        if (carousel && carousel.__x) {
            carousel.__x.$data.activeSlide = (carousel.__x.$data.activeSlide + 1) % carousel.__x.$data.slides;
        }
    }, 5000);
</script>
@endif
