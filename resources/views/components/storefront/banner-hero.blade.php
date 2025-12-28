@props(['banner'])

<div class="relative overflow-hidden rounded-lg shadow-lg mb-8"
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
                
                <h2 class="text-4xl md:text-6xl font-bold mb-4 text-white">
                    {{ $banner->title }}
                </h2>
                
                @if($banner->description)
                    <p class="text-xl md:text-2xl mb-6 text-white/90">
                        {{ $banner->description }}
                    </p>
                @endif
                
                @if($banner->cta_text && $banner->getCtaUrl())
                    <a href="{{ $banner->getCtaUrl() }}" 
                       class="inline-block px-8 py-4 bg-white text-gray-900 font-semibold rounded-lg hover:bg-gray-100 transition-colors">
                        {{ $banner->cta_text }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
