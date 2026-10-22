@props(['banner'])

<div class="rounded-lg shadow-md overflow-hidden mb-4"
     style="background-color: {{ $banner->background_color }}; color: {{ $banner->text_color }};">
    
    @if($banner->image_path)
        <img src="{{ Storage::url($banner->image_path) }}" 
             alt="{{ $banner->title }}" 
             class="w-full h-48 object-cover">
    @endif
    
    <div class="p-4">
        @if($banner->badge_text)
            <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold mb-2
                {{ $banner->badge_color === 'danger' ? 'bg-red-500 text-white' : '' }}
                {{ $banner->badge_color === 'warning' ? 'bg-yellow-500 text-white' : '' }}
                {{ $banner->badge_color === 'success' ? 'bg-green-500 text-white' : '' }}
                {{ $banner->badge_color === 'primary' ? 'bg-blue-500 text-white' : '' }}">
                {{ $banner->badge_text }}
            </span>
        @endif
        
        <h3 class="text-lg font-bold mb-2">{{ $banner->title }}</h3>
        
        @if($banner->description)
            <p class="text-sm mb-3 opacity-90">{{ $banner->description }}</p>
        @endif
        
        @if($banner->cta_text && $banner->getCtaUrl())
            <a href="{{ $banner->getCtaUrl() }}" 
               class="inline-block px-4 py-2 bg-gray-900 text-white text-sm font-semibold rounded hover:bg-gray-800 transition-colors">
                {{ $banner->cta_text }}
            </a>
        @endif
    </div>
</div>
