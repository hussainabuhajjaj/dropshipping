@php
use Illuminate\Support\Arr;

/**
 * @var array|string|null $state
 */
$payload = Arr::wrap($state);
$url = $payload['url'] ?? null;
$raw = Arr::wrap($payload['raw'] ?? []);
$helper = static function ($value) {
    if (is_string($value)) {
        return $value;
    }

    if (is_array($value) && isset($value['url'])) {
        return $value['url'];
    }

    return null;
};
$images = [];
if ($url) {
    $images[] = $url;
}

foreach (['galleryImages', 'imageList', 'productImageList', 'imgList', 'images', 'productImgs'] as $key) {
    if (empty($raw[$key]) || ! is_array($raw[$key])) {
        continue;
    }

    foreach ($raw[$key] as $entry) {
        $candidate = $helper($entry);
        if ($candidate) {
            $images[] = $candidate;
        }
    }
}

$images = array_values(array_unique(array_filter($images)));
$fallback = 'https://via.placeholder.com/120?text=CJ';
$current = $images[0] ?? $fallback;
@endphp

<div x-data="{ open: false }" class="flex items-center gap-2">
    <button type="button" @click="open = true" class="inline-flex">
        <img src="{{ $current }}" alt="CJ product" class="h-14 w-14 rounded border border-slate-200 object-cover" />
    </button>

    <div
        x-show="open"
        x-cloak
        x-on:keydown.escape.window="open = false"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4"
    >
        <div class="relative max-w-3xl w-full rounded bg-white p-4 shadow-lg">
            <button
                type="button"
                @click="open = false"
                class="absolute right-3 top-3 text-slate-500 hover:text-slate-900"
                aria-label="Close gallery"
            >
                &times;
            </button>

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($images as $image)
                    <div class="overflow-hidden rounded border border-slate-200">
                        <img src="{{ $image }}" alt="CJ product image" class="h-48 w-full object-cover" />
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
