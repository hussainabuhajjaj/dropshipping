@props(['banner'])

<div class="w-full py-3 px-4 text-center"
     style="background-color: {{ $banner->background_color }}; color: {{ $banner->text_color }};">
    
    <div class="container mx-auto flex items-center justify-center gap-4 flex-wrap">
        @if($banner->badge_text)
            <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold
                {{ $banner->badge_color === 'danger'  ? 'bg-red-500 text-white' : '' }}
                {{ $banner->badge_color === 'warning' ? 'bg-yellow-500 text-white' : '' }}
                {{ $banner->badge_color === 'success' ? 'bg-green-500 text-white' : '' }}
                {{ $banner->badge_color === 'primary' ? 'bg-blue-500 text-white' : '' }}">
                {{ $banner->badge_text }}
            </span>
        @endif
        
        <span class="font-semibold">{{ $banner->title }}</span>
        
        @if($banner->description)
            <span class="text-sm opacity-90">{{ $banner->description }}</span>
        @endif
        
        @if($banner->cta_text && $banner->getCtaUrl())
            <a href="{{ $banner->getCtaUrl() }}" 
               class="text-sm font-semibold underline hover:no-underline">
                {{ $banner->cta_text }} →
            </a>
        @endif
    </div>
</div>
