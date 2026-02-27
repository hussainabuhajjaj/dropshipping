<div class="space-y-4">
    @if(empty($images))
        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">No images available for this product.</p>
    @else
        {{-- Main image --}}
        <div class="flex justify-center" x-data="{ activeImage: '{{ $images[0] ?? '' }}' }">
            <div class="space-y-4 w-full">
                {{-- Large preview --}}
                <div class="flex justify-center bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <img
                        :src="activeImage"
                        alt="Product image"
                        class="max-h-[500px] w-auto object-contain rounded-lg cursor-zoom-in transition-all duration-200"
                        @click="window.open(activeImage, '_blank')"
                    />
                </div>

                {{-- Thumbnails --}}
                @if(count($images) > 1)
                    <div class="flex gap-2 overflow-x-auto pb-2 justify-center flex-wrap">
                        @foreach($images as $image)
                            <img
                                src="{{ $image }}"
                                alt="Product thumbnail"
                                class="w-16 h-16 object-cover rounded-md cursor-pointer border-2 transition-all duration-150 hover:opacity-100"
                                :class="activeImage === '{{ $image }}' ? 'border-primary-500 opacity-100' : 'border-gray-200 dark:border-gray-700 opacity-60'"
                                @click="activeImage = '{{ $image }}'"
                            />
                        @endforeach
                    </div>
                @endif

                <p class="text-xs text-gray-400 text-center">{{ count($images) }} image(s) — Click main image to open full size</p>
            </div>
        </div>
    @endif
</div>
